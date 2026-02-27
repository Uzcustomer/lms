<?php

namespace App\Console\Commands;

use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

class RunNightlyImports extends Command
{
    protected $signature = 'nightly:run';

    protected $description = 'Kechki import jarayonlarini ketma-ket ishlatish (bitta Telegram xabar)';

    private TelegramService $telegram;
    private string $chatId;
    private ?int $messageId = null;
    private array $steps = [];
    private float $startTime;
    private float $lastProgressUpdate = 0;

    public function handle(TelegramService $telegram): int
    {
        $this->telegram = $telegram;
        $this->chatId = config('services.telegram.chat_id');
        $this->startTime = microtime(true);

        $dateStr = Carbon::now()->format('d.m.Y');
        $this->sendMessage("🌙 Kechki import — {$dateStr}\n\n⏳ Boshlanmoqda...");

        // 1-qadam: Jadval import
        $this->runStep(
            'Jadval import',
            fn () => Artisan::call('import:schedules', ['--silent' => true]),
        );

        // 2-qadam: Final import (baholar)
        $this->runStep(
            'Final import (baholar)',
            fn () => Artisan::call('student:import-data', ['--mode' => 'final', '--silent' => true]),
        );

        // 3-qadam: Davomat nazorati FINAL
        $this->runStep(
            'Davomat nazorati FINAL',
            fn () => Artisan::call('import:attendance-controls', ['--mode' => 'final', '--silent' => true]),
        );

        // Yakuniy xabar
        $totalElapsed = round((microtime(true) - $this->startTime) / 60, 1);
        $allSuccess = collect($this->steps)->every(fn ($s) => $s['status'] === 'done');
        $emoji = $allSuccess ? '✅' : '⚠️';
        $statusText = $allSuccess ? 'Barcha import muvaffaqiyatli' : 'Ba\'zi importlarda xato bor';

        $this->updateMessage("{$emoji} Kechki import tugadi — {$statusText} ({$totalElapsed} daq)");

        Log::info("[NightlyImports] Completed in {$totalElapsed} min");

        return $allSuccess ? self::SUCCESS : self::FAILURE;
    }

    private function runStep(string $name, \Closure $callback): void
    {
        $stepIdx = count($this->steps);
        $this->steps[] = [
            'name' => $name,
            'status' => 'running',
            'start' => Carbon::now()->format('H:i'),
            'end' => null,
            'error' => null,
            'detail' => '',
        ];

        // Sub-commandlar o'z progressini shu callback orqali yuboradi
        app()->instance('nightly.progress', function (string $detail) use ($stepIdx) {
            $this->steps[$stepIdx]['detail'] = $detail;

            // Telegram rate limit: 5 sekund
            $now = microtime(true);
            if ($now - $this->lastProgressUpdate >= 5) {
                $this->updateMessage();
                $this->lastProgressUpdate = $now;
            }
        });

        $this->updateMessage();
        $this->info("{$name} boshlanmoqda...");

        try {
            $exitCode = $callback();
            if ($exitCode !== 0 && $exitCode !== null) {
                $this->steps[$stepIdx]['status'] = 'failed';
                $this->steps[$stepIdx]['error'] = "Exit code: {$exitCode}";
            } else {
                $this->steps[$stepIdx]['status'] = 'done';
            }
            $this->steps[$stepIdx]['end'] = Carbon::now()->format('H:i');
            $this->info("{$name} tugadi (exit: {$exitCode}).");
        } catch (\Throwable $e) {
            $this->steps[$stepIdx]['status'] = 'failed';
            $this->steps[$stepIdx]['end'] = Carbon::now()->format('H:i');
            $this->steps[$stepIdx]['error'] = substr($e->getMessage(), 0, 150);
            $this->error("{$name} xato: " . $e->getMessage());
            Log::error("[NightlyImports] {$name} failed: " . $e->getMessage());
        }

        // Callback ni tozalash
        app()->forgetInstance('nightly.progress');

        // Yakuniy yangilash (rate limit o'tkazib)
        $this->lastProgressUpdate = 0;
        $this->updateMessage();
    }

    private function sendMessage(string $text): void
    {
        if (!$this->chatId) return;
        $this->messageId = $this->telegram->sendAndGetId($this->chatId, $text);
    }

    private function updateMessage(?string $footer = null): void
    {
        if (!$this->messageId || !$this->chatId) return;

        $dateStr = Carbon::now()->format('d.m.Y');
        $totalElapsed = round((microtime(true) - $this->startTime) / 60, 1);

        $lines = ["🌙 Kechki import — {$dateStr}"];
        $lines[] = '';

        foreach ($this->steps as $i => $step) {
            $num = $i + 1;
            $name = $step['name'];

            if ($step['status'] === 'done') {
                $lines[] = "✅ {$num}. {$name}  ({$step['start']} → {$step['end']})";
            } elseif ($step['status'] === 'failed') {
                $error = $step['error'] ? "\n     ↳ {$step['error']}" : '';
                $lines[] = "❌ {$num}. {$name}  ({$step['start']} → {$step['end']}){$error}";
            } elseif ($step['status'] === 'running') {
                $lines[] = "⏳ {$num}. {$name}...  ({$step['start']})";
            }

            // Batafsil ma'lumot (kunlik progress va h.k.)
            if (!empty($step['detail'])) {
                foreach (explode("\n", $step['detail']) as $dl) {
                    $lines[] = "     {$dl}";
                }
            }
        }

        $lines[] = '';
        if ($footer) {
            $lines[] = $footer;
        } else {
            $lines[] = "⏱ {$totalElapsed} daq";
        }

        $this->telegram->editMessage($this->chatId, $this->messageId, implode("\n", $lines));
    }
}
