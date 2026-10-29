<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\StudentGrade;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

/**
 * "Qayta topshirish (Otrabotka)": talabaning joriy semestr fanlaridagi NB va
 * past (low_grade) baholari hamda har birining qayta topshirish muddati.
 *
 * Muddat student_grades.deadline dan olinadi; u bo'sh bo'lsa — dars sanasi +
 * admin sozlamalaridagi kurs bo'yicha kunlar (deadlines.deadline_days), import
 * va jurnal yozganidek. Ma'ruza, MT, ON, OSKI va test baholari kirmaydi.
 */
class OtrabotkaController extends Controller
{
    private const DEFAULT_DEADLINE_DAYS = 7;

    public function index()
    {
        $student = Auth::user();

        $subjects = CurriculumSubject::where('curricula_hemis_id', $student->curriculum_id)
            ->where('semester_code', $student->semester_code)
            ->where('is_active', true)
            ->get();
        $subjects = app(StudentController::class)->keepStudentCycleVariants($subjects, $student, $student->semester_code);
        $subjectIds = $subjects->pluck('subject_id')->filter()->unique()->values()->all();

        $base = StudentGrade::where('student_id', $student->id)
            ->where('semester_code', $student->semester_code)
            ->whereIn('subject_id', $subjectIds ?: [0]);

        // Semestrni qayta o'qiyotganlarda o'tgan yilning shu semestr baholari aralashmasin
        $yearCode = (clone $base)->max('education_year_code');

        $rows = (clone $base)
            ->when($yearCode, fn ($q) => $q->where('education_year_code', $yearCode))
            ->whereIn('reason', ['absent', 'low_grade'])
            ->whereNotIn('training_type_code', config('app.training_type_code', [11, 99, 100, 101, 102, 103]))
            ->orderBy('lesson_date')
            ->get();

        $setting = Deadline::where('level_code', $student->level_code)->first();
        $deadlineDays = $setting->deadline_days ?? self::DEFAULT_DEADLINE_DAYS;
        $today = Carbon::today();

        $items = $rows->map(function (StudentGrade $g) use ($deadlineDays, $today) {
            $lessonDate = Carbon::parse($g->lesson_date);
            $deadline = $g->deadline
                ? Carbon::parse($g->deadline)
                : $lessonDate->copy()->addDays($deadlineDays)->endOfDay();

            if ($g->retake_grade !== null) {
                $state = 'done';
            } elseif ($g->status === 'closed' || now()->greaterThan($deadline)) {
                $state = 'expired';
            } else {
                $state = 'open';
            }

            return [
                'subject_id' => $g->subject_id,
                'subject_name' => $g->subject_name,
                'lesson_date' => $lessonDate,
                'training_type' => $g->training_type_name,
                'pair' => $g->lesson_pair_name,
                'is_absent' => $g->reason === 'absent',
                'grade' => $g->grade,
                'retake_grade' => $g->retake_grade,
                'deadline' => $deadline,
                'days_left' => (int) $today->diffInDays($deadline->copy()->startOfDay(), false),
                'state' => $state,
            ];
        });

        $groups = $items->groupBy('subject_id')->map(fn ($list) => [
            'name' => $list->first()['subject_name'],
            'items' => $list->values(),
            'open' => $list->where('state', 'open')->count(),
            'expired' => $list->where('state', 'expired')->count(),
            'done' => $list->where('state', 'done')->count(),
        ])
            // Ochiq qarzi bor fanlar tepada
            ->sortBy(fn ($g) => [$g['open'] > 0 ? 0 : 1, mb_strtolower($g['name'])])
            ->values();

        $open = $items->where('state', 'open');

        return view('student.otrabotka', [
            'groups' => $groups,
            'openCount' => $open->count(),
            'expiredCount' => $items->where('state', 'expired')->count(),
            'nearestDeadline' => $open->min('deadline'),
            'deadlineDays' => $deadlineDays,
            'byTestMarkazi' => (bool) ($setting->retake_by_test_markazi ?? false),
            'byTeacher' => (bool) ($setting->retake_by_oqituvchi ?? false),
            'semesterName' => $student->semester_name,
        ]);
    }
}
