<?php

namespace App\Http\Controllers\Admin\Monitoring;

use App\Exports\MonitoringIstisnolarExport;
use App\Http\Controllers\Controller;
use App\Models\MonitoringDetector;
use App\Models\MonitoringIstisno;
use App\Models\MonitoringIstisnoEvidence;
use App\Models\Teacher;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\Monitoring\IstisnoWorkflow;
use App\Services\Monitoring\MonitoringAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Istisnolar ro'yxati, kartochka, mas'ul kabineti (TZ 6.2–6.4).
 */
class MonitoringIstisnoController extends Controller
{
    public function __construct(private IstisnoWorkflow $workflow)
    {
    }

    public function index(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canViewAll($actor), 403, 'Monitoring istisnolarini ko\'rish huquqi yo\'q.');

        $query = $this->filtered($request, MonitoringIstisno::query());

        if ($request->input('export') === 'excel') {
            ActivityLogService::log('export', 'monitoring_istisno', 'Monitoring istisnolari Excel eksporti', null, null, $request->except('export'));

            return Excel::download(
                new MonitoringIstisnolarExport($query->with(['detector', 'owner'])->orderBy('id')->limit(20000)->get()),
                'monitoring_istisnolar_' . now()->format('Y_m_d_H_i') . '.xlsx'
            );
        }

        $istisnolar = $query->with(['detector', 'owner'])->orderByRaw('due_at IS NULL')->orderBy('due_at')->paginate(50)->withQueryString();

        return view('admin.monitoring.istisnolar.index', [
            'istisnolar' => $istisnolar,
            'detectors' => MonitoringDetector::orderBy('code')->get(['id', 'code', 'title', 'group_name']),
            'groups' => MonitoringDetector::query()->distinct()->orderBy('group_name')->pluck('group_name'),
            'faculties' => DB::table('departments')->where('structure_type_code', '11')->orderBy('name')->get(['department_hemis_id', 'name']),
            'canAssign' => MonitoringAccess::canAssign($actor),
            'ownerLabel' => $this->ownerLabel($request->input('owner')),
        ]);
    }

    public function mine(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_if($actor === null, 403);

        $istisnolar = MonitoringIstisno::query()
            ->ownedBy($actor)
            ->when($request->input('holat') !== 'hammasi', fn ($q) => $q->open())
            ->with('detector')
            ->orderByRaw('due_at IS NULL')->orderBy('due_at')
            ->paginate(50)->withQueryString();

        return view('admin.monitoring.mine', compact('istisnolar'));
    }

    public function show(MonitoringIstisno $istisno)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canView($actor, $istisno), 403, 'Bu istisno sizga biriktirilmagan.');

        ActivityLogService::log('view', 'monitoring_istisno', "Istisno ko'rildi: {$istisno->subject_label}", $istisno);

        $istisno->load(['detector', 'owner', 'comments.author', 'evidences.uploadedBy']);

        return view('admin.monitoring.istisnolar.show', [
            'istisno' => $istisno,
            'canRespond' => MonitoringAccess::canRespond($actor, $istisno),
            'isOwner' => $istisno->isOwnedBy($actor),
            'canAssign' => MonitoringAccess::canAssign($actor),
            'canClose' => MonitoringAccess::canClose($actor),
        ]);
    }

    public function respond(Request $request, MonitoringIstisno $istisno)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canRespond($actor, $istisno), 403, 'Javob yozish huquqi yo\'q yoki istisno yopilgan.');

        $maxKb = (int) config('monitoring.evidence_max_kb', 20480);
        $data = $request->validate([
            'body' => 'nullable|string|max:5000|required_without:files',
            'files' => 'nullable|array|max:10',
            'files.*' => "file|max:{$maxKb}|mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx",
            'mark_done' => 'nullable|boolean',
        ], [
            'body.required_without' => 'Javob matni yoki kamida bitta fayl kerak.',
        ]);

        $markDone = $istisno->isOwnedBy($actor) && $request->boolean('mark_done');
        $this->workflow->respond($istisno, $actor, $data['body'] ?? null, $request->file('files', []), $markDone);

        return redirect()->route('admin.monitoring.istisnolar.show', $istisno)
            ->with('success', $markDone ? 'Javob yuborildi va «bajarildi» deb belgilandi.' : 'Izoh qo\'shildi.');
    }

    public function assign(Request $request, MonitoringIstisno $istisno)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canAssign($actor), 403, 'Mas\'ul biriktirish huquqi yo\'q.');

        $request->validate(['owner' => 'nullable|string|max:40']);
        $owner = $this->resolveOwner($request->input('owner'));
        if ($request->filled('owner') && $owner === null) {
            return back()->with('error', 'Tanlangan xodim topilmadi.');
        }

        $this->workflow->assign($istisno, $owner, $actor);

        return back()->with('success', $owner ? 'Mas\'ul biriktirildi va xabardor qilindi.' : 'Mas\'ul olib tashlandi.');
    }

    public function bulkAssign(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canAssign($actor), 403, 'Mas\'ul biriktirish huquqi yo\'q.');

        $request->validate([
            'ids' => 'required|array|min:1|max:500',
            'ids.*' => 'integer',
            'owner' => 'required|string|max:40',
        ], ['ids.required' => 'Kamida bitta istisnoni belgilang.']);

        $owner = $this->resolveOwner($request->input('owner'));
        if ($owner === null) {
            return back()->with('error', 'Tanlangan xodim topilmadi.');
        }

        $count = 0;
        foreach (MonitoringIstisno::whereIn('id', $request->input('ids'))->open()->get() as $istisno) {
            $this->workflow->assign($istisno, $owner, $actor);
            $count++;
        }

        return back()->with('success', "{$count} ta istisnoga mas'ul biriktirildi.");
    }

    public function transition(Request $request, MonitoringIstisno $istisno)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canClose($actor), 403, 'Holatni faqat monitoring administratori o\'zgartiradi.');

        $data = $request->validate([
            'action' => 'required|in:close,justify,reopen',
            'body' => 'nullable|string|max:5000|required_if:action,justify|required_if:action,reopen',
        ], [
            'body.required_if' => 'Asosli istisno yoki qayta ochish uchun izoh yozing.',
        ]);

        if ($data['action'] === 'reopen') {
            if ($istisno->isOpen() && $istisno->status !== MonitoringIstisno::STATUS_JAVOB_BERILDI) {
                return back()->with('error', 'Istisno allaqachon ochiq.');
            }
            $this->workflow->reopen($istisno, $actor, $data['body']);
        } else {
            if (!$istisno->isOpen()) {
                return back()->with('error', 'Istisno allaqachon yopilgan.');
            }
            $this->workflow->close($istisno, $actor, $data['body'] ?? null, $data['action'] === 'justify');
        }

        return back()->with('success', 'Holat o\'zgartirildi.');
    }

    public function evidence(MonitoringIstisno $istisno, MonitoringIstisnoEvidence $evidence)
    {
        abort_unless($evidence->istisno_id === $istisno->id, 404);
        abort_unless(MonitoringAccess::canView(MonitoringAccess::actor(), $istisno), 403);

        $disk = Storage::disk(config('monitoring.evidence_disk', 'local'));
        abort_unless($disk->exists($evidence->path), 404, 'Fayl topilmadi.');

        ActivityLogService::log('view', 'monitoring_istisno', "Dalil fayli ochildi: {$evidence->original_name}", $istisno);

        return $disk->download($evidence->path, $evidence->original_name);
    }

    /** Mas'ul tanlash uchun xodim qidiruvi (select2 AJAX). */
    public function staffSearch(Request $request)
    {
        abort_unless(MonitoringAccess::canAssign(MonitoringAccess::actor()), 403);

        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json(['results' => []]);
        }

        $teachers = Teacher::where('full_name', 'like', "%{$q}%")->with('roles:id,name')->limit(15)->get(['id', 'full_name']);
        $users = User::where('name', 'like', "%{$q}%")->with('roles:id,name')->limit(5)->get(['id', 'name']);

        $results = $teachers->map(fn ($t) => [
            'id' => 'teacher:' . $t->id,
            'text' => $t->full_name . $this->rolesSuffix($t),
        ])->concat($users->map(fn ($u) => [
            'id' => 'user:' . $u->id,
            'text' => $u->name . $this->rolesSuffix($u) . ' (admin hisobi)',
        ]))->values();

        return response()->json(['results' => $results]);
    }

    private function filtered(Request $request, Builder $query): Builder
    {
        $status = $request->input('holat', 'ochiq');

        return $query
            ->when($status === 'ochiq', fn ($q) => $q->open())
            ->when($status === 'yuborilgan', fn ($q) => $q->open()->where('warning_only', false))
            ->when($status === 'muddati_otgan', fn ($q) => $q->overdue()->where('warning_only', false))
            ->when($status === 'ogohlantirish', fn ($q) => $q->open()->where('warning_only', true))
            ->when($status !== MonitoringIstisno::STATUS_OCHIQ && array_key_exists($status, MonitoringIstisno::STATUSES), fn ($q) => $q->where('status', $status))
            ->when($request->filled('detector_id'), fn ($q) => $q->where('detector_id', $request->integer('detector_id')))
            ->when($request->filled('guruh'), fn ($q) => $q->whereHas('detector', fn ($d) => $d->where('group_name', $request->input('guruh'))))
            ->when($request->filled('fakultet'), fn ($q) => $q->where('faculty_hemis_id', $request->integer('fakultet')))
            ->when($request->filled('owner'), function ($q) use ($request) {
                if ($request->input('owner') === 'yoq') {
                    return $q->whereNull('owner_id');
                }
                $owner = $this->resolveOwner($request->input('owner'));

                return $owner ? $q->ownedBy($owner) : $q->whereRaw('1 = 0');
            })
            ->when($request->filled('muddat_dan'), fn ($q) => $q->whereDate('due_at', '>=', $request->input('muddat_dan')))
            ->when($request->filled('muddat_gacha'), fn ($q) => $q->whereDate('due_at', '<=', $request->input('muddat_gacha')))
            ->when($request->filled('q'), fn ($q) => $q->where('subject_label', 'like', '%' . $request->input('q') . '%'));
    }

    private function resolveOwner(?string $key): ?Model
    {
        return MonitoringAccess::resolveOwnerKey($key);
    }

    private function ownerLabel(?string $key): ?string
    {
        if ($key === 'yoq') {
            return 'Biriktirilmagan';
        }
        $owner = $this->resolveOwner($key);

        return $owner ? MonitoringAccess::displayName($owner) : null;
    }

    private function rolesSuffix(Model $model): string
    {
        $roles = $model->roles->pluck('name')->map(fn ($r) => \App\Enums\ProjectRole::tryFrom($r)?->label() ?? $r);

        return $roles->isNotEmpty() ? ' — ' . $roles->implode(', ') : '';
    }
}
