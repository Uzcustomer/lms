<?php

namespace App\Console\Commands;

use App\Models\Group;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportGroups extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:groups';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports groups from HEMIS API';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Fetching groups data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/group-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $groups = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for groups...");

                foreach ($groups as $groupData) {
                    Group::updateOrCreate(
                        ['group_hemis_id' => $groupData['id']],
                        [
                            'name' => $groupData['name'],
                            'department_hemis_id' => $groupData['department']['id'],
                            'department_name' => $groupData['department']['name'],
                            'department_code' => $groupData['department']['code'],
                            'department_structure_type_code' => $groupData['department']['structureType']['code'],
                            'department_structure_type_name' => $groupData['department']['structureType']['name'],
                            'department_locality_type_code' => $groupData['department']['localityType']['code'],
                            'department_locality_type_name' => $groupData['department']['localityType']['name'],
                            'department_active' => $groupData['department']['active'],
                            'specialty_hemis_id' => $groupData['specialty']['id'],
                            'specialty_code' => $groupData['specialty']['code'],
                            'specialty_name' => $groupData['specialty']['name'],
                            'education_lang_code' => $groupData['educationLang']['code'],
                            'education_lang_name' => $groupData['educationLang']['name'],
                            'curriculum_hemis_id' => $groupData['_curriculum'],
                        ]
                    );

                    $this->info("Imported group: {$groupData['name']}");
                }

                $page++;
            } else {
                $this->error('Failed to fetch data from the API for groups.');
                break;
            }
        } while ($page <= $totalPages);

        $this->info('Groups import completed successfully.');
    }
}