<?php

namespace App\Http\Controllers\Teacher\AcademicDept;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeGroupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class RetakeGroupController extends Controller
{
    public function __construct(
        private RetakeGroupService $groupService,
        private RetakeApplicationService $applicationService,
    ) {}

    /**
     * Asosiy sahifa: yangi shakllantirish kerak bo'lgan to'plamlar + mavjud guruhlar.
     */
    public function index(Request $request)
    {
        $this->authorize();

        // Tasdiqlanishi kutilayotgan arizalar fan + semestr bo'yicha
        $aggregations = $this->groupService->pendingAggregations();

        // Mavjud guruhlar
        $statusFilter = $request->input('status', 'all');
        $groupsQuery = RetakeGroup::query()
            ->with('teacher')
            ->withCount(['applications as students_count'])
            ->orderByDesc('created_at');

        if ($statusFilter !== 'all') {
            $groupsQuery->where('status', $statusFilter);
        }

        $groups = $groupsQuery->paginate(20)->withQueryString();

        return view('teacher.academic-dept.retake-groups.index', [
            'aggregations' => $aggregations,
            'groups' => $groups,
            'statusFilter' => $statusFilter,
            'canOverride' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
        ]);
    }

    /**
     * AJAX: bitta fan + semestr uchun arizalar va o'qituvchilar ro'yxati.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize();

        $data = $request->validate([
            'subject_id' => 'required|string',
            'semester_id' => 'required|string',
        ]);

        $apps = $this->groupService->applicationsForSubject($data['subject_id'], $data['semester_id']);

        $applications = $apps->map(function (RetakeApplication $a) {
            $student = $a->group->student ?? null;
            return [
                'id' => $a->id,
                'student_name' => $student?->full_name ?? '— talaba topilmadi',
                'student_hemis_id' => $a->student_hemis_id,
                'department_name' => $student?->department_name,
                'specialty_name' => $student?->specialty_name,
                'level_name' => $student?->level_name ?? $student?->level_code,
                'group_name' => $student?->group_name,
                'credit' => (float) $a->credit,
            ];
        });

        // O'qituvchilar — barcha aktiv teacher'lar (kelajakda fakultetga moslashtirsa bo'ladi)
        $teachers = Teacher::query()
            ->where('status', true)
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'department']);

        return response()->json([
            'applications' => $applications,
            'teachers' => $teachers,
        ]);
    }

    /**
     * Guruh yaratish (POST).
     */
    public function store(Request $request): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'name' => 'required|string|max:255',
            'subject_id' => 'required|string',
            'subject_name' => 'required|string|max:255',
            'subject_code' => 'nullable|string|max:50',
            'semester_id' => 'required|string',
            'semester_name' => 'required|string|max:255',
            'teacher_id' => 'required|integer|exists:teachers,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
            'max_students' => 'nullable|integer|min:1',
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'integer',
            'action' => 'required|in:save,publish',
        ]);

        $publish = $data['action'] === 'publish';

        try {
            /** @var Teacher $actor */
            $actor = RetakeAccess::currentStaff();
            $group = $this->groupService->createGroup(
                $data,
                $data['application_ids'],
                $actor,
                $publish,
            );
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors())->withInput();
        }

        $msg = $publish
            ? __('Guruh yaratildi va tasdiqlandi')
            : __('Guruh shakllantirilmoqda (draft)');

        return redirect()->route('admin.retake-groups.index')->with('success', $msg);
    }

    /**
     * Guruhni tahrirlash sahifasi.
     */
    public function edit(int $groupId)
    {
        $this->authorize();

        $group = RetakeGroup::with(['teacher', 'applications.group.student'])->findOrFail($groupId);

        $teachers = Teacher::query()
            ->where('status', true)
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'department']);

        return view('teacher.academic-dept.retake-groups.edit', [
            'group' => $group,
            'teachers' => $teachers,
            'canOverride' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
        ]);
    }

    /**
     * Guruhni yangilash.
     */
    public function update(Request $request, int $groupId): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'name' => 'sometimes|string|max:255',
            'teacher_id' => 'sometimes|integer|exists:teachers,id',
            'start_date' => 'sometimes|date',
            'end_date' => 'sometimes|date',
            'max_students' => 'sometimes|nullable|integer|min:1',
        ]);

        $group = RetakeGroup::findOrFail($groupId);

        try {
            /** @var Teacher $actor */
            $actor = RetakeAccess::currentStaff();
            $isAdmin = RetakeAccess::canOverride($actor);
            $this->groupService->updateGroup($group, $data, $actor, $isAdmin);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->route('admin.retake-groups.edit', $groupId)
            ->with('success', __('Guruh yangilandi'));
    }

    /**
     * Forming guruhni publish qilish (scheduled).
     */
    public function publish(int $groupId): RedirectResponse
    {
        $this->authorize();

        $group = RetakeGroup::findOrFail($groupId);

        try {
            /** @var Teacher $actor */
            $actor = RetakeAccess::currentStaff();
            $this->groupService->publish($group, $actor);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __('Guruh tasdiqlandi va talabalarga ko\'rindi'));
    }

    /**
     * O'quv bo'limi yakka arizani rad etadi (guruhga qo'shilmaydi).
     */
    public function rejectApplication(Request $request, int $applicationId): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'reason' => 'required|string|min:' . \App\Models\RetakeSetting::rejectReasonMinLength() . '|max:1000',
        ]);

        $app = RetakeApplication::findOrFail($applicationId);

        try {
            /** @var Teacher $actor */
            $actor = RetakeAccess::currentStaff();
            $this->applicationService->academicReject($app, $actor, $data['reason']);
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __('Ariza rad etildi'));
    }

    /**
     * Manual override: guruh holatini o'zgartirish (faqat super-admin).
     */
    public function overrideStatus(Request $request, int $groupId): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403);
        }

        $data = $request->validate([
            'status' => 'required|in:forming,scheduled,in_progress,completed',
        ]);

        $group = RetakeGroup::findOrFail($groupId);
        $group->update(['status' => $data['status']]);

        return redirect()->back()->with('success', __('Holat o\'zgartirildi'));
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda qayta o\'qish guruhlarini boshqarish ruxsati yo\'q');
        }
    }
}
