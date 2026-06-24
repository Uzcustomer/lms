<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TestSubject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

class TestSubjectController extends Controller
{
    public function index(Request $request)
    {
        $teacher = auth()->guard('teacher')->user();
        $builderReady = $this->builderReady();

        $query = TestSubject::query()
            ->with(['groups', 'lessons'])
            ->where('teacher_id', $teacher->id)
            ->latest();

        if ($builderReady) {
            $query->with(['lessons.lessonTest']);
        }

        $subjects = $query->paginate(20);

        return view('teacher.test-subjects.index', compact('subjects', 'builderReady'));
    }

    public function show(TestSubject $testSubject)
    {
        $teacher = auth()->guard('teacher')->user();
        abort_unless((int) $testSubject->teacher_id === (int) $teacher->id, 403);
        $builderReady = $this->builderReady();

        $testSubject->load(['groups', 'lessons', 'teacher']);

        if ($builderReady) {
            $testSubject->load(['lessons.lessonTest.questions']);
        }

        return view('teacher.test-subjects.show', compact('testSubject', 'builderReady'));
    }

    private function builderReady(): bool
    {
        return Schema::hasTable('test_subject_lesson_tests')
            && Schema::hasTable('test_subject_lesson_test_questions')
            && Schema::hasTable('test_subject_lesson_test_options');
    }
}
