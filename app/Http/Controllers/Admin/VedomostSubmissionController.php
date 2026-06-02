<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\VedomostSubmission;
use App\Services\VedomostSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class VedomostSubmissionController extends Controller
{
    private const ALLOWED_ROLES = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];

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

    public function index(Request $request)
    {
        $this->checkAccess();

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

        $query = VedomostSubmission::query();

        if ($request->filled('faculty')) {
            // vedomost_submissions.department_hemis_id — kafedra. Fakultet bo'yicha
            // filtrlash uchun guruhning fakulteti (groups.department_hemis_id) orqali bog'laymiz.
            $facultyHemisId = Department::where('id', $request->faculty)->value('department_hemis_id');
            $query->whereIn('group_hemis_id', function ($sub) use ($facultyHemisId) {
                $sub->select('group_hemis_id')
                    ->from('groups')
                    ->where('department_hemis_id', $facultyHemisId);
            });
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        if ($request->filled('closing_form')) {
            $query->where('closing_form', $request->closing_form);
        }
        if ($request->filled('search')) {
            $s = '%' . $request->search . '%';
            $query->where(function ($q) use ($s) {
                $q->where('subject_name', 'like', $s)
                    ->orWhere('group_name', 'like', $s)
                    ->orWhere('teacher_name', 'like', $s);
            });
        }
        if ($request->boolean('overdue')) {
            $query->whereNotNull('deadline')
                ->whereDate('deadline', '<', now()->toDateString())
                ->whereNotIn('status', [VedomostSubmission::STATUS_APPROVED]);
        }

        // Status bo'yicha umumiy statistika (filtrlardan mustaqil)
        $stats = VedomostSubmission::select('status', DB::raw('COUNT(*) as cnt'))
            ->groupBy('status')
            ->pluck('cnt', 'status')
            ->all();

        $perPage = (int) $request->get('per_page', 50);
        $submissions = $query
            ->orderByRaw('deadline IS NULL')
            ->orderBy('deadline')
            ->orderBy('group_name')
            ->orderBy('subject_name')
            ->paginate($perPage)
            ->appends($request->query());

        return view('admin.vedomost-submission.index', compact(
            'submissions',
            'faculties',
            'closingForms',
            'stats'
        ));
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
