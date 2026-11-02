<?php

namespace App\Jobs;

use App\Models\VedomostSubmission;
use App\Services\VedomostJamlanmaExcel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Tasdiqlangan vedomost(lar)ning jamlanma qatorlarini fonda oldindan hisoblaydi —
 * «Jamlanma» bosilganda tayyor qatorlardan bir zumda yig'iladi.
 */
class BuildVedomostJamlanmaRows implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 2;

    /** @param  int[]  $submissionIds */
    public function __construct(public array $submissionIds)
    {
    }

    public function handle(VedomostJamlanmaExcel $excel): void
    {
        @ini_set('memory_limit', '1024M');
        $subs = VedomostSubmission::whereIn('id', $this->submissionIds)
            ->where('status', VedomostSubmission::STATUS_APPROVED)
            ->get();
        foreach ($subs as $sub) {
            try {
                $excel->store($sub);
            } catch (\Throwable $e) {
                // Bittasi xato bersa qolganlari to'xtamasin — jamlanma uni bosilganda o'zi hisoblaydi.
                Log::warning("[Jamlanma] vedomost #{$sub->id} qatorlari hisoblanmadi: " . $e->getMessage());
            }
        }
    }
}
