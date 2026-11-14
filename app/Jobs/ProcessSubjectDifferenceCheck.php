<?php

namespace App\Jobs;

use App\Models\SubjectDifferenceCheck;
use App\Services\SubjectDifference\SubjectDifferenceService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Fanlar farqi: uzoq davom etadigan ishlar (transkriptni AI bilan o'qish, o'quv reja bilan
 * AI moslashtirish) fon navbatida bajariladi — brauzer kutmaydi, nginx'ning 300 s chegarasi
 * ta'sir qilmaydi. Sahifa holatni /status orqali so'rab turadi.
 *
 * action: read | level | rematch
 */
class ProcessSubjectDifferenceCheck implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 900;

    public int $tries = 1;

    public bool $failOnTimeout = true;

    public function __construct(public int $checkId, public string $action, public array $params = [])
    {
    }

    public function handle(SubjectDifferenceService $service): void
    {
        $check = SubjectDifferenceCheck::find($this->checkId);
        if (!$check) {
            return;
        }
        @set_time_limit($this->timeout);

        if ($this->action === 'read') {
            $service->read($check); // o'zi status = read | error qo'yadi

            return;
        }

        try {
            if ($this->action === 'level') {
                $service->addLevel($check, (int) $this->params['curriculum_id'], (int) $this->params['upto'], (bool) ($this->params['use_ai'] ?? true));
            } elseif ($this->action === 'rematch') {
                $service->rematch($check, (string) $this->params['level'], (bool) ($this->params['use_ai'] ?? true));
            }
            $check->refresh();
            $check->error = null;
        } catch (\Throwable $e) {
            $check->refresh();
            $check->error = mb_substr($e->getMessage(), 0, 1000);
        }
        $check->status = !empty($check->levels) ? 'checked' : 'read';
        $check->save();
    }

    public function failed(\Throwable $e): void
    {
        $check = SubjectDifferenceCheck::find($this->checkId);
        if (!$check) {
            return;
        }
        $msg = mb_substr('Fon vazifasi to\'xtadi: ' . $e->getMessage(), 0, 1000);
        if ($this->action === 'read') {
            $check->status = 'error';
        } else {
            $check->status = !empty($check->levels) ? 'checked' : 'read';
        }
        $check->error = $msg;
        $check->save();
    }
}
