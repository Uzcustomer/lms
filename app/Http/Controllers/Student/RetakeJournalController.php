<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\RetakeApplication;
use App\Models\RetakeGroup;
use App\Models\Student;
use App\Services\Retake\RetakeJournalService;
use Illuminate\Support\Facades\Auth;

class RetakeJournalController extends Controller
{
    public function __construct(
        private RetakeJournalService $service,
    ) {}

    /**
     * Talabaning o'zi a'zo bo'lgan retake guruhlari ro'yxati.
     */
    public function index()
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $groupIds = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->whereNotNull('retake_group_id')
            ->pluck('retake_group_id')
            ->unique();

        $groups = RetakeGroup::query()
            ->with('teacher')
            ->whereIn('id', $groupIds)
            ->orderByDesc('start_date')
            ->get();

        return view('student.retake-journal.index', [
            'groups' => $groups,
        ]);
    }

    /**
     * Talaba uchun bitta guruhdagi jurnal — faqat o'qish.
     */
    public function show(int $groupId)
    {
        /** @var Student $student */
        $student = Auth::guard('student')->user();

        $group = RetakeGroup::with('teacher')->findOrFail($groupId);

        // Talaba shu guruhda bo'lsa-yo'qmi
        $myApp = RetakeApplication::query()
            ->where('student_hemis_id', $student->hemis_id)
            ->where('retake_group_id', $group->id)
            ->where('final_status', RetakeApplication::STATUS_APPROVED)
            ->first();
        if (!$myApp) {
            abort(403, 'Siz bu guruhga biriktirilmagansiz');
        }

        $dates = $this->service->lessonDates($group);
        // Faqat o'zining baholari
        $myGrades = \App\Models\RetakeGrade::query()
            ->where('retake_group_id', $group->id)
            ->where('application_id', $myApp->id)
            ->get()
            ->keyBy(fn ($g) => $g->lesson_date->format('Y-m-d'));

        return view('student.retake-journal.show', [
            'group' => $group,
            'application' => $myApp,
            'dates' => $dates,
            'grades' => $myGrades,
        ]);
    }
}
