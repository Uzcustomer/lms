<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Butun doska bo'yicha joylanmagan kartalar hisoboti (XLSX) — dars jadvali sahifasidagi "Butun doska (Excel)".
 * Katta doskada 60 soniyadan oshadi (nginx 504), shuning uchun fonda tayyorlanadi: holat keshga yoziladi
 * (status, percent, step_label, message), sahifa progress bar ko'rsatadi, tayyor fayl storage/app/reports da 1 kun turadi.
 */
class BuildUnplacedReportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;
    public int $tries = 1;

    public function __construct(public int $boardId, public string $token, public array $opts = [])
    {
    }

    public static function stateKey(string $token): string
    {
        return 'timetable_unplaced_report:' . $token;
    }

    /** Foydalanuvchining oxirgi hisoboti (tokeni) — bir vaqtda bittadan ortiq ishga tushmasin. */
    public static function userKey(string $user): string
    {
        return 'timetable_unplaced_report_user:' . sha1($user);
    }

    public static function path(string $token): string
    {
        return storage_path('app/reports/unplaced_' . preg_replace('/[^A-Za-z0-9]/', '', $token) . '.xlsx');
    }

    /** Sahifaga qaytariladigan maydonlar (foydalanuvchi va doska — ichki). 100% — faqat tayyor hisobot. */
    public static function publicState(array $state): array
    {
        $status = $state['status'] ?? 'error';

        return [
            'status' => $status,
            'percent' => $status === 'done' ? 100 : max(0, min(99, (int) ($state['percent'] ?? 0))),
            'step_label' => (string) ($state['step_label'] ?? ''),
            'message' => (string) ($state['message'] ?? ''),
        ];
    }

    /** 1 kundan eski hisobot fayllarini o'chiradi. */
    public static function sweep(): void
    {
        foreach (glob(storage_path('app/reports/unplaced_*.xlsx')) ?: [] as $file) {
            if (@filemtime($file) < time() - 86400) {
                @unlink($file);
            }
        }
    }

    public function handle(): void
    {
        // Worker o'zi yaratgan eski fayllarni o'chira oladi (veb-server foydalanuvchisi — yo'q).
        self::sweep();
        $state = Cache::get(self::stateKey($this->token), []);
        $save = function (array $patch) use (&$state): void {
            $state = $patch + $state;
            $state['updated_at'] = now()->toDateTimeString();
            Cache::put(self::stateKey($this->token), $state, now()->addDay());
        };
        $save(['status' => 'running', 'percent' => 0, 'step_label' => 'Boshlanmoqda…', 'message' => 'Hisobot tayyorlanmoqda…',
            'started_at' => now()->toDateTimeString()]);

        try {
            $board = TimetableBoard::findOrFail($this->boardId);
            $tmp = app(TimetableController::class)->buildUnplacedReport($board, $this->opts,
                fn (int $percent, string $label) => $save(['percent' => $percent, 'step_label' => $label, 'message' => $label]));
            $path = self::path($this->token);
            // Fayl worker foydalanuvchisi nomidan yoziladi, o'qiydigan esa php-fpm (www-data): tempnam fayli 0600 bo'ladi,
            // rename shu ruxsatni saqlaydi — papka 0775, fayl 0664 qilinadi (umask'ga bog'liq emas).
            $dir = dirname($path);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            @chmod($dir, 0775);
            if (!@rename($tmp, $path)) {
                copy($tmp, $path);
                @unlink($tmp);
            }
            if (!@chmod($path, 0664)) {
                Log::warning('Joylanmagan kartalar hisoboti: faylga 0664 ruxsat berib bo\'lmadi', ['path' => $path]);
            }
            $save(['status' => 'done', 'percent' => 100, 'step_label' => 'Tayyor', 'message' => 'Hisobot tayyor',
                'filename' => 'joylanmagan_kartalar_butun_doska_' . now()->format('Y-m-d') . '.xlsx',
                'finished_at' => now()->toDateTimeString()]);
        } catch (\Throwable $e) {
            $save(['status' => 'error', 'step_label' => 'Xatolik', 'message' => 'Xatolik: ' . mb_substr($e->getMessage(), 0, 300),
                'finished_at' => now()->toDateTimeString()]);

            throw $e;
        }
    }

    public function failed(?\Throwable $e): void
    {
        // Worker timeout / crash — holat "running" da qotib qolmasin
        $state = Cache::get(self::stateKey($this->token), []);
        if ($state && in_array($state['status'] ?? null, ['running', 'queued'], true)) {
            $state['status'] = 'error';
            $state['step_label'] = 'Xatolik';
            $state['finished_at'] = $state['updated_at'] = now()->toDateTimeString();
            $state['message'] = "Hisobot to'xtadi: " . mb_substr((string) $e?->getMessage(), 0, 300);
            Cache::put(self::stateKey($this->token), $state, now()->addDay());
        }
    }
}
