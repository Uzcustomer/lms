<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Services\RetakeBookingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

/**
 * Moodle local_otrabotka → LMS: qayta topshirish (otrabotka) muddatlari.
 * Moodle o'zi hisoblamaydi — muddat LMS bo'yicha: tasdiqlangan sababli ariza
 * davridagi dars uchun arizadagi qayta topshirish oralig'i, qolganlariga
 * admin sozlamasidagi kurs bo'yicha kunlar. Auth — boshqa Moodle callback'lari
 * kabi X-SYNC-SECRET.
 *
 * Request body:
 *   {"items": [{"student_hemis_id": 7545, "subject_id": 700, "lesson_date": "2026-10-20"}, ...]}
 *
 * Response:
 *   {"ok": true, "items": [{
 *     "student_hemis_id": 7545, "subject_id": 700, "lesson_date": "2026-10-20",
 *     "found": true,
 *     "deadline": "2026-11-04",     // shu kun oxirigacha (Asia/Tashkent)
 *     "book_from": "2026-10-26",    // sababli bo'lsa — oraliq boshi, aks holda null
 *     "excused": true
 *   }]}
 * Talaba LMS'da topilmasa: found=false, deadline/book_from = null.
 */
class OtrabotkaDeadlineController extends Controller
{
    private const MAX_ITEMS = 500;

    public function deadlines(Request $request): JsonResponse
    {
        $secret = (string) config('services.moodle.pull_secret');
        $got = (string) $request->header('X-SYNC-SECRET', '');

        if ($secret === '' || !hash_equals($secret, $got)) {
            return response()->json(['ok' => false, 'error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'items' => ['required', 'array', 'min:1', 'max:' . self::MAX_ITEMS],
            'items.*.student_hemis_id' => ['required', 'integer', 'min:1'],
            'items.*.subject_id' => ['required', 'integer', 'min:1'],
            'items.*.lesson_date' => ['required', 'date_format:Y-m-d'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'ok' => false,
                'error' => 'Invalid payload',
                'details' => $validator->errors(),
            ], 422);
        }

        $items = collect($request->input('items'));
        $levels = Student::whereIn('hemis_id', $items->pluck('student_hemis_id')->unique()->values())
            ->pluck('level_code', 'hemis_id');

        $result = $items->map(function (array $item) use ($levels) {
            $hemisId = (int) $item['student_hemis_id'];
            $row = [
                'student_hemis_id' => $hemisId,
                'subject_id' => (int) $item['subject_id'],
                'lesson_date' => $item['lesson_date'],
            ];
            if (!$levels->has($hemisId)) {
                return $row + ['found' => false, 'deadline' => null, 'book_from' => null, 'excused' => false];
            }

            return $row + ['found' => true] + RetakeBookingService::retakePeriod(
                $hemisId, $levels[$hemisId], $row['subject_id'], $row['lesson_date']
            );
        })->values();

        return response()->json(['ok' => true, 'items' => $result]);
    }
}
