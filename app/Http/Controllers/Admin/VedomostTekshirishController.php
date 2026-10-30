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
    /** Bitta qaydnoma faylida ko'pi bilan shuncha (guruh, fan) — har biri shablon yuklaydi */
    public const QAYDNOMA_MAX_ROWS = 40;

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
            'AS' => 'Zanjir',
            'AT' => 'JN+MT tekshiruv',
            'AU' => 'JN+MT izoh',
            'AV' => 'YN tekshiruv',
            'AW' => 'YN izoh',
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
        ];
        foreach ($colWidths as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        $calcService = app(\App\Services\VedomostTekshirishService::class);

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

            // --- Qatorlarni yozish ---
            foreach ($students as $stu) {
                $hId = $stu->hemis_id;

                $levelCode = (string) ($semester?->level_code ?? '');
                $calc = $calcService->compute($rawByStudent[$hId], $wJn, $wMt, $wOn, $wOski, $wTest, $levelCode);
                [
                    'jn' => $jn, 'mt' => $mt, 'on' => $on, 'oski' => $oski, 'test' => $test, 'dav' => $dav,
                    'jnBall' => $jnBall, 'mtBall' => $mtBall, 'onBall' => $onBall, 'oskiBall' => $oskiBall,
                    'testBall' => $testBall, 'sumBall' => $sumBall, 'maxSum' => $maxSum, 'yn' => $yn,
                    'ects' => $ects, 'baho' => $baho, 'zanjir' => $zanjir, 'roundJnMtToInt' => $roundJnMtToInt,
                ] = $calc;
                $jnCount = $rawByStudent[$hId]['jnCount'];

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
                $sheet->setCellValue("AS{$r}", $zanjir);

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

                $sheet->setCellValue("AT{$r}", $jnMtCheck);
                $sheet->setCellValue("AU{$r}", $jnMtReason);
                $sheet->setCellValue("AV{$r}", $ynCheck);
                $sheet->setCellValue("AW{$r}", $ynReason);

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

    /**
     * Tanlangan (guruh, fan) qatorlari uchun YN qaydnoma — jurnaldagi "YN qaydnoma
     * yaratish" bilan bir xil: har qator uchun 12-shakl va faol bo'lgan qo'shimcha,
     * 12a, 12b shakllari alohida varaqlarda, hammasi bitta Excel faylda.
     * Chetlashgan talabalar kirmaydi.
     */
    public function exportQaydnoma(Request $request)
    {
        abort_unless(auth()->user()->hasAnyRole($this->allowedRoles), 403);

        $request->validate([
            'rows'                 => 'required|array|min:1|max:' . self::QAYDNOMA_MAX_ROWS,
            'rows.*.group_id'      => 'required',
            'rows.*.subject_id'    => 'required|string',
            'rows.*.semester_code' => 'required|string',
            'weight_jn'   => 'nullable|integer|min:0|max:100',
            'weight_mt'   => 'nullable|integer|min:0|max:100',
            'weight_on'   => 'nullable|integer|min:0|max:100',
            'weight_oski' => 'nullable|integer|min:0|max:100',
            'weight_test' => 'nullable|integer|min:0|max:100',
        ], [
            'rows.max' => 'Bir martada ko\'pi bilan ' . self::QAYDNOMA_MAX_ROWS . ' ta qator tanlang.',
        ]);

        $weights = [
            (int) ($request->weight_jn ?? 50), (int) ($request->weight_mt ?? 20), (int) ($request->weight_on ?? 0),
            (int) ($request->weight_oski ?? 0), (int) ($request->weight_test ?? 30),
        ];
        abort_if(array_sum($weights) !== 100, 422, "Vaznlar jami 100 bo'lishi kerak");

        @set_time_limit(600);

        $rows = $request->input('rows');
        $multi = count($rows) > 1;
        $journal = app(JournalController::class);
        $workbook = null;
        $fileName = null;
        $skipped = [];

        foreach ($rows as $i => $r) {
            $group = Group::find($r['group_id']);
            if (!$group) {
                $skipped[] = [$i + 1, '—', $r['subject_id'], 'Guruh topilmadi'];
                continue;
            }

            $built = $journal->buildYnQaydnomaSpreadsheet(
                (string) $r['subject_id'], (string) $r['semester_code'], (string) $group->group_hemis_id, ...$weights
            );
            if (is_string($built)) {
                $skipped[] = [$i + 1, $group->name, $r['subject_id'], $built];
                continue;
            }
            [$book, $fileName] = $built;

            // Bir nechta qatorda varaq nomlari (guruh nomi) takrorlanadi — qator raqami bilan ajratamiz
            foreach ($book->getWorksheetIterator() as $ws) {
                $title = $multi ? ($i + 1) . '. ' . $ws->getTitle() : $ws->getTitle();
                $ws->setTitle($this->uniqueSheetTitle(array_filter([$workbook, $book]), $title, $ws));
            }

            if ($workbook === null) {
                $workbook = $book;
                continue;
            }
            foreach ($book->getAllSheets() as $ws) {
                $workbook->addExternalSheet($ws);
            }
            $book->disconnectWorksheets();
        }

        if ($workbook === null) {
            return back()->with('error', 'Qaydnoma yaratilmadi: ' . implode('; ', array_map(fn ($s) => $s[3], $skipped)));
        }

        if ($skipped) {
            $sheet = $workbook->createSheet();
            $sheet->setTitle($this->uniqueSheetTitle([$workbook], "O'tkazib yuborilgan", $sheet));
            $sheet->fromArray([['Qator', 'Guruh', 'Fan ID', 'Sabab'], ...$skipped]);
            $sheet->getStyle('A1:D1')->getFont()->setBold(true);
            foreach (range('A', 'D') as $col) {
                $sheet->getColumnDimension($col)->setAutoSize(true);
            }
        }
        $workbook->setActiveSheetIndex(0);

        $filename = $multi
            ? 'YN_qaydnoma_' . now()->format('d.m.Y_His') . '.xlsx'
            : $fileName;
        $writer = SpreadsheetIOFactory::createWriter($workbook, 'Xlsx');

        return response()->streamDownload(function () use ($writer, $workbook) {
            $writer->save('php://output');
            $workbook->disconnectWorksheets();
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        ]);
    }

    // --- Helpers ---

    /**
     * Excel varaq nomi: 31 belgigacha, berilgan workbook'larning hech birida
     * takrorlanmaydi ($self — nomi o'zgarayotgan varaqning o'zi hisobga olinmaydi).
     *
     * @param Spreadsheet[] $books
     */
    private function uniqueSheetTitle(array $books, string $title, $self = null): string
    {
        $base = mb_substr(str_replace(['/', '\\', '*', '?', ':', '[', ']'], '_', $title), 0, 31);
        $taken = function (string $name) use ($books, $self) {
            foreach ($books as $book) {
                $found = $book->getSheetByName($name);
                if ($found && $found !== $self) {
                    return true;
                }
            }

            return false;
        };

        $candidate = $base;
        for ($n = 2; $taken($candidate); $n++) {
            $suffix = ' (' . $n . ')';
            $candidate = mb_substr($base, 0, 31 - mb_strlen($suffix)) . $suffix;
        }

        return $candidate;
    }

    private function applyRowStyle($sheet, int $row, string|int|float $yn, float $dav): void
    {
        $range = "A{$row}:AW{$row}";

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
