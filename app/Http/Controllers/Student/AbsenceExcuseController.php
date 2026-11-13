<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Services\AbsenceExcuseSubmission;
use App\Services\SubjectMatcherService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AbsenceExcuseController extends Controller
{
    public function index()
    {
        $student = Auth::guard('student')->user();

        $excuses = AbsenceExcuse::byStudent($student->id)
            ->orderByDesc('created_at')
            ->paginate(15);

        return view('student.absence-excuses.index', compact('excuses'));
    }

    public function create()
    {
        $reasons = AbsenceExcuse::REASONS;
        $noDayLimit = AbsenceExcuseSubmission::noDayLimit();
        return view('student.absence-excuses.create', compact('reasons', 'noDayLimit'));
    }

    /**
     * AJAX: Sana oralig'i bo'yicha o'tkazib yuborilgan nazoratlarni olish
     */
    public function missedAssessments(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $student = Auth::guard('student')->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);

        return response()->json([
            'assessments' => AbsenceExcuseSubmission::missedAssessments($student, $startDate, $endDate)->toArray(),
            // Sanalar boshqa arizaga tushsa — forma darhol ko'rsatadi, yuborish o'chiriladi
            'overlap' => AbsenceExcuse::overlapMessage((string) $student->hemis_id, $startDate, $endDate),
        ]);
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        $request->validate(AbsenceExcuseSubmission::rules($student, $request), AbsenceExcuseSubmission::messages());

        $makeupDates = AbsenceExcuseSubmission::makeupPlan($request);
        if (is_string($makeupDates)) {
            return back()->withErrors(['makeup_dates' => $makeupDates])->withInput();
        }

        try {
            $excuse = AbsenceExcuseSubmission::create($student, $request, $makeupDates);
        } catch (\Throwable $e) {
            return back()->withErrors(['error' => $e->getMessage()])->withInput();
        }

        return redirect()
            ->route('student.absence-excuses.show', $excuse->id)
            ->with('success', 'Arizangiz muvaffaqiyatli yuborildi!');
    }

    public function scheduleCheck($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $startDate = $excuse->start_date;
        $endDate = $excuse->end_date;
        $groupId = $student->group_id;

        $missedAssessments = AbsenceExcuseSubmission::findMissedAssessments($groupId, $startDate, $endDate, $student->id);

        $existingMakeups = $excuse->makeups;

        if ($existingMakeups->isEmpty() && $missedAssessments->isNotEmpty()) {
            foreach ($missedAssessments as $assessment) {
                // student_subjects orqali to'g'ri subject_id ni aniqlash
                $resolvedSubjectId = $assessment['subject_id'];
                $match = SubjectMatcherService::resolveSubjectId(
                    $assessment['subject_name'],
                    $assessment['subject_id'],
                    $student
                );
                if ($match) {
                    $resolvedSubjectId = $match['subject_id'];
                }

                AbsenceExcuseMakeup::create([
                    'absence_excuse_id' => $excuse->id,
                    'student_id' => $student->id,
                    'subject_name' => $assessment['subject_name'],
                    'subject_id' => $resolvedSubjectId,
                    'assessment_type' => $assessment['assessment_type'],
                    'assessment_type_code' => $assessment['assessment_type_code'],
                    'original_date' => $assessment['original_date'],
                ]);
            }
            $excuse->load('makeups');
        }

        $absentDaysCount = AbsenceExcuseSubmission::countNonSundays($startDate, $endDate);
        $noDayLimit = AbsenceExcuseSubmission::noDayLimit();

        return view('student.absence-excuses.schedule-check', compact('excuse', 'missedAssessments', 'absentDaysCount', 'noDayLimit'));
    }

    public function storeMakeupDates(Request $request, $id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $makeups = $excuse->makeups;

        if ($makeups->isEmpty()) {
            return redirect()
                ->route('student.absence-excuses.show', $excuse->id)
                ->with('info', 'O\'tkazib yuborilgan nazoratlar topilmadi.');
        }

        $noDayLimit = AbsenceExcuseSubmission::noDayLimit();
        $dateRule = $noDayLimit ? 'required|date' : 'required|date|after_or_equal:today';

        $rules = [];
        $messages = [];
        foreach ($makeups as $makeup) {
            $rules["makeup_dates.{$makeup->id}"] = $dateRule;
            $messages["makeup_dates.{$makeup->id}.required"] = "{$makeup->subject_name} ({$makeup->assessment_type_label}) uchun sana tanlang.";
            $messages["makeup_dates.{$makeup->id}.after_or_equal"] = "Sana bugungi kundan oldin bo'lmasligi kerak.";
        }

        $request->validate($rules, $messages);

        $makeupDates = $request->input('makeup_dates', []);

        foreach ($makeupDates as $date) {
            $dayOfWeek = Carbon::parse($date)->dayOfWeek;
            if ($dayOfWeek === Carbon::SUNDAY) {
                return back()->withErrors(['makeup_dates' => 'Yakshanba kunini tanlash mumkin emas.'])->withInput();
            }
        }

        $uniqueDates = collect($makeupDates)->unique()->count();
        $absentDaysCount = AbsenceExcuseSubmission::countNonSundays($excuse->start_date, $excuse->end_date);

        if ($uniqueDates > $absentDaysCount) {
            return back()->withErrors([
                'makeup_dates' => "Siz {$uniqueDates} ta noyob kun tanladingiz, lekin maksimum {$absentDaysCount} kun tanlash mumkin."
            ])->withInput();
        }

        foreach ($makeupDates as $makeupId => $date) {
            AbsenceExcuseMakeup::where('id', $makeupId)
                ->where('absence_excuse_id', $excuse->id)
                ->update([
                    'makeup_date' => $date,
                    'status' => 'scheduled',
                ]);
        }

        return redirect()
            ->route('student.absence-excuses.show', $excuse->id)
            ->with('success', 'Qayta topshirish sanalari muvaffaqiyatli saqlandi.');
    }

    public function show($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);
        $excuse->load('makeups');

        return view('student.absence-excuses.show', compact('excuse'));
    }

    public function download($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $filePath = storage_path('app/public/' . $excuse->file_path);
        if (!file_exists($filePath)) {
            abort(404, 'Fayl serverda topilmadi');
        }

        return response()->download($filePath, $excuse->file_original_name);
    }

    public function downloadPdf($id)
    {
        $student = Auth::guard('student')->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            abort(404, 'PDF hujjat topilmadi');
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);
        if (!file_exists($filePath)) {
            abort(404, 'PDF fayl serverda topilmadi');
        }

        return response()->download($filePath, 'sababli_ariza_' . $excuse->id . '.pdf');
    }
}
