<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Exports\InternationalStudentsExport;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Maatwebsite\Excel\Facades\Excel;

class InternationalStudentController extends Controller
{
    /**
     * Xalqaro talabalar fakulteti talabalari ro'yxatini filtrlash uchun
     * "Xalqaro ta'lim" so'zi bo'lgan yoki citizenship_code 'UZ' bo'lmagan talabalar.
     */
    private function internationalStudentsQuery()
    {
        return Student::where(function ($q) {
            $q->where('department_name', 'like', '%alqaro%')
              ->orWhere(function ($q2) {
                  $q2->whereNotNull('citizenship_code')
                      ->where('citizenship_code', '!=', '')
                      ->where('citizenship_code', '!=', 'UZ');
              });
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

        if ($request->filled('visa_expiry')) {
            $days = (int) $request->visa_expiry;
            $query->whereHas('visaInfo', function ($q) use ($days) {
                $q->whereNotNull('visa_end_date')
                  ->whereDate('visa_end_date', '<=', now()->addDays($days));
            });
        }

        if ($request->filled('registration_expiry')) {
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

        return view('admin.international-students.index', compact('students', 'firms'));
    }

    public function show(Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();

        return view('admin.international-students.show', compact('student', 'visaInfo'));
    }

    public function approve(Request $request, Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $visaInfo->update([
            'status' => 'approved',
            'rejection_reason' => null,
            'reviewed_by' => auth()->id(),
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
            'reviewed_by' => auth()->id(),
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

    public function confirmPassportHandover(Request $request, Student $student)
    {
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        $visaInfo->update([
            'passport_handed_over' => true,
            'passport_handed_at' => now(),
            'passport_received_by' => auth()->id(),
        ]);

        return redirect()->route('admin.international-students.show', $student)
            ->with('success', 'Pasport qabul qilindi.');
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
