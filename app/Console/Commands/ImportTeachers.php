<?php

namespace App\Console\Commands;

use App\Enums\ProjectRole;
use App\Models\Group;
use App\Models\Teacher;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;

class ImportTeachers extends Command
{
    protected $signature = 'import:teachers';

    protected $description = 'Imports employees from HEMIS API (/v1/data/employee-list)';

    public function handle(TelegramService $telegram)
    {
        $telegram->notify("Xodimlar importi boshlandi");
        $this->info('Fetching employee data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $totalImported = 0;
        $importedEmployeeIds = [];

        do {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->get("https://student.ttatf.uz/rest/v1/data/employee-list?limit=$pageSize&type=all&page=$page");

            if (!$response->successful()) {
                $telegram->notify("Xodimlar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API.');
                break;
            }

            $data = $response->json()['data'];
            $employees = $data['items'];
            $totalPages = $data['pagination']['pageCount'];
            $this->info("Processing page $page of $totalPages...");

            foreach ($employees as $employeeData) {
                $teacher = Teacher::updateOrCreate(
                    ['employee_id_number' => $employeeData['employee_id_number']],
                    [
                        'hemis_id' => $employeeData['id'],
                        'meta_id' => $employeeData['meta_id'],
                        'full_name' => $employeeData['full_name'],
                        'short_name' => $employeeData['short_name'],
                        'first_name' => $employeeData['first_name'],
                        'second_name' => $employeeData['second_name'],
                        'third_name' => $employeeData['third_name'] ?? null,
                        'birth_date' => date('Y-m-d', $employeeData['birth_date']),
                        'image' => $employeeData['image'] ?? null,
                        'year_of_enter' => $employeeData['year_of_enter'],
                        'specialty' => $employeeData['specialty'] ?? null,
                        'gender' => $employeeData['gender']['name'] ?? null,
                        'department' => $employeeData['department']['name'] ?? null,
                        'department_hemis_id' => $employeeData['department']['id'] ?? null,
                        'employment_form' => $employeeData['employmentForm']['name'] ?? null,
                        'employment_staff' => $employeeData['employmentStaff']['name'] ?? null,
                        'staff_position' => $employeeData['staffPosition']['name'] ?? null,
                        'employee_status' => $employeeData['employeeStatus']['name'] ?? null,
                        'employee_type' => $employeeData['employeeType']['name'] ?? null,
                        'contract_number' => $employeeData['contract_number'],
                        'decree_number' => $employeeData['decree_number'] ?? 0,
                        'contract_date' => date('Y-m-d', $employeeData['contract_date']),
                        'decree_date' => date('Y-m-d', $employeeData['decree_date']),
                        'is_active' => true,
                    ]
                );

                $importedEmployeeIds[] = $employeeData['employee_id_number'];

                if (!$teacher->login) {
                    $teacher->login = $employeeData['employee_id_number'];
                    $teacher->save();
                }
                if (!$teacher->password) {
                    $teacher->password = Hash::make('12345678');
                    $teacher->save();
                }

                if (!$teacher->hasRole(ProjectRole::TEACHER->value)) {
                    $teacher->assignRole(ProjectRole::TEACHER->value);
                }

                if (!empty($employeeData['tutorGroups'])) {
                    $groupIds = [];
                    foreach ($employeeData['tutorGroups'] as $tutorGroup) {
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
                $totalImported++;

                $this->info("Imported: {$teacher->full_name}");
            }

            $page++;

        } while ($page <= $totalPages);

        // HEMIS'da bo'lmagan xodimlarni is_active=0 qilish (o'chirmasdan)
        // Telefon, telegram va boshqa lokal ma'lumotlari saqlanib qoladi
        if (!empty($importedEmployeeIds)) {
            $deactivatedCount = Teacher::whereNotIn('employee_id_number', $importedEmployeeIds)
                ->where('is_active', true)
                ->update(['is_active' => false]);

            if ($deactivatedCount > 0) {
                $this->info("Deactivated: {$deactivatedCount} employees not found in HEMIS");
                $telegram->notify("HEMIS'da topilmagan {$deactivatedCount} ta xodim deaktivatsiya qilindi (ma'lumotlari saqlanib qoldi)");
            }
        }

        $telegram->notify("Xodimlar importi tugadi. Jami: {$totalImported} ta");
        $this->info('Employee import completed successfully.');
    }
}
