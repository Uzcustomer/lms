<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\InternationalStudentsExport;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Services\TelegramService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;

class InternationalStudentController extends Controller
{
    /**
     * Xalqaro talabalar fakulteti talabalari ro'yxatini filtrlash uchun
     * "Xalqaro ta'lim" so'zi bo'lgan yoki citizenship_code 'UZ' bo'lmagan talabalar.
     */
    /**
     * Xorijiy fuqarolar: xd guruhlar YOKI fuqaroligi "xorijiy" bo'lganlar.
     */
    private function internationalStudentsQuery()
    {
        return Student::where(function ($q) {
            $q->where('group_name', 'like', 'xd%')
              ->orWhere('citizenship_name', 'like', '%orijiy%');
        });
    }

    public function index(Request $request)
    {
        // Migratsiya bajarilganligini tekshirish
        if (!Schema::hasTable('student_visa_infos')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        $query = $this->internationalStudentsQuery();

        // Filterlash
        if ($request->filled('search')) {
            $query->where('full_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }

        if ($request->filled('group_name')) {
            $query->where('group_name', 'like', '%' . $request->group_name . '%');
        }

        if ($request->filled('firm')) {
            $query->whereHas('visaInfo', function ($q) use ($request) {
                $q->where('firm', $request->firm);
            });
        }

        if ($request->filled('country')) {
            $query->where('country_name', $request->country);
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('data_status')) {
            if ($request->data_status === 'filled') {
                $query->whereHas('visaInfo');
            } elseif ($request->data_status === 'not_filled') {
                $query->whereDoesntHave('visaInfo');
            } elseif ($request->data_status === 'approved') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'approved'));
            } elseif ($request->data_status === 'pending') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'pending'));
            } elseif ($request->data_status === 'rejected') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'rejected'));
            }
        }

        if ($request->has('visa_expiry') && $request->visa_expiry !== '' && $request->visa_expiry !== null) {
            $days = (int) $request->visa_expiry;
            $query->whereHas('visaInfo', function ($q) use ($days) {
                $q->whereNotNull('visa_end_date')
                  ->whereDate('visa_end_date', '<=', now()->addDays($days));
            });
        }

        if ($request->has('registration_expiry') && $request->registration_expiry !== '' && $request->registration_expiry !== null) {
            $days = (int) $request->registration_expiry;
            $query->whereHas('visaInfo', function ($q) use ($days) {
                $q->whereNotNull('registration_end_date')
                  ->whereDate('registration_end_date', '<=', now()->addDays($days));
            });
        }

        $students = $query->with('visaInfo')
            ->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        $firms = StudentVisaInfo::FIRM_OPTIONS;

        // Davlatlar va fakultetlar (filtr uchun)
        $baseQuery = $this->internationalStudentsQuery();
        $countries = (clone $baseQuery)->whereNotNull('country_name')->where('country_name', '!=', '')->distinct()->pluck('country_name')->sort()->values();
        $departments = (clone $baseQuery)->whereNotNull('department_name')->where('department_name', '!=', '')->select('department_id', 'department_name')->distinct()->get()->sortBy('department_name');

        // Statistika
        $intStudentIds = $this->internationalStudentsQuery()->pluck('id');
        $allVisas = StudentVisaInfo::whereIn('student_id', $intStudentIds);
        $totalIntStudents = $intStudentIds->count();
        $filledCount = (clone $allVisas)->count();
        $notFilledCount = $totalIntStudents - $filledCount;
        $approvedCount = (clone $allVisas)->where('status', 'approved')->count();
        $pendingCount = (clone $allVisas)->where('status', 'pending')->count();
        $rejectedCount = (clone $allVisas)->where('status', 'rejected')->count();

        // Muddati yaqin talabalar
        $visaUrgentCount = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('visa_end_date')
            ->whereDate('visa_end_date', '<=', now()->addDays(30))
            ->whereDate('visa_end_date', '>=', now())
            ->count();
        $regUrgentCount = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('registration_end_date')
            ->whereDate('registration_end_date', '<=', now()->addDays(7))
            ->whereDate('registration_end_date', '>=', now())
            ->count();
        $expiredVisaCount = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('visa_end_date')
            ->whereDate('visa_end_date', '<', now())
            ->count();
        $expiredRegCount = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('registration_end_date')
            ->whereDate('registration_end_date', '<', now())
            ->count();

        $stats = compact(
            'totalIntStudents', 'filledCount', 'notFilledCount',
            'approvedCount', 'pendingCount', 'rejectedCount',
            'visaUrgentCount', 'regUrgentCount', 'expiredVisaCount', 'expiredRegCount'
        );

        return view('admin.international-students.index', compact('students', 'firms', 'stats', 'countries', 'departments'));
    }

    public function show(Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();

        return view('admin.international-students.show', compact('student', 'visaInfo'));
    }

    /**
     * Hozirgi foydalanuvchi ID si (web yoki teacher guard).
     */
    private function currentUserId(): ?int
    {
        if (auth()->guard('web')->check()) {
            return auth()->guard('web')->id();
        }
        return null;
    }

    public function approve(Request $request, Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $visaInfo->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => $this->currentUserId(),
            'reviewed_at' => now(),
        ]);

        // Talabaga bildirishnoma yuborish
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => 'Viza ma\'lumotlari tasdiqlandi',
            'message' => 'Sizning viza ma\'lumotlaringiz registrator ofisi tomonidan tasdiqlandi.',
        ]);

        // Telegram orqali xabar yuborish
        if ($student->telegram_chat_id) {
            app(TelegramService::class)->sendToUser(
                $student->telegram_chat_id,
                "✅ Sizning viza ma'lumotlaringiz registrator ofisi tomonidan tasdiqlandi."
            );
        }

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Talaba ma\'lumotlari tasdiqlandi.');
    }

    public function reject(Request $request, Student $student)
    {
        $request->validate([
            'rejection_reason' => 'required|string|max:1000',
        ], [
            'rejection_reason.required' => 'Rad etish sababini kiriting.',
        ]);

        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $visaInfo->update([
            'status' => 'rejected',
            'rejection_reason' => $request->rejection_reason,
            'reviewed_by' => $this->currentUserId(),
            'reviewed_at' => now(),
        ]);

        // Talabaga bildirishnoma yuborish
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => 'Viza ma\'lumotlari rad etildi',
            'message' => 'Sizning viza ma\'lumotlaringiz rad etildi. Sabab: ' . $request->rejection_reason,
        ]);

        // Telegram orqali xabar yuborish
        if ($student->telegram_chat_id) {
            app(TelegramService::class)->sendToUser(
                $student->telegram_chat_id,
                "❌ Sizning viza ma'lumotlaringiz rad etildi.\nSabab: " . $request->rejection_reason
            );
        }

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Talaba ma\'lumotlari rad etildi.');
    }

    /**
     * Pasport qabul qilindi — registratsiya/viza jarayoni boshlandi.
     */
    public function acceptPassport(Request $request, Student $student)
    {
        $request->validate(['process_type' => 'required|in:registration,visa']);
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();
        $field = $request->process_type === 'registration' ? 'registration_process_status' : 'visa_process_status';

        $updates = [
            'passport_handed_over' => true,
            'passport_handed_at' => now(),
            'passport_received_by' => $this->currentUserId(),
        ];

        // Ustun mavjudligini tekshirish
        $columns = \Schema::getColumnListing('student_visa_infos');
        if (in_array($field, $columns)) {
            $updates[$field] = StudentVisaInfo::PROCESS_PASSPORT_ACCEPTED;
        }
        if ($request->process_type === 'visa' && in_array('registration_process_status', $columns)) {
            $updates['registration_process_status'] = StudentVisaInfo::PROCESS_PASSPORT_ACCEPTED;
        }

        $visaInfo->update($updates);

        $label = $request->process_type === 'visa' ? 'Viza' : 'Registratsiya';
        $this->notifyStudent($student, "Pasportingiz qabul qilindi. {$label} jarayoni boshlandi.");

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Pasport qabul qilindi. Jarayon boshlandi.');
    }

    /**
     * Registratsiya qilinmoqda holatiga o'tkazish.
     */
    public function markRegistering(Request $request, Student $student)
    {
        $request->validate(['process_type' => 'required|in:registration,visa']);
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();
        $field = $request->process_type === 'registration' ? 'registration_process_status' : 'visa_process_status';

        $updates = [$field => StudentVisaInfo::PROCESS_REGISTERING];
        if ($request->process_type === 'visa') {
            $updates['registration_process_status'] = StudentVisaInfo::PROCESS_REGISTERING;
        }
        $visaInfo->update($updates);

        $label = $request->process_type === 'visa' ? 'Viza' : 'Registratsiya';
        $this->notifyStudent($student, "{$label} jarayoni davom etmoqda.");

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Holat yangilandi: Registratsiya qilinmoqda.');
    }

    /**
     * Pasportni qaytarish — talaba yangi ma'lumotlarni kiritishi kerak.
     */
    public function returnPassport(Request $request, Student $student)
    {
        $request->validate(['process_type' => 'required|in:registration,visa']);
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();
        $type = $request->process_type;

        $updates = [
            'passport_handed_over' => false,
            'passport_handed_at' => null,
            'passport_received_by' => null,
            'status' => 'pending',
            'visa_info_deadline' => now()->addDays(3), // 3 kun ichida to'ldirishi kerak
        ];

        if ($type === 'visa') {
            // Viza qaytarilganda registratsiya ham birga yangilanadi
            $updates['visa_process_status'] = StudentVisaInfo::PROCESS_DONE;
            $updates['registration_process_status'] = StudentVisaInfo::PROCESS_DONE;
            $updates['visa_start_date'] = null;
            $updates['visa_end_date'] = null;
            $updates['visa_number'] = null;
            $updates['visa_type'] = null;
            $updates['visa_scan_path'] = null;
            $updates['registration_start_date'] = null;
            $updates['registration_end_date'] = null;
            $updates['registration_doc_path'] = null;
        } else {
            $updates['registration_process_status'] = StudentVisaInfo::PROCESS_DONE;
            $updates['registration_start_date'] = null;
            $updates['registration_end_date'] = null;
            $updates['registration_doc_path'] = null;
        }

        // Eski fayllarni diskdan o'chirish
        foreach (['visa_scan_path', 'registration_doc_path'] as $fileField) {
            if (isset($updates[$fileField]) && $updates[$fileField] === null && $visaInfo->$fileField) {
                \Storage::disk('public')->delete($visaInfo->$fileField);
            }
        }

        $visaInfo->update($updates);

        $label = $type === 'visa' ? 'Viza va registratsiya' : 'Registratsiya';
        $this->notifyStudent($student, "Pasportingiz qaytarildi. {$label} ma'lumotlaringizni 3 kun ichida qaytadan kiriting!");

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Pasport qaytarildi. Talabaga 3 kunlik muddat berildi.');
    }

    private function notifyStudent(Student $student, string $message): void
    {
        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => 'Viza/Registratsiya jarayoni',
            'message' => $message,
        ]);

        if ($student->telegram_chat_id) {
            app(TelegramService::class)->sendToUser($student->telegram_chat_id, $message);
        }
    }

    /**
     * Registratsiya talabnoma Word yaratish.
     */
    public function registrationTalabnoma(Request $request)
    {
        $request->validate(['student_ids' => 'required|array|min:1']);
        $students = Student::whereIn('id', $request->student_ids)->with('visaInfo')->get();

        $word = new PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        foreach ($students as $index => $student) {
            $v = $student->visaInfo;
            $section = $word->addSection(['marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 800, 'marginRight' => 600]);

            // Header
            $table = $section->addTable();
            $table->addRow();
            $cell1 = $table->addCell(4500);
            $cell1->addText('Hurmatli S.Eshqobilov', ['italic' => true, 'size' => 11]);
            $cell1->addText('qonuniy xal qiling', ['italic' => true, 'size' => 11]);
            $cell2 = $table->addCell(4500);
            $cell2->addText('Termiz Shahar IIB M va FRB', ['size' => 11], ['alignment' => Jc::END]);
            $cell2->addText("boshlig'i podpolkovnik", ['size' => 11], ['alignment' => Jc::END]);
            $cell2->addText('S. S. Kabilovga', ['size' => 11], ['alignment' => Jc::END]);

            $section->addTextBreak(1);
            $section->addText('TALABNOMA', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
            $section->addTextBreak(1);

            $section->addText(
                "Toshkent davlat tibbiyot universiteti Termiz filiali Sizdan quyidagi chet el fuqarosi yoki fuqaroligi bo'lmagan shaxsni vaqtincha ro'yxatga olishni (vaqtincha ro'yxat muddatini uzaytirshni) so'raydi:",
                ['size' => 11], ['alignment' => Jc::BOTH]
            );

            $b = ['bold' => true, 'size' => 11];
            $n = ['size' => 11];
            $u = ['size' => 11];

            $section->addText('');
            $this->addField($section, '1. F.I.O: ', $student->full_name);
            $section->addText('(chet el fuqarosi yoki fuqaroligi bo\'lmagan shaxsning familiyasi, ismi, otasining ismi hujjat bo\'yicha lotinchada yoziladi)', ['size' => 9, 'italic' => true, 'color' => '666666']);
            $this->addField($section, '2. Farzandlari: ', '_______________');
            $birthPlace = ($v?->birth_city ?? $student->district_name ?? '___') . ',' . ($v?->birth_region ?? $student->province_name ?? '');
            $this->addField($section, '3. Fuqaroligi: ', ($student->country_name ?? '___') . '   4. Jinsi: ' . ($student->gender_name ?? '___'));
            $this->addField($section, '5. Tug\'ilgan joyi va sanasi: ', $birthPlace . ',  ' . ($student->birth_date?->format('d.m.Y') ?? '___'));
            $this->addField($section, '6. Ish joyi va lavozimi: ', 'Toshkent davlat tibbiyot universiteti Termiz filiali talaba');
            $this->addField($section, '7. Passport/harakatlanish hujjati: ', $v?->passport_number ?? '___');

            $vizaInfo = ($v?->visa_type ?? '___') . '; № ' . ($v?->visa_number ?? '___') . '; ' . ($v?->visa_entries_count ?? '___') . ' MARTALIK';
            $this->addField($section, '8. Viza turi: ', $vizaInfo);

            $vizaBerilgan = ($v?->visa_issued_place ?? '___') . ' (' . ($v?->visa_type ?? '') . ', № ' . ($v?->visa_number ?? '') . '; ' . ($v?->visa_start_date?->format('d.m.Y') ?? '___') . ' dan ' . ($v?->visa_end_date?->format('d.m.Y') ?? '___') . ' gacha)';
            $this->addField($section, '9. Viza kim tomonidan rasmiyashtirib berilgan va uning muddati: ', $vizaBerilgan);

            $regDays = $v?->visa_stay_days ?? '___';
            $this->addField($section, '10. So\'ralayotgan vaqtincha ro\'yxat muddati (kunlarda): ', $regDays . ' kun');
            $this->addField($section, '11. O\'zbekistonga kirib kelgan sanasi (nazorat o\'tish punkti): ', $v?->entry_date?->format('d.m.Y') ?? '___');
            $this->addField($section, '12. Vaqtincha yashash manzili: ', 'Termiz shahar I.Karimov ko\'chasi 64-uy');
            $this->addField($section, '13. Uy joy maydon bergan shaxsning F.I.O: ', 'Toshkent davlat tibbiyot universiteti Termiz filiali yotoqxona');
            $section->addText('     Kadastr raqami: 19:15:01:03:01:0704', $n);
            $this->addField($section, '14. Hujjatlarni rasmiylashtirish va taqdim etishga mas\'ul bo\'lgan shaxsning F.I.O: ', '');
            $section->addText('     Temirov Shukrullo Xonimqulovich', $u);
            $section->addText('     Passport harakatlanish hujjat seriyasi va raqami: AC 2275461', $n);
            $section->addText('     Xizmat tel raqami_______________ uvali tel. raqami +998995721774', $n);

            $section->addTextBreak(2);
            $signTable = $section->addTable();
            $signTable->addRow();
            $signTable->addCell(4500)->addText('Direktor', ['bold' => true, 'size' => 13]);
            $signTable->addCell(4500)->addText('F.A.Otamuradov', ['bold' => true, 'size' => 13], ['alignment' => Jc::END]);

            $section->addTextBreak(2);
            $section->addText('Ijrochi:Sh.Temirov', ['size' => 10]);
            $section->addText('Tel:+998995721774', ['size' => 10]);
        }

        $fileName = 'registratsiya_talabnoma_' . now()->format('Y_m_d') . '.docx';
        $temp = tempnam(sys_get_temp_dir(), 'word');
        $writer = IOFactory::createWriter($word, 'Word2007');
        $writer->save($temp);

        return response()->download($temp, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Viza talabnoma Word yaratish.
     */
    public function visaTalabnoma(Request $request)
    {
        $request->validate(['student_ids' => 'required|array|min:1']);
        $students = Student::whereIn('id', $request->student_ids)->with('visaInfo')->get();

        $word = new PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);

        foreach ($students as $student) {
            $v = $student->visaInfo;
            $section = $word->addSection(['marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 800, 'marginRight' => 600]);

            $section->addText("Surxondaryo viloyati IIB Migratsiya va fuqarolikni", ['size' => 11], ['alignment' => Jc::END]);
            $section->addText("rasmiylashtirish boshqarmasi boshlig'iga", ['size' => 11], ['alignment' => Jc::END]);

            $section->addTextBreak(1);
            $section->addText('TALABNOMA', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
            $section->addTextBreak(1);

            $section->addText(
                'Toshkent davlat tibbiyot universiteti Termiz filiali quyidagi xorijiy talaba vizasining amal qilish muddatini 3 oy muddatga (2 martalik) uzaytirib berishda amaliy yordam berishingizni so\'raydi',
                ['size' => 11], ['alignment' => Jc::BOTH]
            );
            $section->addText('');

            $birthPlace = ($v?->birth_city ?? $student->district_name ?? '___') . ',' . ($v?->birth_region ?? $student->province_name ?? '');
            $lavozim = 'Toshkent davlat tibbiyot universiteti Termiz filiali ' . ($student->department_name ?? '') . ' "' . ($student->specialty_code ?? '') . '" ' . ($student->level_code ?? '') . '-bosqich talabasi';

            $this->addField($section, '1. F.I.SH: ', ($student->full_name ?? '___') . '   2. Fuqaroligi: ' . ($student->country_name ?? '___'));
            $this->addField($section, '3. Jinsi: ', ($student->gender_name ?? '___') . '   4. Tug\'ilgan sanasi: ' . ($student->birth_date?->format('d.m.Y') ?? '___'));
            $this->addField($section, '5. Tug\'ilgan joyi: ', $birthPlace);
            $this->addField($section, '6. Ish joyi va lavozimi: ', $lavozim);
            $this->addField($section, '7. Milliy passport: ', $v?->passport_number ?? '___');

            $vizaInfo = ($v?->visa_type ?? '___') . ';№ ' . ($v?->visa_number ?? '___') . '; ' . ($v?->visa_entries_count ?? '___') . ' MARTALIK';
            $this->addField($section, '8. Viza turi raqami hamda safarlar soni: ', $vizaInfo);
            $this->addField($section, '9. Farzandlari: ', 'yo\'q');

            $vizaBerilgan = ($v?->visa_issued_place ?? '___') . ' (' . ($v?->visa_type ?? '') . ', № ' . ($v?->visa_number ?? '') . '; ' . ($v?->visa_start_date?->format('d.m.Y') ?? '___') . ' dan ' . ($v?->visa_end_date?->format('d.m.Y') ?? '___') . ' gacha)';
            $this->addField($section, '10. Viza kim tomonidan rasmiyashtirilb berilgan (turi, raqam va amal qilish muddati): ', $vizaBerilgan);
            $this->addField($section, '11. Viza uzaytirish so\'ralayotgan muddat (kunlarda): ', ($v?->visa_stay_days ?? '___') . ' kun');
            $this->addField($section, '12. Chegara nazorat maskanidan O\'zbekiston Respublikasiga kirib kelgan sanasi: ', $v?->entry_date?->format('d.m.Y') ?? '___');
            $this->addField($section, '13. Vaqtincha yashash manzili (uy. telefon r.): ', "MA'RIFAT MFY, Islom Karimov ko'chasi, 64-uy");
            $this->addField($section, '14. Uy joy taqdim etayotgan shaxs yoki tashkilot nomi: ', 'Toshkent davlat tibbiyot universiteti Termiz filiali');
            $this->addField($section, '15. TTV akkredatsiyadan o\'tgan ro\'yxat raqami: ', 'yo\'q');
            $this->addField($section, '16. Adliya Vazirligi yoki Hokimiyatdan o\'tgan ro\'yxat raqami: ', 'yo\'q');
            $this->addField($section, '17. B va MM vazirligidan o\'tgan ro\'yxat va muddati: ', 'yo\'q');
            $this->addField($section, '18. Moliya vazirligidan o\'tgan yat raqami va muddati: ', 'yo\'q');
            $this->addField($section, '19. Hujjatlarni rasmiylashtirish va topshirishga mas\'ul bo\'lgan shaxsning F.I.SH, passport ma\'lumotlari hamda telefon raqami: ', 'Temirov Shukrullo Xonimqulovich AC 2275461 +998995721774');

            $section->addTextBreak(2);
            $signTable = $section->addTable();
            $signTable->addRow();
            $signTable->addCell(4500)->addText('Direktor', ['bold' => true, 'size' => 13]);
            $signTable->addCell(4500)->addText('F.A.Otamuradov', ['bold' => true, 'size' => 13], ['alignment' => Jc::END]);

            $section->addTextBreak(2);
            $section->addText('Ijrochi:Temirov.Sh', ['size' => 10]);
            $section->addText('Tel:+998995721774', ['size' => 10]);
        }

        $fileName = 'viza_talabnoma_' . now()->format('Y_m_d') . '.docx';
        $temp = tempnam(sys_get_temp_dir(), 'word');
        $writer = IOFactory::createWriter($word, 'Word2007');
        $writer->save($temp);

        return response()->download($temp, $fileName)->deleteFileAfterSend(true);
    }

    /**
     * Word talabnoma uchun yordamchi: qalin sarlavha + oddiy qiymat.
     */
    private function addField($section, string $label, string $value): void
    {
        $run = $section->addTextRun();
        $run->addText($label, ['bold' => true, 'size' => 11]);
        $run->addText($value, ['size' => 11]);
    }

    /**
     * Talabaga firma biriktirish (dekan yoki admin).
     */
    public function assignFirm(Request $request, Student $student)
    {
        $request->validate([
            'firm' => 'required|string|max:255',
            'firm_custom' => 'nullable|string|max:255',
        ]);

        $visaInfo = StudentVisaInfo::firstOrCreate(
            ['student_id' => $student->id],
            ['birth_country' => $student->country_name, 'birth_region' => $student->province_name, 'birth_city' => $student->district_name, 'birth_date' => $student->birth_date]
        );

        $firm = $request->firm;
        $firmCustom = null;
        if ($firm === 'other' && $request->filled('firm_custom')) {
            $firmCustom = $request->firm_custom;
        }

        $visaInfo->update([
            'firm' => $firm,
            'firm_custom' => $firmCustom,
        ]);

        return redirect()->back()->with('success', $student->full_name . ' uchun firma biriktirildi.');
    }

    /**
     * Admin talaba viza ma'lumotlarini o'chiradi.
     */
    public function destroyVisaInfo(Request $request, Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        // Yuklangan fayllarni o'chirish
        foreach (['passport_scan_path', 'visa_scan_path', 'registration_doc_path'] as $field) {
            if ($visaInfo->$field) {
                \Storage::disk('public')->delete($visaInfo->$field);
            }
        }

        $visaInfo->delete();

        $this->notifyStudent($student, 'Viza ma\'lumotlaringiz admin tomonidan o\'chirildi. Qaytadan kiritishingiz kerak.');

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Talaba viza ma\'lumotlari o\'chirildi.');
    }

    public function showFile(Student $student, string $field)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $allowed = ['passport_scan_path', 'visa_scan_path', 'registration_doc_path'];
        if (!in_array($field, $allowed) || !$visaInfo->$field) {
            abort(404);
        }

        return \Storage::disk('public')->response($visaInfo->$field);
    }

    public function export(Request $request)
    {
        return Excel::download(
            new InternationalStudentsExport($request->all()),
            'xalqaro_talabalar_' . now()->format('Y_m_d') . '.xlsx'
        );
    }
}
