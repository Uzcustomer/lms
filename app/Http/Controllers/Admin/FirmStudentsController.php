<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\StudentVisaInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class FirmStudentsController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('student_visa_infos')) {
            return redirect()->route('admin.dashboard')
                ->with('error', 'Iltimos, avval migratsiyani bajaring: php artisan migrate');
        }

        // Web yoki Teacher guarddan firmani aniqlash
        $assignedFirm = null;
        if (auth()->guard('web')->check()) {
            $assignedFirm = auth()->guard('web')->user()->assigned_firm;
        } elseif (auth()->guard('teacher')->check()) {
            $assignedFirm = auth()->guard('teacher')->user()->assigned_firm;
        }

        if (!$assignedFirm) {
            abort(403, 'Sizga firma biriktirilmagan.');
        }

        $query = Student::whereHas('visaInfo', function ($q) use ($assignedFirm) {
            $q->where('firm', $assignedFirm);
        })->with('visaInfo');

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

        if ($request->filled('data_status')) {
            $status = $request->data_status;
            if ($status === 'approved') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'approved'));
            } elseif ($status === 'pending') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'pending'));
            } elseif ($status === 'rejected') {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', 'rejected'));
            }
        }

        $students = $query->orderBy('full_name')
            ->paginate(25)
            ->withQueryString();

        $firmName = StudentVisaInfo::FIRM_OPTIONS[$assignedFirm] ?? $assignedFirm;

        return view('admin.firm-students.index', compact('students', 'firmName', 'assignedFirm'));
    }

    private function getAssignedFirm(): ?string
    {
        if (auth()->guard('web')->check()) {
            return auth()->guard('web')->user()->assigned_firm;
        } elseif (auth()->guard('teacher')->check()) {
            return auth()->guard('teacher')->user()->assigned_firm;
        }
        return null;
    }

    public function show(Student $student)
    {
        $assignedFirm = $this->getAssignedFirm();

        $visaInfo = StudentVisaInfo::where('student_id', $student->id)->first();

        // Faqat o'z firmasidagi talabalarni ko'rish mumkin
        if (!$visaInfo || $visaInfo->firm !== $assignedFirm) {
            abort(403, 'Bu talaba sizning firmangizga tegishli emas.');
        }

        return view('admin.firm-students.show', compact('student', 'visaInfo'));
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
