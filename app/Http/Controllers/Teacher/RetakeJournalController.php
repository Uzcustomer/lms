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

        $isAdmin = $actor->hasAnyRole([ProjectRole::SUPERADMIN->value, ProjectRole::ADMIN->value]);
        $canEdit = $isAdmin
            || ($actor instanceof Teacher && $this->service->isAssignedTeacher($group, $actor) && $this->service->isEditable($group));

        return view('teacher.retake-journal.show', [
            'group' => $group,
            'applications' => $applications,
            'dates' => $dates,
            'gradesMap' => $gradesMap,
            'canEdit' => $canEdit,
            'isEditable' => $this->service->isEditable($group),
        ]);
    }

    /**
     * Bitta katakni saqlash (AJAX).
     */
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
