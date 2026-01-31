<?php

namespace App\Console\Commands;

use App\Models\Student;
use App\Models\StudentPerformance;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportStudentPerformance extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'student:import-performance';


    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import student performance from Hemis API';

    protected string $baseUrl;
    protected string $token;

    public function __construct()
    {
        parent::__construct();
        $this->baseUrl = config('services.hemis.base_url');
        $this->token = config('services.hemis.token');
    }


    /**
     * Execute the console command.
     */

    public function handle()
    {
        $this->info('Fetching student attendance and performance data from Hemis API...');

        $queryParams = [
            'limit' => 200,
            'lesson_date_from' => 1727049600, // Example timestamp
            'lesson_date_to' => Carbon::now()->timestamp, // Current date
        ];

        // Process attendance data
        $this->processApiData('attendance-list', $queryParams, function ($item) {
            if ($item['absent_off'] > 0 && Student::where('hemis_id', $item['student']['id'])->first()) {
                $this->processAttendance($item);
            }
        });

        // Process grade data
        $this->processApiData('student-grade-list', $queryParams, function ($item) {
            $student = Student::where('hemis_id', $item['_student'])->first();
            if ($student) {
                $isLowGrade = ($student->level_code == 16 && $item['grade'] < 3) || ($student->level_code != 16 && $item['grade'] < 60);
                if ($isLowGrade) {
                    $this->processGrades($item, $student);
                }
            }
        });
    }

    private function processApiData($endpoint, $queryParams, $processFunction)
    {
        $currentPage = 1;
        $totalPages = 1;

        do {
            $queryParams['page'] = $currentPage;
            $response = Http::withoutVerifying()->withToken($this->token)
                ->get("{$this->baseUrl}/v1/data/{$endpoint}", $queryParams);

            if ($response->successful()) {
                $data = $response->json()['data']['items'];

                foreach ($data as $item) {
                    $processFunction($item);
                }

                // Update pagination info
                if (isset($response->json()['data']['pagination'])) {
                    $pagination = $response->json()['data']['pagination'];
                    $totalPages = $pagination['pageCount'];
                    $this->info("Processed {$endpoint} data for page {$currentPage} of {$totalPages}.");
                } else {
                    $this->info("Processed {$endpoint} data for page {$currentPage}. No pagination info available.");
                    break; // Exit the loop if no pagination info is present
                }

                $currentPage++;
            } else {
                $this->error("Failed to fetch {$endpoint} data from Hemis API.");
                break;
            }
        } while ($currentPage <= $totalPages);
    }




    private function processAttendance($item)
    {
        $student = Student::where('hemis_id', $item['student']['id'])->first();

        if ($student) {
            StudentPerformance::updateOrCreate(
                [
                    'student_id' => $student->id,
                    'hemis_id' => $student->hemis_id,
                    'subject_name' => $item['subject']['name'],
                    'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
                ],
                [
                    'subject_code' => $item['subject']['code'],
                    'semester_code' => $item['semester']['code'],
                    'training_type' => $item['trainingType']['name'],
                    'teacher_name' => $item['employee']['name'],
                    'reason' => 'absence',
                    'deadline' => Carbon::now()->addWeek(),
                    'status' => 'pending',
                ]
            );
        }
    }

    private function processGrades($item, $student)
    {
        StudentPerformance::updateOrCreate(
            [
                'student_id' => $student->id,
                'hemis_id' => $student->hemis_id,
                'subject_name' => $item['subject']['name'],
                'lesson_date' => Carbon::createFromTimestamp($item['lesson_date']),
            ],
            [
                'subject_code' => $item['subject']['code'],
                'semester_code' => $item['semester']['code'],
                'training_type' => $item['trainingType']['name'],
                'teacher_name' => $item['employee']['name'],
                'grade' => $item['grade'],
                'reason' => 'low_grade',
                'deadline' => Carbon::now()->addWeek(),
                'status' => 'pending',
            ]
        );
    }


}