<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\File;

class ServerDebugController extends Controller
{
    /**
     * Server debug dashboard — ulanish loglarini ko'rish va jonli tekshirish.
     */
    public function index()
    {
        $data = [
            'server_status' => $this->getServerStatus(),
            'recent_logs' => $this->getRecentConnectionLogs(),
            'db_info' => $this->getDatabaseInfo(),
            'php_info' => $this->getPhpInfo(),
        ];

        return view('admin.server-debug', $data);
    }

    /**
     * AJAX orqali jonli server ping qilish.
     */
    public function ping()
    {
        $results = [];

        // Database ping
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $results['database'] = [
                'status' => 'ok',
                'ping_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $results['database'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Cache ping
        try {
            $start = microtime(true);
            $key = 'ping_test_' . time();
            Cache::put($key, true, 5);
            Cache::get($key);
            Cache::forget($key);
            $results['cache'] = [
                'status' => 'ok',
                'ping_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $results['cache'] = [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }

        // Disk
        $freeBytes = disk_free_space(storage_path());
        $results['disk'] = [
            'status' => $freeBytes > 1073741824 ? 'ok' : 'warning',
            'free_gb' => round($freeBytes / 1073741824, 2),
        ];

        // Memory
        $results['memory'] = [
            'status' => 'ok',
            'usage_mb' => round(memory_get_usage(true) / 1048576, 2),
            'peak_mb' => round(memory_get_peak_usage(true) / 1048576, 2),
        ];

        $results['timestamp'] = now()->toDateTimeString();

        return response()->json($results);
    }

    /**
     * Connection debug loglarini tozalash.
     */
    public function clearLogs()
    {
        $logPattern = storage_path('logs/connection_debug-*.log');
        $files = glob($logPattern);
        foreach ($files as $file) {
            File::delete($file);
        }

        // Asosiy fayl ham
        $mainLog = storage_path('logs/connection_debug.log');
        if (File::exists($mainLog)) {
            File::delete($mainLog);
        }

        return redirect()->route('admin.server-debug')->with('success', 'Debug loglar tozalandi.');
    }

    private function getServerStatus(): array
    {
        $status = [];

        // DB
        try {
            $start = microtime(true);
            DB::select('SELECT 1');
            $status['database'] = [
                'ok' => true,
                'ping_ms' => round((microtime(true) - $start) * 1000, 2),
            ];
        } catch (\Throwable $e) {
            $status['database'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        // Cache
        try {
            Cache::put('_health', true, 5);
            $status['cache'] = ['ok' => Cache::get('_health') === true];
            Cache::forget('_health');
        } catch (\Throwable $e) {
            $status['cache'] = ['ok' => false, 'error' => $e->getMessage()];
        }

        // Disk
        $free = disk_free_space(storage_path());
        $total = disk_total_space(storage_path());
        $status['disk'] = [
            'ok' => $free > 1073741824,
            'free_gb' => round($free / 1073741824, 2),
            'total_gb' => round($total / 1073741824, 2),
            'used_percent' => round((1 - $free / $total) * 100, 1),
        ];

        return $status;
    }

    private function getRecentConnectionLogs(): array
    {
        $logs = [];

        // Kunlik loglarni tekshirish
        $logFiles = glob(storage_path('logs/connection_debug*.log'));
        rsort($logFiles); // eng yangilari birinchi

        foreach (array_slice($logFiles, 0, 2) as $file) {
            $content = File::get($file);
            $lines = array_filter(explode("\n", $content));
            // Oxirgi 100 ta qatorni olish
            $recentLines = array_slice($lines, -100);

            foreach ($recentLines as $line) {
                if (empty(trim($line))) continue;

                // Laravel log formatini parse qilish
                if (preg_match('/^\[(\d{4}-\d{2}-\d{2}\s\d{2}:\d{2}:\d{2})\]\s\w+\.(\w+):\s(.+)/', $line, $matches)) {
                    $logs[] = [
                        'time' => $matches[1],
                        'level' => $matches[2],
                        'message' => mb_substr($matches[3], 0, 300),
                    ];
                }
            }
        }

        // Vaqt bo'yicha tartiblash (yangidan eskiga)
        usort($logs, fn($a, $b) => strcmp($b['time'], $a['time']));

        return array_slice($logs, 0, 50);
    }

    private function getDatabaseInfo(): array
    {
        $info = [
            'driver' => config('database.default'),
            'host' => config('database.connections.' . config('database.default') . '.host'),
            'database' => config('database.connections.' . config('database.default') . '.database'),
        ];

        if (config('database.default') === 'mysql') {
            try {
                $threads = DB::select("SHOW STATUS LIKE 'Threads_connected'");
                $maxConn = DB::select("SHOW VARIABLES LIKE 'max_connections'");
                $uptime = DB::select("SHOW STATUS LIKE 'Uptime'");
                $aborted = DB::select("SHOW STATUS LIKE 'Aborted_connects'");
                $abortedClients = DB::select("SHOW STATUS LIKE 'Aborted_clients'");

                $info['threads_connected'] = $threads[0]->Value ?? '-';
                $info['max_connections'] = $maxConn[0]->Value ?? '-';
                $info['uptime_seconds'] = $uptime[0]->Value ?? '-';
                $info['aborted_connects'] = $aborted[0]->Value ?? '-';
                $info['aborted_clients'] = $abortedClients[0]->Value ?? '-';
            } catch (\Throwable $e) {
                $info['mysql_status_error'] = $e->getMessage();
            }
        }

        return $info;
    }

    private function getPhpInfo(): array
    {
        return [
            'version' => PHP_VERSION,
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'session_driver' => config('session.driver'),
            'session_lifetime' => config('session.lifetime') . ' daqiqa',
            'cache_driver' => config('cache.default'),
        ];
    }
}
