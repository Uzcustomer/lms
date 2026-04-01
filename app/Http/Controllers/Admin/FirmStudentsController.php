<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentNotification;
use App\Models\StudentVisaInfo;
use App\Services\TelegramService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FirmStudentsController extends Controller
{
    private function getAssignedFirm(): ?string
    {
        if (auth()->guard('web')->check()) {
            return auth()->guard('web')->user()->assigned_firm;
        } elseif (auth()->guard('teacher')->check()) {
            return auth()->guard('teacher')->user()->assigned_firm;
        }
        return null;
    }

    public function index(Request $request)
    {
        if (!Schema::hasTable('student_visa_infos')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        $assignedFirm = $this->getAssignedFirm();
        if (!$assignedFirm) {
            abort(403, 'Sizga firma biriktirilmagan.');
        }

        $query = Student::where('group_name', 'like', 'xd%')
            ->whereHas('visaInfo', fn($vq) => $vq->where('firm', $assignedFirm))
            ->with('visaInfo');

        if ($request->filled('search')) {
            $query->where('full_name', 'like', '%' . $request->search . '%');
        }
        if ($request->filled('level_code')) {
            $query->where('level_code', $request->level_code);
        }
        if ($request->filled('group_name')) {
            $query->where('group_name', 'like', '%' . $request->group_name . '%');
        }

        $students = $query->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        $firmName = StudentVisaInfo::FIRM_OPTIONS[$assignedFirm] ?? $assignedFirm;

        return view('admin.firm-students.index', compact('students', 'firmName', 'assignedFirm'));
    }

    public function show(Student $student)
    {
        $assignedFirm = $this->getAssignedFirm();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();

        if ($visaInfo && $visaInfo->firm !== $assignedFirm) {
            abort(403, 'Bu talaba sizning firmangizga tegishli emas.');
        }

        return view('admin.firm-students.show', compact('student', 'visaInfo'));
    }

    /**
     * Firma javobgari pasport qabul qiladi.
     */
    public function acceptPassport(Request $request, Student $student)
    {
        $request->validate(['process_type' => 'required|in:registration,visa']);
        $assignedFirm = $this->getAssignedFirm();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        if ($visaInfo->firm !== $assignedFirm) {
            abort(403);
        }

        $field = $request->process_type === 'registration' ? 'registration_process_status' : 'visa_process_status';

        $updates = [
            $field => StudentVisaInfo::PROCESS_PASSPORT_ACCEPTED,
            'passport_handed_over' => true,
            'passport_handed_at' => now(),
        ];

        // Viza ustun: agar viza uchun pasport qabul qilinsa, registratsiya ham birga yangilanadi
        if ($request->process_type === 'visa') {
            $updates['registration_process_status'] = StudentVisaInfo::PROCESS_PASSPORT_ACCEPTED;
        }

        $visaInfo->update($updates);

        StudentNotification::create([
            'student_id' => $student->id,
            'type' => 'system',
            'title' => 'Pasportingiz qabul qilindi',
            'message' => 'Pasportingiz firma tomonidan qabul qilindi. Jarayon boshlandi.',
        ]);

        if ($student->telegram_chat_id) {
            app(TelegramService::class)->sendToUser($student->telegram_chat_id,
                "Pasportingiz firma tomonidan qabul qilindi. Jarayon boshlandi.");
        }

        return redirect()->route('admin.firm-students.show', $student)
            ->with('success', 'Pasport qabul qilindi.');
    }

    public function showFile(Student $student, string $field)
    {
        $assignedFirm = $this->getAssignedFirm();
        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->firstOrFail();

        if ($visaInfo->firm !== $assignedFirm) {
            abort(403);
        }

        $allowed = ['passport_scan_path', 'visa_scan_path', 'registration_doc_path'];
        if (!in_array($field, $allowed) || !$visaInfo->$field) {
            abort(404);
        }

        return \Storage::disk('public')->response($visaInfo->$field);
    }
}
