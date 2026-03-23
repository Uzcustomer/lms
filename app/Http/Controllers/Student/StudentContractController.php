<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentContract;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class StudentContractController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $contracts = StudentContract::where('student_id', $student->id)
            ->orderByDesc('created_at')
            ->get();

        // Oxirgi shartnomadan ma'lumotlarni olish (agar mavjud bo'lsa)
        $lastContract = $contracts->first();

        // Placeholder ma'lumotlari
        $placeholderData = [
            'student_name' => mb_strtoupper($student->full_name),
            'student_address' => $lastContract->student_address ?? ($student->province_name . ', ' . $student->district_name),
            'specialty_name' => $student->specialty_name,
            'contract_year' => date('Y'),
            'student_phone' => $lastContract->student_phone ?? ($student->phone ?? ''),
            'student_passport' => $lastContract->student_passport ?? '',
            'student_inn' => $lastContract->student_inn ?? '',
        ];

        return view('student.contracts.index', compact('contracts', 'student', 'placeholderData'));
    }

    public function create()
    {
        $student = Auth::guard('student')->user();

        // Faqat bitiruvchi kurs talabalari
        if (!$student->is_graduate) {
            return redirect()->route('student.contracts.index')
                ->with('error', 'Shartnoma faqat bitiruvchi kurs talabalari uchun mavjud.');
        }

        // Faol yoki kutilayotgan shartnoma borligini tekshirish
        $existingActive = StudentContract::where('student_id', $student->id)
            ->whereIn('status', [StudentContract::STATUS_PENDING, StudentContract::STATUS_REGISTRAR_REVIEW, StudentContract::STATUS_APPROVED])
            ->exists();

        if ($existingActive) {
            return redirect()->route('student.contracts.index')
                ->with('error', 'Sizda allaqachon faol shartnoma mavjud.');
        }

        return view('student.contracts.create', compact('student'));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        if (!$student->is_graduate) {
            return redirect()->route('student.contracts.index')
                ->with('error', 'Shartnoma faqat bitiruvchi kurs talabalari uchun mavjud.');
        }

        $request->validate([
            'contract_type' => 'required|in:3_tomonlama,4_tomonlama',
            'student_address' => 'required|string|max:255',
            'student_phone' => 'required|string|max:50',
            'student_passport' => 'nullable|string|max:20',
            'student_bank_account' => 'nullable|string|max:50',
            'student_bank_mfo' => 'nullable|string|max:20',
            'student_inn' => 'nullable|string|max:20',
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
            'specialty_field' => 'nullable|string|max:255',
        ], [
            'contract_type.required' => 'Shartnoma turini tanlash majburiy.',
            'student_address.required' => 'Manzilni kiritish majburiy.',
            'student_phone.required' => 'Telefon raqamni kiritish majburiy.',
        ]);

        // 4-tomonlama uchun 4-tomon ma'lumotlarini tekshirish
        if ($request->contract_type === '4_tomonlama') {
            $request->validate([
                'fourth_party_name' => 'required|string|max:255',
            ], [
                'fourth_party_name.required' => '4-tomonlama shartnoma uchun MFY/tuman nomini kiritish majburiy.',
            ]);
        }

        $contract = StudentContract::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'student_full_name' => $student->full_name,
            'group_name' => $student->group_name,
            'department_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'level_name' => $student->level_name,
            'contract_type' => $request->contract_type,
            'student_address' => $request->student_address,
            'student_phone' => $request->student_phone,
            'student_passport' => $request->student_passport,
            'student_bank_account' => $request->student_bank_account,
            'student_bank_mfo' => $request->student_bank_mfo,
            'student_inn' => $request->student_inn,
            'employer_name' => $request->employer_name,
            'employer_address' => $request->employer_address,
            'employer_phone' => $request->employer_phone,
            'employer_director_name' => $request->employer_director_name,
            'employer_director_position' => $request->employer_director_position,
            'employer_bank_account' => $request->employer_bank_account,
            'employer_bank_mfo' => $request->employer_bank_mfo,
            'employer_inn' => $request->employer_inn,
            'fourth_party_name' => $request->fourth_party_name,
            'fourth_party_address' => $request->fourth_party_address,
            'fourth_party_phone' => $request->fourth_party_phone,
            'fourth_party_director_name' => $request->fourth_party_director_name,
            'specialty_field' => $request->specialty_field,
            'status' => StudentContract::STATUS_PENDING,
        ]);

        return redirect()->route('student.contracts.index')
            ->with('success', 'Shartnoma arizasi yuborildi. Registrator ofisi ko\'rib chiqadi.');
    }

    public function show(StudentContract $contract)
    {
        $student = Auth::guard('student')->user();

        if ($contract->student_id !== $student->id) {
            abort(403);
        }

        return view('student.contracts.show', compact('contract', 'student'));
    }

    public function download(StudentContract $contract)
    {
        $student = Auth::guard('student')->user();

        if ($contract->student_id !== $student->id) {
            abort(403);
        }

        if (!$contract->document_path || $contract->status !== StudentContract::STATUS_APPROVED) {
            return back()->with('error', 'Hujjat hali tayyor emas.');
        }

        $path = storage_path('app/public/' . $contract->document_path);

        if (!file_exists($path)) {
            return back()->with('error', 'Hujjat topilmadi.');
        }

        $filename = 'Shartnoma_' . str_replace(' ', '_', $contract->student_full_name) . '.docx';
        return response()->download($path, $filename);
    }
}
