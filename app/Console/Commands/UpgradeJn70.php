<?php

namespace App\Console\Commands;

use App\Http\Controllers\Admin\JournalController;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "JN 70%": YN imtihonlari chegara sanadan (sukut bo'yicha 26.10.2026) oldin tugagan fanda (o'tish bali 60)
 * OSKI yoki testdan o'tmagan talaba — qatnashmagan yoki 60 dan past olgan — keyingi urinishni chegara sanadan
 * keyin topshiradi (12-qo'shimcha / sababli, 12a, 12b). Unga JN kunlik baholar grades:admin-upgrade formulasi
 * bo'yicha oshirib hisoblanadi (60 dan pasti — o'zgarmaydi; asl JN 60 dan past talabaga umuman tegilmaydi) va
 * jn_upgrades ga yoziladi: jurnalda "JN 70%" ustuni, qaydnomaning keyingi urinishlar varag'ida shu JN va o'tish
 * chegarasi 70. Asosiy 12-shakl, asl baholar va JN o'zgarmaydi. Sukut bo'yicha — faqat hisobot; yozish — --force;
 * bekor qilish — --revert.
 *
 *   php artisan grades:jn70                       hisobot (barcha guruhlar)
 *   php artisan grades:jn70 d1/d22-01 --force     yozish (kichik guruhlari a, b, c bilan)
 *   php artisan grades:jn70 --revert --force      hammasini bekor qilish
 */
class UpgradeJn70 extends Command
{
    protected $signature = 'grades:jn70
        {groups?* : Faqat shu guruhlar, masalan d1/d22-01 (kichik guruhlari a, b, c olinadi)}
        {--cutoff=2026-10-26 : Chegara sana — YN imtihonlari shundan oldin tugagan fanlar}
        {--since=2026-09-01 : O\'quv yili boshi — undan oldingi imtihonlar olinmaydi}
        {--force : Haqiqatan yozish (bo\'lmasa faqat hisobot)}
        {--revert : "JN 70%" ni bekor qilish (yozuvlar o\'chiriladi; asl JN o\'zi o\'zgarmagan)}';

    /** Asl JN shundan past bo'lsa — "JN 70%" yozilmaydi (oshirilmaydi) */
    private const MIN_JN = 60;

    /** Chegara sanadan oldingi o'tish bali — OSKI / testdan shundan past olgan talaba ham keyingi urinishga qoladi */
    private const OLD_PASS = 60;

    protected $description = "\"JN 70%\": 26.10 dan oldin tugagan fanda OSKI / testdan o'tmagan (qatnashmagan yoki 60 dan past) talabalarning keyingi urinishlari uchun oshirilgan JN";

    public function handle(): int
    {
        if (!Schema::hasTable('jn_upgrades')) {
            $this->error('Avval migratsiya: php artisan migrate');

            return self::FAILURE;
        }
        $force = (bool) $this->option('force');
        $groupIds = $this->groupIds();
        if ($groupIds === []) {
            $this->warn('Guruh topilmadi');

            return self::FAILURE;
        }

        if ($this->option('revert')) {
            $q = fn () => DB::table('jn_upgrades')->when($groupIds !== null, fn ($q) => $q->whereIn('group_hemis_id', $groupIds));
            $count = $q()->count();
            if ($force) {
                $touched = $q()->distinct()->get(['group_hemis_id', 'subject_id', 'semester_code'])->all();
                $q()->delete();
                $this->forgetFormsCache($touched);
            }
            $this->info(($force ? "Bekor qilindi: " : "Bekor qilinadi: ") . $count . ' ta "JN 70%"');
            if (!$force) {
                $this->warn('Faqat hisobot. Bajarish uchun: --revert --force');
            }

            return self::SUCCESS;
        }

        $cutoff = Carbon::parse((string) $this->option('cutoff'))->toDateString();
        $since = Carbon::parse((string) $this->option('since'))->toDateString();
        $journal = app(JournalController::class);
        $rows = [];
        $write = [];
        $skipped = 0;
        $stale = [];   // avval yozilgan, endi tegilmaydiganlar (asl JN 60 dan past) — o'chiriladi

        $vedomosts = $this->vedomosts($groupIds, $cutoff, $since);
        $this->info("Imtihonlari {$since} — {$cutoff} oralig'ida tugagan guruh × fanlar: " . $vedomosts->count()
            . " (qatnashmaganlari borlarida JN jurnaldagidek hisoblanadi — bir necha daqiqa olishi mumkin)");
        $started = microtime(true);
        foreach ($vedomosts as $i => $v) {
            $absent = $this->failedStudents($v);
            if ($absent->isEmpty()) {
                continue;
            }
            $this->line(sprintf('  [%d/%d] %s · %s — o\'tmagan / qatnashmagan: %d', $i + 1, $vedomosts->count(), $v->group_name,
                mb_substr($v->subject_name, 0, 40), $absent->count()));
            $plain = $journal->ynqJnMaps($v->subject_id, $v->semester_code, $v->group_hemis_id, false);
            $up = $journal->ynqJnMaps($v->subject_id, $v->semester_code, $v->group_hemis_id, true);
            if (is_string($plain) || is_string($up)) {
                $this->warn("{$v->group_name} · {$v->subject_name}: " . (is_string($plain) ? $plain : $up));
                continue;
            }
            foreach ($absent as $s) {
                // Keyingi urinishlar JN i — sababli bahosi bilan (V2)
                $jn = (int) round((float) ($plain[1][$s->hemis_id] ?? 0));
                $jn70 = (int) round((float) ($up[1][$s->hemis_id] ?? 0));
                // Asl JN 60 dan past — tegilmaydi (oshirilmaydi)
                if ($jn < self::MIN_JN) {
                    $skipped++;
                    $stale[] = [(string) $s->hemis_id, (string) $v->subject_id, (string) $v->semester_code];
                    continue;
                }
                $rows[] = [$v->group_name, mb_substr($v->subject_name, 0, 34), $s->full_name, Carbon::parse($v->exam_date)->format('d.m.Y'), $jn, $jn70];
                $write[] = [
                    'student_hemis_id' => (string) $s->hemis_id, 'group_hemis_id' => (string) $v->group_hemis_id,
                    'subject_id' => (string) $v->subject_id, 'semester_code' => (string) $v->semester_code,
                    'jn_original' => $jn, 'jn_upgraded' => $jn70, 'created_by' => 'grades:jn70',
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }

        $this->table(['Guruh', 'Fan', 'Talaba', 'Imtihon', 'JN', 'JN 70%'], $rows);
        $this->line('Vaqt: ' . round(microtime(true) - $started) . ' s');
        $this->info('Talabalar: ' . count($write) . ($skipped ? ' · asl JN ' . self::MIN_JN . " dan past — tegilmadi: {$skipped}" : ''));
        if (!$force) {
            $this->warn('Faqat hisobot. Yozish uchun: --force (bekor qilish: --revert --force)');

            return self::SUCCESS;
        }
        foreach ($stale as [$student, $subject, $semester]) {
            DB::table('jn_upgrades')->where('student_hemis_id', $student)->where('subject_id', $subject)->where('semester_code', $semester)->delete();
        }
        foreach (array_chunk($write, 200) as $chunk) {
            DB::table('jn_upgrades')->upsert($chunk, ['student_hemis_id', 'subject_id', 'semester_code'],
                ['group_hemis_id', 'jn_original', 'jn_upgraded', 'created_by', 'updated_at']);
        }
        $this->forgetFormsCache(array_map(fn ($w) => (object) $w, $write));
        $this->info('Yozildi: ' . count($write) . ' ta "JN 70%"');

        return self::SUCCESS;
    }

    /** Argumentdagi guruhlar (kichik guruhlari bilan) HEMIS id lari; argument yo'q — null (hammasi) */
    private function groupIds(): ?array
    {
        $names = array_filter((array) $this->argument('groups'));
        if (!$names) {
            return null;
        }

        return DB::table('groups')->where(function ($q) use ($names) {
            foreach ($names as $n) {
                $q->orWhere('name', 'like', $n . '%');
            }
        })->pluck('group_hemis_id')->map(fn ($v) => (string) $v)->all();
    }

    /** YN imtihonlari [since, cutoff) oralig'ida tugagan guruh × fanlar, yopuvchi imtihon turi va sanasi bilan */
    private function vedomosts(?array $groupIds, string $cutoff, string $since)
    {
        return DB::table('exam_schedules as es')
            ->join('groups as g', 'g.group_hemis_id', '=', 'es.group_hemis_id')
            ->leftJoin('curriculum_subjects as cs', function ($j) {
                $j->on('cs.curricula_hemis_id', '=', 'g.curriculum_hemis_id')->on('cs.subject_id', '=', 'es.subject_id')
                    ->on('cs.semester_code', '=', 'es.semester_code');
            })
            ->whereNull('es.student_hemis_id')
            ->when($groupIds !== null, fn ($q) => $q->whereIn('es.group_hemis_id', $groupIds))
            ->get(['es.group_hemis_id', 'es.subject_id', 'es.semester_code', 'es.oski_date', 'es.test_date', 'es.oski_na', 'es.test_na',
                'g.name as group_name', 'cs.subject_name', 'cs.closing_form'])
            ->map(function ($v) {
                $oski = $v->oski_na ? null : $v->oski_date;
                $test = $v->test_na ? null : $v->test_date;
                // Fanning YN imtihonlari: OSKI va / yoki test ("yo'q" deb belgilanmaganlari)
                $v->exam_types = array_values(array_filter(['oski' => $oski ? 'oski' : null, 'test' => $test ? 'test' : null]));
                // Ikkala sana ham yo'q (yoki "yo'q" deb belgilangan) jadval qatori — o'tkazib yuboriladi
                $dates = array_filter([(string) $oski, (string) $test]);
                $v->exam_date = $dates ? max($dates) : null;
                $v->subject_name = $v->subject_name ?? ('Fan #' . $v->subject_id);

                return $v;
            })
            ->filter(fn ($v) => $v->exam_types && $v->exam_date
                && substr($v->exam_date, 0, 10) < $cutoff && substr($v->exam_date, 0, 10) >= $since)
            ->values();
    }

    /**
     * Asosiy urinishda fanning YN imtihonlaridan (OSKI va / yoki test) birortasidan o'tmagan talabalar:
     * qatnashmagan (bahosi yo'q yoki "kelmagan") yoki eski chegaradan (60) past olgan — ular keyingi urinishni
     * chegara sanadan keyin topshiradi. Sababli bahosi (retake_grade) hisobga olinmaydi.
     */
    private function failedStudents(object $v)
    {
        $rows = DB::table('student_grades as sg')
            ->leftJoin('hemis_quiz_results as q', 'q.id', '=', 'sg.quiz_result_id')
            ->whereNull('sg.deleted_at')
            ->where('sg.subject_id', $v->subject_id)->where('sg.semester_code', $v->semester_code)
            ->whereIn('sg.training_type_code', [101, 102, 103])
            ->when(Schema::hasColumn('student_grades', 'attempt'), fn ($q) => $q->where(fn ($w) => $w->where('sg.attempt', 1)->orWhereNull('sg.attempt')))
            ->whereNotNull('sg.grade')
            ->where(fn ($q) => $q->whereNull('sg.reason')->orWhere('sg.reason', '!=', 'absent'))
            ->get(['sg.student_hemis_id', 'sg.training_type_code', 'sg.grade', 'q.quiz_type']);
        // Har talaba bo'yicha imtihon turi → eng yuqori baho (quiz natijasi — turi bo'yicha OSKI yoki test)
        $best = [];
        foreach ($rows as $r) {
            $type = match ((int) $r->training_type_code) {
                101 => 'oski',
                102 => 'test',
                default => str_contains(mb_strtolower((string) $r->quiz_type), 'oski') ? 'oski' : 'test',
            };
            $key = (string) $r->student_hemis_id;
            $best[$key][$type] = max($best[$key][$type] ?? 0, (float) $r->grade);
        }
        $passed = fn (string $h) => collect($v->exam_types)->every(fn ($t) => ($best[$h][$t] ?? -1) >= self::OLD_PASS);

        return DB::table('students')->where('group_id', $v->group_hemis_id)
            ->where(fn ($q) => $q->whereNull('student_status_code')->orWhere('student_status_code', '!=', '60'))
            ->orderBy('full_name')->get(['hemis_id', 'full_name'])
            ->reject(fn ($s) => $passed((string) $s->hemis_id))
            ->values();
    }

    /** Vedomostlarimdagi shakllar hisobi keshi (JournalController::ynqFormsByMember) — yangi JN bilan qayta hisoblansin */
    private function forgetFormsCache(array $vedomosts): void
    {
        $done = [];
        foreach ($vedomosts as $v) {
            $members = \App\Services\YnqFamily::members((string) $v->subject_id, (string) $v->semester_code, (string) $v->group_hemis_id);
            $key = 'ynq_forms_by_member:' . $v->subject_id . ':' . $v->semester_code . ':' . $members[0];
            if (!isset($done[$key])) {
                \Illuminate\Support\Facades\Cache::forget($key);
                $done[$key] = true;
            }
        }
    }
}
