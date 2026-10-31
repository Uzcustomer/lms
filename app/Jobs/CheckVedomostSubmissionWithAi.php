<?php

namespace App\Jobs;

use App\Models\VedomostSubmission;
use App\Services\VedomostAiChecker;
use App\Services\VedomostAiLimitException;
use App\Services\VedomostAiProgress as Progress;
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

    /**
     * Bo'sh kalit bo'lmasa job 30 s dan keyin qayta navbatga qo'yiladi (release) — urinishlar shu
     * uchun ko'p. Xatolar handle() ichida ushlanadi; timeout esa darhol muvaffaqiyatsiz (qayta urinmaydi).
     */
    public int $tries = 60;

    public bool $failOnTimeout = true;

    private const KEY_WAIT_SECONDS = 30;

    /** @param  bool  $fresh  PDF keshdan emas, AI'ga qaytadan o'qitiladi (qo'lda «Qayta tekshirish»). */
    public function __construct(public int $submissionId, public bool $fresh = false)
    {
        $this->onQueue(self::QUEUE);
    }

    public function handle(VedomostAiChecker $checker, VedomostMergeService $merge): void
    {
        // Sahifa rasmlari (200 dpi, GD) va Gemini so'rovlari standart 128 MB ga sig'maydi —
        // xotira tugasa ishchi halok bo'lib, holat «tahlil qilmoqda»da qotib qolardi.
        @ini_set('memory_limit', '1024M');

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

        // Bitta kalit — bitta ishchi: bo'sh kalit bo'lmasa (boshqa ishchilar band) keyinroq qaytamiz.
        $client = app(\App\Services\VedomostGeminiClient::class);
        if ($client->usableKeys()->isNotEmpty() && !$client->leaseKey($v->id)) {
            Progress::attach($v->pdf_path);
            Progress::log("Barcha kalitlar boshqa vedomostlarni tekshirmoqda — bo'sh kalit kutilmoqda (navbatda)", 'warn');
            $this->release(self::KEY_WAIT_SECONDS);

            return;
        }

        $save(['ai_check_status' => 'running', 'ai_error' => null]);
        Progress::start($v->pdf_path, 'Tekshiruv boshlandi: ' . $v->group_name . ' · ' . VedomostSubmission::formLabel($v->form_type)
            . ($this->fresh ? ' · PDF qaytadan o‘qiladi' : '') . ' · ' . $siblings->count() . ' ta guruh yozuvi');
        if ($kid = \App\Services\VedomostGeminiClient::leasedKeyId()) {
            $k = \App\Models\VedomostAiKey::find($kid);
            Progress::log('Kalit: ' . ($k?->label ?: 'kalit #' . $kid) . ' ' . ($k?->key_hint ?? '') . ' — tekshiruv tugaguncha faqat shu ishchida');
        }

        try {
            $result = $checker->check($v, $this->fresh);
            $c = $result['report']['counts'] ?? [];
            Progress::log('Server xotira sarfi (cho‘qqi): ' . round(memory_get_peak_usage(true) / 1048576) . ' MB — natijaga ta’sir qilmaydi');
            Progress::log('Hisobot tayyor: ' . ($c['ok'] ?? 0) . ' mos, ' . ($c['bad'] ?? 0) . ' XATO, ' . ($c['ocr'] ?? 0)
                . ' vedomost PDFdan tekshiring', 'ok');
            $result['log'] = Progress::lines($v->pdf_path);

            $save([
                'ai_check_status' => 'done',
                'ai_verdict' => $result['verdict'] ?? 'issues',
                'ai_summary' => $result['summary'] ?? null,
                'ai_result' => $result,
                'ai_error' => null,
                'ai_checked_at' => now(),
            ]);
        } catch (VedomostAiLimitException $e) {
            Progress::log('AI limiti tugadi: ' . $e->getMessage(), 'error');
            // Limit tugadi — xato emas: "tekshirilmagan" holatiga qaytaramiz, keyinroq
            // "Tekshirilmaganlarni navbatga qo'shish" orqali qayta navbatga tushadi.
            $save([
                'ai_check_status' => 'none',
                'ai_error' => 'AI limiti tugadi: ' . mb_substr($e->getMessage(), 0, 900),
            ]);
        } catch (\Throwable $e) {
            Progress::log('Xato: ' . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')', 'error');
            Log::warning('[VedomostAiChecker] ' . $e->getMessage());
            $save([
                'ai_check_status' => 'error',
                'ai_error' => mb_substr($e->getMessage(), 0, 1000),
                'ai_checked_at' => now(),
            ]);
        } finally {
            $client->releaseLease();
        }
    }

    /** Navbat ishchisi job'ni muvaffaqiyatsiz deb belgilasa (timeout va h.k.) — holat «xato» bo'ladi. */
    public function failed(?\Throwable $e): void
    {
        $v = VedomostSubmission::find($this->submissionId);
        if (!$v) {
            return;
        }
        $siblings = app(VedomostMergeService::class)->siblingsOf($v);
        if (!$siblings->contains('id', $v->id)) {
            $siblings->push($v);
        }
        app(\App\Services\VedomostGeminiClient::class)->releaseLease();
        Progress::attach($v->pdf_path);
        Progress::log('Tekshiruv tugamadi: ' . mb_substr($e?->getMessage() ?? "noma'lum xato", 0, 300), 'error');
        foreach ($siblings as $sib) {
            if (in_array($sib->ai_check_status, ['queued', 'running'], true)) {
                $sib->update([
                    'ai_check_status' => 'error',
                    'ai_error' => 'AI tekshiruvi tugamadi: ' . mb_substr($e?->getMessage() ?? "noma'lum xato", 0, 900),
                    'ai_checked_at' => now(),
                ]);
            }
        }
    }
}
