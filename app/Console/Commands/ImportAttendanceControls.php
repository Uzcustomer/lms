<?php

namespace App\Console\Commands;

use App\Models\AttendanceControl;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportAttendanceControls extends Command
{
    protected $signature = 'import:attendance-controls';

    protected $description = 'Import attendance controls from HEMIS API';

    public function handle(TelegramService $telegram)
    {
        $telegram->notify("🟢 Davomat nazorati importi boshlandi");
        $this->info('Fetching attendance controls from HEMIS API...');

        $token = config('services.hemis.token');
        $baseUrl = config('services.hemis.base_url');
        $page = 1;
        $pageSize = 200;
        $totalImported = 0;
        $totalPages = 1;

        do {
            try {
                $response = Http::withoutVerifying()
                    ->withToken($token)
                    ->timeout(60)
                    ->get($baseUrl . 'data/attendance-control-list', [
                        'limit' => $pageSize,
                        'page' => $page,
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    if (!($json['success'] ?? false)) {
                        $this->error('API returned success=false');
                        break;
                    }

                    $items = $json['data']['items'] ?? [];
                    $totalPages = $json['data']['pagination']['pageCount'] ?? 1;
                    $this->info("Processing page $page of $totalPages (" . count($items) . " items)...");

                    foreach ($items as $item) {
                        AttendanceControl::updateOrCreate(
                            ['hemis_id' => $item['id']],
                            [
                                'subject_schedule_id' => $item['_subject_schedule'] ?? null,
                                'subject_id' => $item['subject']['id'] ?? null,
                                'subject_code' => $item['subject']['code'] ?? null,
                                'subject_name' => $item['subject']['name'] ?? null,
                                'employee_id' => $item['employee']['id'] ?? null,
                                'employee_name' => $item['employee']['name'] ?? null,
                                'education_year_code' => $item['educationYear']['code'] ?? null,
                                'education_year_name' => $item['educationYear']['name'] ?? null,
                                'semester_code' => $item['semester']['code'] ?? null,
                                'semester_name' => $item['semester']['name'] ?? null,
                                'group_id' => $item['group']['id'] ?? null,
                                'group_name' => $item['group']['name'] ?? null,
                                'education_lang_code' => $item['group']['educationLang']['code'] ?? null,
                                'education_lang_name' => $item['group']['educationLang']['name'] ?? null,
                                'training_type_code' => $item['trainingType']['code'] ?? null,
                                'training_type_name' => $item['trainingType']['name'] ?? null,
                                'lesson_pair_code' => $item['lessonPair']['code'] ?? null,
                                'lesson_pair_name' => $item['lessonPair']['name'] ?? null,
                                'lesson_pair_start_time' => $item['lessonPair']['start_time'] ?? null,
                                'lesson_pair_end_time' => $item['lessonPair']['end_time'] ?? null,
                                'lesson_date' => isset($item['lesson_date']) ? date('Y-m-d H:i:s', $item['lesson_date']) : null,
                                'load' => $item['load'] ?? 2,
                            ]
                        );
                        $totalImported++;
                    }

                    $page++;
                } else {
                    $telegram->notify("❌ Davomat nazorati importida xatolik (API status: {$response->status()})");
                    $this->error('Failed to fetch data from HEMIS API. Status: ' . $response->status());
                    break;
                }
            } catch (\Exception $e) {
                $telegram->notify("❌ Davomat nazorati importida xatolik: " . $e->getMessage());
                $this->error('Error: ' . $e->getMessage());
                break;
            }
        } while ($page <= $totalPages);

        $telegram->notify("✅ Davomat nazorati importi tugadi. Jami: {$totalImported} ta");
        $this->info("Import completed. Total: {$totalImported} records.");
    }
}
