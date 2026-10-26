<?php

namespace App\Services\Timetable;

use App\Models\Teacher;
use App\Models\TimetableBlockTicket;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * To'siq so'rovlari uchun Telegram: tugmali xabar yuborish ("Qabul qildim" / "Rad etaman" + mark havolasi)
 * va tugma bosilganda (callback_query) javobni yozish. Javobni faqat so'rov qabul qiluvchisi bera oladi —
 * chat id o'qituvchining tasdiqlangan telegram_chat_id siga mos kelishi kerak.
 */
class BlockTicketTelegram
{
    public const ROLE_NAMES = [
        'teacher' => 'dars o\'qituvchisi',
        'head' => 'kafedra mudiri',
        'blocker' => 'xonani band qilgan dars o\'qituvchisi',
    ];

    public const DAY_NAMES = [1 => 'Dushanba', 2 => 'Seshanba', 3 => 'Chorshanba', 4 => 'Payshanba', 5 => 'Juma', 6 => 'Shanba', 7 => 'Yakshanba'];

    private function token(): ?string
    {
        return config('services.telegram.bot_token');
    }

    public function text(TimetableBlockTicket $t, string $role): string
    {
        $b = (array) $t->blocker;
        $date = $t->lesson_date instanceof \DateTimeInterface ? $t->lesson_date : new \DateTime((string) $t->lesson_date);
        $what = match ($t->block_type) {
            'room' => 'Xona ' . ($b['room'] ?? '') . ' band',
            'teacher' => 'O\'qituvchi ' . ($b['teacher'] ?? '') . ' shu vaqtda boshqa darsda',
            default => 'To\'siq',
        };
        $by = trim(implode(' · ', array_filter([$b['source'] ?? null, $b['group'] ?? null, !empty($b['subject']) ? '«' . $b['subject'] . '»' : null,
            ($t->block_type === 'room' && !empty($b['teacher'])) ? 'o\'qituvchi: ' . $b['teacher'] : null,
            ($t->block_type === 'teacher' && !empty($b['room'])) ? 'xona: ' . $b['room'] : null, $b['time'] ?? null])));

        return "⛔ Dars jadvali: to'siq (№{$t->id})\n\n"
            . "Guruh: {$t->group_name}\nFan: {$t->subject_name}\n"
            . 'Sana: ' . $date->format('d.m.Y') . ' (' . (self::DAY_NAMES[(int) $date->format('N')] ?? '') . ") {$t->start_time}–{$t->end_time}\n\n"
            . $what . ($by !== '' ? ":\n" . $by : '') . "\n"
            . ($t->block_type === 'other' && $t->block_text ? $t->block_text . "\n" : '')
            . "\nSiz — " . (self::ROLE_NAMES[$role] ?? $role) . ".\n"
            . "Javobingizni tugma bilan bering. Kafedra mudiri mark'da shu dars uchun xonani yoki vaqtni o'zgartiradi "
            . "(kunini o'zgartirish — registrator ofisi tasdig'i bilan).";
    }

    public function keyboard(TimetableBlockTicket $t): array
    {
        $rows = [[
            ['text' => '✅ Qabul qildim', 'callback_data' => 'tb:' . $t->id . ':a'],
            ['text' => '❌ Rad etaman', 'callback_data' => 'tb:' . $t->id . ':d'],
        ]];
        $url = url('/admin/dars-jadvali-tuzish') . '?ticket=' . $t->id;
        if (str_starts_with($url, 'https://')) {   // Telegram URL tugmasi faqat https
            $rows[] = [['text' => 'mark\'da ochish', 'url' => $url]];
        }

        return ['inline_keyboard' => $rows];
    }

    /** Tugmali xabar; message_id qaytaradi (yoki null). */
    public function send(string $chatId, string $text, ?array $keyboard = null): ?int
    {
        $token = $this->token();
        if (!$token || $chatId === '') {
            return null;
        }
        try {
            $r = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/sendMessage", array_filter([
                'chat_id' => $chatId,
                'text' => $text,
                'reply_markup' => $keyboard ? json_encode($keyboard) : null,
            ]));

            return $r->successful() ? (int) ($r->json('result.message_id') ?? 0) ?: null : null;
        } catch (\Throwable $e) {
            Log::warning('Block ticket telegram: ' . $e->getMessage());

            return null;
        }
    }

    /** Hal qilingani haqida qisqa xabar barcha qabul qiluvchilarga. */
    public function broadcast(TimetableBlockTicket $t, string $text): void
    {
        foreach ((array) $t->recipients as $r) {
            if (!empty($r['chat_id'])) {
                $this->send((string) $r['chat_id'], $text);
            }
        }
    }

    /** Telegram callback_query ("tb:ID:a|d"); qayta ishlangan bo'lsa true. */
    public function callback(array $cq): bool
    {
        $data = (string) ($cq['data'] ?? '');
        if (!preg_match('/^tb:(\d+):(a|d)$/', $data, $m)) {
            return false;
        }
        $chatId = (string) ($cq['message']['chat']['id'] ?? $cq['from']['id'] ?? '');
        $teacher = $chatId !== '' ? Teacher::where('telegram_chat_id', $chatId)->first() : null;
        $answer = 'So\'rov topilmadi.';
        if ($teacher) {
            $res = $this->respond((int) $m[1], $teacher->id, $m[2] === 'a' ? 'accepted' : 'declined', null);
            $answer = $res ?? 'Siz bu so\'rovga javob bera olmaysiz.';
        }
        $this->answerCallback((string) ($cq['id'] ?? ''), $answer);
        if (!empty($cq['message']['message_id']) && $chatId !== '') {
            $this->appendToMessage($chatId, (int) $cq['message']['message_id'], (string) ($cq['message']['text'] ?? ''), $answer);
        }

        return true;
    }

    /**
     * Qabul qiluvchining javobini yozadi (veb yoki Telegram). Matnli natija yoki null (ruxsat yo'q).
     */
    public function respond(int $ticketId, int $teacherId, string $response, ?string $note): ?string
    {
        return DB::transaction(function () use ($ticketId, $teacherId, $response, $note) {
            $t = TimetableBlockTicket::lockForUpdate()->find($ticketId);
            if (!$t) {
                return null;
            }
            $recipients = (array) $t->recipients;
            $found = false;
            foreach ($recipients as &$r) {
                if ((int) ($r['teacher_id'] ?? 0) === $teacherId) {
                    $r['response'] = $response;
                    $r['note'] = $note;
                    $r['at'] = now()->toDateTimeString();
                    $found = true;
                }
            }
            unset($r);
            if (!$found) {
                return null;
            }
            $t->recipients = $recipients;
            $t->save();

            return $response === 'accepted' ? 'Javobingiz yozildi: qabul qildingiz.' : 'Javobingiz yozildi: rad etdingiz.';
        });
    }

    private function answerCallback(string $id, string $text): void
    {
        $token = $this->token();
        if (!$token || $id === '') {
            return;
        }
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/answerCallbackQuery", ['callback_query_id' => $id, 'text' => $text]);
        } catch (\Throwable $e) {
            Log::warning('answerCallbackQuery: ' . $e->getMessage());
        }
    }

    private function appendToMessage(string $chatId, int $messageId, string $text, string $line): void
    {
        $token = $this->token();
        if (!$token) {
            return;
        }
        try {
            Http::timeout(10)->post("https://api.telegram.org/bot{$token}/editMessageText", [
                'chat_id' => $chatId, 'message_id' => $messageId, 'text' => mb_substr($text . "\n\n— " . $line, 0, 4000),
            ]);
        } catch (\Throwable $e) {
            Log::warning('editMessageText: ' . $e->getMessage());
        }
    }
}
