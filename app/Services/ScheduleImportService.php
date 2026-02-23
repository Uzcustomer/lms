<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScheduleImportService
{
    public function importBetween(Carbon $from, Carbon $to, ?\Closure $onProgress = null): void
    {
        $message = "🟢 Jadval importi boshlandi: {$from->toDateString()} — {$to->toDateString()}";
        $this->notifyTelegram($message);
        $token = config('services.hemis.token');
        $limit = 200;
        $page = 1;
        $importedHemisIds = [];
        $failedPages = [];
        $pages = 1;
        $startTime = microtime(true);

        do {
            $response = $this->fetchPage($token, [
                'lesson_date_from' => $from->timestamp,
                'lesson_date_to' => $to->copy()->endOfDay()->timestamp,
                'limit' => $limit,
                'page' => $page,
            ]);

            if (!$response || !$response->successful()) {
                $status = $response ? $response->status() : 'timeout';
                Log::channel('import_schedule')->warning("HEMIS API sahifa {$page} o'tkazib yuborildi (status {$status})");
                $failedPages[] = $page;

                if ($page === 1) {
                    $this->notifyTelegram("❌ API birinchi sahifada xato (status {$status}) — import to'xtatildi");
                    break;
                }

                if (count($failedPages) >= 5) {
                    $lastFive = array_slice($failedPages, -5);
                    if ($lastFive[4] - $lastFive[0] === 4) {
                        $this->notifyTelegram("❌ Ketma-ket 5 ta sahifa xato — import to'xtatildi (sahifa {$page})");
                        break;
                    }
                }

                $page++;
                sleep(2);
                continue;
            }

            $data = $response->json('data', []);
            $items = $data['items'] ?? [];
            $pages = $data['pagination']['pageCount'] ?? 1;

            if ($page === 1) {
                $this->notifyTelegram("📄 Jami sahifalar: {$pages}");
            }

            foreach ($items as $item) {
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($this->map($item));
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
                $importedHemisIds[] = $item['id'];
            }

            if ($onProgress) {
                $onProgress($page, $pages);
            }

            if ($page % 50 === 0 || $page === $pages) {
                $elapsed = microtime(true) - $startTime;
                $remaining = max(0, $pages - $page);
                $eta = round(($elapsed / $page) * $remaining);
                $failed = count($failedPages);
                $this->notifyTelegram("⌛ {$remaining} sahifa qoldi, ~{$eta}s" . ($failed > 0 ? " ({$failed} xato)" : ""));
            }

            $page++;
            sleep(1);
        } while ($page <= $pages);

        $totalImported = count($importedHemisIds);
        $failedCount = count($failedPages);

        if ($failedCount === 0 && $totalImported > 0) {
            $deleted = Schedule::whereBetween('lesson_date', [$from, $to->copy()->endOfDay()])
                ->whereNotIn('schedule_hemis_id', $importedHemisIds)
                ->delete();
            if ($deleted > 0) {
                $this->notifyTelegram("🗑 {$deleted} ta eski jadval o'chirildi (HEMIS'da topilmadi)");
            }
        }

        $msg = "✅ Jadval importi tugadi ({$from->toDateString()} — {$to->toDateString()}) — {$totalImported} ta yozuv";
        if ($failedCount > 0) {
            $msg .= " ({$failedCount} ta sahifa o'tkazib yuborildi)";
        }
        $this->notifyTelegram($msg);
    }

    /**
     * Joriy o'quv yili bo'yicha jadval import (cron uchun)
     */
    public function importByEducationYear(?\Closure $log = null): void
    {
        $educationYearCode = DB::table('semesters')
            ->where('current', true)
            ->value('education_year');

        if (!$educationYearCode) {
            $this->notifyTelegram("❌ Joriy o'quv yili topilmadi (semesters jadvalida current=true yo'q)");
            Log::channel('import_schedule')->error('Joriy o\'quv yili topilmadi');
            return;
        }

        $this->notifyTelegram("🟢 Cron: Jadval importi boshlandi (o'quv yili: {$educationYearCode})");
        if ($log) $log("O'quv yili: {$educationYearCode}");

        $token = config('services.hemis.token');
        $limit = 200;
        $page = 1;
        $importedHemisIds = [];
        $failedPages = [];
        $pages = 1;
        $startTime = microtime(true);

        do {
            $response = $this->fetchPage($token, [
                '_education_year' => $educationYearCode,
                'limit' => $limit,
                'page' => $page,
            ]);

            if (!$response || !$response->successful()) {
                $status = $response ? $response->status() : 'timeout';
                Log::channel('import_schedule')->warning("HEMIS API sahifa {$page} o'tkazib yuborildi (status {$status})");
                $failedPages[] = $page;
                if ($log) $log("  ❌ Sahifa {$page}/{$pages} — xato (status {$status}), o'tkazib yuborildi");

                if ($page === 1) {
                    $this->notifyTelegram("❌ API birinchi sahifada xato (status {$status}) — import to'xtatildi");
                    if ($log) $log("Birinchi sahifa xato — import to'xtatildi");
                    break;
                }

                if (count($failedPages) >= 5) {
                    $lastFive = array_slice($failedPages, -5);
                    if ($lastFive[4] - $lastFive[0] === 4) {
                        $this->notifyTelegram("❌ Ketma-ket 5 ta sahifa xato — import to'xtatildi (sahifa {$page})");
                        if ($log) $log("Ketma-ket 5 ta xato — import to'xtatildi");
                        break;
                    }
                }

                $page++;
                sleep(2);
                continue;
            }

            $data = $response->json('data', []);
            $items = $data['items'] ?? [];
            $pages = $data['pagination']['pageCount'] ?? 1;
            $count = count($items);
            $total = count($importedHemisIds) + $count;

            if ($page === 1) {
                $this->notifyTelegram("📄 Jami sahifalar: {$pages}");
                if ($log) $log("Jami sahifalar: {$pages}");
            }

            foreach ($items as $item) {
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($this->map($item));
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
                $importedHemisIds[] = $item['id'];
            }

            if ($log) $log("  ✓ Sahifa {$page}/{$pages} — {$count} ta yozuv (jami: {$total})");

            if ($page % 50 === 0 || $page === $pages) {
                $elapsed = microtime(true) - $startTime;
                $remaining = max(0, $pages - $page);
                $eta = round(($elapsed / $page) * $remaining);
                $failed = count($failedPages);
                $this->notifyTelegram("⌛ {$remaining} sahifa qoldi, ~{$eta}s" . ($failed > 0 ? " ({$failed} xato)" : ""));
            }

            $page++;
            sleep(1);
        } while ($page <= $pages);

        $totalImported = count($importedHemisIds);
        $failedCount = count($failedPages);

        // Faqat BARCHA sahifalar muvaffaqiyatli bo'lganda eski yozuvlarni o'chirish
        if ($failedCount === 0 && $totalImported > 0) {
            $deleted = Schedule::where('education_year_code', $educationYearCode)
                ->whereNotIn('schedule_hemis_id', $importedHemisIds)
                ->delete();
            if ($deleted > 0) {
                $this->notifyTelegram("🗑 {$deleted} ta eski jadval o'chirildi (HEMIS'da topilmadi)");
            }
        }

        $msg = "✅ Cron: Jadval importi tugadi ({$educationYearCode}) — {$totalImported} ta yozuv";
        if ($failedCount > 0) {
            $msg .= " ({$failedCount} ta sahifa o'tkazib yuborildi)";
        }
        $this->notifyTelegram($msg);
        if ($log) $log($msg);
    }

    /**
     * Guruh + fan bo'yicha HEMIS'dan jadval import qilish (sinxron, jurnal uchun)
     */
    public function importForGroupSubject(int $groupId, int $subjectId): array
    {
        $token = config('services.hemis.token');
        $limit = 200;
        $page = 1;
        $importedHemisIds = [];
        $apiFailed = false;

        do {
            $response = $this->fetchPage($token, [
                '_group' => $groupId,
                '_subject' => $subjectId,
                'limit' => $limit,
                'page' => $page,
            ], 30);

            if (!$response || !$response->successful()) {
                $status = $response ? $response->status() : 'timeout';
                Log::channel('import_schedule')->error('HEMIS API xatolik (guruh+fan sync)', [
                    'group_id' => $groupId,
                    'subject_id' => $subjectId,
                    'page' => $page,
                    'status' => $status,
                ]);
                $apiFailed = true;
                break;
            }

            $data = $response->json('data', []);
            $items = $data['items'] ?? [];
            $pages = $data['pagination']['pageCount'] ?? 1;

            foreach ($items as $item) {
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($this->map($item));
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
                $importedHemisIds[] = $item['id'];
            }

            $page++;
            if ($page <= $pages) {
                sleep(1);
            }
        } while ($page <= $pages);

        $totalImported = count($importedHemisIds);

        // Faqat muvaffaqiyatli bo'lganda — HEMIS'da yo'q yozuvlarni soft-delete
        if (!$apiFailed && $totalImported > 0) {
            Schedule::where('group_id', $groupId)
                ->where('subject_id', $subjectId)
                ->whereNotIn('schedule_hemis_id', $importedHemisIds)
                ->delete();
        }

        return ['count' => $totalImported, 'failed' => $apiFailed];
    }

    /**
     * HEMIS API sahifasini olish — 502/503/timeout bo'lsa 3 marta qayta urinadi
     */
    protected function fetchPage(string $token, array $params, int $timeout = 60): ?\Illuminate\Http\Client\Response
    {
        $maxRetries = 3;
        $delays = [5, 10, 20]; // soniyalarda

        for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
            try {
                $response = Http::withoutVerifying()
                    ->timeout($timeout)
                    ->withToken($token)
                    ->get('https://student.ttatf.uz/rest/v1/data/schedule-list', $params);

                if ($response->successful() || $response->status() < 500) {
                    return $response;
                }

                // 5xx xato — retry
                if ($attempt < $maxRetries) {
                    $delay = $delays[$attempt - 1] ?? 20;
                    Log::channel('import_schedule')->warning("HEMIS API {$response->status()} — sahifa {$params['page']}, {$attempt}/{$maxRetries} urinish, {$delay}s kutish");
                    sleep($delay);
                } else {
                    return $response;
                }
            } catch (\Throwable $e) {
                if ($attempt < $maxRetries) {
                    $delay = $delays[$attempt - 1] ?? 20;
                    Log::channel('import_schedule')->warning("HEMIS API timeout — sahifa {$params['page']}, {$attempt}/{$maxRetries} urinish, {$delay}s kutish");
                    sleep($delay);
                } else {
                    Log::channel('import_schedule')->error("HEMIS API {$maxRetries} marta xatolik: " . $e->getMessage());
                    return null;
                }
            }
        }

        return null;
    }

    protected function map(array $d): array
    {
        return [
            'subject_id' => $d['subject']['id'],
            'subject_name' => $d['subject']['name'],
            'subject_code' => $d['subject']['code'],
            'semester_code' => $d['semester']['code'],
            'semester_name' => $d['semester']['name'],
            'education_year_code' => $d['educationYear']['code'],
            'education_year_name' => $d['educationYear']['name'],
            'education_year_current' => $d['educationYear']['current'],
            'group_id' => $d['group']['id'],
            'group_name' => $d['group']['name'],
            'education_lang_code' => $d['group']['educationLang']['code'],
            'education_lang_name' => $d['group']['educationLang']['name'],
            'faculty_id' => $d['faculty']['id'],
            'faculty_name' => $d['faculty']['name'],
            'faculty_code' => $d['faculty']['code'],
            'faculty_structure_type_code' => $d['faculty']['structureType']['code'],
            'faculty_structure_type_name' => $d['faculty']['structureType']['name'],
            'department_id' => $d['department']['id'],
            'department_name' => $d['department']['name'],
            'department_code' => $d['department']['code'],
            'department_structure_type_code' => $d['department']['structureType']['code'],
            'department_structure_type_name' => $d['department']['structureType']['name'],
            'auditorium_code' => $d['auditorium']['code'],
            'auditorium_name' => $d['auditorium']['name'],
            'auditorium_type_code' => $d['auditorium']['auditoriumType']['code'],
            'auditorium_type_name' => $d['auditorium']['auditoriumType']['name'],
            'building_id' => $d['auditorium']['building']['id'],
            'building_name' => $d['auditorium']['building']['name'],
            'training_type_code' => $d['trainingType']['code'],
            'training_type_name' => $d['trainingType']['name'],
            'lesson_pair_code' => $d['lessonPair']['code'],
            'lesson_pair_name' => $d['lessonPair']['name'],
            'lesson_pair_start_time' => $d['lessonPair']['start_time'],
            'lesson_pair_end_time' => $d['lessonPair']['end_time'],
            'employee_id' => $d['employee']['id'],
            'employee_name' => $d['employee']['name'],
            'week_start_time' => isset($d['weekStartTime']) && $d['weekStartTime'] ? Carbon::createFromTimestamp($d['weekStartTime']) : null,
            'week_end_time' => isset($d['weekEndTime']) && $d['weekEndTime'] ? Carbon::createFromTimestamp($d['weekEndTime']) : null,
            'lesson_date' => isset($d['lesson_date']) && $d['lesson_date'] ? Carbon::createFromTimestamp($d['lesson_date']) : null,
            'week_number' => $d['_week'],
        ];
    }

    protected function notifyTelegram(string $message): void
    {
        $botToken = config('services.telegram.bot_token');
        $chatId = config('services.telegram.chat_id');

        try {
            Http::retry(3, 1000)
                ->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                    'chat_id' => $chatId,
                    'text' => $message,
                ])
                ->throw();
        } catch (\Throwable $e) {
            Log::channel('import_schedule')->error('Telegramga yuborishda xato: ' . $e->getMessage());
        }
    }
}
