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

        $students = Student::select('full_name', 'student_id_number', 'hemis_id', 'student_status_code', 'student_status_name')
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

        // YN hali yuborilmagan (snapshot yo'q) talabalar uchun — jurnaldagi tirik
        // JN/MT (AcademicScheduleController bilan bir xil tartib: snapshot ustun).
        // Aks holda AI tekshiruv har talabada "tizimda yo'q" deb soxta farq chiqaradi.
        $needLive = array_filter($studentHemisIds, fn ($hid) => ($jn[$hid] ?? null) === null || ($mt[$hid] ?? null) === null);
        if (!empty($needLive)) {
            try {
                $live = \App\Services\JournalGradeService::computeJnMtBulk(
                    [[(string) $group->group_hemis_id, (string) $subject->subject_id, (string) $semesterCode]],
                    array_fill_keys(array_map('strval', $needLive), (string) $group->group_hemis_id)
                );
                $key = $group->group_hemis_id . '|' . $subject->subject_id . '|' . $semesterCode;
                foreach ($live[$key] ?? [] as $hid => $vals) {
                    if (($jn[$hid] ?? null) === null && $vals['jn'] !== null) {
                        $jn[$hid] = $vals['jn'];
                    }
                    if (($mt[$hid] ?? null) === null && $vals['mt'] !== null) {
                        $mt[$hid] = $vals['mt'];
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('YnQaydnomaDataService live JN/MT failed: ' . $e->getMessage());
            }
        }

        // ON, OSKI, Test — jurnaldagi AYNAN bir xil tanlash mantig'i (MAX EMAS):
        // is_qoshimcha=0, education_year/minScheduleDate oynasi, attempt=1,
        // effectiveGrade va soxta 'sinov_yn_test' qatorini chetlatish.
        $onOskiTest = \App\Services\JournalGradeService::computeOnOskiTest(
            (string) $groupHemisId,
            (string) $subject->subject_id,
            (string) $semesterCode,
            $studentHemisIds
        );
        $on = $onOskiTest['on'];
        $oski = $onOskiTest['oski'];
        $test = $onOskiTest['test'];

        // O'qituvchilar
        $maruza = DB::table('student_grades as s')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
            ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
            ->where('s.subject_id', $subject->subject_id)
            ->where('s.training_type_code', 11)
            ->whereIn('s.student_hemis_id', $studentHemisIds)
            ->groupBy('s.employee_id')
            ->first();

        // Amaliyot o'qituvchisi — guruhcha uchun BITTA (jurnal logikasi: dars jadvalida
        // eng ko'p dars o'tgan). Ma'ruza (11) va baho turlari (99-103) hisobga olinmaydi.
        $excludedTrainingCodes = [11, 99, 100, 101, 102, 103];
        $subjectKeys = array_values(array_filter([
            $subjectId,
            $subject->curriculum_subject_hemis_id ?? null,
        ]));
        $practiceTeacher = DB::table('schedules')
            ->whereNull('deleted_at')
            ->where('group_id', $group->group_hemis_id)
            ->whereIn('subject_id', $subjectKeys)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('employee_name')
            ->where('employee_name', '!=', 'Manual Entry')
            ->where('employee_name', '!=', '')
            ->select('employee_name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('employee_name')
            ->orderByDesc('cnt')
            ->value('employee_name');

        // Har talaba uchun FOIZ (%) qiymatlari — tizim haqiqati. Ball/o'zlashtirish
        // og'irliklarni talab qiladi; og'irliklar esa har vedomostda PDF sarlavhasida
        // ko'rsatilgan (farq qilishi mumkin), shuning uchun ballni AI skanerdan o'qigan
        // og'irliklar bilan hisoblaydi. Bu yerda faqat foizlarni beramiz.
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
                'hemis_id' => (string) $hid,
                'status_code' => (string) ($stu->student_status_code ?? ''),
                'status_name' => (string) ($stu->student_status_name ?? ''),
            ];
        }

        return [
            'fakultet' => $department->name ?? null,
            // O'quv yilini diapazon ko'rinishida: "2025" -> "2025-2026" (vedomostdagidek).
            'oquv_yili' => $this->formatEducationYear($semester->education_year ?? null),
            'yonalish' => $specialty->name ?? null,
            'fan' => $subject->subject_name,
            'yopilish_shakli' => $subject->closing_form,
            'maruzachi' => $maruza->full_names ?? null,
            'amaliyot_oqituvchilari' => $practiceTeacher ?: '',
            'umumiy_soat' => $subject->total_acload,
            'kredit' => $subject->credit,
            'yn_sanasi' => optional($submission?->exam_date)->format('d.m.Y'),
            // Kurs/semestr — inson o'qiydigan nom (level_name="3-kurs", name="6-semestr"),
            // ichki kod (13/16) emas — aks holda AI "13 vs 3-kurs" deb noto'g'ri belgilaydi.
            'kurs' => $semester->level_name ?? $semester->level_code ?? null,
            'level_code' => $semester->level_code ?? null,
            'semestr' => $semester->name ?? $semester->code ?? null,
            'guruh' => $group->name,
            'subject_id' => (string) $subject->subject_id,
            'dekan' => $this->dekanName($department?->department_hemis_id),
            'jami_talabalar' => count($studentRows),
            'talabalar' => $studentRows,
        ];
    }

    /**
     * Fakultet dekani (KtrController tasdiqlovchilar bilan bir xil tartib):
     * dekan roli + dean_faculties → role ustuni → HEMIS lavozimi.
     */
    private function dekanName($facultyHemisId): ?string
    {
        if (!$facultyHemisId) {
            return null;
        }
        try {
            $q = fn () => \App\Models\Teacher::where('is_active', true);
            $dekan = null;
            if (\Illuminate\Support\Facades\Schema::hasTable('dean_faculties')) {
                $dekan = $q()->whereHas('roles', fn ($r) => $r->where('name', 'dekan'))
                    ->whereHas('deanFaculties', fn ($r) => $r->where('dean_faculties.department_hemis_id', $facultyHemisId))
                    ->first();
            }
            $dekan ??= $q()->where('role', 'dekan')->where('department_hemis_id', $facultyHemisId)->first();
            $dekan ??= $q()->where('staff_position', 'LIKE', '%ekan%')->where('department_hemis_id', $facultyHemisId)->first();

            return $dekan?->full_name;
        } catch (\Throwable $e) {
            return null;
        }
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
