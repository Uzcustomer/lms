<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Models\User;
use App\Services\TelegramService;
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
                ->with('error', __('Ma\'lumotlaringiz allaqachon tasdiqlangan. Tahrirlash mumkin emas.'));
        }

        $fileRule = function (string $pathField) use ($existing) {
            $base = 'file|mimes:pdf|max:5120';
            if (!$existing || !$existing->$pathField) {
                return 'required|' . $base;
            }
            return 'nullable|' . $base;
        };

        $request->validate([
            'birth_country' => 'required|string|max:255',
            'birth_region' => 'required|string|max:255',
            'birth_city' => 'required|string|max:255',
            'passport_number' => 'required|string|max:50',
            'passport_issued_place' => 'required|string|max:255',
            'passport_issued_date' => 'required|date',
            'passport_expiry_date' => 'required|date|after:passport_issued_date',
            'registration_start_date' => 'required|date',
            'registration_end_date' => 'required|date|after:registration_start_date',
            'address_type' => 'required|in:dormitory,other',
            'current_address' => 'nullable|required_if:address_type,other|string|max:500',
            'visa_number' => 'required|string|max:50',
            'visa_type' => 'required|string|in:' . implode(',', array_keys(StudentVisaInfo::VISA_TYPES)),
            'visa_start_date' => 'required|date',
            'visa_end_date' => 'required|date|after:visa_start_date',
            'visa_entries_count' => 'required|integer|min:1',
            'visa_stay_days' => 'required|integer|min:1',
            'visa_issued_place' => 'required|string|max:255',
            'visa_issued_date' => 'required|date',
            'entry_date' => 'required|date',
            'passport_scan' => $fileRule('passport_scan_path'),
            'visa_scan' => $fileRule('visa_scan_path'),
            'registration_doc' => $fileRule('registration_doc_path'),
            'agreement_accepted' => 'accepted',
        ], [
            'birth_country.required' => __('Tug\'ilgan davlatni kiriting.'),
            'birth_region.required' => __('Tug\'ilgan viloyatni kiriting.'),
            'birth_city.required' => __('Tug\'ilgan shaharni kiriting.'),
            'passport_number.required' => 'Pasport raqamini kiriting.',
            'passport_issued_place.required' => 'Pasport berilgan joyni kiriting.',
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
            'birth_country', 'birth_region', 'birth_city',
            'passport_number', 'passport_issued_place', 'passport_issued_date', 'passport_expiry_date',
            'registration_start_date', 'registration_end_date', 'address_type', 'current_address',
            'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date',
            'visa_entries_count', 'visa_stay_days', 'visa_issued_place', 'visa_issued_date',
            'entry_date',
        ]);

        $data['birth_date'] = $student->birth_date;
        $data['agreement_accepted'] = true;
        $data['status'] = 'pending';
        $data['rejection_reason'] = null;

        // Ustunlar mavjudligini tekshirish (migratsiya ishlatilmagan bo'lishi mumkin)
        $columns = \Schema::getColumnListing('student_visa_infos');
        $data = array_intersect_key($data, array_flip($columns));

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

        try {
            StudentVisaInfo::updateOrCreate(
                ['student_id' => $student->id],
                $data
            );
        } catch (\Throwable $e) {
            \Log::error('Viza ma\'lumotlarini saqlashda xato: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', 'Saqlashda xatolik. Iltimos, administratorga murojaat qiling.');
        }

        // Registrator ofisi xodimlariga va firma javobgariga xabar yuborish
        $this->notifyStaffAboutSubmission($student);

        return redirect()->route('student.visa-info.index')
            ->with('success', __('Viza ma\'lumotlari saqlandi va tekshirish uchun yuborildi.'));
    }

    /**
     * Registrator ofisi va firma javobgariga talaba ma'lumot kiritgani haqida xabar.
     */
    private function notifyStaffAboutSubmission($student): void
    {
        $message = "📋 {$student->full_name} ({$student->group_name}) viza ma'lumotlarini kiritdi. Tekshirish kerak.";

        // Registrator ofisi xodimlariga sayt bildirishnomasi + Telegram
        $registrarUsers = User::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))->get();
        foreach ($registrarUsers as $user) {
            Notification::create([
                'sender_id' => $student->id,
                'sender_type' => get_class($student),
                'recipient_id' => $user->id,
                'recipient_type' => User::class,
                'subject' => 'Yangi viza ma\'lumotlari kiritildi',
                'body' => $message,
                'type' => 'info',
                'is_read' => false,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
            if ($user->telegram_chat_id) {
                try { app(TelegramService::class)->sendToUser($user->telegram_chat_id, $message); } catch (\Throwable $e) {}
            }
        }

        // Registrator ofisi xodimlari (Teacher jadvalidan ham)
        $registrarTeachers = \App\Models\Teacher::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))->get();
        foreach ($registrarTeachers as $teacher) {
            if ($teacher->telegram_chat_id) {
                try { app(TelegramService::class)->sendToUser($teacher->telegram_chat_id, $message); } catch (\Throwable $e) {}
            }
        }

        // Firma javobgariga (agar firma belgilangan bo'lsa)
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();
        if ($visaInfo?->firm) {
            $firmUsers = User::where('assigned_firm', $visaInfo->firm)
                ->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))
                ->get();
            foreach ($firmUsers as $firmUser) {
                Notification::create([
                    'sender_id' => $student->id,
                    'sender_type' => get_class($student),
                    'recipient_id' => $firmUser->id,
                    'recipient_type' => User::class,
                    'subject' => 'Talaba viza ma\'lumotlarini kiritdi',
                    'body' => $message,
                    'type' => 'info',
                    'is_read' => false,
                    'is_draft' => false,
                    'sent_at' => now(),
                ]);
                if ($firmUser->telegram_chat_id) {
                    try {
                        app(TelegramService::class)->sendToUser($firmUser->telegram_chat_id, $message);
                    } catch (\Throwable $e) {}
                }
            }

            // Teacher firmalardan ham
            $firmTeachers = \App\Models\Teacher::where('assigned_firm', $visaInfo->firm)
                ->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))
                ->get();
            foreach ($firmTeachers as $teacher) {
                if ($teacher->telegram_chat_id) {
                    try {
                        app(TelegramService::class)->sendToUser($teacher->telegram_chat_id, $message);
                    } catch (\Throwable $e) {}
                }
            }
        }
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
