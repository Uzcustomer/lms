<?php

namespace App\Jobs;

use App\Http\Controllers\Admin\AcademicScheduleController;
use App\Models\ExamSchedule;
use App\Services\ComputerAssignmentService;
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
 * Test markazi jadvalida belgilangan guruhlar uchun og'ir YN pipeline +
 * komp taqsimotini fonda bajaradi — HTTP so'rov 504 timeout bermasligi uchun.
 */
class AssignComputersForRangeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800; // 30 daqiqa — ko'p guruh bo'lsa ham yetadi

    /**
     * @param array<int,array{schedule_id:int,yn_type:string,attempt:int}> $items
     *        Belgilangan qatorlar — har biri bitta (schedule, yn_type, attempt).
     */
    public function __construct(
        public array $items,
    ) {}

    public function handle(): void
    {
        // Har bir belgilangan (schedule, yn_type, attempt) ni o'z sana+vaqtiga
        // ko'ra processYnOldiWord kutadigan item'ga aylantirib, sana bo'yicha
        // guruhlaymiz — processYnOldiWord bitta sana ustida ishlaydi.
        $byDate = [];
        foreach ($this->items as $sel) {
            $scheduleId = (int) ($sel['schedule_id'] ?? 0);
            $ynType = strtolower((string) ($sel['yn_type'] ?? ''));
            $attempt = (int) ($sel['attempt'] ?? 1);
            if ($scheduleId <= 0 || !in_array($ynType, ['oski', 'test'], true)) {
                continue;
            }

            $schedule = ExamSchedule::find($scheduleId);
            if (!$schedule || empty($schedule->group_hemis_id)
                || empty($schedule->subject_id) || empty($schedule->semester_code)) {
                continue;
            }

            $cols = ComputerAssignmentService::attemptFields($ynType, $attempt);
            $dateVal = $schedule->{$cols['date']};
            $timeVal = $schedule->{$cols['time']};
            if (empty($dateVal) || empty($timeVal)) {
                continue;
            }
            if ($cols['na'] !== null && !empty($schedule->{$cols['na']})) {
                continue;
            }

            try {
                $dateStr = $dateVal instanceof Carbon
                    ? $dateVal->format('Y-m-d')
                    : Carbon::parse((string) $dateVal)->format('Y-m-d');
            } catch (\Throwable $e) {
                continue;
            }
            $timeStr = substr((string) $timeVal, 0, 5);
            if (!preg_match('/^\d{2}:\d{2}$/', $timeStr)) {
                continue;
            }

            $byDate[$dateStr][] = [
                'group_hemis_id' => (string) $schedule->group_hemis_id,
                'subject_id'     => (string) $schedule->subject_id,
                'semester_code'  => (string) $schedule->semester_code,
                'attempt'        => $attempt,
                'yn_type'        => $ynType,
                'schedule_id'    => $scheduleId,
                'exam_time'      => $timeStr,
            ];
        }

        if (empty($byDate)) {
            Log::warning('AssignComputersForRangeJob: yaroqli guruh topilmadi');
            return;
        }

        $controller = app(AcademicScheduleController::class);
        $totalAssigned = 0;

        foreach ($byDate as $dateStr => $items) {
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
            'items' => count($this->items),
            'dates' => count($byDate),
            'assigned' => $totalAssigned,
        ]);
    }
}
