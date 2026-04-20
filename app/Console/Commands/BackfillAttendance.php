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

    protected $description = 'Faqat o\'zgargan attendance yozuvlarini HEMIS API dan yangilash (baholarni tegmaydi)';

    private string $baseUrl;
    private string $token;

    public function handle(): int
    {
        ini_set('memory_limit', '512M');

        $this->baseUrl = config('services.hemis.base_url') ?? '';
        $this->token = config('services.hemis.token') ?? '';

        if (!$this->baseUrl || !$this->token) {
            $this->error('HEMIS API konfiguratsiyasi topilmadi');
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
            $this->error("Boshlanish sanasi tugash sanasidan katta.");
            return self::FAILURE;
        }

        $period = CarbonPeriod::create($startDate, $endDate);
        $totalDays = abs($startDate->diffInDays($endDate)) + 1;

        $this->info("ATTENDANCE SYNC: {$startDate->toDateString()} → {$endDate->toDateString()} ({$totalDays} kun)");
        $this->info("Faqat o'zgarganlar yangilanadi.");
        $this->newLine();

        // Student lookup cache
        $studentCache = [];

        $successDays = 0;
        $totalUpdated = 0;
        $totalSkipped = 0;
        $totalNew = 0;
        $failedDays = [];
        $dayNum = 0;

        foreach ($period as $date) {
            $dayNum++;
            $dayFrom = $date->copy()->startOfDay()->timestamp;
            $dayTo = $date->copy()->endOfDay()->timestamp;

            $apiItems = $this->fetchAttendance($dayFrom, $dayTo);

            if ($apiItems === false) {
                $failedDays[] = $date->toDateString();
                $this->error("  {$date->toDateString()} — API xato");
                continue;
            }

            if (empty($apiItems)) {
                $successDays++;
                continue;
            }

            // API dan kelgan hemis_id lar
            $apiHemisIds = array_column($apiItems, 'id');

            // DB dan mavjud yozuvlarni olish (faqat hemis_id va absent_on/off)
            $existingRows = DB::table('attendances')
                ->whereIn('hemis_id', $apiHemisIds)
                ->pluck(DB::raw("CONCAT(absent_on, '|', absent_off)"), 'hemis_id')
                ->toArray();

            $dayUpdated = 0;
            $daySkipped = 0;
            $dayNew = 0;

            foreach ($apiItems as $item) {
                $hemisId = $item['id'] ?? 0;
                $absentOn = $item['absent_on'] ?? 0;
                $absentOff = $item['absent_off'] ?? 0;

                if ($absentOn == 0 && $absentOff == 0) continue;

                // DB da bormi va qiymatlari bir xilmi?
                if (isset($existingRows[$hemisId])) {
                    $dbValue = $existingRows[$hemisId];
                    $apiValue = $absentOn . '|' . $absentOff;

                    if ($dbValue === $apiValue) {
                        $daySkipped++;
                        continue; // O'zgarmagan — o'tkazib yuborish
                    }

                    // Faqat absent_on va absent_off yangilash
                    DB::table('attendances')
                        ->where('hemis_id', $hemisId)
                        ->update([
                            'absent_on' => $absentOn,
                            'absent_off' => $absentOff,
                            'updated_at' => now(),
                        ]);
                    $dayUpdated++;
                } else {
                    // Yangi yozuv — to'liq insert
                    $studentId = $item['student']['id'] ?? null;
                    if (!$studentId) continue;

                    if (!isset($studentCache[$studentId])) {
                        $studentCache[$studentId] = Student::where('hemis_id', $studentId)->first();
                    }
                    $student = $studentCache[$studentId];
                    if (!$student) continue;

                    Attendance::create([
                        'hemis_id' => $hemisId,
                        'subject_schedule_id' => $item['_subject_schedule'],
                        'student_id' => $student->id,
                        'student_hemis_id' => $studentId,
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
                    ]);
                    $dayNew++;
                }
            }

            $totalUpdated += $dayUpdated;
            $totalSkipped += $daySkipped;
            $totalNew += $dayNew;
            $successDays++;

            $info = "{$date->toDateString()} — {$dayNum}/{$totalDays}";
            if ($dayUpdated > 0 || $dayNew > 0) {
                $this->info("  {$info} — yangilandi: {$dayUpdated}, yangi: {$dayNew}, o'tkazildi: {$daySkipped}");
            }
        }

        $this->newLine();
        $this->info("TUGADI: {$successDays}/{$totalDays} kun");
        $this->info("  Yangilandi: {$totalUpdated} | Yangi: {$totalNew} | O'tkazildi (bir xil): {$totalSkipped}");

        if (!empty($failedDays)) {
            $this->warn("Xato kunlar: " . implode(', ', $failedDays));
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
}
