<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\DeanExamReschedule;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Models\Student;
use App\Services\DeanExamRescheduleService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Dekanat — kech qolgan guruhning YN vaqtini SHU KUN ichida boshqa vaqtga
 * o'tkazish sahifasi va endpointlari (guruh sathida — butun guruh birga
 * ko'chiriladi).
 *
 * Slot ro'yxati — barcha ish vaqtlari (tushlikdan tashqari). Sig'im
 * yetmasa slot ogohlantirish bilan ko'rsatiladi, dekanat ataylab tanlashi
 * mumkin (force=true).
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
        $rows = $this->todaysGroupExams($date);

        $usedKeys = DeanExamReschedule::whereDate('used_date', $date->toDateString())
            ->get(['exam_schedule_id', 'yn_type'])
            ->map(fn ($r) => $r->exam_schedule_id . '|' . $r->yn_type)
            ->all();
        $usedSet = array_flip($usedKeys);

        $availableSlots = $service->availableSlots($date->toDateString(), now());

        return view('admin.dean-exam-reschedule.index', [
            'date' => $date->toDateString(),
            'rows' => $rows,
            'usedSet' => $usedSet,
            'availableSlots' => $availableSlots,
        ]);
    }

    public function availableSlots(Request $request, DeanExamRescheduleService $service): JsonResponse
    {
        $this->ensureAccess();

        $date = $this->resolveDate($request);
        $requiredFree = max(1, (int) $request->input('required_free', 1));

        // Guruhning o'z hissasini istisno qilish — yangi vaqtda o'ziga
        // qarshi sanalmasin.
        $exclude = null;
        $scheduleId = $request->input('exam_schedule_id');
        $ynType = strtolower((string) $request->input('yn_type', ''));
        if ($scheduleId && in_array($ynType, ['oski', 'test'], true)) {
            $schedule = ExamSchedule::find((int) $scheduleId);
            if ($schedule && $this->canTouchSchedule($schedule)) {
                $exclude = $service->excludeKeyFor($schedule, $ynType);
            }
        }

        $slots = $service->availableSlots($date->toDateString(), now(), $requiredFree, $exclude);

        return response()->json([
            'date' => $date->toDateString(),
            'slots' => $slots,
        ]);
    }

    public function store(Request $request, DeanExamRescheduleService $service): JsonResponse
    {
        $this->ensureAccess();

        $data = $request->validate([
            'exam_schedule_id' => ['required', 'integer', 'exists:exam_schedules,id'],
            'yn_type' => ['required', 'in:oski,test'],
            'new_time' => ['required', 'regex:/^\d{2}:\d{2}$/'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'force' => ['nullable', 'boolean'],
        ]);

        $schedule = ExamSchedule::findOrFail($data['exam_schedule_id']);

        if (!$this->canTouchSchedule($schedule)) {
            return response()->json([
                'ok' => false,
                'error' => 'Bu guruh sizning fakultetingizga tegishli emas.',
            ], 403);
        }

        $dateField = $data['yn_type'] . '_date';
        $scheduleDate = $schedule->{$dateField}?->format('Y-m-d');
        if ($scheduleDate !== Carbon::today()->toDateString()) {
            return response()->json([
                'ok' => false,
                'error' => 'Faqat bugungi imtihonni boshqa vaqtga ko\'chirish mumkin.',
            ], 422);
        }

        $user = $this->currentUser();
        $result = $service->reschedule(
            (int) $user->id,
            $schedule,
            $data['yn_type'],
            $data['new_time'],
            $data['reason'] ?? null,
            (bool) ($data['force'] ?? false),
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
     * Berilgan kun uchun dekanat fakulteti doirasidagi (yoki admin uchun
     * barchasi) ExamSchedule qatorlarini har bir YN turi bo'yicha alohida
     * satr sifatida qaytaradi.
     *
     * @return \Illuminate\Support\Collection<int, object>
     */
    private function todaysGroupExams(Carbon $date)
    {
        $dateStr = $date->toDateString();
        $groupIds = $this->scopedGroupHemisIds();

        $query = ExamSchedule::query()
            ->where(function ($q) use ($dateStr) {
                $q->where(function ($q2) use ($dateStr) {
                    $q2->whereDate('oski_date', $dateStr)
                        ->where('oski_na', false)
                        ->whereNotNull('oski_time');
                })->orWhere(function ($q2) use ($dateStr) {
                    $q2->whereDate('test_date', $dateStr)
                        ->where('test_na', false)
                        ->whereNotNull('test_time');
                });
            });

        if ($groupIds !== null) {
            $query->whereIn('group_hemis_id', $groupIds);
        }

        $schedules = $query->get();

        $allGroupIds = $schedules->pluck('group_hemis_id')->unique()->all();
        $groups = Group::whereIn('group_hemis_id', $allGroupIds)
            ->pluck('name', 'group_hemis_id');
        $studentCounts = Student::whereIn('group_id', $allGroupIds)
            ->whereNotNull('student_id_number')
            ->selectRaw('group_id, COUNT(*) as cnt')
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        $rows = collect();
        foreach ($schedules as $s) {
            foreach (['oski', 'test'] as $yn) {
                $dateField = $yn . '_date';
                $timeField = $yn . '_time';
                $naField = $yn . '_na';

                $rowDate = $s->{$dateField};
                if (!$rowDate || $s->{$naField} || empty($s->{$timeField})) {
                    continue;
                }
                if ($rowDate->format('Y-m-d') !== $dateStr) {
                    continue;
                }

                $rows->push((object) [
                    'exam_schedule_id' => $s->id,
                    'yn_type' => $yn,
                    'current_time' => substr((string) $s->{$timeField}, 0, 5),
                    'group_hemis_id' => $s->group_hemis_id,
                    'group_name' => $groups[$s->group_hemis_id] ?? '—',
                    'subject_name' => $s->subject_name,
                    'subject_id' => $s->subject_id,
                    'student_count' => (int) ($studentCounts[$s->group_hemis_id] ?? 0),
                ]);
            }
        }

        return $rows
            ->sortBy(['current_time', 'group_name'])
            ->values();
    }

    private function canTouchSchedule(ExamSchedule $schedule): bool
    {
        $groupIds = $this->scopedGroupHemisIds();
        if ($groupIds === null) {
            return true;
        }
        return in_array($schedule->group_hemis_id, $groupIds, true);
    }

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
