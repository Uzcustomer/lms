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

        // False show global
        $falseShowEnabled = \App\Models\Setting::get('false_show_enabled', '0') === '1';

        // Filterlash
        if ($request->filled('search')) {
            $query->where('full_name', 'like', '%' . $request->search . '%');
        }

        if ($request->filled('level_code')) {
            $query->where('level_code', (string) $request->level_code);
        }

        if ($request->filled('group_name')) {
            $query->where('group_name', 'like', '%' . $request->group_name . '%');
        }

        if ($request->filled('firm')) {
            if ($request->firm === 'none') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('visaInfo')
                      ->orWhereHas('visaInfo', fn($vq) => $vq->whereNull('firm')->orWhere('firm', ''));
                });
            } else {
                $query->whereHas('visaInfo', fn($q) => $q->where('firm', $request->firm));
            }
        }

        if ($request->filled('country')) {
            $query->where('country_name', $request->country);
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('data_status')) {
            if ($request->data_status === 'filled') {
                $query->whereHas('visaInfo', fn($q) => $q->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date')));
            } elseif ($request->data_status === 'not_filled') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('visaInfo')
                      ->orWhereHas('visaInfo', fn($q2) => $q2->whereNull('passport_number')->whereNull('visa_number')->whereNull('registration_end_date'));
                });
            } elseif ($request->data_status === 'approved') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'approved')->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date')));
            } elseif ($request->data_status === 'pending') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'pending')->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date')));
            } elseif ($request->data_status === 'rejected') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'rejected')->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date')));
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

        // Excel-style ustun filtri: aniq tanlangan sanalar
        if ($request->filled('visa_end_dates')) {
            $dates = array_filter((array) $request->visa_end_dates);
            if (!empty($dates)) {
                $query->whereHas('visaInfo', fn($q) => $q->whereIn('visa_end_date', $dates));
            }
        }

        if ($request->filled('registration_end_dates')) {
            $dates = array_filter((array) $request->registration_end_dates);
            if (!empty($dates)) {
                $query->whereHas('visaInfo', fn($q) => $q->whereIn('registration_end_date', $dates));
            }
        }

        if ($request->filled('hemis_status')) {
            if ($request->hemis_status === 'inactive') {
                $query->where('student_status_code', '60');
            } elseif ($request->hemis_status === 'active') {
                $query->where('student_status_code', '!=', '60');
            }
        }

        // Filtrlangan query klon — statistika uchun
        $filteredIds = (clone $query)->pluck('students.id');

        // False show: kiritmaganlarni oxirga tushirish
        if ($falseShowEnabled) {
            $query->orderByRaw("CASE WHEN id NOT IN (SELECT student_id FROM student_visa_infos WHERE passport_number IS NOT NULL OR visa_number IS NOT NULL OR registration_end_date IS NOT NULL) THEN 1 ELSE 0 END");
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

        // Excel-style ustun filtri uchun mavjud sanalar ro'yxati
        $intStudentIds = (clone $baseQuery)->pluck('students.id');
        $visaEndDates = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('visa_end_date')
            ->distinct()
            ->orderBy('visa_end_date')
            ->pluck('visa_end_date');
        $regEndDates = StudentVisaInfo::whereIn('student_id', $intStudentIds)
            ->whereNotNull('registration_end_date')
            ->distinct()
            ->orderBy('registration_end_date')
            ->pluck('registration_end_date');

        // Statistika — filtrlangan natijaga asoslangan
        $totalFiltered = $filteredIds->count();
        $allVisas = StudentVisaInfo::whereIn('student_id', $filteredIds);
        // Haqiqiy ma'lumot kiritganlar (faqat firma emas)
        $realFilledCount = (clone $allVisas)->where(function ($q) {
            $q->whereNotNull('passport_number')
              ->orWhereNotNull('visa_number')
              ->orWhereNotNull('registration_end_date');
        })->count();
        if ($falseShowEnabled) {
            $filledCount = $totalFiltered;
            $notFilledCount = 0;
        } else {
            $filledCount = $realFilledCount;
            $notFilledCount = $totalFiltered - $filledCount;
        }
        $realDataFilter = function ($q) {
            $q->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date'));
        };
        $approvedCount = (clone $allVisas)->where('status', 'approved')->where($realDataFilter)->count();
        $pendingCount = (clone $allVisas)->where('status', 'pending')->where($realDataFilter)->count();
        $rejectedCount = (clone $allVisas)->where('status', 'rejected')->where($realDataFilter)->count();

        $visaUrgentCount = StudentVisaInfo::whereIn('student_id', $filteredIds)
            ->whereNotNull('visa_end_date')
            ->whereDate('visa_end_date', '<=', now()->addDays(30))
            ->whereDate('visa_end_date', '>=', now())
            ->count();
        $regUrgentCount = StudentVisaInfo::whereIn('student_id', $filteredIds)
            ->whereNotNull('registration_end_date')
            ->whereDate('registration_end_date', '<=', now()->addDays(7))
            ->whereDate('registration_end_date', '>=', now())
            ->count();
        $expiredVisaCount = StudentVisaInfo::whereIn('student_id', $filteredIds)
            ->whereNotNull('visa_end_date')
            ->whereDate('visa_end_date', '<', now())
            ->count();
        $expiredRegCount = StudentVisaInfo::whereIn('student_id', $filteredIds)
            ->whereNotNull('registration_end_date')
            ->whereDate('registration_end_date', '<', now())
            ->count();

        $totalIntStudents = $totalFiltered;
        $stats = compact(
            'totalIntStudents', 'filledCount', 'notFilledCount',
            'approvedCount', 'pendingCount', 'rejectedCount',
            'visaUrgentCount', 'regUrgentCount', 'expiredVisaCount', 'expiredRegCount'
        );

        // Obuna holati
        $isSubscribed = false;
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if ($user && \Schema::hasTable('visa_notification_subscribers')) {
            $isSubscribed = \DB::table('visa_notification_subscribers')
                ->where('subscribable_type', get_class($user))
                ->where('subscribable_id', $user->id)
                ->exists();
        }

        return view('admin.international-students.index', compact('students', 'firms', 'stats', 'countries', 'departments', 'isSubscribed', 'falseShowEnabled', 'visaEndDates', 'regEndDates'));
    }

    /**
     * False show: global yoqish/o'chirish.
     */
    /**
     * Qizil holatdagi talabalarga bildirishnoma yuborish.
     */
    public function notifyDanger()
    {
        $telegram = app(TelegramService::class);
        $sent = 0;

        $visaInfos = StudentVisaInfo::with('student')
            ->where(function ($q) {
                $q->where(function ($q2) {
                    $q2->whereNotNull('registration_end_date')
                       ->whereDate('registration_end_date', '<=', now()->addDays(3));
                })->orWhere(function ($q2) {
                    $q2->whereNotNull('visa_end_date')
                       ->whereDate('visa_end_date', '<=', now()->addDays(15));
                });
            })->get();

        foreach ($visaInfos as $info) {
            $student = $info->student;
            if (!$student) continue;

            $parts = [];
            $regDays = $info->registrationDaysLeft();
            $visaDays = $info->visaDaysLeft();
            $en = str_starts_with(strtolower($student->group_name ?? ''), 'xd') || str_contains(strtolower($student->citizenship_name ?? ''), 'orijiy');

            if ($regDays !== null && $regDays <= 3) {
                $parts[] = $en
                    ? ($regDays <= 0 ? "Registration expired!" : "Registration expires in {$regDays} days!")
                    : ($regDays <= 0 ? "Registratsiya muddati tugagan!" : "Registratsiya muddati tugashiga {$regDays} kun!");
            }
            if ($visaDays !== null && $visaDays <= 15) {
                $parts[] = $en
                    ? ($visaDays <= 0 ? "Visa expired!" : "Visa expires in {$visaDays} days!")
                    : ($visaDays <= 0 ? "Viza muddati tugagan!" : "Viza muddati tugashiga {$visaDays} kun!");
            }

            if (empty($parts)) continue;

            $tail = $en ? "Submit your passport to the registrator office!" : "Pasportingizni registrator ofisiga topshiring!";
            $message = "🔴 " . implode(' ', $parts) . " " . $tail;

            // Sayt bildirishnoma
            StudentNotification::create([
                'student_id' => $student->id,
                'type' => 'system',
                'title' => 'Muddati yaqinlashmoqda!',
                'message' => $message,
                'data' => ['level' => 'danger'],
            ]);

            // Telegram
            if ($student->telegram_chat_id) {
                try { $telegram->sendToUser($student->telegram_chat_id, $message); } catch (\Throwable $e) {}
            }

            $sent++;
        }

        return redirect()->back()->with('success', "{$sent} ta talabaga ogohlantirish yuborildi.");
    }

    public function toggleFalseShow()
    {
        $current = \App\Models\Setting::get('false_show_enabled', '0');
        \App\Models\Setting::set('false_show_enabled', $current === '1' ? '0' : '1');
        return redirect()->back()->with('success', 'False show ' . ($current === '1' ? "o'chirildi" : 'yoqildi'));
    }

    public function statistics()
    {
        $query = $this->internationalStudentsQuery();
        $total = $query->count();

        // Ma'lumot holati
        $allIds = (clone $query)->pluck('id');
        $filled = StudentVisaInfo::whereIn('student_id', $allIds)->count();
        $notFilled = $total - $filled;
        $approved = StudentVisaInfo::whereIn('student_id', $allIds)->where('status', 'approved')->count();
        $pending = StudentVisaInfo::whereIn('student_id', $allIds)->where('status', 'pending')->count();
        $rejected = StudentVisaInfo::whereIn('student_id', $allIds)->where('status', 'rejected')->count();

        // Davlat bo'yicha
        $byCountry = (clone $query)->whereNotNull('country_name')->where('country_name', '!=', '')
            ->selectRaw('country_name, count(*) as cnt')->groupBy('country_name')->orderByDesc('cnt')->limit(15)->pluck('cnt', 'country_name');

        // Kurs bo'yicha
        $byLevel = (clone $query)->whereNotNull('level_name')
            ->selectRaw('level_name, count(*) as cnt')->groupBy('level_name')->orderBy('level_name')->pluck('cnt', 'level_name');

        // Fakultet bo'yicha
        $byDept = (clone $query)->whereNotNull('department_name')->where('department_name', '!=', '')
            ->selectRaw('department_name, count(*) as cnt')->groupBy('department_name')->orderByDesc('cnt')->limit(10)->pluck('cnt', 'department_name');

        // Firma bo'yicha
        $byFirm = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('firm')->where('firm', '!=', '')
            ->selectRaw('firm, count(*) as cnt')->groupBy('firm')->orderByDesc('cnt')->pluck('cnt', 'firm');

        // Viza muddati
        $visaExpired = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('visa_end_date')->whereDate('visa_end_date', '<', now())->count();
        $visa30 = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('visa_end_date')->whereDate('visa_end_date', '>=', now())->whereDate('visa_end_date', '<=', now()->addDays(30))->count();
        $visaOk = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('visa_end_date')->whereDate('visa_end_date', '>', now()->addDays(30))->count();

        // Registratsiya muddati
        $regExpired = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('registration_end_date')->whereDate('registration_end_date', '<', now())->count();
        $reg7 = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('registration_end_date')->whereDate('registration_end_date', '>=', now())->whereDate('registration_end_date', '<=', now()->addDays(7))->count();
        $regOk = StudentVisaInfo::whereIn('student_id', $allIds)->whereNotNull('registration_end_date')->whereDate('registration_end_date', '>', now()->addDays(7))->count();

        return view('admin.international-students.statistics', compact(
            'total', 'filled', 'notFilled', 'approved', 'pending', 'rejected',
            'byCountry', 'byLevel', 'byDept', 'byFirm',
            'visaExpired', 'visa30', 'visaOk', 'regExpired', 'reg7', 'regOk'
        ));
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

        $visaInfo->update([$field => StudentVisaInfo::PROCESS_REGISTERING]);

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
            $updates['visa_process_status'] = StudentVisaInfo::PROCESS_DONE;
            $updates['visa_start_date'] = null;
            $updates['visa_end_date'] = null;
            $updates['visa_number'] = null;
            $updates['visa_type'] = null;
            $updates['visa_scan_path'] = null;
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

    private function entriesText(int $count): string
    {
        return match($count) {
            1 => 'BIR martalik',
            2 => 'IKKI martalik',
            3 => 'UCH martalik',
            4 => "TO'RT martalik",
            5 => 'BESH martalik',
            99 => "KO'P martalik",
            default => $count . ' martalik',
        };
    }

    /**
     * Registratsiya talabnoma Word yaratish.
     */
    public function registrationTalabnoma(Request $request)
    {
        $request->validate(['student_ids' => 'required|array|min:1', 'reg_months' => 'required|integer']);
        $students = Student::whereIn('id', $request->student_ids)->with('visaInfo')->get();
        $regMonths = (int) $request->reg_months;

        $word = new PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $word->setDefaultParagraphStyle(['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.0]);

        foreach ($students as $student) {
            $v = $student->visaInfo;
            $n = ['size' => 11];
            $section = $word->addSection(['marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 800, 'marginRight' => 600]);

            $table = $section->addTable();
            $table->addRow();
            $c1 = $table->addCell(4500);
            $c1->addText('Hurmatli S.Eshqobilov', ['italic' => true, 'size' => 11]);
            $c1->addText('qonuniy xal qiling', ['italic' => true, 'size' => 11]);
            $c2 = $table->addCell(4500);
            $c2->addText('Termiz Shahar IIB M va FRB', ['size' => 11], ['alignment' => Jc::END]);
            $c2->addText("boshlig'i podpolkovnik", ['size' => 11], ['alignment' => Jc::END]);
            $c2->addText('S. S. Kabilovga', ['size' => 11], ['alignment' => Jc::END]);

            $section->addText('TALABNOMA', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
            $section->addText("Toshkent davlat tibbiyot universiteti Termiz filiali Sizdan quyidagi chet el fuqarosi yoki fuqaroligi bo'lmagan shaxsni vaqtincha ro'yxatga olishni (vaqtincha ro'yxat muddatini uzaytirishni) so'raydi:", $n, ['alignment' => Jc::BOTH]);

            $bp = ($v?->birth_city ?? '___') . ', ' . ($v?->birth_region ?? '');
            $entries = $v?->visa_entries_count ? $this->entriesText($v->visa_entries_count) : '___';
            $vizaStr = ($v?->visa_type ?? '___') . ';№ ' . ($v?->visa_number ?? '___') . ';  ' . $entries;
            $vizaGiven = ($v?->visa_issued_place ?? '___') . ' (' . ($v?->visa_type ?? '') . ', № ' . ($v?->visa_number ?? '') . '; ' . ($v?->visa_start_date?->format('d.m.Y') ?? '___') . ' dan ' . ($v?->visa_end_date?->format('d.m.Y') ?? '___') . ' gacha)';

            $this->addField($section, '1. F.I.O: ', strtoupper($student->full_name));
            $section->addText("(chet el fuqarosi yoki fuqaroligi bo'lmagan shaxsning familiyasi, ismi, otasining ismi hujjat bo'yicha lotinchada yoziladi)", ['size' => 9, 'italic' => true]);
            $this->addField($section, '2. Farzandlari: ', '_______________');
            $section->addText("(16 yoshgacha bo'lgan farzandlarning familiyasi, ismi, otasining ismi tug'ilgan yili)", ['size' => 9, 'italic' => true]);
            $this->addField($section, '3. Fuqaroligi: ', ($student->country_name ?? '___') . '   4. Jinsi: ' . strtoupper($student->gender_name ?? '___'));
            $this->addField($section, "5. Tug'ilgan joyi va sanasi: ", $bp . ',  ' . ($student->birth_date?->format('d.m.Y') ?? '___'));
            $this->addField($section, '6. Ish joyi va lavozimi: ', 'Toshkent davlat tibbiyot universiteti Termiz filiali talaba');
            $this->addField($section, '7. Passport/harakatlanish hujjati: ', $v?->passport_number ?? '___');
            $this->addField($section, '8. Viza turi: ', $vizaStr);
            $this->addField($section, '9. Viza kim tomonidan rasmiylashtirib berilgan va uning muddati: ', $vizaGiven);
            $this->addField($section, "10. So'ralayotgan vaqtincha ro'yxat muddati (kunlarda): ", $regMonths . ' oy');
            $this->addField($section, "11. O'zbekistonga kirib kelgan sanasi (nazorat o'tish punkti): ", $v?->entry_date?->format('d.m.Y') ?? '________');
            $this->addField($section, "12. Vaqtincha yashash manzili: ", "Termiz shahar I.Karimov ko'chasi 64-uy");
            $this->addField($section, "13. Uy joy maydon bergan shaxsning F.I.O: ", "Toshkent davlat tibbiyot universiteti Termiz filiali yotoqxona");
            $section->addText('      Kadastr raqami: 19:15:01:03:01:0704', $n);
            $this->addField($section, "14. Hujjatlarni rasmiylashtirish va taqdim etishga mas'ul bo'lgan shaxsning F.I.O: ", '');
            $section->addText('      Temirov Shukrullo Xonimqulovich', $n);
            $section->addText('      Passport harakatlanish hujjat seriyasi va raqami:  AC 2275461', $n);
            $section->addText('      Xizmat tel raqami_______________ uyali tel. raqami +998995721774', $n);

            $section->addTextBreak(1);
            $st = $section->addTable(); $st->addRow();
            $st->addCell(4500)->addText('Direktor', ['bold' => true, 'size' => 13]);
            $st->addCell(4500)->addText('F.A.Otamuradov', ['bold' => true, 'size' => 13], ['alignment' => Jc::END]);
            $section->addTextBreak(1);
            $section->addText('Ijrochi:Sh.Temirov', ['size' => 10]);
            $section->addText('Tel:+998995721774', ['size' => 10]);
        }

        $temp = tempnam(sys_get_temp_dir(), 'word');
        IOFactory::createWriter($word, 'Word2007')->save($temp);
        return response()->download($temp, 'registratsiya_talabnoma_' . now()->format('Y_m_d') . '.docx')->deleteFileAfterSend(true);
    }

    /**
     * Viza talabnoma Word yaratish.
     */
    public function visaTalabnoma(Request $request)
    {
        $request->validate(['student_ids' => 'required|array|min:1', 'visa_months' => 'required|integer', 'visa_entries' => 'required|integer']);
        $students = Student::whereIn('id', $request->student_ids)->with('visaInfo')->get();
        $visaMonths = (int) $request->visa_months;
        $visaEntries = (int) $request->visa_entries;
        $entriesText = $this->entriesText($visaEntries);

        $word = new PhpWord();
        $word->setDefaultFontName('Times New Roman');
        $word->setDefaultFontSize(12);
        $word->setDefaultParagraphStyle(['spaceAfter' => 0, 'spaceBefore' => 0, 'lineHeight' => 1.0]);

        foreach ($students as $student) {
            $v = $student->visaInfo;
            $n = ['size' => 11];
            $section = $word->addSection(['marginTop' => 600, 'marginBottom' => 600, 'marginLeft' => 800, 'marginRight' => 600]);

            $section->addText("Surxondaryo viloyati IIB Migratsiya va fuqarolikni", ['bold' => true, 'size' => 11], ['alignment' => Jc::END]);
            $section->addText("rasmiylashtirish boshqarmasi boshlig'iga", ['bold' => true, 'size' => 11], ['alignment' => Jc::END]);
            $section->addText('TALABNOMA', ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);

            $bodyRun = $section->addTextRun(['alignment' => Jc::BOTH]);
            $bodyRun->addText("Toshkent davlat tibbiyot universiteti Termiz filiali quyidagi xorijiy talaba vizasining amal qilish muddatini ", $n);
            $bodyRun->addText("{$visaMonths} oy muddatga  {$entriesText}", ['size' => 11, 'bold' => true]);
            $bodyRun->addText(" uzaytirib berishda amaliy yordam berishingizni so'raydi", $n);

            $bp = ($v?->birth_city ?? '___') . ', ' . ($v?->birth_region ?? '');
            $lavozim = 'Toshkent davlat tibbiyot universiteti Termiz filiali ' . ($student->department_name ?? '') . ' "' . ($student->specialty_code ?? '') . '" ' . ($student->level_code ?? '') . '-bosqich talabasi';
            $curEntries = $v?->visa_entries_count ? $this->entriesText($v->visa_entries_count) : '___';
            $vizaStr = ($v?->visa_type ?? '___') . ';№ ' . ($v?->visa_number ?? '___') . '; ' . $curEntries;
            $vizaGiven = ($v?->visa_issued_place ?? '___') . ' (' . ($v?->visa_type ?? '') . ';№ ' . ($v?->visa_number ?? '') . '; ' . ($v?->visa_start_date?->format('d.m.Y') ?? '___') . ' dan ' . ($v?->visa_end_date?->format('d.m.Y') ?? '___') . ' gacha)';

            $r = $section->addTextRun();
            $r->addText('1. F.I.SH: ', $n);
            $r->addText(strtoupper($student->full_name ?? '___'), ['size' => 11, 'bold' => true]);
            $r->addText('   2. Fuqaroligi: ', $n);
            $r->addText(($student->country_name ?? '___') . ' Respublikasi', ['size' => 11, 'bold' => true]);

            $r = $section->addTextRun();
            $r->addText('3. Jinsi: ', $n);
            $r->addText(ucfirst(strtolower($student->gender_name ?? '___')), ['size' => 11, 'bold' => true]);
            $r->addText("   4. Tug'ilgan sanasi: ", $n);
            $r->addText($student->birth_date?->format('d.m.Y') ?? '___', ['size' => 11, 'bold' => true]);

            $this->addField($section, "5. Tug'ilgan joyi: ", strtoupper($bp), true);
            $this->addField($section, '6. Ish joyi va lavozimi: ', $lavozim, true);

            $r = $section->addTextRun();
            $r->addText('7. Milliy passport: ', $n);
            $r->addText($v?->passport_number ?? '___', ['size' => 11, 'bold' => true]);
            $r->addText('   8. Viza turi raqami hamda safarlar soni: ', $n);
            $r->addText($vizaStr, ['size' => 11, 'bold' => true]);
            $r->addText('   9. Farzandlari: ', $n);
            $r->addText("yo'q", ['size' => 11, 'bold' => true]);

            $this->addField($section, '10. Viza kim tomonidan rasmiylashtirilib berilgan (turi, raqam va amal qilish muddati): ', $vizaGiven, true);
            $this->addField($section, "11. Viza uzaytirish so'ralayotgan muddat (kunlarda) ", $visaMonths . ' oy', true);
            $this->addField($section, "12. Chegara nazorat maskanidan O'zbekiston Respublikasiga kirib kelgan sanasi: ", $v?->entry_date?->format('d.m.Y') ?? '________', true);
            $this->addField($section, "13. Vaqtincha yashash manzili (uy. telefon r.): ", "MA'RIFAT MFY, Islom Karimov ko'chasi, 64-uy", true);
            $this->addField($section, "14. Uy joy taqdim etayotgan shaxs yoki tashkilot nomi: ", "Toshkent davlat tibbiyot universiteti Termiz filiali", true);
            $this->addField($section, "15. TIV akkredatsiyadan o'tgan ro'yxat raqami: ", "yo'q", true);
            $this->addField($section, "16. Adliya Vazirligi yoki Hokimiyatdan o'tgan ro'yxat raqami: ", "yo'q", true);
            $this->addField($section, "17. B va MM vazirligidan o'tgan ro'yxat raqami va muddati: ", "yo'q", true);
            $this->addField($section, "18. Moliya vazirligidan o'tgan yat raqami va muddati: ", "yo'q", true);
            $this->addField($section, "19. Hujjatlarni rasmiylashtirish va topshirishga ma'sul bo'lgan shaxsning F.I.SH, passport ma'lumotlari hamda telefon raqami: ", "Temirov Shukrullo Xonimqulovich AC 2275461  +998995721774", true);

            $section->addTextBreak(1);
            $st = $section->addTable(); $st->addRow();
            $st->addCell(4500)->addText('Direktor', ['bold' => true, 'size' => 13]);
            $st->addCell(4500)->addText('F.A.Otamuradov', ['bold' => true, 'size' => 13], ['alignment' => Jc::END]);
            $section->addTextBreak(1);
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
     * Word talabnoma uchun yordamchi: sarlavha + qiymat.
     */
    private function addField($section, string $label, string $value, bool $boldValue = false): void
    {
        $run = $section->addTextRun();
        $run->addText($label, ['size' => 11]);
        $run->addText($value, ['size' => 11, 'bold' => $boldValue]);
    }

    /**
     * Talabaga firma biriktirish (dekan yoki admin).
     */
    /**
     * Admin/Registrator talaba viza ma'lumotlarini to'ldirish/tahrirlash.
     */
    public function storeVisa(Request $request, Student $student)
    {
        // Sanalarni dd.mm.yyyy / dd/mm/yyyy / dd,mm,yyyy / dd-mm-yyyy formatdan Y-m-d ga o'tkazish
        $dateFields = ['passport_issued_date','passport_expiry_date','registration_start_date','registration_end_date','visa_start_date','visa_end_date','visa_issued_date','entry_date'];
        foreach ($dateFields as $field) {
            $val = $request->input($field);
            if ($val && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $val)) {
                $cleaned = preg_replace('/[\/,\-]/', '.', $val);
                $parts = explode('.', $cleaned);
                if (count($parts) === 3) {
                    $d = (int)$parts[0]; $m = (int)$parts[1]; $y = (int)$parts[2];
                    if ($y < 100) $y += 2000;
                    $request->merge([$field => sprintf('%04d-%02d-%02d', $y, $m, $d)]);
                }
            }
        }

        $request->validate([
            'passport_number' => 'nullable|string|max:50',
            'passport_issued_place' => 'nullable|string|max:255',
            'passport_issued_date' => 'nullable|date',
            'passport_expiry_date' => 'nullable|date',
            'birth_country' => 'nullable|string|max:255',
            'birth_region' => 'nullable|string|max:255',
            'birth_city' => 'nullable|string|max:255',
            'registration_start_date' => 'nullable|date',
            'registration_end_date' => 'nullable|date',
            'visa_number' => 'nullable|string|max:50',
            'visa_type' => 'nullable|string',
            'visa_start_date' => 'nullable|date',
            'visa_end_date' => 'nullable|date',
            'visa_entries_count' => 'nullable|integer',
            'visa_stay_days' => 'nullable|integer',
            'visa_issued_place' => 'nullable|string|max:255',
            'visa_issued_date' => 'nullable|date',
            'entry_date' => 'nullable|date',
        ]);

        $data = $request->only([
            'passport_number', 'passport_issued_place', 'passport_issued_date', 'passport_expiry_date',
            'birth_country', 'birth_region', 'birth_city',
            'registration_start_date', 'registration_end_date',
            'visa_number', 'visa_type', 'visa_start_date', 'visa_end_date',
            'visa_entries_count', 'visa_stay_days', 'visa_issued_place', 'visa_issued_date',
            'entry_date',
        ]);
        $data['birth_date'] = $student->birth_date;
        $data['agreement_accepted'] = true;
        // Mavjud statusni saqlab qolish — faqat approve tugmasi bilan tasdiqlanadi
        $existing = StudentVisaInfo::where('student_id', $student->id)->first();
        $data['status'] = $existing?->status ?? 'pending';

        $columns = \Schema::getColumnListing('student_visa_infos');
        $data = array_intersect_key($data, array_flip($columns));

        // Bo'sh qiymatlarni olib tashlash — eski ma'lumotni o'chirmasligi uchun
        $data = array_filter($data, fn($v) => $v !== null && $v !== '');

        $visaInfo = StudentVisaInfo::updateOrCreate(['student_id' => $student->id], $data);

        // PDF fayllar
        $storagePath = 'student-visa/' . $student->id;
        foreach ([
            'passport_scan' => 'passport_scan_path',
            'visa_scan' => 'visa_scan_path',
            'registration_doc' => 'registration_doc_path',
        ] as $inputName => $dbField) {
            if ($request->hasFile($inputName)) {
                if ($visaInfo->$dbField) {
                    \Storage::disk('public')->delete($visaInfo->$dbField);
                }
                $visaInfo->$dbField = $request->file($inputName)->store($storagePath, 'public');
            }
        }
        $visaInfo->save();

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Viza ma\'lumotlari saqlandi.');
    }

    /**
     * Bir nechta talabaga firma biriktirish.
     */
    public function bulkAssignFirm(Request $request)
    {
        $request->validate(['student_ids' => 'required|array|min:1', 'firm' => 'required|string']);
        $students = Student::whereIn('id', $request->student_ids)->get();

        foreach ($students as $student) {
            $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();
            if ($visaInfo) {
                $visaInfo->update(['firm' => $request->firm]);
            } else {
                StudentVisaInfo::create([
                    'student_id' => $student->id,
                    'firm' => $request->firm,
                    'birth_date' => $student->birth_date,
                    'status' => 'pending',
                ]);
            }
        }

        return redirect()->back()->with('success', count($students) . ' ta talabaga firma biriktirildi.');
    }

    public function assignFirm(Request $request, Student $student)
    {
        $request->validate([
            'firm' => 'required|string|max:255',
            'firm_custom' => 'nullable|string|max:255',
        ]);

        $firm = $request->firm;
        $firmCustom = null;
        if ($firm === 'other' && $request->filled('firm_custom')) {
            $firmCustom = $request->firm_custom;
        }

        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();
        if ($visaInfo) {
            $visaInfo->update(['firm' => $firm, 'firm_custom' => $firmCustom]);
        } else {
            // visaInfo yo'q — faqat firma bilan yaratish, status kiritilmagan qoladi
            StudentVisaInfo::create([
                'student_id' => $student->id,
                'firm' => $firm,
                'firm_custom' => $firmCustom,
                'birth_date' => $student->birth_date,
                'status' => 'pending',
            ]);
        }

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

    public function subscribe()
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user) return redirect()->back();

        \DB::table('visa_notification_subscribers')->updateOrInsert(
            ['subscribable_type' => get_class($user), 'subscribable_id' => $user->id],
            ['telegram_chat_id' => $user->telegram_chat_id ?? null, 'updated_at' => now(), 'created_at' => now()]
        );

        return redirect()->back()->with('success', 'Bildirishnomaga obuna bo\'ldingiz.');
    }

    public function unsubscribe()
    {
        $user = auth()->guard('web')->user() ?? auth()->guard('teacher')->user();
        if (!$user) return redirect()->back();

        \DB::table('visa_notification_subscribers')
            ->where('subscribable_type', get_class($user))
            ->where('subscribable_id', $user->id)
            ->delete();

        return redirect()->back()->with('success', 'Obunadan chiqarildi.');
    }
}
