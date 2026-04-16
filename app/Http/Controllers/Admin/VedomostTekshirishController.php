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
        $dekanFacultyIds = get_dekan_faculty_ids();
        $currentSemester = $request->input('current_semester', '1') == '1';
        $excludedCodes   = [11, 99, 100, 101, 102, 103];

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

        $rows = $query
            ->select('g.id as group_pk', 'g.group_hemis_id', 'g.name as group_name',
                     'sp.name as specialty_name', 'cs.subject_id', 'cs.subject_name',
                     'cs.credit', 'cs.semester_code', 'cs.semester_name', 's.level_code', 's.level_name',
                     'dep.name as faculty_name')
            ->orderBy('g.name')->orderBy('cs.subject_name')
            ->distinct()->get();

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

        // OSKI/Test sanalar exam_schedules jadvalida saqlanadi
        $examSchedules = DB::table('exam_schedules')
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
            ];
        }

        return response()->json($result);

        } catch (\Throwable $e) {
            \Log::error('VedomostTekshirish search error: ' . $e->getMessage(), ['trace' => $e->getTraceAsString()]);
            return response()->json(['error' => $e->getMessage()], 500);
        }
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

            try {
                $synced += app(HemisService::class)->syncExamGradesForGroup(
                    $group->group_hemis_id, $subjectId, $semesterCode ?? '', 15
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
        ]);

        $exportRows   = $request->input('rows');
        $wJn   = (int) ($request->weight_jn   ?? 50);
        $wMt   = (int) ($request->weight_mt   ?? 20);
        $wOn   = (int) ($request->weight_on   ?? 0);
        $wOski = (int) ($request->weight_oski ?? 0);
        $wTest = (int) ($request->weight_test ?? 30);
        $shaklId = (int) ($request->shakl ?? 1);

        $shakllar = config('app.shakllar', []);
        $shaklName = '12-shakl';
        foreach ($shakllar as $sh) {
            if (($sh['id'] ?? 0) === $shaklId) {
                $shaklName = $sh['name'];
                break;
            }
        }

        // --- Spreadsheet ---
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Tekshirish');

        // --- Header ---
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
            'AZ' => 'Zanjir',
            'BA' => 'JN+MT tekshiruv',
            'BB' => 'JN+MT izoh',
            'BC' => 'YN tekshiruv',
            'BD' => 'YN izoh',
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
            'AZ' => 25, 'BA' => 12, 'BB' => 20, 'BC' => 12, 'BD' => 20,
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        // --- Effective grade helper ---
        $getEffectiveGrade = function ($row) {
            if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
                return (float) $row->grade;
            }
            if ($row->status === 'pending') return null;
            if ($row->reason === 'absent' && $row->grade === null) {
                return $row->retake_grade !== null ? (float) $row->retake_grade : null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $row->retake_grade === null) {
                return null;
            }
            if ($row->status === 'recorded') return $row->grade !== null ? (float) $row->grade : null;
            if ($row->status === 'closed')   return $row->grade !== null ? (float) $row->grade : null;
            if ($row->retake_grade !== null)  return (float) $row->retake_grade;
            return null;
        };

        $roundHalfUp = fn($v) => (int) floor((float) $v + 0.5);

        // --- Ma'lumot yig'ish ---
        $dataRow  = 2;
        $rowIndex = 1;

        foreach ($exportRows as $rowData) {
            $groupId      = $rowData['group_id'];
            $subjectId    = $rowData['subject_id'];
            $semesterCode = $rowData['semester_code'];

            $group = is_numeric($groupId)
                ? Group::find($groupId)
                : Group::where('group_hemis_id', $groupId)->first();
            if (!$group) continue;
            $groupHemisId = $group->group_hemis_id;

            $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
            $educationYearCode = $curriculum?->education_year_code;

            $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->first();

            if (!$subject) continue;

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            $students = Student::where('group_id', $groupHemisId)->orderBy('full_name')->get();
            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            if (empty($studentHemisIds)) continue;

            // Education year (schedules dan — hisobot qilinayotgan joriy yil)
            $scheduleYearRow = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->whereNotNull('lesson_date')
                ->whereNotNull('education_year_code')
                ->orderBy('lesson_date', 'desc')
                ->select('education_year_code', 'education_year_name')
                ->first();
            $educationYearName = null;
            if ($scheduleYearRow) {
                $educationYearCode = $scheduleYearRow->education_year_code;
                $educationYearName = $scheduleYearRow->education_year_name;
            }

            // --- JN schedule ---
            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
            $excludedNames = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];

            $jbScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($educationYearCode, fn($q) => $q->where('education_year_code', $educationYearCode))
                ->whereNotIn('training_type_name', $excludedNames)
                ->whereNotIn('training_type_code', $excludedCodes)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->orderBy('lesson_date')->orderBy('lesson_pair_code')
                ->get();

            $jbColumns = $jbScheduleRows->map(fn($s) => [
                'date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                'pair' => $s->lesson_pair_code,
            ])->unique(fn($i) => $i['date'] . '_' . $i['pair'])->values();

            $jbDatePairSet = [];
            $jbPairsPerDay = [];
            foreach ($jbColumns as $col) {
                $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
                $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
            }

            $cutoff = Carbon::now('Asia/Tashkent')->endOfDay();
            $jbDatesForAvg = $jbColumns->pluck('date')->unique()->sort()->filter(
                fn($d) => Carbon::parse($d, 'Asia/Tashkent')->startOfDay()->lte($cutoff)
            )->values()->toArray();
            $jbDatesForAvgLookup = array_flip($jbDatesForAvg);
            $totalJbDays = count($jbDatesForAvg);

            // --- MT schedule ---
            $mtScheduleRows = DB::table('schedules')
                ->where('group_id', $groupHemisId)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNull('deleted_at')
                ->when($educationYearCode, fn($q) => $q->where('education_year_code', $educationYearCode))
                ->where('training_type_code', 99)
                ->whereNotNull('lesson_date')
                ->select('lesson_date', 'lesson_pair_code')
                ->get();

            $mtColumns = $mtScheduleRows->map(fn($s) => [
                'date' => Carbon::parse($s->lesson_date)->format('Y-m-d'),
                'pair' => $s->lesson_pair_code,
            ])->unique(fn($i) => $i['date'] . '_' . $i['pair'])->values();

            $mtDatePairSet = [];
            $mtPairsPerDay = [];
            foreach ($mtColumns as $col) {
                $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
                $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
            }
            $mtDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();
            $totalMtDays = count($mtDates);

            $minScheduleDate = collect()
                ->merge($jbColumns->pluck('date'))
                ->merge($mtColumns->pluck('date'))
                ->min();

            // --- JN va MT baholar ---
            $allGradesRaw = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->when($educationYearCode, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $educationYearCode)
                        ->orWhere(fn($q3) => $q3->whereNull('education_year_code')
                            ->when($minScheduleDate, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate)));
                }))
                ->when(!$educationYearCode && $minScheduleDate, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select('student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                ->get();

            $jbGrades = [];
            $mtGradesMap = [];
            foreach ($allGradesRaw as $g) {
                $eff = $getEffectiveGrade($g);
                if ($eff === null) continue;
                $date = Carbon::parse($g->lesson_date)->format('Y-m-d');
                $key  = $date . '_' . $g->lesson_pair_code;
                if (isset($jbDatePairSet[$key])) {
                    $jbGrades[$g->student_hemis_id][$date][$g->lesson_pair_code] = $eff;
                }
                if (isset($mtDatePairSet[$key])) {
                    $mtGradesMap[$g->student_hemis_id][$date][$g->lesson_pair_code] = $eff;
                }
            }

            // Manual MT
            $manualMt = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->where('training_type_code', 99)
                ->whereNull('lesson_date')
                ->whereNotNull('grade')
                ->select('student_hemis_id', 'grade')
                ->get()->keyBy('student_hemis_id');

            // JN/MT hisoblash
            $jnGrades = [];
            $mtGrades = [];
            foreach ($studentHemisIds as $hId) {
                // JN
                $dailySum = 0;
                $studentDayGrades = $jbGrades[$hId] ?? [];
                foreach ($jbDatesForAvg as $date) {
                    $dayGrades = $studentDayGrades[$date] ?? [];
                    $pairs = $jbPairsPerDay[$date] ?? 1;
                    $dailySum += $roundHalfUp(array_sum($dayGrades) / $pairs);
                }
                $jnGrades[$hId] = $totalJbDays > 0 ? $roundHalfUp($dailySum / $totalJbDays) : 0;

                // MT (scheduled)
                $mtDailySum = 0;
                $studentMtGrades = $mtGradesMap[$hId] ?? [];
                foreach ($mtDates as $date) {
                    $dayGrades = $studentMtGrades[$date] ?? [];
                    $pairs = $mtPairsPerDay[$date] ?? 1;
                    $mtDailySum += $roundHalfUp(array_sum($dayGrades) / $pairs);
                }
                $mtGrades[$hId] = $totalMtDays > 0 ? $roundHalfUp($mtDailySum / $totalMtDays) : 0;

                // Manual MT ustun turadi
                if (isset($manualMt[$hId])) {
                    $mtGrades[$hId] = $roundHalfUp((float) $manualMt[$hId]->grade);
                }
            }

            // --- ON, OSKI, Test baholar ---
            // YN qaydnoma yaratish bilan bir xil qiymat chiqishi uchun
            // YnQaytnomaController::generateYnQaydnoma() dagi logikani
            // aynan takrorlaymiz: har bir training_type_code uchun MAX(grade)
            // to'g'ridan-to'g'ri SQL darajasida, education_year_code/
            // lesson_date filtrlarisiz.
            $gradesByType = [100 => [], 101 => [], 102 => []];
            foreach ([100, 101, 102] as $tc) {
                $gradesByType[$tc] = DB::table('student_grades')
                    ->whereNull('deleted_at')
                    ->whereIn('student_hemis_id', $studentHemisIds)
                    ->where('subject_id', $subjectId)
                    ->where('semester_code', $semesterCode)
                    ->where('training_type_code', $tc)
                    ->select('student_hemis_id', DB::raw('MAX(grade) as grade'))
                    ->groupBy('student_hemis_id')
                    ->pluck('grade', 'student_hemis_id')
                    ->toArray();
            }

            // --- O'qituvchilar ---
            $lectureTeacher   = $this->getTopTeacher($groupHemisId, $subjectId, $semesterCode, [11]);
            $practiceTeacher  = $this->getTopTeacher($groupHemisId, $subjectId, $semesterCode, [12, 13, 14, 18]);

            // --- Davomat (attendances jadvalidan, jurnal bilan bir xil hisoblash) ---
            $totalHours = (int) ($subject->total_acload ?? 0);
            $excludedAttendanceCodes = [99, 100, 101, 102];
            $attendanceByStudent = DB::table('attendances')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->when($educationYearCode, fn($q) => $q->where('education_year_code', $educationYearCode))
                ->whereNotIn('training_type_code', $excludedAttendanceCodes)
                ->selectRaw('student_hemis_id, SUM(absent_off) as total_absent_off')
                ->groupBy('student_hemis_id')
                ->pluck('total_absent_off', 'student_hemis_id');

            $nonAuditoriumCodes = ['17'];
            $auditoriumHours = 0;
            if (is_array($subject->subject_details)) {
                foreach ($subject->subject_details as $detail) {
                    $trainingCode = (string) ($detail['trainingType']['code'] ?? '');
                    if ($trainingCode !== '' && !in_array($trainingCode, $nonAuditoriumCodes)) {
                        $auditoriumHours += (float) ($detail['academic_load'] ?? 0);
                    }
                }
            }
            if ($auditoriumHours <= 0) {
                $auditoriumHours = (float) ($subject->total_acload ?? 0);
            }

            $davomatByStudent = [];
            foreach ($students as $stu) {
                $absentOff = (float) ($attendanceByStudent[$stu->hemis_id] ?? 0);
                $davomatByStudent[$stu->hemis_id] = $auditoriumHours > 0
                    ? round(($absentOff / $auditoriumHours) * 100, 2)
                    : 0.0;
            }

            // JN divisor (PHP script mantig'i: talabalar soniga qarab)
            $totalStudents = count($studentHemisIds);
            $threshold = max(3, (int) ($totalStudents * 0.3));
            $dateCounts = DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereNotIn('training_type_code', [99, 100, 101, 102, 103])
                ->whereNotNull('lesson_date')
                ->selectRaw('DATE(lesson_date) as ld, COUNT(DISTINCT student_hemis_id) as cnt')
                ->groupBy('ld')
                ->get();
            $validDates = $dateCounts->filter(fn($r) => $r->cnt >= $threshold)->count();
            $divisor = max(1, $validDates);

            // --- HEMIS exam grades bilan taqqoslash (lokal jadvaldan) ---
            // Sync export'dan oldin alohida AJAX so'rov (syncHemis) orqali
            // amalga oshiriladi. Bu yerda faqat lokal ma'lumot o'qiladi.
            $hemisExamGrades = HemisExamGrade::forComparison($studentHemisIds, $subjectId, $semesterCode)
                ->get()
                ->groupBy('student_hemis_id');

            // --- Qatorlarni yozish ---
            foreach ($students as $stu) {
                $hId = $stu->hemis_id;

                $jnOrig  = $jnGrades[$hId] ?? 0;
                $mt      = $mtGrades[$hId] ?? 0;
                $on      = round($gradesByType[100][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $oski    = round($gradesByType[101][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $test    = round($gradesByType[102][$hId] ?? 0, 0, PHP_ROUND_HALF_UP);
                $dav     = $davomatByStudent[$hId] ?? 0;

                // JN soni — shu talaba JN (JB) darslaridan nechta kunda baho
                // yoki nb (yo'q) belgilangan bo'lsa, shuni sanaydi. Bir kunda
                // bir nechta pair bo'lsa ham 1 kun sifatida sanaladi.
                // Sanash qoidasi:
                //   * kiritilgan numerik baho (>0) — sanaladi;
                //   * nb (reason='absent') — sanaladi (retake bo'lsa yoki
                //     bo'lmasa ham);
                //   * retake_grade (otrabotka) mavjud — sanaladi;
                //   * grade = 0 va nb emas (ya'ni bo'sh ham emas) — sanaladi
                //     (o'qituvchi aniq 0 qo'ygan);
                //   * umuman yozuv yo'q yoki status='pending' — sanalmaydi.
                $jnDaysAttended = [];
                foreach ($allGradesRaw as $g) {
                    $date = Carbon::parse($g->lesson_date)->format('Y-m-d');
                    $key  = $date . '_' . $g->lesson_pair_code;
                    if (!isset($jbDatePairSet[$key])) continue;          // faqat JB jadvalidagi kunlar
                    if ($g->student_hemis_id !== $hId) continue;
                    if ($g->status === 'pending') continue;               // tugallanmagan
                    // "Yozuv mavjud" deb hisoblaymiz agar biror ma'lumot bor:
                    //   grade > 0 (HEMIS kamida 1 dan boshlab qabul qiladi,
                    //     shuning uchun 0 tozalanmagan/xato yozuv deb e'tibor
                    //     berilmaydi), retake_grade > 0, yoki reason='absent'
                    //     (nb — o'qituvchi yo'q deb belgilagan, kun sanaladi).
                    $hasEntry = ($g->grade !== null && (float) $g->grade > 0)
                        || ($g->retake_grade !== null && (float) $g->retake_grade > 0)
                        || ($g->reason === 'absent');
                    if ($hasEntry) {
                        $jnDaysAttended[$date] = true;
                    }
                }
                $jnCount = count($jnDaysAttended);

                // Davomat >= 25% bo'lsa ham JN o'rtacha qiymati saqlanadi —
                // ma'muriyatga haqiqiy ko'rsatkich ko'rinib tursin. V esa -3
                // qaytaradi va talaba FISH'iga "(≥25% davomat)" qo'shiladi.
                $jn = $jnOrig;

                // Balllar:
                //  JB/MT/ON ball — yaxlitlashsiz raw qiymat (excelda format
                //  1 kasrni ko'rsatadi);
                //  OSKI/Test ball — ikkalasi ham vaznga ega bo'lsa raw qiymat,
                //  faqat bittasi vaznga ega bo'lsa butun songacha yaxlitlanadi.
                //  Istisno: 4 yoki 5 kurs talabalari uchun JN/MT ball ham
                //  butun songacha half-up yaxlitlanadi. Semestrda level_code
                //  qiymatlari: 11=1-kurs, 12=2-kurs, 13=3-kurs, 14=4-kurs,
                //  15=5-kurs, 16=6-kurs.
                $levelCode = (string) ($semester?->level_code ?? '');
                $roundJnMtToInt = in_array($levelCode, ['14', '15'], true);

                if ($roundJnMtToInt) {
                    $jnBall = $jn >= 60 ? (int) floor($jn * $wJn / 100 + 0.5) : 0;
                    $mtBall = $mt >= 60 ? (int) floor($mt * $wMt / 100 + 0.5) : 0;
                    $onBall = $on >= 60 ? (int) floor($on * $wOn / 100 + 0.5) : 0;
                } else {
                    $jnBall = $jn >= 60 ? $jn * $wJn / 100 : 0;
                    $mtBall = $mt >= 60 ? $mt * $wMt / 100 : 0;
                    $onBall = $on >= 60 ? $on * $wOn / 100 : 0;
                }

                if ($wOski > 0 && $wTest > 0) {
                    $oskiBall = $oski >= 60 ? $oski * $wOski / 100 : 0;
                    $testBall = $test >= 60 ? $test * $wTest / 100 : 0;
                } elseif ($wOski > 0) {
                    $oskiBall = $oski >= 60 ? (int) round($oski * $wOski / 100) : 0;
                    $testBall = 0;
                } elseif ($wTest > 0) {
                    $oskiBall = 0;
                    $testBall = $test >= 60 ? (int) round($test * $wTest / 100) : 0;
                } else {
                    $oskiBall = 0;
                    $testBall = 0;
                }

                // M ustun (JN+MT) — ballarni yig'ib butun songacha half-up
                // yaxlitlanadi.
                $sumBall = (int) floor($jnBall + $mtBall + $onBall + 0.5);
                $maxSum  = $wJn + $wMt + $wOn;

                // YN natija — ekrandagi ball'lardan hisoblanadi, shuning uchun
                // ularni ham uzatamiz (aks holda 4-5 kurs yoki faqat bitta
                // imtihon vaznga ega bo'lgan holda V ekran yig'indisi bilan
                // mos kelmaydi).
                $yn = $this->calcYn($jn, $mt, $on, $oski, $test, $wJn, $wMt, $wOn, $wOski, $wTest, $dav,
                    (float) $jnBall, (float) $mtBall, (float) $onBall, (float) $oskiBall, (float) $testBall);

                // Yakuniy qiymatni butun songacha half-up yaxlitlaymiz (V ustun
                // butun son chiqishi kerak). Maxsus qiymatlar ('', -2, -1, 0)
                // o'zgartirilmaydi — ular shart kodlari.
                if (is_numeric($yn) && $yn > 0) {
                    $yn = (int) floor((float) $yn + 0.5);
                }

                // ECTS
                $ects = $this->toEcts($yn);

                // Baho
                $baho = $this->toBaho($yn);

                // Zanjir
                $zanjir = $this->checkZanjir($jn, $mt, $on, $oski, $test, $wJn, $wMt, $wOn, $wOski, $wTest);

                $r = $dataRow;

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
                if (!$roundJnMtToInt) {
                    $sheet->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
                $sheet->setCellValue("G{$r}", $mt);
                $sheet->setCellValue("H{$r}", $mtBall);
                if (!$roundJnMtToInt) {
                    $sheet->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
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
                    // Ikkalasi ham vaznga ega — 1 kasr ko'rinishi
                    $sheet->getStyle("Q{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
                $sheet->setCellValue("S{$r}", $test);
                $sheet->setCellValue("T{$r}", $testBall);
                if ($wOski > 0 && $wTest > 0) {
                    $sheet->getStyle("T{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
                $sheet->setCellValue("V{$r}", $yn === '' ? '' : $yn);
                $sheet->setCellValue("W{$r}", $ects);
                $sheet->setCellValue("X{$r}", $practiceTeacher);
                $sheet->setCellValue("Y{$r}", $baho);
                $sheet->setCellValue("Z{$r}", (string) $hId);
                $sheet->setCellValue("AA{$r}", $subjectId);
                $sheet->setCellValue("AB{$r}", $subject->subject_name ?? '');
                $sheet->setCellValue("AC{$r}", $shaklName);
                // Hisobot qilinayotgan joriy o'quv yili (schedule bo'yicha),
                // mavjud bo'lmasa curriculum'dagi yilga qaytamiz.
                $sheet->setCellValue("AD{$r}", $educationYearName ?? ($curriculum?->education_year_name ?? ''));
                $sheet->setCellValue("AE{$r}", $semester?->name ?? $semesterCode);
                $sheet->setCellValue("AF{$r}", (string) $groupHemisId);
                $sheet->setCellValue("AG{$r}", $group->name ?? '');
                $sheet->setCellValue("AH{$r}", $totalHours);
                $sheet->setCellValue("AI{$r}", (int) ($subject->credit ?? 0));
                $sheet->setCellValue("AJ{$r}", $lectureTeacher);
                $sheet->setCellValue("AK{$r}", $jnCount);
                $sheet->setCellValue("AL{$r}", $divisor);
                $sheet->setCellValue("AM{$r}", $dav / 100);
                $sheet->getStyle("AM{$r}")->getNumberFormat()->setFormatCode('0.00%');
                $sheet->setCellValue("AN{$r}", $wJn);
                $sheet->setCellValue("AO{$r}", $wMt);
                $sheet->setCellValue("AP{$r}", $wOn);
                $sheet->setCellValue("AQ{$r}", $wOski);
                $sheet->setCellValue("AR{$r}", $wTest);
                $sheet->setCellValue("AZ{$r}", $zanjir);

                // --- HEMIS bilan taqqoslash ---
                $studentHemisRecords = $hemisExamGrades[$hId] ?? collect();

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

                $sheet->setCellValue("BA{$r}", $jnMtCheck);
                $sheet->setCellValue("BB{$r}", $jnMtReason);
                $sheet->setCellValue("BC{$r}", $ynCheck);
                $sheet->setCellValue("BD{$r}", $ynReason);

                // Rang berish
                $this->applyRowStyle($sheet, $r, $yn, $dav);

                $dataRow++;
                $rowIndex++;
            }
        } // end rows loop

        // Freeze pane
        $sheet->freezePane('D2');

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

    // --- Helpers ---

    private function getTopTeacher(string $groupHemisId, string $subjectId, string $semesterCode, array $typeCodes): string
    {
        $row = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereIn('training_type_code', $typeCodes)
            ->whereNotNull('employee_name')
            ->selectRaw('employee_name, COUNT(*) as cnt')
            ->groupBy('employee_name')
            ->orderByDesc('cnt')
            ->first();

        return $row?->employee_name ?? '';
    }

    private function calcYn(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest, float $dav, float $jnBall = 0.0, float $mtBall = 0.0, float $onBall = 0.0, float $oskiBall = 0.0, float $testBall = 0.0): string|int|float
    {
        // Bo'sh talaba
        if ($jn === 0 && $mt === 0) return '';

        // Davomat >= 25% → -3 (davomat ≥25%)
        if ($dav >= 25) return -3;

        // Imtihonga kirmaganlar
        $oskiMissing = $wOski > 0 && $oski == 0;
        $testMissing = $wTest > 0 && $test == 0;
        if ($oskiMissing || $testMissing) {
            // JN+MT >= 60% dan o'tganlar uchun -1
            $sumBall = ($jn >= 60 ? $jn * $wJn / 100 : 0) + ($mt >= 60 ? $mt * $wMt / 100 : 0) + ($on >= 60 ? $on * $wOn / 100 : 0);
            $maxPre = $wJn + $wMt + $wOn;
            if ($maxPre > 0 && $sumBall / $maxPre >= 0.6) {
                return -1;
            }
        }

        // Har qanday komponent < 60 → 0
        if (($wJn > 0 && $jn < 60) || ($wMt > 0 && $mt < 60) || ($wOn > 0 && $on < 60) || ($wOski > 0 && $oski < 60) || ($wTest > 0 && $test < 60)) {
            return 0;
        }

        // Yakuniy ball — ekranga chiqadigan ball'lardan hisoblanadi (4-5 kurs
        // uchun butun songa yaxlitlangan JN/MT/ON, faqat bittasi vaznga ega
        // OSKI/Test holatlarida butun songa yaxlitlangan ball). Bu V'ni ekran
        // ustunlari yig'indisiga mos qiladi.
        $jbMtOnSum = round($jnBall + $mtBall + $onBall, 1);

        if ($wOski > 0 && $wTest > 0) {
            $examSum = round($oskiBall + $testBall, 1);
        } elseif ($wOski > 0) {
            $examSum = round($oskiBall, 1);
        } elseif ($wTest > 0) {
            $examSum = round($testBall, 1);
        } else {
            $examSum = 0;
        }

        return $jbMtOnSum + $examSum;
    }

    private function toEcts(string|int|float $yn): string
    {
        if (!is_numeric($yn) || $yn < 0) return '';
        $yn = (float) $yn;
        if ($yn >= 90) return 'A';
        if ($yn >= 85) return 'B+';
        if ($yn >= 70) return 'B';
        if ($yn >= 60) return 'C';
        return 'F';
    }

    private function toBaho(string|int|float $yn): string
    {
        if ($yn === '') return '';
        if ($yn === -3) return "davomat \u{2265}25%";
        if ($yn === -2) return "qo\u{2018}yilmadi";
        if ($yn === -1) return 'kelmadi';
        if (!is_numeric($yn)) return '';
        $yn = (float) $yn;
        if ($yn >= 90) return "a\u{02BC}lo";
        if ($yn >= 70) return 'yaxshi';
        if ($yn >= 60) return "o\u{02BB}rta";
        return 'qon-siz';
    }

    private function checkZanjir(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest): string
    {
        $warnings = [];
        $jnPass   = $wJn   === 0 || $jn   >= 60;
        $mtPass   = $wMt   === 0 || $mt   >= 60;
        $onPass   = $wOn   === 0 || $on   >= 60;
        $oskiPass = $wOski === 0 || $oski >= 60;

        if ($oski > 0) {
            if (!$jnPass && $wJn > 0) $warnings[] = 'JN✖→OSKI';
            if (!$mtPass && $wMt > 0) $warnings[] = 'MT✖→OSKI';
            if (!$onPass && $wOn > 0) $warnings[] = 'ON✖→OSKI';
        }
        if ($test > 0) {
            if (!$jnPass && $wJn > 0) $warnings[] = 'JN✖→Test';
            if (!$mtPass && $wMt > 0) $warnings[] = 'MT✖→Test';
            if (!$onPass && $wOn > 0) $warnings[] = 'ON✖→Test';
            if (!$oskiPass && $wOski > 0) $warnings[] = 'OSKI✖→Test';
        }
        return implode(', ', $warnings);
    }

    private function applyRowStyle($sheet, int $row, string|int|float $yn, float $dav): void
    {
        $range = "A{$row}:BD{$row}";

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
