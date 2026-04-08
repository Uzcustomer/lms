<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Student;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BackfillAttendance extends Command
{
    protected $signature = 'attendance:backfill
        {--from= : Boshlanish sanasi (Y-m-d), masalan: 2026-01-01}
        {--to= : Tugash sanasi (Y-m-d), default kecha}';

    protected $description = 'Faqat attendance yozuvlarini HEMIS API dan qayta import qilish (baholarni o\'zgartirmaydi)';

    private string $baseUrl;
    private string $token;

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $this->baseUrl = config('services.hemis.base_url') ?? '';
        $this->token = config('services.hemis.token') ?? '';

        if (!$this->baseUrl || !$this->token) {
            $this->error('HEMIS API konfiguratsiyasi topilmadi (services.hemis.base_url / token)');
            return self::FAILURE;
        }

        $fromDate = $this->option('from');
        if (!$fromDate) {
            $this->error('--from parametri kerak. Masalan: --from=2026-01-01');
            return self::FAILURE;
        }

        $startDate = Carbon::parse($fromDate)->startOfDay();
        $toDate = $this->option('to');
        $endDate = $toDate ? Carbon::parse($toDate)->startOfDay() : Carbon::yesterday()->startOfDay();

        if ($startDate->greaterThan($endDate)) {
            $this->error("Boshlanish sanasi tugash sanasidan katta bo'lishi mumkin emas.");
            return self::FAILURE;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = abs($startDate->diffInDays($endDate)) + 1;

        $this->info("ATTENDANCE BACKFILL: {$startDate->toDateString()} → {$endDate->toDateString()} ({$totalDays} kun)");
        $this->info("Faqat attendance import qilinadi, baholar o'zgarmaydi.");
        $this->newLine();

        $successDays = 0;
        $failedDays = [];
        $totalUpdated = 0;
        $dayNum = 0;

        foreach ($period as $date) {
            $dayNum++;
            $dayFrom = $date->copy()->startOfDay()->timestamp;
            $dayTo = $date->copy()->endOfDay()->timestamp;

            $items = $this->fetchAttendance($dayFrom, $dayTo);

            if ($items === false) {
                $failedDays[] = $date->toDateString();
                $this->error("  {$date->toDateString()} — API xato");
                continue;
            }

            $dayUpdated = 0;
            foreach ($items as $item) {
                try {
                    if ($this->processAttendance($item)) {
                        $dayUpdated++;
                    }
                } catch (\Throwable $e) {
                    Log::warning("[AttendanceBackfill] Item failed: " . substr($e->getMessage(), 0, 100));
                }
            }

            $totalUpdated += $dayUpdated;
            $successDays++;
            $this->info("  {$date->toDateString()} — {$dayNum}/{$totalDays} — " . count($items) . " ta olindi, {$dayUpdated} ta yangilandi");
        }

        $this->newLine();
        $this->info("TUGADI: {$successDays}/{$totalDays} kun muvaffaqiyatli, jami {$totalUpdated} ta attendance yangilandi.");

        if (!empty($failedDays)) {
            $this->warn("Xato bo'lgan kunlar: " . implode(', ', $failedDays));
        }

        return empty($failedDays) ? self::SUCCESS : self::FAILURE;
    }

    private function fetchAttendance(int $from, int $to): array|false
    {
        $allItems = [];
        $page = 1;
        $totalPages = 1;

        do {
            try {
                $response = Http::connectTimeout(30)->timeout(60)->withoutVerifying()
                    ->withToken($this->token)
                    ->get("{$this->baseUrl}/v1/data/attendance-list", [
                        'limit' => 200,
                        'page' => $page,
                        'lesson_date_from' => $from,
                        'lesson_date_to' => $to,
                    ]);

                if (!$response->successful()) {
                    return false;
                }

                $data = $response->json()['data']['items'] ?? [];
                $pagination = $response->json()['data']['pagination'] ?? [];

                if ($page === 1) {
                    $totalPages = $pagination['pageCount'] ?? 1;
                }

                $allItems = array_merge($allItems, $data);
                $page++;
            } catch (\Throwable $e) {
                Log::error("[AttendanceBackfill] Fetch error: " . $e->getMessage());
                return false;
            }
        } while ($page <= $totalPages);

        return $allItems;
    }

    private function processAttendance(array $item): bool
    {
        $studentId = $item['student']['id'] ?? null;
        if (!$studentId) return false;

        $absentOn = $item['absent_on'] ?? 0;
        $absentOff = $item['absent_off'] ?? 0;
        if ($absentOn == 0 && $absentOff == 0) return false;

        $student = Student::where('hemis_id', $studentId)->first();
        if (!$student) return false;

        Attendance::updateOrCreate(
            ['hemis_id' => $item['id']],
            [
                'subject_schedule_id' => $item['_subject_schedule'],
                'student_id' => $student->id,
                'student_hemis_id' => $item['student']['id'],
                'student_name' => $item['student']['name'],
                'employee_id' => $item['employee']['id'],
                'employee_name' => $item['employee']['name'],
                'subject_id' => $item['subject']['id'],
                'subject_name' => $item['subject']['name'],
                'subject_code' => $item['subject']['code'],
                'education_year_code' => $item['educationYear']['code'],
                'education_year_name' => $item['educationYear']['name'],
                'education_year_current' => $item['educationYear']['current'],
                'semester_code' => $item['semester']['code'],
                'semester_name' => $item['semester']['name'],
                'group_id' => $item['group']['id'],
                'group_name' => $item['group']['name'],
                'education_lang_code' => $item['group']['educationLang']['code'],
                'education_lang_name' => $item['group']['educationLang']['name'],
                'training_type_code' => $item['trainingType']['code'],
                'training_type_name' => $item['trainingType']['name'],
                'lesson_pair_code' => $item['lessonPair']['code'],
                'lesson_pair_name' => $item['lessonPair']['name'],
                'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                'absent_on' => $absentOn,
                'absent_off' => $absentOff,
                'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
                'status' => 'absent',
            ]
        );

        return true;
    }
}
