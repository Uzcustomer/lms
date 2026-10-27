<?php

namespace App\Http\Controllers\Admin\Concerns;

use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
use App\Services\Timetable\BoardSnapshots;
use Illuminate\Http\Request;

/** Doska versiyalari (nusxalar): ro'yxat, qo'lda nusxa, nusxadan joylashuvni tiklash — faqat admin. */
trait TimetableBoardSnapshotsActions
{
    private function snapshotAdminOnly(Request $request): void
    {
        abort_unless(in_array($this->timetableActiveRole($request), ['admin', 'superadmin'], true), 403, 'Faqat admin uchun.');
        abort_unless(BoardSnapshots::ready(), 422, 'Versiyalar jadvali yo\'q (php artisan migrate).');
    }

    public function boardSnapshotsIndex(Request $request, TimetableBoard $board)
    {
        $this->snapshotAdminOnly($request);

        return response()->json(['snapshots' => TimetableBoardSnapshot::where('board_id', $board->id)->orderByDesc('id')->limit(200)
            ->get(['id', 'reason', 'created_by_name', 'cards_count', 'placed_count', 'overrides_count', 'created_at'])
            ->map(fn ($s) => $s->only(['id', 'reason', 'created_by_name', 'cards_count', 'placed_count', 'overrides_count'])
                + ['created_at' => $s->created_at?->format('d.m.Y H:i')])]);
    }

    public function boardSnapshotStore(Request $request, TimetableBoard $board)
    {
        $this->snapshotAdminOnly($request);
        $actor = $this->timetableActor($request);
        $s = app(BoardSnapshots::class)->take($board, 'manual', $actor?->full_name ?? $actor?->name);
        abort_unless($s, 500, 'Nusxa olinmadi.');

        return response()->json(['ok' => true, 'id' => $s->id]);
    }

    public function boardSnapshotRestore(Request $request, TimetableBoard $board, TimetableBoardSnapshot $snapshot)
    {
        $this->snapshotAdminOnly($request);
        abort_unless((int) $snapshot->board_id === (int) $board->id, 404);
        $data = $request->validate([
            'dry_run' => 'nullable|boolean',
            'faculty_names' => 'nullable|array', 'faculty_names.*' => 'nullable|string|max:255',
            'specialty_names' => 'nullable|array', 'specialty_names.*' => 'string|max:255',
            'courses' => 'nullable|array', 'courses.*' => 'integer|min:1|max:7',
        ]);
        @ini_set('memory_limit', '1024M');
        @set_time_limit(300);

        return response()->json(app(BoardSnapshots::class)->restorePlacement($board, $snapshot->data(), $request->boolean('dry_run', true), $data, true,
            $this->restoreConflictChecker()));
    }
}
