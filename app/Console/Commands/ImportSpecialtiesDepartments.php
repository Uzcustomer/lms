<?php

namespace App\Console\Commands;

use App\Models\Specialty;
use App\Models\Department;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ImportSpecialtiesDepartments extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:specialties-departments';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports specialties and departments from HEMIS API';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        $lock = Cache::lock('import:specialties-departments', 3600);

        if (!$lock->get()) {
            $telegram->notify("⚠️ Mutaxassislik va kafedralar importi allaqachon ishlayapti");
            $this->warn('Import already running, skipping...');
            return 1;
        }

        try {
            $telegram->notify("🟢 Mutaxassislik va kafedralar importi boshlandi");
            $this->info('Fetching specialties and departments data from HEMIS API...');

            $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $totalDepartments = 0;
        $totalSpecialties = 0;
        $totalPages = 1;
        $startTime = microtime(true);

        // Kafedralar import
        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/department-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $departments = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                if ($page === 1) {
                    $telegram->notify("📄 Kafedralar: Jami {$totalPages} sahifa");
                }

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

                if ($page % 5 === 0 || $page === $totalPages) {
                    $elapsed = microtime(true) - $startTime;
                    $remaining = max(0, $totalPages - $page);
                    $eta = $page > 0 ? round(($elapsed / $page) * $remaining) : 0;
                    $telegram->notify("⌛ Kafedralar: {$page}/{$totalPages} sahifa, ~{$eta} soniya qoldi");
                }

                $page++;
            } else {
                $telegram->notify("❌ Kafedralar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for departments.');
                break;
            }
        } while ($page <= $totalPages);

        // Mutaxassisliklar import
        $page = 1;
        $startTime = microtime(true);

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/specialty-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $specialties = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                if ($page === 1) {
                    $telegram->notify("📄 Mutaxassisliklar: Jami {$totalPages} sahifa");
                }

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

                if ($page % 5 === 0 || $page === $totalPages) {
                    $elapsed = microtime(true) - $startTime;
                    $remaining = max(0, $totalPages - $page);
                    $eta = $page > 0 ? round(($elapsed / $page) * $remaining) : 0;
                    $telegram->notify("⌛ Mutaxassisliklar: {$page}/{$totalPages} sahifa, ~{$eta} soniya qoldi");
                }

                $page++;
            } else {
                $telegram->notify("❌ Mutaxassisliklar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for specialties.');
                break;
            }
        } while ($page <= $totalPages);

            $telegram->notify("✅ Mutaxassislik va kafedralar importi tugadi. Kafedralar: {$totalDepartments} ta, Mutaxassisliklar: {$totalSpecialties} ta");
            $this->info('Specialties and departments import completed successfully.');
        } finally {
            $lock->release();
        }
    }
}
