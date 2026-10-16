<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

/**
 * HEMIS API'da buyruqlar (decree) endpointi bormi — tekshiruv.
 *
 * Shaxsiy ma'lumot chiqarmaydi: faqat HTTP holati, yozuvlar soni va birinchi
 * yozuvdagi maydon NOMLARI ko'rsatiladi. RTM prod serverda ishga tushiradi:
 *   php artisan monitoring:hemis-probe
 *   php artisan monitoring:hemis-probe data/boshqa-endpoint
 */
class MonitoringHemisProbe extends Command
{
    protected $signature = 'monitoring:hemis-probe
                            {endpoint?* : Tekshiriladigan endpointlar (standart: config monitoring.hemis_probe_endpoints)}
                            {--no-verify : TLS sertifikatini tekshirmaslik (HemisService kabi)}';

    protected $description = 'HEMIS API buyruqlar endpointlarini tekshiradi (qiymatlarsiz, faqat tuzilma)';

    public function handle(): int
    {
        $base = rtrim((string) config('services.hemis.base_url'), '/');
        $token = (string) config('services.hemis.token');

        if ($base === '' || $token === '') {
            $this->error('HEMIS_API_BASE_URL yoki HEMIS_API_TOKEN .env da berilmagan.');

            return self::FAILURE;
        }

        $endpoints = (array) $this->argument('endpoint') ?: config('monitoring.hemis_probe_endpoints', []);
        $base = preg_replace('#/v1$#', '', $base) . '/v1/';

        $rows = [];
        foreach ($endpoints as $endpoint) {
            $url = $base . ltrim($endpoint, '/');
            try {
                $request = Http::withToken($token)->acceptJson()->timeout(30);
                if ($this->option('no-verify')) {
                    $request = $request->withoutVerifying();
                }
                $response = $request->get($url, ['limit' => 1, 'page' => 1]);
                $json = $response->json() ?? [];
                $item = $json['data']['items'][0] ?? null;
                $rows[] = [
                    $endpoint,
                    $response->status(),
                    $json['data']['pagination']['totalCount'] ?? '—',
                    is_array($item) ? implode(', ', array_keys($item)) : ($json['error'] ?? $json['message'] ?? '—'),
                ];
            } catch (\Throwable $e) {
                $rows[] = [$endpoint, 'xato', '—', mb_substr($e->getMessage(), 0, 120)];
            }
        }

        $this->table(['Endpoint', 'HTTP', 'Yozuvlar', 'Maydonlar (qiymatlarsiz)'], $rows);
        $this->line('Natijani RTM orqali ishlab chiquvchiga yuboring — unda talaba ma\'lumoti yo\'q.');

        return self::SUCCESS;
    }
}
