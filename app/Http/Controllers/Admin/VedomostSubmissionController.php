<?php

namespace App\Http\Controllers\Admin;

use App\Exports\VedomostSubmissionExport;
use App\Http\Controllers\Controller;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\VedomostSubmission;
use App\Services\VedomostSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VedomostSubmissionController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

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

    public function __construct(private VedomostSubmissionService $service)
    {
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
            $query->where('c.specialty_hemis_id', $request->specialty);
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

        return view('admin.vedomost-submission.index', compact(
            'submissions',
            'faculties',
            'educationTypes',
            'selectedEducationType',
            'closingForms',
            'stats'
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
}
