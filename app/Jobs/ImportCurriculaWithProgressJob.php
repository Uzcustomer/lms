<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\Console\Output\Output;

/**
 * O'quv rejalarni HEMIS'dan sinxronlash (import:curricula + import:curriculum-subjects)
 * — KTR sahifasidan ishga tushiriladi. Buyruqlar "Processing page X of Y" qatorlarini
 * chiqaradi; ular ushlanib, holat keshga yoziladi va sahifa progress bar ko'rsatadi.
 */
class ImportCurriculaWithProgressJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const PROGRESS_KEY = 'curricula_import_progress';

    /** [buyruq, sahifadagi nomi, umumiy progressdagi ulushi (%)] */
    private const STEPS = [
        ['import:curricula', "O'quv rejalar", 10],
        ['import:curriculum-subjects', "O'quv reja fanlari", 90],
    ];

    public int $timeout = 3600;
    public int $tries = 1;

    public function __construct(public string $startedBy = '')
    {
    }

    public function handle(): void
    {
        ini_set('memory_limit', '512M');

        $state = [
            'status' => 'running',
            'step' => 0,
            'steps' => count(self::STEPS),
            'step_label' => '',
            'page' => 0,
            'pages' => 0,
            'percent' => 0,
            'started_by' => $this->startedBy,
            'started_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
            'message' => "HEMIS'dan o'quv rejalar olinmoqda...",
            'summary' => [],
        ];
        $save = function () use (&$state) {
            $state['updated_at'] = now()->toDateTimeString();
            Cache::put(self::PROGRESS_KEY, $state, now()->addDay());
        };
        $save();

        try {
            $done = 0;
            foreach (self::STEPS as $i => [$command, $label, $weight]) {
                $state['step'] = $i + 1;
                $state['step_label'] = $label;
                $state['page'] = 0;
                $state['pages'] = 0;
                $state['message'] = $label . ' yangilanmoqda...';
                $save();

                $lines = [];
                $failed = false;
                $output = new class(function (string $line) use (&$state, &$lines, &$failed, $save, $done, $weight) {
                    $line = trim(strip_tags($line));
                    if ($line === '') {
                        return;
                    }
                    $lines[] = $line;
                    if (preg_match('/Processing page (\d+) of (\d+)/', $line, $m)) {
                        $state['page'] = (int) $m[1];
                        $state['pages'] = max(1, (int) $m[2]);
                        $state['percent'] = (int) floor($done + $weight * $state['page'] / $state['pages']);
                        $save();
                    } elseif (stripos($line, 'Failed to fetch') !== false) {
                        $failed = true;
                    }
                }) extends Output {
                    public function __construct(private \Closure $onLine)
                    {
                        parent::__construct(self::VERBOSITY_NORMAL, false);
                    }

                    protected function doWrite(string $message, bool $newline): void
                    {
                        foreach (preg_split('/\R/', $message) as $line) {
                            ($this->onLine)($line);
                        }
                    }
                };

                Artisan::call($command, [], $output);

                // Buyruq oxiridagi xulosa: "YANGI: .. | YANGILANDI: .. | O'ZGARMADI: .." kabi qatorlar
                $state['summary'][] = $label . ': ' . $this->summaryOf($lines);
                if ($failed) {
                    throw new \RuntimeException($label . " — HEMIS API javob bermadi (" . $state['page'] . '/' . $state['pages'] . ' sahifada to\'xtadi)');
                }
                $done += $weight;
                $state['percent'] = $done;
                $save();
            }

            $state['status'] = 'done';
            $state['percent'] = 100;
            $state['finished_at'] = now()->toDateTimeString();
            $state['message'] = 'Sinxronlash yakunlandi';
            $state['updated_at'] = $state['finished_at'];
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
        } catch (\Throwable $e) {
            $state['status'] = 'error';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = 'Xatolik: ' . mb_substr($e->getMessage(), 0, 300);
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));

            throw $e;
        }
    }

    /**
     * Buyruq xulosa jadvalidan (renderImportSummary) sonlarni oladi:
     * "jami N · yangi N · yangilandi N · o'zgarmadi N [· nofaol N]".
     */
    private function summaryOf(array $lines): string
    {
        $labels = ["Jami ko'rildi" => 'jami', "Yangi qo'shildi" => 'yangi', 'Yangilandi' => 'yangilandi', "O'zgarmadi" => "o'zgarmadi"];
        $parts = [];
        foreach ($lines as $line) {
            foreach ($labels as $title => $short) {
                if (preg_match('/^\|\s*' . preg_quote($title, '/') . '\s*\|\s*(\d+)\s*\|$/u', $line, $m)) {
                    $parts[$short] = $short . ' ' . $m[1];
                }
            }
            if (preg_match('/NOFAOL QILINDI:\s*(\d+)/u', $line, $m)) {
                $parts['nofaol'] = 'nofaol qilindi ' . $m[1];
            }
        }

        return $parts ? implode(' · ', $parts) : (string) (end($lines) ?: 'tugadi');
    }

    public function failed(?\Throwable $e): void
    {
        // Worker timeout / crash — holat "running" da qotib qolmasin
        $state = Cache::get(self::PROGRESS_KEY, []);
        if (in_array($state['status'] ?? null, ['running', 'queued'], true)) {
            $state['status'] = 'error';
            $state['finished_at'] = now()->toDateTimeString();
            $state['updated_at'] = $state['finished_at'];
            $state['message'] = "Sinxronlash to'xtadi: " . mb_substr((string) $e?->getMessage(), 0, 300);
            Cache::put(self::PROGRESS_KEY, $state, now()->addDays(30));
        }
    }
}
