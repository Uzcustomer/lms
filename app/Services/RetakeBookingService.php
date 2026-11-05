<?php

namespace App\Services;

use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\Deadline;
use App\Models\MarkingSystemScore;
use App\Models\RetakeBooking;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\StudentNotification;
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
    /** Kurs uchun sozlama bo'lmasa (deadlines jadvali) */
    public const DEFAULT_DEADLINE_DAYS = 7;

    public function __construct(private MoodleRetakeClient $client)
    {
    }

    /**
     * Moodle'dagi qarzlar. Moodle "missed" degan bronlar LMS'da ham missed bo'ladi.
     *
     * @throws MoodleRetakeException
     */
    /** Oxirgi debts() so'rovidagi test markazi yopgan vaqtlar: [{start, end, note}] */
    private array $closed = [];

    public function debts(int $studentHemisId): array
    {
        $response = $this->client->getDebts($studentHemisId);
        $debts = $response['debts'];
        $this->closed = $response['closed'] ?? [];

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
        // Test markazi Moodle'da bekor qilgan bronlar: urinish kuymaydi, talaba qayta tanlaydi
        // (odatda Moodle buni darhol API orqali aytadi; yetib bormagan bo'lsa — shu yerda)
        if ($cancelledIds = $response['cancelled_booking_ids'] ?? []) {
            RetakeBooking::whereIn('id', $cancelledIds)
                ->where('student_hemis_id', $studentHemisId)
                ->whereIn('status', [RetakeBooking::STATUS_BOOKED, RetakeBooking::STATUS_MISSED])
                ->get()
                ->each(fn (RetakeBooking $b) => self::adminCancel($b));
        }

        return $debts;
    }

    /**
     * Qayta topshirish muddati Moodle'niki emas, LMS'niki: sozlamadagi kurs
     * bo'yicha kunlar (otrabotka sahifasi bilan bir xil). Dars sanasi tasdiqlangan
     * sababli arizaga tushsa — arizada shu fanga belgilangan qayta topshirish
     * oralig'i (book_from — deadline). Muddati LMS bo'yicha o'tgan qarzga vaqt
     * tanlab bo'lmaydi.
     */
    /**
     * Test markazi Moodle'da bekor qilgan bron: LMS'da ham bekor (urinish kuymaydi),
     * talabaga profil bildirishnomasi va Telegram xabari. Faqat amaldagi (booked/missed)
     * bron uchun — takroriy chaqiriq xabarni qayta yubormaydi.
     *
     * @return bool bekor qilindimi
     */
    public static function adminCancel(RetakeBooking $booking): bool
    {
        $changed = RetakeBooking::whereKey($booking->id)
            ->whereIn('status', [RetakeBooking::STATUS_BOOKED, RetakeBooking::STATUS_MISSED])
            ->update([
                'status' => RetakeBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'moodle_sync' => RetakeBooking::SYNC_OK,
                'moodle_code' => self::CODE_ADMIN_CANCELLED,
                'updated_at' => now(),
            ]);
        if (!$changed) {
            return false;
        }
        $booking->refresh();

        try {
            self::notifyAdminCancelled($booking);
        } catch (\Throwable $e) {
            Log::warning('Otrabotka: bekor qilingan bron haqida xabar yuborilmadi: ' . $e->getMessage(), ['booking_id' => $booking->id]);
        }

        return true;
    }

    /** Talabaga: profil e'loni (StudentNotification) va Telegram */
    private static function notifyAdminCancelled(RetakeBooking $booking): void
    {
        $student = Student::where('hemis_id', $booking->student_hemis_id)->first(['id', 'telegram_chat_id']);
        if (!$student) {
            return;
        }
        $subject = trim(($booking->subject_name ?: 'Fan') . ($booking->mavzu ? ', ' . $booking->mavzu . '-mavzu' : ''));
        $slot = $booking->slot_start->format('d.m.Y') . ', ' . $booking->slot_start->format('H:i') . '–' . $booking->slot_end->format('H:i');
        $link = route('student.retake-booking.index', [
            'subject_id' => $booking->subject_id,
            'lesson_date' => $booking->lesson_date->format('Y-m-d'),
        ], false);

        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'retake_booking',
            'title' => 'Qayta topshirish broningiz bekor qilindi',
            'message' => "{$subject} bo'yicha {$slot} vaqtidagi qayta topshirish broningiz test markazi tomonidan bekor qilindi. "
                . 'Urinishingiz kuymadi — iltimos, qayta sana va vaqt tanlang.',
            'link' => $link,
            'data' => [
                'booking_id' => $booking->id,
                'subject_id' => $booking->subject_id,
                'lesson_date' => $booking->lesson_date->format('Y-m-d'),
                'slot' => $slot,
            ],
        ]);

        if (!empty($student->telegram_chat_id)) {
            app(TelegramService::class)->sendToUser((string) $student->telegram_chat_id, implode("\n", [
                '❌ <b>Qayta topshirish broningiz bekor qilindi</b>',
                '',
                '📚 <b>' . e($booking->subject_name ?: 'Fan') . '</b>' . ($booking->mavzu ? ' — ' . $booking->mavzu . '-mavzu' : ''),
                '📅 Dars sanasi: ' . $booking->lesson_date->format('d.m.Y'),
                '🕒 Bekor qilingan vaqt: <b>' . $slot . '</b>',
                '',
                'Test markazi bu vaqtni bekor qildi. Urinishingiz <b>kuymadi</b>.',
                '👉 Iltimos, qayta sana va vaqt tanlang:',
                '<a href="' . e(url($link)) . '">Qayta topshirish vaqtini tanlash</a>',
            ]));
        }
    }

    /** Test markazi yopgan vaqtlar (oxirgi debts() so'rovidan) */
    public function closedSlots(): array
    {
        return $this->closed;
    }

    public function withLmsDeadline(array $debts, $student): array
    {
        $today = now()->toDateString();

        return array_map(function (array $debt) use ($student, $today) {
            if (empty($debt['subject_id']) || empty($debt['lesson_date'])) {
                return $debt;
            }
            $period = self::retakePeriod((int) $student->hemis_id, $student->level_code, (int) $debt['subject_id'], (string) $debt['lesson_date']);
            $debt['deadline'] = $period['deadline'];
            if ($period['excused']) {
                $debt['book_from'] = $period['book_from'];
                $debt['excused'] = true;
                // Moodle o'z muddati bo'yicha "expired" desa ham sababli oraliq ichida ochiq
                if (($debt['state'] ?? '') === 'expired' && $debt['deadline'] >= $today) {
                    $debt['state'] = 'start';
                    $debt['can_book'] = true;
                }
            }
            if ($debt['deadline'] < $today && !in_array($debt['state'] ?? '', ['done', 'continue'], true)) {
                $debt['state'] = 'expired';
                $debt['can_book'] = false;
            }

            return $debt;
        }, $debts);
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

    /** Test markazi Moodle'da bekor qilgan bron (moodle_code) */
    public const CODE_ADMIN_CANCELLED = 'admin_cancelled';

    /** Bir vaqt oralig'ida talaba ko'pi bilan shuncha fanni topshiradi */
    public const MAX_SUBJECTS_PER_SLOT = 2;

    /**
     * Talaba tanlay oladigan vaqt oraliqlari (test markazi ish vaqti bo'yicha):
     * tushlik (13:00–14:00) olib tashlanadi, shanba — qisqa kun (15:00 gacha).
     * Kun berilmasa — oddiy ish kuni.
     *
     * @return array<string, array{0: string, 1: string}> "09:00-10:00" => ["09:00", "10:00"]
     */
    public static function slotOptions(?Carbon $date = null): array
    {
        $from = Carbon::createFromFormat('H:i', (string) config('services.moodle.retake_slot_from', '09:00'));
        $to = Carbon::createFromFormat('H:i', $date && $date->isSaturday()
            ? self::saturdayTo()
            : (string) config('services.moodle.retake_slot_to', '17:00'));
        $minutes = max(15, (int) config('services.moodle.retake_slot_minutes', 60));
        $breaks = self::breaks();

        $slots = [];
        for ($start = $from->copy(); $start->copy()->addMinutes($minutes)->lessThanOrEqualTo($to); $start->addMinutes($minutes)) {
            $end = $start->copy()->addMinutes($minutes);
            [$s, $e] = [$start->format('H:i'), $end->format('H:i')];
            foreach ($breaks as [$bs, $be]) {
                if ($s < $be && $e > $bs) {
                    continue 2;
                }
            }
            $slots[$s . '-' . $e] = [$s, $e];
        }

        return $slots;
    }

    /** Shanba — qisqa kun: vaqt oraliqlari shu soatgacha */
    public static function saturdayTo(): string
    {
        return (string) config('services.moodle.retake_slot_to_saturday', '15:00');
    }

    /**
     * Tanaffuslar (tushlik): "13:00-14:00,..." → [["13:00", "14:00"], ...]
     *
     * @return array<int, array{0: string, 1: string}>
     */
    public static function breaks(): array
    {
        $breaks = [];
        foreach (explode(',', (string) config('services.moodle.retake_slot_breaks', '13:00-14:00')) as $part) {
            if (preg_match('/^\s*(\d{2}:\d{2})\s*-\s*(\d{2}:\d{2})\s*$/', $part, $m) && $m[1] < $m[2]) {
                $breaks[] = [$m[1], $m[2]];
            }
        }

        return $breaks;
    }

    /** Talaba tanlagan vaqt oralig'ini tekshiradi: xato matni yoki null */
    public static function validateSlot(Carbon $start, Carbon $end, ?string $deadline, ?string $from = null): ?string
    {
        if ($start->lessThanOrEqualTo(now())) {
            return "Boshlanish vaqti hozirgi vaqtdan keyin bo'lishi kerak.";
        }
        if ($end->lessThanOrEqualTo($start)) {
            return "Tugash vaqti boshlanish vaqtidan keyin bo'lishi kerak.";
        }
        if ($from && $start->toDateString() < $from) {
            return "Sababli ariza bo'yicha qayta topshirish " . Carbon::parse($from)->format('d.m.Y') . ' dan boshlanadi.';
        }
        if ($deadline && $start->toDateString() > $deadline) {
            return 'Sana qayta topshirish muddatidan (' . Carbon::parse($deadline)->format('d.m.Y') . ') kech bo\'lmasligi kerak.';
        }

        return null;
    }

    /** Shu kundagi NB / past baho yozuvlari (sahifada ko'rsatish, grade_id va muddat uchun) */
    public static function gradeRows(int $studentHemisId, int $subjectId, string $lessonDate): Collection
    {
        return StudentGrade::where('student_hemis_id', $studentHemisId)
            ->where('subject_id', $subjectId)
            ->whereDate('lesson_date', $lessonDate)
            // Chegara ko'tarilishidan oldin import qilingan past baholar "low_grade" belgisisiz
            ->where(fn ($q) => $q->whereIn('reason', ['absent', 'low_grade'])
                ->orWhere(fn ($w) => $w->whereNotNull('grade')->where('grade', '<', MarkingSystemScore::lowGradeLimit())))
            ->orderBy('id')
            ->get(['id', 'grade', 'reason', 'retake_grade', 'deadline', 'lesson_date']);
    }

    /** Admin sozlamalaridagi kurs bo'yicha qayta topshirish kunlari */
    public static function deadlineDays($levelCode): int
    {
        return (int) (Deadline::where('level_code', $levelCode)->value('deadline_days') ?? self::DEFAULT_DEADLINE_DAYS);
    }

    /**
     * Bitta bahoning qayta topshirish muddati: saqlangan student_grades.deadline,
     * bo'sh bo'lsa — dars sanasi + sozlamadagi kunlar (import va jurnal yozganidek).
     */
    public static function rowDeadline($deadline, $lessonDate, int $days): Carbon
    {
        return $deadline
            ? Carbon::parse($deadline)
            : Carbon::parse($lessonDate)->addDays($days)->endOfDay();
    }

    /** Mavzu (talaba + fan + kun) muddati LMS bo'yicha: shu kundagi juftliklarning eng yaqini */
    public static function lmsDeadline(int $studentHemisId, $levelCode, int $subjectId, string $lessonDate): Carbon
    {
        $days = self::deadlineDays($levelCode);
        $rows = self::gradeRows($studentHemisId, $subjectId, $lessonDate);

        return $rows->isEmpty()
            ? self::rowDeadline(null, $lessonDate, $days)
            : $rows->map(fn ($r) => self::rowDeadline($r->deadline, $r->lesson_date, $days))->min();
    }

    /**
     * Dars sanasi tasdiqlangan sababli arizaning davriga tushsa — arizada shu fan
     * uchun belgilangan JN qayta topshirish oralig'i [Y-m-d dan, Y-m-d gacha]
     * (yangi arizalarda shu darsning bir kunlik retake kuni).
     * Bajarilmagan (missed) oraliq keyingi arizada qayta belgilangan bo'lsa
     * (o'sha original_date bilan) — eng so'nggisi amalda.
     *
     * @return array{0: string, 1: string}|null
     */
    public static function excuseWindow(int $studentHemisId, int $subjectId, string $lessonDate): ?array
    {
        $jn = fn () => AbsenceExcuseMakeup::where('subject_id', (string) $subjectId)
            ->where('assessment_type', 'jn')
            ->where('jn_submitted', false)
            ->whereNotNull('makeup_date');
        $approved = fn ($q) => $q->where('status', 'approved')->where('student_hemis_id', (string) $studentHemisId);

        $covering = $jn()->whereHas('absenceExcuse', fn ($q) => $approved($q)
            ->whereDate('start_date', '<=', $lessonDate)
            ->whereDate('end_date', '>=', $lessonDate))
            ->get(['id', 'original_date']);
        if ($covering->isEmpty()) {
            return null;
        }

        $candidates = $jn()->whereHas('absenceExcuse', $approved)
            ->where('status', '!=', 'missed')
            ->where(function ($q) use ($covering) {
                foreach ($covering->pluck('original_date')->filter()->unique() as $date) {
                    $q->orWhereDate('original_date', Carbon::parse($date)->toDateString());
                }
                $q->orWhereIn('id', $covering->pluck('id'));
            })
            ->orderByDesc('id')
            ->get(['absence_excuse_id', 'original_date', 'makeup_date', 'makeup_end_date']);
        if ($candidates->isEmpty()) {
            return null;
        }

        // Yangi arizalarda har bir dars kuniga bitta kunlik retake — aynan shu darsniki.
        // Topilmasa (eski arizalar: fanga bitta oraliq) — eng so'nggi arizaning oralig'i.
        $day = Carbon::parse($lessonDate)->toDateString();
        $exact = $candidates->first(fn ($m) => Carbon::parse($m->original_date)->toDateString() === $day);
        $rows = $exact
            ? collect([$exact])
            : $candidates->where('absence_excuse_id', $candidates->first()->absence_excuse_id);

        return [
            $rows->map(fn ($m) => Carbon::parse($m->makeup_date)->toDateString())->min(),
            $rows->map(fn ($m) => Carbon::parse($m->makeup_end_date ?? $m->makeup_date)->toDateString())->max(),
        ];
    }

    /**
     * Mavzuning qayta topshirish davri LMS bo'yicha (bron sahifasi va Moodle
     * uchun API bir xil hisoblaydi): sababli ariza oralig'i, bo'lmasa sozlama.
     *
     * @return array{deadline: string, book_from: ?string, excused: bool} sanalar Y-m-d, deadline shu kun oxirigacha
     */
    public static function retakePeriod(int $studentHemisId, $levelCode, int $subjectId, string $lessonDate): array
    {
        if ($window = self::excuseWindow($studentHemisId, $subjectId, $lessonDate)) {
            return ['deadline' => $window[1], 'book_from' => $window[0], 'excused' => true];
        }

        return [
            'deadline' => self::lmsDeadline($studentHemisId, $levelCode, $subjectId, $lessonDate)->toDateString(),
            'book_from' => null,
            'excused' => false,
        ];
    }

    /**
     * Talabaning boshqa mavzular bo'yicha amaldagi bronlari (bugundan boshlab):
     * sababli arizada bir kunga bir nechta fan retake'i tushadi — bir vaqtda
     * ikkita fanni topshirib bo'lmaydi.
     */
    public static function otherBookings(int $studentHemisId, int $subjectId, string $lessonDate): Collection
    {
        return RetakeBooking::where('student_hemis_id', $studentHemisId)
            ->where('status', RetakeBooking::STATUS_BOOKED)
            ->where('slot_end', '>', now())
            ->where(fn ($q) => $q->where('subject_id', '!=', $subjectId)->orWhereDate('lesson_date', '!=', $lessonDate))
            ->orderBy('slot_start')
            ->get();
    }

    /** Tanlangan vaqt boshqa mavzu broni bilan ustma-ust tushsa — o'sha bron */
    public static function overlappingBooking(int $studentHemisId, int $subjectId, string $lessonDate, Carbon $start, Carbon $end): ?RetakeBooking
    {
        return self::overlappingBookings($studentHemisId, $subjectId, $lessonDate, $start, $end)->first();
    }

    /** Tanlangan vaqt bilan ustma-ust tushadigan boshqa mavzular bronlari */
    public static function overlappingBookings(int $studentHemisId, int $subjectId, string $lessonDate, Carbon $start, Carbon $end): Collection
    {
        return self::otherBookings($studentHemisId, $subjectId, $lessonDate)
            ->filter(fn (RetakeBooking $b) => $b->slot_start->lt($end) && $b->slot_end->gt($start))
            ->values();
    }

    /**
     * Tanlangan vaqt uchun xato matni yoki null: bir kunda bitta fandan bitta qayta
     * topshirish; bir vaqt oralig'ida ko'pi bilan MAX_SUBJECTS_PER_SLOT ta fan.
     */
    public static function bookingConflict(int $studentHemisId, int $subjectId, string $lessonDate, Carbon $start, Carbon $end): ?string
    {
        $others = self::otherBookings($studentHemisId, $subjectId, $lessonDate);
        $sameSubject = $others->first(fn (RetakeBooking $b) => (int) $b->subject_id === $subjectId
            && $b->slot_start->isSameDay($start));
        if ($sameSubject) {
            return 'Bir kunda bitta fandan faqat bitta qayta topshirish: shu kuni ' . ($sameSubject->subject_name ?: 'bu fan')
                . ' fanidan ' . $sameSubject->slot_start->format('H:i') . '–' . $sameSubject->slot_end->format('H:i')
                . ' vaqti tanlangan. Boshqa kunni tanlang.';
        }
        $overlap = self::overlappingBookings($studentHemisId, $subjectId, $lessonDate, $start, $end);
        if ($overlap->count() >= self::MAX_SUBJECTS_PER_SLOT) {
            return 'Bu vaqtda sizda allaqachon ' . $overlap->count() . ' ta fan bor ('
                . $overlap->map(fn (RetakeBooking $b) => $b->subject_name ?: 'boshqa fan')->implode(', ')
                . '). Bir vaqt oralig\'ida ko\'pi bilan ' . self::MAX_SUBJECTS_PER_SLOT . ' ta fan — boshqa vaqtni tanlang.';
        }

        return null;
    }

    /**
     * Sababli ariza tasdiqlanganda: arizadagi qayta topshirish kuniga to'g'ri kelmaydigan
     * bronlar (ariza ko'rib chiqilguncha oddiy muddat bo'yicha tanlangan) bekor qilinadi.
     * Moodle'ga bekor qilish cron (retake-bookings:sync) orqali boradi.
     *
     * @return Collection<int, array{booking: RetakeBooking, from: string, to: string}>
     */
    public static function cancelOutsideExcuse(AbsenceExcuse $excuse): Collection
    {
        $cancelled = collect();
        $makeups = $excuse->makeups()
            ->where('assessment_type', 'jn')
            ->where('jn_submitted', false)
            ->where('status', 'scheduled')
            ->whereNotNull('makeup_date')
            ->whereNotNull('original_date')
            ->get();

        foreach ($makeups as $makeup) {
            if (!ctype_digit((string) $makeup->subject_id)) {
                continue;
            }
            $from = $makeup->makeup_date->toDateString();
            $to = ($makeup->makeup_end_date ?? $makeup->makeup_date)->toDateString();
            $booking = self::activeBooking((int) $excuse->student_hemis_id, (int) $makeup->subject_id, $makeup->original_date->toDateString());
            if (!$booking) {
                continue;
            }
            $day = $booking->slot_start->toDateString();
            if ($day >= $from && $day <= $to) {
                continue;
            }

            $booking->update([
                'status' => RetakeBooking::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'moodle_sync' => RetakeBooking::SYNC_PENDING,
                'sync_attempts' => 0,
            ]);
            $cancelled->push(['booking' => $booking, 'from' => $from, 'to' => $to]);
        }

        return $cancelled;
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
            // Faqat hali o'tmagan bron qayta ishlatiladi: o'tib ketgani (kelib topshirilgan yoki
            // kelinmagan) Moodle'da urinish tarixi — ustidan yozilmaydi va bekor qilinmaydi
            $booked = RetakeBooking::forDebt($studentHemisId, $subjectId, $lessonDate)
                ->where('status', RetakeBooking::STATUS_BOOKED)
                ->where('slot_end', '>', now())
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
            $result = $this->client->bookRetake($this->bookPayload($booking));
        } catch (MoodleRetakeException $e) {
            $this->markFailed($booking, $e);

            return [
                'type' => 'warning',
                'message' => 'Bron bekor qilindi, Moodle bilan sinxronlash kutilmoqda.',
                'booking' => $booking,
            ];
        }

        if (!$result['ok']) {
            // Moodle rad etdi (masalan, vaqti boshlangan — "started"): bron amalda qoladi
            $this->restoreBooked($booking, $result);

            return [
                'type' => 'error',
                'message' => $result['message'] !== '' ? $result['message'] : "Bronni bekor qilib bo'lmadi.",
                'booking' => $booking,
            ];
        }

        $this->markSynced($booking, $result);

        return ['type' => 'success', 'message' => 'Bron bekor qilindi.', 'booking' => $booking];
    }

    /** Moodle bekor qilishni rad etgan bron — LMS'da ham amaldagi holatiga qaytadi */
    private function restoreBooked(RetakeBooking $booking, array $result): void
    {
        $booking->update([
            'status' => RetakeBooking::STATUS_BOOKED,
            'cancelled_at' => null,
            'moodle_sync' => RetakeBooking::SYNC_OK,
            'moodle_code' => $result['code'],
            'moodle_message' => $result['message'],
            'synced_at' => now(),
        ]);
    }

    /**
     * Cron: Moodle'ga yetib bormagan yozuvni qayta yuborish.
     * true — Moodle javob berdi (saqlandi yoki rad etildi), false — yana yetib bormadi.
     */
    public function sync(RetakeBooking $booking): bool
    {
        try {
            // bookPayload status ni yozuvdan oladi: booked yoki cancelled
            $result = $this->client->bookRetake($this->bookPayload($booking));
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
        if ($booking->status === RetakeBooking::STATUS_CANCELLED && !$result['ok'] && $result['code'] === 'started') {
            // Bekor qilish kech yetib bordi — Moodle'da bron vaqti boshlangan, u amalda qoladi
            $this->restoreBooked($booking, $result);

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

    /**
     * book_retake parametrlari. Bekor qilishda ham to'liq yuboriladi (status=cancelled):
     * Moodle bronni faqat booking_id bilan topa olmasa ham talaba/fan/sana bo'yicha topsin.
     */
    private function bookPayload(RetakeBooking $booking): array
    {
        return [
            'booking_id' => $booking->id,
            'student_hemis_id' => $booking->student_hemis_id,
            'subject_id' => $booking->subject_id,
            'lesson_date' => $booking->lesson_date->format('Y-m-d'),
            'slot_start' => $booking->slot_start->format('Y-m-d H:i'),
            'slot_end' => $booking->slot_end->format('Y-m-d H:i'),
            'status' => $booking->status === RetakeBooking::STATUS_CANCELLED ? 'cancelled' : 'booked',
        ];
    }

    private function markSynced(RetakeBooking $booking, array $result): void
    {
        // Muddat LMS bo'yicha — Moodle qaytargan deadline yozilmaydi
        $booking->update([
            'moodle_sync' => RetakeBooking::SYNC_OK,
            'moodle_code' => $result['code'],
            'moodle_message' => $result['message'],
            'synced_at' => now(),
        ]);
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
