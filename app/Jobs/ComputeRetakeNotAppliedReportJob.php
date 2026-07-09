<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * "Qarzdorlar — academic records" (retake-not-applied) hisobotini fon rejimida
 * hisoblaydi. To'liq qatorlar ro'yxati diskka JSON qilib yoziladi, holat
 * (percent/message) cache orqali polling qilinadi. Sahifalash/saralash/Excel
 * keyin tayyor natijadan calc_key bilan o'qiladi — qayta hisoblanmaydi.
 */
class ComputeRetakeNotAppliedReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    public function __construct(
        private array $filters,
        private string $calcKey,
    ) {
    }

    public static function dirPath(): string
    {
        $dir = storage_path('app/report-calcs');
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return $dir;
    }

    public static function pathFor(string $calcKey): string
    {
        return self::dirPath() . '/' . md5($calcKey) . '.json';
    }

    /**
     * Tayyor natija qatorlarini o'qish. Fayl yo'q/buzilgan bo'lsa null —
     * chaqiruvchi qayta hisoblashni boshlashi kerak (HTTP 410).
     */
    public static function loadRows(string $calcKey): ?array
    {
        $path = self::pathFor($calcKey);
        if (!is_file($path)) {
            return null;
        }

        $decoded = json_decode((string) @file_get_contents($path), true);

        return is_array($decoded) ? $decoded : null;
    }

    public function handle(): void
    {
        ini_set('memory_limit', '1024M');

        try {
            $this->updateStatus('running', 'Hisob boshlandi...', 1);

            $rows = app(\App\Http\Controllers\Admin\ReportController::class)
                ->computeRetakeNotAppliedRows(
                    $this->filters,
                    fn ($percent, $message) => $this->updateStatus('running', (string) $message, (float) $percent)
                );

            // Eski natija fayllarini tozalash (24 soatdan katta).
            foreach (glob(self::dirPath() . '/*.json') ?: [] as $old) {
                if (@filemtime($old) < time() - 86400) {
                    @unlink($old);
                }
            }

            file_put_contents(self::pathFor($this->calcKey), json_encode($rows));

            $this->updateStatus('done', 'Tayyor', 100, count($rows));
        } catch (\Throwable $e) {
            Log::error('[ComputeRetakeNotAppliedReportJob] Xato: ' . $e->getMessage(), [
                'trace' => mb_substr($e->getTraceAsString(), 0, 800),
            ]);
            $this->updateStatus('failed', 'Xato: ' . mb_substr($e->getMessage(), 0, 160), 0);
        }
    }

    public function failed(\Throwable $exception): void
    {
        $this->updateStatus('failed', 'Xato: ' . mb_substr($exception->getMessage(), 0, 160), 0);
    }

    private function updateStatus(string $status, string $message, float $percent, ?int $total = null): void
    {
        Cache::put($this->calcKey, [
            'status' => $status,
            'message' => $message,
            'percent' => $percent,
            'total' => $total,
            'updated_at' => now()->toDateTimeString(),
        ], 1800);
    }
}
