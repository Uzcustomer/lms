<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Semester;
use App\Models\StudentContract;
use App\Services\StudentContractService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentContractController extends Controller
{
    public function __construct(
        protected StudentContractService $contractService
    ) {}

    public function index(Request $request)
    {
        $query = StudentContract::with('student', 'reviewer')->orderByDesc('created_at');

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('contract_type')) {
            $query->where('contract_type', $request->contract_type);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('student_full_name', 'like', "%{$search}%")
                    ->orWhere('student_hemis_id', 'like', "%{$search}%")
                    ->orWhere('group_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('department')) {
            $query->where('department_name', $request->department);
        }

        $contracts = $query->paginate(20)->withQueryString();

        $departments = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name')
            ->pluck('name');

        $statusCounts = [
            'all' => StudentContract::count(),
            'pending' => StudentContract::where('status', StudentContract::STATUS_PENDING)->count(),
            'registrar_review' => StudentContract::where('status', StudentContract::STATUS_REGISTRAR_REVIEW)->count(),
            'approved' => StudentContract::where('status', StudentContract::STATUS_APPROVED)->count(),
            'rejected' => StudentContract::where('status', StudentContract::STATUS_REJECTED)->count(),
        ];

        return view('admin.student-contracts.index', compact('contracts', 'departments', 'statusCounts'));
    }

    public function show(StudentContract $studentContract)
    {
        $studentContract->load('student', 'reviewer');
        return view('admin.student-contracts.show', compact('studentContract'));
    }

    public function review(StudentContract $studentContract)
    {
        if ($studentContract->status === StudentContract::STATUS_PENDING) {
            $studentContract->update(['status' => StudentContract::STATUS_REGISTRAR_REVIEW]);
        }

        $studentContract->load('student', 'reviewer');
        return view('admin.student-contracts.review', compact('studentContract'));
    }

    public function approve(Request $request, StudentContract $studentContract)
    {
        $request->validate([
            'employer_name' => 'nullable|string|max:255',
            'employer_address' => 'nullable|string|max:255',
            'employer_phone' => 'nullable|string|max:50',
            'employer_director_name' => 'nullable|string|max:255',
            'employer_director_position' => 'nullable|string|max:255',
            'employer_bank_account' => 'nullable|string|max:50',
            'employer_bank_mfo' => 'nullable|string|max:20',
            'employer_inn' => 'nullable|string|max:20',
            'fourth_party_name' => 'nullable|string|max:255',
            'fourth_party_address' => 'nullable|string|max:255',
            'fourth_party_phone' => 'nullable|string|max:50',
            'fourth_party_director_name' => 'nullable|string|max:255',
        ]);

        $teacher = Auth::guard('teacher')->user() ?? Auth::user();

        $updateData = array_filter($request->only([
            'employer_name', 'employer_address', 'employer_phone',
            'employer_director_name', 'employer_director_position',
            'employer_bank_account', 'employer_bank_mfo', 'employer_inn',
            'fourth_party_name', 'fourth_party_address', 'fourth_party_phone', 'fourth_party_director_name',
        ]), fn($v) => $v !== null);

        $updateData['status'] = StudentContract::STATUS_APPROVED;
        $updateData['reviewed_by'] = $teacher?->id;
        $updateData['reviewed_at'] = now();

        $studentContract->update($updateData);

        // Generate Word document
        try {
            $docPath = $this->contractService->generateContractDocument($studentContract->fresh());
            $studentContract->update(['document_path' => $docPath]);
        } catch (\Throwable $e) {
            \Log::error('Contract document generation failed: ' . $e->getMessage());
        }

        return redirect()->route('admin.student-contracts.index')
            ->with('success', 'Shartnoma tasdiqlandi.');
    }

    public function reject(Request $request, StudentContract $studentContract)
    {
        $request->validate([
            'reject_reason' => 'required|string|max:500',
        ]);

        $teacher = Auth::guard('teacher')->user() ?? Auth::user();

        $studentContract->update([
            'status' => StudentContract::STATUS_REJECTED,
            'reject_reason' => $request->reject_reason,
            'reviewed_by' => $teacher?->id,
            'reviewed_at' => now(),
        ]);

        return redirect()->route('admin.student-contracts.index')
            ->with('success', 'Shartnoma rad etildi.');
    }

    public function download(StudentContract $studentContract)
    {
        if (!$studentContract->document_path) {
            return back()->with('error', 'Hujjat hali yaratilmagan.');
        }

        $path = storage_path('app/public/' . $studentContract->document_path);

        if (!file_exists($path)) {
            // Try regenerating
            try {
                $docPath = $this->contractService->generateContractDocument($studentContract);
                $studentContract->update(['document_path' => $docPath]);
                $path = storage_path('app/public/' . $docPath);
            } catch (\Throwable $e) {
                return back()->with('error', 'Hujjat topilmadi: ' . $e->getMessage());
            }
        }

        $filename = 'Shartnoma_' . str_replace(' ', '_', $studentContract->student_full_name) . '.docx';
        return response()->download($path, $filename);
    }

    public function regenerate(StudentContract $studentContract)
    {
        try {
            $docPath = $this->contractService->generateContractDocument($studentContract);
            $studentContract->update(['document_path' => $docPath]);
            return back()->with('success', 'Hujjat qayta yaratildi.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Hujjat yaratishda xatolik: ' . $e->getMessage());
        }
    }
}
