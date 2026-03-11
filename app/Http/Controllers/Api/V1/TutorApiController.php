<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Group;
use App\Models\Student;
use App\Models\Attendance;
use App\Models\StudentGrade;
use App\Models\AcademicRecord;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TutorApiController extends Controller
{
    /**
     * Tyutorga biriktirilgan guruhlar ro'yxati (group_teacher orqali)
     */
    public function groups(Request $request): JsonResponse
    {
        $tutor = $request->user();

        $groups = $tutor->groups()
            ->where('active', true)
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => $groups->map(fn($g) => [
                'id'                  => $g->id,
                'group_hemis_id'      => $g->group_hemis_id,
                'name'                => $g->name,
                'department_name'     => $g->department_name ?? null,
                'specialty_name'      => $g->specialty_name ?? null,
                'education_lang_name' => $g->education_lang_name ?? null,
                'students_count'      => Student::where('group_id', $g->group_hemis_id)->count(),
            ])->values(),
        ]);
    }

    /**
     * Guruh talabalari ro'yxati
     * GET /api/v1/tutor/groups/{groupId}/students
     */
    public function groupStudents(Request $request, int $groupId): JsonResponse
    {
        $tutor = $request->user();

        // Tyutor faqat o'ziga biriktirilgan guruhlarni ko'rishi mumkin
        $group = $tutor->groups()->where('groups.id', $groupId)->first();
        if (!$group) {
            return response()->json(['message' => 'Guruh topilmadi yoki sizga biriktirilmagan.'], 404);
        }

        $search = $request->input('search');

        $students = Student::where('group_id', $group->group_hemis_id)
            ->when($search, fn($q) => $q->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            }))
            ->orderBy('full_name')
            ->get();

        return response()->json([
            'data' => [
                'group' => [
                    'id'             => $group->id,
                    'group_hemis_id' => $group->group_hemis_id,
                    'name'           => $group->name,
                    'department_name'=> $group->department_name,
                    'specialty_name' => $group->specialty_name,
                ],
                'students' => $students->map(fn($s) => [
                    'id'               => $s->id,
                    'hemis_id'         => $s->hemis_id,
                    'full_name'        => $s->full_name,
                    'student_id_number'=> $s->student_id_number,
                    'image'            => $s->image,
                    'birth_date'       => $s->birth_date?->format('Y-m-d'),
                    'gender'           => $s->gender,
                    'phone'            => $s->phone,
                    'avg_gpa'          => $s->avg_gpa,
                    'avg_grade'        => $s->avg_grade,
                    'semester_name'    => $s->semester_name,
                    'payment_form_name'=> $s->payment_form_name,
                    'student_status_name' => $s->student_status_name,
                    'province_name'    => $s->province_name,
                    'district_name'    => $s->district_name,
                ])->values(),
            ],
        ]);
    }

    /**
     * Talabaning akademik yozuvlari (tyutor uchun)
     * GET /api/v1/tutor/students/{studentId}/academic-records
     */
    public function studentAcademicRecords(Request $request, int $studentId): JsonResponse
    {
        $tutor = $request->user();

        $student = Student::find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Talaba topilmadi.'], 404);
        }

        // Tyutor faqat o'z guruhidagi talabalarni ko'rishi mumkin
        $hasAccess = $tutor->groups()
            ->where('group_hemis_id', $student->group_id)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Ushbu talabaga ruxsatingiz yo\'q.'], 403);
        }

        $records = AcademicRecord::where('student_id', $student->hemis_id)
            ->orderBy('semester_name')
            ->orderBy('subject_name')
            ->get()
            ->map(fn($r) => [
                'id'                   => $r->id,
                'semester_name'        => $r->semester_name,
                'subject_name'         => $r->subject_name,
                'employee_name'        => $r->employee_name,
                'credit'               => $r->credit,
                'total_acload'         => $r->total_acload,
                'total_point'          => $r->total_point,
                'grade'                => $r->grade,
                'finish_credit_status' => $r->finish_credit_status,
                'retraining_status'    => $r->retraining_status,
            ]);

        return response()->json([
            'data' => [
                'student' => [
                    'id'                => $student->id,
                    'hemis_id'          => $student->hemis_id,
                    'full_name'         => $student->full_name,
                    'student_id_number' => $student->student_id_number,
                    'group_name'        => $student->group_name,
                    'specialty_name'    => $student->specialty_name,
                    'semester_name'     => $student->semester_name,
                    'avg_gpa'           => $student->avg_gpa,
                    'avg_grade'         => $student->avg_grade,
                    'total_credit'      => $student->total_credit,
                ],
                'academic_records'       => $records->values(),
                'academic_records_count' => $records->count(),
            ],
        ]);
    }

    /**
     * Talaba profili (tyutor uchun)
     * GET /api/v1/tutor/students/{studentId}
     */
    public function studentProfile(Request $request, int $studentId): JsonResponse
    {
        $tutor = $request->user();

        $student = Student::find($studentId);
        if (!$student) {
            return response()->json(['message' => 'Talaba topilmadi.'], 404);
        }

        // Tyutor faqat o'z guruhidagi talabalarni ko'rishi mumkin
        $hasAccess = $tutor->groups()
            ->where('group_hemis_id', $student->group_id)
            ->exists();

        if (!$hasAccess) {
            return response()->json(['message' => 'Ushbu talabaga ruxsatingiz yo\'q.'], 403);
        }

        // Kursni hisoblash
        $course = null;
        if ($student->semester_name && preg_match('/(\d+)/', $student->semester_name, $matches)) {
            $semNum = (int) $matches[1];
            $course = (int) ceil($semNum / 2);
        }
        if (!$course && $student->year_of_enter) {
            $enterYear = (int) $student->year_of_enter;
            $currentMonth = (int) date('m');
            $currentYear = (int) date('Y');
            $course = $currentMonth >= 9
                ? $currentYear - $enterYear + 1
                : $currentYear - $enterYear;
        }

        // Davomatlar (jami soni)
        $totalAbsent = Attendance::where('student_id', $student->id)->count();

        // Qarzdor fanlar
        $debtCount = AcademicRecord::where('student_id', $student->hemis_id)
            ->where(function ($q) {
                $q->whereNull('grade')
                  ->orWhereIn('grade', ['2', '0'])
                  ->orWhere('retraining_status', true);
            })
            ->when($student->semester_id, fn($q) => $q->where('semester_id', '!=', $student->semester_id))
            ->count();

        // So'ngi baholar
        $recentGrades = StudentGrade::where('student_id', $student->id)
            ->where('status', 'recorded')
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->map(fn($g) => [
                'subject_name'       => $g->subject_name,
                'grade'              => $g->grade,
                'lesson_date'        => $g->lesson_date,
                'training_type_name' => $g->training_type_name,
                'employee_name'      => $g->employee_name,
            ]);

        return response()->json([
            'data' => [
                'id'                  => $student->id,
                'hemis_id'            => $student->hemis_id,
                'full_name'           => $student->full_name,
                'student_id_number'   => $student->student_id_number,
                'image'               => $student->image,
                'birth_date'          => $student->birth_date?->format('Y-m-d'),
                'gender'              => $student->gender,
                'phone'               => $student->phone ?? '',
                'hemis_phone'         => $student->other['phone'] ?? '',
                'email'               => $student->other['email'] ?? '',
                'telegram_username'   => $student->telegram_username ?? '',
                'group_name'          => $student->group_name,
                'department_name'     => $student->department_name,
                'specialty_name'      => $student->specialty_name,
                'level_name'          => $student->level_name,
                'course'              => $course,
                'education_type_name' => $student->education_type_name,
                'education_form_name' => $student->education_form_name,
                'semester_name'       => $student->semester_name,
                'province_name'       => $student->province_name,
                'district_name'       => $student->district_name,
                'avg_gpa'             => $student->avg_gpa,
                'avg_grade'           => $student->avg_grade,
                'total_credit'        => $student->total_credit,
                'payment_form_name'   => $student->payment_form_name,
                'student_status_name' => $student->student_status_name,
                'accommodation_name'  => $student->accommodation_name,
                'social_category_name'=> $student->social_category_name,
                'year_of_enter'       => $student->year_of_enter,
                'total_absences'      => $totalAbsent,
                'debt_subjects_count' => $debtCount,
                'recent_grades'       => $recentGrades,
            ],
        ]);
    }
}
