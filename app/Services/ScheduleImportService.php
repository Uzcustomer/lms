<?php

namespace App\Services;

use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ScheduleImportService
{
    public function importBetween(Carbon $from, Carbon $to): void
    {

        $message = "🟢 Jadval importi boshlandi: Boshlanish sanasi: {$from->toDateString()} • Tugash sanasi:   {$to->toDateString()}";
        $this->notifyTelegram($message);
        $token = config('services.hemis.token');
        $limit = 50;
        $page = 1;

//        Schedule::whereBetween('lesson_date', [$from, $to])->delete();
        Schedule::whereBetween('lesson_date', [
            $from,
            $to->copy()->endOfDay()
        ])->delete();

        do {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withToken($token)
                ->get('https://student.ttatf.uz/rest/v1/data/schedule-list', [
                    'lesson_date_from' => $from->timestamp,
                    'lesson_date_to' => $to->copy()->endOfDay()->timestamp,
//                    'lesson_date_to' => $to->timestamp,
                    'limit' => $limit,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::channel('import_schedule')->error('HEMIS API request failed', ['page' => $page, 'status' => $response->status()]);
                $this->notifyTelegram("❌ API request failed on page {$page} (status {$response->status()})");
                break;
            }

            $data = $response->json('data', []);
            $items = $data['items'] ?? [];
            $pages = $data['pagination']['pageCount'] ?? 1;

            if ($page === 1) {
                $this->notifyTelegram("📄 Jami sahifalar: {$pages}");
                $startTime = microtime(true);
            }


            foreach ($items as $item) {
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($this->map($item));
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
            }

            if ($page % 10 === 0 || $page === $pages) {
                $elapsed = microtime(true) - $startTime;
                $processed = $page;
                $remaining = max(0, $pages - $processed);
                $eta = round(($elapsed / $processed) * $remaining);

                $this->notifyTelegram(
                    "⌛ {$remaining} sahifa qoldi, taxminan {$eta} soniya qolmoqda"
                );
            }


            $page++;
            sleep(1);
        } while ($page <= $pages);

        $this->notifyTelegram("✅ Jadval importi tugadi ({$from->toDateString()} — {$to->toDateString()})");
    }

    /**
     * Joriy o'quv yili bo'yicha jadval import (cron uchun)
     */
    public function importByEducationYear(): void
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

        $token = config('services.hemis.token');
        $limit = 50;
        $page = 1;

        // Joriy o'quv yili bo'yicha soft-delete
        $deleted = Schedule::where('education_year_code', $educationYearCode)->delete();
        $this->notifyTelegram("🗑 {$deleted} ta eski jadval o'chirildi (education_year: {$educationYearCode})");

        $totalImported = 0;

        do {
            $response = Http::withoutVerifying()
                ->timeout(60)
                ->withToken($token)
                ->get('https://student.ttatf.uz/rest/v1/data/schedule-list', [
                    '_education_year' => $educationYearCode,
                    'limit' => $limit,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::channel('import_schedule')->error('HEMIS API request failed', ['page' => $page, 'status' => $response->status()]);
                $this->notifyTelegram("❌ API xatolik sahifa {$page} (status {$response->status()})");
                break;
            }

            $data = $response->json('data', []);
            $items = $data['items'] ?? [];
            $pages = $data['pagination']['pageCount'] ?? 1;

            if ($page === 1) {
                $this->notifyTelegram("📄 Jami sahifalar: {$pages}");
                $startTime = microtime(true);
            }

            foreach ($items as $item) {
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($this->map($item));
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
                $totalImported++;
            }

            if ($page % 10 === 0 || $page === $pages) {
                $elapsed = microtime(true) - $startTime;
                $remaining = max(0, $pages - $page);
                $eta = round(($elapsed / $page) * $remaining);
                $this->notifyTelegram("⌛ {$remaining} sahifa qoldi, ~{$eta} soniya");
            }

            $page++;
            sleep(1);
        } while ($page <= $pages);

        $this->notifyTelegram("✅ Cron: Jadval importi tugadi ({$educationYearCode}) — {$totalImported} ta yozuv");
    }

    /**
     * Guruh + fan bo'yicha HEMIS'dan jadval import qilish (sinxron, jurnal uchun)
     */
    public function importForGroupSubject(int $groupId, int $subjectId): array
    {
        $token = config('services.hemis.token');
        $limit = 200;
        $page = 1;
        $totalImported = 0;

        // Faqat shu guruh+fan kombinatsiyasini soft-delete
        Schedule::where('group_id', $groupId)
            ->where('subject_id', $subjectId)
            ->delete();

        do {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withToken($token)
                ->get('https://student.ttatf.uz/rest/v1/data/schedule-list', [
                    '_group' => $groupId,
                    '_subject' => $subjectId,
                    'limit' => $limit,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                Log::channel('import_schedule')->error('HEMIS API xatolik (guruh+fan sync)', [
                    'group_id' => $groupId,
                    'subject_id' => $subjectId,
                    'page' => $page,
                    'status' => $response->status(),
                ]);
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
                $totalImported++;
            }

            $page++;
            if ($page <= $pages) {
                sleep(1);
            }
        } while ($page <= $pages);

        return ['count' => $totalImported];
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
