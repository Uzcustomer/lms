<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\AcademicScheduleController;
use Carbon\Carbon;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Request;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * "Kompyuter raqamlarini taqsimlash" tugmasi bosilganda dispatch qilinadi.
 * Sana oralig'idagi har bir kun uchun og'ir YN pipeline + komp taqsimotini
 * fonda bajaradi — HTTP so'rov 504 timeout bermasligi uchun.
 */
class AssignComputersForRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 daqiqa — ko'p guruh bo'lsa ham yetadi

    public function __construct(
        public string $dateFrom,
        public string $dateTo,
    ) {}

    public function handle(): void
    {
        try {
            $from = Carbon::parse($this->dateFrom)->startOfDay();
            $to = Carbon::parse($this->dateTo)->startOfDay();
        } catch (\Throwable $e) {
            Log::warning('AssignComputersForRangeJob: sana xato — ' . $e->getMessage());
            return;
        }
        if ($to->lt($from)) {
            $to = $from->copy();
        }

        $controller = app(AcademicScheduleController::class);
        $totalAssigned = 0;
        $daysProcessed = 0;

        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            $dateStr = $d->format('Y-m-d');
            $items = $controller->collectScheduledItemsForDate($dateStr);
            if (empty($items)) {
                continue;
            }
            $daysProcessed++;
            try {
                $request = Request::create('', 'POST', [
                    'items' => $items,
                    'exam_date' => $dateStr,
                    'assign_computers' => true,
                ]);
                $resp = $controller->processYnOldiWord($request);
                $payload = json_decode($resp->getContent(), true);
                if (is_array($payload) && !empty($payload['ok'])) {
                    $totalAssigned += (int) ($payload['assigned'] ?? 0);
                }
            } catch (\Throwable $e) {
                Log::warning('AssignComputersForRangeJob: ' . $dateStr . ' — ' . $e->getMessage());
            }
        }

        Log::info('AssignComputersForRangeJob: completed', [
            'date_from' => $this->dateFrom,
            'date_to' => $this->dateTo,
            'days' => $daysProcessed,
            'assigned' => $totalAssigned,
        ]);
    }
}
