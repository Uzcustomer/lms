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
        $search = trim((string) $request->input('search', ''));

        $studentFilters = [
            'education_type' => $request->input('education_type'),
            'department' => $request->input('department'),
            'specialty' => $request->input('specialty'),
            'level_code' => $request->input('level_code'),
            'semester_code' => $request->input('semester_code'),
            'group' => $request->input('group'),
        ];
        $subjectFilter = $request->input('subject');
        $perPage = (int) $request->input('per_page', 50);
        if (!in_array($perPage, [10, 25, 50, 100], true)) {
            $perPage = 50;
        }

        // Guruh shakllantirilishi kutilayotgan arizalar — yassi ro'yxat (filtrlanadi).
        // O'quv bo'limi tasdiqlagan, lekin guruhga biriktirilmagan arizalar.
        $studentSearch = trim((string) $request->input('search', ''));
        $pendingAppsQuery = RetakeApplication::query()
            ->with(['group.student'])
            ->where('dean_status', 'approved')
            ->where('registrar_status', 'approved')
            ->where('academic_dept_status', 'approved')
            ->where('final_status', 'pending')
            ->whereNull('retake_group_id');

        // Talaba ma'lumoti bo'yicha filtrlar (cascading: education_type, department, ...)
        $hasStudentLevelFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty()
            || $studentSearch !== '';
        if ($hasStudentLevelFilter) {
            $pendingAppsQuery->whereIn('student_hemis_id', function ($sub) use ($studentFilters, $studentSearch) {
                $sub->select('hemis_id')->from('students');
                if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
                if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
                if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
                if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
                if (!empty($studentFilters['semester_code'])) $sub->where('semester_code', $studentFilters['semester_code']);
                if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
                if ($studentSearch !== '') {
                    $sub->where(function ($q) use ($studentSearch) {
                        $q->where('full_name', 'like', "%{$studentSearch}%")
                          ->orWhere('hemis_id', $studentSearch);
                    });
                }
            });
        }
        if ($subjectFilter) {
            $pendingAppsQuery->where('subject_id', $subjectFilter);
        }

        $pendingApps = $pendingAppsQuery
            ->orderBy('subject_name')
            ->orderBy('semester_name')
            ->orderByDesc('created_at')
            ->paginate($perPage, ['*'], 'apps_page')
            ->withQueryString();

        $groupsQuery = RetakeGroup::query()
            ->with('teacher')
            ->withCount(['applications as students_count'])
            ->orderByDesc('created_at');

        if ($statusFilter !== 'all'
            && in_array($statusFilter, ['forming', 'scheduled', 'in_progress', 'completed'], true)
        ) {
            $groupsQuery->where('status', $statusFilter);
        }

        if ($search !== '') {
            $groupsQuery->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('subject_name', 'like', "%{$search}%")
                  ->orWhere('teacher_name', 'like', "%{$search}%");
            });
        }

        // Talaba ma'lumotlari bo'yicha filtrlar — guruh ichidagi arizalardan
        // hech bo'lmaganda bittasi tanlangan talaba shartiga mos kelishi kerak.
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();
        if ($hasStudentFilter) {
            $groupsQuery->whereHas('applications', function ($q) use ($studentFilters) {
                $q->whereIn('student_hemis_id', function ($sub) use ($studentFilters) {
                    $sub->select('hemis_id')->from('students');
                    if (!empty($studentFilters['education_type'])) $sub->where('education_type_code', $studentFilters['education_type']);
                    if (!empty($studentFilters['department'])) $sub->where('department_id', $studentFilters['department']);
                    if (!empty($studentFilters['specialty'])) $sub->where('specialty_id', $studentFilters['specialty']);
                    if (!empty($studentFilters['level_code'])) $sub->where('level_code', $studentFilters['level_code']);
                    if (!empty($studentFilters['semester_code'])) $sub->where('semester_code', $studentFilters['semester_code']);
                    if (!empty($studentFilters['group'])) $sub->where('group_id', $studentFilters['group']);
                });
            });
        }

        if ($subjectFilter) {
            $groupsQuery->where('subject_id', $subjectFilter);
        }

        $groups = $groupsQuery->paginate($perPage)->withQueryString();

        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();
        $subjects = \App\Services\Retake\RetakeFilterCache::subjects();

        // Bo'sh (talabasiz) guruh ID'lari — o'chirish mumkin
        $deletableGroupIds = $groups->getCollection()
            ->filter(fn ($g) => ($g->students_count ?? 0) === 0)
            ->pluck('id')
            ->all();

        $trashedCount = RetakeGroup::onlyTrashed()->count();

        return view('teacher.academic-dept.retake-groups.index', [
            'aggregations' => $aggregations,
            'pendingApps' => $pendingApps,
            'groups' => $groups,
            'statusFilter' => $statusFilter,
            'search' => $search,
            'canOverride' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
            'educationTypes' => $educationTypes,
            'subjects' => $subjects,
            'deletableGroupIds' => $deletableGroupIds,
            'trashedCount' => $trashedCount,
        ]);
    }

    /**
     * AJAX: bitta fan + semestr uchun arizalar va o'qituvchilar ro'yxati.
     */
    public function lookup(Request $request): JsonResponse
    {
        $this->authorize();

        $data = $request->validate([
            'subject_name' => 'required|string',
            'semester_name' => 'required|string',
        ]);

        $apps = $this->groupService->applicationsForSubject($data['subject_name'], $data['semester_name']);

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
                'has_oske' => (bool) $a->has_oske,
                'has_test' => (bool) $a->has_test,
                'has_sinov' => (bool) $a->has_sinov,
            ];
        });

        // Qabul oynasidagi sanalar — guruh shu vaqtga moslashadi.
        // Arizalar bir nechta oyna ostida bo'lishi mumkin (turli fakultet/kurs);
        // shuning uchun majority bo'yicha eng ko'p uchragan oynani olamiz.
        $windowDates = null;
        $windowIds = $apps->pluck('group.window_id')->filter();
        if ($windowIds->isNotEmpty()) {
            $countByWindow = $windowIds->countBy()->sortDesc();
            $topWindowId = $countByWindow->keys()->first();
            $window = \App\Models\RetakeApplicationWindow::find($topWindowId);
            if ($window) {
                $windowDates = [
                    'start_date' => $window->start_date->format('Y-m-d'),
                    'end_date' => $window->end_date->format('Y-m-d'),
                ];
            }
        }

        // O'qituvchilar — barcha aktiv teacher'lar (kelajakda fakultetga moslashtirsa bo'ladi)
        $teachers = Teacher::query()
            ->where('status', true)
            ->orderBy('full_name')
            ->limit(500)
            ->get(['id', 'full_name', 'department']);

        return response()->json([
            'applications' => $applications,
            'teachers' => $teachers,
            'windowDates' => $windowDates,
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
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'max_students' => 'nullable|integer|min:1',
            'application_ids' => 'required|array|min:1',
            'application_ids.*' => 'integer',
            'action' => 'required|in:save,publish',
            // O'quv bo'limi qo'lda tanlaydi — majburiy
            'assessment_type' => 'required|in:oske,test,oske_test,sinov_fan',
            'oske_date' => 'nullable|date|required_if:assessment_type,oske,oske_test',
            'test_date' => 'nullable|date|required_if:assessment_type,test,oske_test|after_or_equal:oske_date',
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
            'assessment_type' => 'sometimes|in:oske,test,oske_test,sinov_fan',
            'oske_date' => 'sometimes|nullable|date',
            'test_date' => 'sometimes|nullable|date|after_or_equal:oske_date',
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

    /**
     * Guruhni arxivga ko'chirish (soft delete) — faqat hech qanday talaba
     * biriktirilmagan bo'lsa.
     */
    public function destroy(int $groupId): RedirectResponse
    {
        $this->authorize();

        $group = RetakeGroup::withCount('applications as students_count')->findOrFail($groupId);

        if (($group->students_count ?? 0) > 0) {
            return redirect()->back()->withErrors([
                'group' => 'Bu guruhga talabalar biriktirilgan, o\'chirib bo\'lmaydi',
            ]);
        }

        $group->delete();

        return redirect()->route('admin.retake-groups.index')
            ->with('success', __('Guruh arxivga ko\'chirildi'));
    }

    /**
     * Tanlangan guruhlarni ommaviy arxivga ko'chirish.
     */
    public function bulkDestroy(Request $request): RedirectResponse
    {
        $this->authorize();

        $data = $request->validate([
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'integer',
        ]);

        $groups = RetakeGroup::withCount('applications as students_count')
            ->whereIn('id', $data['group_ids'])
            ->get();

        $deleted = 0;
        $skipped = 0;

        foreach ($groups as $group) {
            if (($group->students_count ?? 0) > 0) {
                $skipped++;
                continue;
            }
            $group->delete();
            $deleted++;
        }

        $msg = "{$deleted} ta guruh arxivga ko'chirildi";
        if ($skipped > 0) {
            $msg .= ", {$skipped} ta guruhga talabalar biriktirilgani sababli o'tkazib yuborildi";
        }

        return redirect()->route('admin.retake-groups.index')->with('success', $msg);
    }

    /**
     * Tarix sahifasi — arxivlangan guruhlar.
     */
    public function trashed()
    {
        $this->authorize();

        $groups = RetakeGroup::onlyTrashed()
            ->with('teacher')
            ->withCount('applications as students_count')
            ->orderByDesc('deleted_at')
            ->get();

        return view('teacher.academic-dept.retake-groups.trashed', [
            'groups' => $groups,
            'canForceDelete' => RetakeAccess::canOverride(RetakeAccess::currentStaff()),
        ]);
    }

    /**
     * Arxivdan tiklash.
     */
    public function restore(int $groupId): RedirectResponse
    {
        $this->authorize();

        $group = RetakeGroup::onlyTrashed()->findOrFail($groupId);
        $group->restore();

        return redirect()->route('admin.retake-groups.trashed')
            ->with('success', __('Guruh tiklandi'));
    }

    /**
     * Arxivdan butunlay o'chirish (faqat super-admin).
     */
    public function forceDestroy(int $groupId): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403);
        }

        $group = RetakeGroup::onlyTrashed()->findOrFail($groupId);
        $group->forceDelete();

        return redirect()->route('admin.retake-groups.trashed')
            ->with('success', __('Guruh butunlay o\'chirildi'));
    }

    /**
     * Tanlangan guruhlarni butunlay o'chirish (faqat super-admin).
     * Tarixda qolmaydi. Aktiv yoki arxivlangan bo'lishidan qat'iy nazar ishlaydi.
     */
    public function bulkForceDestroy(Request $request): RedirectResponse
    {
        if (!RetakeAccess::canOverride(RetakeAccess::currentStaff())) {
            abort(403);
        }

        $data = $request->validate([
            'group_ids' => 'required|array|min:1',
            'group_ids.*' => 'integer',
        ]);

        $deleted = RetakeGroup::withTrashed()
            ->whereIn('id', $data['group_ids'])
            ->get()
            ->each(fn ($g) => $g->forceDelete())
            ->count();

        return redirect()->back()->with('success', "{$deleted} ta guruh butunlay o'chirildi");
    }

    private function authorize(): void
    {
        if (!RetakeAccess::canManageAcademicDept(RetakeAccess::currentStaff())) {
            abort(403, 'Sizda qayta o\'qish guruhlarini boshqarish ruxsati yo\'q');
        }
    }
}
