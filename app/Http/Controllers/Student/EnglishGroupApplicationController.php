<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class EnglishGroupApplicationController extends Controller
{
    public function create()
    {
        $student = auth('student')->user();
        if (!$student) {
            abort(401);
        }

        $applications = InglizGuruhAriza::where('student_hemis_id', $student->hemis_id)
            ->latest()
            ->get();
        $latest = $applications->first();
        $canResubmit = $latest
            && $latest->status === 'rejected'
            && $latest->rejection_reason_code !== 'interview_failed';
        $showForm = $latest === null || ($canResubmit && request()->boolean('resubmit'));

        return view('student.english-group-application.create', [
            'student' => $student,
            'applications' => $applications,
            'latest' => $latest,
            'canSubmit' => $showForm,
            'canResubmit' => $canResubmit,
            'englishLevels' => [
                'boshlangich' => "Boshlang'ich",
                'orta' => "O'rta",
                'mukammal' => 'Mukammal',
            ],
        ]);
    }

    public function store(Request $request)
    {
        $student = auth('student')->user();
        if (!$student) {
            abort(401);
        }

        $existing = InglizGuruhAriza::where('student_hemis_id', $student->hemis_id)->latest()->first();
        if ($existing && ($existing->status !== 'rejected' || $existing->rejection_reason_code === 'interview_failed')) {
            return redirect()
                ->route('student.english-group-application.create')
                ->with('success', "Ingliz tili guruhiga o'tish uchun topshirgan arizangiz muvaffaqqiyatli qabul qilindi. Til sertifikati bo'lmagan talabalar ingliz tilida suhbat asosida qabul qilinadi.");
        }

        $data = $request->validate([
            'english_level' => 'required|in:boshlangich,orta,mukammal',
            'phone_number' => 'nullable|string|max:50',
            'certificate_pdf' => 'nullable|file|mimes:pdf|max:2048',
        ], [
            'english_level.required' => "Ingliz tilini bilish darajasi tanlanishi shart.",
            'english_level.in' => "Ingliz tili darajasi noto'g'ri tanlangan.",
            'phone_number.max' => "Telefon raqam juda uzun.",
            'certificate_pdf.mimes' => 'Til sertifikati faqat PDF formatda yuklanadi.',
            'certificate_pdf.max' => 'Til sertifikati 2 MB dan oshmasligi kerak.',
        ]);

        $application = InglizGuruhAriza::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'full_name' => $student->full_name,
            'phone_number' => $data['phone_number'] ?? $student->phone,
            'faculty_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'course_name' => $student->level_name,
            'semester_name' => $student->semester_name,
            'group_name' => $student->group_name,
            'english_level' => $data['english_level'],
            'status' => 'pending',
        ]);

        if ($request->hasFile('certificate_pdf')) {
            $dir = "english-group-applications/{$application->id}";
            $path = $request->file('certificate_pdf')->storeAs(
                $dir,
                'til_sertifikati.' . $request->file('certificate_pdf')->getClientOriginalExtension()
            );

            $application->update([
                'certificate_pdf_path' => $path,
            ]);
        }

        return redirect()
            ->route('student.english-group-application.create')
            ->with('success', "Ingliz tili guruhiga o'tish uchun topshirgan arizangiz muvaffaqqiyatli qabul qilindi. Til sertifikati bo'lmagan talabalar ingliz tilida suhbat asosida qabul qilinadi.");
    }

    public function certificate(int $id)
    {
        $student = auth('student')->user();
        if (!$student) {
            abort(401);
        }

        $application = InglizGuruhAriza::where('id', $id)
            ->where('student_hemis_id', $student->hemis_id)
            ->firstOrFail();

        abort_if(!$application->certificate_pdf_path || !Storage::exists($application->certificate_pdf_path), 404);

        return response()->file(Storage::path($application->certificate_pdf_path), [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="til_sertifikati.pdf"',
        ]);
    }
}
