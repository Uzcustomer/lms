<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringIstisno;
use App\Models\MonitoringRun;
use App\Models\Notification;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\User;
use App\Services\TelegramService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Monitoring xabarlari: mark ichki bildirishnomasi + Telegram (chat_id bo'lsa).
 * Xabar yuborilmasa ham asosiy jarayon to'xtamaydi — faqat logga yoziladi.
 */
class IstisnoNotifier
{
    public function __construct(private TelegramService $telegram)
    {
    }

    public function assigned(MonitoringIstisno $istisno): void
    {
        $owner = $istisno->owner;
        if ($owner === null || $istisno->warning_only) {
            return;
        }

        $code = $istisno->detector?->code ?? '';
        $due = $istisno->due_at?->format('d.m.Y') ?? '—';
        $title = "Monitoring: sizga istisno biriktirildi ({$code})";
        $body = "{$istisno->subject_label}\nJavob muddati: {$due}";

        $this->send($owner, $title, $body, self::link('admin.monitoring.istisnolar.show', $istisno), [
            'monitoring_istisno_id' => $istisno->id,
        ]);
    }

    public function runFailed(MonitoringRun $run, Throwable $e): void
    {
        $code = $run->detector?->code ?? '?';
        $title = "Monitoring: {$code} detektori xato bilan tugadi";
        $body = mb_substr($e->getMessage(), 0, 500);

        foreach ($this->managers() as $manager) {
            $this->send($manager, $title, $body, self::link('admin.monitoring.detektorlar.index'), ['monitoring_run_id' => $run->id]);
        }

        try {
            $this->telegram->notify("⚠️ {$title}\n" . e($body));
        } catch (Throwable $t) {
            Log::warning('[Monitoring] admin Telegram: ' . $t->getMessage());
        }
    }

    /** Ixtiyoriy xabar (masalan E-1 bo'yicha ijara kotibiga). */
    public function send(Model $recipient, string $title, string $body, ?string $link = null, array $data = []): bool
    {
        $delivered = false;

        try {
            if ($recipient instanceof Teacher) {
                TeacherNotification::create([
                    'teacher_id' => $recipient->id,
                    'type' => 'monitoring',
                    'title' => $title,
                    'message' => $body,
                    'link' => $link,
                    'data' => $data,
                ]);
                $delivered = true;
            } elseif ($recipient instanceof User) {
                Notification::create([
                    'recipient_id' => $recipient->id,
                    'recipient_type' => User::class,
                    'subject' => $title,
                    'body' => $body,
                    'type' => Notification::TYPE_ALERT,
                    'url' => $link,
                    'data' => $data,
                    'is_draft' => false,
                    'sent_at' => now(),
                ]);
                $delivered = true;
            }
        } catch (Throwable $e) {
            Log::warning('[Monitoring] ichki bildirishnoma: ' . $e->getMessage());
        }

        $chatId = $recipient->telegram_chat_id ?? null;
        if (!empty($chatId)) {
            try {
                $text = '<b>' . e($title) . "</b>\n" . e($body) . ($link ? "\n" . e($link) : '');
                $delivered = $this->telegram->sendToUser((string) $chatId, $text) || $delivered;
            } catch (Throwable $e) {
                Log::warning('[Monitoring] Telegram: ' . $e->getMessage());
            }
        }

        return $delivered;
    }

    private static function link(string $name, $parameters = []): ?string
    {
        try {
            return route($name, $parameters);
        } catch (Throwable) {
            return null;
        }
    }

    /** @return iterable<Model> */
    private function managers(): iterable
    {
        $roles = config('monitoring.roles.manage', []);
        $monitoringOnly = array_values(array_intersect($roles, ['monitoring_admin'])) ?: $roles;

        try {
            yield from Teacher::whereHas('roles', fn ($q) => $q->whereIn('name', $monitoringOnly))->get();
            yield from User::whereHas('roles', fn ($q) => $q->whereIn('name', $monitoringOnly))->get();
        } catch (Throwable $e) {
            Log::warning('[Monitoring] administratorlarni topib bo\'lmadi: ' . $e->getMessage());
        }
    }
}
