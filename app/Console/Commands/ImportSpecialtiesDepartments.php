<?php

namespace App\Console\Commands;

use App\Models\Schedule;
use App\Models\Specialty;
use App\Models\Department;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

class ImportSpecialtiesDepartments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:specialties-departments {--local : Lokal bazadan (schedules jadvalidan) import qilish}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports specialties and departments from HEMIS API or local database';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        if ($this->option('local')) {
            return $this->importFromLocal();
        }

        $telegram->notify("🟢 Mutaxassislik va kafedralar importi boshlandi");
        $this->info('Fetching specialties and departments data from HEMIS API...');

        $token = config('services.hemis.token');
        $baseUrl = rtrim(config('services.hemis.base_url'), '/');
        $page = 1;
        $pageSize = 40;
        $totalDepartments = 0;
        $totalSpecialties = 0;

        $this->info("HEMIS API URL: {$baseUrl}");
        $this->info("Token: " . ($token ? mb_substr($token, 0, 10) . '...' : 'TOPILMADI'));

        if (!$token) {
            $this->error('HEMIS_API_TOKEN .env faylida sozlanmagan!');
            return 1;
        }

        do {
            $url = "{$baseUrl}/data/department-list?limit=$pageSize&page=$page";
            $this->info("So'rov: {$url}");

            try {
                $response = Http::withoutVerifying()->withToken($token)->get($url);
            } catch (\Exception $e) {
                $this->error("HTTP xatolik: " . $e->getMessage());
                $telegram->notify("❌ Kafedralar importida HTTP xatolik: " . $e->getMessage());
                break;
            }

            if ($response->successful()) {
                $data = $response->json()['data'];
                $departments = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for departments...");

                foreach ($departments as $departmentData) {
                    Department::updateOrCreate(
                        ['department_hemis_id' => $departmentData['id']],
                        [
                            'name' => $departmentData['name'],
                            'code' => $departmentData['code'],
                            'structure_type_code' => $departmentData['structureType']['code'],
                            'structure_type_name' => $departmentData['structureType']['name'],
                            'locality_type_code' => $departmentData['localityType']['code'],
                            'locality_type_name' => $departmentData['localityType']['name'],
                            'parent_id' => $departmentData['parent'],
                            'active' => $departmentData['active'],
                        ]
                    );
                    $totalDepartments++;

                    $this->info("Imported department: {$departmentData['name']}");
                }

                $page++;
            } else {
                $this->error("API xatolik! Status: {$response->status()}, URL: {$url}");
                $this->error("Javob: " . mb_substr($response->body(), 0, 500));
                $telegram->notify("❌ Kafedralar importida xatolik: HTTP {$response->status()}");
                break;
            }
        } while ($page <= $totalPages);

        $page = 1;

        do {
            $url = "{$baseUrl}/data/specialty-list?limit=$pageSize&page=$page";

            try {
                $response = Http::withoutVerifying()->withToken($token)->get($url);
            } catch (\Exception $e) {
                $this->error("HTTP xatolik: " . $e->getMessage());
                $telegram->notify("❌ Mutaxassisliklar importida HTTP xatolik: " . $e->getMessage());
                break;
            }

            if ($response->successful()) {
                $data = $response->json()['data'];
                $specialties = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for specialties...");

                foreach ($specialties as $specialtyData) {
                    Specialty::updateOrCreate(
                        ['specialty_hemis_id' => $specialtyData['id']],
                        [
                            'code' => $specialtyData['code'] ?? null,
                            'name' => $specialtyData['name'] ?? null,
                            'department_hemis_id' => $specialtyData['department']['id'] ?? null,
                            'department_name' => $specialtyData['department']['name'] ?? null,
                            'department_code' => $specialtyData['department']['code'] ?? null,
                            'locality_type_code' => $specialtyData['localityType']['code'],
                            'locality_type_name' => $specialtyData['localityType']['name'],
                            'education_type_code' => $specialtyData['educationType']['code'],
                            'education_type_name' => $specialtyData['educationType']['name'],
                            'bachelor_specialty_code' => $specialtyData['bachelorSpecialty']['code'] ?? null,
                            'bachelor_specialty_name' => $specialtyData['bachelorSpecialty']['name'] ?? null,
                            'master_specialty_code' => $specialtyData['masterSpecialty']['code'] ?? null,
                            'master_specialty_name' => $specialtyData['masterSpecialty']['name'] ?? null,
                            'doctorate_specialty_code' => $specialtyData['doctorateSpecialty']['code'] ?? null,
                            'doctorate_specialty_name' => $specialtyData['doctorateSpecialty']['name'] ?? null,
                            'ordinature_specialty_code' => $specialtyData['ordinatureSpecialty']['code'] ?? null,
                            'ordinature_specialty_name' => $specialtyData['ordinatureSpecialty']['name'] ?? null,
                        ]
                    );
                    $totalSpecialties++;

                    $this->info("Imported specialty: {$specialtyData['name']}");
                }

                $page++;
            } else {
                $this->error("API xatolik! Status: {$response->status()}, URL: {$url}");
                $this->error("Javob: " . mb_substr($response->body(), 0, 500));
                $telegram->notify("❌ Mutaxassisliklar importida xatolik: HTTP {$response->status()}");
                break;
            }
        } while ($page <= $totalPages);

        $telegram->notify("✅ Mutaxassislik va kafedralar importi tugadi. Kafedralar: {$totalDepartments} ta, Mutaxassisliklar: {$totalSpecialties} ta");
        $this->info('Specialties and departments import completed successfully.');
    }

    /**
     * Lokal bazadagi schedules jadvalidan fakultet va kafedralarni import qilish.
     */
    protected function importFromLocal(): int
    {
        $this->info('Lokal bazadan (schedules jadvalidan) import qilinmoqda...');

        $total = 0;

        // Fakultetlarni schedules jadvalidan olish
        $faculties = DB::table('schedules')
            ->select('faculty_id', 'faculty_name', 'faculty_code', 'faculty_structure_type_code', 'faculty_structure_type_name')
            ->whereNotNull('faculty_id')
            ->where('faculty_id', '>', 0)
            ->distinct()
            ->get()
            ->unique('faculty_id');

        $this->info("Schedules jadvalidan {$faculties->count()} ta fakultet topildi.");

        foreach ($faculties as $faculty) {
            Department::updateOrCreate(
                ['department_hemis_id' => $faculty->faculty_id],
                [
                    'name' => $faculty->faculty_name,
                    'code' => $faculty->faculty_code,
                    'structure_type_code' => '11',
                    'structure_type_name' => 'Fakultet',
                    'locality_type_code' => '11',
                    'locality_type_name' => 'Asosiy',
                    'parent_id' => null,
                    'active' => true,
                ]
            );
            $total++;
            $this->info("Fakultet: {$faculty->faculty_name} (ID: {$faculty->faculty_id}, original code: {$faculty->faculty_structure_type_code})");
        }

        // Kafedralarni schedules jadvalidan olish
        $departments = DB::table('schedules')
            ->select('department_id', 'department_name', 'department_code', 'department_structure_type_code', 'department_structure_type_name')
            ->whereNotNull('department_id')
            ->where('department_id', '>', 0)
            ->distinct()
            ->get()
            ->unique('department_id');

        $this->info("Schedules jadvalidan {$departments->count()} ta kafedra topildi.");

        foreach ($departments as $dept) {
            Department::updateOrCreate(
                ['department_hemis_id' => $dept->department_id],
                [
                    'name' => $dept->department_name,
                    'code' => $dept->department_code,
                    'structure_type_code' => $dept->department_structure_type_code ?? '12',
                    'structure_type_name' => $dept->department_structure_type_name ?? 'Kafedra',
                    'locality_type_code' => '11',
                    'locality_type_name' => 'Asosiy',
                    'parent_id' => null,
                    'active' => true,
                ]
            );
            $total++;
            $this->info("Kafedra: {$dept->department_name} (ID: {$dept->department_id})");
        }

        $this->info("Jami {$total} ta yozuv departments jadvaliga qo'shildi/yangilandi.");
        return 0;
    }
}
