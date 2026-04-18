<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AbsenceExcuse;
use App\Models\AbsenceExcuseMakeup;
use App\Models\ExamSchedule;
use App\Models\ExamTest;
use App\Models\Notification;
use App\Models\OraliqNazorat;
use App\Models\Oski;
use App\Models\Schedule;
use App\Models\Student;
use App\Models\Teacher;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AbsenceExcuseApiController extends Controller
{
    public function reasons(): JsonResponse
    {
        $reasons = collect(AbsenceExcuse::REASONS)->map(fn($data, $key) => [
            'key' => $key,
            'label' => $data['label'],
            'document' => $data['document'],
            'max_days' => $data['max_days'],
            'note' => $data['note'],
        ])->values();

        return response()->json(['data' => $reasons]);
    }

    public function index(Request $request): JsonResponse
    {
        $student = $request->user();

        $excuses = AbsenceExcuse::byStudent($student->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn($e) => $this->formatExcuse($e));

        return response()->json(['data' => $excuses]);
    }

    public function show(Request $request, $id): JsonResponse
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->with('makeups')->findOrFail($id);

        return response()->json(['data' => $this->formatExcuseDetail($excuse)]);
    }

    public function store(Request $request): JsonResponse
    {
        $student = $request->user();
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
                                $fail('Hujjatlarni taqdim qilish muddati o\'tgan (10 kundan ko\'p).');
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
                    $allowedExtensions = ['pdf', 'jpg', 'jpeg'];
                    $clientExt = strtolower($value->getClientOriginalExtension());
                    $guessedExt = $value->guessExtension();
                    if (!in_array($clientExt, $allowedExtensions) && !in_array($guessedExt, $allowedExtensions)) {
                        $fail('Faqat PDF va JPG formatdagi fayllar qabul qilinadi.');
                    }
                },
            ],
            'description' => 'nullable|string|max:1000',
            'makeup_dates' => 'nullable|array',
            'makeup_dates.*.subject_name' => 'required|string',
            'makeup_dates.*.subject_id' => 'nullable|string',
            'makeup_dates.*.assessment_type' => 'required|string|in:jn,mt,oski,test',
            'makeup_dates.*.assessment_type_code' => 'required|string',
            'makeup_dates.*.original_date' => 'required|date',
            'makeup_dates.*.makeup_date' => 'nullable|date',
            'makeup_dates.*.makeup_start' => 'nullable|date',
            'makeup_dates.*.makeup_end' => 'nullable|date',
        ], [
            'reason.required' => 'Sababni tanlang',
            'doc_number.required' => 'Hujjat raqamini kiriting',
            'start_date.required' => 'Boshlanish sanasini kiriting',
            'end_date.required' => 'Tugash sanasini kiriting',
            'file.required' => 'Hujjat yuklash majburiy',
            'file.max' => 'Fayl hajmi 10MB dan oshmasligi kerak',
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

            $makeupDates = $request->input('makeup_dates', []);

            if (!empty($makeupDates)) {
                foreach ($makeupDates as $makeup) {
                    $dateToSave = ($makeup['assessment_type'] === 'jn')
                        ? ($makeup['makeup_start'] ?? $makeup['makeup_date'] ?? null)
                        : ($makeup['makeup_date'] ?? null);

                    $makeupEndDate = null;
                    if (($makeup['assessment_type'] ?? '') === 'jn' && !empty($makeup['makeup_end'])) {
                        $makeupEndDate = $makeup['makeup_end'];
                    }

                    AbsenceExcuseMakeup::create([
                        'absence_excuse_id' => $excuse->id,
                        'student_id' => $student->id,
                        'subject_name' => $makeup['subject_name'],
                        'subject_id' => $makeup['subject_id'] ?? null,
                        'assessment_type' => $makeup['assessment_type'],
                        'assessment_type_code' => $makeup['assessment_type_code'],
                        'original_date' => $makeup['original_date'],
                        'makeup_date' => $dateToSave,
                        'makeup_end_date' => $makeupEndDate,
                        'status' => 'scheduled',
                    ]);
                }
            } else {
                $startDate = Carbon::parse($request->start_date);
                $endDate = Carbon::parse($request->end_date);
                $missedAssessments = $this->findMissedAssessments($student->group_id, $startDate, $endDate);

                foreach ($missedAssessments as $assessment) {
                    AbsenceExcuseMakeup::create([
                        'absence_excuse_id' => $excuse->id,
                        'student_id' => $student->id,
                        'subject_name' => $assessment['subject_name'],
                        'subject_id' => $assessment['subject_id'] ?? null,
                        'assessment_type' => $assessment['assessment_type'],
                        'assessment_type_code' => $assessment['assessment_type_code'],
                        'original_date' => $assessment['original_date'],
                        'status' => 'pending',
                    ]);
                }
            }

            DB::commit();

            $this->notifyAdmins($excuse, $student);

            $excuse->load('makeups');

            return response()->json([
                'message' => 'Arizangiz muvaffaqiyatli yuborildi!',
                'data' => $this->formatExcuseDetail($excuse),
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json(['message' => 'Xatolik: ' . $e->getMessage()], 500);
        }
    }

    public function missedAssessments(Request $request): JsonResponse
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $student = $request->user();
        $startDate = Carbon::parse($request->start_date);
        $endDate = Carbon::parse($request->end_date);
        $excuseDays = $this->countNonSundays($startDate, $endDate);

        $assessments = $this->findMissedAssessments($student->group_id, $startDate, $endDate);

        $jnSubjectIds = $assessments->where('assessment_type', 'jn')->pluck('subject_id')->unique()->filter()->values();

        $futureAssessments = collect();
        if ($jnSubjectIds->isNotEmpty()) {
            $futureEnd = Carbon::today()->copy();
            $daysAdded = 0;
            while ($daysAdded < $excuseDays) {
                $futureEnd->addDay();
                if ($futureEnd->dayOfWeek !== Carbon::SUNDAY) {
                    $daysAdded++;
                }
            }

            $futureSchedules = Schedule::where('group_id', $student->group_id)
                ->whereDate('lesson_date', '>', $endDate)
                ->whereDate('lesson_date', '<=', $futureEnd)
                ->whereIn('training_type_code', [101, 102])
                ->whereIn('subject_id', $jnSubjectIds)
                ->get();

            $typeMap = [101 => 'oski', 102 => 'test'];
            foreach ($futureSchedules as $s) {
                $type = $typeMap[$s->training_type_code] ?? null;
                if ($type) {
                    $futureAssessments->push([
                        'subject_name' => $s->subject_name,
                        'subject_id' => $s->subject_id,
                        'assessment_type' => $type,
                        'assessment_type_code' => (string) $s->training_type_code,
                        'original_date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                        'is_future' => true,
                    ]);
                }
            }

            $futureExamSchedules = ExamSchedule::where('group_hemis_id', $student->group_id)
                ->whereIn('subject_id', $jnSubjectIds)
                ->where(function ($q) use ($endDate, $futureEnd) {
                    $q->where(function ($q2) use ($endDate, $futureEnd) {
                        $q2->whereDate('oski_date', '>', $endDate)->whereDate('oski_date', '<=', $futureEnd);
                    })->orWhere(function ($q2) use ($endDate, $futureEnd) {
                        $q2->whereDate('test_date', '>', $endDate)->whereDate('test_date', '<=', $futureEnd);
                    });
                })->get();

            foreach ($futureExamSchedules as $es) {
                if ($es->oski_date && $es->oski_date->gt($endDate) && $es->oski_date->lte($futureEnd)) {
                    $futureAssessments->push([
                        'subject_name' => $es->subject_name,
                        'subject_id' => $es->subject_id,
                        'assessment_type' => 'oski',
                        'assessment_type_code' => '101',
                        'original_date' => $es->oski_date->format('Y-m-d'),
                        'is_future' => true,
                    ]);
                }
                if ($es->test_date && $es->test_date->gt($endDate) && $es->test_date->lte($futureEnd)) {
                    $futureAssessments->push([
                        'subject_name' => $es->subject_name,
                        'subject_id' => $es->subject_id,
                        'assessment_type' => 'test',
                        'assessment_type_code' => '102',
                        'original_date' => $es->test_date->format('Y-m-d'),
                        'is_future' => true,
                    ]);
                }
            }

            $futureAssessments = $futureAssessments->unique(fn($item) => $item['subject_name'] . '|' . $item['assessment_type'] . '|' . $item['original_date']);
        }

        $allAssessments = $assessments->map(function ($a) {
            $a['is_future'] = false;
            return $a;
        })->merge($futureAssessments)->values();

        return response()->json([
            'data' => $allAssessments,
            'excuse_days' => $excuseDays,
        ]);
    }

    public function download(Request $request, $id)
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        $filePath = storage_path('app/public/' . $excuse->file_path);
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'Fayl topilmadi'], 404);
        }

        return response()->download($filePath, $excuse->file_original_name);
    }

    public function downloadPdf(Request $request, $id)
    {
        $student = $request->user();
        $excuse = AbsenceExcuse::byStudent($student->id)->findOrFail($id);

        if (!$excuse->isApproved() || !$excuse->approved_pdf_path) {
            return response()->json(['message' => 'PDF hujjat topilmadi'], 404);
        }

        $filePath = storage_path('app/public/' . $excuse->approved_pdf_path);
        if (!file_exists($filePath)) {
            return response()->json(['message' => 'PDF fayl topilmadi'], 404);
        }

        return response()->download($filePath, 'sababli_ariza_' . $excuse->id . '.pdf');
    }

    private function formatExcuse(AbsenceExcuse $e): array
    {
        return [
            'id' => $e->id,
            'reason' => $e->reason,
            'reason_label' => $e->reason_label,
            'doc_number' => $e->doc_number,
            'start_date' => $e->start_date->format('Y-m-d'),
            'end_date' => $e->end_date->format('Y-m-d'),
            'status' => $e->status,
            'status_label' => $e->status_label,
            'status_color' => $e->status_color,
            'created_at' => $e->created_at->format('Y-m-d H:i'),
        ];
    }

    private function formatExcuseDetail(AbsenceExcuse $e): array
    {
        $data = $this->formatExcuse($e);
        $data['description'] = $e->description;
        $data['file_original_name'] = $e->file_original_name;
        $data['reviewed_by_name'] = $e->reviewed_by_name;
        $data['rejection_reason'] = $e->rejection_reason;
        $data['reviewed_at'] = $e->reviewed_at?->format('Y-m-d H:i');
        $data['has_approved_pdf'] = $e->isApproved() && !empty($e->approved_pdf_path);
        $data['makeups'] = $e->makeups->map(fn($m) => [
            'id' => $m->id,
            'subject_name' => $m->subject_name,
            'assessment_type' => $m->assessment_type,
            'assessment_type_label' => $m->assessment_type_label ?? $m->assessment_type,
            'original_date' => $m->original_date?->format('Y-m-d'),
            'makeup_date' => $m->makeup_date?->format('Y-m-d'),
            'status' => $m->status,
        ])->toArray();

        return $data;
    }

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

    private function findMissedAssessments($groupId, $startDate, $endDate)
    {
        $missed = collect();

        $schedules = Schedule::where('group_id', $groupId)
            ->whereDate('lesson_date', '>=', $startDate)
            ->whereDate('lesson_date', '<=', $endDate)
            ->whereIn('training_type_code', [99, 100, 101, 102])
            ->get();

        $typeMap = [100 => 'jn', 99 => 'mt', 101 => 'oski', 102 => 'test'];
        foreach ($schedules as $s) {
            $type = $typeMap[$s->training_type_code] ?? null;
            if ($type) {
                $missed->push([
                    'subject_name' => $s->subject_name,
                    'subject_id' => $s->subject_id,
                    'assessment_type' => $type,
                    'assessment_type_code' => (string) $s->training_type_code,
                    'original_date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                ]);
            }
        }

        $oraliqNazorats = OraliqNazorat::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();
        foreach ($oraliqNazorats as $on) {
            $missed->push([
                'subject_name' => $on->subject_name,
                'subject_id' => $on->subject_hemis_id,
                'assessment_type' => 'jn',
                'assessment_type_code' => '100',
                'original_date' => Carbon::parse($on->start_date)->format('Y-m-d'),
            ]);
        }

        $oskis = Oski::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();
        foreach ($oskis as $o) {
            $missed->push([
                'subject_name' => $o->subject_name,
                'subject_id' => $o->subject_hemis_id,
                'assessment_type' => 'oski',
                'assessment_type_code' => '101',
                'original_date' => Carbon::parse($o->start_date)->format('Y-m-d'),
            ]);
        }

        $examTests = ExamTest::where('group_hemis_id', $groupId)
            ->whereDate('start_date', '>=', $startDate)
            ->whereDate('start_date', '<=', $endDate)
            ->get();
        foreach ($examTests as $et) {
            $missed->push([
                'subject_name' => $et->subject_name,
                'subject_id' => $et->subject_hemis_id,
                'assessment_type' => 'test',
                'assessment_type_code' => '102',
                'original_date' => Carbon::parse($et->start_date)->format('Y-m-d'),
            ]);
        }

        $examSchedules = ExamSchedule::where('group_hemis_id', $groupId)
            ->where(function ($q) use ($startDate, $endDate) {
                $q->where(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('oski_date', '>=', $startDate)->whereDate('oski_date', '<=', $endDate);
                })->orWhere(function ($q2) use ($startDate, $endDate) {
                    $q2->whereDate('test_date', '>=', $startDate)->whereDate('test_date', '<=', $endDate);
                });
            })->get();

        foreach ($examSchedules as $es) {
            if ($es->oski_date && $es->oski_date->between($startDate, $endDate)) {
                $missed->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'oski',
                    'assessment_type_code' => '101',
                    'original_date' => $es->oski_date->format('Y-m-d'),
                ]);
            }
            if ($es->test_date && $es->test_date->between($startDate, $endDate)) {
                $missed->push([
                    'subject_name' => $es->subject_name,
                    'subject_id' => $es->subject_id,
                    'assessment_type' => 'test',
                    'assessment_type_code' => '102',
                    'original_date' => $es->test_date->format('Y-m-d'),
                ]);
            }
        }

        return $missed->unique(fn($item) => $item['subject_name'] . '|' . $item['assessment_type'] . '|' . $item['original_date'])->values();
    }

    private function notifyAdmins(AbsenceExcuse $excuse, $student): void
    {
        $roles = ['superadmin', 'admin', 'kichik_admin', 'registrator_ofisi'];
        $url = route('admin.absence-excuses.show', $excuse->id);
        $subject = "Yangi sababli ariza: {$excuse->student_full_name}";
        $body = "Talaba: {$excuse->student_full_name}\nGuruh: {$excuse->group_name}\nSabab: {$excuse->reason_label}\nSana: {$excuse->start_date->format('d.m.Y')} — {$excuse->end_date->format('d.m.Y')}";

        $adminUsers = User::role($roles)->get();
        foreach ($adminUsers as $user) {
            Notification::create([
                'sender_id' => $student->id,
                'sender_type' => Student::class,
                'recipient_id' => $user->id,
                'recipient_type' => User::class,
                'subject' => $subject,
                'body' => $body,
                'type' => Notification::TYPE_SYSTEM,
                'url' => $url,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        }

        $adminTeachers = Teacher::role($roles)->get();
        foreach ($adminTeachers as $teacher) {
            Notification::create([
                'sender_id' => $student->id,
                'sender_type' => Student::class,
                'recipient_id' => $teacher->id,
                'recipient_type' => Teacher::class,
                'subject' => $subject,
                'body' => $body,
                'type' => Notification::TYPE_SYSTEM,
                'url' => $url,
                'is_draft' => false,
                'sent_at' => now(),
            ]);
        }
    }
}
