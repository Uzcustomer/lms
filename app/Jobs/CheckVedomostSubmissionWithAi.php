<?php

namespace App\Jobs;

use App\Models\VedomostSubmission;
use App\Services\VedomostAiChecker;
use App\Services\VedomostAiLimitException;
use App\Services\VedomostMergeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Vedomost AI tekshiruvi. Alohida "vedomost-ai" navbatida ishlaydi (supervisord
 * da o'z ishchisi bor) — uzoq Gemini so'rovlari boshqa job'larni to'sib qo'ymasin.
 */
class CheckVedomostSubmissionWithAi implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public const QUEUE = 'vedomost-ai';

    public int $timeout = 900;
    public int $tries = 1;

    /** @param  bool  $fresh  PDF keshdan emas, AI'ga qaytadan o'qitiladi (qo'lda «Qayta tekshirish»). */
    public function __construct(public int $submissionId, public bool $fresh = false)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(VedomostAiChecker $checker, VedomostMergeService $merge): void
    {
        $v = VedomostSubmission::find($this->submissionId);
        if (!$v) {
            return;
        }

        // Bitta PDF barcha guruhcha yozuvlariga tegishli — natija hammasiga yoziladi.
        $siblings = $merge->siblingsOf($v);
        if (!$siblings->contains('id', $v->id)) {
            $siblings->push($v);
        }
        $save = function (array $data) use ($siblings) {
            foreach ($siblings as $sib) {
                $sib->update($data);
            }
        };

        $save(['ai_check_status' => 'running', 'ai_error' => null]);

        try {
            $result = $checker->check($v, $this->fresh);

            $save([
                'ai_check_status' => 'done',
                'ai_verdict' => $result['verdict'] ?? 'issues',
                'ai_summary' => $result['summary'] ?? null,
                'ai_result' => $result,
                'ai_error' => null,
                'ai_checked_at' => now(),
            ]);
        } catch (VedomostAiLimitException $e) {
            // Limit tugadi — xato emas: "tekshirilmagan" holatiga qaytaramiz, keyinroq
            // "Tekshirilmaganlarni navbatga qo'shish" orqali qayta navbatga tushadi.
            $save([
                'ai_check_status' => 'none',
                'ai_error' => 'AI limiti tugadi: ' . mb_substr($e->getMessage(), 0, 900),
            ]);
        } catch (\Throwable $e) {
            Log::warning('[VedomostAiChecker] ' . $e->getMessage());
            $save([
                'ai_check_status' => 'error',
                'ai_error' => mb_substr($e->getMessage(), 0, 1000),
                'ai_checked_at' => now(),
            ]);
        }
    }
}
