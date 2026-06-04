<?php

namespace App\Services;

use App\Models\CurriculumSubject;
use App\Models\Department;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Specialty;
use App\Models\Student;
use App\Models\YnStudentGrade;
use App\Models\YnSubmission;
use Illuminate\Support\Facades\DB;

/**
 * YN qaydnoma uchun kutilgan (tizimdagi) ma'lumotni quradi —
 * AI tekshiruvida "haqiqat manbai" sifatida ishlatiladi.
 *
 * Mantiq YnQaytnomaController::generateYnQaydnoma() bilan bir xil
 * (faqat o'qish — mavjud generatsiyaga tegmaydi).
 */
class YnQaydnomaDataService
{
    /**
     * @return array|null Bitta (guruh, fan, semestr) uchun kutilgan ma'lumot.
     */
    public function buildExpectedData(string $groupHemisId, string $subjectId, string $semesterCode): ?array
    {
        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        if (!$group) {
            return null;
        }

        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        $department = Department::where('department_hemis_id', $group->department_hemis_id)
            ->where('structure_type_code', 11)
            ->first();

        $specialty = Specialty::where('specialty_hemis_id', $group->specialty_hemis_id)->first();

        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();
        if (!$subject) {
            return null;
        }

        $submission = YnSubmission::where('group_hemis_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();

        $students = Student::select('full_name', 'student_id_number', 'hemis_id')
            ->where('group_id', $group->group_hemis_id)
            ->groupBy('id')
            ->orderBy('full_name')
            ->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();

        $jn = $mt = [];
        if ($submission) {
            $latest = YnStudentGrade::latestPerStudent($submission->id)->get();
            $jn = $latest->pluck('jn', 'student_hemis_id')->toArray();
            $mt = $latest->pluck('mt', 'student_hemis_id')->toArray();
        }

        $grade = fn(int $code) => DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subject->subject_id)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', $code)
            ->select('student_hemis_id', DB::raw('MAX(grade) as grade'))
            ->groupBy('student_hemis_id')
            ->pluck('grade', 'student_hemis_id')
            ->toArray();

        $on = $grade(100);
        $oski = $grade(101);
        $test = $grade(102);

        // O'qituvchilar
        $maruza = DB::table('student_grades as s')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
            ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
            ->where('s.subject_id', $subject->subject_id)
            ->where('s.training_type_code', 11)
            ->whereIn('s.student_hemis_id', $studentHemisIds)
            ->groupBy('s.employee_id')
            ->first();

        $others = DB::table('student_grades as s')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
            ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
            ->where('s.subject_id', $subject->subject_id)
            ->where('s.training_type_code', '!=', 11)
            ->whereNotIn('s.training_type_code', [99, 100, 101, 102, 103])
            ->whereIn('s.student_hemis_id', $studentHemisIds)
            ->groupBy('s.employee_id')
            ->get();
        $otherNames = [];
        foreach ($others as $t) {
            foreach (explode(', ', (string) $t->full_names) as $name) {
                $name = trim($name);
                if ($name && !in_array($name, $otherNames, true)) {
                    $otherNames[] = $name;
                }
            }
        }

        $studentRows = [];
        foreach ($students as $i => $stu) {
            $hid = $stu->hemis_id;
            $studentRows[] = [
                'n' => $i + 1,
                'fish' => $stu->full_name,
                'student_id' => $stu->student_id_number,
                'jn' => $jn[$hid] ?? null,
                'mt' => $mt[$hid] ?? null,
                'on' => $on[$hid] ?? null,
                'oski' => $oski[$hid] ?? null,
                'test' => $test[$hid] ?? null,
            ];
        }

        return [
            'fakultet' => $department->name ?? null,
            // O'quv yilini diapazon ko'rinishida: "2025" -> "2025-2026" (vedomostdagidek).
            'oquv_yili' => $this->formatEducationYear($semester->education_year ?? null),
            'yonalish' => $specialty->name ?? null,
            'fan' => $subject->subject_name,
            'maruzachi' => $maruza->full_names ?? null,
            'amaliyot_oqituvchilari' => implode(', ', $otherNames),
            'umumiy_soat' => $subject->total_acload,
            'kredit' => $subject->credit,
            'yn_sanasi' => optional($submission?->exam_date)->format('d.m.Y'),
            // Kurs/semestr — inson o'qiydigan nom (level_name="3-kurs", name="6-semestr"),
            // ichki kod (13/16) emas — aks holda AI "13 vs 3-kurs" deb noto'g'ri belgilaydi.
            'kurs' => $semester->level_name ?? $semester->level_code ?? null,
            'semestr' => $semester->name ?? $semester->code ?? null,
            'guruh' => $group->name,
            'jami_talabalar' => count($studentRows),
            'talabalar' => $studentRows,
        ];
    }

    /**
     * "2025" -> "2025-2026". Allaqachon diapazon yoki raqamsiz bo'lsa — o'zgartirmaydi.
     */
    private function formatEducationYear($year): ?string
    {
        if ($year === null || $year === '') {
            return null;
        }
        $year = (string) $year;
        if (str_contains($year, '-')) {
            return $year;
        }
        if (ctype_digit($year)) {
            return $year . '-' . ((int) $year + 1);
        }

        return $year;
    }
}
