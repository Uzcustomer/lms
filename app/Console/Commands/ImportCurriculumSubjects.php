<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CurriculumSubject;
use App\Models\Curriculum;
use App\Models\Specialty;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;

class ImportCurriculumSubjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:curriculum-subjects {--fresh : Boshidan boshlash}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports curriculum subjects from HEMIS API';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        $lock = Cache::lock('import:curriculum-subjects', 3600);

        if (!$lock->get()) {
            $telegram->notify("⚠️ O'quv reja fanlari importi allaqachon ishlayapti");
            $this->warn('Import already running, skipping...');
            return 1;
        }

        try {
            $token = config('services.hemis.token');
            $pageSize = 40;
            $totalImported = 0;
            $startTime = microtime(true);

            // Davom ettirish yoki boshidan boshlash
            $progressKey = 'import:curriculum-subjects:progress';
            $savedProgress = Cache::get($progressKey);

            if ($this->option('fresh') || !$savedProgress) {
                $page = 1;
                $totalPages = 1;
                Cache::forget($progressKey);
                $telegram->notify("🟢 O'quv reja fanlari importi boshlandi");
            } else {
                $page = $savedProgress['page'];
                $totalPages = $savedProgress['totalPages'];
                $telegram->notify("🔄 O'quv reja fanlari importi davom etmoqda ({$page}/{$totalPages} sahifadan)");
            }

            $this->info('Fetching curriculum subjects data from HEMIS API...');

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/curriculum-subject-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $curriculumSubjects = $data['items'];
                $totalPages = $data['pagination']['pageCount'];

                if ($page === 1) {
                    $telegram->notify("📄 O'quv reja fanlari: Jami {$totalPages} sahifa");
                }

                $this->info("Processing page $page of $totalPages for curriculum subjects...");

                foreach ($curriculumSubjects as $subjectData) {
                    CurriculumSubject::updateOrCreate(
                        ['curriculum_subject_hemis_id' => $subjectData['id']],
                        [
                            'curricula_hemis_id' => $subjectData['_curriculum'],
                            'subject_id' => $subjectData['subject']['id'],
                            'subject_name' => $subjectData['subject']['name'],
                            'subject_code' => $subjectData['subject']['code'],
                            'subject_type_code' => $subjectData['subjectType']['code'] ?? null,
                            'subject_type_name' => $subjectData['subjectType']['name'] ?? null,
                            'subject_block_code' => $subjectData['subjectBlock']['code'] ?? null,
                            'subject_block_name' => $subjectData['subjectBlock']['name'] ?? null,
                            'semester_code' => $subjectData['semester']['code'],
                            'semester_name' => $subjectData['semester']['name'],
                            'total_acload' => $subjectData['total_acload'],
                            'credit' => $subjectData['credit'],
                            'in_group' => $subjectData['in_group'],
                            'at_semester' => $subjectData['at_semester'],
                            'subject_details' => ($subjectData['subjectDetails']),
                            'subject_exam_types' => ($subjectData['subjectExamTypes']),
                            'rating_grade_code' => $subjectData['ratingGrade']['code'] ?? null,
                            'rating_grade_name' => $subjectData['ratingGrade']['name'] ?? null,
                            'exam_finish_code' => $subjectData['examFinish']['code'] ?? null,
                            'exam_finish_name' => $subjectData['examFinish']['name'] ?? null,
                            'department_id' => $subjectData['department']['id'] ?? null,
                            'department_name' => $subjectData['department']['name'] ?? null,
                        ]
                    );
                    $totalImported++;

                    $this->info("Imported curriculum subject: {$subjectData['subject']['name']}");
                }

                // Progressni saqlash (7200 soniya = 2 soat)
                $page++;
                Cache::put($progressKey, [
                    'page' => $page,
                    'totalPages' => $totalPages,
                ], 7200);

                if (($page - 1) % 10 === 0 || $page > $totalPages) {
                    $elapsed = microtime(true) - $startTime;
                    $remaining = max(0, $totalPages - $page + 1);
                    $eta = ($page - 1) > 0 ? round(($elapsed / ($page - 1)) * $remaining) : 0;
                    $telegram->notify("⌛ O'quv reja fanlari: " . ($page - 1) . "/{$totalPages} sahifa, ~{$eta} soniya qoldi");
                }
            } else {
                $telegram->notify("❌ O'quv reja fanlari importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for curriculum subjects.');
                break;
            }
        } while ($page <= $totalPages);

            // Import tugadi - progressni tozalash
            Cache::forget($progressKey);
            $telegram->notify("✅ O'quv reja fanlari importi tugadi. Jami: {$totalImported} ta");
            $this->info('Curriculum subjects import completed successfully.');
        } finally {
            $lock->release();
        }
    }
}