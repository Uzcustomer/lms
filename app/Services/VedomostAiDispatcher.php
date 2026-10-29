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
        private VedomostAiSettings $settings,
        private VedomostSubmissionNotifier $notifier
    ) {
    }

    public function queue(VedomostSubmission $v): void
    {
        $siblings = $this->merge->siblingsOf($v);
        if (!$siblings->contains('id', $v->id)) {
            $siblings->push($v);
        }
        // AI tekshiruv boshlanishi = vedomost tekshirishga olindi ("Qabul qilindi" → "Tekshirilmoqda"),
        // "Tekshirishga olish" tugmasi bilan bir xil: tarixga yoziladi va xabar boradi.
        $toReview = $v->status === VedomostSubmission::STATUS_RECEIVED;
        foreach ($siblings as $sib) {
            $sib->update(array_merge(
                ['ai_check_status' => 'queued', 'ai_error' => null],
                $toReview && $sib->status === VedomostSubmission::STATUS_RECEIVED ? ['status' => VedomostSubmission::STATUS_REVIEWING] : []
            ));
        }
        if ($toReview) {
            $v->refresh();
            \App\Models\VedomostSubmissionLog::create([
                'vedomost_submission_id' => $v->id,
                'action' => 'review',
                'from_status' => VedomostSubmission::STATUS_RECEIVED,
                'to_status' => VedomostSubmission::STATUS_REVIEWING,
                'note' => 'AI tekshiruv boshlandi',
                'user_id' => auth()->id(),
                'user_name' => ($u = auth()->user()) ? ($u->name ?? $u->full_name ?? $u->short_name ?? null) : 'Tizim',
            ]);
            try {
                $this->notifier->notifyStatusChange($v);
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('[VedomostAiDispatcher] notify: ' . $e->getMessage());
            }
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

    /**
     * Tasdiqlashdan oldingi AI shartlari (sozlamada yoqilgan bo'lsa):
     *  - AI tekshiruv navbatda/ishlamoqda → tasdiqlab bo'lmaydi;
     *  - tekshirilmagan, xato bilan tugagan yoki nomuvofiqlik topilgan → sabab yozish shart.
     *
     * @return array{blocked: bool, needs_reason: bool, message: ?string}
     */
    public function approvalGate(VedomostSubmission $v): array
    {
        $none = ['blocked' => false, 'needs_reason' => false, 'message' => null];
        if (!$this->settings->get('require_ai_before_approve') || !VedomostAiChecker::isConfigured() || !$v->pdf_path) {
            return $none;
        }

        return match (true) {
            in_array($v->ai_check_status, ['queued', 'running'], true) => [
                'blocked' => true, 'needs_reason' => false,
                'message' => 'AI tekshiruvi tugashini kuting — natija chiqqach tasdiqlash mumkin bo\'ladi.',
            ],
            $v->ai_check_status === 'done' && $v->ai_verdict === 'ok' => $none,
            $v->ai_check_status === 'done' => [
                'blocked' => false, 'needs_reason' => true,
                'message' => 'AI nomuvofiqlik topgan. Baribir tasdiqlash uchun sababini yozing (jarayon tarixiga yoziladi).',
            ],
            default => [
                'blocked' => false, 'needs_reason' => true,
                'message' => 'Vedomost AI bilan tekshirilmagan. AI tekshiruvsiz tasdiqlash uchun sababini yozing.',
            ],
        };
    }
}
