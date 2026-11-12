<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProjectRole;
use App\Http\Controllers\Controller;
use App\Models\CurriculumSubject;
use App\Models\Curriculum;
use App\Models\Department;
use App\Models\Group;
use App\Models\HemisExamGrade;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Teacher;
use App\Services\HemisService;
use App\Services\YnAttemptStatusService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\IOFactory as SpreadsheetIOFactory;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Color as SpreadsheetColor;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\RichText\RichText;

class VedomostTekshirishController extends Controller
{
    private array $allowedRoles = [
        'superadmin', 'admin', 'kichik_admin',
        'registrator_ofisi', 'oquv_bolimi', 'oquv_bolimi_boshligi', 'oquv_prorektori',
    ];

    public function index()
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        $dekanFacultyIds = get_dekan_faculty_ids();

        $facultyQuery = Department::where('structure_type_code', 11)
            ->where('active', true)
            ->orderBy('name');
        if (!empty($dekanFacultyIds)) {
            $facultyQuery->whereIn('id', $dekanFacultyIds);
        }
        $faculties = $facultyQuery->get();

        $educationTypes = Curriculum::select('education_type_code', 'education_type_name')
            ->whereNotNull('education_type_code')
            ->groupBy('education_type_code', 'education_type_name')
            ->get();

        $defaultEducationType = $educationTypes
            ->first(fn($t) => str_contains(mb_strtolower($t->education_type_name ?? ''), 'bakalavr'))
            ?->education_type_code ?? '';

        $kafedras = DB::table('curriculum_subjects as cs')
            ->join('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                  ->on('s.code', '=', 'cs.semester_code');
            })
            ->where('s.current', true)
            ->whereNotNull('cs.department_id')
            ->whereNotNull('cs.department_name')
            ->select('cs.department_id', 'cs.department_name')
            ->groupBy('cs.department_id', 'cs.department_name')
            ->orderBy('cs.department_name')
            ->get();

        return view('admin.vedomost_tekshirish.index', compact(
            'faculties', 'educationTypes', 'kafedras', 'dekanFacultyIds', 'defaultEducationType'
        ));
    }

    public function search(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        try {
        $excludedCodes   = [11, 99, 100, 101, 102, 103];

        $query = $this->filteredQuery($request);

        $rows = $query
            ->select('g.id as group_pk', 'g.group_hemis_id', 'g.name as group_name',
                     'sp.name as specialty_name', 'cs.subject_id', 'cs.subject_name',
                     'cs.credit', 'cs.semester_code', 'cs.semester_name', 'cs.closing_form',
                     's.level_code', 's.level_name',
                     'dep.name as faculty_name')
            ->orderBy('g.name')->orderBy('cs.subject_name')
            ->distinct()->get();

        // Fallback: agar asosiy query bo'sh qaytarsa va guruh tanlangan bo'lsa,
        // schedules jadvalidan fan/semester ma'lumotlarini olish
        if ($rows->isEmpty() && $request->filled('group_ids')) {
            $fallbackGroupIds = (array) $request->group_ids;
            $fallbackGroups = Group::whereIn('id', $fallbackGroupIds)->get();
            $fallbackRows = collect();
            foreach ($fallbackGroups as $fg) {
                $schedSubjects = DB::table('schedules')
                    ->where('group_id', $fg->group_hemis_id)
                    ->where('education_year_current', true)
                    ->whereNull('deleted_at')
                    ->whereNotNull('lesson_date')
                    ->select('subject_id', 'subject_name', 'semester_code')
                    ->distinct()
                    ->get();
                $dep = Department::where('department_hemis_id', $fg->department_hemis_id)->first();
                $sp = \App\Models\Specialty::where('specialty_hemis_id', $fg->specialty_hemis_id)->first();
                foreach ($schedSubjects as $ss) {
                    if ($request->filled('subject_ids') && !in_array($ss->subject_id, (array) $request->subject_ids)) continue;
                    if ($request->filled('semester_code') && $ss->semester_code != $request->semester_code) continue;
                    $fallbackRows->push((object) [
                        'group_pk' => $fg->id,
                        'group_hemis_id' => $fg->group_hemis_id,
                        'group_name' => $fg->name,
                        'specialty_name' => $sp->name ?? '',
                        'subject_id' => $ss->subject_id,
                        'subject_name' => $ss->subject_name,
                        'credit' => null,
                        'semester_code' => $ss->semester_code,
                        'semester_name' => '',
                        'level_code' => '',
                        'level_name' => '',
                        'faculty_name' => $dep->name ?? '',
                    ]);
                }
            }
            $rows = $fallbackRows;
        }

        if ($rows->isEmpty()) {
            return response()->json([]);
        }

        // --- Batch schedule date queries ---
        $allGroupHemisIds = $rows->pluck('group_hemis_id')->unique()->values()->toArray();
        $allSubjectIds    = $rows->pluck('subject_id')->unique()->values()->toArray();

        $schedDates = DB::table('schedules')
            ->whereNull('deleted_at')
            ->whereIn('group_id', $allGroupHemisIds)
            ->whereIn('subject_id', $allSubjectIds)
            ->whereNotIn('training_type_code', $excludedCodes)
            ->whereNotNull('lesson_date')
            ->selectRaw('group_id, subject_id, semester_code, MIN(lesson_date) as min_date, MAX(lesson_date) as max_date')
            ->groupBy('group_id', 'subject_id', 'semester_code')
            ->get()
            ->keyBy(fn($r) => $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code);

        // OSKI/Test sanalar exam_schedules jadvalida saqlanadi.
        // Faqat guruh sathidagi yozuvlar (student_hemis_id NULL): per-student
        // (individual 2/3-urinish) yozuvlarida oski_date/test_date NULL bo'ladi,
        // va kalit student_hemis_id ni o'z ichiga olmagani uchun keyBy ularni
        // guruh qatori ustiga yozib, sanani "—" qilib ko'rsatib qo'yardi.
        $examSchedules = DB::table('exam_schedules')
            ->whereNull('student_hemis_id')
            ->whereIn('group_hemis_id', $allGroupHemisIds)
            ->whereIn('subject_id', $allSubjectIds)
            ->select('group_hemis_id', 'subject_id', 'semester_code', 'oski_date', 'test_date')
            ->get()
            ->keyBy(fn($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code);

        // YN date filter
        $ynFrom = null;
        $ynTo   = null;
        if ($request->filled('yn_date_from')) {
            try { $ynFrom = Carbon::createFromFormat('Y-m-d', trim($request->yn_date_from))->format('Y-m-d'); } catch (\Throwable $e) {
                try { $ynFrom = Carbon::createFromFormat('d.m.Y', trim($request->yn_date_from))->format('Y-m-d'); } catch (\Throwable $e2) {}
            }
        }
        if ($request->filled('yn_date_to')) {
            try { $ynTo = Carbon::createFromFormat('Y-m-d', trim($request->yn_date_to))->format('Y-m-d'); } catch (\Throwable $e) {
                try { $ynTo = Carbon::createFromFormat('d.m.Y', trim($request->yn_date_to))->format('Y-m-d'); } catch (\Throwable $e2) {}
            }
        }

        $result = [];
        foreach ($rows as $row) {
            $key      = $row->group_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code;
            $sched    = $schedDates[$key] ?? null;
            $examRow  = $examSchedules[$key] ?? null;

            $dateStart = $sched?->min_date ? Carbon::parse($sched->min_date)->format('d.m.Y') : null;
            $dateEnd   = $sched?->max_date ? Carbon::parse($sched->max_date)->format('d.m.Y') : null;
            $oskiDate  = $examRow?->oski_date ? Carbon::parse($examRow->oski_date)->format('d.m.Y') : null;
            $testDate  = $examRow?->test_date ? Carbon::parse($examRow->test_date)->format('d.m.Y') : null;

            // YN date filter — exam_schedules jadvalidagi oski_date/test_date bo'yicha
            if ($ynFrom || $ynTo) {
                $oskiRaw = $examRow?->oski_date;
                $testRaw = $examRow?->test_date;
                // OSKI yoki Test sanalaridan kamida biri oraliqqa to'g'ri kelsa ko'rsatiladi
                $oskiMatch = $oskiRaw && (!$ynFrom || $oskiRaw >= $ynFrom) && (!$ynTo || $oskiRaw <= $ynTo);
                $testMatch = $testRaw && (!$ynFrom || $testRaw >= $ynFrom) && (!$ynTo || $testRaw <= $ynTo);
                if (!$oskiMatch && !$testMatch) continue;
            }

            $result[] = [
                'group_pk'       => $row->group_pk,
                'group_hemis_id' => $row->group_hemis_id,
                'group_name'     => $row->group_name,
                'faculty_name'   => $row->faculty_name ?? '',
                'specialty_name' => $row->specialty_name ?? '',
                'level_code'     => $row->level_code ?? '',
                'level_name'     => $row->level_name ?? '',
                'subject_id'     => $row->subject_id,
                'subject_name'   => $row->subject_name,
                'credit'         => $row->credit,
                'semester_code'  => $row->semester_code,
                'semester_name'  => $row->semester_name ?? '',
                'date_start'     => $dateStart,
                'date_end'       => $dateEnd,
                'oski_date'      => $oskiDate,
                'test_date'      => $testDate,
                'closing_form'   => $row->closing_form ?? null,
            ];
        }

        return response()->json($result);

        } catch (\Throwable $e) {
            \Log::error('VedomostTekshirish search error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Sahifa filtrlari bo'yicha guruh × fan (curriculum_subjects × groups × semesters) so'rovi —
     * qidiruv va tasdiqlangan qaydnomalar ro'yxati uchun umumiy. Dekan — o'z fakulteti.
     */
    private function filteredQuery(Request $request)
    {
        $dekanFacultyIds = get_dekan_faculty_ids();
        $currentSemester = $request->input('current_semester', '1') == '1';

        // --- Base query: curriculum_subjects × groups × semesters ---
        $query = DB::table('curriculum_subjects as cs')
            ->join('groups as g', 'g.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
            ->join('semesters as s', function ($j) {
                $j->on('s.curriculum_hemis_id', '=', 'cs.curricula_hemis_id')
                  ->on('s.code', '=', 'cs.semester_code');
            })
            ->leftJoin('specialties as sp', 'sp.specialty_hemis_id', '=', 'g.specialty_hemis_id')
            ->leftJoin('departments as dep', 'dep.department_hemis_id', '=', 'g.department_hemis_id')
            ->where('g.department_active', true)
            ->where('g.active', true);

        if ($currentSemester) {
            $query->where('s.current', true);
        }

        // Education type
        if ($request->filled('education_type')) {
            $et = $request->education_type;
            $query->whereExists(function ($sub) use ($et) {
                $sub->select(DB::raw(1))->from('curricula as c')
                    ->whereColumn('c.curricula_hemis_id', 'cs.curricula_hemis_id')
                    ->where('c.education_type_code', $et);
            });
        }

        // Faculty
        if ($request->filled('faculty_id')) {
            $dh = Department::find($request->faculty_id)?->department_hemis_id;
            if ($dh) $query->where('g.department_hemis_id', $dh);
        }

        // Dekan restriction
        if (!empty($dekanFacultyIds)) {
            $dhs = Department::whereIn('id', $dekanFacultyIds)->pluck('department_hemis_id');
            $query->whereIn('g.department_hemis_id', $dhs);
        }

        if ($request->filled('specialty_id')) {
            $query->where('g.specialty_hemis_id', $request->specialty_id);
        }
        if ($request->filled('level_code')) {
            $query->where('s.level_code', $request->level_code);
        }
        if ($request->filled('semester_code')) {
            $query->where('cs.semester_code', $request->semester_code);
        }
        if ($request->filled('department_id')) {
            $query->where('cs.department_id', $request->department_id);
        }
        if ($request->filled('group_ids')) {
            $query->whereIn('g.id', (array) $request->group_ids);
        }
        if ($request->filled('subject_ids')) {
            $query->whereIn('cs.subject_id', (array) $request->subject_ids);
        }
        return $query;
    }

    /**
     * Dekan tasdiqlagan YN qaydnomalar — sahifa filtrlari bo'yicha (filtrsiz ham: sukut bo'yicha
     * joriy semestr), eng so'nggi tasdiqlanganlari birinchi.
     */
    public function confirmed(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);
        $limit = 500;
        $key = fn ($r) => $r->group_hemis_id . '|' . $r->subject_id . '|' . $r->semester_code;
        $Confirmation = \App\Models\YnQaydnomaConfirmation::class;

        // Ro'yxat tasdiqlarning o'zidan quriladi (siklli fanlar curriculum_subjects da bo'lmasligi
        // mumkin — qidiruvdagi fallback kabi); filtrlar guruh / semestr / o'quv reja bo'yicha.
        // "Joriy semestr" qo'llanmaydi — tasdiqlangan qaydnoma o'tgan semestrniki bo'lsa ham ko'rinadi
        $deans = $Confirmation::with('verification')->where('role', $Confirmation::ROLE_DEAN)
            ->orderByDesc('confirmed_at')->get()->unique($key)->values();
        $groups = Group::whereIn('group_hemis_id', $deans->pluck('group_hemis_id')->unique()->values())->get()->keyBy(fn ($g) => (string) $g->group_hemis_id);
        $curricula = $groups->pluck('curriculum_hemis_id')->filter()->unique()->values();
        $semesters = DB::table('semesters')->whereIn('curriculum_hemis_id', $curricula)
            ->get(['curriculum_hemis_id', 'code', 'current', 'level_code', 'level_name'])
            ->keyBy(fn ($s) => $s->curriculum_hemis_id . '|' . $s->code);
        $subjects = DB::table('curriculum_subjects')->whereIn('curricula_hemis_id', $curricula)
            ->whereIn('subject_id', $deans->pluck('subject_id')->unique()->values())
            ->get(['curricula_hemis_id', 'subject_id', 'semester_code', 'subject_name', 'semester_name', 'department_id'])
            ->keyBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->subject_id . '|' . $s->semester_code);
        $educationTypes = DB::table('curricula')->whereIn('curricula_hemis_id', $curricula)->pluck('education_type_code', 'curricula_hemis_id');
        $faculties = Department::whereIn('department_hemis_id', $groups->pluck('department_hemis_id')->unique()->values())->pluck('name', 'department_hemis_id');
        $specialties = \App\Models\Specialty::whereIn('specialty_hemis_id', $groups->pluck('specialty_hemis_id')->unique()->values())->pluck('name', 'specialty_hemis_id');

        $dekanFacultyIds = get_dekan_faculty_ids();
        $allowedFaculties = $dekanFacultyIds ? Department::whereIn('id', $dekanFacultyIds)->pluck('department_hemis_id')->map(fn ($v) => (string) $v)->all() : null;
        $facultyFilter = $request->filled('faculty_id') ? (string) Department::find($request->faculty_id)?->department_hemis_id : null;
        $groupIds = array_map('strval', (array) $request->input('group_ids', []));
        $subjectIds = array_map('strval', (array) $request->input('subject_ids', []));

        $rows = $deans->map(function ($c) use ($groups, $semesters, $subjects, $educationTypes) {
            $g = $groups->get((string) $c->group_hemis_id);
            if (!$g) {
                return null;
            }

            return (object) [
                'confirmation' => $c,
                'group' => $g,
                'semester' => $semesters->get($g->curriculum_hemis_id . '|' . $c->semester_code),
                'subject' => $subjects->get($g->curriculum_hemis_id . '|' . $c->subject_id . '|' . $c->semester_code),
                'education_type' => (string) ($educationTypes[$g->curriculum_hemis_id] ?? ''),
            ];
        })->filter()->filter(function ($r) use ($request, $allowedFaculties, $facultyFilter, $groupIds, $subjectIds) {
            $g = $r->group;
            $c = $r->confirmation;

            return (!$request->filled('education_type') || $r->education_type === (string) $request->education_type)
                && ($allowedFaculties === null || in_array((string) $g->department_hemis_id, $allowedFaculties, true))
                && ($facultyFilter === null || (string) $g->department_hemis_id === $facultyFilter)
                && (!$request->filled('specialty_id') || (string) $g->specialty_hemis_id === (string) $request->specialty_id)
                && (!$request->filled('level_code') || (string) ($r->semester->level_code ?? '') === (string) $request->level_code)
                && (!$request->filled('semester_code') || (string) $c->semester_code === (string) $request->semester_code)
                && (!$request->filled('department_id') || (string) ($r->subject->department_id ?? '') === (string) $request->department_id)
                && (!$groupIds || in_array((string) $g->id, $groupIds, true))
                && (!$subjectIds || in_array((string) $c->subject_id, $subjectIds, true));
        });

        // YN sanasi (OSKI yoki test) oralig'i — qidiruvdagidek
        $from = $request->filled('yn_date_from') ? $this->parseDate($request->yn_date_from) : null;
        $to = $request->filled('yn_date_to') ? $this->parseDate($request->yn_date_to) : null;
        if (($from || $to) && $rows->isNotEmpty()) {
            $exams = DB::table('exam_schedules')
                ->whereNull('student_hemis_id')
                ->whereIn('group_hemis_id', $rows->map(fn ($r) => (string) $r->confirmation->group_hemis_id)->unique()->values())
                ->whereIn('subject_id', $rows->map(fn ($r) => (string) $r->confirmation->subject_id)->unique()->values())
                ->get(['group_hemis_id', 'subject_id', 'semester_code', 'oski_date', 'test_date'])
                ->keyBy($key);
            $inRange = fn ($d) => $d && (!$from || substr($d, 0, 10) >= $from) && (!$to || substr($d, 0, 10) <= $to);
            $rows = $rows->filter(fn ($r) => ($e = $exams->get($key($r->confirmation))) && ($inRange($e->oski_date) || $inRange($e->test_date)));
        }

        $more = $rows->count() > $limit;
        $rows = $rows->take($limit)->values();

        // Kichik guruhlar (a/b/c) — bitta qaydnoma: barcha guruhlarining o'qituvchilari, nomi — harfsiz
        foreach ($rows as $r) {
            $c = $r->confirmation;
            $r->members = \App\Services\YnqFamily::membersFor((string) $c->subject_id, (string) $c->semester_code,
                (string) $c->group_hemis_id, $r->group->name, $r->group->curriculum_hemis_id);
        }
        // Amaliy o'qituvchi(lar) — shu qaydnomalar bo'yicha
        $teachers = $Confirmation::where('role', $Confirmation::ROLE_TEACHER)
            ->whereIn('group_hemis_id', $rows->flatMap(fn ($r) => $r->members)->unique()->values())
            ->whereIn('subject_id', $rows->map(fn ($r) => (string) $r->confirmation->subject_id)->unique()->values())
            ->get(['group_hemis_id', 'subject_id', 'semester_code', 'teacher_id', 'teacher_name'])
            ->groupBy($key);

        $items = $rows->map(function ($r) use ($teachers, $faculties, $specialties) {
            $c = $r->confirmation;
            $v = $c->verification;

            return [
                'faculty_name' => $faculties[$r->group->department_hemis_id] ?? ($v->department_name ?? ''),
                'specialty_name' => $specialties[$r->group->specialty_hemis_id] ?? '',
                'level_name' => $r->semester->level_name ?? '',
                'group_name' => \App\Services\YnqFamily::label($r->group->name, count($r->members)),
                'subject_name' => $r->subject->subject_name ?? ($v->subject_name ?? ''),
                'semester_name' => $r->subject->semester_name ?? ($v->semester_name ?? ''),
                'teachers' => collect($r->members)->flatMap(fn ($g) => $teachers[$g . '|' . $c->subject_id . '|' . $c->semester_code] ?? collect())
                    ->unique('teacher_id')->pluck('teacher_name')->values(),
                'dean_name' => $c->teacher_name,
                'dean_at' => $c->confirmed_at->format('d.m.Y H:i'),
                'pdf_url' => route('admin.vedomost-tekshirish.qaydnoma-pdf', [
                    'subject_id' => $c->subject_id, 'semester_code' => $c->semester_code, 'group_hemis_id' => $c->group_hemis_id,
                ]),
                'pdf_ready' => (bool) $c->pdf_path,
                'key' => ['subject_id' => (string) $c->subject_id, 'semester_code' => (string) $c->semester_code, 'group_hemis_id' => (string) $c->group_hemis_id],
            ];
        })->values();

        return response()->json(['items' => $items, 'more' => $more, 'limit' => $limit,
            // Superadmin — qaydnomani butunlay o'chirishi mumkin
            'can_delete' => auth()->user()->hasRole('superadmin') && !session('impersonating')]);
    }

    /**
     * Tasdiqlangan YN qaydnoma PDF da: har varaq (12-shakl, 12a, ...) — bitta sahifa, bitta fayl.
     * O'qituvchi tasdiqlagan vaznlar bilan, barcha QR lari bilan.
     */
    public function qaydnomaPdf(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);
        $data = $request->validate(['subject_id' => 'required', 'semester_code' => 'required', 'group_hemis_id' => 'required']);
        [$subjectId, $semesterCode, $groupHemisId] = [(string) $data['subject_id'], (string) $data['semester_code'], (string) $data['group_hemis_id']];

        $Confirmation = \App\Models\YnQaydnomaConfirmation::class;
        $base = $Confirmation::latestFor($subjectId, $semesterCode, $groupHemisId)->first();
        abort_unless($base && $Confirmation::latestFor($subjectId, $semesterCode, $groupHemisId, $Confirmation::ROLE_DEAN)->isNotEmpty(), 404,
            'Qaydnoma hali dekan tomonidan tasdiqlanmagan.');

        // "PDF" (yuklab olish) — har bosilganda eskisi o'chirilib, joriy ko'rinish va baholar bo'yicha yangisi;
        // "Ko'rish" (inline) — mavjud PDF brauzerda ochiladi (yo'q bo'lsa — tayyorlanadi)
        $inline = $request->boolean('inline');
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $stored = $inline ? $Confirmation::forVedomost($subjectId, $semesterCode, $groupHemisId)
            ->where('role', $Confirmation::ROLE_DEAN)->latest('confirmed_at')->value('pdf_path') : null;
        $path = $stored && $disk->exists($stored)
            ? $stored
            : app(JournalController::class)->generateYnQaydnomaPdf($subjectId, $semesterCode, $groupHemisId);
        abort_unless($path, 422, 'PDF tayyorlab bo\'lmadi.');

        // Kichik guruhlar qaydnomasi — umumiy (harfsiz) nomi tasdiqda saqlangan
        $group = $base->verification->group_names ?? Group::where('group_hemis_id', $groupHemisId)->value('name') ?? $groupHemisId;
        $subject = $base->verification->subject_name ?? $subjectId;
        $name = str_replace(['/', '\\', ' '], '_', 'YN_qaydnoma_' . $group . '_' . $subject) . '.pdf';

        if ($inline) {
            return response()->file($disk->path($path), [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => 'inline; filename="' . $name . '"',
            ]);
        }

        return response()->download($disk->path($path), $name, ['Content-Type' => 'application/pdf']);
    }

    private function parseDate(string $value): ?string
    {
        foreach (['Y-m-d', 'd.m.Y'] as $format) {
            try {
                return Carbon::createFromFormat($format, trim($value))->format('Y-m-d');
            } catch (\Throwable $e) {
            }
        }

        return null;
    }

    /**
     * Export'dan oldin HEMIS exam grades ni sync qilish (AJAX).
     * Frontend avval shu endpointni chaqiradi, keyin export formni yuboradi.
     */
    public function syncHemis(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        $rows = $request->input('rows', []);
        $synced = 0;

        foreach ($rows as $rowData) {
            $groupId      = $rowData['group_id'] ?? null;
            $subjectId    = $rowData['subject_id'] ?? null;
            $semesterCode = $rowData['semester_code'] ?? null;
            if (!$groupId || !$subjectId) continue;

            $group = is_numeric($groupId)
                ? Group::find($groupId)
                : Group::where('group_hemis_id', $groupId)->first();
            if (!$group) continue;

            // Guruh talabalarining HEMIS ID'larini olamiz
            $studentHemisIds = Student::where('group_id', $group->group_hemis_id)
                ->pluck('hemis_id')
                ->toArray();
            if (empty($studentHemisIds)) continue;

            // Curriculum'dan educationYearCode
            $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
            $educationYearCode = $curriculum?->education_year_code;
            $scheduleYear = DB::table('schedules')
                ->where('group_id', $group->group_hemis_id)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->value('education_year_code');
            if ($scheduleYear) $educationYearCode = $scheduleYear;

            try {
                $synced += app(HemisService::class)->syncExamGradesForGroup(
                    $studentHemisIds, $subjectId, $semesterCode ?? '', $educationYearCode, 15
                );
            } catch (\Throwable $e) {
                // HEMIS javob bermasa — davom etamiz
            }
        }

        return response()->json(['synced' => $synced]);
    }

    public function export(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        $request->validate([
            'rows'                 => 'required|array|min:1',
            'rows.*.group_id'      => 'required',
            'rows.*.subject_id'    => 'required|string',
            'rows.*.semester_code' => 'required|string',
            'weight_jn'    => 'nullable|integer|min:0|max:100',
            'weight_mt'    => 'nullable|integer|min:0|max:100',
            'weight_on'    => 'nullable|integer|min:0|max:100',
            'weight_oski'  => 'nullable|integer|min:0|max:100',
            'weight_test'  => 'nullable|integer|min:0|max:100',
            'pass_score'   => 'nullable|integer|min:1|max:100',
        ]);

        $exportRows   = $request->input('rows');

        // Sinov fanlari uchun student_grades sinov_yn_test va SinovTestGrade
        // yozuvlarini eksportdan oldin avtomatik backfill qilish (eski oqim yoki
        // lesson_pair_code bug'i sababli yo'qolgan yozuvlar bo'lsa).
        foreach ($exportRows as $r) {
            try {
                $g = Group::where('id', $r['group_id'])->first();
                if (!$g) continue;
                \App\Http\Controllers\Admin\JournalController::backfillSinovDataForGroup(
                    (string) $r['subject_id'],
                    (string) $r['semester_code'],
                    (string) $g->group_hemis_id
                );
            } catch (\Throwable $e) {
                \Log::warning('Sinov backfill failed for export row: ' . $e->getMessage());
            }
        }

        $wJn   = (int) ($request->weight_jn   ?? 50);
        $wMt   = (int) ($request->weight_mt   ?? 20);
        $wOn   = (int) ($request->weight_on   ?? 0);
        $wOski = (int) ($request->weight_oski ?? 0);
        $wTest = (int) ($request->weight_test ?? 30);
        // O'tish bali — Excel yaratishda so'raladi (sahifada standart 70)
        $pass  = (int) ($request->pass_score ?? \App\Models\MarkingSystemScore::lowGradeLimit());
        $shaklId = (int) ($request->shakl ?? 1);

        $shakllar = config('app.shakllar', []);
        $shaklName = '12-shakl';
        foreach ($shakllar as $sh) {
            if (($sh['id'] ?? 0) === $shaklId) {
                $shaklName = $sh['name'];
                break;
            }
        }

        // Qo'shimcha / 12a / 12b varaqlaridagi "Qaydnoma turi" (config('app.shakllar') id'lari)
        $formShaklIds = [
            YnAttemptStatusService::FORM_12_QOSHIMCHA  => 2,
            YnAttemptStatusService::FORM_12A           => 3,
            YnAttemptStatusService::FORM_12A_QOSHIMCHA => 4,
            YnAttemptStatusService::FORM_12B           => 5,
            YnAttemptStatusService::FORM_12B_QOSHIMCHA => 6,
        ];
        $formLabels = [YnAttemptStatusService::FORM_12 => $shaklName];
        foreach ($formShaklIds as $form => $id) {
            $formLabels[$form] = collect($shakllar)->firstWhere('id', $id)['name'] ?? $form;
        }

        $calcService = app(\App\Services\VedomostTekshirishService::class);
        $journal = app(JournalController::class);

        // --- Ma'lumot yig'ish ---
        // Har shakl alohida varaqqa: 12-shakl — avvalgidek tekshiruv hisobi bilan;
        // qo'shimcha / 12a / 12b — jurnal YN qaydnomasidagi talabalar va baholar
        // (ynQaydnomaForms), lekin shu jadval ustunlarida.
        $rowsByForm = array_fill_keys(array_keys($formLabels), []);

        foreach ($exportRows as $rowData) {
            $groupId      = $rowData['group_id'];
            $subjectId    = $rowData['subject_id'];
            $semesterCode = $rowData['semester_code'];

            $group = is_numeric($groupId)
                ? Group::find($groupId)
                : Group::where('group_hemis_id', $groupId)->first();
            if (!$group) continue;
            // Hisob (JN/MT/ON/OSKI/Test, davomat, o'qituvchilar) — umumiy servisda:
            // AI tekshiruv ham aynan shu qiymatlarni ishlatadi.
            $collected = $calcService->collect($group, (string) $subjectId, (string) $semesterCode);
            if (!$collected) continue;
            [
                'groupHemisId' => $groupHemisId, 'curriculum' => $curriculum, 'educationYearName' => $educationYearName,
                'subject' => $subject, 'semester' => $semester, 'students' => $students,
                'studentHemisIds' => $studentHemisIds, 'raw' => $rawByStudent, 'lectureTeacher' => $lectureTeacher,
                'practiceTeacher' => $practiceTeacher, 'totalHours' => $totalHours, 'divisor' => $divisor,
            ] = $collected;

            // --- HEMIS exam grades bilan taqqoslash (lokal jadvaldan) ---
            // Sync export'dan oldin alohida AJAX so'rov (syncHemis) orqali
            // amalga oshiriladi. Bu yerda faqat lokal ma'lumot o'qiladi.
            // subject_id bo'yicha filtrlash — HEMIS API'dagi subject.id
            // bilan bizning subject_id mos kelishi kerak.
            $hemisExamGrades = HemisExamGrade::forComparison($studentHemisIds, $subjectId, $semesterCode)
                ->get()
                ->groupBy('student_hemis_id');

            $levelCode = (string) ($semester?->level_code ?? '');
            $common = [
                'practiceTeacher' => $practiceTeacher, 'subjectId' => $subjectId, 'subjectName' => $subject->subject_name ?? '',
                'educationYear' => $educationYearName ?? ($curriculum?->education_year_name ?? ''),
                'semesterName' => $semester?->name ?? $semesterCode, 'groupHemisId' => (string) $groupHemisId,
                'groupName' => $group->name ?? '', 'totalHours' => $totalHours, 'credit' => (int) ($subject->credit ?? 0),
                'lectureTeacher' => $lectureTeacher, 'divisor' => $divisor,
                'weights' => [$wJn, $wMt, $wOn, $wOski, $wTest],
                'pass' => $pass,
            ];

            foreach ($students as $stu) {
                $hId = $stu->hemis_id;
                $rowsByForm[YnAttemptStatusService::FORM_12][] = $common + [
                    'student' => $stu,
                    'calc' => $calcService->compute($rawByStudent[$hId], $wJn, $wMt, $wOn, $wOski, $wTest, $levelCode, $pass),
                    'jnCount' => $rawByStudent[$hId]['jnCount'],
                    'shakl' => $formLabels[YnAttemptStatusService::FORM_12],
                    'hemis' => $hemisExamGrades[$hId] ?? collect(),
                ];
            }

            $forms = $journal->ynQaydnomaForms(
                (string) $subjectId, (string) $semesterCode, (string) $groupHemisId, $wJn, $wMt, $wOn, $wOski, $wTest
            );
            foreach (is_array($forms) ? $forms['forms'] : [] as $form => $data) {
                if ($form === YnAttemptStatusService::FORM_12) {
                    continue;
                }
                foreach ($data['students'] as $stu) {
                    $hId = $stu->hemis_id;
                    $raw = [
                        'jn'   => (int) ($data['jn'][$hId] ?? 0),
                        'mt'   => (int) ($data['mt'][$hId] ?? 0),
                        'on'   => round($data['other'][100][$hId] ?? 0, 0, PHP_ROUND_HALF_UP),
                        'oski' => round($data['other'][101][$hId] ?? 0, 0, PHP_ROUND_HALF_UP),
                        'test' => round($data['other'][102][$hId] ?? 0, 0, PHP_ROUND_HALF_UP),
                        'dav'  => $rawByStudent[$hId]['dav'] ?? 0,
                    ];
                    $rowsByForm[$form][] = $common + [
                        'student' => $stu,
                        'calc' => $calcService->compute($raw, $wJn, $wMt, $wOn, $wOski, $wTest, $levelCode, $pass),
                        'jnCount' => $rawByStudent[$hId]['jnCount'] ?? 0,
                        'shakl' => $formLabels[$form],
                        // HEMIS'dagi qayta topshirish YN bahosi bilan taqqoslanmaydi
                        'hemis' => null,
                    ];
                }
            }
        } // end rows loop

        // --- Varaqlar: 12-shakl doim, qolganlari talabasi bo'lsa ---
        $spreadsheet = new Spreadsheet();
        $first = true;
        foreach ($rowsByForm as $form => $rows) {
            if (!$first && !$rows) {
                continue;
            }
            $sheet = $first ? $spreadsheet->getActiveSheet() : $spreadsheet->createSheet();
            $sheet->setTitle(mb_substr($formLabels[$form], 0, 31));
            $this->writeCheckHeader($sheet);
            foreach ($rows as $i => $row) {
                $this->writeCheckRow($sheet, $i + 2, $i + 1, $row);
            }
            // Freeze pane
            $sheet->freezePane('D2');
            $first = false;
        }
        $spreadsheet->setActiveSheetIndex(0);

        // Fayl yuborish
        $writer = SpreadsheetIOFactory::createWriter($spreadsheet, 'Xlsx');
        if (method_exists($writer, 'setPreCalculateFormulas')) {
            $writer->setPreCalculateFormulas(false);
        }

        $filename = 'vedomost_tekshirish_' . now()->format('d.m.Y_His') . '.xlsx';

        return response()->streamDownload(function () use ($writer) {
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    /** Tekshiruv jadvali sarlavhasi va ustun kengliklari */
    private function writeCheckHeader($sheet): void
    {
        $headers = [
            'A' => '#',
            'B' => 'FIO',
            'C' => 'ID raqami',
            'D' => 'JN',
            'E' => 'JN ball',
            'F' => '',
            'G' => 'MT',
            'H' => 'MT ball',
            'I' => '',
            'J' => 'ON',
            'K' => 'ON ball',
            'L' => '',
            'M' => 'JN+MT',
            'N' => 'JN+MT %',
            'O' => '',
            'P' => 'OSKI',
            'Q' => 'OSKI ball',
            'R' => '',
            'S' => 'Test',
            'T' => 'Test ball',
            'U' => '',
            'V' => 'YN natija',
            'W' => 'ECTS',
            'X' => 'Amaliyot o\'q.',
            'Y' => 'Baho',
            'Z' => 'Talaba HEMIS ID',
            'AA' => 'Fan ID',
            'AB' => 'Fan nomi',
            'AC' => 'Qaydnoma turi',
            'AD' => 'O\'quv yili',
            'AE' => 'Semestr',
            'AF' => 'Guruh HEMIS ID',
            'AG' => 'Guruh nomi',
            'AH' => 'Soat',
            'AI' => 'Kredit',
            'AJ' => 'Ma\'ruzachi',
            'AK' => 'JN soni',
            'AL' => 'Divisor',
            'AM' => 'Davomat %',
            'AN' => 'jn_vazn',
            'AO' => 'mt_vazn',
            'AP' => 'on_vazn',
            'AQ' => 'oski_vazn',
            'AR' => 'test_vazn',
            'AS' => 'Zanjir',
            'AT' => 'JN+MT tekshiruv',
            'AU' => 'JN+MT izoh',
            'AV' => 'YN tekshiruv',
            'AW' => 'YN izoh',
            'AX' => 'O\'tish bali',
        ];

        $sheet->getRowDimension(1)->setRowHeight(30);
        foreach ($headers as $col => $label) {
            $sheet->setCellValue($col . '1', $label);
            $sheet->getStyle($col . '1')->applyFromArray([
                'font' => ['bold' => true, 'size' => 9],
                'alignment' => [
                    'horizontal' => Alignment::HORIZONTAL_CENTER,
                    'vertical'   => Alignment::VERTICAL_CENTER,
                    'wrapText'   => true,
                ],
                'fill' => [
                    'fillType' => Fill::FILL_SOLID,
                    'startColor' => ['argb' => 'FFD9E1F2'],
                ],
                'borders' => [
                    'allBorders' => [
                        'borderStyle' => Border::BORDER_THIN,
                        'color' => ['argb' => 'FF000000'],
                    ],
                ],
            ]);
        }

        // Ustun kengliklarini belgilash
        $colWidths = [
            'A' => 4, 'B' => 30, 'C' => 14, 'D' => 6, 'E' => 7,
            'F' => 2, 'G' => 6, 'H' => 7, 'I' => 2, 'J' => 6,
            'K' => 7, 'L' => 2, 'M' => 7, 'N' => 7, 'O' => 2,
            'P' => 6, 'Q' => 7, 'R' => 2, 'S' => 6, 'T' => 7,
            'U' => 2, 'V' => 7, 'W' => 6, 'X' => 20, 'Y' => 10,
            'Z' => 14, 'AA' => 10, 'AB' => 28, 'AC' => 14,
            'AD' => 14, 'AE' => 8, 'AF' => 12, 'AG' => 16,
            'AH' => 6, 'AI' => 6, 'AJ' => 22, 'AK' => 8,
            'AL' => 7, 'AM' => 8,
            'AN' => 8, 'AO' => 8, 'AP' => 8, 'AQ' => 9, 'AR' => 9,
            'AS' => 25, 'AT' => 12, 'AU' => 20, 'AV' => 12, 'AW' => 20,
            'AX' => 9,
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }
    }

    /**
     * Tekshiruv jadvalining bitta talaba qatori. $d['hemis'] — HEMIS baholari
     * (taqqoslash uchun); null bo'lsa tekshiruv ustunlari bo'sh qoladi.
     */
    private function writeCheckRow($sheet, int $r, int $rowIndex, array $d): void
    {
        $stu = $d['student'];
        $hId = $stu->hemis_id;
        [$wJn, $wMt, $wOn, $wOski, $wTest] = $d['weights'];
        [
            'jn' => $jn, 'mt' => $mt, 'on' => $on, 'oski' => $oski, 'test' => $test, 'dav' => $dav,
            'jnBall' => $jnBall, 'mtBall' => $mtBall, 'onBall' => $onBall, 'oskiBall' => $oskiBall,
            'testBall' => $testBall, 'sumBall' => $sumBall, 'maxSum' => $maxSum, 'yn' => $yn,
            'ects' => $ects, 'baho' => $baho, 'zanjir' => $zanjir, 'roundJnMtToInt' => $roundJnMtToInt,
        ] = $d['calc'];

        // Hujayralarga yozish
        $sheet->setCellValue("A{$r}", $rowIndex);
        $fioLabel = $stu->full_name;
        if ($dav >= 25) {
            // Haqiqiy davomat foizini ko'rsatamiz (1 kasr, oxiridagi
            // nollar tozalanadi): 30 → "30", 30.5 → "30.5", 30.15 → "30.2"
            $davStr = rtrim(rtrim(number_format($dav, 1, '.', ''), '0'), '.');
            $fioLabel .= " ({$davStr}% davomat)";
        }
        $sheet->setCellValue("B{$r}", $fioLabel);
        $sheet->setCellValueExplicit("C{$r}", (string) $stu->student_id_number, DataType::TYPE_STRING);
        $sheet->setCellValue("D{$r}", $jn);
        $sheet->setCellValue("E{$r}", $jnBall);
        $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0');
        $sheet->setCellValue("G{$r}", $mt);
        $sheet->setCellValue("H{$r}", $mtBall);
        $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0');
        $sheet->setCellValue("J{$r}", $on);
        $sheet->setCellValue("K{$r}", $onBall);
        if ($wOn > 0 && !$roundJnMtToInt) {
            $sheet->getStyle("K{$r}")->getNumberFormat()->setFormatCode('0.0');
        }
        $sheet->setCellValue("M{$r}", $sumBall);
        if ($maxSum > 0) {
            $sheet->setCellValue("N{$r}", $sumBall / $maxSum);
            $sheet->getStyle("N{$r}")->getNumberFormat()->setFormatCode('0%');
        }
        $sheet->setCellValue("P{$r}", $oski);
        $sheet->setCellValue("Q{$r}", $oskiBall);
        if ($wOski > 0 && $wTest > 0) {
            // Ikkalasi ham vaznga ega — 2 kasr ko'rinishi (masalan 83*15/100=12.45)
            $sheet->getStyle("Q{$r}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->setCellValue("S{$r}", $test);
        $sheet->setCellValue("T{$r}", $testBall);
        if ($wOski > 0 && $wTest > 0) {
            $sheet->getStyle("T{$r}")->getNumberFormat()->setFormatCode('0.00');
        }
        $sheet->setCellValue("V{$r}", $yn === '' ? '' : $yn);
        $sheet->setCellValue("W{$r}", $ects);
        $sheet->setCellValue("X{$r}", $d['practiceTeacher']);
        $sheet->setCellValue("Y{$r}", $baho);
        $sheet->setCellValue("Z{$r}", (string) $hId);
        $sheet->setCellValue("AA{$r}", $d['subjectId']);
        $sheet->setCellValue("AB{$r}", $d['subjectName']);
        $sheet->setCellValue("AC{$r}", $d['shakl']);
        // Hisobot qilinayotgan joriy o'quv yili (schedule bo'yicha),
        // mavjud bo'lmasa curriculum'dagi yilga qaytamiz.
        $sheet->setCellValue("AD{$r}", $d['educationYear']);
        $sheet->setCellValue("AE{$r}", $d['semesterName']);
        $sheet->setCellValue("AF{$r}", $d['groupHemisId']);
        $sheet->setCellValue("AG{$r}", $d['groupName']);
        $sheet->setCellValue("AH{$r}", $d['totalHours']);
        $sheet->setCellValue("AI{$r}", $d['credit']);
        $sheet->setCellValue("AJ{$r}", $d['lectureTeacher']);
        $sheet->setCellValue("AK{$r}", $d['jnCount']);
        $sheet->setCellValue("AL{$r}", $d['divisor']);
        $sheet->setCellValue("AM{$r}", $dav / 100);
        $sheet->getStyle("AM{$r}")->getNumberFormat()->setFormatCode('0.00%');
        $sheet->setCellValue("AN{$r}", $wJn);
        $sheet->setCellValue("AO{$r}", $wMt);
        $sheet->setCellValue("AP{$r}", $wOn);
        $sheet->setCellValue("AQ{$r}", $wOski);
        $sheet->setCellValue("AR{$r}", $wTest);
        $sheet->setCellValue("AS{$r}", $zanjir);
        $sheet->setCellValue("AX{$r}", $d['pass']);

        // --- HEMIS bilan taqqoslash ---
        if ($d['hemis'] !== null) {
            $studentHemisRecords = $d['hemis'];

            // JN+MT: examType.code = '11'
            $hemisJnMt = $studentHemisRecords->firstWhere('exam_type_code', '11')?->grade;
            if ($hemisJnMt === null) {
                $jnMtCheck = 'D';
                $jnMtReason = "Hemisda yo'q";
            } elseif ((int) $hemisJnMt !== (int) $sumBall) {
                $jnMtCheck = 'D';
                $jnMtReason = "{$sumBall}≠{$hemisJnMt}";
            } else {
                $jnMtCheck = '';
                $jnMtReason = '';
            }

            // YN: examType.code = '13' (faqat V > 0 bo'lganda)
            $hemisYn = $studentHemisRecords->firstWhere('exam_type_code', '13')?->grade;
            if (is_numeric($yn) && $yn > 0) {
                if ($hemisYn === null) {
                    $ynCheck = 'D';
                    $ynReason = "Hemisda yo'q";
                } elseif ((int) $hemisYn !== (int) $yn) {
                    $ynCheck = 'D';
                    $ynReason = "{$yn}≠{$hemisYn}";
                } else {
                    $ynCheck = '';
                    $ynReason = '';
                }
            } else {
                $ynCheck = '';
                $ynReason = '';
            }

            $sheet->setCellValue("AT{$r}", $jnMtCheck);
            $sheet->setCellValue("AU{$r}", $jnMtReason);
            $sheet->setCellValue("AV{$r}", $ynCheck);
            $sheet->setCellValue("AW{$r}", $ynReason);
        }

        // Rang berish
        $this->applyRowStyle($sheet, $r, $yn, $dav);
    }

    // --- Helpers ---

    private function applyRowStyle($sheet, int $row, string|int|float $yn, float $dav): void
    {
        $range = "A{$row}:AX{$row}";

        $sheet->getStyle($range)->applyFromArray([
            'font' => ['size' => 9],
            'borders' => [
                'allBorders' => [
                    'borderStyle' => Border::BORDER_THIN,
                    'color' => ['argb' => 'FFD0D0D0'],
                ],
            ],
        ]);

        // Rang — YN qaydnoma shabloni bilan bir xil:
        //  V = -3 → davomat ≥25%: butun qator kursiv qizil shrift
        //  V = -2 → qizil (FFFFC1C1)
        //  V = -1 → pushti (FFFEC2F1)
        //  V =  0 → sariq (FFFFFFCC)
        if ($yn === -3 || $dav >= 25) {
            $sheet->getStyle($range)->getFont()->setItalic(true)->getColor()->setARGB('FFFF0000');
        } elseif ($yn === -2) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFC1C1');
        } elseif ($yn === -1) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFEC2F1');
        } elseif ($yn === 0) {
            $sheet->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFFFFFCC');
        }
    }
}
