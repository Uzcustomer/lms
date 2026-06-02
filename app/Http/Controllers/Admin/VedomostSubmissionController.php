<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VedomostSubmissionExport;
use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Setting;
use App\Models\VedomostSubmission;
use App\Models\VedomostSubmissionLog;
use App\Services\VedomostSubmissionNotifier;
use App\Services\VedomostSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class VedomostSubmissionController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

    /** Telegram/bildirishnoma toggle'ini boshqara oladigan rollar (admin). */
    private const NOTIFY_TOGGLE_ROLES = ['superadmin', 'admin', 'kichik_admin'];

    /** Saralash mumkin bo'lgan ustunlar. */
    private const SORTABLE = [
        'group' => 'vs.group_name',
        'subject' => 'vs.subject_name',
        'department' => 'vs.department_name',
        'teacher' => 'vs.teacher_name',
        'closing_form' => 'vs.closing_form',
        'semester' => 'vs.semester_code',
        'base_date' => 'vs.base_date',
        'deadline' => 'vs.deadline',
        'status' => 'vs.status',
    ];

    public function __construct(
        private VedomostSubmissionService $service,
        private VedomostSubmissionNotifier $notifier
    ) {
    }

    private function checkAccess(): void
    {
        if (!auth()->user()) {
            abort(403);
        }
        $activeRole = session('active_role', '');
        if (!in_array($activeRole, self::ALLOWED_ROLES, true)) {
            abort(403, "Vedomost hisobotini faqat admin va registrator ofisi ko'ra oladi.");
        }
    }

    private function resolveSelectedEducationType(Request $request, $educationTypes)
    {
        if ($request->has('education_type')) {
            return $request->get('education_type');
        }

        return $educationTypes
            ->first(fn($t) => str_contains(mb_strtolower($t->education_type_name ?? ''), 'bakalavr'))
            ?->education_type_code;
    }

    /**
     * Filtrlangan so'rov (hisobot va Excel eksport bitta mantiqdan foydalanadi).
     */
    private function filteredQuery(Request $request)
    {
        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();
        $selectedEducationType = $this->resolveSelectedEducationType($request, $educationTypes);

        $query = DB::table('vedomost_submissions as vs')
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'vs.curriculum_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->leftJoin('semesters as s', function ($join) {
                $join->on('s.curriculum_hemis_id', '=', 'vs.curriculum_hemis_id')
                    ->on('s.code', '=', 'vs.semester_code');
            })
            ->select('vs.*', 'f.name as faculty_name', 's.level_name', 's.level_code');

        if ($selectedEducationType) {
            $query->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $query->where('f.id', $request->faculty);
        }
        if ($request->filled('specialty')) {
            $query->where('vs.specialty_name', $request->specialty);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('vs.semester_code', $request->semester_code);
        }
        if ($request->filled('subject_name')) {
            $query->where('vs.subject_name', 'like', '%' . $request->subject_name . '%');
        }
        if ($request->filled('closing_form_filter')) {
            $query->where('vs.closing_form', $request->closing_form_filter);
        }
        if ($request->filled('status')) {
            $query->where('vs.status', $request->status);
        }
        if ($request->boolean('overdue')) {
            $query->whereNotNull('vs.deadline')
                ->whereDate('vs.deadline', '<', now()->toDateString())
                ->where('vs.status', '!=', VedomostSubmission::STATUS_APPROVED);
        }

        // Saralash
        $sort = $request->get('sort');
        $dir = $request->get('dir') === 'desc' ? 'desc' : 'asc';
        if (isset(self::SORTABLE[$sort])) {
            $query->orderBy(self::SORTABLE[$sort], $dir);
        } else {
            $query->orderByRaw('vs.deadline IS NULL')
                ->orderBy('vs.deadline')
                ->orderBy('vs.group_name')
                ->orderBy('vs.subject_name');
        }

        return [$query, $educationTypes, $selectedEducationType];
    }

    public function index(Request $request)
    {
        $this->checkAccess();

        [$query, $educationTypes, $selectedEducationType] = $this->filteredQuery($request);

        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        // Yo'nalishlar — nom bo'yicha distinct (bir xil nomlilar bitta ko'rinadi).
        // Tanlangan ta'lim turi/fakultetga qarab cheklanadi.
        $specialtyQuery = DB::table('vedomost_submissions as vs')
            ->leftJoin('curricula as c', 'c.curricula_hemis_id', '=', 'vs.curriculum_hemis_id')
            ->leftJoin('departments as f', 'f.department_hemis_id', '=', 'c.department_hemis_id')
            ->whereNotNull('vs.specialty_name')
            ->where('vs.specialty_name', '!=', '');
        if ($selectedEducationType) {
            $specialtyQuery->where('c.education_type_code', $selectedEducationType);
        }
        if ($request->filled('faculty')) {
            $specialtyQuery->where('f.id', $request->faculty);
        }
        $specialties = $specialtyQuery->distinct()
            ->orderBy('vs.specialty_name')
            ->pluck('vs.specialty_name');

        $closingForms = [
            'oski' => 'Faqat OSKI',
            'test' => 'Faqat Test',
            'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ',
            'sinov' => 'Sinov (test)',
        ];

        // Status statistikasi (filtrlardan mustaqil — umumiy holat)
        $stats = VedomostSubmission::select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $perPage = (int) $request->get('per_page', 50);
        $submissions = $query->paginate($perPage)->appends($request->query());

        $notifyEnabled = VedomostSubmissionNotifier::enabled();
        $canToggleNotify = in_array(session('active_role', ''), self::NOTIFY_TOGGLE_ROLES, true);

        return view('admin.vedomost-submission.index', compact(
            'submissions',
            'faculties',
            'specialties',
            'educationTypes',
            'selectedEducationType',
            'closingForms',
            'stats',
            'notifyEnabled',
            'canToggleNotify'
        ));
    }

    public function export(Request $request)
    {
        $this->checkAccess();

        [$query] = $this->filteredQuery($request);

        $closingForms = [
            'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
            'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
        ];
        $statusLabels = VedomostSubmission::statusLabels();
        $today = now()->toDateString();

        $rows = [];
        $i = 1;
        foreach ($query->get() as $v) {
            $overdue = $v->deadline && $v->deadline < $today && $v->status !== VedomostSubmission::STATUS_APPROVED;
            $rows[] = [
                $i++,
                $v->faculty_name,
                $v->group_name,
                $v->specialty_name,
                $v->subject_name,
                $v->department_name,
                $v->teacher_name,
                $v->teacher_phone,
                $v->fan_masuli_name,
                $v->fan_masuli_phone,
                $v->kafedra_mudiri_name,
                $v->kafedra_mudiri_phone,
                $closingForms[$v->closing_form] ?? $v->closing_form,
                $v->base_date ? date('d.m.Y', strtotime($v->base_date)) : '',
                $v->deadline ? date('d.m.Y', strtotime($v->deadline)) : '',
                $overdue ? 'Ha' : '',
                $statusLabels[$v->status] ?? $v->status,
            ];
        }

        $fileName = 'vedomost-topshirish-' . now()->format('Y-m-d_His') . '.xlsx';

        return (new VedomostSubmissionExport($rows))->download($fileName);
    }

    /**
     * Joriy semestr bo'yicha vedomost yozuvlarini generatsiya/yangilash.
     */
    public function sync(Request $request)
    {
        $this->checkAccess();

        $count = $this->service->sync();

        return redirect()
            ->route('admin.vedomost-submission.index', $request->query())
            ->with('success', "Joriy semestr bo'yicha {$count} ta vedomost yozuvi yangilandi.");
    }

    /**
     * Telegram/bildirishnoma yuborishni yoqish/o'chirish (faqat admin).
     */
    public function toggleNotify(Request $request)
    {
        $this->checkAccess();
        if (!in_array(session('active_role', ''), self::NOTIFY_TOGGLE_ROLES, true)) {
            abort(403, 'Bu sozlamani faqat admin o\'zgartira oladi.');
        }

        $enabled = $request->boolean('enabled');
        Setting::set('vedomost_notify_enabled', $enabled ? '1' : '0');

        return redirect()
            ->route('admin.vedomost-submission.index', $request->except(['enabled', '_token']))
            ->with('success', $enabled
                ? 'Telegram/bildirishnoma yuborish YOQILDI.'
                : "Telegram/bildirishnoma yuborish O'CHIRILDI (test rejimi).");
    }

    public function show($id)
    {
        $this->checkAccess();
        $submission = VedomostSubmission::with('logs')->findOrFail($id);
        $aiConfigured = \App\Services\VedomostAiChecker::isConfigured();

        return view('admin.vedomost-submission.show', compact('submission', 'aiConfigured'));
    }

    /**
     * Yuklangan vedomostni Claude API orqali tizim ma'lumotiga solishtirib tekshirish.
     * Faqat tavsiya — yakuniy qarorni registrator qiladi. Async (Job).
     */
    public function aiCheck($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!\App\Services\VedomostAiChecker::isConfigured()) {
            return back()->with('error', "AI tekshiruv sozlanmagan (ANTHROPIC_API_KEY yo'q).");
        }
        if (!$v->pdf_path) {
            return back()->with('error', 'Avval skaner (PDF) yuklang.');
        }
        if ($v->ai_check_status === 'running' || $v->ai_check_status === 'queued') {
            return back()->with('error', 'AI tekshiruv allaqachon ishlamoqda.');
        }

        $v->update(['ai_check_status' => 'queued', 'ai_error' => null]);
        \App\Jobs\CheckVedomostSubmissionWithAi::dispatch($v->id);

        return back()->with('success', 'AI tekshiruv boshlandi. Natija bir necha daqiqada tayyor bo\'ladi (sahifani yangilang).');
    }

    /**
     * Skaner qilingan vedomostni yuklash (PDF + Excel). Status -> qabul qilindi.
     */
    public function uploadFiles(Request $request, $id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!in_array($v->status, [VedomostSubmission::STATUS_PENDING, VedomostSubmission::STATUS_REJECTED], true)) {
            return back()->with('error', "Bu vedomost allaqachon qabul qilingan.");
        }

        $request->validate([
            'pdf' => 'required|file|mimes:pdf|max:20480',
            'excel' => 'required|file|mimes:xlsx,xls|max:20480',
        ], [
            'pdf.required' => 'Skaner qilingan PDF faylni yuklang.',
            'pdf.mimes' => 'PDF fayl PDF formatida bo\'lishi kerak.',
            'excel.required' => 'Excel faylни yuklang.',
            'excel.mimes' => 'Excel fayl .xlsx yoki .xls formatida bo\'lishi kerak.',
        ]);

        $dir = "vedomost-submissions/{$v->id}";
        // Eski fayllar bo'lsa (qayta yuklash) — o'chiramiz
        foreach ([$v->pdf_path, $v->excel_path] as $old) {
            if ($old && Storage::disk('public')->exists($old)) {
                Storage::disk('public')->delete($old);
            }
        }

        $from = $v->status;
        $v->update([
            'pdf_path' => $request->file('pdf')->store($dir, 'public'),
            'excel_path' => $request->file('excel')->store($dir, 'public'),
            'uploaded_by' => auth()->id(),
            'uploaded_by_name' => $this->userName(),
            'uploaded_at' => now(),
            'status' => VedomostSubmission::STATUS_RECEIVED,
            'rejection_reason' => null,
            'reviewed_by' => null,
            'reviewed_by_name' => null,
            'reviewed_at' => null,
        ]);

        $this->log($v, 'upload', $from, $v->status);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost yuklandi va tekshirishga qabul qilindi.');
    }

    public function review($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if ($v->status !== VedomostSubmission::STATUS_RECEIVED) {
            return back()->with('error', "Faqat qabul qilingan vedomostni tekshirishga olish mumkin.");
        }

        $from = $v->status;
        $v->update(['status' => VedomostSubmission::STATUS_REVIEWING]);
        $this->log($v, 'review', $from, $v->status);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost tekshirishga olindi.');
    }

    public function approve($id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!in_array($v->status, [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING], true)) {
            return back()->with('error', "Bu vedomostni tasdiqlab bo'lmaydi.");
        }

        $from = $v->status;
        $v->update([
            'status' => VedomostSubmission::STATUS_APPROVED,
            'reviewed_by' => auth()->id(),
            'reviewed_by_name' => $this->userName(),
            'reviewed_at' => now(),
            'rejection_reason' => null,
        ]);
        $this->log($v, 'approve', $from, $v->status);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost tasdiqlandi.');
    }

    public function reject(Request $request, $id)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        if (!in_array($v->status, [VedomostSubmission::STATUS_RECEIVED, VedomostSubmission::STATUS_REVIEWING], true)) {
            return back()->with('error', "Bu vedomostni rad etib bo'lmaydi.");
        }

        $request->validate([
            'rejection_reason' => 'required|string|min:3|max:1000',
        ], [
            'rejection_reason.required' => 'Rad etish sababini (xatolarni) kiriting.',
        ]);

        $from = $v->status;
        $v->update([
            'status' => VedomostSubmission::STATUS_REJECTED,
            'reviewed_by' => auth()->id(),
            'reviewed_by_name' => $this->userName(),
            'reviewed_at' => now(),
            'rejection_reason' => $request->rejection_reason,
        ]);
        $this->log($v, 'reject', $from, $v->status, $request->rejection_reason);
        $this->notifier->notifyStatusChange($v);

        return back()->with('success', 'Vedomost rad etildi va tegishli shaxslarga xabar yuborildi.');
    }

    public function downloadFile($id, $type)
    {
        $this->checkAccess();
        $v = VedomostSubmission::findOrFail($id);

        $path = $type === 'excel' ? $v->excel_path : $v->pdf_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404, 'Fayl topilmadi.');

        return Storage::disk('public')->download($path);
    }

    private function userName(): string
    {
        $u = auth()->user();
        return $u->name ?? $u->full_name ?? $u->short_name ?? 'Foydalanuvchi';
    }

    private function log(VedomostSubmission $v, string $action, ?string $from, ?string $to, ?string $note = null): void
    {
        VedomostSubmissionLog::create([
            'vedomost_submission_id' => $v->id,
            'action' => $action,
            'from_status' => $from,
            'to_status' => $to,
            'note' => $note,
            'user_id' => auth()->id(),
            'user_name' => $this->userName(),
        ]);
    }
}
