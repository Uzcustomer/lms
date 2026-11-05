<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RetakeBooking;
use App\Services\MoodleRetakeException;
use App\Services\RetakeBookingService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Talaba qayta topshirish (otrabotka) kuni va vaqt oralig'ini tanlaydi.
 * Qarzlar va muddat Moodle (local_otrabotka_get_debts) dan olinadi.
 * student_hemis_id faqat sessiyadagi talabadan — URL'dan qabul qilinmaydi.
 */
class RetakeBookingController extends Controller
{
    private const STATE_LABELS = [
        'start' => 'Topshirilmagan',
        'booked' => 'Vaqt tanlangan',
        'missed' => 'Tanlangan vaqtda kelinmagan',
        'noquiz' => 'Test hali yuklanmagan',
        'continue' => 'Urinish davom etmoqda',
        'done' => 'Yakunlangan',
        'expired' => "Muddati o'tgan",
        'nobooking' => 'Vaqt tanlanmagan',
        'nextday' => 'Bugun urinish ishlatilgan',
        'wait' => 'Sababli oraliq hali boshlanmagan',
    ];

    private const MOODLE_DOWN = "Moodle bilan bog'lanib bo'lmadi. Birozdan keyin qayta urinib ko'ring.";

    private const LOCKED = "Tanlangan vaqtni o'zgartirib bo'lmaydi. Boshqa vaqt kerak bo'lsa, avval uni bekor qiling (vaqti boshlanmasidan oldin).";

    public function __construct(private RetakeBookingService $service)
    {
    }

    public function index(Request $request)
    {
        $student = $this->student();
        $hemisId = (int) $student->hemis_id;
        $subjectId = (int) $request->query('subject_id');
        $lessonDate = $this->dateParam($request->query('lesson_date'));

        try {
            $debts = $this->service->withLmsDeadline($this->service->debts($hemisId), $student);
        } catch (MoodleRetakeException $e) {
            Log::warning('Moodle otrabotka get_debts', ['student_hemis_id' => $hemisId, 'error' => $e->getMessage()]);

            return view('student.retake-booking', ['debt' => null, 'debts' => collect(), 'loadError' => self::MOODLE_DOWN, 'notFound' => false]);
        }

        $notFound = false;
        if ($subjectId && $lessonDate) {
            $debt = RetakeBookingService::findDebt($debts, $subjectId, $lessonDate);
            if ($debt) {
                return view('student.retake-booking', [
                    'debt' => $this->present($hemisId, $debt), 'debts' => collect(), 'loadError' => null, 'notFound' => false,
                    'slots' => RetakeBookingService::slotOptions(),
                    'saturdayTo' => RetakeBookingService::saturdayTo(),
                    'perSlot' => RetakeBookingService::MAX_SUBJECTS_PER_SLOT,
                    // Boshqa fanlar bo'yicha tanlangan vaqtlar: oraliqda 2 ta fan bo'lsa — band;
                    // shu fanning boshqa mavzusi tanlangan kun — butunlay band
                    'busy' => RetakeBookingService::otherBookings($hemisId, $subjectId, $lessonDate)
                        ->map(fn (RetakeBooking $b) => [
                            'start' => $b->slot_start->format('Y-m-d\TH:i'),
                            'end' => $b->slot_end->format('Y-m-d\TH:i'),
                            'name' => (string) $b->subject_name,
                            'same' => (int) $b->subject_id === $subjectId,
                        ])->values()
                        // Test markazi yopgan vaqtlar (Moodle) — tanlab bo'lmaydi
                        ->concat(collect($this->service->closedSlots())->map(fn ($c) => [
                            'start' => str_replace(' ', 'T', (string) ($c['start'] ?? '')),
                            'end' => str_replace(' ', 'T', (string) ($c['end'] ?? '')),
                            'name' => (string) ($c['note'] ?? ''),
                            'closed' => true,
                        ]))->values(),
                ]);
            }
            $notFound = true;
        }

        $list = collect($debts)
            ->filter(fn ($d) => !empty($d['can_book']) || in_array($d['state'] ?? '', ['booked', 'missed'], true))
            ->map(fn ($d) => $this->present($hemisId, $d))
            ->sortBy(fn ($d) => $d['deadline']?->timestamp ?? PHP_INT_MAX)
            ->values();

        return view('student.retake-booking', ['debt' => null, 'debts' => $list, 'loadError' => null, 'notFound' => $notFound]);
    }

    public function store(Request $request)
    {
        $slots = RetakeBookingService::slotOptions();
        $data = $request->validate([
            'subject_id' => 'required|integer',
            'lesson_date' => 'required|date_format:Y-m-d',
            'date' => 'required|date_format:Y-m-d',
            'slot' => ['required', Rule::in(array_keys($slots))],
        ], [
            'date.required' => 'Kunni tanlang.',
            'date.date_format' => "Sana noto'g'ri formatda.",
            'slot.required' => "Vaqt oralig'ini tanlang.",
            'slot.in' => "Vaqt oralig'ini ro'yxatdan tanlang.",
        ]);

        $student = $this->student();
        $hemisId = (int) $student->hemis_id;
        $back = $this->debtUrl((int) $data['subject_id'], $data['lesson_date']);

        // can_book ni brauzerdan emas, Moodle'dan qayta olamiz; muddat — LMS bo'yicha
        try {
            $debt = RetakeBookingService::findDebt(
                $this->service->withLmsDeadline($this->service->debts($hemisId), $student),
                (int) $data['subject_id'],
                $data['lesson_date']
            );
        } catch (MoodleRetakeException $e) {
            Log::warning('Moodle otrabotka get_debts', ['student_hemis_id' => $hemisId, 'error' => $e->getMessage()]);

            return redirect($back)->withInput()->with('booking_flash', ['type' => 'error', 'message' => self::MOODLE_DOWN . ' Vaqt saqlanmadi.']);
        }
        if (!$debt) {
            return redirect($back)->with('booking_flash', ['type' => 'error', 'message' => 'Bu mavzu bo\'yicha qarz topilmadi.']);
        }
        if (empty($debt['can_book'])) {
            return redirect($back)->with('booking_flash', ['type' => 'error', 'message' => 'Bu mavzu uchun hozir vaqt tanlab bo\'lmaydi.']);
        }
        // Saqlangan vaqt o'zgartirilmaydi va bekor qilinmaydi — kelinmasa urinish kuyadi
        $active = RetakeBookingService::activeBooking($hemisId, (int) $data['subject_id'], $data['lesson_date']);
        if ($active && $active->slot_end->greaterThan(now())) {
            return redirect($back)->with('booking_flash', ['type' => 'error', 'message' => self::LOCKED]);
        }

        // Shu kunning vaqtlari (shanba — qisqa kun, tushlik yo'q)
        $daySlots = RetakeBookingService::slotOptions(Carbon::createFromFormat('Y-m-d', $data['date']));
        if (!isset($daySlots[$data['slot']])) {
            return redirect($back)->withInput()->withErrors(['slot' => "Bu kunda bunday vaqt oralig'i yo'q (shanba — "
                . RetakeBookingService::saturdayTo() . ' gacha). Boshqa vaqtni tanlang.']);
        }
        [$startTime, $endTime] = $daySlots[$data['slot']];
        $start = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $startTime);
        $end = Carbon::createFromFormat('Y-m-d H:i', $data['date'] . ' ' . $endTime);
        if ($end->lessThanOrEqualTo(now())) {
            return redirect($back)->withInput()->withErrors(['slot' => "Bu vaqt oralig'i o'tib ketgan — keyingisini tanlang."]);
        }
        // Davom etayotgan oraliq: bron hozirdan (keyingi daqiqadan) oraliq oxirigacha
        if ($start->lessThanOrEqualTo(now())) {
            $start = now()->addMinute()->startOfMinute();
            if ($start->greaterThanOrEqualTo($end)) {
                return redirect($back)->withInput()->withErrors(['slot' => "Bu vaqt oralig'i tugayapti — keyingisini tanlang."]);
            }
        }
        // Oddiy NB: bugun urinish ishlatilgan yoki bronga kelinmagan — keyingisi ertadan (Moodle ham tekshiradi)
        $minDate = $this->minDate($hemisId, $debt);
        if ($start->toDateString() < $minDate->toDateString()) {
            return redirect($back)->withInput()->withErrors(['slot' => 'Bugun bu mavzudan urinish ishlatilgan — vaqtni '
                . $minDate->format('d.m.Y') . ' dan boshlab tanlang.']);
        }
        if ($error = RetakeBookingService::validateSlot($start, $end, $debt['deadline'] ?? null, $debt['book_from'] ?? null)) {
            return redirect($back)->withInput()->withErrors(['slot' => $error]);
        }
        if ($conflict = RetakeBookingService::bookingConflict($hemisId, (int) $data['subject_id'], $data['lesson_date'], $start, $end)) {
            return redirect($back)->withInput()->withErrors(['slot' => $conflict]);
        }

        $result = $this->service->book($hemisId, $debt, $start, $end);

        return redirect($back)
            ->withInput($result['type'] === 'error' ? $request->input() : [])
            ->with('booking_flash', ['type' => $result['type'], 'message' => $result['message']]);
    }

    /** Talaba bronni faqat vaqti boshlanmasidan oldin bekor qila oladi (keyin — urinish kuygan) */
    public function cancel(RetakeBooking $booking)
    {
        $hemisId = (int) $this->student()->hemis_id;
        abort_unless((int) $booking->student_hemis_id === $hemisId, 404);

        $back = $this->debtUrl($booking->subject_id, $booking->lesson_date->format('Y-m-d'));
        if ($booking->status !== RetakeBooking::STATUS_BOOKED) {
            return redirect($back)->with('booking_flash', ['type' => 'warning', 'message' => 'Bu bron allaqachon amalda emas.']);
        }
        if ($booking->slot_start->lessThanOrEqualTo(now())) {
            return redirect($back)->with('booking_flash', ['type' => 'error', 'message' => "Vaqti boshlangan bronni bekor qilib bo'lmaydi."]);
        }

        $result = $this->service->cancel($booking);

        return redirect($back)->with('booking_flash', ['type' => $result['type'], 'message' => $result['message']]);
    }

    /**
     * Kun tanlashning eng erta sanasi: sababli arizada oraliq boshi; oddiy NB'da bugun
     * urinish ishlatilgan (Moodle "nextday") yoki bugungi bronga kelinmagan bo'lsa — ertaga
     * (bir kunda bitta urinish), aks holda bugun.
     */
    private function minDate(int $hemisId, array $debt): Carbon
    {
        $min = now()->startOfDay();
        if (!empty($debt['book_from']) && Carbon::parse($debt['book_from'])->greaterThan($min)) {
            $min = Carbon::parse($debt['book_from'])->startOfDay();
        }
        if (empty($debt['excused'])) {
            $state = (string) ($debt['state'] ?? '');
            $missedToday = $state === 'missed' && RetakeBooking::forDebt($hemisId, (int) $debt['subject_id'], (string) $debt['lesson_date'])
                ->where('status', RetakeBooking::STATUS_MISSED)
                ->whereDate('slot_start', now()->toDateString())
                ->exists();
            if ($state === 'nextday' || $missedToday) {
                $min = $min->max(now()->addDay()->startOfDay());
            }
        }

        return $min;
    }

    /** Moodle qarzi + LMS'dagi bron va baholar — sahifa uchun */
    private function present(int $hemisId, array $debt): array
    {
        $subjectId = (int) ($debt['subject_id'] ?? 0);
        $lessonDate = (string) ($debt['lesson_date'] ?? '');
        $state = (string) ($debt['state'] ?? '');
        $isNb = ($debt['reason'] ?? '') === 'nb';

        $local = RetakeBookingService::activeBooking($hemisId, $subjectId, $lessonDate);
        $grades = $isNb ? collect() : RetakeBookingService::gradeRows($hemisId, $subjectId, $lessonDate)
            ->pluck('grade')->filter(fn ($g) => $g !== null)
            ->map(fn ($g) => rtrim(rtrim(number_format((float) $g, 2, '.', ''), '0'), '.'))->unique();

        $slotStart = !empty($debt['slot_start']) ? Carbon::parse($debt['slot_start']) : $local?->slot_start;
        $slotEnd = !empty($debt['slot_end']) ? Carbon::parse($debt['slot_end']) : $local?->slot_end;
        // LMS'da amaldagi bron bo'lsa ham "tanlangan" — Moodle'ga hali yetib bormagan yoki
        // vaqt oralig'i boshlanib Moodle holatni boshqacha qaytarsa ham talaba bekor qila olsin
        // O'tib ketgan bron (kelib topshirilgan) hisobga olinmaydi — keyingi urinishga yangi vaqt tanlanadi
        if ($local && $local->slot_end->lessThanOrEqualTo(now())) {
            $local = null;
        }
        $isBooked = $state === 'booked' || ($local && !in_array($state, ['done', 'missed'], true));

        $bookFrom = !empty($debt['book_from']) ? Carbon::parse($debt['book_from']) : null;
        $deadline = !empty($debt['deadline']) ? Carbon::parse($debt['deadline']) : null;
        $minDate = $this->minDate($hemisId, $debt);
        // Keyingi urinish ertadan, muddat esa bugun tugaydi — vaqt tanlab bo'lmaydi
        $noTimeLeft = $deadline && $minDate->toDateString() > $deadline->toDateString();

        return [
            'subject_id' => $subjectId,
            'subject_name' => (string) ($debt['subject_name'] ?? ''),
            'mavzu' => $debt['mavzu'] ?? null,
            'lesson_date' => $lessonDate !== '' ? Carbon::parse($lessonDate) : null,
            'lesson_date_raw' => $lessonDate,
            'is_nb' => $isNb,
            'mark' => $isNb ? 'NB' : ($grades->isNotEmpty() ? $grades->implode(' / ') : 'past baho'),
            'deadline' => $deadline,
            'book_from' => $bookFrom,
            'excused' => !empty($debt['excused']),
            // Yangi sababli arizalarda har bir dars kuniga bitta kunlik retake — kun tanlanmaydi
            'retake_day' => !empty($debt['excused']) && $bookFrom && $deadline && $bookFrom->isSameDay($deadline) ? $bookFrom : null,
            'tries_left' => $debt['tries_left'] ?? null,
            'state' => $isBooked ? 'booked' : $state,
            'state_label' => self::STATE_LABELS[$isBooked ? 'booked' : $state] ?? $state,
            // Saqlangan vaqt o'zgartirilmaydi: tanlangan bo'lsa forma ko'rsatilmaydi
            'can_book' => !empty($debt['can_book']) && !$isBooked && !$noTimeLeft,
            'min_date' => $minDate,
            'no_time_left' => $noTimeLeft && !$isBooked && !empty($debt['can_book']),
            'quiz_uploaded' => !empty($debt['quiz_uploaded']),
            'is_booked' => $isBooked,
            'slot_start' => $isBooked ? $slotStart : null,
            'slot_end' => $isBooked ? $slotEnd : null,
            'booking' => $local,
            'sync_pending' => $local && $local->moodle_sync !== RetakeBooking::SYNC_OK,
            'url' => $this->debtUrl($subjectId, $lessonDate),
        ];
    }

    /** Xizmat faqat 1–3-kurslarga: 4–6-kurslar havolani ochsa ham sahifa yo'q */
    private function student()
    {
        $student = Auth::guard('student')->user();
        abort_unless($student && $student->hasOtrabotka(), 404);

        return $student;
    }

    private function debtUrl(int $subjectId, string $lessonDate): string
    {
        return route('student.retake-booking.index', ['subject_id' => $subjectId, 'lesson_date' => $lessonDate]);
    }

    private function dateParam($value): ?string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? $value : null;
    }
}
