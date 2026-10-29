<?php

namespace App\Services;

use App\Jobs\CheckVedomostSubmissionWithAi;
use App\Models\VedomostSubmission;

/**
 * Vedomostni AI tekshiruv navbatiga qo'yadi.
 *
 * Bitta o'zak guruh × o'zak fan = bitta PDF (guruhcha yozuvlari birgalikda),
 * shuning uchun holat barcha siblinglarga yoziladi, job esa bitta yuboriladi.
 */
class VedomostAiDispatcher
{
    public function __construct(
        private VedomostMergeService $merge,
        private VedomostAiSettings $settings
    ) {
    }

    public function queue(VedomostSubmission $v): void
    {
        $siblings = $this->merge->siblingsOf($v);
        if (!$siblings->contains('id', $v->id)) {
            $siblings->push($v);
        }
        foreach ($siblings as $sib) {
            $sib->update(['ai_check_status' => 'queued', 'ai_error' => null]);
        }

        CheckVedomostSubmissionWithAi::dispatch($v->id);
    }

    /**
     * Yuklangandan keyin avtomatik tekshiruv (sozlamada yoqilgan bo'lsa).
     */
    public function queueIfAuto(VedomostSubmission $v): bool
    {
        if (!$this->settings->get('auto_check') || !$v->pdf_path || !VedomostAiChecker::isConfigured()) {
            return false;
        }
        $this->queue($v);

        return true;
    }

    /**
     * PDF yuklangan, tasdiqlanmagan va hali AI tekshirmagan vedomostlarni navbatga qo'yadi.
     *
     * @return int navbatga qo'yilganlar soni
     */
    public function queuePending(?int $max = null): int
    {
        $ids = VedomostSubmission::query()
            ->whereIn('status', [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING])
            ->whereNotNull('pdf_path')
            ->where('ai_check_status', 'none')
            ->selectRaw('MIN(id) as id')
            ->groupBy('pdf_path')
            ->orderBy('id')
            ->when($max !== null, fn ($q) => $q->limit($max))
            ->pluck('id');

        $count = 0;
        foreach (VedomostSubmission::whereIn('id', $ids)->get() as $v) {
            $this->queue($v);
            $count++;
        }

        return $count;
    }
}
