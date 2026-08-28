<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentGroupChangePermission;
use Illuminate\Support\Facades\Auth;

class GroupChangeApplicationController
{
    public function create()
    {
        $student = Auth::guard('student')->user();
        $enabled = StudentGroupChangePermission::query()
            ->where('student_id', $student->id)->where('enabled', true)->exists();

        abort_unless($enabled, 403, 'Bu xizmat uchun registrator ofisi ruxsati kerak.');
        return view('student.group-change-application.create', compact('student'));
    }
}
