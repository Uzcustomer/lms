<?php

namespace App\Jobs;

use App\Models\ExamSchedule;
use App\Services\VedomostExamDateNotice;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * YN kunini belgilashda OSKI/test sanasi saqlangandan keyin —
 * vedomost muddati haqida xabar (yopilish shakliga qarab).
 */
class SendVedomostExamDateNoticeJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function __construct(public int $examScheduleId, public string $yn)
    {
    }

    public function handle(VedomostExamDateNotice $notice): void
    {
        $exam = ExamSchedule::find($this->examScheduleId);
        if (!$exam) {
            return;
        }

        $result = $notice->handle($exam, $this->yn);

        Log::info('[VedomostExamDateNotice] exam#' . $this->examScheduleId . ' ' . $this->yn . ': ' . $result['reason']);
    }
}
