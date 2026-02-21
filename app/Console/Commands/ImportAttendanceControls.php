<?php

namespace App\Console\Commands;

use App\Models\AttendanceControl;
use App\Services\TelegramService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportAttendanceControls extends Command
{
    protected $signature = 'import:attendance-controls
        {--mode=live : Import rejimi: live yoki final}
        {--date= : Faqat shu kun uchun import (Y-m-d)}
        {--silent : Telegram xabar yubormaslik}';

    protected $description = 'Import attendance controls from HEMIS API (live/final rejimlar)';

    public function handle(TelegramService $telegram)
    {
        $mode = $this->option('mode');
        $dateOption = $this->option('date');
        $silent = $this->option('silent');

        $token = config('services.hemis.token');

        if ($mode === 'final') {
            return $this->handleFinalImport($token, $telegram, $silent);
        }

        return $this->handleLiveImport($token, $dateOption, $telegram, $silent);
    }

    // =========================================================================
    // LIVE IMPORT — kun davomida, bugungi davomat nazoratini yangilaydi
    // =========================================================================
    private function handleLiveImport(string $token, ?string $dateOption, TelegramService $telegram, bool $silent)
    {
        $date = $dateOption ? Carbon::parse($dateOption) : Carbon::today();
        $todayStr = $date->toDateString();

        // Agar bugungi davomat allaqachon yakunlangan (is_final=true) bo'lsa, import qilmaslik
        $alreadyFinalized = AttendanceControl::where('lesson_date', '>=', $date->copy()->startOfDay())
            ->where('lesson_date', '<=', $date->copy()->endOfDay())
            ->where('is_final', true)
            ->exists();

        if ($alreadyFinalized) {
            $this->info("Live import: {$todayStr} davomat nazorati allaqachon yakunlangan (is_final=true), o'tkazib yuborildi.");
            return;
        }

        $this->info("Live import: {$todayStr} uchun davomat nazorati yangilanmoqda...");

        $from = $date->copy()->startOfDay()->timestamp;
        $to = $date->copy()->endOfDay()->timestamp;

        // 1-qadam: API dan barcha sahifalarni xotiraga yig'ish
        $items = $this->fetchAllPages($token, $from, $to);

        if ($items === false) {
            $this->error("Live import: API xato — eski ma'lumotlar saqlanib qoldi.");
            Log::error("[AttCtrl LiveImport] API failed for {$todayStr}, skipping soft delete.");
            if (!$silent) {
                $telegram->notify("❌ Davomat nazorati live import xato: {$todayStr}");
            }
            return;
        }

        // 2-qadam: API muvaffaqiyatli — faqat upsert (soft delete yo'q)
        $this->applyAttendanceControls($items, $date, false);

        $this->info("Live import tugadi: {$todayStr}, API dan: " . count($items) . " ta yozuv");
    }

    // =========================================================================
    // FINAL IMPORT — har kuni 02:00 da, yakunlanmagan kunlarni is_final=true qiladi
    // =========================================================================
    private function handleFinalImport(string $token, TelegramService $telegram, bool $silent)
    {
        if (!$silent) {
            $telegram->notify("🟢 Davomat nazorati FINAL import boshlandi (butun semestr)");
        }
        $this->info('Starting FINAL attendance controls import (butun semestr)...');

        // HEMIS dan BARCHA yozuvlarni olish (sana filtrsiz — butun semestr)
        $items = $this->fetchAllPages($token);

        if ($items === false) {
            $this->error('Final import: API xato — import bekor qilindi.');
            if (!$silent) {
                $telegram->notify("❌ Davomat nazorati FINAL import xato: API javob bermadi");
            }
            return;
        }

        if (empty($items)) {
            $this->info('Final import: API dan hech qanday yozuv kelmadi.');
            if (!$silent) {
                $telegram->notify("✅ Davomat nazorati: API da yozuv yo'q.");
            }
            return;
        }

        // Sanalar bo'yicha guruhlash
        $todayStr = Carbon::today()->toDateString();
        $groupedByDate = collect($items)->groupBy(function ($item) {
            return isset($item['lesson_date']) ? date('Y-m-d', $item['lesson_date']) : null;
        })->filter(function ($dateItems, $date) use ($todayStr) {
            // null sanalarni va bugungi sanani o'tkazib yuborish (bugunni live import boshqaradi)
            return $date !== null && $date < $todayStr;
        })->sortKeys();

        $totalDays = $groupedByDate->count();
        $successDays = 0;
        $totalRecords = 0;

        $this->info("Jami: " . count($items) . " yozuv, {$totalDays} kun (bugundan oldingi)");

        foreach ($groupedByDate as $dateStr => $dateItems) {
            $date = Carbon::parse($dateStr);
            $this->applyAttendanceControls($dateItems->toArray(), $date, true);
            $successDays++;
            $totalRecords += $dateItems->count();
            $this->info("  {$dateStr} — {$dateItems->count()} yozuv yakunlandi ({$successDays}/{$totalDays})");
        }

        $msg = "✅ Davomat nazorati FINAL import: {$successDays} kun, {$totalRecords} yozuv (butun semestr)";
        if (!$silent) {
            $telegram->notify($msg);
        }
        $this->info($msg);
    }

    // =========================================================================
    // API dan barcha sahifalarni xotiraga yig'ish (sana filtr bilan)
    // Muvaffaqiyatli bo'lsa array, xato bo'lsa false qaytaradi
    // =========================================================================
    private function fetchAllPages(string $token, ?int $from = null, ?int $to = null): array|false
    {
        $allItems = [];
        $page = 1;
        $pageSize = 200;
        $totalPages = 1;
        $maxRetries = 3;

        do {
            $url = "https://student.ttatf.uz/rest/v1/data/attendance-control-list?limit={$pageSize}&page={$page}";
            if ($from !== null && $to !== null) {
                $url .= "&lesson_date_from={$from}&lesson_date_to={$to}";
            }

            $retryCount = 0;
            $pageSuccess = false;

            while ($retryCount < $maxRetries && !$pageSuccess) {
                try {
                    $response = Http::withoutVerifying()
                        ->withToken($token)
                        ->timeout(60)
                        ->get($url);

                    if ($response->successful()) {
                        $json = $response->json();
                        if (!($json['success'] ?? false)) {
                            $this->error("API returned success=false on page {$page}");
                            return false;
                        }

                        $items = $json['data']['items'] ?? [];
                        $totalPages = $json['data']['pagination']['pageCount'] ?? 1;
                        $allItems = array_merge($allItems, $items);
                        $this->info("Fetched page {$page}/{$totalPages} (" . count($items) . " items)");
                        $pageSuccess = true;
                        sleep(1);
                    } else {
                        $retryCount++;
                        Log::error("[AttCtrl Fetch] Page {$page} failed. Status: {$response->status()}");
                        if ($retryCount < $maxRetries) {
                            sleep(3);
                        }
                    }
                } catch (\Exception $e) {
                    $retryCount++;
                    Log::error("[AttCtrl Fetch] Page {$page} exception: " . $e->getMessage());
                    if ($retryCount < $maxRetries) {
                        sleep(3);
                    }
                }
            }

            if (!$pageSuccess) {
                $this->error("Failed page {$page} after {$maxRetries} retries — ABORTING.");
                return false;
            }

            $page++;
        } while ($page <= $totalPages);

        $this->info("Total items fetched: " . count($allItems));
        return $allItems;
    }

    // =========================================================================
    // Davomat nazoratini bazaga yozish
    // Live: faqat upsert (soft-delete QILMAYDI — API har doim to'liq ma'lumot qaytarmaydi)
    // Final: soft delete + upsert (tunda, barcha ma'lumot to'liq bo'lganda)
    // =========================================================================
    private function applyAttendanceControls(array $items, Carbon $date, bool $isFinal): void
    {
        $dateStart = $date->copy()->startOfDay();
        $dateEnd = $date->copy()->endOfDay();
        $dateStr = $dateStart->toDateString();
        $now = now();

        $softDeletedCount = 0;

        // Soft delete faqat FINAL import da — live import da qilmaslik!
        // Sabab: HEMIS API kun davomida to'liq ma'lumot qaytarmasligi mumkin,
        // oldingi importda kiritilgan yozuvlarni yo'qotib qo'yadi.
        // Tranzaksiyadan TASHQARIDA — tez bajariladi va lockni ushlab turmasligi kerak
        if ($isFinal) {
            $softDeletedCount = AttendanceControl::where('lesson_date', '>=', $dateStart)
                ->where('lesson_date', '<=', $dateEnd)
                ->delete();
            $this->info("Soft deleted {$softDeletedCount} old records for {$dateStr}");
        }

        // Upsert uchun ma'lumotlarni tayyorlash
        $upsertRows = [];
        foreach ($items as $item) {
            $lessonDate = isset($item['lesson_date']) ? date('Y-m-d H:i:s', $item['lesson_date']) : null;

            $upsertRows[] = [
                'hemis_id' => $item['id'],
                'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                'subject_id' => $item['subject']['id'] ?? null,
                'subject_code' => $item['subject']['code'] ?? null,
                'subject_name' => $item['subject']['name'] ?? null,
                'employee_id' => $item['employee']['id'] ?? null,
                'employee_name' => $item['employee']['name'] ?? null,
                'education_year_code' => $item['educationYear']['code'] ?? null,
                'education_year_name' => $item['educationYear']['name'] ?? null,
                'semester_code' => $item['semester']['code'] ?? null,
                'semester_name' => $item['semester']['name'] ?? null,
                'group_id' => $item['group']['id'] ?? null,
                'group_name' => $item['group']['name'] ?? null,
                'education_lang_code' => $item['group']['educationLang']['code'] ?? null,
                'education_lang_name' => $item['group']['educationLang']['name'] ?? null,
                'training_type_code' => $item['trainingType']['code'] ?? null,
                'training_type_name' => $item['trainingType']['name'] ?? null,
                'lesson_pair_code' => $item['lessonPair']['code'] ?? null,
                'lesson_pair_name' => $item['lessonPair']['name'] ?? null,
                'lesson_pair_start_time' => $item['lessonPair']['start_time'] ?? null,
                'lesson_pair_end_time' => $item['lessonPair']['end_time'] ?? null,
                'lesson_date' => $lessonDate,
                'load' => $item['load'] ?? 2,
                'is_final' => $isFinal,
                'deleted_at' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        // Bulk upsert — 200 tadan chunk qilib, har biri alohida tranzaksiya
        // updateOrCreate loop o'rniga ~50x tez, lock vaqti minimal
        $updateColumns = [
            'subject_schedule_id', 'subject_id', 'subject_code', 'subject_name',
            'employee_id', 'employee_name', 'education_year_code', 'education_year_name',
            'semester_code', 'semester_name', 'group_id', 'group_name',
            'education_lang_code', 'education_lang_name', 'training_type_code', 'training_type_name',
            'lesson_pair_code', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time',
            'lesson_date', 'load', 'is_final', 'deleted_at', 'updated_at',
        ];

        foreach (array_chunk($upsertRows, 200) as $chunk) {
            DB::table('attendance_controls')->upsert($chunk, ['hemis_id'], $updateColumns);
        }

        $this->info("Written " . count($upsertRows) . " records for {$dateStr} (is_final=" . ($isFinal ? 'true' : 'false') . ")");
    }
}
