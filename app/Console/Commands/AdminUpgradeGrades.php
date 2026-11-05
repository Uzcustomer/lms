<?php

namespace App\Console\Commands;

use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ro'yxatdagi guruh + fan + sana oralig'idagi kunlik (mavzu) baholarni formula bo'yicha oshiradi:
 *   60–69.9  → eski + 10
 *   70–89.9  → eski + (90 − eski) / 2
 *   90–100   → eski + (100 − eski) / 2
 * 60 dan past, NB, ma'ruza/MT/nazoratlar va otrabotka qilingan (retake_grade bor) baholarga tegilmaydi.
 *
 * Yozuvda: grade = yangi baho, upgraded_by_admin = true, admin_original_grade = eski baho.
 * HEMIS importi bunday baholarni qayta yozmaydi; jurnalda katak to'q sariq, hover'da eski/yangi baho.
 * Formula har doim eski bahoga qo'llanadi — qayta ishga tushirish bahoni ikki marta oshirmaydi.
 *
 * Ro'yxat — CSV (;): guruh;fan;boshlanish;tugash (sana d.m.Y). Sukut bo'yicha faqat hisobot,
 * yozish — --force; --revert — eski baholarni qaytaradi.
 */
class AdminUpgradeGrades extends Command
{
    protected $signature = 'grades:admin-upgrade
        {file=database/data/grade-upgrade-1-davolash.csv : Ro\'yxat (guruh;fan;boshlanish;tugash)}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : Oshirilgan baholarni eski holiga qaytarish}';

    protected $description = "Ro'yxatdagi guruh/fanlarning kunlik baholarini formula bo'yicha oshirish (admin)";

    /** Formula; null — o'zgarmaydi (60 dan past). */
    public static function upgradedGrade(float $old): ?float
    {
        if ($old < 60) {
            return null;
        }
        $new = match (true) {
            $old < 70 => $old + 10,
            $old < 90 => $old + (90 - $old) / 2,
            default => $old + (100 - $old) / 2,
        };

        return round(min($new, 100), 2);
    }

    public function handle(): int
    {
        if (!Schema::hasColumn('student_grades', 'upgraded_by_admin')) {
            $this->error("Avval migratsiya: php artisan migrate");

            return self::FAILURE;
        }

        $path = base_path($this->argument('file'));
        if (!is_file($path)) {
            $this->error("Fayl topilmadi: {$path}");

            return self::FAILURE;
        }

        $force = (bool) $this->option('force');
        $revert = (bool) $this->option('revert');
        $limit = MarkingSystemScore::lowGradeLimit();
        $deadlineDays = Deadline::pluck('deadline_days', 'level_code');
        $excluded = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        $table = [];
        $samples = [];
        $total = 0;
        foreach ($this->readList($path) as [$groupName, $subject, $from, $to]) {
            // Ro'yxatdagi guruhning faqat kichik guruhlari: d1/d22-01 → d1/d22-01(a), d1/d22-01 (b) ... (o'zi emas)
            $groups = DB::table('groups')
                ->where(fn ($q) => $q->where('name', 'like', $groupName . '(%')
                    ->orWhere('name', 'like', $groupName . ' (%'))
                ->get(['group_hemis_id', 'name']);
            if ($groups->isEmpty()) {
                $table[] = [$groupName, $subject, 'GURUH TOPILMADI', '', '', '', ''];
                continue;
            }
            $groupName = $groups->pluck('name')->sort()->implode(', ');

            $students = DB::table('students')->whereIn('group_id', $groups->pluck('group_hemis_id'))->pluck('level_code', 'hemis_id');
            $rows = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $students->keys()->map(fn ($id) => (string) $id)->all() ?: ['0'])
                ->whereRaw('LOWER(TRIM(subject_name)) = ?', [mb_strtolower($subject)])
                ->where('lesson_date', '>=', $from->copy()->startOfDay())
                ->where('lesson_date', '<=', $to->copy()->endOfDay())
                ->whereNotIn('training_type_code', $excluded)
                ->whereNotNull('grade')
                ->get(['id', 'student_hemis_id', 'lesson_date', 'grade', 'retake_grade', 'reason', 'status',
                    'upgraded_by_admin', 'admin_original_grade']);

            if ($rows->isEmpty()) {
                $names = DB::table('student_grades')->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $students->keys()->map(fn ($id) => (string) $id)->all() ?: ['0'])
                    ->whereBetween('lesson_date', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
                    ->distinct()->pluck('subject_name')->implode(' | ');
                $table[] = [$groupName, $subject, 'BAHO TOPILMADI', '', '', '', $names ? "bor fanlar: {$names}" : ''];
                continue;
            }

            $changed = $below = $retake = $same = 0;
            foreach ($rows as $r) {
                if ($revert) {
                    if (!$r->upgraded_by_admin) {
                        continue;
                    }
                    $old = (float) $r->admin_original_grade;
                    $update = ['grade' => $old, 'upgraded_by_admin' => false, 'admin_original_grade' => null, 'admin_upgraded_at' => null];
                    if ($old < $limit && $r->reason === null && $r->retake_grade === null) {
                        $update += ['reason' => 'low_grade', 'status' => 'pending',
                            'deadline' => Carbon::parse($r->lesson_date)->addDays((int) ($deadlineDays[$students[$r->student_hemis_id] ?? ''] ?? 7))->endOfDay()];
                    }
                    $changed++;
                    $samples[] = [$groupName, $r->student_hemis_id, substr($r->lesson_date, 0, 10), self::num($r->grade), self::num($old)];
                } else {
                    // Otrabotka qilingan bahoga tegilmaydi (jurnal otrabotka bahosini ko'rsatadi)
                    if ($r->retake_grade !== null) {
                        $retake++;
                        continue;
                    }
                    $old = $r->upgraded_by_admin ? (float) $r->admin_original_grade : (float) $r->grade;
                    $new = self::upgradedGrade($old);
                    if ($new === null) {
                        $below++;
                        continue;
                    }
                    if ($r->upgraded_by_admin && abs((float) $r->grade - $new) < 0.001) {
                        $same++;
                        continue;
                    }
                    $update = ['grade' => $new, 'upgraded_by_admin' => true, 'admin_original_grade' => $old, 'admin_upgraded_at' => now()];
                    // Yangi baho chegaradan o'tsa — otrabotka qarzi yopiladi
                    if ($r->reason === 'low_grade' && $new >= $limit) {
                        $update += ['reason' => null, 'status' => 'recorded', 'deadline' => null];
                    }
                    $changed++;
                    $samples[] = [$groupName, $r->student_hemis_id, substr($r->lesson_date, 0, 10), self::num($old), self::num($new)];
                }

                if ($force) {
                    DB::table('student_grades')->where('id', $r->id)->update($update + ['updated_at' => now()]);
                }
            }

            $total += $changed;
            $table[] = [$groupName, $subject, $rows->count(), $changed, $below, $retake, $same ? "allaqachon: {$same}" : ''];
        }

        $this->table(['Guruh', 'Fan', 'Baholar', $revert ? 'Qaytariladi' : 'Oshiriladi', '<60', 'Otrabotka', 'Izoh'], $table);
        if ($samples) {
            $this->line('Namunalar:');
            $this->table(['Guruh', 'Talaba', 'Sana', $revert ? 'Hozir' : 'Eski', $revert ? 'Qaytadi' : 'Yangi'], array_slice($samples, 0, 15));
        }

        $verb = $revert ? 'qaytarildi' : 'oshirildi';
        if ($force) {
            $this->info("Jami {$verb}: {$total} ta baho.");
        } else {
            $this->info("[hisobot] Jami " . ($revert ? 'qaytariladi' : 'oshiriladi') . ": {$total} ta baho.");
            $this->comment('Bu faqat hisobot. Yozish uchun: php artisan grades:admin-upgrade ' . $this->argument('file') . ($revert ? ' --revert' : '') . ' --force');
        }

        return self::SUCCESS;
    }

    /** @return array<int, array{0: string, 1: string, 2: Carbon, 3: Carbon}> */
    private function readList(string $path): array
    {
        $list = [];
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $i => $line) {
            $cols = array_map('trim', str_getcsv(ltrim($line, "\u{FEFF}"), ';'));
            if ($i === 0 && mb_strtolower($cols[0]) === 'guruh') {
                continue;
            }
            if (count($cols) < 4) {
                continue;
            }
            $list[] = [$cols[0], $cols[1], Carbon::createFromFormat('d.m.Y', $cols[2]), Carbon::createFromFormat('d.m.Y', $cols[3])];
        }

        return $list;
    }

    private static function num($v): string
    {
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
