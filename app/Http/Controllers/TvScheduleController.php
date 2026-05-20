<?php

namespace App\Http\Controllers;

use App\Models\Schedule;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

/**
 * TV displey uchun dars jadvali — faqat keyingi 60 daqiqalik oyna.
 * Lobbidagi ekran uchun mo'ljallangan, login talab qilinmaydi.
 */
class TvScheduleController extends Controller
{
    private const WINDOW_MINUTES = 60;

    public function index()
    {
        return view('tv.schedule');
    }

    /**
     * Hozir ketayotgan + keyingi 60 daqiqada boshlanadigan darslar (JSON).
     */
    public function data(): JsonResponse
    {
        $now = Carbon::now();
        $nowTime = $now->format('H:i:s');

        $windowEndCarbon = $now->copy()->addMinutes(self::WINDOW_MINUTES);
        // 60 daqiqalik oyna kun oxiridan o'tib ketsa, kun oxirida cheklaymiz
        $windowEnd = $windowEndCarbon->isSameDay($now)
            ? $windowEndCarbon->format('H:i:s')
            : '23:59:59';

        $records = Schedule::query()
            ->whereDate('lesson_date', $now->toDateString())
            ->where('lesson_pair_end_time', '>', $nowTime)
            ->where('lesson_pair_start_time', '<=', $windowEnd)
            ->orderBy('lesson_pair_start_time')
            ->orderBy('building_name')
            ->orderBy('auditorium_name')
            ->get();

        $lessons = $records
            ->unique(fn ($l) => $l->subject_id . '|' . $l->lesson_pair_start_time . '|'
                . $l->lesson_pair_end_time . '|' . $l->auditorium_code . '|' . $l->employee_id)
            ->map(function ($l) use ($now) {
                $date = $l->lesson_date->toDateString();
                $start = Carbon::parse($date . ' ' . $l->lesson_pair_start_time);
                $end = Carbon::parse($date . ' ' . $l->lesson_pair_end_time);
                $ongoing = $start->lte($now) && $end->gt($now);

                return [
                    'subject_name' => $l->subject_name,
                    'group_name' => $l->group_name,
                    'employee_name' => $l->employee_name,
                    'auditorium_name' => $l->auditorium_name ?: '—',
                    'building_name' => $l->building_name ?: '',
                    'training_type_name' => $l->training_type_name ?: '',
                    'lesson_pair_name' => $l->lesson_pair_name ?: '',
                    'start' => $start->format('H:i'),
                    'end' => $end->format('H:i'),
                    'status' => $ongoing ? 'ongoing' : 'soon',
                    'minutes_to_start' => $ongoing
                        ? 0
                        : (int) max(0, ceil(($start->getTimestamp() - $now->getTimestamp()) / 60)),
                ];
            })
            ->values();

        return response()->json([
            'now' => $now->format('H:i'),
            'date' => $now->locale('uz')->isoFormat('D-MMMM, dddd'),
            'window_minutes' => self::WINDOW_MINUTES,
            'count' => $lessons->count(),
            'lessons' => $lessons,
        ]);
    }
}
