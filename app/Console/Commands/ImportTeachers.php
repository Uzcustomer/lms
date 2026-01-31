<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Models\Teacher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class ImportTeachers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:teachers';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports teachers from HEMIS API and assigns them default credentials';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        //
        $this->info('Fetching teacher data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/employee-list?limit=$pageSize&type=all&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $teachers = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages...");

                foreach ($teachers as $teacherData) {
                    $teacher = Teacher::updateOrCreate(
                        ['employee_id_number' => $teacherData['employee_id_number']],
                        [
                            'hemis_id' => $teacherData['id'],
                            'meta_id' => $teacherData['meta_id'],
                            'full_name' => $teacherData['full_name'],
                            'short_name' => $teacherData['short_name'],
                            'first_name' => $teacherData['first_name'],
                            'second_name' => $teacherData['second_name'],
                            'third_name' => $teacherData['third_name'] ?? null,
                            'birth_date' => date('Y-m-d', $teacherData['birth_date']),
                            'image' => $teacherData['image'] ?? null,
                            'year_of_enter' => $teacherData['year_of_enter'],
                            'specialty' => $teacherData['specialty'] ?? null,
                            'gender' => $teacherData['gender']['name'],
                            'department' => $teacherData['department']['name'],
                            'department_hemis_id' => $teacherData['department']['id'],
                            'employment_form' => $teacherData['employmentForm']['name'],
                            'employment_staff' => $teacherData['employmentStaff']['name'],
                            'staff_position' => $teacherData['staffPosition']['name'],
                            'employee_status' => $teacherData['employeeStatus']['name'],
                            'employee_type' => $teacherData['employeeType']['name'],
                            'contract_number' => $teacherData['contract_number'],
                            'decree_number' => $teacherData['decree_number'] ?? 0,
                            'contract_date' => date('Y-m-d', $teacherData['contract_date']),
                            'decree_date' => date('Y-m-d', $teacherData['decree_date']),
                        ]
                    );

                    if (!$teacher->login) {
                        $teacher->login = $teacherData['employee_id_number'];
                        $teacher->save();
                    }
                    if (!$teacher->password) {
                        $teacher->password = Hash::make('12345678');
                        $teacher->save();
                    }

                    if (!$teacher->hasRole('teacher')) {
                        $teacher->assignRole('teacher');
                    }

                    if (!empty($teacherData['tutorGroups'])) {
                        $groupIds = [];
                        foreach ($teacherData['tutorGroups'] as $tutorGroup) {
                            $group = Group::where('group_hemis_id', $tutorGroup['id'])->first();
                            if ($group) {
                                $groupIds[] = $group->id;
                            }
                        }
                        if (!empty($groupIds)) {
                            $teacher->groups()->sync($groupIds);
                        } else {
                            $teacher->groups()->detach();
                        }
                    } else {
                        $teacher->groups()->detach();
                    }

                    $this->info("Imported teacher: {$teacher->full_name}");
                }

                $page++;
            } else {
                $this->error('Failed to fetch data from the API.');
                break;
            }

        } while ($page <= $totalPages);

        //        $this->info('Teacher import completed successfully.');
    }
}