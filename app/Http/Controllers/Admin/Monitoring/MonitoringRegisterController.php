<?php

namespace App\Http\Controllers\Admin\Monitoring;

use App\Http\Controllers\Controller;
use App\Models\MonitoringAdmissionRecommendation;
use App\Models\MonitoringDetector;
use App\Models\MonitoringNoticeLog;
use App\Models\MonitoringOrder;
use App\Models\MonitoringTransferChecklist;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use App\Services\Monitoring\IstisnoNotifier;
use App\Services\Monitoring\MonitoringAccess;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Pilot manba reyestrlari:
 *  - buyruqlar reyestri + M3 xabarnoma jurnali (E-1);
 *  - kontrakt bo'yicha tavsiya etilganlar (A-5);
 *  - M8 ko'chirib kelganlar yig'majildi cheklisti (E-7).
 */
class MonitoringRegisterController extends Controller
{
    public function __construct(private IstisnoNotifier $notifier)
    {
    }

    public function orders(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canViewAll($actor), 403);

        $e1 = MonitoringDetector::where('code', 'E-1')->first();
        $noticeTypes = (array) ($e1?->param('order_types') ?? ['akademik_tatil', 'chetlashtirish', 'kursda_qoldirish']);
        $recipients = (array) ($e1?->param('recipients') ?? array_keys(MonitoringNoticeLog::RECIPIENT_KINDS));

        $orders = MonitoringOrder::query()
            ->with('noticeLogs')
            ->when($request->filled('turi'), fn ($q) => $q->where('order_type', $request->input('turi')))
            ->when($request->input('xabar') === 'kerak', fn ($q) => $q->whereIn('order_type', $noticeTypes))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w
                ->where('full_name', 'like', '%' . $request->input('q') . '%')
                ->orWhere('order_number', 'like', '%' . $request->input('q') . '%')
                ->orWhere('student_id_number', $request->input('q'))))
            ->orderByDesc('order_date')
            ->paginate(50)->withQueryString();

        return view('admin.monitoring.buyruqlar.index', [
            'orders' => $orders,
            'noticeTypes' => $noticeTypes,
            'recipients' => $recipients,
            'canWork' => MonitoringAccess::canWork($actor),
        ]);
    }

    /**
     * E-1: mark ijara kotibi va buxgalteriyaga o'zi xabar yuboradi va M3 yozuvini qo'yadi.
     */
    public function sendNotice(Request $request, MonitoringOrder $order)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canWork($actor), 403);

        $e1 = MonitoringDetector::where('code', 'E-1')->first();
        $kinds = array_intersect(
            (array) $request->input('kinds', (array) ($e1?->param('recipients') ?? [])),
            array_keys(MonitoringNoticeLog::RECIPIENT_KINDS)
        );

        $title = "Buyruq: {$order->typeLabel()} — {$order->full_name}";
        $body = "Buyruq № {$order->order_number}, {$order->order_date->format('d.m.Y')}.\n"
            . 'Ijara/TTJ va to\'lov hisob-kitobini tegishli tartibda o\'zgartiring.';

        $sent = [];
        $missing = [];
        foreach ($kinds as $kind) {
            $recipients = $this->recipientsFor($kind, $e1);
            if ($recipients === []) {
                $missing[] = MonitoringNoticeLog::RECIPIENT_KINDS[$kind];
                continue;
            }

            $delivered = [];
            foreach ($recipients as $recipient) {
                if ($this->notifier->send($recipient, $title, $body, null, ['monitoring_order_id' => $order->id])) {
                    $delivered[] = $recipient;
                }
            }
            if ($delivered === []) {
                $missing[] = MonitoringNoticeLog::RECIPIENT_KINDS[$kind];
                continue;
            }

            foreach ($delivered as $recipient) {
                MonitoringNoticeLog::create([
                    'order_id' => $order->id,
                    'recipient_kind' => $kind,
                    'recipient_type' => $recipient->getMorphClass(),
                    'recipient_id' => $recipient->getKey(),
                    'recipient_name' => MonitoringAccess::displayName($recipient),
                    'sent_at' => now(),
                    'method' => 'mark',
                    'recorded_by_type' => $actor->getMorphClass(),
                    'recorded_by_id' => $actor->getKey(),
                ]);
            }
            $sent[] = MonitoringNoticeLog::RECIPIENT_KINDS[$kind];
        }

        $message = $sent ? 'Xabar yuborildi: ' . implode(', ', $sent) . '.' : 'Xabar yuborilmadi.';
        if ($missing) {
            $message .= ' Qabul qiluvchi topilmadi: ' . implode(', ', $missing) . ' (E-1 detektori sozlamasida tanlang).';
        }

        return back()->with($missing ? 'error' : 'success', $message);
    }

    /** M3: xabar boshqa yo'l bilan (qog'ozda) berilgan bo'lsa — qo'lda qayd etish. */
    public function recordNotice(Request $request, MonitoringOrder $order)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canWork($actor), 403);

        $data = $request->validate([
            'recipient_kind' => 'required|in:' . implode(',', array_keys(MonitoringNoticeLog::RECIPIENT_KINDS)),
            'recipient_name' => 'nullable|string|max:255',
            'sent_at' => 'required|date|before_or_equal:now',
            'method' => 'required|in:qogoz,boshqa',
            'note' => 'nullable|string|max:500',
            'evidence' => 'nullable|file|max:' . (int) config('monitoring.evidence_max_kb', 20480) . '|mimes:pdf,jpg,jpeg,png',
        ]);

        $evidencePath = $request->hasFile('evidence')
            ? $request->file('evidence')->storeAs(
                trim(config('monitoring.evidence_dir', 'monitoring'), '/') . '/xabarnoma/' . $order->id,
                Str::uuid() . '.' . strtolower($request->file('evidence')->getClientOriginalExtension()),
                config('monitoring.evidence_disk', 'local')
            )
            : null;

        MonitoringNoticeLog::create(array_merge($data, [
            'order_id' => $order->id,
            'evidence_path' => $evidencePath,
            'recorded_by_type' => $actor->getMorphClass(),
            'recorded_by_id' => $actor->getKey(),
        ]));

        return back()->with('success', 'Xabarnoma qayd etildi.');
    }

    public function recommendations(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canViewAll($actor), 403);

        $a5 = MonitoringDetector::where('code', 'A-5')->first();
        $year = (string) $request->input('yil', $a5?->param('education_year') ?? MonitoringImportController::defaultEducationYear());

        $recommendations = MonitoringAdmissionRecommendation::query()
            ->where('education_year', $year)
            ->with('student:hemis_id,full_name,student_id_number,group_name,student_status_code')
            ->when($request->input('moslik') === 'yoq', fn ($q) => $q->whereNull('student_hemis_id'))
            ->when($request->input('moslik') === 'tasdiqlanmagan', fn ($q) => $q->whereNotNull('student_hemis_id')->where('match_confirmed', false))
            ->when($request->filled('q'), fn ($q) => $q->where('full_name', 'like', '%' . $request->input('q') . '%'))
            ->orderBy('full_name')
            ->paginate(50)->withQueryString();

        $contracts = DB::table('contract_list')
            ->whereIn('student_hemis_id', $recommendations->pluck('student_hemis_id')->filter()->all())
            ->where('edu_year', 'like', $year . '%')
            ->get(['student_hemis_id', 'edu_contract_sum', 'paid_credit_amount'])
            ->keyBy('student_hemis_id');

        return view('admin.monitoring.tavsiyalar.index', [
            'recommendations' => $recommendations,
            'contracts' => $contracts,
            'year' => $year,
            'canWork' => MonitoringAccess::canWork($actor),
        ]);
    }

    public function linkRecommendation(Request $request, MonitoringAdmissionRecommendation $recommendation)
    {
        abort_unless(MonitoringAccess::canWork(MonitoringAccess::actor()), 403);

        $data = $request->validate([
            'action' => 'required|in:confirm,link,unlink',
            'student_id_number' => 'nullable|required_if:action,link|string|max:50',
        ], ['student_id_number.required_if' => 'Talaba ID raqamini kiriting.']);

        if ($data['action'] === 'unlink') {
            $recommendation->update(['student_hemis_id' => null, 'match_method' => null, 'match_confirmed' => false]);

            return back()->with('success', 'Moslik olib tashlandi.');
        }

        if ($data['action'] === 'confirm') {
            abort_if(!$recommendation->student_hemis_id, 422);
            $recommendation->update(['match_confirmed' => true]);

            return back()->with('success', 'Moslik tasdiqlandi.');
        }

        $student = Student::where('student_id_number', $data['student_id_number'])->first(['hemis_id']);
        if ($student === null) {
            return back()->with('error', 'Bu ID raqamli talaba topilmadi.');
        }
        $recommendation->update(['student_hemis_id' => $student->hemis_id, 'match_method' => 'qolda', 'match_confirmed' => true]);

        return back()->with('success', 'Talabaga bog\'landi.');
    }

    public function checklists(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canViewAll($actor), 403);

        $e7 = MonitoringDetector::where('code', 'E-7')->first();
        $requireOriginal = (bool) ($e7?->param('require_original_check') ?? true);

        $checklists = MonitoringTransferChecklist::query()
            ->with('order')
            ->when($request->input('holat') === 'toliq_emas', fn ($q) => $q->whereNull('completed_at'))
            ->when($request->filled('q'), fn ($q) => $q->where('full_name', 'like', '%' . $request->input('q') . '%'))
            ->latest()
            ->paginate(30)->withQueryString();

        return view('admin.monitoring.yigmajild.index', [
            'checklists' => $checklists,
            'documents' => MonitoringTransferChecklist::documents(),
            'requireOriginal' => $requireOriginal,
            'canWork' => MonitoringAccess::canWork($actor),
        ]);
    }

    public function storeChecklist(Request $request)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canWork($actor), 403);

        $data = $request->validate([
            'student_id_number' => 'nullable|string|max:50',
            'full_name' => 'required_without:student_id_number|nullable|string|max:255',
            'arrived_at' => 'nullable|date',
        ], ['full_name.required_without' => 'Talaba ID raqami yoki F.I.Sh. kerak.']);

        $student = !empty($data['student_id_number'])
            ? Student::where('student_id_number', $data['student_id_number'])->first(['hemis_id', 'full_name', 'student_id_number'])
            : null;
        if (!empty($data['student_id_number']) && $student === null && empty($data['full_name'])) {
            return back()->withInput()->with('error', 'Bu ID raqamli talaba topilmadi — F.I.Sh. ni kiriting.');
        }

        MonitoringTransferChecklist::create([
            'student_hemis_id' => $student?->hemis_id,
            'student_id_number' => $student?->student_id_number ?? ($data['student_id_number'] ?? null),
            'full_name' => $student?->full_name ?? $data['full_name'],
            'arrived_at' => $data['arrived_at'] ?? null,
            'items' => [],
            'updated_by_type' => $actor->getMorphClass(),
            'updated_by_id' => $actor->getKey(),
        ]);

        return back()->with('success', 'Yig\'majild cheklisti ochildi.');
    }

    public function updateChecklist(Request $request, MonitoringTransferChecklist $checklist)
    {
        $actor = MonitoringAccess::actor();
        abort_unless(MonitoringAccess::canWork($actor), 403);

        $request->validate([
            'items' => 'nullable|array',
            'items.*.izoh' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
        ]);

        $items = [];
        foreach (array_keys(MonitoringTransferChecklist::documents()) as $key) {
            $input = (array) $request->input("items.{$key}", []);
            $items[$key] = [
                'bor' => !empty($input['bor']),
                'asli_tekshirildi' => !empty($input['asli_tekshirildi']),
                'izoh' => $input['izoh'] ?? null,
            ];
        }

        $e7 = MonitoringDetector::where('code', 'E-7')->first();
        $checklist->fill([
            'items' => $items,
            'note' => $request->input('note'),
            'updated_by_type' => $actor->getMorphClass(),
            'updated_by_id' => $actor->getKey(),
        ]);
        $complete = $checklist->isComplete((bool) ($e7?->param('require_original_check') ?? true));
        $checklist->completed_at = $complete ? ($checklist->completed_at ?? now()) : null;
        $checklist->save();

        return back()->with('success', $complete ? 'Yig\'majild to\'liq.' : 'Cheklist saqlandi.');
    }

    /**
     * E-1 qabul qiluvchilari: detektor sozlamasidagi xodim (masalan ijara kotibi),
     * buxgalteriya uchun tanlanmagan bo'lsa — buxgalteriya rolidagi barcha xodimlar.
     *
     * @return \Illuminate\Database\Eloquent\Model[]
     */
    private function recipientsFor(string $kind, ?MonitoringDetector $e1): array
    {
        $chosen = MonitoringAccess::resolveOwnerKey((string) ($e1?->param("recipient_{$kind}") ?? ''));
        if ($chosen !== null) {
            return [$chosen];
        }

        if ($kind === 'buxgalteriya') {
            return Teacher::whereHas('roles', fn ($q) => $q->where('name', 'buxgalteriya'))->get()
                ->concat(User::whereHas('roles', fn ($q) => $q->where('name', 'buxgalteriya'))->get())
                ->all();
        }

        return [];
    }
}
