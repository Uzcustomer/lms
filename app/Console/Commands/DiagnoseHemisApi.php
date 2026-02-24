<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class DiagnoseHemisApi extends Command
{
    protected $signature = 'hemis:diagnose {--date= : Tekshirish sanasi (YYYY-MM-DD, default: kecha)}';
    protected $description = 'HEMIS API sana filtrini diagnostika qilish — noto\'g\'ri sanalar qaytishini isbotlash';

    public function handle()
    {
        $baseUrl = config('services.hemis.base_url');
        $token = config('services.hemis.token');

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::yesterday();

        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;

        $this->info("========================================");
        $this->info("HEMIS API SANA FILTRI DIAGNOSTIKASI");
        $this->info("========================================");
        $this->info("");
        $this->info("So'ralgan sana: {$date->toDateString()} ({$date->translatedFormat('l')})");
        $this->info("");
        $this->info("REQUEST:");
        $this->info("  URL: {$baseUrl}/v1/data/student-grade-list");
        $this->info("  Parametrlar:");
        $this->info("    lesson_date_from = {$from}  (" . Carbon::createFromTimestamp($from)->format('Y-m-d H:i:s') . ")");
        $this->info("    lesson_date_to   = {$to}  (" . Carbon::createFromTimestamp($to)->format('Y-m-d H:i:s') . ")");
        $this->info("    limit = 200");
        $this->info("    page = 1");
        $this->info("");

        $queryParams = [
            'limit' => 200,
            'page' => 1,
            'lesson_date_from' => $from,
            'lesson_date_to' => $to,
        ];

        $response = Http::timeout(60)->withoutVerifying()->withToken($token)
            ->get("{$baseUrl}/v1/data/student-grade-list", $queryParams);

        if (!$response->successful()) {
            $this->error("API xato: HTTP {$response->status()}");
            $this->error($response->body());
            return 1;
        }

        $json = $response->json();
        $items = $json['data']['items'] ?? [];
        $pagination = $json['data']['pagination'] ?? [];
        $totalCount = $pagination['totalCount'] ?? '?';
        $pageCount = $pagination['pageCount'] ?? '?';

        $this->info("RESPONSE:");
        $this->info("  Jami yozuvlar: {$totalCount}");
        $this->info("  Jami sahifalar: {$pageCount}");
        $this->info("  Shu sahifadagi yozuvlar: " . count($items));
        $this->info("");

        // Sanalar bo'yicha guruhlash
        $dateGroups = [];
        foreach ($items as $item) {
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date'])->toDateString();
            if (!isset($dateGroups[$lessonDate])) {
                $dateGroups[$lessonDate] = 0;
            }
            $dateGroups[$lessonDate]++;
        }

        ksort($dateGroups);

        $correctCount = $dateGroups[$date->toDateString()] ?? 0;
        $wrongCount = count($items) - $correctCount;

        $this->info("NATIJA (1-sahifa, 200 ta yozuv):");
        $this->info("----------------------------------------");

        foreach ($dateGroups as $d => $count) {
            $marker = $d === $date->toDateString() ? ' ← TO\'G\'RI' : ' ← NOTO\'G\'RI SANA!';
            $this->info("  {$d}: {$count} ta yozuv{$marker}");
        }

        $this->info("----------------------------------------");
        $this->info("  To'g'ri sana:    {$correctCount} ta");
        $this->info("  Noto'g'ri sana:  {$wrongCount} ta");
        $this->info("");

        if ($wrongCount > 0) {
            $wrongPercent = round($wrongCount / count($items) * 100, 1);
            $this->error("MUAMMO: So'ralgan sana {$date->toDateString()} bo'lsa ham,");
            $this->error("API {$wrongCount} ta ({$wrongPercent}%) noto'g'ri sanadagi yozuvlarni qaytarmoqda!");
            $this->info("");
            $this->info("Jami {$pageCount} sahifa bo'lsa, taxminan " . ($wrongCount * $pageCount) . " ta yozuv noto'g'ri.");
        } else {
            $this->info("API to'g'ri ishlayapti — barcha yozuvlar so'ralgan sanaga mos.");
        }

        // Birinchi noto'g'ri yozuvni namuna sifatida ko'rsatish
        if ($wrongCount > 0) {
            $this->info("");
            $this->info("NAMUNA (noto'g'ri sanadagi 1 ta yozuv):");
            foreach ($items as $item) {
                $lessonDate = Carbon::createFromTimestamp($item['lesson_date'])->toDateString();
                if ($lessonDate !== $date->toDateString()) {
                    $this->info("  lesson_date: {$item['lesson_date']} ({$lessonDate})");
                    $this->info("  student: " . ($item['_student'] ?? '?'));
                    $this->info("  subject: " . ($item['subject']['name'] ?? '?'));
                    $this->info("  grade: " . ($item['grade'] ?? '?'));
                    break;
                }
            }
        }

        return 0;
    }
}
