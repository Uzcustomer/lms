<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentDisabilityInfo;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

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

        $data = $request->validate([
            'examined_at' => 'required|date|before_or_equal:today',
            'disability_group' => 'required|string|max:50',
            'disability_reason' => 'required|string|max:500',
            'disability_duration' => 'required|string|max:100',
            'reexamination_at' => 'nullable|date|after:examined_at',
        ], [
            'examined_at.required' => "Ko'rikdan o'tgan sanani kiriting.",
            'examined_at.before_or_equal' => "Ko'rik sanasi bugungi kundan keyin bo'lmasligi kerak.",
            'disability_group.required' => "Nogironlik guruhini tanlang.",
            'disability_reason.required' => "Nogironlik sababini kiriting.",
            'disability_duration.required' => "Nogironlik muddatini kiriting.",
            'reexamination_at.after' => "Qayta ko'rik sanasi ko'rikdan o'tgan sanadan keyin bo'lishi kerak.",
        ]);

        StudentDisabilityInfo::updateOrCreate(
            ['student_id' => $student->id],
            $data
        );

        $redirect = $request->input('_redirect_back')
            ? redirect()->back()
            : redirect()->route('student.disability-info.index');

        return $redirect->with('success', "Nogironlik ma'lumotlari saqlandi.");
    }
}
