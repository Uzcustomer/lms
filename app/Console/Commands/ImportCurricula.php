<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Console\Commands\Concerns\ReportsImportChanges;
use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Specialty;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

class ImportCurricula extends Command
{
    use ReportsImportChanges;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:curricula';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports curricula from HEMIS API';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        $telegram->notify("🟢 O'quv rejalar importi boshlandi");
        $this->info('Fetching curricula data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $this->resetImportStats();

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/curriculum-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $curricula = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for curricula...");

                foreach ($curricula as $curriculumData) {
                    $curriculum = Curriculum::updateOrCreate(
                        ['curricula_hemis_id' => $curriculumData['id']],
                        [
                            'name' => $curriculumData['name'],
                            'specialty_hemis_id' => $curriculumData['specialty']['id'],
                            'department_hemis_id' => $curriculumData['department']['id'],
                            'education_year_code' => $curriculumData['educationYear']['code'],
                            'education_year_name' => $curriculumData['educationYear']['name'],
                            'current' => $curriculumData['educationYear']['current'],
                            'education_type_code' => $curriculumData['educationType']['code'],
                            'education_type_name' => $curriculumData['educationType']['name'],
                            'education_form_code' => $curriculumData['educationForm']['code'],
                            'education_form_name' => $curriculumData['educationForm']['name'],
                            'marking_system_code' => $curriculumData['markingSystem']['code'],
                            'marking_system_name' => $curriculumData['markingSystem']['name'],
                            'marking_system_minimum_limit' => $curriculumData['markingSystem']['minimum_limit'],
                            'marking_system_gpa_limit' => $curriculumData['markingSystem']['gpa_limit'],
                            'semester_count' => $curriculumData['semester_count'],
                            'education_period' => $curriculumData['education_period'],
                        ]
                    );

                    // Yangi / yangilangan / o'zgarmagan bo'yicha sanaymiz.
                    $this->trackImport($curriculum, $curriculumData['name'] ?? null);
                }

                $page++;
            } else {
                $telegram->notify("❌ O'quv rejalar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for curricula.');
                break;
            }
        } while ($page <= $totalPages);

        $this->renderImportSummary("O'quv rejalar");
        $telegram->notify($this->importTelegramMessage("O'quv rejalar"));
    }
}