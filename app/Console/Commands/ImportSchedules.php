<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Models\ScheduleReserve;
use Carbon\Carbon;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportSchedules extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:schedules';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import schedules from HEMIS API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching schedules data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 50;

        $date_from = Carbon::now()->subMonth()->toDateTimeString();

        if ($schedule = Schedule::orderBy('id', 'desc')->first()) {
            $date_from = Carbon::parse($schedule->lesson_date)->toDateTimeString();
        }

        $lessonDateFrom = strtotime($date_from);
        do {
            //    $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?limit=$pageSize&page=$page");
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?lesson_date_from={$lessonDateFrom}&limit=$pageSize&page=$page");//sentabr
            // $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?lesson_date_from=1727740800&lesson_date_to=1730419200&limit=$pageSize&page=$page");//oktiyabr


            if ($response->successful()) {


                $data = $response->json()['data'];
                $schedules = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for schedules...");

                foreach ($schedules as $scheduleData) {
                    try {
                        // try {
                        //     $week_start_time = Carbon::createFromTimestamp($scheduleData['weekStartTime']);
                        //     $week_end_time = Carbon::createFromTimestamp($scheduleData['weekEndTime']);
                        // } catch (\Throwable $th) {
                        //     $data_week = $this->getWeekRange($scheduleData['lesson_date']);
                        //     $week_start_time = $data_week['week_start'];
                        //     $week_end_time = $data_week['week_end'];
                        // }
                        Schedule::withTrashed()->updateOrCreate(
                            ['schedule_hemis_id' => $scheduleData['id']],
                            [
                                'subject_id' => $scheduleData['subject']['id'],
                                'subject_name' => $scheduleData['subject']['name'],
                                'subject_code' => $scheduleData['subject']['code'],
                                'semester_code' => $scheduleData['semester']['code'],
                                'semester_name' => $scheduleData['semester']['name'],
                                'education_year_code' => $scheduleData['educationYear']['code'],
                                'education_year_name' => $scheduleData['educationYear']['name'],
                                'education_year_current' => $scheduleData['educationYear']['current'],
                                'group_id' => $scheduleData['group']['id'],
                                'group_name' => $scheduleData['group']['name'],
                                'education_lang_code' => $scheduleData['group']['educationLang']['code'],
                                'education_lang_name' => $scheduleData['group']['educationLang']['name'],
                                'faculty_id' => $scheduleData['faculty']['id'],
                                'faculty_name' => $scheduleData['faculty']['name'],
                                'faculty_code' => $scheduleData['faculty']['code'],
                                'faculty_structure_type_code' => $scheduleData['faculty']['structureType']['code'],
                                'faculty_structure_type_name' => $scheduleData['faculty']['structureType']['name'],
                                'department_id' => $scheduleData['department']['id'],
                                'department_name' => $scheduleData['department']['name'],
                                'department_code' => $scheduleData['department']['code'],
                                'department_structure_type_code' => $scheduleData['department']['structureType']['code'],
                                'department_structure_type_name' => $scheduleData['department']['structureType']['name'],
                                'auditorium_code' => $scheduleData['auditorium']['code'],
                                'auditorium_name' => $scheduleData['auditorium']['name'],
                                'auditorium_type_code' => $scheduleData['auditorium']['auditoriumType']['code'],
                                'auditorium_type_name' => $scheduleData['auditorium']['auditoriumType']['name'],
                                'building_id' => $scheduleData['auditorium']['building']['id'],
                                'building_name' => $scheduleData['auditorium']['building']['name'],
                                'training_type_code' => $scheduleData['trainingType']['code'],
                                'training_type_name' => $scheduleData['trainingType']['name'],
                                'lesson_pair_code' => $scheduleData['lessonPair']['code'],
                                'lesson_pair_name' => $scheduleData['lessonPair']['name'],
                                'lesson_pair_start_time' => $scheduleData['lessonPair']['start_time'],
                                'lesson_pair_end_time' => $scheduleData['lessonPair']['end_time'],
                                'employee_id' => $scheduleData['employee']['id'],
                                'employee_name' => $scheduleData['employee']['name'],
                                'week_start_time' => Carbon::createFromTimestamp($scheduleData['weekStartTime']),
                                'week_end_time' => Carbon::createFromTimestamp($scheduleData['weekEndTime']),
                                'lesson_date' => Carbon::createFromTimestamp($scheduleData['lesson_date']),
                                'week_number' => $scheduleData['_week'],
                                'deleted_at' => null,
                            ]
                        );
                    } catch (\Throwable $th) {
                        $token = "8124753525:AAFmZKZgHaNvorfvtx3XmDdAM5WjJl8PbGo";
                        if (gettype($scheduleData) != "string") {
                            $scheduleData = json_encode($scheduleData) . $th->getMessage() . " " . $th->getLine();
                        }
                        $text_array = str_split($scheduleData, 4000);
                        foreach ($text_array as $val) {
                            $data = [
                                'chat_id' => "904664945",
                                'text' => $val
                            ];
                            try {
                                file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data));
                            } catch (\Throwable $th) {
                            }

                        }
                    }
                }

                $page++;

            } else {
                $token = "8124753525:AAFmZKZgHaNvorfvtx3XmDdAM5WjJl8PbGo";
                if (gettype($response) != "string") {
                    $response = json_encode($response) . $th->getMessage() . " " . $th->getLine();
                }
                $text_array = str_split($response, 4000);
                foreach ($text_array as $val) {
                    $data = [
                        'chat_id' => "904664945",
                        'text' => $val
                    ];
                    try {
                        file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data));
                    } catch (\Throwable $th) {
                    }

                }
                $this->error('Failed to fetch data from the API for schedules.');
                // break;
            }
        } while ($page <= $totalPages);

        $this->info('Schedules import completed successfully.');
    }
    public function getWeekRange($lesson_date)
    {
        // Sanani formatlaymiz va DateTime obyektiga o‘tkazamiz
        $date = new DateTime($lesson_date);

        // Hafta boshini topamiz (Dushanba)
        $week_start = clone $date;
        $week_start->modify('Monday this week');

        // Hafta oxirini topamiz (Yakshanba)
        $week_end = clone $week_start;
        $week_end->modify('Sunday this week');

        // Natijani formatlab qaytaramiz
        return [
            'week_start' => $week_start->format('Y-m-d'),
            'week_end' => $week_end->format('Y-m-d')
        ];
    }
}
