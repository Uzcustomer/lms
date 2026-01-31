<?php

namespace App\Console\Commands;

use App\Models\Group;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

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
    public function handle(TelegramService $telegram)
    {
        $lock = Cache::lock('import:groups', 3600);

        if (!$lock->get()) {
            $telegram->notify("⚠️ Guruhlar importi allaqachon ishlayapti");
            $this->warn('Import already running, skipping...');
            return 1;
        }

        try {
            $telegram->notify("🟢 Guruhlar importi boshlandi");
            $this->info('Fetching groups data from HEMIS API...');

            $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $totalImported = 0;
        $totalPages = 1;
        $startTime = microtime(true);

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/group-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $groups = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                if ($page === 1) {
                    $telegram->notify("📄 Guruhlar: Jami {$totalPages} sahifa");
                }

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
                    $totalImported++;

                    $this->info("Imported group: {$groupData['name']}");
                }

                if ($page % 5 === 0 || $page === $totalPages) {
                    $elapsed = microtime(true) - $startTime;
                    $remaining = max(0, $totalPages - $page);
                    $eta = $page > 0 ? round(($elapsed / $page) * $remaining) : 0;
                    $telegram->notify("⌛ Guruhlar: {$page}/{$totalPages} sahifa, ~{$eta} soniya qoldi");
                }

                $page++;
            } else {
                $telegram->notify("❌ Guruhlar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for groups.');
                break;
            }
        } while ($page <= $totalPages);

            $telegram->notify("✅ Guruhlar importi tugadi. Jami: {$totalImported} ta");
            $this->info('Groups import completed successfully.');
        } finally {
            $lock->release();
        }
    }
}