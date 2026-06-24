<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TestSubject;
use Illuminate\Http\Request;

class TestSubjectController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->guard('teacher')->user();

        $subjects = TestSubject::query()
            ->with(['groups', 'lessons'])
            ->where('teacher_id', $teacher->id)
            ->latest()
            ->paginate(20);

        return view('teacher.test-subjects.index', compact('subjects'));
    }

    public function show(TestSubject $testSubject)
    {
        $teacher = auth()->guard('teacher')->user();
        abort_unless((int) $testSubject->teacher_id === (int) $teacher->id, 403);

        $testSubject->load(['groups', 'lessons', 'teacher']);

        return view('teacher.test-subjects.show', compact('testSubject'));
    }
}
