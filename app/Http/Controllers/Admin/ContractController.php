<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
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

        return view('admin.contracts.index', compact(
            'faculties',
            'educationTypes',
            'educationYears',
            'currentEducationYear'
        ));
    }

    public function data(Request $request)
    {
        $params = [
            'page' => $request->input('page', 1),
            'limit' => $request->input('limit', 50),
            '_student' => $request->input('_student'),
            '_education_year' => $request->input('_education_year'),
            '_education_type' => $request->input('_education_type'),
            '_department' => $request->input('_department'),
            '_specialty' => $request->input('_specialty'),
            '_group' => $request->input('_group'),
            '_level' => $request->input('_level'),
            '_semester' => $request->input('_semester'),
        ];

        $result = $this->hemisService->fetchContracts($params);

        if (!($result['success'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $result['error'] ?? 'HEMIS dan ma\'lumot olishda xatolik',
                'code' => 500,
                'data' => [],
            ], 500);
        }

        return response()->json($result);
    }
}
