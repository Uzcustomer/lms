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
        'done' => 'Topshirilgan',
        'expired' => "Muddati o'tgan",
    ];

    private const MOODLE_DOWN = "Moodle bilan bog'lanib bo'lmadi. Birozdan keyin qayta urinib ko'ring.";

    public function __construct(private RetakeBookingService $service)
    {
    }

    public function index(Request $request)
    {
        $student = Auth::guard('student')->user();
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

        $student = Auth::guard('student')->user();
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

        [$startTime, $endTime] = $slots[$data['slot']];
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
        if ($error = RetakeBookingService::validateSlot($start, $end, $debt['deadline'] ?? null)) {
            return redirect($back)->withInput()->withErrors(['slot' => $error]);
        }

        $result = $this->service->book($hemisId, $debt, $start, $end);

        return redirect($back)
            ->withInput($result['type'] === 'error' ? $request->input() : [])
            ->with('booking_flash', ['type' => $result['type'], 'message' => $result['message']]);
    }

    public function cancel(RetakeBooking $booking)
    {
        $hemisId = (int) Auth::guard('student')->user()->hemis_id;
        abort_unless((int) $booking->student_hemis_id === $hemisId, 404);

        $back = $this->debtUrl($booking->subject_id, $booking->lesson_date->format('Y-m-d'));
        if ($booking->status !== RetakeBooking::STATUS_BOOKED) {
            return redirect($back)->with('booking_flash', ['type' => 'warning', 'message' => 'Bu bron allaqachon amalda emas.']);
        }

        $result = $this->service->cancel($booking);

        return redirect($back)->with('booking_flash', ['type' => $result['type'], 'message' => $result['message']]);
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
        $isBooked = $state === 'booked' || ($local && !in_array($state, ['done', 'missed'], true));

        return [
            'subject_id' => $subjectId,
            'subject_name' => (string) ($debt['subject_name'] ?? ''),
            'mavzu' => $debt['mavzu'] ?? null,
            'lesson_date' => $lessonDate !== '' ? Carbon::parse($lessonDate) : null,
            'lesson_date_raw' => $lessonDate,
            'is_nb' => $isNb,
            'mark' => $isNb ? 'NB' : ($grades->isNotEmpty() ? $grades->implode(' / ') : 'past baho'),
            'deadline' => !empty($debt['deadline']) ? Carbon::parse($debt['deadline']) : null,
            'tries_left' => $debt['tries_left'] ?? null,
            'state' => $isBooked ? 'booked' : $state,
            'state_label' => self::STATE_LABELS[$isBooked ? 'booked' : $state] ?? $state,
            'can_book' => !empty($debt['can_book']),
            'quiz_uploaded' => !empty($debt['quiz_uploaded']),
            'is_booked' => $isBooked,
            'slot_start' => $isBooked ? $slotStart : null,
            'slot_end' => $isBooked ? $slotEnd : null,
            'booking' => $local,
            'sync_pending' => $local && $local->moodle_sync !== RetakeBooking::SYNC_OK,
            'url' => $this->debtUrl($subjectId, $lessonDate),
        ];
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
