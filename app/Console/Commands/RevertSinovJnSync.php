<?php

namespace App\Console\Commands;

use App\Models\MarkingSystemScore;
use App\Models\SinovTestGrade;
use App\Services\JnMtCalculator;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * sinov:fix-jn-mismatch ning baholar ko'tarilgandan keyingi ishini qaytaradi: Sinov (test) bahosi
 * yangi (ko'tarilgan) JN ga tenglangan yozuvlar ko'tarishdan oldingi JN ga qaytariladi.
 *
 * Eski qiymat saqlanmagan — u ko'tarilgan baholarning asl qiymati (admin_original_grade) bilan
 * qayta hisoblanadi; bu avvalgi avtomatik Sinov bahosining aynan o'zi (o'sha qoida: JN, MT ≥
 * chegara va sababsiz davomat < 25% bo'lsa JN, aks holda 0).
 *
 * Faqat: --date kuni o'zgargan, avtomatik (o'qituvchi qo'lda o'zgartirmagan) va hozir yangi JN ga
 * teng yozuvlar. Farqi ko'tarishdan bo'lmagan yozuvlar tiklanmaydi (eski qiymati yo'q).
 * Sukut bo'yicha faqat hisobot; o'zgartirish --force bilan.
 */
class RevertSinovJnSync extends Command
{
    protected $signature = 'sinov:revert-jn-sync
        {--date= : sinov:fix-jn-mismatch ishlatilgan kun (Y-m-d), sukut — bugun}
        {--group= : Faqat shu guruh (group_hemis_id)}
        {--force : Haqiqatan qaytarish (bo\'lmasa faqat hisobot)}';

    protected $description = "Sinov (test) baholarini baholar ko'tarilishidan oldingi JN ga qaytarish";

    public function handle(): int
    {
        $date = $this->option('date') ?: Carbon::today()->toDateString();
        $force = (bool) $this->option('force');
        $calculator = new JnMtCalculator();
        $fixer = new FixSinovJnMismatch();

        $changed = SinovTestGrade::whereDate('updated_at', $date)
            ->whereColumn('default_grade', 'override_grade')
            ->when($this->option('group'), fn ($q, $g) => $q->where('group_hemis_id', $g))
            ->get()
            ->groupBy(fn ($r) => $r->subject_id . '|' . $r->semester_code . '|' . $r->group_hemis_id);

        $rows = [];
        $reverted = 0;
        $notUpgrade = 0;
        $alreadyOther = 0;
        foreach ($changed as $items) {
            $first = $items->first();
            [$subjectId, $semesterCode, $groupHemisId] = [(int) $first->subject_id, (string) $first->semester_code, (string) $first->group_hemis_id];
            $now = $calculator->computeForGroup($groupHemisId, $subjectId, $semesterCode);
            $before = $calculator->computeForGroup($groupHemisId, $subjectId, $semesterCode, true);
            $absence = $fixer->computeAbsence((string) $subjectId, $semesterCode, $groupHemisId);
            $groupName = DB::table('groups')->where('group_hemis_id', $groupHemisId)->value('name') ?? $groupHemisId;
            $subjectName = DB::table('curriculum_subjects')->where('subject_id', $subjectId)->where('semester_code', $semesterCode)
                ->value('subject_name') ?? $subjectId;

            foreach ($items as $row) {
                $hemisId = $row->student_hemis_id;
                if (!isset($now[$hemisId], $before[$hemisId])) {
                    continue;
                }
                $limit = (int) (MarkingSystemScore::getByStudentHemisId($hemisId)->minimum_limit ?? 60);
                $abs = (float) ($absence[$hemisId] ?? 0);
                $grade = fn (array $jnMt) => ($jnMt['jn'] >= $limit && $jnMt['mt'] >= $limit && $abs < 25.0) ? (int) $jnMt['jn'] : 0;
                $current = (int) round((float) $row->override_grade);
                $newGrade = $grade($now[$hemisId]);
                $oldGrade = $grade($before[$hemisId]);

                if ($current !== $newGrade) {
                    $alreadyOther++;   // keyin boshqa yo'l bilan o'zgargan — tegilmaydi
                    continue;
                }
                if ($oldGrade === $newGrade) {
                    $notUpgrade++;     // farq ko'tarishdan emas — eski qiymati noma'lum
                    continue;
                }

                $rows[] = [$groupName, $subjectName, DB::table('students')->where('hemis_id', $hemisId)->value('full_name') ?? $hemisId,
                    $current, $oldGrade, $force ? 'QAYTARILDI' : 'qaytariladi'];
                $reverted++;
                if ($force) {
                    $row->default_grade = $oldGrade;
                    $row->override_grade = $oldGrade;
                    $row->save();
                    DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->where('student_hemis_id', $hemisId)
                        ->where('subject_id', $subjectId)
                        ->where('semester_code', $semesterCode)
                        ->where('training_type_code', 102)
                        ->where('reason', 'sinov_yn_test')
                        ->update(['grade' => $oldGrade, 'updated_at' => now()]);
                }
            }
        }

        if ($rows) {
            $this->table(['Guruh', 'Fan', 'Talaba', 'Hozir (yangi JN)', 'Qaytadi (eski JN)', 'Holat'], $rows);
        }
        $this->newLine();
        $this->info(($force ? 'Qaytarildi: ' : '[hisobot] Qaytariladi: ') . $reverted);
        $this->line("Ko'tarish bilan bog'liq emas (tegilmaydi): {$notUpgrade}");
        $this->line("Keyin boshqacha o'zgargan (tegilmaydi): {$alreadyOther}");
        if (!$force) {
            $this->comment('Bu faqat hisobot. Qaytarish uchun: php artisan sinov:revert-jn-sync --date=' . $date . ' --force');
        }

        return self::SUCCESS;
    }
}
