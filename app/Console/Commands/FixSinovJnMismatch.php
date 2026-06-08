<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\SinovTestGrade;
use App\Models\YnSubmission;
use App\Services\JnMtCalculator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Diagnostika + tuzatish:
 *   - Eski JN hisoblashda training_type_name filtri yo'qligi sababli
 *     sinov_test_grades.override_grade noto'g'ri hisoblangan bo'lishi mumkin.
 *
 *   Eligibility (bulkCopySinovFromJn bilan bir xil):
 *     grade = (JN >= minLimit AND MT >= minLimit AND sababsiz davomat < 25%) ? JN : 0
 *
 *   Xavfsizlik qoidalari:
 *   - default_grade == override_grade  → avtomatik o'rnatilgan → tuzatiladi
 *   - default_grade != override_grade  → o'qituvchi qo'lda edit qilgan → HECH QACHON TEGMAYDI
 *   - is_locked = true                 → --fix-locked bilan tuzatiladi
 */
class FixSinovJnMismatch extends Command
{
    protected $signature = 'sinov:fix-jn-mismatch
        {--dry-run    : Faqat ko\'rib chiqish, DB ga yozmaslik}
        {--fix-locked : Qulflangan lekin avtomatik o\'rnatilgan yozuvlarni ham tuzatish}
        {--group=     : Faqat shu guruh uchun (group_hemis_id)}
        {--subject=   : Faqat shu fan uchun (subject_id)}
        {--export=    : Natijani CSV faylga saqlash (masalan: --export=sinov.csv)}';

    protected $description = 'sinov_test_grades da JN o\'rtachasi bilan mos kelmaydigan va qo\'lda edit qilinmagan yozuvlarni tuzatadi.';

    public function handle(): int
    {
        $dryRun      = (bool) $this->option('dry-run');
        $fixLocked   = (bool) $this->option('fix-locked');
        $filterGroup   = $this->option('group');
        $filterSubject = $this->option('subject');

        if ($dryRun) {
            $this->warn('DRY-RUN rejim — DB ga hech narsa yozilmaydi.');
        }
        if ($fixLocked) {
            $this->warn('--fix-locked: qulflangan avtomatik yozuvlar ham tuzatiladi.');
        }

        $sinovSubjectIds = CurriculumSubject::where('closing_form', 'sinov')
            ->when($filterSubject, fn($q) => $q->where('subject_id', $filterSubject))
            ->distinct()
            ->pluck('subject_id')
            ->all();

        if (empty($sinovSubjectIds)) {
            $this->warn('Sinov fan topilmadi.');
            return self::SUCCESS;
        }

        // yn_submissions ba'zi sinov fanlarida o'chirilgan bo'lishi mumkin,
        // shuning uchun sinov_test_grades dan to'g'ridan-to'g'ri olamiz.
        $submissions = SinovTestGrade::whereIn('subject_id', $sinovSubjectIds)
            ->when($filterGroup, fn($q) => $q->where('group_hemis_id', $filterGroup))
            ->select('subject_id', 'semester_code', 'group_hemis_id')
            ->distinct()
            ->get();

        if ($submissions->isEmpty()) {
            $this->warn('Mos sinov_test_grades yozuvi topilmadi.');
            return self::SUCCESS;
        }

        $calculator = new JnMtCalculator();
        $rows        = [];
        $fixedCount    = 0;
        $dryFixCount   = 0;
        $skippedManual = 0;
        $skippedLocked = 0;
        $noChange      = 0;

        foreach ($submissions as $sub) {
            // JN va MT ni birga olamiz
            $jnMtMap = $calculator->computeForGroup(
                (string) $sub->group_hemis_id,
                (int) $sub->subject_id,
                (string) $sub->semester_code
            );

            if (empty($jnMtMap)) {
                continue;
            }

            // Sababsiz davomat foizini hisoblash (bulkCopySinovFromJn mantig'i)
            $absPctMap = $this->computeAbsence(
                (string) $sub->subject_id,
                (string) $sub->semester_code,
                (string) $sub->group_hemis_id
            );

            $sinovRows = SinovTestGrade::where('subject_id', $sub->subject_id)
                ->where('semester_code', $sub->semester_code)
                ->where('group_hemis_id', $sub->group_hemis_id)
                ->get()
                ->keyBy('student_hemis_id');

            $studentNames = DB::table('students')
                ->where('group_id', $sub->group_hemis_id)
                ->pluck('full_name', 'hemis_id');

            $groupName = DB::table('groups')
                ->where('group_hemis_id', $sub->group_hemis_id)
                ->value('name') ?? $sub->group_hemis_id;

            $subjectName = DB::table('curriculum_subjects')
                ->where('subject_id', $sub->subject_id)
                ->where('semester_code', $sub->semester_code)
                ->value('subject_name') ?? $sub->subject_id;

            foreach ($jnMtMap as $hemisId => $jnMt) {
                $jnInt  = (int) ($jnMt['jn'] ?? 0);
                $mtInt  = (int) ($jnMt['mt'] ?? 0);
                $absPct = (float) ($absPctMap[$hemisId] ?? 0);

                $minLimit = (int) (MarkingSystemScore::getByStudentHemisId($hemisId)->minimum_limit ?? 60);
                $eligible = ($jnInt >= $minLimit) && ($mtInt >= $minLimit) && ($absPct < 25.0);
                $correctGrade = $eligible ? $jnInt : 0;

                $sinovRow = $sinovRows[$hemisId] ?? null;
                if (!$sinovRow) {
                    continue;
                }

                $storedDefault  = (int) round((float) $sinovRow->default_grade);
                $storedOverride = (int) round((float) $sinovRow->override_grade);
                $isLocked       = (bool) $sinovRow->is_locked;
                $studentName    = $studentNames[$hemisId] ?? $hemisId;

                // O'zgarmagan
                if ($storedDefault === $correctGrade && $storedOverride === $correctGrade) {
                    $noChange++;
                    continue;
                }

                // O'qituvchi qo'lda edit qilgan → hech qachon tegmaymiz
                if ($storedDefault !== $storedOverride) {
                    $skippedManual++;
                    $rows[] = [
                        $groupName, $subjectName, $studentName,
                        $storedDefault, $storedOverride, $correctGrade,
                        $eligible ? "JN={$jnInt} MT={$mtInt}" : "JN={$jnInt} MT={$mtInt} abs={$absPct}%",
                        $isLocked ? 'HA' : 'YO\'Q',
                        'QOLDIRILDI (qo\'lda edit)',
                    ];
                    continue;
                }

                // default == override (avtomatik), qulflangan
                if ($isLocked) {
                    if ($fixLocked) {
                        $rows[] = [
                            $groupName, $subjectName, $studentName,
                            $storedDefault, $storedOverride, $correctGrade,
                            $eligible ? "JN={$jnInt} MT={$mtInt}" : "JN={$jnInt} MT={$mtInt} abs={$absPct}%",
                            'HA',
                            $dryRun ? '[DRY] TUZATILADI (locked)' : 'TUZATILDI (locked)',
                        ];
                        $dryFixCount++;
                        if (!$dryRun) {
                            $sinovRow->default_grade  = $correctGrade;
                            $sinovRow->override_grade = $correctGrade;
                            $sinovRow->save();
                            DB::table('student_grades')
                                ->whereNull('deleted_at')
                                ->where('student_hemis_id', $hemisId)
                                ->where('subject_id', $sub->subject_id)
                                ->where('semester_code', $sub->semester_code)
                                ->where('training_type_code', 102)
                                ->where('reason', 'sinov_yn_test')
                                ->update(['grade' => $correctGrade, 'updated_at' => now()]);
                            $fixedCount++;
                        }
                    } else {
                        $skippedLocked++;
                        $rows[] = [
                            $groupName, $subjectName, $studentName,
                            $storedDefault, $storedOverride, $correctGrade,
                            $eligible ? "JN={$jnInt} MT={$mtInt}" : "JN={$jnInt} MT={$mtInt} abs={$absPct}%",
                            'HA',
                            'DIQQAT: qulflangan (--fix-locked bilan tuzating)',
                        ];
                    }
                    continue;
                }

                // Qulflanmagan, avtomatik → tuzatamiz
                $rows[] = [
                    $groupName, $subjectName, $studentName,
                    $storedDefault, $storedOverride, $correctGrade,
                    $eligible ? "JN={$jnInt} MT={$mtInt}" : "JN={$jnInt} MT={$mtInt} abs={$absPct}%",
                    'YO\'Q',
                    $dryRun ? '[DRY] TUZATILADI' : 'TUZATILDI',
                ];
                $dryFixCount++;
                if (!$dryRun) {
                    $sinovRow->default_grade  = $correctGrade;
                    $sinovRow->override_grade = $correctGrade;
                    $sinovRow->save();
                    DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->where('student_hemis_id', $hemisId)
                        ->where('subject_id', $sub->subject_id)
                        ->where('semester_code', $sub->semester_code)
                        ->where('training_type_code', 102)
                        ->where('reason', 'sinov_yn_test')
                        ->update(['grade' => $correctGrade, 'updated_at' => now()]);
                    $fixedCount++;
                }
            }
        }

        $headers = ['Guruh', 'Fan', 'Talaba', 'Eski', 'Override', 'To\'g\'ri', 'Eligibility', 'Qulf', 'Holat'];

        if (!empty($rows)) {
            $exportFile = $this->option('export');
            if ($exportFile) {
                $fp = fopen($exportFile, 'w');
                fprintf($fp, "\xEF\xBB\xBF"); // UTF-8 BOM for Excel
                fputcsv($fp, $headers, ';');
                foreach ($rows as $row) {
                    fputcsv($fp, $row, ';');
                }
                fclose($fp);
                $this->info("CSV saqlandi: {$exportFile}");
            } else {
                $this->table($headers, $rows);
            }
        } else {
            $this->info('Hech qanday farqli yozuv topilmadi — hammasi to\'g\'ri.');
        }

        $this->newLine();
        if ($dryRun) {
            $this->info("Tuzatiladi (dry-run): {$dryFixCount}");
        } else {
            $this->info("Tuzatildi:            {$fixedCount}");
        }
        $this->warn("Qulflangan (skip):    {$skippedLocked}");
        $this->warn("Qo'lda edit (skip):   {$skippedManual}");
        $this->line("O'zgarmagan:          {$noChange}");

        if ($skippedLocked > 0 && !$fixLocked) {
            $this->newLine();
            $this->warn("Qulflangan lekin avtomatik o'rnatilgan {$skippedLocked} ta yozuv topildi.");
            $this->warn('Ularni tuzatish uchun: php artisan sinov:fix-jn-mismatch --fix-locked --dry-run');
            $this->warn('Ko\'rib chiqqandan keyin: php artisan sinov:fix-jn-mismatch --fix-locked');
        }

        return self::SUCCESS;
    }

    /**
     * bulkCopySinovFromJn::computeAbsenceForGroup bilan bir xil mantiq.
     * hemis_id => sababsiz davomat foizi (0..100)
     */
    private function computeAbsence(string $subjectId, string $semesterCode, string $groupHemisId): array
    {
        $studentHemisIds = DB::table('students')
            ->where('group_id', $groupHemisId)
            ->pluck('hemis_id')
            ->toArray();

        if (empty($studentHemisIds)) {
            return [];
        }

        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        $auditoriumHours = 0.0;
        if ($group) {
            $subj = DB::table('curriculum_subjects')
                ->where('subject_id', $subjectId)
                ->where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('semester_code', $semesterCode)
                ->value('total_acload');
            $auditoriumHours = (float) ($subj ?? 0);
        }

        $absences = DB::table('attendances')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->selectRaw('student_hemis_id, SUM(absent_off) as total_absent_off')
            ->groupBy('student_hemis_id')
            ->pluck('total_absent_off', 'student_hemis_id')
            ->toArray();

        $result = [];
        foreach ($studentHemisIds as $hemisId) {
            $absent = (float) ($absences[$hemisId] ?? 0);
            $result[$hemisId] = $auditoriumHours > 0
                ? round(($absent / $auditoriumHours) * 100, 2)
                : 0.0;
        }

        return $result;
    }
}
