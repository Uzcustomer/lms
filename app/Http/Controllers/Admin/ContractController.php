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
            $query->where(function ($q) use ($search) {
                $q->where('full_name', 'like', "%{$search}%")
                  ->orWhere('contract_number', 'like', "%{$search}%");
            });
        }

        $limit = (int) $request->input('limit', 50);
        $page = (int) $request->input('page', 1);

        $total = $query->count();
        $items = $query->orderByDesc('hemis_created_at')
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
