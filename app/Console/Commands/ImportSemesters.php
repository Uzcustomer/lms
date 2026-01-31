<?php

namespace App\Console\Commands;

use App\Models\CurriculumWeek;
use App\Models\Semester;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportSemesters extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:semesters';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import semesters and curriculum weeks from HEMIS API';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        $telegram->notify("🟢 Semestrlar importi boshlandi");
        $this->info('Fetching semesters data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 50;
        $totalImported = 0;
        $totalPages = 1;
        $startTime = microtime(true);

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/semester-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $semesters = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                if ($page === 1) {
                    $telegram->notify("📄 Semestrlar: Jami {$totalPages} sahifa");
                }

                $this->info("Processing page $page of $totalPages for semesters...");

                foreach ($semesters as $semesterData) {
                    $semester = Semester::updateOrCreate(
                        ['semester_hemis_id' => $semesterData['id']],
                        [
                            'code' => $semesterData['code'],
                            'name' => $semesterData['name'],
                            'curriculum_hemis_id' => $semesterData['_curriculum'],
                            'education_year' => $semesterData['_education_year'],
                            'level_code' => $semesterData['level']['code'] ?? null,
                            'level_name' => $semesterData['level']['name'] ?? null,
                            'current' => $semesterData['current'],
                        ]
                    );

                    foreach ($semesterData['curriculumWeeks'] as $weekData) {
                        CurriculumWeek::updateOrCreate(
                            ['curriculum_week_hemis_id' => $weekData['id']],
                            [
                                'semester_hemis_id' => $semester->semester_hemis_id,
                                'current' => $weekData['current'],
                                'start_date' => date('Y-m-d H:i:s', $weekData['start_date']),
                                'end_date' => date('Y-m-d H:i:s', $weekData['end_date']),
                                'start_date_formatted' => $weekData['start_date_f'],
                                'end_date_formatted' => $weekData['end_date_f'],
                            ]
                        );
                    }
                    $totalImported++;

                    $this->info("Imported semester: {$semesterData['name']} with {$semester->curriculumWeeks->count()} weeks");
                }

                if ($page % 10 === 0 || $page === $totalPages) {
                    $elapsed = microtime(true) - $startTime;
                    $remaining = max(0, $totalPages - $page);
                    $eta = $page > 0 ? round(($elapsed / $page) * $remaining) : 0;
                    $telegram->notify("⌛ Semestrlar: {$page}/{$totalPages} sahifa, ~{$eta} soniya qoldi");
                }

                $page++;
            } else {
                $telegram->notify("❌ Semestrlar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for semesters.');
                break;
            }
        } while ($page <= $totalPages);

        $telegram->notify("✅ Semestrlar importi tugadi. Jami: {$totalImported} ta");
        $this->info('Semesters and curriculum weeks import completed successfully.');
    }
}