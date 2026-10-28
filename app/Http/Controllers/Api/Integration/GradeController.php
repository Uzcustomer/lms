<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\JnMtCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Tashqi saytlar uchun talabalar baholari (faqat o'qish).
 *
 * student_grades jadvalidagi barcha baholar — HEMIS'dan import qilinganlar ham,
 * LMS'da qo'yilganlar (o'qituvchi, qayta topshirish, test markazi) ham.
 * O'chirilgan baholar qaytarilmaydi. effective_grade — jurnal hisobga oladigan baho.
 *
 * Jadval juda katta, shuning uchun student_hemis_id / group_id / subject_id dan
 * kamida bittasi majburiy. Sahifalash id bo'yicha: javobdagi next_after_id ni
 * keyingi so'rovda after_id qilib yuboriladi (null bo'lsa — oxiri).
 */
class GradeController extends Controller
{
    private const MAX_PER_PAGE = 1000;

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'student_hemis_id' => 'nullable|integer',
            'group_id' => 'nullable|integer',
            'subject_id' => 'nullable|integer',
            'semester_code' => 'nullable|string|max:20',
            'education_year_code' => 'nullable|string|max:20',
            'training_type_code' => 'nullable|string|max:20',
            'date_from' => 'nullable|date_format:Y-m-d',
            'date_to' => 'nullable|date_format:Y-m-d',
            'updated_since' => 'nullable|date',
            'after_id' => 'nullable|integer|min:0',
            'per_page' => 'nullable|integer|min:1|max:' . self::MAX_PER_PAGE,
        ]);

        if (empty($data['student_hemis_id']) && empty($data['group_id']) && empty($data['subject_id'])) {
            return response()->json([
                'success' => false,
                'error' => 'student_hemis_id, group_id yoki subject_id dan kamida bittasi kerak',
            ], 422);
        }

        $perPage = (int) ($data['per_page'] ?? 500);

        $query = DB::table('student_grades as sg')
            ->leftJoin('students as s', 's.hemis_id', '=', 'sg.student_hemis_id')
            ->whereNull('sg.deleted_at')
            ->select([
                'sg.id', 'sg.student_hemis_id', 's.student_id_number', 's.full_name as student_name',
                's.group_id', 's.group_name',
                'sg.subject_id', 'sg.subject_name', 'sg.subject_code',
                'sg.semester_code', 'sg.semester_name', 'sg.education_year_code',
                'sg.training_type_code', 'sg.training_type_name',
                'sg.lesson_date', 'sg.lesson_pair_code',
                'sg.grade', 'sg.retake_grade', 'sg.status', 'sg.reason',
                'sg.employee_name', 'sg.created_at', 'sg.updated_at',
            ]);

        if (!empty($data['student_hemis_id'])) {
            $query->where('sg.student_hemis_id', (int) $data['student_hemis_id']);
        }
        if (!empty($data['group_id'])) {
            $hemisIds = DB::table('students')->where('group_id', (int) $data['group_id'])->pluck('hemis_id')->all();
            $query->whereIn('sg.student_hemis_id', $hemisIds ?: [0]);
        }
        if (!empty($data['subject_id'])) {
            $query->where('sg.subject_id', (int) $data['subject_id']);
        }
        foreach (['semester_code', 'education_year_code', 'training_type_code'] as $col) {
            if (!empty($data[$col])) {
                $query->where('sg.' . $col, $data[$col]);
            }
        }
        if (!empty($data['date_from'])) {
            $query->where('sg.lesson_date', '>=', $data['date_from']);
        }
        if (!empty($data['date_to'])) {
            $query->where('sg.lesson_date', '<', date('Y-m-d', strtotime($data['date_to'] . ' +1 day')));
        }
        if (!empty($data['updated_since'])) {
            $query->where('sg.updated_at', '>=', $data['updated_since']);
        }
        if (!empty($data['after_id'])) {
            $query->where('sg.id', '>', (int) $data['after_id']);
        }

        // Bitta ortiq olamiz — keyingi sahifa bor-yo'qligini bilish uchun
        $rows = $query->orderBy('sg.id')->limit($perPage + 1)->get();
        $hasMore = $rows->count() > $perPage;
        $rows = $rows->take($perPage);

        $items = $rows->map(fn ($r) => [
            'id' => (int) $r->id,
            'student_hemis_id' => (int) $r->student_hemis_id,
            'student_id_number' => $r->student_id_number,
            'student_name' => $r->student_name,
            'group_id' => $r->group_id !== null ? (int) $r->group_id : null,
            'group_name' => $r->group_name,
            'subject_id' => (int) $r->subject_id,
            'subject_name' => $r->subject_name,
            'subject_code' => $r->subject_code,
            'semester_code' => $r->semester_code,
            'semester_name' => $r->semester_name,
            'education_year_code' => $r->education_year_code,
            'training_type_code' => $r->training_type_code,
            'training_type_name' => $r->training_type_name,
            'lesson_date' => $r->lesson_date ? substr((string) $r->lesson_date, 0, 10) : null,
            'lesson_pair_code' => $r->lesson_pair_code,
            'grade' => $r->grade !== null ? (float) $r->grade : null,
            'retake_grade' => $r->retake_grade !== null ? (float) $r->retake_grade : null,
            'effective_grade' => JnMtCalculator::effectiveGrade($r),
            'status' => $r->status,
            'reason' => $r->reason,
            'employee_name' => $r->employee_name,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ])->values();

        return response()->json([
            'success' => true,
            'meta' => [
                'count' => $items->count(),
                'per_page' => $perPage,
                'next_after_id' => $hasMore ? $items->last()['id'] : null,
            ],
            'data' => $items,
        ]);
    }
}
