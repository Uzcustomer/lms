<?php

namespace App\Http\Controllers\Teacher;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\RetakeGroup;
use App\Models\Teacher;
use App\Services\Retake\RetakeAccess;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

/**
 * Qayta o'qish jurnali — kunlik baholar.
 *
 * Ko'radi:
 *  - Guruh o'qituvchisi (tahrirlash huquqi)
 *  - Admin / superadmin (tahrirlash huquqi)
 *  - Registrator / O'quv bo'limi / Dekan (read-only)
 */
class RetakeJournalController extends Controller
{
    public function __construct(
        private RetakeJournalService $service,
    ) {}

    /**
     * Guruhlar ro'yxati — joriy aktorga ko'rinadigan retake guruhlari.
     */
    public function index(Request $request)
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        $isTeacher = $actor instanceof Teacher;

        // Cascade filtrlar (talaba ma'lumotlari + fan)
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

        $query = RetakeGroup::query()
            ->with('teacher')
            ->withCount('applications as students_count')
            ->orderByDesc('start_date');

        // O'qituvchi faqat o'zining guruhlarini ko'radi (admin emas)
        if ($isTeacher && !$isAdmin) {
            $query->where('teacher_id', $actor->id);
        }

        // Fan bo'yicha filtr
        if ($subjectFilter) {
            $query->where('subject_id', $subjectFilter);
        }

        // Talaba ma'lumotlari bo'yicha — guruhdagi arizalardan hech bo'lmaganda
        // bittasi tanlangan talaba shartiga mos kelishi kerak
        $hasStudentFilter = collect($studentFilters)->filter(fn ($v) => filled($v))->isNotEmpty();
        if ($hasStudentFilter) {
            $query->whereHas('applications', function ($q) use ($studentFilters) {
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

        $groups = $query->paginate($perPage)->withQueryString();

        $educationTypes = \App\Services\Retake\RetakeFilterCache::educationTypes();
        $subjects = \App\Services\Retake\RetakeFilterCache::subjects();

        return view('teacher.retake-journal.index', [
            'groups' => $groups,
            'educationTypes' => $educationTypes,
            'subjects' => $subjects,
        ]);
    }

    /**
     * Bitta guruh jurnali.
     */
    public function show(int $groupId)
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::with(['teacher'])->findOrFail($groupId);

        $this->authorizeView($actor, $group);

        $applications = $this->service->applications($group);
        $dates = $this->service->lessonDates($group);
        $gradesMap = $this->service->gradesMap($group);
        $mustaqilMap = $this->service->mustaqilMap($group);

        // URINISH ("nechinchi marta") — har talaba uchun shu fan bo'yicha tasdiqlangan
        // arizalar tartibi. Joriy ariza nechinchi navbatdaligini hisoblaymiz.
        $attemptsMap = [];
        foreach ($applications as $app) {
            $allIds = \App\Models\RetakeApplication::query()
                ->where('student_hemis_id', $app->student_hemis_id)
                ->where('subject_id', $app->subject_id)
                ->where('final_status', \App\Models\RetakeApplication::STATUS_APPROVED)
                ->orderBy('created_at')
                ->pluck('id')
                ->toArray();
            $idx = array_search($app->id, $allIds);
            $attemptsMap[$app->id] = $idx !== false ? $idx + 1 : 1;
        }

        // Filtr panel uchun fakultet/yo'nalish/guruh ko'rsatkichlari (talabalar bo'yicha unikal)
        $studentInfo = $applications
            ->map(fn ($a) => $a->group->student ?? null)
            ->filter();

        $facultyNames = $studentInfo->pluck('department_name')->filter()->unique()->values();
        $specialtyNames = $studentInfo->pluck('specialty_name')->filter()->unique()->values();
        $levelNames = $studentInfo->pluck('level_name')->filter()->unique()->values();
        $semesterNames = $studentInfo->pluck('semester_name')->filter()->unique()->values();
        $groupNames = $studentInfo->pluck('group_name')->filter()->unique()->values();

        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        $canEdit = $isAdmin
            || ($actor instanceof Teacher && $this->service->isAssignedTeacher($group, $actor) && $this->service->isEditable($group));

        return view('teacher.retake-journal.show', [
            'group' => $group,
            'applications' => $applications,
            'dates' => $dates,
            'gradesMap' => $gradesMap,
            'mustaqilMap' => $mustaqilMap,
            'attemptsMap' => $attemptsMap,
            'facultyNames' => $facultyNames,
            'specialtyNames' => $specialtyNames,
            'levelNames' => $levelNames,
            'semesterNames' => $semesterNames,
            'groupNames' => $groupNames,
            'canEdit' => $canEdit,
            'isEditable' => $this->service->isEditable($group),
        ]);
    }

    /**
     * Mustaqil ta'limni baholash (o'qituvchi/admin).
     */
    public function gradeMustaqil(Request $request, int $groupId): JsonResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi'], 403);
        }

        $data = $request->validate([
            'application_id' => 'required|integer',
            'grade' => 'nullable|numeric|min:0|max:100',
            'comment' => 'nullable|string|max:1000',
        ]);

        $group = RetakeGroup::findOrFail($groupId);
        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);

        if (!$isAdmin) {
            if (!$actor instanceof Teacher || !$this->service->isAssignedTeacher($group, $actor)) {
                return response()->json(['success' => false, 'message' => 'Siz bu guruhga biriktirilmagansiz'], 403);
            }
        }

        try {
            $submission = $this->service->gradeMustaqil(
                $group,
                (int) $data['application_id'],
                $data['grade'] !== null && $data['grade'] !== '' ? (float) $data['grade'] : null,
                $data['comment'] ?? null,
                $actor instanceof Teacher ? $actor : new Teacher(['id' => $actor->id, 'full_name' => $actor->name ?? 'admin']),
                $isAdmin,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'grade' => $submission->grade,
            'comment' => $submission->teacher_comment,
            'graded_by_name' => $submission->graded_by_name,
            'graded_at' => optional($submission->graded_at)->format('Y-m-d H:i'),
        ]);
    }

    /**
     * Guruhni yopish (yakuniy yuborish) — kunlik baholar dastlabki yakuniy
     * sifatida saqlanadi va keyin tahrirlash mumkin emas.
     */
    public function lock(int $groupId): RedirectResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::findOrFail($groupId);
        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        if (!$isAdmin && (!$actor instanceof Teacher || !$this->service->isAssignedTeacher($group, $actor))) {
            abort(403);
        }

        try {
            $this->service->lockGroup($group, $actor instanceof Teacher ? $actor : new Teacher(['id' => $actor->id, 'full_name' => $actor->name ?? 'admin']));
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __('Guruh yopildi va yakuniy baholar shakllantirildi'));
    }

    /**
     * Lock'ni bekor qilish (faqat super-admin).
     */
    public function unlock(int $groupId): RedirectResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor || !$actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value])) {
            abort(403);
        }
        $group = RetakeGroup::findOrFail($groupId);
        $this->service->unlockGroup($group);
        return redirect()->back()->with('success', __('Guruh tahrirlash uchun ochildi'));
    }

    /**
     * Vedomost PDF generatsiya qilish va yuklab olish.
     */
    public function vedomost(Request $request, int $groupId)
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);
        $this->authorizeView($actor, $group);

        $request->validate([
            'weight_jn'   => 'required|integer|min:0|max:100',
            'weight_mt'   => 'required|integer|min:0|max:100',
            'weight_on'   => 'nullable|integer|min:0|max:100',
            'weight_oski' => 'nullable|integer|min:0|max:100',
            'weight_test' => 'nullable|integer|min:0|max:100',
        ]);

        $weights = [
            'jn'   => (int) $request->input('weight_jn'),
            'mt'   => (int) $request->input('weight_mt'),
            'on'   => (int) ($request->input('weight_on') ?? 0),
            'oski' => (int) ($request->input('weight_oski') ?? 0),
            'test' => (int) ($request->input('weight_test') ?? 0),
        ];

        if (array_sum($weights) !== 100) {
            return response()->json(['error' => "Vaznlar jami 100 bo'lishi kerak"], 422);
        }

        try {
            $built = $this->service->buildVedomostExcel($group, $weights);
        } catch (ValidationException $e) {
            return response()->json(['error' => collect($e->errors())->flatten()->first()], 422);
        }

        if ($group->vedomost_path !== $built['relPath']) {
            $group->update([
                'vedomost_path' => $built['relPath'],
                'vedomost_generated_at' => now(),
            ]);
        }

        return response()->download($built['path'], $built['filename'])
            ->deleteFileAfterSend(false);
    }

    /**
     * HEMIS'dan OSKE va Test natijalarini tortish (student_grades 101/102).
     * Mavjud Admin/JournalController::fetchYnResults logikasiga moslashtirilgan.
     */
    public function fetchResults(int $groupId): RedirectResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::findOrFail($groupId);
        $this->authorizeView($actor, $group);

        if (!in_array($group->assessment_type, ['oske', 'test', 'oske_test'], true)) {
            return redirect()->back()->withErrors([
                'assessment_type' => "Bu guruh uchun OSKE/Test natijalari kerak emas",
            ]);
        }

        $result = $this->service->fetchOskeTestResults($group);

        $parts = [];
        if ($result['fetched_oske'] > 0) $parts[] = "OSKE: {$result['fetched_oske']}";
        if ($result['fetched_test'] > 0) $parts[] = "Test: {$result['fetched_test']}";

        if (empty($parts)) {
            return redirect()->back()->with('error', "HEMIS'da hali natijalar yo'q. Keyinroq urinib ko'ring.");
        }

        $msg = "Natijalar tortildi — " . implode(', ', $parts) . " ta yangilandi";
        if ($result['missing'] > 0) {
            $msg .= ", {$result['missing']} ta talaba uchun natija topilmadi";
        }
        return redirect()->back()->with('success', $msg);
    }

    /**
     * Test markaziga yuborish (yopilgan guruhlar uchun, vedomost saqlangan).
     */
    public function sendToTestMarkazi(int $groupId): RedirectResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::findOrFail($groupId);
        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        if (!$isAdmin && (!$actor instanceof Teacher || !$this->service->isAssignedTeacher($group, $actor))) {
            abort(403);
        }

        try {
            $this->service->sendToTestMarkazi($group, $actor instanceof Teacher ? $actor : new Teacher(['id' => $actor->id]));
        } catch (ValidationException $e) {
            return redirect()->back()->withErrors($e->errors());
        }

        return redirect()->back()->with('success', __('Vedomost test markaziga yuborildi'));
    }

    /**
     * Mustaqil ta'lim faylini yuklab olish.
     */
    public function downloadMustaqil(int $groupId, int $submissionId)
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) abort(403);

        $group = RetakeGroup::findOrFail($groupId);
        $this->authorizeView($actor, $group);

        $submission = \App\Models\RetakeMustaqilSubmission::query()
            ->where('id', $submissionId)
            ->where('retake_group_id', $group->id)
            ->firstOrFail();

        if (!$submission->file_path || !\Illuminate\Support\Facades\Storage::disk('public')->exists($submission->file_path)) {
            abort(404, 'Fayl topilmadi');
        }

        return \Illuminate\Support\Facades\Storage::disk('public')->download(
            $submission->file_path,
            $submission->original_filename ?: basename($submission->file_path)
        );
    }

    /**
     * Bitta katakni saqlash (AJAX).
     */
    /**
     * Joriy nazorat (JN) bahosini saqlash — har talaba uchun bitta yagona baho.
     */
    public function saveJoriy(Request $request, int $groupId): JsonResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi'], 403);
        }

        $data = $request->validate([
            'application_id' => 'required|integer',
            'score' => 'nullable|numeric|min:0|max:100',
        ]);

        $group = RetakeGroup::findOrFail($groupId);
        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);

        if (!$isAdmin) {
            if (!$actor instanceof Teacher || !$this->service->isAssignedTeacher($group, $actor)) {
                return response()->json(['success' => false, 'message' => 'Siz bu guruhga biriktirilmagansiz'], 403);
            }
        }

        try {
            $app = $this->service->saveJoriyScore(
                $group,
                (int) $data['application_id'],
                $data['score'] !== null && $data['score'] !== '' ? (float) $data['score'] : null,
                $actor instanceof Teacher ? $actor : new Teacher(['id' => 0, 'full_name' => $actor->name ?? 'admin']),
                $isAdmin,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'score' => $app->joriy_score,
            'graded_by_name' => $app->joriy_graded_by_name,
            'graded_at' => optional($app->joriy_graded_at)->format('Y-m-d H:i'),
        ]);
    }

    public function saveGrade(Request $request, int $groupId): JsonResponse
    {
        $actor = RetakeAccess::currentStaff();
        if (!$actor) {
            return response()->json(['success' => false, 'message' => 'Avtorizatsiya talab qilinadi'], 403);
        }

        $data = $request->validate([
            'application_id' => 'required|integer',
            'lesson_date' => 'required|date',
            'grade' => 'nullable|numeric|min:0|max:100',
            'comment' => 'nullable|string|max:500',
        ]);

        $group = RetakeGroup::findOrFail($groupId);

        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);

        // Tahrirlash huquqi — assigned teacher yoki admin
        if (!$isAdmin) {
            if (!$actor instanceof Teacher || !$this->service->isAssignedTeacher($group, $actor)) {
                return response()->json(['success' => false, 'message' => 'Siz bu guruhga biriktirilmagansiz'], 403);
            }
        }

        try {
            $row = $this->service->saveGrade(
                $group,
                (int) $data['application_id'],
                $data['lesson_date'],
                $data['grade'] !== null && $data['grade'] !== '' ? (float) $data['grade'] : null,
                $data['comment'] ?? null,
                $actor instanceof Teacher ? $actor : Teacher::find(0) ?? new Teacher(['id' => 0, 'full_name' => $actor->name ?? 'admin']),
                $isAdmin,
            );
        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'message' => collect($e->errors())->flatten()->first(),
                'errors' => $e->errors(),
            ], 422);
        }

        return response()->json([
            'success' => true,
            'grade' => $row->grade,
            'comment' => $row->comment,
            'graded_by_name' => $row->graded_by_name,
            'graded_at' => optional($row->graded_at)->format('Y-m-d H:i'),
        ]);
    }

    private function authorizeView($actor, RetakeGroup $group): void
    {
        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        if ($isAdmin) return;

        // Guruh o'qituvchisi
        if ($actor instanceof Teacher && (int) $group->teacher_id === (int) $actor->id) {
            return;
        }

        // Registrator/O'quv bo'limi/Dekan — read-only ko'radi
        $allowedRoles = [
            ProjectRole::REGISTRAR_OFFICE->value,
            ProjectRole::ACADEMIC_DEPARTMENT->value,
            ProjectRole::ACADEMIC_DEPARTMENT_HEAD->value,
            ProjectRole::DEAN->value,
        ];
        if ($actor->hasAnyRole($allowedRoles)) {
            return;
        }

        abort(403, 'Sizda bu jurnalni ko\'rish ruxsati yo\'q');
    }
}
