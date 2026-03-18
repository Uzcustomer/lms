<?php

namespace App\Console\Commands;

use App\Models\AbsenceExcuseMakeup;
use App\Models\Student;
use App\Models\StudentSubject;
use App\Models\CurriculumSubject;
use App\Services\SubjectMatcherService;
use Illuminate\Console\Command;

class FixAbsenceExcuseSubjects extends Command
{
    protected $signature = 'absence:fix-subjects
        {--dry-run : Faqat ko\'rsatish, o\'zgartirmaslik}
        {--min-similarity=50 : Minimal o\'xshashlik foizi (default: 50)}
        {--debug : Diagnostika ma\'lumotlarini ko\'rsatish}';

    protected $description = 'Ariza makeuplarida subject_id ni student_subjects/curriculum_subjects orqali fuzzy match bilan tuzatish';

    public function handle()
    {
        $dryRun = $this->option('dry-run');
        $minSimilarity = (float) $this->option('min-similarity');
        $debug = $this->option('debug');

        $this->info($dryRun ? '=== DRY RUN rejimi ===' : '=== TUZATISH rejimi ===');
        $this->newLine();

        // Barcha makeuplarni olish (subject_id bo'sh yoki student_subjects da yo'q)
        $makeups = AbsenceExcuseMakeup::with(['absenceExcuse'])
            ->orderBy('absence_excuse_id')
            ->get();

        $this->info("Jami makeuplar: {$makeups->count()} ta");
        $this->newLine();

        $fixed = 0;
        $notFound = 0;
        $alreadyCorrect = 0;
        $noStudent = 0;

        $headers = ['ariza_id', 'hemis_id', 'talaba', 'guruh', 'fan', 'baholash_turi', 'holat', 'hemis_subject_id'];
        $rows = [];

        // Studentlarni cache qilish
        $studentCache = [];
        $subjectCache = [];
        $debugShown = [];

        foreach ($makeups as $makeup) {
            $excuse = $makeup->absenceExcuse;
            if (!$excuse) {
                continue;
            }

            $hemisId = $excuse->student_hemis_id;

            // Student ni cache dan olish
            if (!isset($studentCache[$hemisId])) {
                $studentCache[$hemisId] = Student::where('hemis_id', $hemisId)->first();
            }
            $student = $studentCache[$hemisId];

            if (!$student) {
                $noStudent++;
                continue;
            }

            // Student subjects ni cache dan olish
            if (!isset($subjectCache[$hemisId])) {
                $subjectCache[$hemisId] = SubjectMatcherService::getStudentSubjects($student);
            }
            $studentSubjects = $subjectCache[$hemisId];

            if ($debug && !isset($debugShown[$hemisId])) {
                $debugShown[$hemisId] = true;
                $ssCount = StudentSubject::where('student_hemis_id', $hemisId)->count();
                $ssSemCount = $student->semester_id
                    ? StudentSubject::where('student_hemis_id', $hemisId)->where('semester_id', $student->semester_id)->count()
                    : 0;
                $csCount = $student->curriculum_id
                    ? CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)->active()->count()
                    : 0;
                $csSemCount = ($student->curriculum_id && $student->semester_code)
                    ? CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)->active()->where('semester_code', $student->semester_code)->count()
                    : 0;

                $this->info("  [{$hemisId}] {$excuse->student_full_name}");
                $this->info("    semester_id={$student->semester_id}, semester_code={$student->semester_code}, curriculum_id={$student->curriculum_id}");
                $this->info("    student_subjects: {$ssCount} (semestr bilan: {$ssSemCount})");
                $this->info("    curriculum_subjects: {$csCount} (semestr bilan: {$csSemCount})");
                $this->info("    getStudentSubjects natijasi: {$studentSubjects->count()} ta");
                if ($studentSubjects->isNotEmpty()) {
                    foreach ($studentSubjects->take(5) as $ss) {
                        $this->info("      - [{$ss->subject_id}] {$ss->subject_name}");
                    }
                    if ($studentSubjects->count() > 5) {
                        $this->info("      ... va yana " . ($studentSubjects->count() - 5) . " ta");
                    }
                }
                $this->newLine();
            }

            if ($studentSubjects->isEmpty()) {
                $rows[] = [
                    $excuse->id,
                    $hemisId,
                    $excuse->student_full_name,
                    $excuse->group_name,
                    $makeup->subject_name,
                    $makeup->assessment_type,
                    'FANLARI YO\'Q',
                    $makeup->subject_id ?? '-',
                ];
                $notFound++;
                continue;
            }

            // Match qilish
            $match = SubjectMatcherService::resolveSubjectId(
                $makeup->subject_name,
                $makeup->subject_id,
                $student
            );

            if (!$match) {
                $rows[] = [
                    $excuse->id,
                    $hemisId,
                    $excuse->student_full_name,
                    $excuse->group_name,
                    $makeup->subject_name,
                    $makeup->assessment_type,
                    'TOPILMADI',
                    $makeup->subject_id ?? '-',
                ];
                $notFound++;
                continue;
            }

            if ($match['match_type'] === 'exact_id') {
                $alreadyCorrect++;
                continue;
            }

            // Similarity tekshirish (fuzzy uchun)
            if ($match['match_type'] === 'fuzzy' && ($match['similarity'] ?? 0) < $minSimilarity) {
                $rows[] = [
                    $excuse->id,
                    $hemisId,
                    $excuse->student_full_name,
                    $excuse->group_name,
                    $makeup->subject_name . ' → ' . $match['subject_name'] . " ({$match['similarity']}%)",
                    $makeup->assessment_type,
                    'KAM_OXSHASH',
                    $makeup->subject_id ?? '-',
                ];
                $notFound++;
                continue;
            }

            $matchLabel = $match['match_type'] === 'fuzzy'
                ? "FUZZY ({$match['similarity']}%)"
                : 'EXACT_NAME';

            $rows[] = [
                $excuse->id,
                $hemisId,
                $excuse->student_full_name,
                $excuse->group_name,
                $makeup->subject_name . ' → ' . $match['subject_name'],
                $makeup->assessment_type,
                $matchLabel,
                $match['subject_id'],
            ];

            if (!$dryRun) {
                $makeup->update(['subject_id' => $match['subject_id']]);
            }
            $fixed++;
        }

        if (!empty($rows)) {
            $this->table($headers, $rows);
        }

        $this->newLine();
        $this->info("Natijalar:");
        $this->info("  Allaqachon to'g'ri: {$alreadyCorrect} ta");
        $this->info("  Tuzatildi" . ($dryRun ? ' (tuzatilar edi)' : '') . ": {$fixed} ta");
        $this->warn("  Topilmadi: {$notFound} ta");
        if ($noStudent > 0) {
            $this->warn("  Talaba topilmadi: {$noStudent} ta");
        }

        if ($dryRun && $fixed > 0) {
            $this->newLine();
            $this->info("Haqiqiy tuzatish uchun --dry-run ni olib tashlang:");
            $this->info("  php artisan absence:fix-subjects");
        }

        return 0;
    }
}
