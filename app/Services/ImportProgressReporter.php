<?php

namespace App\Services;

use Carbon\Carbon;

class ImportProgressReporter
{
    private TelegramService $telegram;
    private string $chatId;
    private string $dateStr;
    private ?int $messageId = null;
    private array $steps = [];
    private float $lastEditTime = 0;
    private int $editInterval;

    public function __construct(TelegramService $telegram, string $chatId, string $dateStr, int $editInterval = 10)
    {
        $this->telegram = $telegram;
        $this->chatId = $chatId;
        $this->dateStr = $dateStr;
        $this->editInterval = $editInterval;
    }

    /**
     * Boshlang'ich xabarni yuborish
     */
    public function start(): void
    {
        $text = "📅 Bugungi sana: {$this->dateStr}";
        $this->messageId = $this->telegram->sendAndGetId($this->chatId, $text);
    }

    /**
     * Yangi bosqichni boshlash
     * @param string $runningLabel "HEMIS dan jadval yangilanmoqda"
     * @param string $doneLabel "Jadval muvaffaqiyatli yangilandi"
     */
    public function startStep(string $runningLabel, string $doneLabel): void
    {
        $this->steps[] = [
            'running_label' => $runningLabel,
            'done_label' => $doneLabel,
            'status' => 'running',
            'start' => Carbon::now()->format('H:i:s'),
            'end' => null,
            'context' => '',
            'page' => 0,
            'total' => 0,
        ];
        $this->forceUpdate();
    }

    /**
     * Bosqich kontekstini o'rnatish (masalan: "2/7 kun (2026-02-15)")
     * Kontekst o'zgarganda sahifa progressi resetlanadi
     */
    public function setStepContext(string $context): void
    {
        if (empty($this->steps)) {
            return;
        }

        $lastIdx = count($this->steps) - 1;
        $this->steps[$lastIdx]['context'] = $context;
        $this->steps[$lastIdx]['page'] = 0;
        $this->steps[$lastIdx]['total'] = 0;
        $this->forceUpdate();
    }

    /**
     * Sahifa progressini yangilash (har editInterval sekundda)
     */
    public function updateProgress(int $page, int $total): void
    {
        if (empty($this->steps)) {
            return;
        }

        $lastIdx = count($this->steps) - 1;
        $this->steps[$lastIdx]['page'] = $page;
        $this->steps[$lastIdx]['total'] = $total;

        if (microtime(true) - $this->lastEditTime >= $this->editInterval) {
            $this->forceUpdate();
        }
    }

    /**
     * Joriy bosqichni muvaffaqiyatli tugatish
     */
    public function completeStep(): void
    {
        if (empty($this->steps)) {
            return;
        }

        $lastIdx = count($this->steps) - 1;
        $this->steps[$lastIdx]['status'] = 'done';
        $this->steps[$lastIdx]['end'] = Carbon::now()->format('H:i:s');
        $this->forceUpdate();
    }

    /**
     * Joriy bosqichni xato bilan tugatish
     */
    public function failStep(string $error = ''): void
    {
        if (empty($this->steps)) {
            return;
        }

        $lastIdx = count($this->steps) - 1;
        $this->steps[$lastIdx]['status'] = 'failed';
        $this->steps[$lastIdx]['end'] = Carbon::now()->format('H:i:s');
        $this->steps[$lastIdx]['error'] = $error;
        $this->forceUpdate();
    }

    /**
     * Telegram xabarni darhol yangilash
     */
    private function forceUpdate(): void
    {
        if (!$this->messageId) {
            return;
        }

        $this->telegram->editMessage($this->chatId, $this->messageId, $this->buildText());
        $this->lastEditTime = microtime(true);
    }

    /**
     * Xabar matnini shakllantirish
     */
    private function buildText(): string
    {
        $lines = ["📅 Bugungi sana: {$this->dateStr}"];

        foreach ($this->steps as $step) {
            $lines[] = '';

            $contextInfo = !empty($step['context']) ? " [{$step['context']}]" : '';
            $pageInfo = '';
            if ($step['total'] > 0) {
                $pageInfo = " {$step['page']}/{$step['total']} sahifa";
            }

            if ($step['status'] === 'running') {
                $lines[] = "⏳ {$step['running_label']}...{$contextInfo}{$pageInfo} ({$step['start']})";
            } elseif ($step['status'] === 'done') {
                $lines[] = "✅ {$step['done_label']}.{$contextInfo}{$pageInfo} ({$step['start']} → {$step['end']})";
            } elseif ($step['status'] === 'failed') {
                $error = !empty($step['error']) ? ": {$step['error']}" : '';
                $lines[] = "❌ {$step['running_label']} xato{$error} ({$step['start']} → {$step['end']})";
            }
        }

        return implode("\n", $lines);
    }
}
