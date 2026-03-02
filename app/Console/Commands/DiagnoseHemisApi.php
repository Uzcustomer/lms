<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class DiagnoseHemisApi extends Command
{
    protected $signature = 'hemis:diagnose {--days=7 : Necha kunni tekshirish}';
    protected $description = 'HEMIS API diagnostikasi — har bir kun uchun alohida API so\'rov + DB holati';

    public function handle()
    {
        $baseUrl = rtrim(config('services.hemis.base_url') ?? '', '/');
        $token = config('services.hemis.token') ?? '';
        $days = (int) $this->option('days');

        if (!$baseUrl || !$token) {
            $this->error('HEMIS base_url yoki token topilmadi');
            return 1;
        }

        $this->info("HEMIS API: {$baseUrl}");
        $this->info("Token: " . substr($token, 0, 15) . '...');
        $this->info("Vaqt zonasi: " . config('app.timezone', 'UTC'));
        $this->info("Hozir: " . Carbon::now()->format('Y-m-d H:i:s'));
        $this->newLine();

        // =====================================================================
        // 1. HEMIS API testi — har bir kun uchun alohida so'rov
        // =====================================================================
        $this->info("=== HEMIS API TESTI (oxirgi {$days} kun) ===");
        $this->newLine();

        $apiResults = [];

        for ($i = 1; $i <= $days; $i++) {
            $date = Carbon::today()->subDays($i);
            $dateStr = $date->toDateString();
            $from = Carbon::parse($dateStr, 'UTC')->startOfDay()->timestamp;
            $to = Carbon::parse($dateStr, 'UTC')->endOfDay()->timestamp;

            $this->info("--- {$dateStr} ({$i} kun oldin) ---");
            $this->info("  from={$from} to={$to}");

            $startTime = microtime(true);
            $result = ['date' => $dateStr, 'days_ago' => $i];

            try {
                $response = Http::connectTimeout(30)
                    ->timeout(60)
                    ->withoutVerifying()
                    ->withToken($token)
                    ->get("{$baseUrl}/v1/data/student-grade-list", [
                        'limit' => 10,
                        'page' => 1,
                        'lesson_date_from' => $from,
                        'lesson_date_to' => $to,
                    ]);

                $elapsed = round((microtime(true) - $startTime) * 1000);

                if ($response->successful()) {
                    $data = $response->json()['data'] ?? [];
                    $pagination = $data['pagination'] ?? [];
                    $items = $data['items'] ?? [];
                    $totalCount = $pagination['totalCount'] ?? $pagination['total'] ?? '?';
                    $pageCount = $pagination['pageCount'] ?? '?';

                    $result['status'] = 'OK';
                    $result['http'] = $response->status();
                    $result['ms'] = $elapsed;
                    $result['totalCount'] = $totalCount;
                    $result['pageCount'] = $pageCount;
                    $result['itemsOnPage'] = count($items);

                    $this->info("  OK — HTTP {$response->status()} — {$elapsed}ms — totalCount={$totalCount}, pages={$pageCount}");
                } else {
                    $result['status'] = 'HTTP_ERROR';
                    $result['http'] = $response->status();
                    $result['ms'] = $elapsed;
                    $result['body'] = substr($response->body(), 0, 300);

                    $this->error("  XATO — HTTP {$response->status()} — {$elapsed}ms");
                    $this->error("  Body: " . substr($response->body(), 0, 200));
                }
            } catch (\Exception $e) {
                $elapsed = round((microtime(true) - $startTime) * 1000);
                $result['status'] = 'EXCEPTION';
                $result['ms'] = $elapsed;
                $result['error'] = $e->getMessage();

                $this->error("  EXCEPTION — {$elapsed}ms — " . $e->getMessage());
            }

            $apiResults[] = $result;
            $this->newLine();
        }

        // =====================================================================
        // 2. DB holati
        // =====================================================================
        $this->info("=== BAZADAGI BAHOLAR ===");
        $this->newLine();

        $dbResults = DB::table('student_grades')
            ->selectRaw('DATE(lesson_date) as day, COUNT(*) as total, SUM(CASE WHEN is_final = 1 THEN 1 ELSE 0 END) as final_count')
            ->where('lesson_date', '>=', Carbon::today()->subDays($days)->startOfDay())
            ->whereNull('deleted_at')
            ->groupByRaw('DATE(lesson_date)')
            ->orderBy('day')
            ->get();

        $this->table(
            ['Sana', 'Jami', 'Final', 'Final %'],
            $dbResults->map(fn($r) => [
                $r->day,
                $r->total,
                $r->final_count,
                $r->total > 0 ? round($r->final_count / $r->total * 100) . '%' : '0%',
            ])
        );

        // =====================================================================
        // 3. Xulosa jadvali
        // =====================================================================
        $this->newLine();
        $this->info("=== XULOSA ===");
        $this->newLine();

        $this->table(
            ['Sana', 'Kun oldin', 'API', 'Javob (ms)', 'API total', 'DB total', 'DB final'],
            collect($apiResults)->map(function ($api) use ($dbResults) {
                $dbRow = $dbResults->firstWhere('day', $api['date']);
                return [
                    $api['date'],
                    $api['days_ago'],
                    $api['status'] . (isset($api['http']) ? " ({$api['http']})" : ''),
                    $api['ms'] ?? '?',
                    $api['totalCount'] ?? '-',
                    $dbRow->total ?? 0,
                    $dbRow->final_count ?? 0,
                ];
            })
        );

        // =====================================================================
        // 4. Cache holati
        // =====================================================================
        $this->newLine();
        $this->info("=== CACHE ===");
        $cacheValue = cache('final_import_last_success');
        $this->info("final_import_last_success: " . ($cacheValue ?: '(yo\'q)'));

        $this->newLine();
        $this->info("Natijalarni Claude ga yuboring.");

        return 0;
    }
}
