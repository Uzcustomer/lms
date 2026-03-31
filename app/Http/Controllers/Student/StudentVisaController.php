<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StudentVisaController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('student_visa_infos')) {
            return redirect()->route('student.dashboard')
                ->with('error', 'Viza bo\'limi hali faollashtirilmagan.');
        }

        $student = Auth::guard('student')->user();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();

        return view('student.visa-info', compact('visaInfo', 'student'));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();
        $existing = StudentVisaInfo::where('student_id', $student->id)->first();

        // Agar tasdiqlangan bo'lsa, tahrirlash mumkin emas
        if ($existing && $existing->status === 'approved') {
            return redirect()->route('student.visa-info.index')
                ->with('error', 'Ma\'lumotlaringiz allaqachon tasdiqlangan. Tahrirlash mumkin emas.');
        }

        $fileRule = function (string $pathField) use ($existing) {
            $base = 'file|mimes:pdf|max:5120';
            if (!$existing || !$existing->$pathField) {
                return 'required|' . $base;
            }
            return 'nullable|' . $base;
        };

        $request->validate([
            'passport_issued_place' => 'required|string|max:255',
            'passport_number' => 'required|string|max:50',
            'passport_issued_date' => 'required|date',
            'passport_expiry_date' => 'required|date|after:passport_issued_date',
            'registration_start_date' => 'required|date',
            'registration_end_date' => 'required|date|after:registration_start_date',
            'visa_number' => 'required|string|max:50',
            'visa_type' => 'required|string|in:' . implode(',', array_keys(StudentVisaInfo::VISA_TYPES)),
            'visa_start_date' => 'required|date',
            'visa_end_date' => 'required|date|after:visa_start_date',
            'visa_entries_count' => 'required|integer|min:1',
            'visa_stay_days' => 'required|integer|min:1',
            'visa_issued_place' => 'required|string|max:255',
            'visa_issued_date' => 'required|date',
            'entry_date' => 'required|date',
            'firm' => 'required|string',
            'firm_custom' => 'nullable|required_if:firm,other|string|max:255',
            'passport_scan' => $fileRule('passport_scan_path'),
            'visa_scan' => $fileRule('visa_scan_path'),
            'registration_doc' => $fileRule('registration_doc_path'),
            'agreement_accepted' => 'accepted',
        ], [
            'passport_issued_place.required' => 'Pasport berilgan joyni kiriting.',
            'passport_number.required' => 'Pasport raqamini kiriting.',
            'passport_issued_date.required' => 'Pasport berilgan sanani kiriting.',
            'passport_expiry_date.required' => 'Pasport muddati tugash sanasini kiriting.',
            'passport_expiry_date.after' => 'Tugash sanasi berilgan sanadan keyin bo\'lishi kerak.',
            'registration_start_date.required' => 'Ro\'yxatga olish boshlanish sanasini kiriting.',
            'registration_end_date.required' => 'Ro\'yxatga olish tugash sanasini kiriting.',
            'registration_end_date.after' => 'Tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak.',
            'visa_number.required' => 'Viza raqamini kiriting.',
            'visa_type.required' => 'Viza turini tanlang.',
            'visa_start_date.required' => 'Viza boshlanish sanasini kiriting.',
            'visa_end_date.required' => 'Viza tugash sanasini kiriting.',
            'visa_end_date.after' => 'Viza tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak.',
            'visa_entries_count.required' => 'Kirishlar sonini kiriting.',
            'visa_stay_days.required' => 'Istiqomat muddatini kiriting.',
            'visa_issued_place.required' => 'Viza berilgan joyni kiriting.',
            'visa_issued_date.required' => 'Viza berilgan vaqtni kiriting.',
            'entry_date.required' => 'Chegaradan kirgan sanani kiriting.',
            'firm.required' => 'Firmani tanlang.',
            'firm_custom.required_if' => 'Firma nomini kiriting.',
            'passport_scan.required' => 'Pasport skanerini yuklang.',
            'passport_scan.mimes' => 'Pasport skaneri faqat PDF formatida bo\'lishi kerak.',
            'passport_scan.max' => 'Fayl hajmi 5MB dan oshmasligi kerak.',
            'visa_scan.required' => 'Viza skanerini yuklang.',
            'visa_scan.mimes' => 'Viza skaneri faqat PDF formatida bo\'lishi kerak.',
            'visa_scan.max' => 'Fayl hajmi 5MB dan oshmasligi kerak.',
            'registration_doc.required' => 'Ro\'yxatga olish hujjatini yuklang.',
            'registration_doc.mimes' => 'Hujjat faqat PDF formatida bo\'lishi kerak.',
            'registration_doc.max' => 'Fayl hajmi 5MB dan oshmasligi kerak.',
            'agreement_accepted.accepted' => 'Javobgarlikni qabul qilishingiz shart.',
        ]);

        $data = $request->only([
            'passport_issued_place', 'passport_number', 'passport_issued_date', 'passport_expiry_date',
            'registration_start_date', 'registration_end_date',
            'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date',
            'visa_entries_count', 'visa_stay_days', 'visa_issued_place', 'visa_issued_date',
            'entry_date', 'firm', 'firm_custom',
        ]);

        // HEMIS ma'lumotlarini avtomatik qo'shish
        $data['birth_country'] = $student->country_name;
        $data['birth_region'] = $student->province_name;
        $data['birth_city'] = $student->district_name;
        $data['birth_date'] = $student->birth_date;
        $data['agreement_accepted'] = true;
        $data['status'] = 'pending';
        $data['rejection_reason'] = null;

        $storagePath = 'student-visa/' . $student->id;

        foreach ([
            'passport_scan' => 'passport_scan_path',
            'visa_scan' => 'visa_scan_path',
            'registration_doc' => 'registration_doc_path',
        ] as $inputName => $dbField) {
            if ($request->hasFile($inputName)) {
                if ($existing?->$dbField) {
                    Storage::disk('public')->delete($existing->$dbField);
                }
                $data[$dbField] = $request->file($inputName)->store($storagePath, 'public');
            }
        }

        StudentVisaInfo::updateOrCreate(
            ['student_id' => $student->id],
            $data
        );

        return redirect()->route('student.visa-info.index')
            ->with('success', 'Viza ma\'lumotlari saqlandi va tekshirish uchun yuborildi.');
    }

    public function showFile(string $field)
    {
        $student = Auth::guard('student')->user();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $allowed = ['passport_scan_path', 'visa_scan_path', 'registration_doc_path'];
        if (!in_array($field, $allowed) || !$visaInfo->$field) {
            abort(404);
        }

        return Storage::disk('public')->response($visaInfo->$field);
    }
}
