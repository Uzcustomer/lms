<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractList;
use App\Models\Curriculum;
use App\Models\Department;
use App\Services\HemisService;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(
        protected HemisService $hemisService,
        protected TelegramService $telegram
    ) {}

    public function index(): View
    {
        $faculties = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->get();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        // Education year qiymatlarini ContractList dan olish (filter mos kelishi uchun)
        $yearCodeToName = Curriculum::whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->pluck('education_year_name', 'education_year_code');

        $educationYears = ContractList::select('education_year as code')
            ->whereNotNull('education_year')
            ->groupBy('education_year')
            ->orderByDesc('education_year')
            ->get()
            ->map(fn($r) => (object)[
                'code' => $r->code,
                'name' => $yearCodeToName[$r->code] ?? $r->code,
            ]);

        $currentEducationYear = DB::table('semesters')
            ->where('current', true)
            ->value('education_year');

        // Agar contract_list da bu kod mavjud bo'lmasa, birinchi yilni tanlash
        $availableYearCodes = $educationYears->pluck('code')->toArray();
        if ($currentEducationYear && !in_array((string)$currentEducationYear, array_map('strval', $availableYearCodes))) {
            $currentEducationYear = $educationYears->first()?->code;
        }

        $contractTypes = ContractList::select('edu_contract_type_code', 'edu_contract_type_name')
            ->whereNotNull('edu_contract_type_code')
            ->groupBy('edu_contract_type_code', 'edu_contract_type_name')
            ->get();

        $eduForms = ContractList::select('edu_form')
            ->whereNotNull('edu_form')->where('edu_form', '!=', '')
            ->groupBy('edu_form')->orderBy('edu_form')->pluck('edu_form');

        $statuses = ContractList::select('status', 'status_id')
            ->whereNotNull('status')->where('status', '!=', '')
            ->groupBy('status', 'status_id')->orderBy('status')->get();

        $organizations = ContractList::select('edu_organization')
            ->whereNotNull('edu_organization')->where('edu_organization', '!=', '')
            ->groupBy('edu_organization')->orderBy('edu_organization')->pluck('edu_organization');

        $sumTypes = ContractList::select('edu_contract_sum_type_code', 'edu_contract_sum_type_name')
            ->whereNotNull('edu_contract_sum_type_code')
            ->groupBy('edu_contract_sum_type_code', 'edu_contract_sum_type_name')->get();

        return view('admin.contracts.index', compact(
            'faculties',
            'educationTypes',
            'educationYears',
            'currentEducationYear',
            'contractTypes',
            'eduForms',
            'statuses',
            'organizations',
            'sumTypes'
        ));
    }

    public function data(Request $request)
    {
        $totalInDb = ContractList::count();
        $query = ContractList::query();

        if ($request->filled('_student')) {
            $query->where('student_hemis_id', $request->input('_student'));
        }

        if ($request->filled('_education_year')) {
            $query->where('education_year', $request->input('_education_year'));
        }

        if ($request->filled('_education_type')) {
            $query->where('edu_type_code', $request->input('_education_type'));
        }

        if ($request->filled('_department')) {
            $faculty = Department::find($request->input('_department'));
            if ($faculty) {
                $query->where('faculty_code', $faculty->code);
            }
        }

        if ($request->filled('_specialty')) {
            $query->where('edu_speciality_code', $request->input('_specialty'));
        }

        if ($request->filled('_level')) {
            $query->where('edu_cours_id', $request->input('_level'));
        }

        if ($request->filled('_contract_type')) {
            $query->where('edu_contract_type_code', $request->input('_contract_type'));
        }

        if ($request->filled('_edu_form')) {
            $query->where('edu_form', $request->input('_edu_form'));
        }

        if ($request->filled('_status')) {
            $query->where('status', $request->input('_status'));
        }

        if ($request->filled('_organization')) {
            $query->where('edu_organization', $request->input('_organization'));
        }

        if ($request->filled('_sum_type')) {
            $query->where('edu_contract_sum_type_code', $request->input('_sum_type'));
        }

        if ($request->filled('search')) {
            $search = $request->input('search');
            $tokens = array_filter(explode(' ', trim($search)));
            $query->where(function ($q) use ($tokens, $search) {
                // Shartnoma raqami bo'yicha to'g'ridan-to'g'ri qidiruv
                $q->where('contract_number', 'like', "%{$search}%");
                // Ism-familiya bo'yicha — har bir so'z alohida tekshiriladi (istalgan tartibda)
                if (count($tokens) > 1) {
                    $q->orWhere(function ($inner) use ($tokens) {
                        foreach ($tokens as $token) {
                            $inner->where('full_name', 'like', "%{$token}%");
                        }
                    });
                } else {
                    $q->orWhere('full_name', 'like', "%{$search}%");
                }
            });
        }

        if ($request->filled('_group')) {
            $query->whereExists(function ($sub) use ($request) {
                $sub->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.hemis_id', 'contract_list.student_hemis_id')
                    ->where('students.group_name', $request->input('_group'));
            });
        }

        if ($request->filled('_current_semester')) {
            $query->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('students')
                    ->whereColumn('students.hemis_id', 'contract_list.student_hemis_id')
                    ->whereColumn('students.level_code', 'contract_list.edu_cours_id');
            });
        }

        if ($request->filled('_debt')) {
            $debt = $request->input('_debt');
            $thresholds = ['25' => 0.25, '50' => 0.50, '75' => 0.75, '100' => 1.00];
            if (isset($thresholds[$debt])) {
                $t = $thresholds[$debt];
                $query->where('edu_contract_sum', '>', 0)
                      ->whereRaw('paid_credit_amount / edu_contract_sum < ?', [$t]);
            }
        }

        $limit = (int) $request->input('limit', 50);
        $page = (int) $request->input('page', 1);

        $total = $query->count();
        $items = $query->select('contract_list.*', 'students.group_name', 'students.student_id_number')
            ->leftJoin('students', 'students.hemis_id', '=', 'contract_list.student_hemis_id')
            ->orderByDesc('contract_list.hemis_created_at')
            ->offset(($page - 1) * $limit)
            ->limit($limit)
            ->get();

        return response()->json([
            'success' => true,
            'data' => [
                'items' => $items,
                'totalInDb' => $totalInDb,
                'pagination' => [
                    'totalCount' => $total,
                    'page' => $page,
                    'pageCount' => (int) ceil($total / $limit),
                    'limit' => $limit,
                ],
            ],
        ]);
    }

    public function getGroups(Request $request)
    {
        $query = DB::table('students')
            ->whereNotNull('group_name')->where('group_name', '!=', '')
            ->select('group_name')->distinct()->orderBy('group_name');

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->input('level_code'));
        }
        if ($request->filled('faculty_id')) {
            $dept = Department::find($request->input('faculty_id'));
            if ($dept) $query->where('department_code', $dept->code);
        }

        $groups = $query->pluck('group_name');
        return response()->json($groups);
    }

    public function sync(Request $request)
    {
        $user = auth()->user();
        $name = $user ? ($user->name ?? $user->login ?? 'Admin') : 'Admin';
        $this->telegram->notify("👤 {$name} tomonidan Kontraktlar sinxronizatsiyasi boshlandi");
        Artisan::queue('import:contracts');

        return response()->json([
            'success' => true,
            'message' => 'Kontraktlar sinxronizatsiyasi boshlandi (fon rejimida).',
        ]);
    }
}
