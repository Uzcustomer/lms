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
 * Jadvalning barcha ustunlari qaytariladi, ustiga talaba ID raqami, F.I.Sh.,
 * guruhi va effective_grade (jurnal hisobga oladigan baho). O'chirilganlar yo'q.
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
            // student_grades ning barcha ustunlari + talaba ma'lumotlari
            ->select([
                'sg.*',
                's.student_id_number', 's.full_name as student_name', 's.group_id', 's.group_name',
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

        $items = $rows->map(fn ($r) => (array) $r + [
            'effective_grade' => JnMtCalculator::effectiveGrade($r),
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
