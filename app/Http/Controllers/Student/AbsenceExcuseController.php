<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\ExamSchedule;
use App\Models\ExamTest;
use App\Models\OraliqNazorat;
use App\Models\Oski;
use App\Models\Schedule;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

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
        return view('student.absence-excuses.create', compact('reasons'));
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
        $groupId = $student->group_id;

        $missedAssessments = $this->findMissedAssessments($groupId, $startDate, $endDate);

        return response()->json([
            'assessments' => $missedAssessments->values()->toArray(),
        ]);
    }

    public function store(Request $request)
    {
        $student = Auth::guard('student')->user();

        $reasonKeys = implode(',', array_keys(AbsenceExcuse::REASONS));

        $request->validate([
            'reason' => "required|in:{$reasonKeys}",
            'doc_number' => 'required|string|max:100',
            'start_date' => 'required|date|before_or_equal:end_date',
            'end_date' => [
                'required',
                'date',
                'after_or_equal:start_date',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->reason && $request->start_date && $value) {
                        $reasonData = AbsenceExcuse::REASONS[$request->reason] ?? null;
                        if ($reasonData && $reasonData['max_days']) {
                            $start = Carbon::parse($request->start_date);
                            $end = Carbon::parse($value);
                            $days = $this->countNonSundays($start, $end);
                            if ($days > $reasonData['max_days']) {
                                $fail("Tanlangan sabab uchun maksimum {$reasonData['max_days']} kun ruxsat etiladi. Siz {$days} kun belgiladingiz.");
                            }
                        }
                    }
                },
                function ($attribute, $value, $fail) {
                    if ($value) {
                        $endDate = Carbon::parse($value);
                        $nextDay = $endDate->copy()->addDay();

                        if ($nextDay->isSunday()) {
                            $nextDay->addDay();
                        }

                        $today = Carbon::today();

                        if ($today->gte($nextDay)) {
                            $daysPassed = $nextDay->diffInDays($today);
                            if ($daysPassed > 10) {
                                $fail('Hujjatlarni taqdim qilish muddati o\'tgan (10 kundan ko\'p). Tugash sanasidan keyin 10 kun ichida ariza topshirishingiz kerak edi.');
                            }
                        }
                    }
                },
            ],
            'file' => [
                'required',
                'file',
                'max:10240',
                function ($attribute, $value, $fail) {
                    $allowedExtensions = ['pdf', 'jpg', 'jpeg', 'png', 'doc', 'docx'];
                    $clientExt = strtolower($value->getClientOriginalExtension());
                    $guessedExt = $value->guessExtension();
                    if (!in_array($clientExt, $allowedExtensions) && !in_array($guessedExt, $allowedExtensions)) {
                        $fail('Faqat PDF, JPG, PNG, DOC, DOCX formatdagi fayllar qabul qilinadi.');
                    }
                },
            ],
            'makeup_dates' => 'nullable|array',
            'makeup_dates.*.subject_name' => 'required|string',
            'makeup_dates.*.subject_id' => 'nullable|string',
            'makeup_dates.*.assessment_type' => 'required|string',
            'makeup_dates.*.assessment_type_code' => 'required|string',
            'makeup_dates.*.original_date' => 'required|date',
            'makeup_dates.*.makeup_date' => 'required|date|after_or_equal:today',
        ], [
            'reason.required' => 'Sababni tanlang',
            'reason.in' => 'Noto\'g\'ri sabab tanlangan',
            'doc_number.required' => 'Hujjat raqamini kiriting',
            'start_date.required' => 'Boshlanish sanasini kiriting',
            'end_date.required' => 'Tugash sanasini kiriting',
            'start_date.before_or_equal' => 'Boshlanish sanasi tugash sanasidan keyin bo\'lmasligi kerak',
            'end_date.after_or_equal' => 'Tugash sanasi boshlanish sanasidan oldin bo\'lmasligi kerak',
            'file.required' => 'Hujjat yuklash majburiy',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak',
            'makeup_dates.*.makeup_date.required' => 'Har bir nazorat uchun qayta topshirish sanasini tanlang',
            'makeup_dates.*.makeup_date.after_or_equal' => 'Qayta topshirish sanasi bugungi kundan oldin bo\'lmasligi kerak',
        ]);

        $file = $request->file('file');
        $filePath = $file->store('absence-excuses/' . $student->hemis_id, 'public');

        DB::beginTransaction();
        try {
            $excuse = AbsenceExcuse::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'student_full_name' => $student->full_name,
                'group_name' => $student->group_name,
                'department_name' => $student->department_name,
                'doc_number' => $request->doc_number,
                'reason' => $request->reason,
                'start_date' => $request->start_date,
                'end_date' => $request->end_date,
                'description' => $request->description,
                'file_path' => $filePath,
                'file_original_name' => $file->getClientOriginalName(),
                'status' => 'pending',
            ]);

            // Makeup sanalarni saqlash
            $makeupDates = $request->input('makeup_dates', []);
            foreach ($makeupDates as $makeup) {
                // Yakshanba tekshiruvi
                $makeupDate = Carbon::parse($makeup['makeup_date']);
                if ($makeupDate->isSunday()) {
                    throw new \RuntimeException('Yakshanba kunini tanlash mumkin emas.');
                }

                AbsenceExcuseMakeup::create([
                    'absence_excuse_id' => $excuse->id,
                    'student_id' => $student->id,
                    'subject_name' => $makeup['subject_name'],
                    'subject_id' => $makeup['subject_id'] ?? null,
                    'assessment_type' => $makeup['assessment_type'],
                    'assessment_type_code' => $makeup['assessment_type_code'],
                    'original_date' => $makeup['original_date'],
                    'makeup_date' => $makeup['makeup_date'],
                    'status' => 'scheduled',
                ]);
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
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

        $missedAssessments = $this->findMissedAssessments($groupId, $startDate, $endDate);

        $existingMakeups = $excuse->makeups;

        if ($existingMakeups->isEmpty() && $missedAssessments->isNotEmpty()) {
            foreach ($missedAssessments as $assessment) {
                AbsenceExcuseMakeup::create([
                    'absence_excuse_id' => $excuse->id,
                    'student_id' => $student->id,
                    'subject_name' => $assessment['subject_name'],
                    'subject_id' => $assessment['subject_id'],
                    'assessment_type' => $assessment['assessment_type'],
                    'assessment_type_code' => $assessment['assessment_type_code'],
                    'original_date' => $assessment['original_date'],
                ]);
            }
            $excuse->load('makeups');
        }

        $absentDaysCount = $this->countNonSundays($startDate, $endDate);

        return view('student.absence-excuses.schedule-check', compact('excuse', 'missedAssessments', 'absentDaysCount'));
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

        $rules = [];
        $messages = [];
        foreach ($makeups as $makeup) {
            $rules["makeup_dates.{$makeup->id}"] = 'required|date|after_or_equal:today';
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
        $absentDaysCount = $this->countNonSundays($excuse->start_date, $excuse->end_date);

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

    /**
     * Yakshanbalarni hisobdan chiqarib, kunlarni sanash
     */
    private function countNonSundays(Carbon $start, Carbon $end): int
    {
        $days = 0;
        $current = $start->copy();
        while ($current->lte($end)) {
            if ($current->dayOfWeek !== Carbon::SUNDAY) {
                $days++;
            }
            $current->addDay();
        }
        return $days;
    }

    /**
     * Sana oralig'i bo'yicha o'tkazib yuborilgan nazoratlarni topish
     */
    private function findMissedAssessments($groupId, $startDate, $endDate)
    {
        $missedAssessments = collect();

        // 1. Schedules jadvalidan (dars jadvali)
        $schedules = Schedule::where('group_id', $groupId)
            ->whereDate('lesson_date', '>=', $startDate)
            ->whereDate('lesson_date', '<=', $endDate)
            ->whereIn('training_type_code', [99, 100, 101, 102])
            ->get();

        foreach ($schedules as $schedule) {
            $typeMap = [
                100 => 'jn',
                99 => 'mt',
                101 => 'oski',
                102 => 'test',
            ];
            $assessmentType = $typeMap[$schedule->training_type_code] ?? null;
            if ($assessmentType) {
                $missedAssessments->push([
                    'subject_name' => $schedule->subject_name,
                    'subject_id' => $schedule->subject_id,
                    'assessment_type' => $assessmentType,
                    'assessment_type_code' => (string) $schedule->training_type_code,
                    'original_date' => Carbon::parse($schedule->lesson_date)->format('Y-m-d'),
                ]);
            }
        }

        // 2. Oraliq nazoratlar (JN)
        $oraliqNazorats = OraliqNazorat::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($oraliqNazorats as $on) {
            $missedAssessments->push([
                'subject_name' => $on->subject_name,
                'subject_id' => $on->subject_hemis_id,
                'assessment_type' => 'jn',
                'assessment_type_code' => '100',
                'original_date' => Carbon::parse($on->start_date)->format('Y-m-d'),
            ]);
        }

        // 3. OSKI
        $oskis = Oski::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($oskis as $oski) {
            $missedAssessments->push([
                'subject_name' => $oski->subject_name,
                'subject_id' => $oski->subject_hemis_id,
                'assessment_type' => 'oski',
                'assessment_type_code' => '101',
                'original_date' => Carbon::parse($oski->start_date)->format('Y-m-d'),
            ]);
        }

        // 4. Yakuniy testlar
        $examTests = ExamTest::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();

        foreach ($examTests as $et) {
            $missedAssessments->push([
                'subject_name' => $et->subject_name,
                'subject_id' => $et->subject_hemis_id,
                'assessment_type' => 'test',
                'assessment_type_code' => '102',
                'original_date' => Carbon::parse($et->start_date)->format('Y-m-d'),
            ]);
        }

        // 5. Imtihon jadvali (OSKI va Test sanalari)
        $examSchedules = ExamSchedule::where('group_hemis_id', $groupId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('oski_date', '>=', $startDate)
                        ->whereDate('oski_date', '<=', $endDate);
                })->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('test_date', '>=', $startDate)
                        ->whereDate('test_date', '<=', $endDate);
                });
            })->get();

        foreach ($examSchedules as $es) {
            if ($es->oski_date && $es->oski_date->between($startDate, $endDate)) {
                $missedAssessments->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'oski',
                    'assessment_type_code' => '101',
                    'original_date' => $es->oski_date->format('Y-m-d'),
                ]);
            }
            if ($es->test_date && $es->test_date->between($startDate, $endDate)) {
                $missedAssessments->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'test',
                    'assessment_type_code' => '102',
                    'original_date' => $es->test_date->format('Y-m-d'),
                ]);
            }
        }

        // Duplikatlarni olib tashlash
        return $missedAssessments->unique(function ($item) {
            return $item['subject_name'] . '|' . $item['assessment_type'] . '|' . $item['original_date'];
        })->values();
    }
}
