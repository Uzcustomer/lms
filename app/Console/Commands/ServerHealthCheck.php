<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class ServerHealthCheck extends Command
{
    protected $signature = 'server:health-check
                            {--continuous : Doimiy tekshirish rejimi (har 30 sekundda)}
                            {--interval=30 : Tekshirish oraligi (sekundda)}
                            {--log : Natijalarni logga ham yozish}';

    protected $description = 'Server ulanishlarini tekshirish va diagnostika (DB, Cache, Disk, Memory)';

    public function handle(): int
    {
        if ($this->option('continuous')) {
            return $this->runContinuous();
        }

        return $this->runOnce();
    }

    private function runOnce(): int
    {
        $this->info('=== SERVER DIAGNOSTIKA ===');
        $this->info('Vaqt: ' . now()->toDateTimeString());
        $this->newLine();

        $results = [];

        // 1. Database tekshirish
        $results[] = $this->checkDatabase();

        // 2. Cache tekshirish
        $results[] = $this->checkCache();

        // 3. Disk tekshirish
        $results[] = $this->checkDisk();

        // 4. Memory tekshirish
        $results[] = $this->checkMemory();

        // 5. PHP-FPM tekshirish
        $results[] = $this->checkPhpFpm();

        // 6. DNS resolution tekshirish
        $results[] = $this->checkDns();

        $this->newLine();
        $this->table(
            ['Komponent', 'Holat', 'Tafsilot', 'Vaqt (ms)'],
            $results
        );

        $hasErrors = collect($results)->contains(fn($r) => $r[1] === 'XATOLIK');

        if ($this->option('log')) {
            $logData = [
                'timestamp' => now()->toDateTimeString(),
                'results' => $results,
                'has_errors' => $hasErrors,
            ];
            Log::channel('connection_debug')->info('SERVER HEALTH CHECK', $logData);
        }

        if ($hasErrors) {
            $this->error('Ba\'zi komponentlarda muammo aniqlandi!');
            return Command::FAILURE;
        }

        $this->info('Barcha komponentlar ishlayapti.');
        return Command::SUCCESS;
    }

    private function runContinuous(): int
    {
        $interval = (int) $this->option('interval');
        $this->info("Doimiy tekshirish rejimi boshlandi (har {$interval} sekundda)...");
        $this->info('To\'xtatish uchun: Ctrl+C');
        $this->newLine();

        while (true) {
            $this->info('--- ' . now()->toDateTimeString() . ' ---');

            // Database ping
            $dbResult = $this->checkDatabase();
            $status = $dbResult[1] === 'OK' ? '<fg=green>OK</>' : '<fg=red>XATOLIK</>';
            $this->line("  DB: {$status} ({$dbResult[3]})  {$dbResult[2]}");

            // Cache ping
            $cacheResult = $this->checkCache();
            $status = $cacheResult[1] === 'OK' ? '<fg=green>OK</>' : '<fg=red>XATOLIK</>';
            $this->line("  Cache: {$status} ({$cacheResult[3]})  {$cacheResult[2]}");

            // Memory
            $memResult = $this->checkMemory();
            $this->line("  Memory: {$memResult[2]}");

            if ($this->option('log') && ($dbResult[1] !== 'OK' || $cacheResult[1] !== 'OK')) {
                Log::channel('connection_debug')->warning('HEALTH CHECK - MUAMMO', [
                    'timestamp' => now()->toDateTimeString(),
                    'database' => $dbResult,
                    'cache' => $cacheResult,
                ]);
            }

            $this->newLine();
            sleep($interval);
        }
    }

    private function checkDatabase(): array
    {
        try {
            $start = microtime(true);
            DB::connection()->getPdo();
            DB::select('SELECT 1');
            $ms = round((microtime(true) - $start) * 1000, 2);

            $host = config('database.connections.' . config('database.default') . '.host');
            $driver = config('database.default');

            // Ulanish sonini tekshirish (MySQL uchun)
            $detail = "Driver: {$driver}, Host: {$host}";
            if ($driver === 'mysql') {
                try {
                    $threads = DB::select("SHOW STATUS LIKE 'Threads_connected'");
                    $maxConn = DB::select("SHOW VARIABLES LIKE 'max_connections'");
                    if (!empty($threads) && !empty($maxConn)) {
                        $detail .= ", Ulanishlar: {$threads[0]->Value}/{$maxConn[0]->Value}";
                    }
                } catch (\Throwable $e) {
                    // ignore
                }
            }

            return ['Database', 'OK', $detail, "{$ms} ms"];
        } catch (\Throwable $e) {
            return ['Database', 'XATOLIK', $e->getMessage(), '-'];
        }
    }

    private function checkCache(): array
    {
        try {
            $start = microtime(true);
            $testKey = 'health_check_' . time();
            Cache::put($testKey, 'ok', 10);
            $value = Cache::get($testKey);
            Cache::forget($testKey);
            $ms = round((microtime(true) - $start) * 1000, 2);

            $driver = config('cache.default');
            $status = $value === 'ok' ? 'OK' : 'XATOLIK';
            return ['Cache', $status, "Driver: {$driver}", "{$ms} ms"];
        } catch (\Throwable $e) {
            return ['Cache', 'XATOLIK', $e->getMessage(), '-'];
        }
    }

    private function checkDisk(): array
    {
        try {
            $start = microtime(true);
            $storagePath = storage_path();
            $freeBytes = disk_free_space($storagePath);
            $totalBytes = disk_total_space($storagePath);
            $ms = round((microtime(true) - $start) * 1000, 2);

            $freeGb = round($freeBytes / 1073741824, 2);
            $totalGb = round($totalBytes / 1073741824, 2);
            $usedPercent = round((1 - $freeBytes / $totalBytes) * 100, 1);

            $status = $freeGb > 1 ? 'OK' : 'XATOLIK';
            return ['Disk', $status, "Bo'sh: {$freeGb}GB / {$totalGb}GB ({$usedPercent}% band)", "{$ms} ms"];
        } catch (\Throwable $e) {
            return ['Disk', 'XATOLIK', $e->getMessage(), '-'];
        }
    }

    private function checkMemory(): array
    {
        $memUsage = memory_get_usage(true);
        $memPeak = memory_get_peak_usage(true);
        $memLimit = ini_get('memory_limit');

        $usageMb = round($memUsage / 1048576, 2);
        $peakMb = round($memPeak / 1048576, 2);

        return ['Memory', 'OK', "Joriy: {$usageMb}MB, Pik: {$peakMb}MB, Limit: {$memLimit}", '-'];
    }

    private function checkPhpFpm(): array
    {
        try {
            $start = microtime(true);
            // PHP-FPM process sonini tekshirish
            $output = shell_exec('ps aux | grep php-fpm | grep -v grep | wc -l');
            $processCount = trim($output ?? '0');
            $ms = round((microtime(true) - $start) * 1000, 2);

            $status = (int)$processCount > 0 ? 'OK' : 'XATOLIK';
            return ['PHP-FPM', $status, "Processlar soni: {$processCount}", "{$ms} ms"];
        } catch (\Throwable $e) {
            return ['PHP-FPM', 'XATOLIK', $e->getMessage(), '-'];
        }
    }

    private function checkDns(): array
    {
        try {
            $start = microtime(true);
            $dbHost = config('database.connections.' . config('database.default') . '.host');

            if ($dbHost === '127.0.0.1' || $dbHost === 'localhost') {
                $ms = round((microtime(true) - $start) * 1000, 2);
                return ['DNS', 'OK', "DB host: {$dbHost} (local)", "{$ms} ms"];
            }

            $ip = gethostbyname($dbHost);
            $ms = round((microtime(true) - $start) * 1000, 2);

            if ($ip === $dbHost) {
                return ['DNS', 'XATOLIK', "'{$dbHost}' resolve bo'lmadi", "{$ms} ms"];
            }

            return ['DNS', 'OK', "{$dbHost} -> {$ip}", "{$ms} ms"];
        } catch (\Throwable $e) {
            return ['DNS', 'XATOLIK', $e->getMessage(), '-'];
        }
    }
}
