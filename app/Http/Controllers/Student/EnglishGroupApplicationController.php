<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\InglizGuruhAriza;
use Illuminate\Http\Request;

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
        $canSubmit = $latest === null;

        return view('student.english-group-application.create', [
            'student' => $student,
            'applications' => $applications,
            'latest' => $latest,
            'canSubmit' => $canSubmit,
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
        if ($existing) {
            return redirect()
                ->route('student.english-group-application.create')
                ->with('success', "Ingliz tili guruhiga o'tish uchun topshirgan arizangiz muvaffaqqiyatli qabul qilindi. Til sertifikati bo'lmagan talabalar ingliz tilida suhbat asosida qabul qilinadi.");
        }

        $data = $request->validate([
            'english_level' => 'nullable|in:boshlangich,orta,mukammal',
            'certificate_pdf' => 'nullable|file|mimes:pdf|max:2048',
        ], [
            'english_level.in' => "Ingliz tili darajasi noto'g'ri tanlangan.",
            'certificate_pdf.mimes' => 'Til sertifikati faqat PDF formatda yuklanadi.',
            'certificate_pdf.max' => 'Til sertifikati 2 MB dan oshmasligi kerak.',
        ]);

        $application = InglizGuruhAriza::create([
            'student_id' => $student->id,
            'student_hemis_id' => $student->hemis_id,
            'full_name' => $student->full_name,
            'phone_number' => $student->phone,
            'faculty_name' => $student->department_name,
            'specialty_name' => $student->specialty_name,
            'course_name' => $student->level_name,
            'semester_name' => $student->semester_name,
            'group_name' => $student->group_name,
            'english_level' => $data['english_level'] ?? null,
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
}
