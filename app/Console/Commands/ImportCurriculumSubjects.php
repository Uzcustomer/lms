<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\CurriculumSubject;
use App\Models\Curriculum;
use App\Models\Specialty;
use App\Services\TelegramService;
use Illuminate\Support\Facades\Http;

class ImportCurriculumSubjects extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:curriculum-subjects';

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
        $telegram->notify("🟢 O'quv reja fanlari importi boshlandi");
        $this->info('Fetching curriculum subjects data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $totalImported = 0;
        $nofaol = 0;
        $restored = 0;
        $importedHemisIds = [];

        do {
            $response = Http::withoutVerifying()->withToken($token)->get("https://student.ttatf.uz/rest/v1/data/curriculum-subject-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $curriculumSubjects = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for curriculum subjects...");

                foreach ($curriculumSubjects as $subjectData) {
                    // withTrashed — avval soft-delete qilingan fanlarni ham topish uchun
                    $subject = CurriculumSubject::withTrashed()
                        ->where('curriculum_subject_hemis_id', $subjectData['id'])
                        ->first();

                    $attributes = [
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
                        'is_active' => $subjectData['active'] ?? true,
                        'subject_details' => ($subjectData['subjectDetails']),
                        'subject_exam_types' => ($subjectData['subjectExamTypes']),
                        'rating_grade_code' => $subjectData['ratingGrade']['code'] ?? null,
                        'rating_grade_name' => $subjectData['ratingGrade']['name'] ?? null,
                        'exam_finish_code' => $subjectData['examFinish']['code'] ?? null,
                        'exam_finish_name' => $subjectData['examFinish']['name'] ?? null,
                        'department_id' => $subjectData['department']['id'] ?? null,
                        'department_name' => $subjectData['department']['name'] ?? null,
                    ];

                    if ($subject) {
                        // Avval o'chirilgan bo'lsa — qayta tiklash
                        if ($subject->trashed()) {
                            $subject->restore();
                            $restored++;
                            $this->info("Restored: {$subjectData['subject']['name']}");
                        }
                        $subject->update($attributes);
                    } else {
                        CurriculumSubject::create(array_merge(
                            ['curriculum_subject_hemis_id' => $subjectData['id']],
                            $attributes
                        ));
                    }

                    // Nofaol (HEMIS active: false) sanash
                    if (!($subjectData['active'] ?? true)) {
                        $nofaol++;
                    }

                    $importedHemisIds[] = $subjectData['id'];
                    $totalImported++;

                    $this->info("Imported curriculum subject: {$subjectData['subject']['name']}");
                }

                $page++;
            } else {
                $telegram->notify("❌ O'quv reja fanlari importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for curriculum subjects.');
                break;
            }
        } while ($page <= $totalPages);

        // API'dan qaytmagan fanlarni soft-delete qilish (HEMIS'dan o'chirilgan)
        $deleted = 0;
        if (!empty($importedHemisIds)) {
            $deleted = CurriculumSubject::whereNotIn('curriculum_subject_hemis_id', $importedHemisIds)
                ->count();

            if ($deleted > 0) {
                CurriculumSubject::whereNotIn('curriculum_subject_hemis_id', $importedHemisIds)
                    ->delete();
            }
        }

        $msg = "✅ O'quv reja fanlari importi tugadi. Jami: {$totalImported} ta";
        if ($nofaol > 0) {
            $msg .= ", nofaol: {$nofaol} ta";
        }
        if ($deleted > 0) {
            $msg .= ", o'chirilgan: {$deleted} ta";
        }
        if ($restored > 0) {
            $msg .= ", qaytgan: {$restored} ta";
        }

        $telegram->notify($msg);
        $this->info("Import completed. Total: {$totalImported}, inactive: {$nofaol}, deleted: {$deleted}, restored: {$restored}");
    }
}
