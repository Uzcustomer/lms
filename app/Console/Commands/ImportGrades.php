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
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ImportGrades extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */

    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url');
        $this->token = config('services.hemis.token');
    }

    protected $signature = 'student:import-data';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import student grades and attendance from Hemis API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting import of student data from Hemis API...');
        Log::info('Starting import of student data from Hemis API...' . Carbon::now());

        $endpoints = ['attendance-list', 'student-grade-list'];

        foreach ($endpoints as $endpoint) {
            $this->importData($endpoint);
        }
        Log::info('Import completed successfully.' . Carbon::now());
        $this->info('Import completed successfully.');
    }


    //    private function getDeadline($levelCode)
//    {
//        $deadline = Deadline::where('level_code', $levelCode)->first();
//        if ($deadline) {
//            return Carbon::now()->addDays($deadline->deadline_days)->endOfDay();
//        }
//        return Carbon::now()->addWeek()->endOfDay();
//    }

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

        foreach ($period as $date) {
            $from = $date->copy()->startOfDay()->timestamp;
            $to = $date->copy()->endOfDay()->timestamp;

            $currentPage = 1;
            $totalPages = 1;
            $importFailed = false;
            $maxRetries = 3;

            do {
                $queryParams = [
                    'limit' => 50,
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

                            usleep(1000);
                        } else {
                            $retryCount++;
                            $errorMsg = "Failed to fetch {$endpoint} page {$currentPage} for {$date->toDateString()}. Status: {$response->status()}. Response: " . $response->body();
                            Log::error($errorMsg);
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
                    $this->error("Failed to import {$endpoint} page {$currentPage} for {$date->toDateString()} after {$maxRetries} attempts. Stopping this date.");
                    $importFailed = true;
                    break;
                }
            } while ($currentPage <= $totalPages);

            if ($importFailed) {
                $this->error("Import incomplete for {$date->toDateString()} - timestamp NOT saved. Will retry this date next run.");
                Log::error("Import incomplete for {$endpoint} on {$date->toDateString()} - stopped at page {$currentPage} of {$totalPages}");
                break;
            }

            $importStatus->last_import_timestamp = $to;
            $importStatus->save();
            $this->info("Completed import for {$date->toDateString()} ({$totalPages} pages).");
            Log::info("Imported {$endpoint} data for: " . $date->toDateString());
        }
    }


//    private function importData($endpoint)
//    {
//        $importStatus = ImportStatus::firstOrCreate(['endpoint' => $endpoint]);
//
//        $startTimestamp = $importStatus->last_import_timestamp ?? 1745808000;
////        $startDate = Carbon::createFromTimestamp($startTimestamp)->startOfDay();
//        $startDate = Carbon::now()->subDays(10)->startOfDay();
//        $endDate = Carbon::now()->subDays(3)->startOfDay();
//
//        $period = CarbonPeriod::create($startDate, $endDate);
//
//        foreach ($period as $date) {
//            $from = $date->copy()->startOfDay()->timestamp;
//            $to = $date->copy()->endOfDay()->timestamp;
//
//            $currentPage = 1;
//            $totalPages = 1;
//
//            do {
//                $queryParams = [
//                    'limit' => 50,
//                    'page' => $currentPage,
//                    'lesson_date_from' => $from,
//                    'lesson_date_to' => $to,
//                ];
//
//                try {
//                    $response = Http::timeout(30)->withoutVerifying()->withToken($this->token)
//                        ->get("{$this->baseUrl}/v1/data/{$endpoint}", $queryParams);
//
//                    if ($response->successful()) {
//                        $data = $response->json()['data']['items'] ?? [];
//
//                        if (!empty($data)) {
//                            if ($endpoint === 'attendance-list') {
//                                foreach ($data as $item) {
//                                    $this->processAttendance($item);
//                                }
//                            } elseif ($endpoint === 'student-grade-list') {
//                                $this->processGrade($data);
//                            }
//                        }
//
//                        $pagination = $response->json()['data']['pagination'] ?? null;
//                        if ($pagination) {
//                            $totalPages = $pagination['pageCount'];
//                            $this->info("Processed {$endpoint} data for {$date->toDateString()} - page {$currentPage} of {$totalPages}.");
//                            $currentPage++;
//                        } else {
//                            break;
//                        }
//
//                        usleep(1000);
//                    } else {
//                        Log::error("Failed to fetch {$endpoint} data for page {$currentPage}. Response: " . $response->body());
//                        break;
//                    }
//                } catch (\Exception $e) {
//                    Log::error("Exception for {$endpoint} on {$date->toDateString()} page {$currentPage}: " . $e->getMessage());
//                    $this->error("Error occurred on {$date->toDateString()}... Retrying in 5 seconds.");
//                    sleep(5);
//                    continue;
//                }
//            } while ($currentPage <= $totalPages);
//
//            $importStatus->last_import_timestamp = $to;
//            $importStatus->save();
//            Log::info("Imported data for: " . $date->toDateString());
//        }
//    }

    private function processAttendance($item)
    {
        $student = Student::where('hemis_id', $item['student']['id'])->first();

        if ($student && $item['absent_off'] > 0) {
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

            $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
                ($student->level_code != 16 && $gradeValue < 60);

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
            $isLowGrade = ($student->level_code == 16 && $gradeValue < 3) ||
                ($student->level_code != 16 && $gradeValue < 60);

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
