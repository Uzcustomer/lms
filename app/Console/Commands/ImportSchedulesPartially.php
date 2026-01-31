<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use Carbon\Carbon;
use DateTime;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportSchedulesPartially extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:schedules-partially {--date_from=} {--date_to=}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import schedules from HEMIS API';

    /**
     * Execute the console command.
     */

    public function handle(): int
    {
        $token = config('services.hemis.token');
        $limit = 50;
        $page = 1;

        $from = $this->option('date_from') ? Carbon::parse($this->option('date_from')) : Carbon::now()->subWeek();
        $to = $this->option('date_to') ? Carbon::parse($this->option('date_to')) : Carbon::now();

        Schedule::whereBetween('lesson_date', [$from, $to])->delete();

        while (true) {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->retry(2)
                ->get('https://student.ttatf.uz/rest/v1/data/schedule-list', [
                    'lesson_date_from' => $from->timestamp,
                    'lesson_date_to' => $to->timestamp,
                    'limit' => $limit,
                    'page' => $page,
                ]);

            if (!$response->successful()) {
                $this->error('API error');
                return self::FAILURE;
            }

            $payload = $response->json('data');
            $items = $payload['items'] ?? [];
            $pages = $payload['pagination']['pageCount'] ?? 1;
            $this->info("Processing page {$page} of {$pages}");

            if (empty($items)) {
                break;
            }

            foreach ($items as $item) {
                $attrs = $this->map($item);
                $schedule = Schedule::withTrashed()->firstOrNew(['schedule_hemis_id' => $item['id']]);
                $schedule->fill($attrs);
                if ($schedule->trashed()) {
                    $schedule->restore();
                }
                $schedule->save();
            }

            if ($page >= $pages) {
                break;
            }
            $page++;
            sleep(2);
        }

        $this->info('Schedules imported.');
        return self::SUCCESS;
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

//    public function handle()
//    {
//        $this->info('Fetching schedules data from HEMIS API...');
//
//        $token = config('services.hemis.token');
//        $page = 1;
//        $pageSize = 50;
//        $date_from = $this->option('date_from') ?? Carbon::now()->subWeek()->toDateTimeString();
//        $date_to = $this->option('date_to') ?? Carbon::now()->toDateTimeString();
//        $lessonDateFrom = Carbon::parse($date_from)->timestamp;
//        $lessonDateTo = Carbon::parse($date_to)->timestamp;
//        Schedule::whereBetween('lesson_date', [$date_from, $date_to])->update([
//            'deleted_at'=>Carbon::now()
//        ]);
//        do {
//            //    $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?limit=$pageSize&page=$page");
//            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?lesson_date_from={$lessonDateFrom}&lesson_date_to={$lessonDateTo}&limit=$pageSize&page=$page");//sentabr
//            // $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/schedule-list?lesson_date_from=1727740800&lesson_date_to=1730419200&limit=$pageSize&page=$page");//oktiyabr
//
//
//            if ($response->successful()) {
//
//
//                $data = $response->json()['data'];
//                $schedules = $data['items'];
//                $totalPages = $data['pagination']['pageCount'];
//                $this->info("Processing page $page of $totalPages for schedules...");
//
//                foreach ($schedules as $scheduleData) {
//                    try {
//                        // try {
//                        //     $week_start_time = Carbon::createFromTimestamp($scheduleData['weekStartTime']);
//                        //     $week_end_time = Carbon::createFromTimestamp($scheduleData['weekEndTime']);
//                        // } catch (\Throwable $th) {
//                        //     $data_week = $this->getWeekRange($scheduleData['lesson_date']);
//                        //     $week_start_time = $data_week['week_start'];
//                        //     $week_end_time = $data_week['week_end'];
//                        // }
//                        Schedule::withTrashed()->updateOrCreate(
//                            ['schedule_hemis_id' => $scheduleData['id']],
//                            [
//                                'subject_id' => $scheduleData['subject']['id'],
//                                'subject_name' => $scheduleData['subject']['name'],
//                                'subject_code' => $scheduleData['subject']['code'],
//                                'semester_code' => $scheduleData['semester']['code'],
//                                'semester_name' => $scheduleData['semester']['name'],
//                                'education_year_code' => $scheduleData['educationYear']['code'],
//                                'education_year_name' => $scheduleData['educationYear']['name'],
//                                'education_year_current' => $scheduleData['educationYear']['current'],
//                                'group_id' => $scheduleData['group']['id'],
//                                'group_name' => $scheduleData['group']['name'],
//                                'education_lang_code' => $scheduleData['group']['educationLang']['code'],
//                                'education_lang_name' => $scheduleData['group']['educationLang']['name'],
//                                'faculty_id' => $scheduleData['faculty']['id'],
//                                'faculty_name' => $scheduleData['faculty']['name'],
//                                'faculty_code' => $scheduleData['faculty']['code'],
//                                'faculty_structure_type_code' => $scheduleData['faculty']['structureType']['code'],
//                                'faculty_structure_type_name' => $scheduleData['faculty']['structureType']['name'],
//                                'department_id' => $scheduleData['department']['id'],
//                                'department_name' => $scheduleData['department']['name'],
//                                'department_code' => $scheduleData['department']['code'],
//                                'department_structure_type_code' => $scheduleData['department']['structureType']['code'],
//                                'department_structure_type_name' => $scheduleData['department']['structureType']['name'],
//                                'auditorium_code' => $scheduleData['auditorium']['code'],
//                                'auditorium_name' => $scheduleData['auditorium']['name'],
//                                'auditorium_type_code' => $scheduleData['auditorium']['auditoriumType']['code'],
//                                'auditorium_type_name' => $scheduleData['auditorium']['auditoriumType']['name'],
//                                'building_id' => $scheduleData['auditorium']['building']['id'],
//                                'building_name' => $scheduleData['auditorium']['building']['name'],
//                                'training_type_code' => $scheduleData['trainingType']['code'],
//                                'training_type_name' => $scheduleData['trainingType']['name'],
//                                'lesson_pair_code' => $scheduleData['lessonPair']['code'],
//                                'lesson_pair_name' => $scheduleData['lessonPair']['name'],
//                                'lesson_pair_start_time' => $scheduleData['lessonPair']['start_time'],
//                                'lesson_pair_end_time' => $scheduleData['lessonPair']['end_time'],
//                                'employee_id' => $scheduleData['employee']['id'],
//                                'employee_name' => $scheduleData['employee']['name'],
//                                'week_start_time' => Carbon::createFromTimestamp($scheduleData['weekStartTime']),
//                                'week_end_time' => Carbon::createFromTimestamp($scheduleData['weekEndTime']),
//                                'lesson_date' => Carbon::createFromTimestamp($scheduleData['lesson_date']),
//                                'week_number' => $scheduleData['_week'],
//                            ]
//                        );
//                    } catch (\Throwable $th) {
//                        $token = "8124753525:AAFmZKZgHaNvorfvtx3XmDdAM5WjJl8PbGo";
//                        if (gettype($scheduleData) != "string") {
//                            $scheduleData = json_encode($scheduleData) . $th->getMessage() . " " . $th->getLine();
//                        }
//                        $text_array = str_split($scheduleData, 4000);
//                        foreach ($text_array as $val) {
//                            $data = [
//                                'chat_id' => "904664945",
//                                'text' => $val
//                            ];
//                            try {
//                                file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data));
//                            } catch (\Throwable $th) {
//                            }
//
//                        }
//                    }
//                }
//
//                $page++;
//
//            } else {
//                $token = "8124753525:AAFmZKZgHaNvorfvtx3XmDdAM5WjJl8PbGo";
//                if (gettype($response) != "string") {
//                    $response = json_encode($response) . $th->getMessage() . " " . $th->getLine();
//                }
//                $text_array = str_split($response, 4000);
//                foreach ($text_array as $val) {
//                    $data = [
//                        'chat_id' => "904664945",
//                        'text' => $val
//                    ];
//                    try {
//                        file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data));
//                    } catch (\Throwable $th) {
//                    }
//
//                }
//                $this->error('Failed to fetch data from the API for schedules.');
//                // break;
//            }
//        } while ($page <= $totalPages);
//
//        $this->info('Schedules import completed successfully.');
//    }
//    public function getWeekRange($lesson_date)
//    {
//        // Sanani formatlaymiz va DateTime obyektiga o‘tkazamiz
//        $date = new DateTime($lesson_date);
//
//        // Hafta boshini topamiz (Dushanba)
//        $week_start = clone $date;
//        $week_start->modify('Monday this week');
//
//        // Hafta oxirini topamiz (Yakshanba)
//        $week_end = clone $week_start;
//        $week_end->modify('Sunday this week');
//
//        // Natijani formatlab qaytaramiz
//        return [
//            'week_start' => $week_start->format('Y-m-d'),
//            'week_end' => $week_end->format('Y-m-d')
//        ];
//    }
}
