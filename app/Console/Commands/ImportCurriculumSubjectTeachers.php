<?php

namespace App\Console\Commands;

use App\Models\CurriculumSubjectTeacher;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportCurriculumSubjectTeachers extends Command
{
    protected $signature = 'import:curriculum-subject-teachers';
    protected $description = 'HEMIS API dan fan-o\'qituvchi biriktirishlarini import qilish';

    public function handle(TelegramService $telegram)
    {
        $telegram->notify("🟢 Fan-o'qituvchi biriktirishlar importi boshlandi");
        $this->info('Fetching curriculum-subject-teacher data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 200;
        $totalImported = 0;
        $importedHemisIds = [];

        do {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->timeout(60)
                ->get("https://student.ttatf.uz/rest/v1/data/curriculum-subject-teacher-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $items = $data['items'];
                $totalPages = $data['pagination']['pageCount'] ?? 1;
                $this->info("Processing page $page of $totalPages...");

                foreach ($items as $item) {
                    CurriculumSubjectTeacher::updateOrCreate(
                        ['hemis_id' => $item['id']],
                        [
                            'semester_id' => $item['_semester'] ?? null,
                            'education_year_id' => $item['_education_year'] ?? null,
                            'curriculum_id' => $item['_curriculum'] ?? null,
                            'department_id' => $item['_department'] ?? null,
                            'group_id' => $item['_group'] ?? null,
                            'training_type_id' => $item['_training_type'] ?? null,
                            'subject_id' => $item['subject']['id'] ?? null,
                            'subject_code' => $item['subject']['code'] ?? null,
                            'subject_name' => $item['subject']['name'] ?? null,
                            'employee_id' => $item['employee']['id'] ?? null,
                            'employee_name' => $item['employee']['name'] ?? null,
                            'training_type_code' => $item['curriculumSubjectDetail']['trainingType']['code'] ?? null,
                            'training_type_name' => $item['curriculumSubjectDetail']['trainingType']['name'] ?? null,
                            'curriculum_subject_detail_id' => $item['curriculumSubjectDetail']['id'] ?? null,
                            'academic_load' => $item['curriculumSubjectDetail']['academic_load'] ?? null,
                            'active' => $item['active'] ?? true,
                            'students_count' => $item['students_count'] ?? null,
                        ]
                    );

                    $importedHemisIds[] = $item['id'];
                    $totalImported++;
                }

                $page++;
            } else {
                $telegram->notify("❌ Fan-o'qituvchi importida xatolik yuz berdi (API status: {$response->status()})");
                $this->error('Failed to fetch data from the API. Status: ' . $response->status());
                break;
            }
        } while ($page <= $totalPages);

        // HEMIS da yo'q yozuvlarni o'chirish
        if (!empty($importedHemisIds)) {
            $deleted = CurriculumSubjectTeacher::whereNotIn('hemis_id', $importedHemisIds)->delete();
            if ($deleted > 0) {
                $this->info("Removed $deleted outdated records.");
            }
        }

        $telegram->notify("✅ Fan-o'qituvchi biriktirishlar importi tugadi. Jami: {$totalImported} ta");
        $this->info("Import completed. Total: {$totalImported} records.");
    }
}
