<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\HemisService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContractController extends Controller
{
    public function __construct(
        protected HemisService $hemisService
    ) {}

    public function index(): View
    {
        return view('admin.contracts.index');
    }

    public function data(Request $request)
    {
        $params = [
            'page' => $request->input('page', 1),
            'limit' => $request->input('limit', 50),
            '_student' => $request->input('_student'),
            '_education_year' => $request->input('_education_year'),
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
