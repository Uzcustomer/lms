<?php

namespace App\Services\Monitoring;

use App\Models\MonitoringIstisno;
use App\Models\MonitoringIstisnoComment;
use App\Models\MonitoringIstisnoEvidence;
use App\Support\WorkdayCalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Istisno ustidagi amallar va holat o'tishlari (TZ 3-bo'lim):
 *   ochiq → javob_berildi → yopildi | asosli_istisno | qayta_ochildi
 * Har bir o'zgarish izoh sifatida tarixga yoziladi.
 */
class IstisnoWorkflow
{
    public function __construct(private IstisnoNotifier $notifier)
    {
    }

    public static function dueFrom(Carbon $from, int $workdays): Carbon
    {
        return WorkdayCalculator::addWorkdays($from, max(1, $workdays))->endOfDay();
    }

    public function assign(MonitoringIstisno $istisno, ?Model $owner, ?Model $actor): void
    {
        $previous = $istisno->owner;

        DB::transaction(function () use ($istisno, $owner, $actor, $previous) {
            $istisno->forceFill([
                'owner_type' => $owner?->getMorphClass(),
                'owner_id' => $owner?->getKey(),
                'assigned_by_type' => $actor?->getMorphClass(),
                'assigned_by_id' => $actor?->getKey(),
                'assigned_at' => $owner ? now() : null,
            ])->save();

            $this->log($istisno, $actor, MonitoringIstisnoComment::KIND_HOLAT,
                "Mas'ul: " . MonitoringAccess::displayName($previous) . ' → ' . MonitoringAccess::displayName($owner));
        });

        if ($owner !== null) {
            $this->notifier->assigned($istisno->fresh(['owner', 'detector']));
        }
    }

    /**
     * Mas'ul javobi (izoh + ixtiyoriy fayllar). $markDone bo'lsa holat «javob berildi» bo'ladi.
     *
     * @param  UploadedFile[]  $files
     */
    public function respond(MonitoringIstisno $istisno, Model $actor, ?string $body, array $files = [], bool $markDone = false): MonitoringIstisnoComment
    {
        if (!$istisno->isOpen()) {
            throw new InvalidArgumentException('Yopilgan istisnoga javob yozib bo\'lmaydi.');
        }

        return DB::transaction(function () use ($istisno, $actor, $body, $files, $markDone) {
            $old = $istisno->status;
            $new = $markDone ? MonitoringIstisno::STATUS_JAVOB_BERILDI : $old;

            $comment = MonitoringIstisnoComment::create([
                'istisno_id' => $istisno->id,
                'author_type' => $actor->getMorphClass(),
                'author_id' => $actor->getKey(),
                'kind' => $istisno->isOwnedBy($actor) ? MonitoringIstisnoComment::KIND_JAVOB : MonitoringIstisnoComment::KIND_IZOH,
                'body' => $body,
                'old_status' => $new !== $old ? $old : null,
                'new_status' => $new !== $old ? $new : null,
            ]);

            foreach ($files as $file) {
                $this->storeEvidence($istisno, $file, $actor, $comment);
            }

            if ($new !== $old) {
                $istisno->forceFill(['status' => $new])->save();
            }

            return $comment;
        });
    }

    public function close(MonitoringIstisno $istisno, Model $actor, ?string $body, bool $justified = false): void
    {
        $new = $justified ? MonitoringIstisno::STATUS_ASOSLI : MonitoringIstisno::STATUS_YOPILDI;
        $this->transition($istisno, $actor, $new, $body, [
            'closed_at' => now(),
            'closed_by_type' => $actor->getMorphClass(),
            'closed_by_id' => $actor->getKey(),
            'closed_by_system' => false,
        ]);
    }

    public function reopen(MonitoringIstisno $istisno, Model $actor, ?string $body): void
    {
        $this->transition($istisno, $actor, MonitoringIstisno::STATUS_QAYTA_OCHILDI, $body, [
            'closed_at' => null,
            'closed_by_type' => null,
            'closed_by_id' => null,
            'closed_by_system' => false,
            'due_at' => self::dueFrom(now(), (int) ($istisno->detector?->due_workdays ?: config('monitoring.default_due_workdays', 3))),
        ]);
    }

    public function storeEvidence(MonitoringIstisno $istisno, UploadedFile $file, ?Model $actor, ?MonitoringIstisnoComment $comment = null): MonitoringIstisnoEvidence
    {
        $dir = trim(config('monitoring.evidence_dir', 'monitoring'), '/') . '/istisno/' . $istisno->id;
        $name = Str::uuid()->toString() . '.' . strtolower($file->getClientOriginalExtension() ?: 'bin');
        $path = $file->storeAs($dir, $name, config('monitoring.evidence_disk', 'local'));

        return MonitoringIstisnoEvidence::create([
            'istisno_id' => $istisno->id,
            'comment_id' => $comment?->id,
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $file->getClientMimeType(),
            'size' => (int) $file->getSize(),
            'uploaded_by_type' => $actor?->getMorphClass(),
            'uploaded_by_id' => $actor?->getKey(),
        ]);
    }

    /** Tizim yozuvi (detektor ochdi/yopdi/qayta ochdi). */
    public function systemNote(MonitoringIstisno $istisno, string $body, ?string $old = null, ?string $new = null): void
    {
        MonitoringIstisnoComment::create([
            'istisno_id' => $istisno->id,
            'kind' => MonitoringIstisnoComment::KIND_TIZIM,
            'body' => $body,
            'old_status' => $old,
            'new_status' => $new,
        ]);
    }

    private function transition(MonitoringIstisno $istisno, Model $actor, string $new, ?string $body, array $extra): void
    {
        DB::transaction(function () use ($istisno, $actor, $new, $body, $extra) {
            $old = $istisno->status;
            $istisno->forceFill(array_merge(['status' => $new], $extra))->save();

            MonitoringIstisnoComment::create([
                'istisno_id' => $istisno->id,
                'author_type' => $actor->getMorphClass(),
                'author_id' => $actor->getKey(),
                'kind' => MonitoringIstisnoComment::KIND_HOLAT,
                'body' => $body,
                'old_status' => $old,
                'new_status' => $new,
            ]);
        });
    }

    private function log(MonitoringIstisno $istisno, ?Model $actor, string $kind, string $body): void
    {
        MonitoringIstisnoComment::create([
            'istisno_id' => $istisno->id,
            'author_type' => $actor?->getMorphClass(),
            'author_id' => $actor?->getKey(),
            'kind' => $actor ? $kind : MonitoringIstisnoComment::KIND_TIZIM,
            'body' => $body,
        ]);
    }
}
