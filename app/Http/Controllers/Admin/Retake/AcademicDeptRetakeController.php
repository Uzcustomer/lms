<?php

namespace App\Http\Controllers\Admin\Retake;

use App\Enums\RetakeAcademicDeptStatus;
use App\Enums\RetakeGroupStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Retake\CreateRetakeGroupRequest;
use App\Http\Requests\Retake\RejectRetakeApplicationRequest;
use App\Http\Requests\Retake\UpdateRetakeGroupRequest;
use App\Models\Department;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use App\Services\Retake\RetakeApprovalService;
use App\Services\Retake\RetakeGroupService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * O'quv bo'limi yakuniy bosqich (3-bosqich):
 *  - Faqat dekan VA registrator tasdiqlagan arizalar (academic_dept_status='pending')
 *    fan + semestr bo'yicha gruhlangan ko'rinadi
 *  - O'quv bo'limi guruh shakllantiradi (sanalar + o'qituvchi MAJBURIY)
 *  - Saqlanganda: retake_groups yaratiladi, tanlangan arizalar approved bo'ladi,
 *    har biri uchun verification_code generatsiya qilinadi
 *  - Yakka talabani rad etish ham mumkin (boshqalarga ta'sir qilmaydi)
 */
class AcademicDeptRetakeController extends Controller
{
    public function __construct(
        private readonly RetakeApprovalService $approvalService,
        private readonly RetakeGroupService $groupService,
    ) {
    }

    /**
     * Gruhlangan kutilayotgan arizalar (fan + semestr bo'yicha).
     */
    public function index(Request $request): View
    {
        $this->ensureAcademicDept();

        $query = RetakeApplication::query()
            ->where('academic_dept_status', RetakeAcademicDeptStatus::PENDING->value)
            ->with(['student:id,hemis_id,full_name,group_name,group_id,department_id,department_name,specialty_name'])
            ->orderBy('subject_id')
            ->orderBy('semester_id');

        if ($departmentId = $request->query('department_id')) {
            $query->whereHas('student', fn ($q) => $q->where('department_id', $departmentId));
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('subject_name', 'like', "%{$search}%")
                    ->orWhereHas('student', fn ($s) => $s->where('full_name', 'like', "%{$search}%"));
            });
        }

        $applications = $query->get();

        // Gruhlash: subject_id|semester_id => Collection
        $grouped = $applications->groupBy(fn (RetakeApplication $a) => $a->subject_id . '|' . $a->semester_id)
            ->map(function (Collection $apps) {
                $first = $apps->first();
                return [
                    'subject_id' => $first->subject_id,
                    'subject_name' => $first->subject_name,
                    'semester_id' => $first->semester_id,
                    'semester_name' => $first->semester_name,
                    'count' => $apps->count(),
                    'applications' => $apps,
                ];
            })->sortByDesc('count')->values();

        $departments = Department::query()
            ->where('active', true)
            ->orderBy('name')
            ->get(['id', 'department_hemis_id', 'name']);

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name', 'department_hemis_id']);

        $stats = [
            'pending_groups' => $grouped->count(),
            'pending_applications' => $applications->count(),
            'active_groups' => RetakeGroup::query()
                ->whereIn('status', [RetakeGroupStatus::SCHEDULED->value, RetakeGroupStatus::IN_PROGRESS->value])
                ->count(),
            'completed_groups' => RetakeGroup::query()
                ->where('status', RetakeGroupStatus::COMPLETED->value)
                ->count(),
        ];

        return view('admin.retake.academic.index', [
            'grouped' => $grouped,
            'departments' => $departments,
            'teachers' => $teachers,
            'stats' => $stats,
            'filters' => $request->only(['department_id', 'search']),
        ]);
    }

    /**
     * Yangi guruh shakllantirish (POST).
     */
    public function storeGroup(CreateRetakeGroupRequest $request): RedirectResponse
    {
        $this->ensureAcademicDept();
        $actor = $this->actor();

        try {
            $group = $this->groupService->createAndAssign(
                $actor,
                $request->input('name'),
                (int) $request->input('subject_id'),
                $request->input('subject_name'),
                (int) $request->input('semester_id'),
                $request->input('semester_name'),
                Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
                Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
                (int) $request->input('teacher_id'),
                $request->input('max_students') ? (int) $request->input('max_students') : null,
                $request->input('application_ids'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.retake.academic.index')
            ->with('success', "Guruh \"{$group->name}\" shakllantirildi va arizalar tasdiqlandi.");
    }

    /**
     * Yakka talabani rad etish (boshqalarga ta'sir qilmaydi).
     */
    public function rejectApplication(RejectRetakeApplicationRequest $request, int $id): RedirectResponse
    {
        $this->ensureAcademicDept();
        $actor = $this->actor();
        $application = RetakeApplication::findOrFail($id);

        try {
            $this->approvalService->rejectAsAcademicDept(
                $actor,
                $application,
                $request->input('rejection_reason'),
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return back()->with('success', 'Ariza rad etildi.');
    }

    /**
     * Mavjud guruhlar boshqaruvi.
     */
    public function groupsIndex(Request $request): View
    {
        $this->ensureAcademicDept();

        $query = RetakeGroup::query()
            ->with(['teacher:id,full_name', 'applications.student:id,full_name'])
            ->withCount('applications')
            ->orderByDesc('start_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($search = trim((string) $request->query('search'))) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('subject_name', 'like', "%{$search}%");
            });
        }

        $groups = $query->paginate(25)->withQueryString();

        return view('admin.retake.academic.groups.index', [
            'groups' => $groups,
            'filters' => $request->only(['status', 'search']),
        ]);
    }

    public function groupShow(int $id): View
    {
        $this->ensureAcademicDept();

        $group = RetakeGroup::query()
            ->with(['teacher', 'applications.student'])
            ->findOrFail($id);

        $teachers = Teacher::query()
            ->where('is_active', true)
            ->orderBy('full_name')
            ->get(['id', 'full_name']);

        return view('admin.retake.academic.groups.show', [
            'group' => $group,
            'teachers' => $teachers,
        ]);
    }

    public function groupUpdate(UpdateRetakeGroupRequest $request, int $id): RedirectResponse
    {
        $this->ensureAcademicDept();
        $group = RetakeGroup::findOrFail($id);

        try {
            $this->groupService->update(
                $group,
                Carbon::createFromFormat('Y-m-d', $request->input('start_date')),
                Carbon::createFromFormat('Y-m-d', $request->input('end_date')),
                (int) $request->input('teacher_id'),
                $request->input('max_students') ? (int) $request->input('max_students') : null,
            );
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->withErrors($e->errors())->withInput();
        }

        return redirect()
            ->route('admin.retake.academic.groups.show', $id)
            ->with('success', "Guruh ma'lumotlari yangilandi.");
    }

    /**
     * Kvitansiya/DOCX/Tasdiqnoma yuklab olish (private storage).
     */
    public function downloadFile(int $id, string $type): BinaryFileResponse|StreamedResponse|RedirectResponse
    {
        $this->ensureAcademicDept();
        $application = RetakeApplication::findOrFail($id);

        $path = match ($type) {
            'receipt' => $application->receipt_path,
            'document' => $application->generated_doc_path,
            'tasdiqnoma' => $application->tasdiqnoma_pdf_path,
            default => null,
        };

        if ($path === null || ! Storage::disk('local')->exists($path)) {
            return back()->with('error', 'Fayl topilmadi.');
        }

        $filename = match ($type) {
            'receipt' => $application->receipt_original_name ?? "receipt-{$application->id}",
            'document' => "ariza-{$application->application_group_id}.docx",
            'tasdiqnoma' => "tasdiqnoma-{$application->id}.pdf",
        };

        return Storage::disk('local')->download($path, $filename);
    }

    private function ensureAcademicDept(): void
    {
        $user = $this->actor();
        if ($user === null
            || ! method_exists($user, 'hasRole')
            || ! $user->hasRole(['oquv_bolimi', 'oquv_bolimi_boshligi', 'admin', 'superadmin'])) {
            abort(403, "Faqat o'quv bo'limi xodimi foydalana oladi.");
        }
    }

    private function actor(): ?\Illuminate\Contracts\Auth\Authenticatable
    {
        return Auth::guard('teacher')->user() ?? Auth::guard('web')->user();
    }
}
