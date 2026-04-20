<?php

namespace App\Http\Controllers;

use App\Models\StaffEvaluation;
use App\Models\Teacher;
use Illuminate\Http\Request;

class StaffEvaluateController extends Controller
{
    public function form(string $token)
    {
        $teacher = Teacher::where('eval_qr_token', $token)->firstOrFail();

        return view('staff-evaluate', compact('teacher', 'token'));
    }

    public function submit(Request $request, string $token)
    {
        $teacher = Teacher::where('eval_qr_token', $token)->firstOrFail();

        $validated = $request->validate([
            'rating'  => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
        ]);

        StaffEvaluation::create([
            'teacher_id' => $teacher->id,
            'student_id' => auth()->guard('student')->id(),
            'rating'     => $validated['rating'],
            'comment'    => $validated['comment'] ?? null,
            'ip_address' => $request->ip(),
        ]);

        return back()->with('success', 'Bahoingiz qabul qilindi. Rahmat!');
    }
}
