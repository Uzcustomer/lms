<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubject;
use App\Models\SinovTestGrade;
use App\Models\YnSubmission;
use App\Services\JnMtCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostika + tuzatish:
 *   - Eski JN hisoblashda training_type_name filtri yo'qligi sababli
 *     sinov_test_grades.override_grade noto'g'ri hisoblangan bo'lishi mumkin.
 *   - default_grade == override_grade  → dastur avtomatik o'rnatgan → tuzatiladi
 *   - default_grade != override_grade  → o'qituvchi qo'lda edit qilgan → TEGMAYDI
 *   - is_locked = true AND YN yuborilgan → faqat ko'rsatiladi, tuzatilmaydi
 */
class FixSinovJnMismatch extends Command
{
    protected $signature = 'sinov:fix-jn-mismatch
        {--dry-run : Faqat ko\'rib chiqish, DB ga yozmaslik}
        {--group= : Faqat shu guruh uchun (group_hemis_id)}
        {--subject= : Faqat shu fan uchun (subject_id)}';

    protected $description = 'sinov_test_grades da JN o\'rtachasi bilan mos kelmaydigan (va qo\'lda edit qilinmagan) yozuvlarni tuzatadi.';

    public function handle(): int
    {
        $dryRun  = (bool) $this->option('dry-run');
        $filterGroup   = $this->option('group');
        $filterSubject = $this->option('subject');

        if ($dryRun) {
            $this->warn('DRY-RUN rejim — DB ga hech narsa yozilmaydi.');
        }

        // Yopilish shakli 'sinov' bo'lgan fanlar
        $sinovSubjectIds = CurriculumSubject::where('closing_form', 'sinov')
            ->when($filterSubject, fn($q) => $q->where('subject_id', $filterSubject))
            ->distinct()
            ->pluck('subject_id')
            ->all();

        if (empty($sinovSubjectIds)) {
            $this->warn('Sinov fan topilmadi.');
            return self::SUCCESS;
        }

        // YN yuborilgan guruh+fan+semestr'lar
        $submissions = YnSubmission::whereIn('subject_id', $sinovSubjectIds)
            ->when($filterGroup,   fn($q) => $q->where('group_hemis_id', $filterGroup))
            ->select('subject_id', 'semester_code', 'group_hemis_id')
            ->distinct()
            ->get();

        if ($submissions->isEmpty()) {
            $this->warn('Mos YN submission topilmadi.');
            return self::SUCCESS;
        }

        $calculator = new JnMtCalculator();

        $rows = []; // table output uchun

        $fixedCount    = 0;
        $skippedManual = 0;
        $skippedLocked = 0;
        $noChange      = 0;

        foreach ($submissions as $sub) {
            $jnMap = $calculator->computeForGroup(
                $sub->group_hemis_id,
                (int) $sub->subject_id,
                $sub->semester_code
            );

            if (empty($jnMap)) {
                continue;
            }

            $sinovRows = SinovTestGrade::where('subject_id', $sub->subject_id)
                ->where('semester_code', $sub->semester_code)
                ->where('group_hemis_id', $sub->group_hemis_id)
                ->get()
                ->keyBy('student_hemis_id');

            // Talaba ismlarini olish
            $studentNames = DB::table('students')
                ->where('group_id', $sub->group_hemis_id)
                ->pluck('full_name', 'hemis_id');

            // Guruh nomi
            $groupName = DB::table('groups')
                ->where('group_hemis_id', $sub->group_hemis_id)
                ->value('name') ?? $sub->group_hemis_id;

            // Fan nomi
            $subjectName = DB::table('curriculum_subjects')
                ->where('subject_id', $sub->subject_id)
                ->where('semester_code', $sub->semester_code)
                ->value('subject_name') ?? $sub->subject_id;

            foreach ($jnMap as $hemisId => $correctJn) {
                $sinovRow = $sinovRows[$hemisId] ?? null;
                if (!$sinovRow) {
                    continue; // yozuv yo'q — backfill boshqa komanda
                }

                $storedDefault  = (int) round((float) $sinovRow->default_grade);
                $storedOverride = (int) round((float) $sinovRow->override_grade);
                $isLocked       = (bool) $sinovRow->is_locked;
                $correctJnInt   = (int) $correctJn;

                if ($storedDefault === $correctJnInt && $storedOverride === $correctJnInt) {
                    $noChange++;
                    continue;
                }

                $studentName = $studentNames[$hemisId] ?? $hemisId;

                // O'qituvchi qo'lda edit qilgan (override != default)
                if ($storedDefault !== $storedOverride) {
                    $skippedManual++;
                    $rows[] = [
                        $groupName,
                        $subjectName,
                        $studentName,
                        $storedDefault,
                        $storedOverride,
                        $correctJnInt,
                        $isLocked ? 'HA' : 'YO\'Q',
                        '<-- QOLDIRILDI (qo\'lda edit)',
                    ];
                    continue;
                }

                // Qulflangan (jurnalga ko'chirilgan) — faqat xabar berish
                if ($isLocked) {
                    $skippedLocked++;
                    $rows[] = [
                        $groupName,
                        $subjectName,
                        $studentName,
                        $storedDefault,
                        $storedOverride,
                        $correctJnInt,
                        'HA',
                        '<-- DIQQAT: qulflangan, tuzatilmadi',
                    ];
                    continue;
                }

                // Avtomatik o'rnatilgan va to'g'ri emas → tuzatish
                $rows[] = [
                    $groupName,
                    $subjectName,
                    $studentName,
                    $storedDefault,
                    $storedOverride,
                    $correctJnInt,
                    'YO\'Q',
                    $dryRun ? '[DRY] tuzatiladi' : 'TUZATILDI',
                ];

                if (!$dryRun) {
                    $sinovRow->default_grade  = $correctJnInt;
                    $sinovRow->override_grade = $correctJnInt;
                    $sinovRow->save();

                    // student_grades sinov_yn_test yozuvini ham yangilash
                    DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->where('student_hemis_id', $hemisId)
                        ->where('subject_id', $sub->subject_id)
                        ->where('semester_code', $sub->semester_code)
                        ->where('training_type_code', 102)
                        ->where('reason', 'sinov_yn_test')
                        ->update(['grade' => $correctJnInt, 'updated_at' => now()]);
                }

                $fixedCount++;
            }
        }

        if (!empty($rows)) {
            $this->table(
                ['Guruh', 'Fan', 'Talaba', 'Eski default', 'Eski override', 'To\'g\'ri JN', 'Qulflangan', 'Holat'],
                $rows
            );
        } else {
            $this->info('Hech qanday farqli yozuv topilmadi.');
        }

        $this->newLine();
        $this->info("Tuzatildi:         {$fixedCount}");
        $this->warn("Qulflangan (DIQQAT): {$skippedLocked}");
        $this->warn("Qo'lda edit (skip):  {$skippedManual}");
        $this->line("O'zgarmagan:       {$noChange}");

        if ($skippedLocked > 0) {
            $this->newLine();
            $this->warn('DIQQAT: Qulflangan yozuvlar YN ga yuborilgan va jurnalda saqlanib qolgan.');
            $this->warn('Ularni tuzatish uchun admin panelida "Sinov baholarini o\'zgartirish" toggle\'ini yoqib, qo\'lda to\'g\'rilash kerak.');
        }

        return self::SUCCESS;
    }
}
