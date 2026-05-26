<?php

namespace App\Jobs;

use App\Models\ComputerAssignment;
use App\Models\ExamSchedule;
use App\Services\ComputerAssignmentService;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Bandlik ko'rsatkichi sahifasidagi "Yetishmaganlarni biriktirish" tugmasi
 * uchun: pendingDetails dan kelgan (schedule_id, yn_type, attempt) qatorlari
 * uchun ComputerAssignmentService ni to'g'ridan-to'g'ri chaqirib komp
 * raqamlarini biriktiradi. Per-student schedule_id (student_hemis_id bor) ga
 * assignSingleStudent, guruh-level ga assign() ishlatiladi.
 *
 * processYnOldiWord pipeline'ini chetlab o'tadi — chunki u guruh-level
 * pipeline va per-student schedule_id'larni ham guruh sifatida ishlatadi,
 * natijada individual qatorlar uchun yozuvlar yaratilmaydi.
 */
class AssignMissingComputersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $timeout = 1800;

    /**
     * @param array<int,array{schedule_id:int,yn_type:string,attempt:int}> $items
     * @param string $token Cache holat kaliti — frontend polling uchun.
     */
    public function __construct(
        public array $items,
        public string $token = '',
    ) {}

    public function handle(): void
    {
        $this->setStatus('running');

        $service = app(ComputerAssignmentService::class);
        $okCount = 0;
        $skipCount = 0;
        $failures = [];

        foreach ($this->items as $it) {
            $sid = (int) ($it['schedule_id'] ?? 0);
            $ynType = strtolower((string) ($it['yn_type'] ?? ''));
            $attempt = (int) ($it['attempt'] ?? 1);

            if ($sid <= 0 || !in_array($ynType, ['oski', 'test'], true)
                || !in_array($attempt, [1, 2, 3], true)) {
                $skipCount++;
                continue;
            }

            $schedule = ExamSchedule::find($sid);
            if (!$schedule) {
                $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => 'schedule not found'];
                continue;
            }

            try {
                if (!empty($schedule->student_hemis_id)) {
                    // Per-student qator — faqat shu talabaga biriktiramiz.
                    $cols = ComputerAssignmentService::attemptFields($ynType, $attempt);
                    $dateVal = $schedule->{$cols['date']};
                    $timeVal = $schedule->{$cols['time']};
                    if (empty($dateVal) || empty($timeVal)) {
                        $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => 'date/time missing'];
                        continue;
                    }
                    $dateStr = $dateVal instanceof Carbon
                        ? $dateVal->format('Y-m-d')
                        : Carbon::parse((string) $dateVal)->format('Y-m-d');
                    $timeStr = substr((string) $timeVal, 0, 5);
                    $startsAt = Carbon::parse($dateStr . ' ' . $timeStr, config('app.timezone'));

                    $res = $service->assignSingleStudent(
                        $schedule,
                        $ynType,
                        $attempt,
                        (string) $schedule->student_hemis_id,
                        $startsAt
                    );
                    if (!empty($res['ok'])) {
                        $okCount++;
                    } else {
                        $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => $res['reason'] ?? 'unknown'];
                    }
                } else {
                    // Guruh-level qator — guruhdagi barcha mos talabalarga.
                    $res = $service->assign($schedule, $ynType, $attempt);
                    if (!empty($res['ok']) && empty($res['skipped'])) {
                        $okCount += (int) ($res['count'] ?? 0);
                    } elseif (!empty($res['skipped'])) {
                        $skipCount++;
                        if (!empty($res['reason'])) {
                            $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => $res['reason']];
                        }
                    } else {
                        $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => $res['reason'] ?? 'unknown'];
                    }
                }
            } catch (\Throwable $e) {
                $failures[] = compact('sid', 'ynType', 'attempt') + ['reason' => $e->getMessage()];
                Log::warning('AssignMissingComputersJob item failed', [
                    'schedule_id' => $sid,
                    'yn_type' => $ynType,
                    'attempt' => $attempt,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('AssignMissingComputersJob: completed', [
            'items' => count($this->items),
            'assigned' => $okCount,
            'skipped' => $skipCount,
            'failed' => count($failures),
        ]);

        $this->setStatus('done', [
            'assigned' => $okCount,
            'skipped' => $skipCount,
            // Foydalanuvchiga ko'rsatish uchun birinchi 20 ta failure
            // (UI'ni ko'p ma'lumotdan bo'g'masdan), qolganlar log'da.
            'failures' => array_slice($failures, 0, 20),
            'failed_total' => count($failures),
        ]);
    }

    public function failed(\Throwable $e): void
    {
        $this->setStatus('failed', ['message' => $e->getMessage()]);
        Log::warning('AssignMissingComputersJob failed: ' . $e->getMessage());
    }

    private function setStatus(string $status, array $extra = []): void
    {
        if ($this->token === '') {
            return;
        }
        cache()->put('assign_missing_computers:' . $this->token, array_merge([
            'status' => $status,
            'requested' => count($this->items),
        ], $extra), 1800);
    }
}
