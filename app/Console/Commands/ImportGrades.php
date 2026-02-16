<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Deadline;
use App\Models\ImportStatus;
use App\Models\Student;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Console\Command;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Models\MarkingSystemScore;

class ImportGrades extends Command
{
    protected string $baseUrl;
    protected string $token;
    protected array $report = [];

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url');
        $this->token = config('services.hemis.token');
    }

    protected $signature = 'student:import-data';

    protected $description = 'Import student grades and attendance from Hemis API';

    public function handle()
    {
        $this->info('Starting import of student data from Hemis API...');
        Log::info('Starting import of student data from Hemis API...' . Carbon::now());

        $endpoints = ['attendance-list', 'student-grade-list'];

        foreach ($endpoints as $endpoint) {
            $this->importData($endpoint);
        }

        $this->sendTelegramReport();

        Log::info('Import completed.' . Carbon::now());
        $this->info('Import completed.');
    }

    private function getDeadline($levelCode, $lessonDate)
    {
        $deadline = Deadline::where('level_code', $levelCode)->first();
        if ($deadline) {
            return $lessonDate->copy()->addDays($deadline->deadline_days)->endOfDay();
        }
        return $lessonDate->copy()->addWeek()->endOfDay();
    }

    private function importData($endpoint)
    {
        $importStatus = ImportStatus::firstOrCreate(['endpoint' => $endpoint]);

        $startTimestamp = $importStatus->last_import_timestamp ?? 1745808000;
        $startDate = Carbon::createFromTimestamp($startTimestamp)->startOfDay();
        $endDate = Carbon::now()->startOfDay();

        $period = CarbonPeriod::create($startDate, $endDate);

        $totalDays = 0;
        $successDays = 0;
        $failedPages = [];
        $maxRetries = 3;

        foreach ($period as $date) {
            $from = $date->copy()->startOfDay()->timestamp;
            $to = $date->copy()->endOfDay()->timestamp;

            $currentPage = 1;
            $totalPages = 1;
            $dayHasErrors = false;
            $totalDays++;

            do {
                $queryParams = [
                    'limit' => 200,
                    'page' => $currentPage,
                    'lesson_date_from' => $from,
                    'lesson_date_to' => $to,
                ];

                $retryCount = 0;
                $pageSuccess = false;

                while ($retryCount < $maxRetries && !$pageSuccess) {
                    try {
                        $response = Http::timeout(60)->withoutVerifying()->withToken($this->token)
                            ->get("{$this->baseUrl}/v1/data/{$endpoint}", $queryParams);

                        if ($response->successful()) {
                            $data = $response->json()['data']['items'] ?? [];
                            foreach ($data as $item) {
                                if ($endpoint === 'attendance-list') {
                                    $this->processAttendance($item);
                                } elseif ($endpoint === 'student-grade-list') {
                                    $this->processGrade($item);
                                }
                            }

                            $totalPages = $response->json()['data']['pagination']['pageCount'] ?? $totalPages;
                            $this->info("Processed {$endpoint} data for {$date->toDateString()} - page {$currentPage} of {$totalPages}.");
                            $currentPage++;
                            $pageSuccess = true;

                            sleep(2);
                        } else {
                            $retryCount++;
                            $errorMsg = "Failed {$endpoint} page {$currentPage} for {$date->toDateString()}. Status: {$response->status()}";
                            Log::error($errorMsg . ". Response: " . $response->body());
                            $this->error($errorMsg);

                            if ($retryCount < $maxRetries) {
                                $this->warn("Retrying in 5 seconds... (attempt {$retryCount}/{$maxRetries})");
                                sleep(5);
                            }
                        }
                    } catch (\Exception $e) {
                        $retryCount++;
                        Log::error("Exception for {$endpoint} on {$date->toDateString()} page {$currentPage}: " . $e->getMessage());
                        $this->error("Error on {$date->toDateString()} page {$currentPage}: " . $e->getMessage());

                        if ($retryCount < $maxRetries) {
                            $this->warn("Retrying in 5 seconds... (attempt {$retryCount}/{$maxRetries})");
                            sleep(5);
                        }
                    }
                }

                if (!$pageSuccess) {
                    $this->error("Skipping {$endpoint} page {$currentPage}/{$totalPages} for {$date->toDateString()} after {$maxRetries} attempts.");
                    $failedPages[] = "{$date->toDateString()} page {$currentPage}/{$totalPages}";
                    $dayHasErrors = true;
                    $currentPage++;
                }
            } while ($currentPage <= $totalPages);

            if ($dayHasErrors) {
                $this->warn("Import incomplete for {$date->toDateString()} - timestamp NOT saved. Will retry next run.");
                Log::warning("Import incomplete for {$endpoint} on {$date->toDateString()}");
            } else {
                $importStatus->last_import_timestamp = $to;
                $importStatus->save();
                $successDays++;
                $this->info("Completed {$endpoint} for {$date->toDateString()} ({$totalPages} pages).");
                Log::info("Imported {$endpoint} data for: " . $date->toDateString());
            }
        }

        $this->report[$endpoint] = [
            'total_days' => $totalDays,
            'success_days' => $successDays,
            'failed_pages' => $failedPages,
        ];
    }

    private function sendTelegramReport()
    {
        $lines = ["Import natijasi (" . Carbon::now()->format('d.m.Y H:i') . "):"];

        $hasErrors = false;
        foreach ($this->report as $endpoint => $stats) {
            if ($stats['total_days'] === 0) {
                $lines[] = "{$endpoint}: yangi ma'lumot yo'q";
                continue;
            }

            if (empty($stats['failed_pages'])) {
                $lines[] = "{$endpoint}: {$stats['success_days']}/{$stats['total_days']} kun muvaffaqiyatli";
            } else {
                $hasErrors = true;
                $lines[] = "{$endpoint}: {$stats['success_days']}/{$stats['total_days']} kun muvaffaqiyatli";
                foreach ($stats['failed_pages'] as $failed) {
                    $lines[] = "  xato: {$failed}";
                }
            }
        }

        $emoji = $hasErrors ? 'Baholash importida xatolar bor' : 'Baholash importi muvaffaqiyatli';
        $message = $emoji . "\n" . implode("\n", $lines);

        $this->info($message);
        app(TelegramService::class)->notify($message);
    }

    private function processAttendance($item)
    {
        $student = Student::where('hemis_id', $item['student']['id'])->first();

        if ($student && ($item['absent_off'] > 0 || $item['absent_on'] > 0)) {
            $attendance = Attendance::updateOrCreate(
                [
                    'hemis_id' => $item['id'],
                ],
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
                    'absent_on' => $item['absent_on'],
                    'absent_off' => $item['absent_off'],
                    'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
                    'status' => 'absent',
                ]
            );

            // Optionally, process grade for absence
            $this->processGradeForAbsence($item, $student);
        }
    }

    private function processGradeForAbsence($item, $student)
    {
        $existingGrade = StudentGrade::where([
            'student_id' => $student->id,
            'subject_name' => $item['subject']['name'],
            'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
            'lesson_pair_code' => $item['lessonPair']['code'],
            'lesson_pair_start_time' => $item['lessonPair']['start_time'],
        ])->first();

        if (!$existingGrade) {
            StudentGrade::create([
                'hemis_id' => 111,
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'semester_code' => $item['semester']['code'],
                'semester_name' => $item['semester']['name'],
                'education_year_code' => $item['educationYear']['code'] ?? null,
                'education_year_name' => $item['educationYear']['name'] ?? null,
                'subject_schedule_id' => $item['_subject_schedule'],
                'subject_id' => $item['subject']['id'],
                'subject_name' => $item['subject']['name'],
                'subject_code' => $item['subject']['code'],
                'training_type_code' => $item['trainingType']['code'],
                'training_type_name' => $item['trainingType']['name'],
                'employee_id' => $item['employee']['id'],
                'employee_name' => $item['employee']['name'],
                'lesson_pair_code' => $item['lessonPair']['code'],
                'lesson_pair_name' => $item['lessonPair']['name'],
                'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                'grade' => null,
                'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
                'created_at_api' => Carbon::now(),
                'reason' => 'absent',
                'deadline' => $this->getDeadline($student->level_code, Carbon::createFromTimestamp($item['lesson_date'])),
                'status' => 'pending',
            ]);
        }
    }


    private function processGrade($item)
    {
        $student = Student::where('hemis_id', $item['_student'])->first();

        if ($student) {
            $gradeValue = $item['grade'];
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);

            $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
            $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
                ($student->level_code != 16 && $gradeValue < $studentMinLimit);

            $status = $isLowGrade ? 'pending' : 'recorded';
            $reason = $isLowGrade ? 'low_grade' : null;
            $deadline = $isLowGrade ? $this->getDeadline($student->level_code, $lessonDate) : null;

            StudentGrade::updateOrCreate(
                [
                    'hemis_id' => $item['id'],
                ],
                [
                    'student_id' => $student->id,
                    'student_hemis_id' => $item['_student'],
                    'semester_code' => $item['semester']['code'],
                    'semester_name' => $item['semester']['name'],
                    'education_year_code' => $item['educationYear']['code'] ?? null,
                    'education_year_name' => $item['educationYear']['name'] ?? null,
                    'subject_schedule_id' => $item['_subject_schedule'],
                    'subject_id' => $item['subject']['id'],
                    'subject_name' => $item['subject']['name'],
                    'subject_code' => $item['subject']['code'],
                    'training_type_code' => $item['trainingType']['code'],
                    'training_type_name' => $item['trainingType']['name'],
                    'employee_id' => $item['employee']['id'],
                    'employee_name' => $item['employee']['name'],
                    'lesson_pair_code' => $item['lessonPair']['code'],
                    'lesson_pair_name' => $item['lessonPair']['name'],
                    'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                    'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                    'grade' => $gradeValue,
                    'lesson_date' => $lessonDate,
                    'created_at_api' => Carbon::createFromTimestamp($item['created_at']),
                    'reason' => $reason,
                    'deadline' => $deadline,
                    'status' => $status,
                ]
            );
        }
    }

    private function processGradeBatch(array $items) //yaxshi emas, hemis_id ga unique qo'ysa ishlaydi bo'lmasa dublikat natija beradi
    {
        $hemisIds = collect($items)->pluck('_student')->unique()->values();
        $students = Student::whereIn('hemis_id', $hemisIds)->get()->keyBy('hemis_id');

        $now = now();
        $rows = [];

        foreach ($items as $item) {
            $student = $students[$item['_student']] ?? null;
            if (!$student) {
                continue;
            }

            $gradeValue = $item['grade'];
            $lessonDate = Carbon::createFromTimestamp($item['lesson_date']);
            $studentMinLimit = MarkingSystemScore::getByStudentHemisId($student->hemis_id)->minimum_limit;
            $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
                ($student->level_code != 16 && $gradeValue < $studentMinLimit);

            $status = $isLowGrade ? 'pending' : 'recorded';
            $reason = $isLowGrade ? 'low_grade' : null;
            $deadline = $isLowGrade ? $this->getDeadline($student->level_code, $lessonDate) : null;

            $rows[] = [
                'hemis_id' => $item['id'],
                'student_id' => $student->id,
                'student_hemis_id' => $item['_student'],
                'semester_code' => $item['semester']['code'],
                'semester_name' => $item['semester']['name'],
                'education_year_code' => $item['educationYear']['code'] ?? null,
                'education_year_name' => $item['educationYear']['name'] ?? null,
                'subject_schedule_id' => $item['_subject_schedule'],
                'subject_id' => $item['subject']['id'],
                'subject_name' => $item['subject']['name'],
                'subject_code' => $item['subject']['code'],
                'training_type_code' => $item['trainingType']['code'],
                'training_type_name' => $item['trainingType']['name'],
                'employee_id' => $item['employee']['id'],
                'employee_name' => $item['employee']['name'],
                'lesson_pair_code' => $item['lessonPair']['code'],
                'lesson_pair_name' => $item['lessonPair']['name'],
                'lesson_pair_start_time' => $item['lessonPair']['start_time'],
                'lesson_pair_end_time' => $item['lessonPair']['end_time'],
                'grade' => $gradeValue,
                'lesson_date' => $lessonDate,
                'created_at_api' => Carbon::createFromTimestamp($item['created_at']),
                'reason' => $reason,
                'deadline' => $deadline,
                'status' => $status,
                'updated_at' => $now,
            ];
        }

        if (!empty($rows)) {
            StudentGrade::upsert($rows, ['hemis_id'], array_keys($rows[0]));
        }
    }

}
