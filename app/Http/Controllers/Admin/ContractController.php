<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContractList;
use App\Models\Curriculum;
use App\Models\Department;
use App\Services\HemisService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(
        protected HemisService $hemisService
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

        $educationYears = Curriculum::select('education_year_code as code', 'education_year_name as name')
            ->whereNotNull('education_year_code')
            ->groupBy('education_year_code', 'education_year_name')
            ->orderByDesc('education_year_code')
            ->get();

        $currentEducationYear = DB::table('semesters')
            ->where('current', true)
            ->value('education_year');

        $contractTypes = ContractList::select('edu_contract_type_code', 'edu_contract_type_name')
            ->whereNotNull('edu_contract_type_code')
            ->groupBy('edu_contract_type_code', 'edu_contract_type_name')
            ->get();

        return view('admin.contracts.index', compact(
            'faculties',
            'educationTypes',
            'educationYears',
            'currentEducationYear',
            'contractTypes'
        ));
    }

    public function data(Request $request)
    {
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
                'pagination' => [
                    'totalCount' => $total,
                    'page' => $page,
                    'pageCount' => (int) ceil($total / $limit),
                    'limit' => $limit,
                ],
            ],
        ]);
    }
}
