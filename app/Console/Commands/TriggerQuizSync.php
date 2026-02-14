<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TriggerQuizSync extends Command
{
    protected $signature = 'quiz:trigger-moodle-sync';

    protected $description = 'Moodle serveridagi quiz natijalar eksportini ishga tushirish';

    public function handle(): int
    {
        $url = config('services.moodle.cron_url');
        $secret = config('services.moodle.sync_secret');

        if (empty($url)) {
            $this->error('MOODLE_CRON_URL sozlanmagan. .env faylida MOODLE_CRON_URL ni belgilang.');
            Log::warning('quiz:trigger-moodle-sync — MOODLE_CRON_URL sozlanmagan');
            return self::FAILURE;
        }

        $this->info("Moodle quiz sync triggerlanmoqda: {$url}");
        Log::info("quiz:trigger-moodle-sync — so'rov yuborilmoqda: {$url}");

        try {
            $response = Http::timeout(120)
                ->withHeaders([
                    'X-SYNC-SECRET' => $secret ?? '',
                ])
                ->post($url);

            if ($response->successful()) {
                $body = $response->json() ?? $response->body();
                $this->info('Moodle javob berdi: ' . json_encode($body, JSON_UNESCAPED_UNICODE));
                Log::info('quiz:trigger-moodle-sync — muvaffaqiyatli', ['response' => $body]);
                return self::SUCCESS;
            }

            $this->error("Moodle xato javob berdi: HTTP {$response->status()}");
            Log::error('quiz:trigger-moodle-sync — xato', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);
            return self::FAILURE;

        } catch (\Exception $e) {
            $this->error("So'rov yuborishda xatolik: {$e->getMessage()}");
            Log::error('quiz:trigger-moodle-sync — exception', ['error' => $e->getMessage()]);
            return self::FAILURE;
        }
    }
}
