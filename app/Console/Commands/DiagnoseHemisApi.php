<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class DiagnoseHemisApi extends Command
{
    protected $signature = 'hemis:diagnose
        {--date= : Tekshirish sanasi (YYYY-MM-DD, default: 2026-02-22)}
        {--all-pages : Barcha sahifalarni yuklash (sekin, lekin to\'liq)}';

    protected $description = 'HEMIS API sana filtrini diagnostika qilish — noto\'g\'ri sanalar qaytishini isbotlash';

    public function handle()
    {
        $baseUrl = rtrim(config('services.hemis.base_url'), '/');
        $token = config('services.hemis.token');

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::parse('2026-02-22');

        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;
        $dayOfWeek = $date->translatedFormat('l');
        $fetchAllPages = $this->option('all-pages');

        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║        HEMIS API SANA FILTRI DIAGNOSTIKASI                  ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        $this->info("┌─ SO'ROV ────────────────────────────────────────────────────┐");
        $this->info("│ Sana:     {$date->toDateString()} ({$dayOfWeek})");
        $this->info("│ lesson_date_from = {$from}  (" . Carbon::createFromTimestamp($from)->format('Y-m-d H:i:s') . ")");
        $this->info("│ lesson_date_to   = {$to}  (" . Carbon::createFromTimestamp($to)->format('Y-m-d H:i:s') . ")");
        $this->info("│ limit = 200");
        $this->info("│");
        $this->info("│ curl ekvivalenti:");
        $this->info("│   curl -k -H 'Authorization: Bearer <TOKEN>' \\");
        $this->info("│     '{$baseUrl}/v1/data/student-grade-list?limit=200&page=1&lesson_date_from={$from}&lesson_date_to={$to}'");
        $this->info("└─────────────────────────────────────────────────────────────┘");
        $this->newLine();

        // ── 1-sahifani yuklash ──
        $this->info("1-sahifani yuklamoqda...");
        $firstPage = $this->fetchPage($baseUrl, $token, $from, $to, 1);
        if ($firstPage === null) {
            return 1;
        }

        $items = $firstPage['items'];
        $totalCount = $firstPage['totalCount'];
        $pageCount = $firstPage['pageCount'];

        $this->info("  Jami: {$totalCount} ta yozuv, {$pageCount} sahifa");

        if (count($items) === 0) {
            $this->info("Hech qanday yozuv qaytmadi.");
            return 0;
        }

        // ── Qaysi sahifalarni yuklash kerak? ──
        $allItems = $items;

        if ($fetchAllPages && $pageCount > 1) {
            // Barcha sahifalarni yuklash
            $this->info("Barcha {$pageCount} sahifani yuklamoqda...");
            for ($p = 2; $p <= $pageCount; $p++) {
                $this->output->write("  Sahifa {$p}/{$pageCount}...");
                $page = $this->fetchPage($baseUrl, $token, $from, $to, $p);
                if ($page !== null) {
                    $allItems = array_merge($allItems, $page['items']);
                    $this->output->writeln(" " . count($page['items']) . " ta yozuv");
                } else {
                    $this->output->writeln(" XATO!");
                }
            }
        } elseif ($pageCount > 1) {
            // Namuna sahifalar: boshi, o'rtasi, oxiri
            $samplePages = $this->getSamplePages($pageCount, 8);
            $this->info("Namuna sahifalarni yuklamoqda: " . implode(', ', $samplePages) . " ...");
            foreach ($samplePages as $p) {
                if ($p === 1) {
                    continue; // 1-sahifa allaqachon yuklangan
                }
                $this->output->write("  Sahifa {$p}/{$pageCount}...");
                $page = $this->fetchPage($baseUrl, $token, $from, $to, $p);
                if ($page !== null) {
                    $allItems = array_merge($allItems, $page['items']);
                    $this->output->writeln(" " . count($page['items']) . " ta yozuv");
                } else {
                    $this->output->writeln(" XATO!");
                }
            }
        }

        $this->newLine();
        $this->info("Jami yuklangan yozuvlar: " . count($allItems));

        // ── Sanalar bo'yicha tahlil ──
        $dateGroups = [];
        $timestamps = [];
        foreach ($allItems as $item) {
            $ts = $item['lesson_date'] ?? 0;
            $d = Carbon::createFromTimestamp($ts)->toDateString();
            if (!isset($dateGroups[$d])) {
                $dateGroups[$d] = ['count' => 0, 'items' => []];
            }
            $dateGroups[$d]['count']++;
            $dateGroups[$d]['items'][] = $item;
            $timestamps[] = $ts;
        }

        ksort($dateGroups);

        $correctCount = $dateGroups[$date->toDateString()]['count'] ?? 0;
        $wrongCount = count($allItems) - $correctCount;

        $minTs = min($timestamps);
        $maxTs = max($timestamps);
        $minDate = Carbon::createFromTimestamp($minTs);
        $maxDate = Carbon::createFromTimestamp($maxTs);
        $daySpan = $minDate->diffInDays($maxDate);

        // ── Vaqt oralig'i ──
        $this->newLine();
        $this->info("┌─ VAQT ORALIG'I ────────────────────────────────────────────┐");
        $this->info("│ So'ralgan:   {$date->toDateString()} (faqat 1 kun)");
        $this->info("│ Kelgan min:  {$minDate->format('Y-m-d H:i:s')} (ts: {$minTs})");
        $this->info("│ Kelgan max:  {$maxDate->format('Y-m-d H:i:s')} (ts: {$maxTs})");
        $this->info("│ Farq:        {$daySpan} kunlik oraliq!");
        $this->info("└─────────────────────────────────────────────────────────────┘");

        // ── Unikal sanalar ──
        $uniqueDates = array_keys($dateGroups);
        $this->newLine();
        $this->info("┌─ UNIKAL SANALAR (" . count($uniqueDates) . " ta) ──────────────────────────────┐");
        $this->info("│");

        $maxBarWidth = 40;
        $maxCount = max(array_column($dateGroups, 'count'));

        foreach ($dateGroups as $d => $info) {
            $count = $info['count'];
            $barLen = $maxCount > 0 ? (int) round($count / $maxCount * $maxBarWidth) : 0;
            $bar = str_repeat('█', max(1, $barLen));
            $padCount = str_pad($count, 5, ' ', STR_PAD_LEFT);

            if ($d === $date->toDateString()) {
                $this->info("│  {$d}  {$padCount} ta  {$bar}  ← TO'G'RI");
            } else {
                $this->warn("│  {$d}  {$padCount} ta  {$bar}  ← NOTO'G'RI!");
            }
        }

        $this->info("│");
        $this->info("├─────────────────────────────────────────────────────────────┤");
        $this->info("│  Jami yuklangan:    " . count($allItems) . " ta");
        $this->info("│  To'g'ri sanali:    {$correctCount} ta");

        $wrongPercent = 0;
        if ($wrongCount > 0) {
            $wrongPercent = round($wrongCount / count($allItems) * 100, 1);
            $this->error("│  Noto'g'ri sanali:  {$wrongCount} ta ({$wrongPercent}%)");
        } else {
            $this->info("│  Noto'g'ri sanali:  0 ta");
        }
        $this->info("└─────────────────────────────────────────────────────────────┘");

        // ── 30-40 ta namuna yozuv ──
        if ($wrongCount > 0) {
            $this->newLine();

            // Noto'g'ri sanalar uchun namuna yig'ish
            $wrongDateGroups = [];
            foreach ($dateGroups as $d => $info) {
                if ($d !== $date->toDateString()) {
                    $wrongDateGroups[$d] = $info['items'];
                }
            }

            $sampled = $this->sampleFromGroups($wrongDateGroups, 35);
            $sampledTotal = $this->countSampled($sampled);

            $this->info("┌─ NOTO'G'RI SANADAGI NAMUNA YOZUVLAR ({$sampledTotal} ta) ─────────┐");
            $this->info("│");
            $this->info("│  So'rov: FAQAT {$date->toDateString()} baholarini so'radik.");
            $this->info("│  API quyidagi BOSHQA sanalar yozuvlarini qaytarmoqda:");
            $this->info("│");

            $num = 0;
            foreach ($sampled as $sana => $records) {
                $totalForDate = $dateGroups[$sana]['count'] ?? count($records);
                $this->warn("│  ── {$sana} (jami {$totalForDate} ta) ─────────────────────────────");
                foreach ($records as $item) {
                    $num++;
                    $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
                    $studentName = $this->getStudentName($item);
                    $subjectName = $item['subject']['name'] ?? '?';
                    $grade = $item['grade'] ?? '-';
                    $trainingType = $item['trainingType']['name'] ?? ($item['_training_type'] ?? '?');

                    $this->warn("│  {$num}. [{$lessonDate->format('Y-m-d H:i')}] {$studentName}");
                    $this->info("│     Fan: {$subjectName}");
                    $this->info("│     Baho: {$grade} | Mashg'ulot: {$trainingType}");
                    $this->info("│     timestamp: {$item['lesson_date']}");
                    $this->info("│");
                }
            }

            $this->info("└─────────────────────────────────────────────────────────────┘");

            // ── Xulosa ──
            $this->newLine();
            $this->error("╔══════════════════════════════════════════════════════════════╗");
            $this->error("║  XULOSA: HEMIS API SANA FILTRI ISHLAMAYAPTI                 ║");
            $this->error("╠══════════════════════════════════════════════════════════════╣");
            $this->error("║                                                              ║");
            $this->error("║  So'ralgan:       {$date->toDateString()} ({$dayOfWeek})");
            $this->error("║  Unikal sanalar:  " . count($uniqueDates) . " ta topildi (kutilgan: 1 ta)");
            $this->error("║  Sana oralig'i:   {$minDate->toDateString()} — {$maxDate->toDateString()} ({$daySpan} kun)");
            $this->error("║  Noto'g'ri:       {$wrongCount} / " . count($allItems) . " ({$wrongPercent}%)");
            $this->error("║  API jami:        {$totalCount} ta yozuv, {$pageCount} sahifa");
            $this->error("║                                                              ║");

            if ($daySpan > 1) {
                $this->error("║  Bu timezone muammosi EMAS:                                ║");
                $this->error("║  Timezone faqat ±5 soat (1 kun), lekin {$daySpan} kun farq bor    ║");
            }

            $this->error("║                                                              ║");
            $this->error("║  Yechim: client-side sana filtri (ImportGrades.php)          ║");
            $this->error("╚══════════════════════════════════════════════════════════════╝");
        } else {
            $this->newLine();
            $this->info("Barcha yozuvlar to'g'ri sanaga mos — API to'g'ri ishlayapti.");
        }

        return 0;
    }

    /**
     * Bitta sahifani curl bilan yuklash
     */
    private function fetchPage(string $baseUrl, string $token, int $from, int $to, int $page): ?array
    {
        $queryString = http_build_query([
            'limit' => 200,
            'page' => $page,
            'lesson_date_from' => $from,
            'lesson_date_to' => $to,
        ]);

        $url = "{$baseUrl}/v1/data/student-grade-list?{$queryString}";

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer {$token}",
                "Accept: application/json",
            ],
        ]);

        $body = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($error) {
            $this->error("curl xato (sahifa {$page}): {$error}");
            return null;
        }

        if ($httpCode !== 200) {
            $this->error("HTTP {$httpCode} (sahifa {$page})");
            return null;
        }

        $json = json_decode($body, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON xato (sahifa {$page}): " . json_last_error_msg());
            return null;
        }

        return [
            'items' => $json['data']['items'] ?? [],
            'totalCount' => $json['data']['pagination']['totalCount'] ?? 0,
            'pageCount' => $json['data']['pagination']['pageCount'] ?? 0,
        ];
    }

    /**
     * Namuna sahifalarni tanlash: boshi, o'rtasi, oxiri
     */
    private function getSamplePages(int $pageCount, int $sampleSize): array
    {
        if ($pageCount <= $sampleSize) {
            return range(1, $pageCount);
        }

        $pages = [1];
        $step = ($pageCount - 1) / ($sampleSize - 1);
        for ($i = 1; $i < $sampleSize - 1; $i++) {
            $pages[] = (int) round(1 + $i * $step);
        }
        $pages[] = $pageCount;

        return array_unique($pages);
    }

    /**
     * Har sanadan teng miqdorda namuna olish
     */
    private function sampleFromGroups(array $groups, int $targetCount): array
    {
        $groupCount = count($groups);
        if ($groupCount === 0) {
            return [];
        }

        $perGroup = max(2, intdiv($targetCount, $groupCount));
        $remaining = $targetCount;
        $result = [];

        foreach ($groups as $key => $records) {
            $take = min($perGroup, $remaining, count($records));
            $result[$key] = array_slice($records, 0, $take);
            $remaining -= $take;
            if ($remaining <= 0) {
                break;
            }
        }

        // Qolgan joyni to'ldirish
        if ($remaining > 0) {
            foreach ($groups as $key => $records) {
                $taken = count($result[$key] ?? []);
                $canTake = min($remaining, count($records) - $taken);
                if ($canTake > 0) {
                    $extra = array_slice($records, $taken, $canTake);
                    $result[$key] = array_merge($result[$key] ?? [], $extra);
                    $remaining -= $canTake;
                }
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $result;
    }

    private function getStudentName(array $item): string
    {
        if (!empty($item['_student'])) {
            return $item['_student'];
        }
        if (!empty($item['student']['full_name'])) {
            return $item['student']['full_name'];
        }
        if (!empty($item['student']['first_name'])) {
            return trim(($item['student']['first_name'] ?? '') . ' ' . ($item['student']['second_name'] ?? ''));
        }
        return '?';
    }

    private function countSampled(array $sampled): int
    {
        $count = 0;
        foreach ($sampled as $records) {
            $count += count($records);
        }
        return $count;
    }
}
