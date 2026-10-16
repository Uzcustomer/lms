<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringDetector;
use App\Models\MonitoringIstisno;
use App\Models\MonitoringRun;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Detektorni ishga tushiradi va topilganlarni mavjud istisnolar bilan solishtiradi:
 *  1. yangi fingerprint → yangi istisno (mas'ul aniqlansa biriktiriladi va xabar boradi);
 *  2. ochiq istisno yana topildi → yangilanadi, dublikat yaratilmaydi;
 *  3. ochiq istisno topilmadi (qamrov ichida) → «yopildi (tizim)», tarix saqlanadi;
 *  4. tizim yopgani yana topildi → «qayta ochildi»; administrator yopgani qayta ochilmaydi;
 *  5. ogohlantirish rejimi → mas'ulga biriktirilmaydi, xabar yuborilmaydi;
 *  6. xato → run «xato», hech narsa yopilmaydi, administratorga xabar.
 */
class DetectorRunner
{
    public function __construct(
        private DetectorRegistry $registry,
        private OwnerResolver $owners,
        private IstisnoWorkflow $workflow,
        private IstisnoNotifier $notifier,
    ) {
    }

    public function run(MonitoringDetector $detector, ?Model $triggeredBy = null): MonitoringRun
    {
        $run = MonitoringRun::create([
            'detector_id' => $detector->id,
            'status' => MonitoringRun::STATUS_RUNNING,
            'started_at' => now(),
            'params_snapshot' => $detector->params ?? [],
            'triggered_by_type' => $triggeredBy?->getMorphClass(),
            'triggered_by_id' => $triggeredBy?->getKey(),
        ]);

        try {
            $result = $this->registry->make($detector->code)->run($detector->params ?? [], $detector);
            [$stats, $toNotify] = DB::transaction(fn () => $this->reconcile($detector, $run, $result));

            $run->forceFill(array_merge($stats, [
                'status' => MonitoringRun::STATUS_OK,
                'finished_at' => now(),
            ]))->save();
        } catch (Throwable $e) {
            Log::error("[Monitoring] {$detector->code} xato: " . $e->getMessage(), ['exception' => $e]);
            $run->forceFill([
                'status' => MonitoringRun::STATUS_ERROR,
                'error_message' => mb_substr($e->getMessage(), 0, 2000),
                'finished_at' => now(),
            ])->save();
            $this->notifier->runFailed($run->setRelation('detector', $detector), $e);

            return $run;
        } finally {
            $detector->forceFill(['last_run_at' => now()])->saveQuietly();
        }

        foreach ($toNotify as $istisno) {
            try {
                $this->notifier->assigned($istisno->load(['owner', 'detector']));
            } catch (Throwable $e) {
                Log::warning("[Monitoring] {$detector->code} xabar yuborilmadi: " . $e->getMessage());
            }
        }

        return $run;
    }

    /**
     * @return array{0: array, 1: MonitoringIstisno[]}
     */
    private function reconcile(MonitoringDetector $detector, MonitoringRun $run, DetectorResult $result): array
    {
        $now = now();
        $dueWorkdays = (int) ($detector->due_workdays ?: config('monitoring.default_due_workdays', 3));
        $findings = $result->findings();
        $stats = ['found_count' => count($findings), 'opened_count' => 0, 'updated_count' => 0, 'closed_count' => 0, 'reopened_count' => 0];
        $toNotify = [];

        $existing = collect();
        foreach (array_chunk(array_keys($findings), 500) as $chunk) {
            $existing = $existing->merge(
                MonitoringIstisno::where('detector_id', $detector->id)->whereIn('fingerprint', $chunk)->get()->keyBy('fingerprint')
            );
        }

        foreach ($findings as $fingerprint => $finding) {
            $warningOnly = $detector->warning_mode || $finding->warningOnly;
            /** @var MonitoringIstisno|null $istisno */
            $istisno = $existing->get($fingerprint);

            $common = [
                'last_run_id' => $run->id,
                'last_seen_at' => $now,
                'subject_type' => $finding->subjectType,
                'subject_ref' => $finding->subjectRef,
                'subject_label' => mb_substr($finding->subjectLabel, 0, 255),
                'scope_key' => $finding->scopeKey,
                'faculty_hemis_id' => $finding->facultyHemisId,
                'department_hemis_id' => $finding->departmentHemisId,
                'details' => $finding->details,
            ];

            if ($istisno === null) {
                $owner = $warningOnly ? null : ($finding->owner ?? $this->owners->defaultOwner($detector));
                $istisno = MonitoringIstisno::create(array_merge($common, [
                    'detector_id' => $detector->id,
                    'first_run_id' => $run->id,
                    'fingerprint' => $fingerprint,
                    'found_at' => $now,
                    'due_at' => IstisnoWorkflow::dueFrom($now, $dueWorkdays),
                    'status' => MonitoringIstisno::STATUS_OCHIQ,
                    'warning_only' => $warningOnly,
                    'owner_type' => $owner?->getMorphClass(),
                    'owner_id' => $owner?->getKey(),
                    'assigned_at' => $owner ? $now : null,
                ]));
                $this->workflow->systemNote($istisno, $warningOnly ? 'Detektor topdi (ogohlantirish rejimi).' : 'Detektor topdi.', null, MonitoringIstisno::STATUS_OCHIQ);
                $stats['opened_count']++;
                if ($owner !== null) {
                    $toNotify[] = $istisno;
                }

                continue;
            }

            if ($istisno->isOpen()) {
                $becameReal = $istisno->warning_only && !$warningOnly;
                $extra = ['warning_only' => $warningOnly];
                if ($becameReal && !$istisno->owner_id) {
                    $owner = $finding->owner ?? $this->owners->defaultOwner($detector);
                    $extra += [
                        'owner_type' => $owner?->getMorphClass(),
                        'owner_id' => $owner?->getKey(),
                        'assigned_at' => $owner ? $now : null,
                        'due_at' => IstisnoWorkflow::dueFrom($now, $dueWorkdays),
                    ];
                }
                $istisno->forceFill(array_merge($common, $extra))->save();
                if ($becameReal) {
                    $this->workflow->systemNote($istisno, 'Ogohlantirish haqiqiy istisnoga aylandi.');
                    if ($istisno->owner_id) {
                        $toNotify[] = $istisno;
                    }
                }
                $stats['updated_count']++;

                continue;
            }

            if ($istisno->status === MonitoringIstisno::STATUS_YOPILDI && $istisno->closed_by_system) {
                $old = $istisno->status;
                $istisno->forceFill(array_merge($common, [
                    'status' => MonitoringIstisno::STATUS_QAYTA_OCHILDI,
                    'warning_only' => $warningOnly,
                    'closed_by_system' => false,
                    'closed_at' => null,
                    'due_at' => IstisnoWorkflow::dueFrom($now, $dueWorkdays),
                ]))->save();
                $this->workflow->systemNote($istisno, 'Holat yana aniqlandi — tizim qayta ochdi.', $old, MonitoringIstisno::STATUS_QAYTA_OCHILDI);
                $stats['reopened_count']++;
                if ($istisno->owner_id && !$warningOnly) {
                    $toNotify[] = $istisno;
                }

                continue;
            }

            // Administrator yopgan yoki «asosli istisno» — holati o'zgarmaydi, faqat ko'rilgan vaqti.
            $istisno->forceFill(['last_seen_at' => $now, 'last_run_id' => $run->id])->save();
        }

        // Topilmagan ochiq istisnolar — holat bartaraf bo'lgan: tizim yopadi.
        $stale = MonitoringIstisno::where('detector_id', $detector->id)
            ->open()
            ->when($result->evaluatedScopes !== null, fn ($q) => $q->whereIn('scope_key', $result->evaluatedScopes ?: ['__hech_narsa__']))
            ->whereNotIn('fingerprint', array_keys($findings) ?: ['__hech_narsa__'])
            ->get();

        foreach ($stale as $istisno) {
            $old = $istisno->status;
            $istisno->forceFill([
                'status' => MonitoringIstisno::STATUS_YOPILDI,
                'closed_by_system' => true,
                'closed_at' => $now,
                'closed_by_type' => null,
                'closed_by_id' => null,
                'last_run_id' => $run->id,
            ])->save();
            $this->workflow->systemNote($istisno, 'Holat bartaraf bo\'ldi — tizim yopdi.', $old, MonitoringIstisno::STATUS_YOPILDI);
            $stats['closed_count']++;
        }

        return [$stats, $toNotify];
    }
}
