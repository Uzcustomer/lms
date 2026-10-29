<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Models\User;
use App\Services\StudentVisaHistoryService;
use App\Services\TelegramService;
use App\Services\VisaSettings;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StudentVisaController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('student_visa_infos')) {
            return redirect()->route('student.dashboard')
                ->with('error', __('Viza bo\'limi hali faollashtirilmagan.'));
        }

        $student = Auth::guard('student')->user();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();
        $basisRule = VisaSettings::ruleFor($student);

        return view('student.visa-info', compact('visaInfo', 'student', 'basisRule'));
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

        // Turish asosi: tanlov ko'rsatilmagan davlatlarda admin qo'ygan yoki standart asos
        $rule = VisaSettings::ruleFor($student);
        if ($rule['show_choice']) {
            $request->validate([
                'residence_basis' => 'required|in:' . implode(',', array_keys(StudentVisaInfo::BASIS_LABELS)),
            ], [
                'residence_basis.required' => __("O'zbekistonda turish asosini tanlang."),
            ]);
            $basis = $request->input('residence_basis');
        } else {
            $basis = $existing?->residence_basis ?: $rule['default_basis'];
        }

        $isVisa = $basis === StudentVisaInfo::BASIS_VISA;
        $isPermit = $basis === StudentVisaInfo::BASIS_RESIDENCE_PERMIT;
        $needsRegistration = !$isPermit;

        $requiredDocs = VisaSettings::requiredDocs($basis);
        $docPaths = [
            'passport_scan' => 'passport_scan_path',
            'visa_scan' => 'visa_scan_path',
            'registration_doc' => 'registration_doc_path',
            'residence_permit_scan' => 'residence_permit_scan_path',
        ];
        $fileRule = function (string $input) use ($existing, $requiredDocs, $docPaths) {
            $base = 'file|mimes:pdf|max:5120';
            $pathField = $docPaths[$input];
            if (in_array($input, $requiredDocs, true) && (!$existing || !$existing->$pathField)) {
                return 'required|' . $base;
            }
            return 'nullable|' . $base;
        };
        $req = fn (bool $needed) => $needed ? 'required' : 'nullable';

        $request->validate([
            'birth_country' => 'required|string|max:255',
            'birth_region' => 'required|string|max:255',
            'birth_city' => 'required|string|max:255',
            'passport_number' => 'required|string|max:50',
            'passport_issued_place' => 'required|string|max:255',
            'passport_issued_date' => 'required|date',
            'passport_expiry_date' => 'required|date|after:passport_issued_date',
            'registration_start_date' => $req($needsRegistration) . '|date',
            'registration_end_date' => $req($needsRegistration) . '|date|after:registration_start_date',
            'address_type' => 'required|in:dormitory,other',
            'current_address' => 'nullable|required_if:address_type,other|string|max:500',
            'visa_number' => $req($isVisa) . '|string|max:50',
            'visa_type' => [$req($isVisa), 'string', Rule::in(array_keys(VisaSettings::visaTypes()))],
            'visa_start_date' => $req($isVisa) . '|date',
            'visa_end_date' => $req($isVisa) . '|date|after:visa_start_date',
            'visa_entries_count' => $req($isVisa) . '|integer|min:1',
            'visa_stay_days' => $req($isVisa) . '|integer|min:1',
            'visa_issued_place' => $req($isVisa) . '|string|max:255',
            'visa_issued_date' => $req($isVisa) . '|date',
            'entry_date' => $req(!$isPermit) . '|date',
            'residence_permit_number' => $req($isPermit) . '|string|max:50',
            'residence_permit_pinfl' => $req($isPermit) . '|digits:14',
            'residence_permit_issued_place' => $req($isPermit) . '|string|max:255',
            'residence_permit_issued_date' => $req($isPermit) . '|date',
            'residence_permit_expiry_date' => $req($isPermit) . '|date|after:residence_permit_issued_date',
            'passport_scan' => $fileRule('passport_scan'),
            'visa_scan' => $fileRule('visa_scan'),
            'registration_doc' => $fileRule('registration_doc'),
            'residence_permit_scan' => $fileRule('residence_permit_scan'),
            'agreement_accepted' => 'accepted',
        ], [
            'birth_country.required' => __('Tug\'ilgan davlatni kiriting.'),
            'birth_region.required' => __('Tug\'ilgan viloyatni kiriting.'),
            'birth_city.required' => __('Tug\'ilgan shaharni kiriting.'),
            'passport_number.required' => __('Pasport raqamini kiriting.'),
            'passport_issued_place.required' => __('Pasport berilgan joyni kiriting.'),
            'passport_issued_date.required' => __('Pasport berilgan sanani kiriting.'),
            'passport_expiry_date.required' => __('Pasport muddati tugash sanasini kiriting.'),
            'passport_expiry_date.after' => __('Tugash sanasi berilgan sanadan keyin bo\'lishi kerak.'),
            'registration_start_date.required' => __('Ro\'yxatga olish boshlanish sanasini kiriting.'),
            'registration_end_date.required' => __('Ro\'yxatga olish tugash sanasini kiriting.'),
            'registration_end_date.after' => __('Tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak.'),
            'visa_number.required' => __('Viza raqamini kiriting.'),
            'visa_type.required' => __('Viza turini tanlang.'),
            'visa_start_date.required' => __('Viza boshlanish sanasini kiriting.'),
            'visa_end_date.required' => __('Viza tugash sanasini kiriting.'),
            'visa_end_date.after' => __('Viza tugash sanasi boshlanish sanasidan keyin bo\'lishi kerak.'),
            'visa_entries_count.required' => __('Kirishlar sonini kiriting.'),
            'visa_stay_days.required' => __('Istiqomat muddatini kiriting.'),
            'visa_issued_place.required' => __('Viza berilgan joyni kiriting.'),
            'visa_issued_date.required' => __('Viza berilgan vaqtni kiriting.'),
            'entry_date.required' => __('Chegaradan kirgan sanani kiriting.'),
            'residence_permit_number.required' => __('Guvohnoma (karta) raqamini kiriting.'),
            'residence_permit_pinfl.required' => __('Shaxsiy raqamni (JShShIR) kiriting.'),
            'residence_permit_pinfl.digits' => __('Shaxsiy raqam 14 ta raqamdan iborat bo\'lishi kerak.'),
            'residence_permit_issued_place.required' => __('Guvohnoma berilgan joyni kiriting.'),
            'residence_permit_issued_date.required' => __('Guvohnoma berilgan sanani kiriting.'),
            'residence_permit_expiry_date.required' => __('Guvohnoma amal qilish muddatini kiriting.'),
            'residence_permit_expiry_date.after' => __('Amal qilish muddati berilgan sanadan keyin bo\'lishi kerak.'),
            'passport_scan.required' => __('Pasport skanerini yuklang.'),
            'passport_scan.mimes' => __('Pasport skaneri faqat PDF formatida bo\'lishi kerak.'),
            'passport_scan.max' => __('Fayl hajmi 5MB dan oshmasligi kerak.'),
            'visa_scan.required' => __('Viza skanerini yuklang.'),
            'visa_scan.mimes' => __('Viza skaneri faqat PDF formatida bo\'lishi kerak.'),
            'visa_scan.max' => __('Fayl hajmi 5MB dan oshmasligi kerak.'),
            'registration_doc.required' => __('Ro\'yxatga olish hujjatini yuklang.'),
            'registration_doc.mimes' => __('Hujjat faqat PDF formatida bo\'lishi kerak.'),
            'registration_doc.max' => __('Fayl hajmi 5MB dan oshmasligi kerak.'),
            'residence_permit_scan.required' => __('Yashash guvohnomasi skanerini yuklang.'),
            'residence_permit_scan.mimes' => __('Guvohnoma skaneri faqat PDF formatida bo\'lishi kerak.'),
            'residence_permit_scan.max' => __('Fayl hajmi 5MB dan oshmasligi kerak.'),
            'agreement_accepted.accepted' => __('Javobgarlikni qabul qilishingiz shart.'),
        ]);

        $data = $request->only([
            'birth_country', 'birth_region', 'birth_city',
            'passport_number', 'passport_issued_place', 'passport_issued_date', 'passport_expiry_date',
            'registration_start_date', 'registration_end_date', 'address_type', 'current_address',
            'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date',
            'visa_entries_count', 'visa_stay_days', 'visa_issued_place', 'visa_issued_date',
            'entry_date',
            'residence_permit_number', 'residence_permit_pinfl', 'residence_permit_issued_place',
            'residence_permit_issued_date', 'residence_permit_expiry_date',
        ]);

        // Asosga tegishli bo'lmagan maydonlar tozalanadi
        $data = array_merge($data, StudentVisaInfo::fieldsClearedFor($basis));
        $data['residence_basis'] = $basis;

        $data['birth_date'] = $student->birth_date;
        $data['agreement_accepted'] = true;
        $data['status'] = 'pending';
        $data['rejection_reason'] = null;

        // Ustunlar mavjudligini tekshirish (migratsiya ishlatilmagan bo'lishi mumkin)
        $columns = \Schema::getColumnListing('student_visa_infos');
        $data = array_intersect_key($data, array_flip($columns));

        $storagePath = 'student-visa/' . $student->id;

        // Yangi PDF lar uchun joy — eski fayllar diskdan O'CHIRILMAYDI (tarix uchun saqlanadi)
        foreach ($docPaths as $inputName => $dbField) {
            if ($request->hasFile($inputName) && in_array($inputName, VisaSettings::DOCS_BY_BASIS[$basis], true)) {
                $data[$dbField] = $request->file($inputName)->store($storagePath, 'public');
            }
        }

        try {
            $historyService = app(StudentVisaHistoryService::class);
            $oldValues = $existing ? $historyService->fieldsFrom($existing) : null;

            $visaInfo = StudentVisaInfo::updateOrCreate(
                ['student_id' => $student->id],
                $data
            );

            $historyService->snapshot(
                $student,
                $visaInfo->fresh(),
                $existing ? \App\Models\StudentVisaInfoHistory::CHANGE_UPDATED : \App\Models\StudentVisaInfoHistory::CHANGE_CREATED,
                $oldValues,
                'Talaba o\'zi to\'ldirdi'
            );
        } catch (\Throwable $e) {
            \Log::error('Viza ma\'lumotlarini saqlashda xato: ' . $e->getMessage());
            return redirect()->back()->withInput()
                ->with('error', __('Saqlashda xatolik. Iltimos, administratorga murojaat qiling.'));
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
        $sentTelegramIds = [];

        // Barcha telegram_chat_id larni yig'ib, faqat unique larga yuborish
        $telegramTargets = [];

        // 1. Registrator ofisi — User jadvalidan
        $regUsers = User::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))->get();
        foreach ($regUsers as $u) {
            if ($u->telegram_chat_id) $telegramTargets[$u->telegram_chat_id] = true;
        }

        // 2. Registrator ofisi — Teacher jadvalidan
        $regTeachers = \App\Models\Teacher::whereHas('roles', fn($q) => $q->where('name', 'registrator_ofisi'))->get();
        foreach ($regTeachers as $t) {
            if ($t->telegram_chat_id) $telegramTargets[$t->telegram_chat_id] = true;
        }

        // 3. Firma javobgarlari
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();
        if ($visaInfo?->firm) {
            $firmUsers = User::where('assigned_firm', $visaInfo->firm)->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))->get();
            foreach ($firmUsers as $fu) {
                if ($fu->telegram_chat_id) $telegramTargets[$fu->telegram_chat_id] = true;
            }
            $firmTeachers = \App\Models\Teacher::where('assigned_firm', $visaInfo->firm)->whereHas('roles', fn($q) => $q->where('name', 'javobgar_firma'))->get();
            foreach ($firmTeachers as $ft) {
                if ($ft->telegram_chat_id) $telegramTargets[$ft->telegram_chat_id] = true;
            }
        }

        // Unique chat_id larga Telegram yuborish
        foreach (array_keys($telegramTargets) as $chatId) {
            try { app(TelegramService::class)->sendToUser($chatId, $message); } catch (\Throwable $e) {}
        }

        // Sayt bildirishnomasi — faqat registrator User larga
        foreach ($regUsers as $u) {
            Notification::create([
                'sender_id' => $student->id, 'sender_type' => get_class($student),
                'recipient_id' => $u->id, 'recipient_type' => User::class,
                'subject' => 'Yangi viza ma\'lumotlari kiritildi', 'body' => $message,
                'type' => 'info', 'is_read' => false, 'is_draft' => false, 'sent_at' => now(),
            ]);
        }
    }

    public function showFile(string $field)
    {
        $student = Auth::guard('student')->user();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $allowed = ['passport_scan_path', 'visa_scan_path', 'registration_doc_path', 'residence_permit_scan_path'];
        if (!in_array($field, $allowed) || !$visaInfo->$field) {
            abort(404);
        }

        return Storage::disk('public')->response($visaInfo->$field);
    }
}
