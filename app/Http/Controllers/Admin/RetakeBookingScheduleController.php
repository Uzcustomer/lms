<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\RetakeBooking;
use App\Models\Student;
use App\Services\RetakeBookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Qayta topshirish (otrabotka) bronlari — haftalik jadval: kunlar (dushanba–shanba)
 * va vaqt oraliqlari bo'yicha, har katakda shu vaqtga yozilgan talabalar.
 * Test markazi kim, qachon kelishini ko'radi.
 */
class RetakeBookingScheduleController extends Controller
{
    private const WEEKDAYS = ['Dushanba', 'Seshanba', 'Chorshanba', 'Payshanba', 'Juma', 'Shanba'];

    public function index(Request $request)
    {
        $date = $this->dateParam($request->query('week')) ?? now();
        $monday = $date->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $saturday = $monday->copy()->addDays(5)->endOfDay();

        $bookings = RetakeBooking::whereIn('status', [RetakeBooking::STATUS_BOOKED, RetakeBooking::STATUS_MISSED])
            ->whereBetween('slot_start', [$monday, $saturday])
            ->orderBy('slot_start')
            ->get();
        $students = Student::whereIn('hemis_id', $bookings->pluck('student_hemis_id')->unique())
            ->get(['hemis_id', 'full_name', 'group_name', 'department_name', 'level_name'])
            ->keyBy('hemis_id');

        // Ish kunidagi oraliqlar (shanba — qisqa kun); jadvalda yo'q oraliqdagi bron ham ko'rinsin
        $slots = RetakeBookingService::slotOptions();
        foreach ($bookings as $b) {
            $key = $b->slot_start->format('H:i') . '-' . $b->slot_end->format('H:i');
            $slots[$key] ??= [$b->slot_start->format('H:i'), $b->slot_end->format('H:i')];
        }
        ksort($slots);

        $days = [];
        for ($i = 0; $i < 6; $i++) {
            $day = $monday->copy()->addDays($i);
            $daySlots = RetakeBookingService::slotOptions($day);
            $days[] = [
                'date' => $day,
                'name' => self::WEEKDAYS[$i],
                'is_today' => $day->isToday(),
                'is_past' => $day->lt(now()->startOfDay()),
                'open' => $daySlots,
                'count' => 0,
            ];
        }

        // [kun indeksi][oraliq] => bronlar
        $grid = [];
        $now = now();
        foreach ($bookings as $b) {
            $i = (int) $monday->diffInDays($b->slot_start->copy()->startOfDay());
            if ($i < 0 || $i > 5) {
                continue;
            }
            $s = $students->get($b->student_hemis_id);
            $grid[$i][$b->slot_start->format('H:i') . '-' . $b->slot_end->format('H:i')][] = [
                'name' => $s->full_name ?? ('HEMIS ' . $b->student_hemis_id),
                'group' => $s->group_name ?? '',
                'subject' => (string) $b->subject_name,
                'mavzu' => $b->mavzu,
                'reason' => $b->reason === 'nb' ? 'NB' : 'past baho',
                'state' => $b->status === RetakeBooking::STATUS_MISSED ? 'missed'
                    : ($b->slot_end->lte($now) ? 'past' : ($b->slot_start->lte($now) ? 'now' : 'booked')),
                'sync' => $b->moodle_sync === RetakeBooking::SYNC_OK,
            ];
            $days[$i]['count']++;
        }

        return view('admin.retake-bookings.schedule', [
            'monday' => $monday,
            'saturday' => $saturday,
            'days' => $days,
            'slots' => $slots,
            'grid' => $grid,
            'breaks' => RetakeBookingService::breaks(),
            'total' => array_sum(array_column($days, 'count')),
            'students' => $bookings->pluck('student_hemis_id')->unique()->count(),
            'missed' => $bookings->where('status', RetakeBooking::STATUS_MISSED)->count(),
            'prevWeek' => $monday->copy()->subWeek()->format('Y-m-d'),
            'nextWeek' => $monday->copy()->addWeek()->format('Y-m-d'),
            'isCurrentWeek' => $monday->isSameDay(now()->startOfWeek(Carbon::MONDAY)),
        ]);
    }

    private function dateParam($value): ?Carbon
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : null;
    }
}
