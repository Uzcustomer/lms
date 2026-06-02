<?php

namespace App\Jobs;

use App\Models\VedomostSubmission;
use App\Services\VedomostAiChecker;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class CheckVedomostSubmissionWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 300;
    public int $tries = 1;

    public function __construct(public int $submissionId)
    {
    }

    public function handle(VedomostAiChecker $checker): void
    {
        $v = VedomostSubmission::find($this->submissionId);
        if (!$v) {
            return;
        }

        $v->update(['ai_check_status' => 'running', 'ai_error' => null]);

        try {
            $result = $checker->check($v);

            $v->update([
                'ai_check_status' => 'done',
                'ai_verdict' => $result['verdict'] ?? 'issues',
                'ai_summary' => $result['summary'] ?? null,
                'ai_result' => $result,
                'ai_error' => null,
                'ai_checked_at' => now(),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[VedomostAiChecker] ' . $e->getMessage());
            $v->update([
                'ai_check_status' => 'error',
                'ai_error' => mb_substr($e->getMessage(), 0, 1000),
                'ai_checked_at' => now(),
            ]);
        }
    }
}
