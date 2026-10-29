<?php

namespace App\Services;

use App\Models\RetakeBooking;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Qayta topshirish (otrabotka) vaqtini bron qilish: LMS'da saqlash va
 * Moodle local_otrabotka_book_retake ga yuborish.
 *
 * Avval yozuv LMS'da (moodle_sync=pending) saqlanadi, keyin Moodle'ga
 * yuboriladi. Moodle'ga yetib bormasa — moodle_sync=failed, uni
 * retake-bookings:sync (cron) qayta yuboradi.
 */
class RetakeBookingService
{
    /** Shuncha muvaffaqiyatsiz urinishdan keyin logga yoziladi */
    public const ALERT_AFTER_ATTEMPTS = 5;
    /** Bekor qilishni yuborish shundan keyin to'xtatiladi */
    public const MAX_SYNC_ATTEMPTS = 50;

    public function __construct(private MoodleRetakeClient $client)
    {
    }

    /**
     * Moodle'dagi qarzlar. Moodle "missed" degan bronlar LMS'da ham missed bo'ladi.
     *
     * @throws MoodleRetakeException
     */
    public function debts(int $studentHemisId): array
    {
        $debts = $this->client->getDebts($studentHemisId)['debts'];

        $missedIds = collect($debts)
            ->filter(fn ($d) => ($d['state'] ?? '') === 'missed' && (int) ($d['missed_booking_id'] ?? 0) > 0)
            ->map(fn ($d) => (int) $d['missed_booking_id'])
            ->all();
        if ($missedIds) {
            RetakeBooking::whereIn('id', $missedIds)
                ->where('student_hemis_id', $studentHemisId)
                ->where('status', RetakeBooking::STATUS_BOOKED)
                ->update(['status' => RetakeBooking::STATUS_MISSED, 'updated_at' => now()]);
        }

        return $debts;
    }

    public static function findDebt(array $debts, int $subjectId, string $lessonDate): ?array
    {
        foreach ($debts as $debt) {
            if ((int) ($debt['subject_id'] ?? 0) === $subjectId && ($debt['lesson_date'] ?? '') === $lessonDate) {
                return $debt;
            }
        }

        return null;
    }

    /**
     * Talaba tanlay oladigan vaqt oraliqlari (test markazi ish vaqti bo'yicha).
     *
     * @return array<string, array{0: string, 1: string}> "09:00-10:00" => ["09:00", "10:00"]
     */
    public static function slotOptions(): array
    {
        $from = Carbon::createFromFormat('H:i', (string) config('services.moodle.retake_slot_from', '09:00'));
        $to = Carbon::createFromFormat('H:i', (string) config('services.moodle.retake_slot_to', '17:00'));
        $minutes = max(15, (int) config('services.moodle.retake_slot_minutes', 60));

        $slots = [];
        for ($start = $from->copy(); $start->copy()->addMinutes($minutes)->lessThanOrEqualTo($to); $start->addMinutes($minutes)) {
            $end = $start->copy()->addMinutes($minutes);
            $slots[$start->format('H:i') . '-' . $end->format('H:i')] = [$start->format('H:i'), $end->format('H:i')];
        }

        return $slots;
    }

    /** Talaba tanlagan vaqt oralig'ini tekshiradi: xato matni yoki null */
    public static function validateSlot(Carbon $start, Carbon $end, ?string $deadline): ?string
    {
        if ($start->lessThanOrEqualTo(now())) {
            return "Boshlanish vaqti hozirgi vaqtdan keyin bo'lishi kerak.";
        }
        if ($end->lessThanOrEqualTo($start)) {
            return "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.";
        }
        if ($deadline && $start->toDateString() > $deadline) {
            return 'Sana qayta topshirish muddatidan (' . Carbon::parse($deadline)->format('d.m.Y') . ') kech bo\'lmasligi kerak.';
        }

        return null;
    }

    /** Shu kundagi NB / past baho yozuvlari (sahifada ko'rsatish va grade_id uchun) */
    public static function gradeRows(int $studentHemisId, int $subjectId, string $lessonDate): Collection
    {
        return StudentGrade::where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->whereDate('lesson_date', $lessonDate)
            ->whereIn('reason', ['absent', 'low_grade'])
            ->orderBy('id')
            ->get(['id', 'grade', 'reason', 'retake_grade']);
    }

    /** Talabaning shu mavzu bo'yicha amaldagi broni */
    public static function activeBooking(int $studentHemisId, int $subjectId, string $lessonDate): ?RetakeBooking
    {
        return RetakeBooking::forDebt($studentHemisId, $subjectId, $lessonDate)
            ->where('status', RetakeBooking::STATUS_BOOKED)
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Yangi bron yoki mavjudini yangi vaqtga o'zgartirish (o'sha id qayta yuboriladi).
     *
     * @return array{type: string, message: string, booking: RetakeBooking}
     */
    public function book(int $studentHemisId, array $debt, Carbon $start, Carbon $end): array
    {
        $subjectId = (int) $debt['subject_id'];
        $lessonDate = (string) $debt['lesson_date'];
        $gradeId = self::gradeRows($studentHemisId, $subjectId, $lessonDate)->first()?->id;

        [$booking, $previous] = DB::transaction(function () use ($studentHemisId, $subjectId, $lessonDate, $debt, $start, $end, $gradeId) {
            $booked = RetakeBooking::forDebt($studentHemisId, $subjectId, $lessonDate)
                ->where('status', RetakeBooking::STATUS_BOOKED)
                ->orderByDesc('id')
                ->lockForUpdate()
                ->get();

            $booking = $booked->shift();
            // Bir mavzuda bittadan ortiq "booked" bo'lmasin — ortiqchasi bekor qilinadi (cron Moodle'ga yuboradi)
            foreach ($booked as $extra) {
                $extra->update([
                    'status' => RetakeBooking::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'moodle_sync' => RetakeBooking::SYNC_PENDING,
                    'sync_attempts' => 0,
                ]);
            }

            $previous = $booking && $booking->moodle_sync === RetakeBooking::SYNC_OK
                ? $booking->only(['slot_start', 'slot_end', 'moodle_code', 'moodle_message', 'synced_at'])
                : null;

            $attrs = [
                'grade_id' => $gradeId,
                'subject_name' => $debt['subject_name'] ?? null,
                'mavzu' => isset($debt['mavzu']) ? (int) $debt['mavzu'] : null,
                'reason' => $debt['reason'] ?? null,
                'deadline' => $debt['deadline'] ?? null,
                'slot_start' => $start,
                'slot_end' => $end,
                'status' => RetakeBooking::STATUS_BOOKED,
                'moodle_sync' => RetakeBooking::SYNC_PENDING,
                'sync_attempts' => 0,
                'moodle_code' => null,
                'moodle_message' => null,
            ];

            if ($booking) {
                $booking->update($attrs);
            } else {
                $booking = RetakeBooking::create($attrs + [
                    'student_hemis_id' => $studentHemisId,
                    'subject_id' => $subjectId,
                    'lesson_date' => $lessonDate,
                ]);
            }

            return [$booking, $previous];
        });

        try {
            $result = $this->client->bookRetake($this->bookPayload($booking));
        } catch (MoodleRetakeException $e) {
            $this->markFailed($booking, $e);

            return [
                'type' => 'warning',
                'message' => "Vaqtingiz saqlandi, Moodle bilan sinxronlash kutilmoqda.",
                'booking' => $booking,
            ];
        }

        if ($result['ok']) {
            $this->markSynced($booking, $result);

            return [
                'type' => 'success',
                'message' => 'Saqlandi: ' . ($result['message'] !== '' ? $result['message'] : $this->slotText($booking)),
                'booking' => $booking,
            ];
        }

        if ($previous) {
            // Vaqtni o'zgartirish rad etildi — Moodle'da eski vaqt turibdi, LMS'da ham qaytariladi
            $booking->update([
                'moodle_sync' => RetakeBooking::SYNC_OK,
                'moodle_code' => $result['code'],
                'moodle_message' => $result['message'],
            ] + $previous);
        } else {
            $booking->update([
                'status' => RetakeBooking::STATUS_REJECTED,
                'moodle_sync' => RetakeBooking::SYNC_OK,
                'moodle_code' => $result['code'],
                'moodle_message' => $result['message'],
                'synced_at' => now(),
            ]);
        }

        return [
            'type' => 'error',
            'message' => $result['message'] !== '' ? $result['message'] : 'Vaqt saqlanmadi. Boshqa vaqtni tanlab ko\'ring.',
            'booking' => $booking,
        ];
    }

    /**
     * @return array{type: string, message: string, booking: RetakeBooking}
     */
    public function cancel(RetakeBooking $booking): array
    {
        $booking->update([
            'status' => RetakeBooking::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'moodle_sync' => RetakeBooking::SYNC_PENDING,
            'sync_attempts' => 0,
        ]);

        try {
            $result = $this->client->cancel($booking->id);
        } catch (MoodleRetakeException $e) {
            $this->markFailed($booking, $e);

            return [
                'type' => 'warning',
                'message' => 'Bron bekor qilindi, Moodle bilan sinxronlash kutilmoqda.',
                'booking' => $booking,
            ];
        }

        $this->markSynced($booking, $result);

        return ['type' => 'success', 'message' => 'Bron bekor qilindi.', 'booking' => $booking];
    }

    /**
     * Cron: Moodle'ga yetib bormagan yozuvni qayta yuborish.
     * true — Moodle javob berdi (saqlandi yoki rad etildi), false — yana yetib bormadi.
     */
    public function sync(RetakeBooking $booking): bool
    {
        try {
            $result = $booking->status === RetakeBooking::STATUS_CANCELLED
                ? $this->client->cancel($booking->id)
                : $this->client->bookRetake($this->bookPayload($booking));
        } catch (MoodleRetakeException $e) {
            $this->markFailed($booking, $e);

            return false;
        }

        if ($booking->status === RetakeBooking::STATUS_BOOKED && !$result['ok']) {
            $booking->update([
                'status' => RetakeBooking::STATUS_REJECTED,
                'moodle_sync' => RetakeBooking::SYNC_OK,
                'moodle_code' => $result['code'],
                'moodle_message' => $result['message'],
                'synced_at' => now(),
            ]);

            return true;
        }

        $this->markSynced($booking, $result);

        return true;
    }

    /** Cron qayta yuboradigan yozuvlar */
    public static function unsyncedQuery()
    {
        return RetakeBooking::whereIn('moodle_sync', [RetakeBooking::SYNC_PENDING, RetakeBooking::SYNC_FAILED])
            // Hozir sahifadan yuborilayotganlar bilan to'qnashmasin
            ->where('updated_at', '<', now()->subMinute())
            ->where(function ($q) {
                $q->where(function ($b) {
                    // Vaqti o'tib ketgan bronni yuborishdan ma'no yo'q
                    $b->where('status', RetakeBooking::STATUS_BOOKED)->where('slot_end', '>', now());
                })->orWhere(function ($c) {
                    $c->where('status', RetakeBooking::STATUS_CANCELLED)
                        ->where('sync_attempts', '<', self::MAX_SYNC_ATTEMPTS);
                });
            })
            ->orderBy('id');
    }

    private function bookPayload(RetakeBooking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'student_hemis_id' => $booking->student_hemis_id,
            'subject_id' => $booking->subject_id,
            'lesson_date' => $booking->lesson_date->format('Y-m-d'),
            'slot_start' => $booking->slot_start->format('Y-m-d H:i'),
            'slot_end' => $booking->slot_end->format('Y-m-d H:i'),
            'status' => 'booked',
        ];
    }

    private function markSynced(RetakeBooking $booking, array $result): void
    {
        $booking->update(array_filter([
            'moodle_sync' => RetakeBooking::SYNC_OK,
            'moodle_code' => $result['code'],
            'moodle_message' => $result['message'],
            'deadline' => $result['deadline'] ?? null,
            'synced_at' => now(),
        ], fn ($v) => $v !== null));
    }

    private function markFailed(RetakeBooking $booking, MoodleRetakeException $e): void
    {
        $booking->update([
            'moodle_sync' => RetakeBooking::SYNC_FAILED,
            'sync_attempts' => $booking->sync_attempts + 1,
            'moodle_message' => mb_substr($e->getMessage(), 0, 1000),
        ]);

        if ($booking->sync_attempts === self::ALERT_AFTER_ATTEMPTS) {
            Log::error('Moodle otrabotka: bron ' . self::ALERT_AFTER_ATTEMPTS . ' marta yuborilmadi', [
                'booking_id' => $booking->id,
                'student_hemis_id' => $booking->student_hemis_id,
                'subject_id' => $booking->subject_id,
                'lesson_date' => $booking->lesson_date?->format('Y-m-d'),
                'status' => $booking->status,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function slotText(RetakeBooking $booking): string
    {
        return $booking->slot_start->format('d.m.Y H:i') . '–' . $booking->slot_end->format('H:i');
    }
}
