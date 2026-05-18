<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\ComputerAssignment;
use App\Models\DeanExamReschedule;
use App\Models\Group;
use App\Services\DeanExamRescheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dekanat — kech qolgan talabani SHU KUN ichida boshqa vaqtga
 * o'tkazish sahifasi va endpointlari.
 *
 * Test markazi rolida "edit today" toggle o'chiq bo'lganda ham
 * dekanatga alohida ruxsat (har talabaga kunlik 1 marta).
 */
class DeanExamRescheduleController extends Controller
{
    private const ALLOWED_ROLES = [
        'superadmin',
        'admin',
        'kichik_admin',
        'dekan',
    ];

    public function index(Request $request, DeanExamRescheduleService $service)
    {
        $this->ensureAccess();

        $date = $this->resolveDate($request);
        $assignments = $this->todaysAssignments($date);
        $usedHemisIds = DeanExamReschedule::whereDate('used_date', $date->toDateString())
            ->pluck('student_hemis_id')
            ->all();

        $availableSlots = $service->availableSlots($date->toDateString(), now());

        return view('admin.dean-exam-reschedule.index', [
            'date' => $date->toDateString(),
            'assignments' => $assignments,
            'usedHemisIds' => array_flip($usedHemisIds),
            'availableSlots' => $availableSlots,
        ]);
    }

    public function availableSlots(Request $request, DeanExamRescheduleService $service): JsonResponse
    {
        $this->ensureAccess();

        $date = $this->resolveDate($request);
        $slots = $service->availableSlots($date->toDateString(), now());

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots,
        ]);
    }

    public function store(Request $request, DeanExamRescheduleService $service): JsonResponse
    {
        $this->ensureAccess();

        $data = $request->validate([
            'computer_assignment_id' => ['required', 'integer', 'exists:computer_assignments,id'],
            'new_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $assignment = ComputerAssignment::findOrFail($data['computer_assignment_id']);

        // Faqat dekanat o'z fakulteti talabasini ko'chira oladi
        if (!$this->canTouchAssignment($assignment)) {
            return response()->json([
                'ok' => false,
                'error' => 'Bu talaba sizning fakultetingizga tegishli emas.',
            ], 403);
        }

        // Faqat bugungi imtihonni ko'chirish
        $date = $assignment->planned_start?->format('Y-m-d');
        if ($date !== Carbon::today()->toDateString()) {
            return response()->json([
                'ok' => false,
                'error' => 'Faqat bugungi imtihonni boshqa vaqtga ko\'chirish mumkin.',
            ], 422);
        }

        $user = $this->currentUser();
        $result = $service->reschedule(
            (int) $user->id,
            $assignment,
            $data['new_time'],
            $data['reason'] ?? null,
        );

        $status = $result['ok'] ? 200 : 422;

        return response()->json($result, $status);
    }

    private function ensureAccess(): void
    {
        $role = $this->activeRole();
        if (!in_array($role, self::ALLOWED_ROLES, true)) {
            abort(403, 'Bu sahifa sizning rolingiz uchun ochiq emas.');
        }
    }

    private function activeRole(): ?string
    {
        $user = $this->currentUser();
        if (!$user) {
            return null;
        }
        $roles = $user->getRoleNames()->toArray();
        $active = session('active_role', $roles[0] ?? null);
        if (!in_array($active, $roles, true) && !empty($roles)) {
            $active = $roles[0];
        }
        return $active;
    }

    private function currentUser()
    {
        return auth()->user() ?? auth('teacher')->user();
    }

    private function resolveDate(Request $request): Carbon
    {
        try {
            if ($request->filled('date')) {
                return Carbon::parse($request->input('date'))->startOfDay();
            }
        } catch (\Throwable) {
            // pastga tushadi
        }
        return Carbon::today();
    }

    /**
     * Dekanatning fakultetlariga (yoki admin uchun barchasiga) tegishli
     * berilgan kunning ComputerAssignment ro'yxati.
     */
    private function todaysAssignments(Carbon $date)
    {
        $query = ComputerAssignment::query()
            ->with(['student:hemis_id,full_name,group_id,student_id_number', 'examSchedule:id,subject_name,group_hemis_id'])
            ->whereDate('planned_start', $date->toDateString())
            ->whereIn('status', [
                ComputerAssignment::STATUS_SCHEDULED,
                ComputerAssignment::STATUS_ABANDONED,
                ComputerAssignment::STATUS_IN_PROGRESS,
            ]);

        $groupIds = $this->scopedGroupHemisIds();
        if ($groupIds !== null) {
            // Talabani fakultet bo'yicha filter qilish — Student.group_id orqali
            // (group_id aslida group_hemis_id ni saqlaydi, qarang Student model)
            $query->whereHas('student', function ($q) use ($groupIds) {
                $q->whereIn('group_id', $groupIds);
            });
        }

        return $query->orderBy('planned_start')->get();
    }

    private function canTouchAssignment(ComputerAssignment $assignment): bool
    {
        $groupIds = $this->scopedGroupHemisIds();
        if ($groupIds === null) {
            return true; // admin
        }
        $student = $assignment->student;
        if (!$student) {
            return false;
        }
        return in_array($student->group_id, $groupIds, true);
    }

    /**
     * Dekanat ko'ra oladigan guruh hemis_id lari, yoki null (admin — barchasi).
     */
    private function scopedGroupHemisIds(): ?array
    {
        $role = $this->activeRole();

        if (in_array($role, ['admin', 'superadmin', 'kichik_admin'], true)) {
            return null;
        }

        if ($role !== ProjectRole::DEAN->value) {
            return [];
        }

        $user = $this->currentUser();
        if (!$user || !method_exists($user, 'deanFaculties')) {
            return [];
        }

        $facultyHemisIds = $user->deanFaculties()
            ->pluck('departments.department_hemis_id')
            ->filter()
            ->unique()
            ->values()
            ->all();

        if (empty($facultyHemisIds)) {
            return [];
        }

        return Group::whereIn('department_hemis_id', $facultyHemisIds)
            ->whereNotNull('group_hemis_id')
            ->pluck('group_hemis_id')
            ->unique()
            ->values()
            ->all();
    }
}
