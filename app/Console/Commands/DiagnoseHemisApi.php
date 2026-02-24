<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;

class DiagnoseHemisApi extends Command
{
    protected $signature = 'hemis:diagnose {--date= : Tekshirish sanasi (YYYY-MM-DD, default: 2026-02-22 yakshanba)}';
    protected $description = 'HEMIS API sana filtrini diagnostika qilish — noto\'g\'ri sanalar qaytishini isbotlash';

    public function handle()
    {
        $baseUrl = rtrim(config('services.hemis.base_url'), '/');
        $token = config('services.hemis.token');

        $date = $this->option('date')
            ? Carbon::parse($this->option('date'))
            : Carbon::parse('2026-02-22'); // Yakshanba — dam olish kuni

        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;

        $dayOfWeek = $date->translatedFormat('l');

        $this->newLine();
        $this->info("╔══════════════════════════════════════════════════════════════╗");
        $this->info("║        HEMIS API SANA FILTRI DIAGNOSTIKASI                  ║");
        $this->info("╚══════════════════════════════════════════════════════════════╝");
        $this->newLine();

        // ── 1. So'rov ma'lumotlari ──
        $queryString = http_build_query([
            'limit' => 200,
            'page' => 1,
            'lesson_date_from' => $from,
            'lesson_date_to' => $to,
        ]);
        $fullUrl = "{$baseUrl}/v1/data/student-grade-list?{$queryString}";

        $this->info("┌─ SO'ROV (curl) ───────────────────────────────────────────────┐");
        $this->info("│ Sana:     {$date->toDateString()} ({$dayOfWeek})");
        $this->info("│");
        $this->info("│ curl komandasi:");
        $this->info("│   curl -k -H \"Authorization: Bearer \$TOKEN\" \\");
        $this->info("│     \"{$baseUrl}/v1/data/student-grade-list?limit=200&page=1\\");
        $this->info("│     &lesson_date_from={$from}&lesson_date_to={$to}\"");
        $this->info("│");
        $this->info("│ Parametrlar:");
        $this->info("│   lesson_date_from = {$from}");
        $this->info("│     → " . Carbon::createFromTimestamp($from)->format('Y-m-d H:i:s') . " (Asia/Tashkent)");
        $this->info("│     → " . Carbon::createFromTimestamp($from)->utc()->format('Y-m-d H:i:s') . " (UTC)");
        $this->info("│   lesson_date_to   = {$to}");
        $this->info("│     → " . Carbon::createFromTimestamp($to)->format('Y-m-d H:i:s') . " (Asia/Tashkent)");
        $this->info("│     → " . Carbon::createFromTimestamp($to)->utc()->format('Y-m-d H:i:s') . " (UTC)");
        $this->info("│   limit = 200, page = 1");
        $this->info("└─────────────────────────────────────────────────────────────┘");
        $this->newLine();

        // ── 2. curl bilan API so'rovi ──
        $this->info("curl bilan so'rov yuborilmoqda...");

        $ch = curl_init($fullUrl);
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

        $responseBody = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        $totalTime = round(curl_getinfo($ch, CURLINFO_TOTAL_TIME), 2);
        curl_close($ch);

        if ($curlError) {
            $this->error("curl xato: {$curlError}");
            return 1;
        }

        $this->info("  HTTP status: {$httpCode}, javob vaqti: {$totalTime}s");

        if ($httpCode !== 200) {
            $this->error("API xato: HTTP {$httpCode}");
            $this->error(mb_substr($responseBody, 0, 500));
            return 1;
        }

        $json = json_decode($responseBody, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error("JSON parse xato: " . json_last_error_msg());
            return 1;
        }

        $items = $json['data']['items'] ?? [];
        $pagination = $json['data']['pagination'] ?? [];
        $totalCount = $pagination['totalCount'] ?? '?';
        $pageCount = $pagination['pageCount'] ?? '?';

        // ── 3. Umumiy natija ──
        $this->newLine();
        $this->info("┌─ JAVOB ──────────────────────────────────────────────────────┐");
        $this->info("│ HTTP status:                   {$httpCode}");
        $this->info("│ Jami yozuvlar (totalCount):    {$totalCount}");
        $this->info("│ Jami sahifalar (pageCount):    {$pageCount}");
        $this->info("│ Shu sahifadagi yozuvlar:       " . count($items));
        $this->info("│ Javob hajmi:                   " . strlen($responseBody) . " bayt");
        $this->info("└─────────────────────────────────────────────────────────────┘");

        if (count($items) === 0) {
            $this->newLine();
            $this->info("Hech qanday yozuv qaytmadi.");
            return 0;
        }

        // ── 4. Sanalar bo'yicha guruhlash ──
        $dateGroups = [];
        $timestamps = [];
        foreach ($items as $item) {
            $lessonTs = $item['lesson_date'] ?? 0;
            $lessonDate = Carbon::createFromTimestamp($lessonTs)->toDateString();
            if (!isset($dateGroups[$lessonDate])) {
                $dateGroups[$lessonDate] = 0;
            }
            $dateGroups[$lessonDate]++;
            $timestamps[] = $lessonTs;
        }

        ksort($dateGroups);

        $correctCount = $dateGroups[$date->toDateString()] ?? 0;
        $wrongCount = count($items) - $correctCount;

        // Vaqt oralig'i
        $minTimestamp = min($timestamps);
        $maxTimestamp = max($timestamps);
        $minDate = Carbon::createFromTimestamp($minTimestamp);
        $maxDate = Carbon::createFromTimestamp($maxTimestamp);
        $daySpan = $minDate->diffInDays($maxDate);

        $this->newLine();
        $this->info("┌─ VAQT ORALIG'I ────────────────────────────────────────────┐");
        $this->info("│ So'ralgan:     {$date->toDateString()} (faqat 1 kun)");
        $this->info("│ Kelgan min:    {$minDate->format('Y-m-d H:i:s')} (ts: {$minTimestamp})");
        $this->info("│ Kelgan max:    {$maxDate->format('Y-m-d H:i:s')} (ts: {$maxTimestamp})");
        $this->info("│ Farq:          {$daySpan} kun oralig'idagi yozuvlar qaytdi!");
        $this->info("└─────────────────────────────────────────────────────────────┘");

        // ── 5. Sanalar taqsimoti ──
        $this->newLine();
        $this->info("┌─ SANALAR TAQSIMOTI (1-sahifa, " . count($items) . " ta yozuv) ─────────────┐");

        foreach ($dateGroups as $d => $count) {
            $bar = str_repeat('█', min($count, 50));
            if ($d === $date->toDateString()) {
                $this->info("│  {$d}: {$count} ta  {$bar}  ← TO'G'RI");
            } else {
                $this->warn("│  {$d}: {$count} ta  {$bar}  ← NOTO'G'RI!");
            }
        }

        $this->info("├─────────────────────────────────────────────────────────────┤");
        $this->info("│  To'g'ri sanali:    {$correctCount} ta");

        $wrongPercent = 0;
        if ($wrongCount > 0) {
            $wrongPercent = round($wrongCount / count($items) * 100, 1);
            $this->error("│  Noto'g'ri sanali:  {$wrongCount} ta ({$wrongPercent}%)");
        } else {
            $this->info("│  Noto'g'ri sanali:  0 ta");
        }
        $this->info("└─────────────────────────────────────────────────────────────┘");

        // ── 6. Namuna yozuvlar (30-40 ta) ──
        if ($wrongCount > 0) {
            $this->newLine();

            // Noto'g'ri sanadagi yozuvlarni yig'ish
            $wrongItems = [];
            $correctItems = [];
            foreach ($items as $item) {
                $lessonDate = Carbon::createFromTimestamp($item['lesson_date'])->toDateString();
                if ($lessonDate !== $date->toDateString()) {
                    $wrongItems[] = $item;
                } else {
                    $correctItems[] = $item;
                }
            }

            // 30-40 ta namuna: turli sanalardan teng taqsimlash
            $sampled = $this->sampleRecords($wrongItems, 35);

            $this->info("┌─ NOTO'G'RI SANADAGI NAMUNA YOZUVLAR ({$this->countSampled($sampled)} ta) ──────┐");
            $this->info("│");
            $this->info("│  So'rov: FAQAT {$date->toDateString()} sanadagi baholar so'raldi.");
            $this->info("│  Lekin API quyidagi boshqa sanadagi yozuvlarni ham qaytarmoqda:");
            $this->info("│");

            $num = 0;
            foreach ($sampled as $sanaGroup => $records) {
                $this->warn("│  ── {$sanaGroup} (so'ralmagan sana) ──────────────────────────");
                foreach ($records as $item) {
                    $num++;
                    $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
                    $studentName = $this->getStudentName($item);
                    $subjectName = $item['subject']['name'] ?? '?';
                    $grade = $item['grade'] ?? '-';
                    $trainingType = $item['trainingType']['name'] ?? ($item['_training_type'] ?? '?');

                    $this->warn("│  {$num}. {$lessonDate->format('Y-m-d H:i:s')} | {$studentName}");
                    $this->info("│     Fan: {$subjectName}");
                    $this->info("│     Baho: {$grade} | Mashg'ulot: {$trainingType}");
                    $this->info("│     lesson_date timestamp: {$item['lesson_date']}");
                    $this->info("│");
                }
            }

            $this->info("└─────────────────────────────────────────────────────────────┘");

            // ── 7. To'g'ri sanadagi namuna (agar bor bo'lsa) ──
            if (count($correctItems) > 0) {
                $this->newLine();
                $showCorrect = array_slice($correctItems, 0, 5);
                $this->info("┌─ TO'G'RI SANADAGI NAMUNA ({$correctCount} tadan " . count($showCorrect) . " tasi) ──────────┐");
                foreach ($showCorrect as $i => $item) {
                    $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
                    $studentName = $this->getStudentName($item);
                    $subjectName = $item['subject']['name'] ?? '?';
                    $grade = $item['grade'] ?? '-';

                    $this->info("│  " . ($i + 1) . ". {$lessonDate->format('Y-m-d H:i:s')} | {$studentName}");
                    $this->info("│     Fan: {$subjectName} | Baho: {$grade}");
                }
                $this->info("└─────────────────────────────────────────────────────────────┘");
            }

            // ── 8. Yakuniy xulosa ──
            $this->newLine();
            $this->error("╔══════════════════════════════════════════════════════════════╗");
            $this->error("║  XULOSA: HEMIS API SANA FILTRI ISHLAMAYAPTI                 ║");
            $this->error("╠══════════════════════════════════════════════════════════════╣");
            $this->error("║                                                              ║");
            $this->error("║  So'ralgan sana:    {$date->toDateString()} ({$dayOfWeek})");
            $this->error("║  Kelgan yozuvlar:   {$daySpan} kunlik oraliqdan");
            $this->error("║  Noto'g'ri yozuv:   {$wrongCount} / " . count($items) . " ({$wrongPercent}%)");
            $this->error("║  Jami sahifalar:    {$pageCount} (taxminan {$totalCount} ta yozuv)");
            $this->error("║                                                              ║");
            $this->error("║  Bu timezone muammosi EMAS:                                  ║");
            $this->error("║  - Timezone farqi faqat ±5 soat (qo'shni kun)                ║");
            $this->error("║  - Lekin API {$daySpan} kunlik oralikdagi yozuvlarni qaytarmoqda");
            $this->error("║                                                              ║");
            $this->error("║  Bizning yechim: client-side filtr (ImportGrades.php:515)    ║");
            $this->error("╚══════════════════════════════════════════════════════════════╝");
        } else {
            $this->newLine();
            $this->info("API to'g'ri ishlayapti — barcha yozuvlar so'ralgan sanaga mos.");
        }

        return 0;
    }

    /**
     * Talaba ismini olish (turli API formatlar uchun)
     */
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

    /**
     * Noto'g'ri sanadagi yozuvlardan har sanadan teng miqdorda namuna olish.
     */
    private function sampleRecords(array $wrongItems, int $targetCount): array
    {
        // Sanalar bo'yicha guruhlash
        $bySana = [];
        foreach ($wrongItems as $item) {
            $d = Carbon::createFromTimestamp($item['lesson_date'])->toDateString();
            $bySana[$d][] = $item;
        }
        ksort($bySana);

        $sanaCount = count($bySana);
        if ($sanaCount === 0) {
            return [];
        }

        // Har sanadan taxminan teng miqdorda olish
        $perSana = max(1, intdiv($targetCount, $sanaCount));
        $remaining = $targetCount;

        $result = [];
        foreach ($bySana as $sana => $records) {
            $take = min($perSana, $remaining, count($records));
            $result[$sana] = array_slice($records, 0, $take);
            $remaining -= $take;
            if ($remaining <= 0) {
                break;
            }
        }

        // Agar hali joy bo'lsa, qolganlardan to'ldirish
        if ($remaining > 0) {
            foreach ($bySana as $sana => $records) {
                $alreadyTaken = count($result[$sana] ?? []);
                $canTake = min($remaining, count($records) - $alreadyTaken);
                if ($canTake > 0) {
                    $extra = array_slice($records, $alreadyTaken, $canTake);
                    $result[$sana] = array_merge($result[$sana] ?? [], $extra);
                    $remaining -= $canTake;
                }
                if ($remaining <= 0) {
                    break;
                }
            }
        }

        return $result;
    }

    /**
     * Namuna yozuvlar sonini hisoblash
     */
    private function countSampled(array $sampled): int
    {
        $count = 0;
        foreach ($sampled as $records) {
            $count += count($records);
        }
        return $count;
    }
}
