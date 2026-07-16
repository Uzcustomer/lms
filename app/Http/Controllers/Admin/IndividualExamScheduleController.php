<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ExamSchedule;
use App\Models\IndividualScheduleAttachment;
use App\Models\IndividualScheduleAudit;
use App\Models\Semester;
use App\Models\Student;
use App\Services\ExamDateRoleService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Individual imtihon sanasi sahifasi — pullik/erta imtihon ruxsati uchun.
 *
 * Talabaga (HEMIS ID yoki F.I.Sh. bo'yicha) individual 1/2/3-urinish
 * OSKI/Test sanalarini belgilash. Mavjud guruh sanalaridan farqli ravishda,
 * exam_schedules.student_hemis_id orqali shaxsiy yozuv yaratiladi va undan
 * keyin test-center, Moodle push, kompyuter taqsimlash avtomatik shu sana
 * bilan ishlaydi.
 *
 * Audit: har bir o'zgarish individual_schedule_audits jadvaliga yoziladi.
 */
class IndividualExamScheduleController extends Controller
{
    /** Sahifani ko'rish va o'zgartirish huquqi tekshiruvi. */
    private function ensureAccess(): ?\Symfony\Component\HttpFoundation\Response
    {
        $user = auth()->user() ?? auth('teacher')->user();
        if (!$user) {
            abort(401);
        }
        $sessionRole = session('active_role');
        $userRole = method_exists($user, 'getRoleNames') ? ($user->getRoleNames()->first()) : null;
        $activeRole = $sessionRole ?: $userRole;
        $allowed = array_merge(ExamDateRoleService::adminRoles(), [
            \App\Enums\ProjectRole::REGISTRAR_OFFICE->value,
        ]);
        if (!in_array($activeRole, $allowed, true)) {
            abort(403, 'Bu sahifaga kirish uchun ruxsat yo\'q.');
        }
        return null;
    }

    private function routePrefix(): string
    {
        return auth('teacher')->check() ? 'teacher' : 'admin';
    }

    public function index(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $routePrefix = $this->routePrefix();
        $currentEducationYear = Semester::where('current', true)->value('education_year');

        return view('admin.individual-exam-schedule.index', [
            'routePrefix' => $routePrefix,
            'currentEducationYear' => $currentEducationYear,
        ]);
    }

    /**
     * AJAX: Talaba qidirish. HEMIS ID (raqam) yoki F.I.Sh. (kamida 3 belgi).
     */
    public function searchStudents(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $q = trim((string) $request->get('q', ''));
        if (mb_strlen($q) < 3) {
            return response()->json(['students' => []]);
        }

        $query = DB::table('students')
            ->where('student_status_code', 11)
            ->select('hemis_id', 'student_id_number', 'full_name', 'group_id', 'group_name', 'specialty_name', 'level_code');

        if (ctype_digit($q)) {
            $query->where(function ($w) use ($q) {
                $w->where('hemis_id', $q)
                  ->orWhere('student_id_number', 'like', $q . '%');
            });
        } else {
            $query->where('full_name', 'like', '%' . $q . '%');
        }

        $rows = $query->orderBy('full_name')->limit(30)->get();

        return response()->json(['students' => $rows]);
    }

    /**
     * AJAX: Tanlangan talaba uchun joriy semestrdagi fanlar ro'yxati,
     * guruh imtihon sanalari va individual sanalari (mavjud bo'lsa).
     * Har fan uchun eligibility statusi ham qaytariladi.
     */
    public function studentSubjects(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $hemisId = (string) $request->get('student_hemis_id', '');
        if ($hemisId === '') {
            return response()->json(['error' => 'student_hemis_id kerak'], 422);
        }

        $student = DB::table('students')->where('hemis_id', $hemisId)->first();
        if (!$student) {
            return response()->json(['error' => 'Talaba topilmadi'], 404);
        }

        $group = DB::table('groups')->where('group_hemis_id', $student->group_id)->first();
        if (!$group) {
            return response()->json(['error' => 'Talabaning guruhi topilmadi'], 404);
        }

        // Talabaning konkret kurikulumiga tegishli joriy semestr(lar).
        // Global Semester::where('current', true)->get() barcha kurikulumlar
        // bo'yicha qaytaradi va oldingi semestr fanlari ham aralashib chiqishi
        // mumkin — shu sababli kurikulum bo'yicha filtr qo'yamiz.
        $currentSemesters = Semester::where('current', true)
            ->where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->get();
        $semCodes = $currentSemesters->pluck('code')->unique()->values()->all();

        // Guruhga (yoki talabaga shaxsiy) allaqachon belgilangan imtihon
        // jadvallari — har qanday semestr bo'lishi mumkin (masalan, o'tgan
        // semestrdagi fan uchun joriy o'quv yilida resit qo'yilgan). Bularni
        // ham ro'yxatda ko'rsatish kerak, hatto curriculum_subjects'da joriy
        // semestrda bo'lmasa ham.
        $existingScheduleRows = ExamSchedule::where(function ($q) use ($student) {
                $q->where('group_hemis_id', $student->group_id)
                  ->whereNull('student_hemis_id');
            })
            ->orWhere('student_hemis_id', $student->hemis_id)
            ->select('subject_id', 'subject_name', 'semester_code')
            ->distinct()
            ->get();
        $extraSubjectKeys = $existingScheduleRows
            ->mapWithKeys(fn ($r) => [$r->subject_id . '|' . $r->semester_code => $r])
            ->all();
        $extraSubjectIds = array_values(array_unique(array_map(
            fn ($r) => $r->subject_id,
            $existingScheduleRows->all()
        )));
        $extraSemCodes = array_values(array_unique(array_map(
            fn ($r) => $r->semester_code,
            $existingScheduleRows->all()
        )));

        // curriculum_subjects'dan joriy semestr fanlari (asosiy ro'yxat)
        $subjectsQuery = DB::table('curriculum_subjects')
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('is_active', true);
        if (!empty($semCodes) || !empty($extraSubjectIds)) {
            $subjectsQuery->where(function ($q) use ($semCodes, $extraSubjectIds, $extraSemCodes) {
                if (!empty($semCodes)) {
                    $q->whereIn('semester_code', $semCodes);
                }
                if (!empty($extraSubjectIds) && !empty($extraSemCodes)) {
                    $q->orWhere(function ($w) use ($extraSubjectIds, $extraSemCodes) {
                        $w->whereIn('subject_id', $extraSubjectIds)
                          ->whereIn('semester_code', $extraSemCodes);
                    });
                }
            });
        }
        $subjects = $subjectsQuery
            ->select('subject_id', 'subject_name', 'semester_code', 'closing_form', 'curriculum_subject_hemis_id')
            ->orderBy('semester_code')
            ->orderBy('subject_name')
            ->get();

        // curriculum_subjects'da yo'q lekin exam_schedules'da bor fanlarni
        // ham qo'shamiz (HEMIS rejasidan tushib qolgan eski fanlar uchun)
        $subjectsByKey = $subjects->keyBy(fn ($s) => $s->subject_id . '|' . $s->semester_code);
        foreach ($extraSubjectKeys as $key => $row) {
            if (!isset($subjectsByKey[$key])) {
                $subjectsByKey[$key] = (object) [
                    'subject_id' => $row->subject_id,
                    'subject_name' => $row->subject_name ?: $row->subject_id,
                    'semester_code' => $row->semester_code,
                    'closing_form' => null,
                    'curriculum_subject_hemis_id' => null,
                ];
            }
        }
        $subjects = $subjectsByKey->values();

        if ($subjects->isEmpty()) {
            return response()->json([
                'student' => $this->formatStudent($student, $group),
                'subjects' => [],
                'audits' => [],
            ]);
        }

        // exam_schedules: guruh sanalari + shu talabaning individual sanalari
        $subjectIds = $subjects->pluck('subject_id')->unique()->all();
        $allSemCodes = $subjects->pluck('semester_code')->unique()->all();
        $groupSchedules = ExamSchedule::whereNull('student_hemis_id')
            ->where('group_hemis_id', $student->group_id)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_code', $allSemCodes)
            ->get()
            ->keyBy(fn ($s) => $s->subject_id . '|' . $s->semester_code);

        $individualSchedules = ExamSchedule::where('student_hemis_id', $hemisId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_code', $allSemCodes)
            ->get()
            ->keyBy(fn ($s) => $s->subject_id . '|' . $s->semester_code);

        // Asoslovchi hujjatlar — individual yozuvga ilova qilinganlar
        $individualIds = $individualSchedules->pluck('id')->all();
        $attachments = [];
        if (!empty($individualIds)) {
            $attachmentRows = IndividualScheduleAttachment::whereIn('exam_schedule_id', $individualIds)
                ->orderByDesc('created_at')
                ->get();
            foreach ($attachmentRows as $row) {
                $attachments[$row->exam_schedule_id][] = [
                    'id' => $row->id,
                    'filename' => $row->original_filename,
                    'mime_type' => $row->mime_type,
                    'size_bytes' => $row->size_bytes,
                    'note' => $row->note,
                    'uploaded_by_name' => $row->uploaded_by_name,
                    'uploaded_at' => $row->created_at?->format('Y-m-d H:i'),
                ];
            }
        }

        // Eligibility: har fan uchun stage hisoblash.
        $eligibility = $this->computeEligibilityForStudent($hemisId, $student->group_id, $subjects);

        $subjectsOut = [];
        foreach ($subjects as $subject) {
            $key = $subject->subject_id . '|' . $subject->semester_code;
            $g = $groupSchedules->get($key);
            $i = $individualSchedules->get($key);
            $elig = $eligibility[$subject->subject_id . '|' . $subject->semester_code] ?? [];

            $subjectsOut[] = [
                'subject_id' => (string) $subject->subject_id,
                'subject_name' => $subject->subject_name,
                'semester_code' => (string) $subject->semester_code,
                'closing_form' => $subject->closing_form,
                'group' => $g ? [
                    'oski_date' => $g->oski_date?->format('Y-m-d'),
                    'oski_time' => $g->oski_time,
                    'oski_na' => (bool) $g->oski_na,
                    'test_date' => $g->test_date?->format('Y-m-d'),
                    'test_time' => $g->test_time,
                    'test_na' => (bool) $g->test_na,
                    'oski_resit_date' => $g->oski_resit_date?->format('Y-m-d'),
                    'oski_resit_time' => $g->oski_resit_time,
                    'test_resit_date' => $g->test_resit_date?->format('Y-m-d'),
                    'test_resit_time' => $g->test_resit_time,
                    'oski_resit2_date' => $g->oski_resit2_date?->format('Y-m-d'),
                    'oski_resit2_time' => $g->oski_resit2_time,
                    'test_resit2_date' => $g->test_resit2_date?->format('Y-m-d'),
                    'test_resit2_time' => $g->test_resit2_time,
                ] : null,
                'individual' => $i ? [
                    'id' => $i->id,
                    'oski_date' => $i->oski_date?->format('Y-m-d'),
                    'oski_time' => $i->oski_time,
                    'test_date' => $i->test_date?->format('Y-m-d'),
                    'test_time' => $i->test_time,
                    'oski_resit_date' => $i->oski_resit_date?->format('Y-m-d'),
                    'oski_resit_time' => $i->oski_resit_time,
                    'test_resit_date' => $i->test_resit_date?->format('Y-m-d'),
                    'test_resit_time' => $i->test_resit_time,
                    'oski_resit2_date' => $i->oski_resit2_date?->format('Y-m-d'),
                    'oski_resit2_time' => $i->oski_resit2_time,
                    'test_resit2_date' => $i->test_resit2_date?->format('Y-m-d'),
                    'test_resit2_time' => $i->test_resit2_time,
                    'note' => $i->individual_note ?? null,
                    'override_warning' => (bool) ($i->override_warning ?? false),
                    'attachments' => $attachments[$i->id] ?? [],
                ] : null,
                'eligibility' => $elig,
            ];
        }

        // Audit tarixi (shu talaba bo'yicha oxirgi 50 ta yozuv)
        $audits = IndividualScheduleAudit::where('student_hemis_id', $hemisId)
            ->orderByDesc('created_at')
            ->limit(50)
            ->get()
            ->map(function ($a) {
                return [
                    'created_at' => $a->created_at?->format('Y-m-d H:i'),
                    'actor_name' => $a->actor_name,
                    'actor_role' => $a->actor_role,
                    'subject_name' => $a->subject_name,
                    'attempt' => $a->attempt,
                    'yn_type' => $a->yn_type,
                    'action' => $a->action,
                    'old_date' => $a->old_date?->format('Y-m-d'),
                    'old_time' => $a->old_time,
                    'new_date' => $a->new_date?->format('Y-m-d'),
                    'new_time' => $a->new_time,
                    'note' => $a->note,
                    'override_warning' => (bool) $a->override_warning,
                ];
            });

        return response()->json([
            'student' => $this->formatStudent($student, $group),
            'subjects' => $subjectsOut,
            'audits' => $audits,
        ]);
    }

    /**
     * POST: Individual imtihon sanasini saqlash (yoki yangilash).
     *
     * Payload:
     *  - student_hemis_id (required)
     *  - subject_id (required)
     *  - semester_code (required)
     *  - yn_type: 'oski' | 'test'
     *  - attempt: 1 | 2 | 3
     *  - date: YYYY-MM-DD (yoki bo'sh — o'chirish uchun "clear" ishlatiladi)
     *  - time: HH:MM (ixtiyoriy)
     *  - note: izoh (ixtiyoriy, lekin override holatda majburiy)
     *  - override: bool — eligibility ruxsat bermasa ham majburan saqlash
     */
    public function save(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $data = $request->validate([
            'student_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'yn_type' => 'required|in:oski,test',
            'attempt' => 'required|integer|in:1,2,3',
            'date' => 'nullable|date_format:Y-m-d',
            'time' => 'nullable|date_format:H:i',
            'note' => 'nullable|string|max:500',
            'override' => 'nullable|boolean',
        ]);

        $hemisId = $data['student_hemis_id'];
        $subjectId = $data['subject_id'];
        $semCode = $data['semester_code'];
        $ynType = $data['yn_type'];
        $attempt = (int) $data['attempt'];
        $newDate = $data['date'] ?? null;
        $newTime = $data['time'] ?? null;
        $note = trim((string) ($data['note'] ?? ''));
        $override = (bool) ($data['override'] ?? false);

        // Sana = null bo'lsa, individual yozuv o'chiriladi.
        if (!$newDate) {
            return $this->clearInternal($hemisId, $subjectId, $semCode, $ynType, $attempt, $note);
        }

        $student = DB::table('students')->where('hemis_id', $hemisId)->first();
        if (!$student) return response()->json(['error' => 'Talaba topilmadi'], 404);

        $subject = DB::table('curriculum_subjects')
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semCode)
            ->first();
        $subjectName = $subject?->subject_name ?? $subjectId;

        // Eligibility tekshiruvi — qaytarilgan stage va sabablar
        $elig = $this->computeEligibilityForStudent($hemisId, $student->group_id, collect([
            (object) [
                'subject_id' => $subjectId,
                'semester_code' => $semCode,
                'subject_name' => $subjectName,
                'closing_form' => $subject?->closing_form,
            ],
        ]))[$subjectId . '|' . $semCode] ?? null;

        $eligOk = $this->isAttemptAllowed($elig, $attempt);
        if (!$eligOk && !$override) {
            return response()->json([
                'error' => 'Eligibility ruxsat bermayapti. Saqlash uchun "Majburan saqlash" tugmasidan foydalaning va izoh yozing.',
                'eligibility' => $elig,
            ], 422);
        }
        if (!$eligOk && $override && $note === '') {
            return response()->json([
                'error' => 'Majburan saqlash uchun izoh maydoni majburiy.',
            ], 422);
        }

        // exam_schedules ga yozish: yoki mavjud yozuvni yangilash, yoki yangi qator
        $columnDate = $this->columnFor($ynType, $attempt, 'date');
        $columnTime = $this->columnFor($ynType, $attempt, 'time');
        if (!$columnDate) {
            return response()->json(['error' => 'Noto\'g\'ri yn_type/attempt'], 422);
        }

        $individual = ExamSchedule::where('student_hemis_id', $hemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semCode)
            ->first();

        $oldDate = $individual?->{$columnDate}?->format('Y-m-d');
        $oldTime = $individual?->{$columnTime};
        $action = $individual ? ($oldDate ? 'update' : 'set') : 'set';

        $group = DB::table('groups')->where('group_hemis_id', $student->group_id)->first();

        $attrs = [
            $columnDate => $newDate,
            $columnTime => $newTime,
            'individual_note' => $note !== '' ? $note : null,
            'override_warning' => !$eligOk,
            'updated_by' => Auth::id() ?? auth('teacher')->id(),
        ];

        if (!$individual) {
            $attrs = array_merge($attrs, [
                'department_hemis_id' => $group?->department_hemis_id ?? '',
                'specialty_hemis_id' => $group?->specialty_hemis_id ?? '',
                'curriculum_hemis_id' => $group?->curriculum_hemis_id ?? '',
                'semester_code' => $semCode,
                'group_hemis_id' => $student->group_id,
                'student_hemis_id' => $hemisId,
                'subject_id' => $subjectId,
                'subject_name' => $subjectName,
                'created_by' => Auth::id() ?? auth('teacher')->id(),
            ]);
            try {
                $individual = ExamSchedule::create($attrs);
            } catch (\Throwable $e) {
                Log::error('Individual schedule create failed: ' . $e->getMessage());
                return response()->json(['error' => 'Saqlashda xato: ' . $e->getMessage()], 500);
            }
        } else {
            try {
                $individual->update($attrs);
            } catch (\Throwable $e) {
                Log::error('Individual schedule update failed: ' . $e->getMessage());
                return response()->json(['error' => 'Saqlashda xato: ' . $e->getMessage()], 500);
            }
        }

        // Audit log
        $this->writeAudit([
            'student_hemis_id' => $hemisId,
            'student_name' => $student->full_name,
            'group_hemis_id' => (string) $student->group_id,
            'subject_id' => (string) $subjectId,
            'subject_name' => $subjectName,
            'semester_code' => (string) $semCode,
            'attempt' => $attempt,
            'yn_type' => $ynType,
            'action' => $action,
            'old_date' => $oldDate,
            'old_time' => $oldTime,
            'new_date' => $newDate,
            'new_time' => $newTime,
            'note' => $note !== '' ? $note : null,
            'override_warning' => !$eligOk,
            'eligibility_snapshot' => $elig,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Individual sana saqlandi.',
            'override_warning' => !$eligOk,
        ]);
    }

    /**
     * POST: Individual yozuvni o'chirish (talabani guruh sanasiga qaytarish).
     */
    public function clear(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $data = $request->validate([
            'student_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'yn_type' => 'required|in:oski,test',
            'attempt' => 'required|integer|in:1,2,3',
            'note' => 'nullable|string|max:500',
        ]);
        return $this->clearInternal(
            $data['student_hemis_id'], $data['subject_id'], $data['semester_code'],
            $data['yn_type'], (int) $data['attempt'], trim((string) ($data['note'] ?? ''))
        );
    }

    private function clearInternal(string $hemisId, string $subjectId, string $semCode, string $ynType, int $attempt, string $note)
    {
        $columnDate = $this->columnFor($ynType, $attempt, 'date');
        $columnTime = $this->columnFor($ynType, $attempt, 'time');

        $individual = ExamSchedule::where('student_hemis_id', $hemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semCode)
            ->first();

        if (!$individual || !$individual->{$columnDate}) {
            return response()->json(['ok' => true, 'message' => 'O\'zgarish yo\'q.']);
        }

        $oldDate = $individual->{$columnDate}?->format('Y-m-d');
        $oldTime = $individual->{$columnTime};

        $student = DB::table('students')->where('hemis_id', $hemisId)->first();
        $subject = DB::table('curriculum_subjects')->where('subject_id', $subjectId)->where('semester_code', $semCode)->first();

        $individual->update([
            $columnDate => null,
            $columnTime => null,
            'updated_by' => Auth::id() ?? auth('teacher')->id(),
        ]);

        // Boshqa hech qanday sana qolmasa, qatorni butunlay o'chiramiz
        $allDateCols = ['oski_date', 'test_date', 'oski_resit_date', 'test_resit_date', 'oski_resit2_date', 'test_resit2_date'];
        $fresh = $individual->fresh();
        $hasAny = false;
        foreach ($allDateCols as $col) {
            if (!empty($fresh->{$col})) { $hasAny = true; break; }
        }
        if (!$hasAny) {
            $fresh->delete();
        }

        $this->writeAudit([
            'student_hemis_id' => $hemisId,
            'student_name' => $student?->full_name,
            'group_hemis_id' => (string) ($student?->group_id ?? ''),
            'subject_id' => (string) $subjectId,
            'subject_name' => $subject?->subject_name ?? $subjectId,
            'semester_code' => (string) $semCode,
            'attempt' => $attempt,
            'yn_type' => $ynType,
            'action' => 'clear',
            'old_date' => $oldDate,
            'old_time' => $oldTime,
            'new_date' => null,
            'new_time' => null,
            'note' => $note !== '' ? $note : null,
            'override_warning' => false,
            'eligibility_snapshot' => null,
        ]);

        return response()->json(['ok' => true, 'message' => 'Individual sana o\'chirildi.']);
    }

    /**
     * POST: Individual exam_schedule yozuviga asoslovchi hujjat ilova qilish.
     * Payload (multipart/form-data):
     *  - student_hemis_id, subject_id, semester_code (required)
     *  - file (required, max 10MB)
     *  - note (optional, fayl haqida qisqa izoh)
     */
    public function uploadAttachment(Request $request)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $data = $request->validate([
            'student_hemis_id' => 'required|string',
            'subject_id' => 'required|string',
            'semester_code' => 'required|string',
            'file' => 'required|file|max:10240', // 10MB
            'note' => 'nullable|string|max:300',
        ]);

        $individual = ExamSchedule::where('student_hemis_id', $data['student_hemis_id'])
            ->where('subject_id', $data['subject_id'])
            ->where('semester_code', $data['semester_code'])
            ->first();

        if (!$individual) {
            return response()->json([
                'error' => 'Avval shu fan uchun individual sana qo\'ying, keyin hujjat ilova qilishingiz mumkin.',
            ], 422);
        }

        $file = $request->file('file');
        $originalName = $file->getClientOriginalName();
        $ext = $file->getClientOriginalExtension();
        $stored = $file->store(
            'individual-schedule-attachments/' . date('Y/m'),
            'local'
        );

        $user = auth()->user() ?? auth('teacher')->user();
        $uploaderName = is_object($user)
            ? ($user->full_name ?? $user->name ?? '—')
            : '—';

        $att = IndividualScheduleAttachment::create([
            'exam_schedule_id' => $individual->id,
            'student_hemis_id' => $data['student_hemis_id'],
            'subject_id' => $data['subject_id'],
            'semester_code' => $data['semester_code'],
            'original_filename' => $originalName,
            'storage_path' => $stored,
            'mime_type' => $file->getMimeType(),
            'size_bytes' => $file->getSize(),
            'uploaded_by_user_id' => $user?->getKey(),
            'uploaded_by_guard' => auth('teacher')->check() ? 'teacher' : 'web',
            'uploaded_by_name' => $uploaderName,
            'note' => trim((string) ($data['note'] ?? '')) ?: null,
        ]);

        // Audit
        $this->writeAudit([
            'student_hemis_id' => $data['student_hemis_id'],
            'student_name' => DB::table('students')->where('hemis_id', $data['student_hemis_id'])->value('full_name'),
            'group_hemis_id' => (string) $individual->group_hemis_id,
            'subject_id' => (string) $data['subject_id'],
            'subject_name' => $individual->subject_name,
            'semester_code' => (string) $data['semester_code'],
            'attempt' => 1,
            'yn_type' => 'attach',
            'action' => 'set',
            'old_date' => null, 'old_time' => null, 'new_date' => null, 'new_time' => null,
            'note' => 'Hujjat ilova qilindi: ' . $originalName . ($att->note ? ' — ' . $att->note : ''),
            'override_warning' => false,
            'eligibility_snapshot' => null,
        ]);

        return response()->json([
            'ok' => true,
            'message' => 'Hujjat ilova qilindi.',
            'attachment' => [
                'id' => $att->id,
                'filename' => $att->original_filename,
                'mime_type' => $att->mime_type,
                'size_bytes' => $att->size_bytes,
                'note' => $att->note,
                'uploaded_by_name' => $att->uploaded_by_name,
                'uploaded_at' => $att->created_at?->format('Y-m-d H:i'),
            ],
        ]);
    }

    /**
     * GET: Asoslovchi hujjatni yuklab olish.
     */
    public function downloadAttachment(Request $request, int $id)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $att = IndividualScheduleAttachment::find($id);
        if (!$att) {
            abort(404, 'Hujjat topilmadi.');
        }
        if (!Storage::disk('local')->exists($att->storage_path)) {
            abort(404, 'Fayl serverda mavjud emas.');
        }
        return Storage::disk('local')->download($att->storage_path, $att->original_filename);
    }

    /**
     * POST: Asoslovchi hujjatni o'chirish (soft delete + faylni saqlash).
     */
    public function deleteAttachment(Request $request, int $id)
    {
        if ($deny = $this->ensureAccess()) return $deny;

        $att = IndividualScheduleAttachment::find($id);
        if (!$att) {
            return response()->json(['error' => 'Hujjat topilmadi.'], 404);
        }

        $filename = $att->original_filename;
        $studentHid = $att->student_hemis_id;
        $subjectId = $att->subject_id;
        $semCode = $att->semester_code;

        // Faylni fizik o'chirmaymiz (soft delete) — kelajakda restore qilish mumkin
        $att->delete();

        // Audit
        $student = DB::table('students')->where('hemis_id', $studentHid)->first();
        $subject = DB::table('curriculum_subjects')
            ->where('subject_id', $subjectId)->where('semester_code', $semCode)->first();
        $this->writeAudit([
            'student_hemis_id' => (string) $studentHid,
            'student_name' => $student?->full_name,
            'group_hemis_id' => (string) ($student?->group_id ?? ''),
            'subject_id' => (string) $subjectId,
            'subject_name' => $subject?->subject_name ?? $subjectId,
            'semester_code' => (string) $semCode,
            'attempt' => 1,
            'yn_type' => 'attach',
            'action' => 'clear',
            'old_date' => null, 'old_time' => null, 'new_date' => null, 'new_time' => null,
            'note' => 'Hujjat o\'chirildi: ' . $filename,
            'override_warning' => false,
            'eligibility_snapshot' => null,
        ]);

        return response()->json(['ok' => true, 'message' => 'Hujjat o\'chirildi.']);
    }

    private function columnFor(string $ynType, int $attempt, string $kind): ?string
    {
        $base = $ynType === 'oski' ? 'oski' : 'test';
        $suffix = match ($attempt) { 1 => '', 2 => '_resit', 3 => '_resit2', default => null };
        if ($suffix === null) return null;
        return $base . $suffix . '_' . $kind;
    }

    private function writeAudit(array $data): void
    {
        try {
            $user = auth()->user() ?? auth('teacher')->user();
            $sessionRole = session('active_role');
            $userRole = (is_object($user) && method_exists($user, 'getRoleNames'))
                ? ($user->getRoleNames()->first())
                : null;
            $role = $sessionRole ?: $userRole;
            $actorName = '—';
            if (is_object($user)) {
                $actorName = $user->full_name ?? $user->name ?? ($user->getAttribute('full_name') ?? '—');
            }
            IndividualScheduleAudit::create(array_merge([
                'actor_user_id' => $user?->getKey(),
                'actor_guard' => auth('teacher')->check() ? 'teacher' : 'web',
                'actor_name' => $actorName,
                'actor_role' => $role,
            ], $data));
        } catch (\Throwable $e) {
            Log::warning('IndividualScheduleAudit write failed: ' . $e->getMessage());
        }
    }

    private function formatStudent($student, $group): array
    {
        return [
            'hemis_id' => (string) $student->hemis_id,
            'student_id_number' => (string) $student->student_id_number,
            'full_name' => $student->full_name,
            'group_id' => (string) $student->group_id,
            'group_name' => $student->group_name ?? ($group->name ?? ''),
            'specialty_name' => $student->specialty_name ?? ($group->specialty_name ?? ''),
            'level_code' => $student->level_code ?? null,
        ];
    }

    /**
     * Talabaning har fan uchun eligibility (1/2/3 urinishga huquqi).
     * 1-urinishda baho 60 dan past bo'lsa yoki sana o'tib baho kelmagan bo'lsa,
     * keyingi urinish avtomatik ochiladi. Xuddi shu qoida 2→3 uchun ham qo'llanadi.
     */
    private function computeEligibilityForStudent(string $hemisId, $groupId, $subjects): array
    {
        $result = [];
        if ($subjects->isEmpty()) return $result;

        $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');
        $subjectIds = $subjects->pluck('subject_id')->unique()->all();
        $semCodes = $subjects->pluck('semester_code')->unique()->all();

        $grades = DB::table('student_grades')
            ->where('student_hemis_id', $hemisId)
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_code', $semCodes)
            ->whereIn('training_type_code', [101, 102, 103])
            ->whereNull('deleted_at')
            ->select(
                'subject_id',
                'semester_code',
                'training_type_code',
                'grade',
                'retake_grade',
                $hasAttemptCol ? 'attempt' : DB::raw('1 as attempt')
            )
            ->get()
            ->groupBy(fn ($r) => $r->subject_id . '|' . $r->semester_code);

        $scheduleMap = $this->buildScheduleMap($hemisId, $groupId, $subjectIds, $semCodes);

        foreach ($subjects as $subject) {
            $key = $subject->subject_id . '|' . $subject->semester_code;
            $subjGrades = $grades->get($key) ?? collect();
            $schedule = $scheduleMap[$key] ?? null;
            $closingForm = $subject->closing_form ?? null;

            $has1 = false;
            $has2 = false;
            $has3 = false;
            $failed1ByGrade = false;
            $failed2ByGrade = false;

            foreach ($subjGrades as $g) {
                $att = (int) ($g->attempt ?? 1);
                $val = $g->retake_grade ?? $g->grade;

                if ($att <= 1) {
                    $has1 = true;
                    if ($val !== null && (float) $val < 60) {
                        $failed1ByGrade = true;
                    }
                } elseif ($att === 2) {
                    $has2 = true;
                    if ($val !== null && (float) $val < 60) {
                        $failed2ByGrade = true;
                    }
                } elseif ($att === 3) {
                    $has3 = true;
                }
            }

            $failed1ByDate = !$has1 && $this->hasAttemptDatePassed($schedule, $closingForm, 1);
            $failed2ByDate = !$has2 && $this->hasAttemptDatePassed($schedule, $closingForm, 2);

            $failed1 = $failed1ByGrade || $failed1ByDate;
            $failed2 = $failed2ByGrade || $failed2ByDate;

            $allow1 = !$has1 && !$failed1;
            $allow2 = $failed1 && !$has2 && !$failed2;
            $allow3 = $failed2 && !$has3;

            $reasons = [
                1 => $allow1
                    ? "1-urinish ochiq."
                    : ($failed1ByDate
                        ? "1-urinish sanasi o'tgan, lekin baho kelmagan."
                        : ($failed1ByGrade
                            ? "1-urinish bahosi 60 dan past."
                            : "1-urinish uchun baho allaqachon mavjud.")),
                2 => $allow2
                    ? ($failed1ByDate
                        ? "1-urinish sanasi o'tib, baho kelmagani uchun 2-urinish ochildi."
                        : "1-urinish bahosi 60 dan past bo'lgani uchun 2-urinish ochildi.")
                    : ($has2
                        ? "2-urinish uchun baho allaqachon mavjud."
                        : ($failed1
                            ? "2-urinish hali yopilmagan."
                            : "1-urinish hali keyingi bosqichga o'tmagan.")),
                3 => $allow3
                    ? ($failed2ByDate
                        ? "2-urinish sanasi o'tib, baho kelmagani uchun 3-urinish ochildi."
                        : "2-urinish bahosi 60 dan past bo'lgani uchun 3-urinish ochildi.")
                    : ($has3
                        ? "3-urinish uchun baho allaqachon mavjud."
                        : ($failed2
                            ? "3-urinish hali yopilmagan."
                            : "2-urinish hali keyingi bosqichga o'tmagan.")),
            ];

            $result[$key] = [
                'has_attempt_1_grade' => $has1,
                'has_attempt_2_grade' => $has2,
                'has_attempt_3_grade' => $has3,
                'failed_attempt_1' => $failed1,
                'failed_attempt_2' => $failed2,
                'allow_1' => $allow1,
                'allow_2' => $allow2,
                'allow_3' => $allow3,
                'reasons' => $reasons,
            ];
        }

        return $result;
    }

    private function buildScheduleMap(string $hemisId, $groupId, array $subjectIds, array $semCodes): array
    {
        $rows = ExamSchedule::query()
            ->whereIn('subject_id', $subjectIds)
            ->whereIn('semester_code', $semCodes)
            ->where(function ($q) use ($groupId, $hemisId) {
                $q->where(function ($w) use ($groupId) {
                    $w->where('group_hemis_id', $groupId)
                      ->whereNull('student_hemis_id');
                })->orWhere('student_hemis_id', $hemisId);
            })
            ->get()
            ->groupBy(fn ($row) => $row->subject_id . '|' . $row->semester_code);

        $result = [];
        foreach ($rows as $key => $groupedRows) {
            $result[$key] = $groupedRows->first(fn ($row) => (string) ($row->student_hemis_id ?? '') === $hemisId)
                ?: $groupedRows->first();
        }

        return $result;
    }

    private function hasAttemptDatePassed($schedule, ?string $closingForm, int $attempt): bool
    {
        if (!$schedule) {
            return false;
        }

        $cols = match ($attempt) {
            1 => ['oski' => 'oski_date', 'test' => 'test_date'],
            2 => ['oski' => 'oski_resit_date', 'test' => 'test_resit_date'],
            3 => ['oski' => 'oski_resit2_date', 'test' => 'test_resit2_date'],
            default => null,
        };

        if (!$cols) {
            return false;
        }

        $oskiDate = $schedule->{$cols['oski']} ?? null;
        $testDate = $schedule->{$cols['test']} ?? null;

        $dates = match ($closingForm) {
            'oski' => $oskiDate ? [$oskiDate] : [],
            'test', 'sinov', 'normativ' => $testDate ? [$testDate] : [],
            'oski_test' => ($oskiDate && $testDate) ? [$oskiDate, $testDate] : [],
            default => array_values(array_filter([$oskiDate, $testDate])),
        };

        if (empty($dates)) {
            return false;
        }

        $today = \Carbon\Carbon::now('Asia/Tashkent')->endOfDay();
        foreach ($dates as $date) {
            $parsed = $date instanceof \Carbon\CarbonInterface
                ? $date->copy()->endOfDay()
                : \Carbon\Carbon::parse($date, 'Asia/Tashkent')->endOfDay();

            if ($parsed->gt($today)) {
                return false;
            }
        }

        return true;
    }

    private function isAttemptAllowed(?array $elig, int $attempt): bool
    {
        if (!$elig) return true; // ma'lumot yo'q — admin tasdiqi bilan davom etadi
        return (bool) ($elig['allow_' . $attempt] ?? false);
    }
}
