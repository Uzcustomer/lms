<?php

namespace App\Console\Commands;

use App\Services\ScheduleImportService;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ImportSchedules extends Command
{
    protected $signature = 'import:schedules {--silent : Telegram xabar yubormaslik}';

    protected $description = 'Import schedules from HEMIS API by current education year';

    public function handle(ScheduleImportService $service): int
    {
        ini_set('memory_limit', '512M');

        // OOM crash himoyasi
        register_shutdown_function(function () {
            $error = error_get_last();
            if ($error && in_array($error['type'], [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR])) {
                $msg = "💀 import:schedules CRASH: {$error['message']} ({$error['file']}:{$error['line']})";

                try {
                    Log::channel('import_schedule')->critical($msg);
                } catch (\Throwable $e) {
                    error_log("[import_schedule] CRITICAL: {$msg}");
                }

                if (!$this->option('silent')) {
                    try {
                        app(TelegramService::class)->send(
                            config('services.telegram.chat_id'),
                            $msg
                        );
                    } catch (\Throwable $e) {
                        // Telegram ham ishlamasa, logga yozilgan
                    }
                }
            }
        });

        $this->info('Jadval importi boshlanmoqda (joriy o\'quv yili bo\'yicha)...');

        try {
            $service->importByEducationYear(fn($msg) => $this->line($msg), (bool)$this->option('silent'));
            $this->info('Jadval importi muvaffaqiyatli yakunlandi.');
            return self::SUCCESS;
        } catch (\Throwable $e) {
            $errorMsg = $e->getMessage();
            $this->error('Xatolik: ' . $errorMsg);

            try {
                Log::channel('import_schedule')->error("Jadval import exception: {$errorMsg}", [
                    'file' => $e->getFile() . ':' . $e->getLine(),
                    'trace' => substr($e->getTraceAsString(), 0, 1000),
                ]);
            } catch (\Throwable $logEx) {
                error_log("[import_schedule] ERROR: Jadval import exception: {$errorMsg}");
            }

            return self::FAILURE;
        }
    }
}
