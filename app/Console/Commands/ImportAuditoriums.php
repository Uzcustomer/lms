<?php

namespace App\Console\Commands;

use App\Models\Auditorium;
use App\Services\TelegramService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class ImportAuditoriums extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:auditoriums';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Imports auditoriums from HEMIS API (/v1/data/auditorium-list)';

    /**
     * Execute the console command.
     */
    public function handle(TelegramService $telegram)
    {
        $telegram->notify("🟢 Auditoriyalar importi boshlandi");
        $this->info('Fetching auditoriums data from HEMIS API...');

        $token = config('services.hemis.token');
        $page = 1;
        $pageSize = 40;
        $totalImported = 0;

        do {
            $response = Http::withoutVerifying()
                ->withToken($token)
                ->get("https://student.ttatf.uz/rest/v1/data/auditorium-list?limit=$pageSize&page=$page");

            if ($response->successful()) {
                $data = $response->json()['data'];
                $items = $data['items'];
                $totalPages = $data['pagination']['pageCount'];
                $this->info("Processing page $page of $totalPages for auditoriums...");

                foreach ($items as $item) {
                    Auditorium::updateOrCreate(
                        ['code' => $item['code']],
                        [
                            'name' => $item['name'],
                            'volume' => $item['volume'] ?? 0,
                            'active' => $item['active'] ?? true,
                            'building_id' => $item['building']['id'] ?? null,
                            'building_name' => $item['building']['name'] ?? null,
                            'auditorium_type_code' => $item['auditoriumType']['code'] ?? null,
                            'auditorium_type_name' => $item['auditoriumType']['name'] ?? null,
                        ]
                    );
                    $totalImported++;

                    $this->info("Imported auditorium: {$item['name']}");
                }

                $page++;
            } else {
                $telegram->notify("❌ Auditoriyalar importida xatolik yuz berdi (API)");
                $this->error('Failed to fetch data from the API for auditoriums.');
                break;
            }
        } while ($page <= $totalPages);

        $telegram->notify("✅ Auditoriyalar importi tugadi. Jami: {$totalImported} ta");
        $this->info('Auditoriums import completed successfully.');
    }
}
