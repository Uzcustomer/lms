<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\TestSubject;
use Carbon\Carbon;
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

    public function studentPreview(TestSubject $testSubject)
    {
        $teacher = auth()->guard('teacher')->user();
        abort_unless((int) $testSubject->teacher_id === (int) $teacher->id, 403);

        $testSubject->load([
            'groups',
            'lessons' => fn ($query) => $query
                ->where('is_active', true)
                ->orderBy('lesson_date')
                ->orderBy('topic_order'),
            'lessons.lessonTest' => fn ($query) => $query->withCount([
                'questions' => fn ($q) => $q->where('is_active', true),
            ]),
        ]);

        $now = now('Asia/Tashkent');
        $lessons = $testSubject->lessons->map(function ($lesson) use ($now) {
            $lessonTest = $lesson->lessonTest;
            $questionCount = (int) ($lessonTest?->questions_count ?? 0);
            $isScheduledNow = $this->isLessonScheduledNow($lesson, $now);
            $isPast = $this->lessonEndsAt($lesson)?->lt($now)
                || (optional($lesson->lesson_date)->format('Y-m-d') < $now->toDateString());
            $isFuture = $this->lessonStartsAt($lesson)?->gt($now)
                || (optional($lesson->lesson_date)->format('Y-m-d') > $now->toDateString());

            return [
                'id' => $lesson->id,
                'topic_order' => (int) ($lesson->topic_order ?? 0),
                'topic_title' => $lesson->topic_title ?: (($lesson->topic_order ?? 1) . '-mavzu'),
                'lesson_date' => optional($lesson->lesson_date)->format('d.m.Y'),
                'lesson_time' => $this->lessonTimeText($lesson),
                'question_count' => $questionCount,
                'is_open' => (bool) ($lessonTest?->is_open),
                'is_published' => (bool) ($lessonTest?->is_published),
                'is_scheduled_now' => $isScheduledNow,
                'is_past' => $isPast,
                'is_future' => $isFuture,
                'status_text' => $this->resolvePreviewStatusText($lessonTest, $questionCount, $isScheduledNow, $isFuture, $isPast),
            ];
        })->values();

        return view('teacher.test-subjects.student-preview', [
            'testSubject' => $testSubject,
            'lessons' => $lessons,
            'now' => $now,
        ]);
    }

    private function builderReady(): bool
    {
        return Schema::hasTable('test_subject_lesson_tests')
            && Schema::hasTable('test_subject_lesson_test_questions')
            && Schema::hasTable('test_subject_lesson_test_options');
    }

    private function lessonStartsAt($lesson): ?Carbon
    {
        $date = optional($lesson->lesson_date)->format('Y-m-d');
        if (!$date) {
            return null;
        }

        return Carbon::parse($date . ' ' . ($lesson->starts_at ?: '00:00:00'), 'Asia/Tashkent');
    }

    private function lessonEndsAt($lesson): ?Carbon
    {
        $date = optional($lesson->lesson_date)->format('Y-m-d');
        if (!$date) {
            return null;
        }

        return Carbon::parse($date . ' ' . ($lesson->ends_at ?: '23:59:59'), 'Asia/Tashkent');
    }

    private function isLessonScheduledNow($lesson, Carbon $now): bool
    {
        $lessonDate = optional($lesson->lesson_date)->format('Y-m-d');
        if ($lessonDate !== $now->toDateString()) {
            return false;
        }

        $startsAt = $this->lessonStartsAt($lesson);
        $endsAt = $this->lessonEndsAt($lesson);

        if (!$startsAt || !$endsAt) {
            return true;
        }

        return $now->between($startsAt, $endsAt, true);
    }

    private function lessonTimeText($lesson): ?string
    {
        if (!$lesson->starts_at && !$lesson->ends_at) {
            return null;
        }

        return trim(
            ($lesson->starts_at ? substr($lesson->starts_at, 0, 5) : '--:--')
            . ' - ' .
            ($lesson->ends_at ? substr($lesson->ends_at, 0, 5) : '--:--')
        );
    }

    private function resolvePreviewStatusText($lessonTest, int $questionCount, bool $isScheduledNow, bool $isFuture, bool $isPast): string
    {
        if (!$lessonTest) {
            return 'Test hali yaratilmagan.';
        }

        if (!$lessonTest->is_published) {
            return 'Test hali nashr qilinmagan.';
        }

        if ($questionCount <= 0) {
            return 'Savollar hali kiritilmagan.';
        }

        if ($isFuture) {
            return 'Dars vaqti kelmagan, test ochilmaydi.';
        }

        if ($isScheduledNow && $lessonTest->is_open) {
            return 'Test hozir ochiq, talaba ishlay oladi.';
        }

        if ($isScheduledNow && !$lessonTest->is_open) {
            return 'Dars vaqti keldi, lekin test hali ochilmagan.';
        }

        if ($isPast && $lessonTest->is_open) {
            return 'Dars vaqti o‘tgan, lekin test hali ochiq turibdi.';
        }

        if ($isPast) {
            return 'Dars vaqti o‘tgan, test yopiq.';
        }

        return 'Test hozircha kutilmoqda.';
    }
}
