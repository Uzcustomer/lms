<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\VisaApplication;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VisaApplicationController extends Controller
{
    /**
     * Faqat xalqaro talabalar uchun: xd*, citizenship "orijiy" yoki
     * xalqaro ta'lim fakulteti talabalari.
     */
    private function ensureInternational($student): void
    {
        $isXd = str_starts_with(mb_strtolower($student->group_name ?? ''), 'xd')
            || str_contains(mb_strtolower($student->citizenship_name ?? ''), 'orijiy')
            || str_contains(mb_strtolower($student->department_name ?? ''), 'xalqaro');
        if (!$isXd) {
            abort(403, "This service is only available for international students.");
        }
    }

    public function create()
    {
        $student = auth('student')->user();
        if (!$student) abort(401);
        $this->ensureInternational($student);

        // Mavjud arizalar (talaba o'z statusini ko'rishi uchun)
        $applications = VisaApplication::where('student_hemis_id', $student->hemis_id)
            ->latest()
            ->get();

        return view('student.visa-application.create', [
            'student'      => $student,
            'applications' => $applications,
        ]);
    }

    public function store(Request $request)
    {
        $student = auth('student')->user();
        if (!$student) abort(401);
        $this->ensureInternational($student);

        $data = $request->validate([
            'student_number'  => 'required|string|max:50',
            'last_name'       => 'required|string|max:100',
            'first_name'      => 'required|string|max:100',
            'middle_name'     => 'nullable|string|max:100',
            'birth_date'      => 'required|date',
            'passport_number' => 'required|string|max:50',
            'phone_number'    => 'required|string|max:30',
            'phone_dial_code' => 'nullable|string|max:8',
            'phone_country_iso2' => 'nullable|string|max:4',
            'passport_pdf'    => 'required|file|mimes:pdf|max:5120',     // 5 MB
            'application_pdf' => 'required|file|mimes:pdf|max:256',      // 256 KB
        ], [
            'passport_pdf.max'    => 'Passport PDF must not exceed 5 MB.',
            'application_pdf.max' => 'Application PDF must not exceed 256 KB.',
        ]);

        // Application number — 4 xonali, unique
        $appNumber = $this->generateApplicationNumber();

        $app = VisaApplication::create([
            'student_id'         => $student->id,
            'student_hemis_id'   => $student->hemis_id,
            'student_number'     => $data['student_number'],
            'last_name'          => mb_strtoupper($data['last_name']),
            'first_name'         => mb_strtoupper($data['first_name']),
            'middle_name'        => isset($data['middle_name']) ? mb_strtoupper($data['middle_name']) : null,
            'birth_date'         => $data['birth_date'],
            'passport_number'    => mb_strtoupper($data['passport_number']),
            'phone_number'       => $data['phone_number'],
            'phone_dial_code'    => $data['phone_dial_code'] ?? null,
            'phone_country_iso2' => $data['phone_country_iso2'] ?? null,
            'application_number' => $appNumber,
            'status'             => 'pending',
        ]);

        // Fayllarni saqlash: storage/app/visa-applications/{id}/...
        $dir = "visa-applications/{$app->id}";
        $passportPath = $request->file('passport_pdf')->storeAs(
            $dir,
            "passport_{$appNumber}." . $request->file('passport_pdf')->getClientOriginalExtension()
        );
        $applicationPath = $request->file('application_pdf')->storeAs(
            $dir,
            "application_{$appNumber}." . $request->file('application_pdf')->getClientOriginalExtension()
        );

        $app->update([
            'passport_pdf_path'    => $passportPath,
            'application_pdf_path' => $applicationPath,
        ]);

        return response()->json([
            'ok' => true,
            'application_number' => $appNumber,
            'verify_url' => url('/visa-application/verify?app=' . $appNumber),
            'message' => "Application submitted successfully.",
        ]);
    }

    private function generateApplicationNumber(): string
    {
        for ($i = 0; $i < 20; $i++) {
            $n = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
            if (!VisaApplication::where('application_number', $n)->exists()) {
                return $n;
            }
        }
        // Fallback — UUID prefix
        return mb_substr(Str::uuid()->toString(), 0, 4);
    }
}
