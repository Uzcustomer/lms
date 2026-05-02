<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentDisabilityInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class StudentDisabilityController extends Controller
{
    public function index()
    {
        if (!Schema::hasTable('student_disability_infos')) {
            return redirect()->route('student.dashboard')
                ->with('error', "Nogironlik bo'limi hali faollashtirilmagan.");
        }

        $student = Auth::guard('student')->user();

        if (!$student->isDisabled()) {
            return redirect()->route('student.dashboard')
                ->with('error', "Bu bo'lim faqat nogiron talabalarga mo'ljallangan.");
        }

        $disabilityInfo = StudentDisabilityInfo::where('student_id', $student->id)->first();

        return view('student.disability-info', compact('disabilityInfo', 'student'));
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        if (!$student->isDisabled()) {
            return redirect()->route('student.dashboard')
                ->with('error', "Bu bo'lim faqat nogiron talabalarga mo'ljallangan.");
        }

        $existing = StudentDisabilityInfo::where('student_id', $student->id)->first();
        $hasCertificateColumn = Schema::hasColumn('student_disability_infos', 'certificate_path');

        $certificateRule = 'nullable|file|mimes:pdf|max:5120';
        if ($hasCertificateColumn && (!$existing || !$existing->certificate_path)) {
            $certificateRule = 'required|file|mimes:pdf|max:5120';
        }

        $rules = [
            'examined_at' => 'required|date|before_or_equal:today',
            'disability_group' => 'required|string|max:50',
            'disability_reason' => 'required|string|max:500',
            'disability_duration' => 'required|string|max:100',
            'reexamination_at' => 'nullable|date|after:examined_at',
        ];
        if ($hasCertificateColumn) {
            $rules['certificate'] = $certificateRule;
        }

        $data = $request->validate($rules, [
            'examined_at.required' => "Ko'rikdan o'tgan sanani kiriting.",
            'examined_at.before_or_equal' => "Ko'rik sanasi bugungi kundan keyin bo'lmasligi kerak.",
            'disability_group.required' => "Nogironlik guruhini tanlang.",
            'disability_reason.required' => "Nogironlik sababini kiriting.",
            'disability_duration.required' => "Nogironlik muddatini kiriting.",
            'reexamination_at.after' => "Qayta ko'rik sanasi ko'rikdan o'tgan sanadan keyin bo'lishi kerak.",
            'certificate.required' => "Nogironlik malumotnomasini PDF shaklida yuklang.",
            'certificate.mimes' => "Malumotnoma faqat PDF formatida bo'lishi kerak.",
            'certificate.max' => "Fayl hajmi 5MB dan oshmasligi kerak.",
        ]);

        $payload = [
            'examined_at' => $data['examined_at'],
            'disability_group' => $data['disability_group'],
            'disability_reason' => $data['disability_reason'],
            'disability_duration' => $data['disability_duration'],
            'reexamination_at' => $data['reexamination_at'] ?? null,
        ];

        if ($hasCertificateColumn && $request->hasFile('certificate')) {
            $payload['certificate_path'] = $request->file('certificate')
                ->store('student-disability/' . $student->id, 'public');
        }

        StudentDisabilityInfo::updateOrCreate(
            ['student_id' => $student->id],
            $payload
        );

        $redirect = $request->input('_redirect_back')
            ? redirect()->back()
            : redirect()->route('student.disability-info.index');

        return $redirect->with('success', "Nogironlik ma'lumotlari saqlandi.");
    }

    public function showFile()
    {
        $student = Auth::guard('student')->user();
        $info = StudentDisabilityInfo::where('student_id', $student->id)->firstOrFail();

        if (!$info->certificate_path) {
            abort(404);
        }

        return Storage::disk('public')->response($info->certificate_path);
    }
}
