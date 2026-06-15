<?php

namespace App\Services;

use App\Models\VedomostRejectionRead;
use App\Models\VedomostSubmission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * "Vedomost rad etilish bildirgilari" inboxi.
 *
 * To'g'ridan-to'g'ri vedomost_submissions dan rad etilgan (rejected) yozuvlarni
 * o'qib, o'zak guruh×fan bo'yicha jamlaydi va har bir foydalanuvchi uchun
 * o'qilgan/o'qilmagan holatini hisoblaydi (Gmail uslubidagi inbox). Bildirgilar
 * Telegram/notify toggle'iga BOG'LIQ EMAS — har doim haqiqiy holatni aks ettiradi.
 */
class VedomostRejectionInbox
{
    public function __construct(private VedomostMergeService $merge)
    {
    }

    /**
     * Rad etilgan vedomostlarning jamlangan (o'zak guruh×fan) ro'yxati —
     * eng yangi rad etilgani birinchi.
     *
     * @return Collection<int, object>
     */
    public function aggregatedRejections(): Collection
    {
        $rows = DB::table('vedomost_submissions as vs')
            ->join('groups as g', function ($join) {
                $join->on('g.group_hemis_id', '=', 'vs.group_hemis_id')
                    ->where('g.active', true);
            })
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'vs.curriculum_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->where('vs.status', VedomostSubmission::STATUS_REJECTED)
            ->select('vs.*', 'f.name as faculty_name')
            ->get();

        return $this->merge->aggregate($rows)
            ->sortByDesc(fn($r) => (string) ($r->reviewed_at ?? ''))
            ->values();
    }

    /**
     * Jamlangan qatorlarga foydalanuvchi uchun o'qilgan/o'qilmagan holatini qo'shadi.
     */
    public function decorate(Collection $rows, Model $viewer): Collection
    {
        [$type, $id] = $this->viewerKey($viewer);

        // Vakil id lar bo'yicha o'qilgan vaqtlar.
        $repIds = $rows->pluck('id')->all();
        $reads = empty($repIds)
            ? collect()
            : VedomostRejectionRead::where('viewer_type', $type)
                ->where('viewer_id', $id)
                ->whereIn('vedomost_submission_id', $repIds)
                ->pluck('read_at', 'vedomost_submission_id');

        foreach ($rows as $row) {
            $readAt = ($reads[$row->id] ?? null);
            $readAt = $readAt ? \Carbon\Carbon::parse($readAt) : null;
            $reviewedAt = $row->reviewed_at ? \Carbon\Carbon::parse($row->reviewed_at) : null;
            // O'qilgan deb hisoblanadi: read_at mavjud VA rad etilgan vaqtdan keyin.
            $row->is_read = $readAt !== null
                && ($reviewedAt === null || $readAt->gte($reviewedAt));
            $row->read_at = $readAt;
        }

        return $rows;
    }

    /**
     * Foydalanuvchi uchun o'qilmagan rad etilish bildirgilari soni (badge uchun, keshlanadi).
     */
    public function unreadCount(Model $viewer): int
    {
        [$type, $id] = $this->viewerKey($viewer);

        return (int) Cache::remember(
            "vedomost_rej_unread:{$type}:{$id}",
            now()->addSeconds(60),
            fn() => $this->decorate($this->aggregatedRejections(), $viewer)
                ->filter(fn($r) => !$r->is_read)
                ->count()
        );
    }

    /**
     * Bitta vedomostni (va uning barcha guruhcha yozuvlarini) o'qilgan deb belgilaydi.
     */
    public function markRead(VedomostSubmission $v, Model $viewer): void
    {
        [$type, $id] = $this->viewerKey($viewer);
        $now = now();

        foreach ($this->merge->siblingsOf($v) as $sib) {
            VedomostRejectionRead::updateOrCreate(
                [
                    'vedomost_submission_id' => $sib->id,
                    'viewer_type' => $type,
                    'viewer_id' => $id,
                ],
                ['read_at' => $now]
            );
        }

        $this->forgetCache($viewer);
    }

    /**
     * Barcha joriy rad etilgan vedomostlarni o'qilgan deb belgilaydi.
     */
    public function markAllRead(Model $viewer): void
    {
        [$type, $id] = $this->viewerKey($viewer);
        $now = now();

        $ids = DB::table('vedomost_submissions as vs')
            ->join('groups as g', function ($join) {
                $join->on('g.group_hemis_id', '=', 'vs.group_hemis_id')
                    ->where('g.active', true);
            })
            ->where('vs.status', VedomostSubmission::STATUS_REJECTED)
            ->pluck('vs.id');

        foreach ($ids as $sid) {
            VedomostRejectionRead::updateOrCreate(
                [
                    'vedomost_submission_id' => $sid,
                    'viewer_type' => $type,
                    'viewer_id' => $id,
                ],
                ['read_at' => $now]
            );
        }

        $this->forgetCache($viewer);
    }

    public function forgetCache(Model $viewer): void
    {
        [$type, $id] = $this->viewerKey($viewer);
        Cache::forget("vedomost_rej_unread:{$type}:{$id}");
    }

    /**
     * @return array{0:string,1:int}  [viewer_type, viewer_id]
     */
    private function viewerKey(Model $viewer): array
    {
        return [$viewer->getMorphClass(), (int) $viewer->getKey()];
    }
}
