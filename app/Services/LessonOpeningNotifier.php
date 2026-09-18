<?php

namespace App\Services;

use App\Models\LessonOpening;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Dars ochish so'rovi bo'yicha o'qituvchiga Telegram xabari.
 *
 * Qabul qiluvchilar: so'rovni yuborgan o'qituvchi (teacher_id) va o'sha kuni
 * jadvalda shu darsni o'tadigan o'qituvchi(lar). Ular ko'pincha bitta odam;
 * bir kishiga bir marta boradi. Telegram ulanmagan o'qituvchi o'tkazib
 * yuboriladi. Xabar yuborilmasa ham tasdiqlash to'xtamaydi.
 */
class LessonOpeningNotifier
{
    public function __construct(private readonly TelegramService $telegram)
    {
    }

    /** Barcha tasdiqlar olindi — dars ochildi: o'qituvchiga va registrator guruhiga. */
    public function opened(LessonOpening $opening): void
    {
        $this->announceToRegistrarGroup($opening);

        $deadline = $opening->deadline?->format('d.m.Y H:i');

        $this->send($opening, fn (Teacher $teacher, array $lesson) =>
            "✅ <b>Dars ochildi</b>\n\n"
            . "Hurmatli {$this->e($teacher->full_name)}!\n\n"
            . "{$lesson['subject']} fani bo'yicha {$lesson['group']} guruhining "
            . "{$lesson['date']} sanadagi darsi baho qo'yish uchun ochildi.\n\n"
            . ($deadline ? "⏰ Baho qo'yish muddati: <b>{$deadline}</b>\n\n" : '')
            . "Iltimos, muddatgacha baholarni kiritib qo'ying."
        );
    }

    /** Bir bosqich tasdiqladi, lekin boshqa tasdiqlar hali kutilmoqda. */
    public function approvedStep(LessonOpening $opening, string $stage): void
    {
        $waiting = array_map(
            fn ($s) => LessonOpening::STAGE_LABELS[$s] ?? $s,
            $opening->remainingStagesAfter($stage)
        );
        if (!$waiting) {
            return;
        }

        $by = LessonOpening::STAGE_LABELS[$stage] ?? $stage;

        $this->send($opening, fn (Teacher $teacher, array $lesson) =>
            "🕓 <b>Dars ochish so'rovi</b>\n\n"
            . "Hurmatli {$this->e($teacher->full_name)}!\n\n"
            . "{$lesson['subject']} fani, {$lesson['group']} guruhi, {$lesson['date']} sanadagi dars "
            . "bo'yicha so'rovingizni <b>{$this->e($by)}</b> tasdiqladi.\n\n"
            . "Dars ochilishi uchun yana tasdiq kutilmoqda: {$this->e(implode(', ', $waiting))}."
        );
    }

    /**
     * So'rov rad etildi. $wasOpen — dars allaqachon ochilgan edi va tasdiqlovchi
     * uni qaytarib oldi (adashib tasdiqlangan): dars yopiladi, registrator
     * guruhi ham xabardor qilinadi, chunki ochilgani haqida xabar u yerga borgan.
     */
    public function rejected(LessonOpening $opening, string $stage, bool $wasOpen = false): void
    {
        $by = LessonOpening::STAGE_LABELS[$stage] ?? $stage;
        $reason = trim((string) $opening->review_comment);

        $this->send($opening, fn (Teacher $teacher, array $lesson) =>
            ($wasOpen ? "🚫 <b>Ochilgan dars yopildi</b>\n\n" : "❌ <b>Dars ochish so'rovi rad etildi</b>\n\n")
            . "Hurmatli {$this->e($teacher->full_name)}!\n\n"
            . "{$lesson['subject']} fani, {$lesson['group']} guruhi, {$lesson['date']} sanadagi dars "
            . ($wasOpen
                ? "uchun berilgan ruxsatni <b>{$this->e($by)}</b> bekor qildi. Bu darsga endi baho qo'yib bo'lmaydi."
                : "bo'yicha so'rovingizni <b>{$this->e($by)}</b> rad etdi.")
            . ($reason !== '' ? "\n\nSabab: {$this->e($reason)}" : '')
        );

        if ($wasOpen) {
            $this->announceRevocationToRegistrarGroup($opening, $stage, $reason);
        }
    }

    private function announceRevocationToRegistrarGroup(LessonOpening $opening, string $stage, string $reason): void
    {
        $chatId = config('services.telegram.registrar_group_id');
        if (!$chatId) {
            return;
        }

        try {
            $lesson = $this->lessonInfo($opening);
            $teacherName = trim((string) $opening->teacher_name) ?: ($this->scheduleTeacherNames($opening) ?: "Noma'lum");
            $by = LessonOpening::STAGE_LABELS[$stage] ?? $stage;
            $decider = $opening->stageDecider($stage);

            $message = "🚫 <b>DARS OCHISH RUXSATI BEKOR QILINDI</b>\n\n"
                . "👤 O'qituvchi: <b>{$this->e($teacherName)}</b>\n"
                . "📚 Fan: {$lesson['subject']}\n"
                . "👥 Guruh: {$lesson['group']}\n"
                . "📅 Dars sanasi: {$lesson['date']}\n\n"
                . "Bekor qildi: {$this->e($by)}"
                . ($decider['name'] ? ' — ' . $this->e($decider['name']) : '')
                . ($decider['at'] ? ' (' . $decider['at']->timezone('Asia/Tashkent')->format('d.m.Y H:i') . ')' : '')
                . ($reason !== '' ? "\nSabab: {$this->e($reason)}" : '');

            $this->telegram->sendToUser((string) $chatId, $message);
        } catch (\Throwable $e) {
            Log::warning("Dars ochish ruxsati bekor qilingani haqida guruhga xabar yuborilmadi", [
                'opening_id' => $opening->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Registrator guruhiga (baho qo'yilmaganlar hisoboti boradigan guruh):
     * qaysi o'qituvchiga, qaysi dars ochildi va kim tasdiqladi.
     */
    private function announceToRegistrarGroup(LessonOpening $opening): void
    {
        $chatId = config('services.telegram.registrar_group_id');
        if (!$chatId) {
            return;
        }

        try {
            $lesson = $this->lessonInfo($opening);

            $teacherName = trim((string) $opening->teacher_name);
            if ($teacherName === '') {
                $teacherName = $this->scheduleTeacherNames($opening) ?: "Noma'lum";
            }

            $approvals = [];
            foreach ($opening->requiredStages() as $stage) {
                $decider = $opening->stageDecider($stage);
                $approvals[] = '• ' . $this->e(LessonOpening::STAGE_LABELS[$stage] ?? $stage)
                    . ' — ' . $this->e($decider['name'] ?: "noma'lum")
                    . ($decider['at'] ? ' (' . $decider['at']->timezone('Asia/Tashkent')->format('d.m.Y H:i') . ')' : '');
            }

            $deadline = $opening->deadline?->format('d.m.Y H:i');

            $message = "📂 <b>DARS OCHISHGA RUXSAT BERILDI</b>\n\n"
                . "👤 O'qituvchi: <b>{$this->e($teacherName)}</b>\n"
                . "📚 Fan: {$lesson['subject']}\n"
                . "👥 Guruh: {$lesson['group']}\n"
                . "📅 Dars sanasi: {$lesson['date']}\n"
                . ($opening->request_number ? "🔢 So'rov: {$opening->request_number}-so'rov\n" : '')
                . "\n✅ Tasdiqlaganlar:\n" . implode("\n", $approvals)
                . ($deadline ? "\n\n⏰ Baho qo'yish muddati: {$deadline}" : '');

            $this->telegram->sendToUser((string) $chatId, $message);
        } catch (\Throwable $e) {
            Log::warning("Dars ochilgani haqida registrator guruhiga xabar yuborilmadi", [
                'opening_id' => $opening->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** So'rovda o'qituvchi yozilmagan bo'lsa — o'sha kungi jadvaldagi o'qituvchi(lar). */
    private function scheduleTeacherNames(LessonOpening $opening): string
    {
        if (!$opening->lesson_date) {
            return '';
        }

        return DB::table('schedules')
            ->where('group_id', $opening->group_hemis_id)
            ->where('subject_id', $opening->subject_id)
            ->where('semester_code', $opening->semester_code)
            ->whereDate('lesson_date', $opening->lesson_date->format('Y-m-d'))
            ->whereNull('deleted_at')
            ->distinct()
            ->pluck('employee_name')
            ->filter()
            ->implode(', ');
    }

    private function send(LessonOpening $opening, callable $message): void
    {
        try {
            $recipients = $this->recipients($opening);
            if ($recipients->isEmpty()) {
                return;
            }

            $lesson = $this->lessonInfo($opening);
            foreach ($recipients as $teacher) {
                $this->telegram->sendToUser((string) $teacher->telegram_chat_id, $message($teacher, $lesson));
            }
        } catch (\Throwable $e) {
            Log::warning("Dars ochish so'rovi bo'yicha Telegram xabar yuborilmadi", [
                'opening_id' => $opening->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /** So'rov egasi + o'sha kungi jadvaldagi o'qituvchilar, Telegram ulanganlari. */
    private function recipients(LessonOpening $opening): Collection
    {
        $scheduleEmployeeIds = $opening->lesson_date
            ? DB::table('schedules')
                ->where('group_id', $opening->group_hemis_id)
                ->where('subject_id', $opening->subject_id)
                ->where('semester_code', $opening->semester_code)
                ->whereDate('lesson_date', $opening->lesson_date->format('Y-m-d'))
                ->whereNull('deleted_at')
                ->distinct()
                ->pluck('employee_id')
            : collect();

        return Teacher::query()
            ->whereNotNull('telegram_chat_id')
            ->where('telegram_chat_id', '!=', '')
            ->where(function ($query) use ($opening, $scheduleEmployeeIds) {
                $query->whereIn('hemis_id', $scheduleEmployeeIds->filter()->all());
                if ($opening->teacher_id) {
                    $query->orWhere('id', $opening->teacher_id);
                }
            })
            ->get(['id', 'full_name', 'telegram_chat_id'])
            ->unique('id')
            ->values();
    }

    /** Xabar uchun fan, guruh va sana — HTML uchun ekranlangan. */
    private function lessonInfo(LessonOpening $opening): array
    {
        $subject = DB::table('schedules')
            ->where('group_id', $opening->group_hemis_id)
            ->where('subject_id', $opening->subject_id)
            ->whereNull('deleted_at')
            ->value('subject_name') ?? "Noma'lum fan";

        $group = DB::table('groups')
            ->where('group_hemis_id', $opening->group_hemis_id)
            ->value('name') ?? "Noma'lum guruh";

        return [
            'subject' => '<b>' . $this->e($subject) . '</b>',
            'group' => '<b>' . $this->e($group) . '</b>',
            'date' => $opening->lesson_date?->format('d.m.Y') ?? '—',
        ];
    }

    private function e(?string $text): string
    {
        return htmlspecialchars((string) $text, ENT_NOQUOTES, 'UTF-8');
    }
}
