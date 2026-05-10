<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Exports\GroupTestScheduleExport;
use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\Group;
use App\Services\AutoAssignService;
use App\Services\ExamCapacityService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Group-level "guruh testga qachon kiradi" view.
 *
 * Allows tutors, deans and the registrar office to see, per role scope:
 *   tyutor          → only their own groups (group_teacher pivot)
 *   dekan           → groups in their faculties (Teacher::deanFaculties)
 *   registrator_ofisi → all groups in the institution
 *
 * Data is read from exam_schedules (test_date / test_time + the two
 * resit attempts). One row per (group, subject, attempt). Excel export
 * uses the same query and filters as the on-screen table.
 */
class GroupTestScheduleController extends Controller
{
    private const ALLOWED_ROLES = [
        'superadmin',
        'admin',
        'kichik_admin',
        'tyutor',
        'dekan',
        'registrator_ofisi',
    ];

    /**
     * Roles that may trigger AutoAssignService::distribute() from this page.
     * Tyutor and dekan can only view; only the registrar office and admins
     * can rewrite the official test_time on a schedule.
     */
    private const AUTO_TIME_ROLES = [
        'superadmin',
        'admin',
        'kichik_admin',
        'registrator_ofisi',
    ];

    public function index(Request $request)
    {
        $this->ensureAccess();

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->buildRows($dateFrom, $dateTo);

        return view('admin.group-test-schedule.index', [
            'rows'        => $rows,
            'dateFrom'    => $dateFrom->toDateString(),
            'dateTo'      => $dateTo->toDateString(),
            'scopeLabel'  => $this->scopeLabel(),
            'canAutoTime' => $this->canTriggerAutoTime(),
        ]);
    }

    public function export(Request $request)
    {
        $this->ensureAccess();

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $rows = $this->buildRows($dateFrom, $dateTo);

        $fileName = sprintf(
            'guruh_test_jadvali_%s_%s.xlsx',
            $dateFrom->format('Y_m_d'),
            $dateTo->format('Y_m_d')
        );

        return Excel::download(
            new GroupTestScheduleExport($rows, $dateFrom, $dateTo, $this->scopeLabel()),
            $fileName
        );
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

    private function resolveDateRange(Request $request): array
    {
        $today = Carbon::today();
        try {
            $from = $request->filled('date_from')
                ? Carbon::parse($request->input('date_from'))->startOfDay()
                : $today->copy();
        } catch (\Throwable $e) {
            $from = $today->copy();
        }
        try {
            $to = $request->filled('date_to')
                ? Carbon::parse($request->input('date_to'))->startOfDay()
                : $from->copy();
        } catch (\Throwable $e) {
            $to = $from->copy();
        }
        if ($to->lt($from)) {
            $to = $from->copy();
        }
        return [$from, $to];
    }

    /**
     * Build the flat list of (group × subject × attempt × date/time) rows
     * scoped to the current user's role.
     *
     * Returns Collection of stdClass with fields:
     *   test_date, test_time, attempt, group_name, group_hemis_id,
     *   subject_id, subject_name, faculty_name, specialty_name,
     *   student_count, semester_code.
     */
    private function buildRows(Carbon $dateFrom, Carbon $dateTo)
    {
        $allowedGroupHemisIds = $this->scopedGroupHemisIds();
        if ($allowedGroupHemisIds !== null && empty($allowedGroupHemisIds)) {
            return collect();
        }

        // Each exam_schedules row carries up to three test attempts.
        // Emit one logical row per attempt that has a date in range.
        $cols = [
            'es.id',
            'es.group_hemis_id',
            'es.subject_id',
            'es.subject_name',
            'es.semester_code',
            'es.test_na',
            'es.test_date',
            'es.test_time',
            'es.test_resit_date',
            'es.test_resit_time',
            'es.test_resit2_date',
            'es.test_resit2_time',
            'g.name as group_name',
            'g.specialty_name',
            'g.department_hemis_id as group_department_hemis_id',
            'g.department_name as group_department_name',
            'g.specialty_hemis_id',
        ];

        $base = DB::table('exam_schedules as es')
            ->leftJoin('groups as g', 'g.group_hemis_id', '=', 'es.group_hemis_id')
            ->where(function ($q) use ($dateFrom, $dateTo) {
                $q->whereBetween('es.test_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->orWhereBetween('es.test_resit_date', [$dateFrom->toDateString(), $dateTo->toDateString()])
                    ->orWhereBetween('es.test_resit2_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
            })
            ->where(function ($q) {
                $q->where('es.test_na', false)->orWhereNull('es.test_na');
            });

        if ($allowedGroupHemisIds !== null) {
            $base->whereIn('es.group_hemis_id', $allowedGroupHemisIds);
        }

        $records = $base->select($cols)->get();

        // Faculty (structure_type_code = 11) lookup so dekan/registrator can
        // see "qaysi fakultet" alongside the kafedra/yo'nalish.
        $facultyByGroupDept = DB::table('groups as g')
            ->leftJoin('departments as d', 'd.department_hemis_id', '=', 'g.department_hemis_id')
            ->whereNotNull('g.department_hemis_id')
            ->select('g.department_hemis_id', 'd.name as faculty_name')
            ->groupBy('g.department_hemis_id', 'd.name')
            ->pluck('faculty_name', 'g.department_hemis_id');

        // Student count per group. students.group_id stores the
        // group_hemis_id (legacy column name from the HEMIS sync).
        $studentCounts = DB::table('students')
            ->select('group_id', DB::raw('COUNT(*) as cnt'))
            ->groupBy('group_id')
            ->pluck('cnt', 'group_id');

        $rows = collect();
        $fromStr = $dateFrom->toDateString();
        $toStr = $dateTo->toDateString();

        foreach ($records as $r) {
            $attempts = [
                ['1-urinish',         $r->test_date,         $r->test_time],
                ['Qayta (2-urinish)', $r->test_resit_date,   $r->test_resit_time],
                ['Qayta (3-urinish)', $r->test_resit2_date,  $r->test_resit2_time],
            ];

            foreach ($attempts as [$label, $date, $time]) {
                if (!$date) {
                    continue;
                }
                $dateOnly = substr((string) $date, 0, 10);
                if ($dateOnly < $fromStr || $dateOnly > $toStr) {
                    continue;
                }

                $rows->push((object) [
                    'exam_schedule_id' => (int) $r->id,
                    'is_first_attempt' => $label === '1-urinish',
                    'test_date'      => $dateOnly,
                    'test_time'      => $time ? substr((string) $time, 0, 5) : null,
                    'attempt'        => $label,
                    'group_name'     => $r->group_name ?? '—',
                    'group_hemis_id' => $r->group_hemis_id,
                    'subject_id'     => $r->subject_id,
                    'subject_name'   => $r->subject_name,
                    'faculty_name'   => $facultyByGroupDept[$r->group_department_hemis_id] ?? null,
                    'kafedra_name'   => $r->group_department_name,
                    'specialty_name' => $r->specialty_name,
                    'student_count'  => (int) ($studentCounts[$r->group_hemis_id] ?? 0),
                    'semester_code'  => $r->semester_code,
                ]);
            }
        }

        return $rows
            ->sortBy([
                ['test_date', 'asc'],
                ['test_time', 'asc'],
                ['group_name', 'asc'],
            ])
            ->values();
    }

    /**
     * Return list of group_hemis_id values the current user is allowed to
     * see, or null if no scoping applies (registrator/admin).
     */
    private function scopedGroupHemisIds(): ?array
    {
        $role = $this->activeRole();

        if (in_array($role, ['registrator_ofisi', 'admin', 'superadmin', 'kichik_admin'], true)) {
            return null;
        }

        $user = $this->currentUser();
        if (!$user) {
            return [];
        }

        if ($role === ProjectRole::TUTOR->value) {
            if (!method_exists($user, 'groups')) {
                return [];
            }
            return $user->groups()
                ->whereNotNull('group_hemis_id')
                ->pluck('group_hemis_id')
                ->unique()
                ->values()
                ->all();
        }

        if ($role === ProjectRole::DEAN->value) {
            if (!method_exists($user, 'deanFaculties')) {
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

        return [];
    }

    private function scopeLabel(): string
    {
        $role = $this->activeRole();
        return match ($role) {
            ProjectRole::TUTOR->value           => "Tyutor: o'z guruhlari",
            ProjectRole::DEAN->value            => 'Dekanat: fakultet guruhlari',
            ProjectRole::REGISTRAR_OFFICE->value => 'Registrator ofisi: barcha guruhlar',
            default => 'Barcha guruhlar',
        };
    }

    private function canTriggerAutoTime(): bool
    {
        return in_array($this->activeRole(), self::AUTO_TIME_ROLES, true);
    }

    /**
     * Run AutoAssignService::distribute() for one or many ExamSchedule rows
     * whose test_date is set but test_time is empty. The same logic that
     * AutoDistributeOnDateSetJob runs in the background — exposed here as
     * a manual trigger so registrar staff can fix records that never had
     * the job dispatched (e.g. dates set before that feature shipped, or
     * a queue worker outage).
     *
     * Single-row form: POST { exam_schedule_id }.
     * Bulk form (no id): processes every test_time-less schedule whose
     * test_date falls inside the page's current date range.
     */
    public function autoTime(Request $request, AutoAssignService $service)
    {
        $this->ensureAccess();
        if (!$this->canTriggerAutoTime()) {
            abort(403, "Avto-vaqt belgilash sizning rolingiz uchun ochiq emas.");
        }

        [$dateFrom, $dateTo] = $this->resolveDateRange($request);
        $singleId = $request->input('exam_schedule_id');

        $query = ExamSchedule::query()
            ->whereNotNull('test_date')
            ->whereNull('test_time')
            ->where(function ($q) {
                $q->where('test_na', false)->orWhereNull('test_na');
            });

        if ($singleId) {
            $query->where('id', (int) $singleId);
        } else {
            $query->whereBetween('test_date', [$dateFrom->toDateString(), $dateTo->toDateString()]);
        }

        $schedules = $query->get();
        if ($schedules->isEmpty()) {
            return redirect()
                ->route('admin.group-test-schedule.index', [
                    'date_from' => $dateFrom->toDateString(),
                    'date_to'   => $dateTo->toDateString(),
                ])
                ->with('warning', "Vaqt belgilashga muhtoj yozuv topilmadi.");
        }

        $okCount = 0;
        $failures = [];

        foreach ($schedules as $schedule) {
            $dateStr = $schedule->test_date instanceof Carbon
                ? $schedule->test_date->format('Y-m-d')
                : (string) $schedule->test_date;

            try {
                $capacity = ExamCapacityService::getSettingsForDate($dateStr);
                $startTime = $capacity['work_hours_start'] ?? '09:00';
                $result = $service->distribute($schedule, 'test', $startTime);
            } catch (\Throwable $e) {
                Log::warning('GroupTestSchedule autoTime: distribute threw', [
                    'schedule_id' => $schedule->id,
                    'message'     => $e->getMessage(),
                ]);
                $result = ['ok' => false, 'reason' => $e->getMessage()];
            }

            if (!empty($result['ok'])) {
                $okCount++;
            } else {
                $failures[] = sprintf(
                    '#%d (%s): %s',
                    $schedule->id,
                    $schedule->subject_name ?: $schedule->subject_id,
                    $result['reason'] ?? 'noma\'lum sabab'
                );
            }
        }

        $msg = "Avto-vaqt belgilandi: {$okCount} ta.";
        if (!empty($failures)) {
            $shown = array_slice($failures, 0, 5);
            $more = count($failures) - count($shown);
            $msg .= ' Bajarilmadi: ' . count($failures) . ' ta — ' . implode('; ', $shown);
            if ($more > 0) {
                $msg .= " (yana {$more} ta)";
            }
        }

        return redirect()
            ->route('admin.group-test-schedule.index', [
                'date_from' => $dateFrom->toDateString(),
                'date_to'   => $dateTo->toDateString(),
            ])
            ->with($okCount > 0 ? 'success' : 'error', $msg);
    }
}
