<?php

namespace App\Http\Controllers;

use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Department;
use App\Models\Group;
use App\Models\MarkingSystemScore;
use App\Models\Schedule;
use App\Models\Semester;
use App\Models\Student;
use App\Models\Vedomost;
use App\Models\VedomostGroup;
use App\Services\StudentGradeService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\IOFactory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Worksheet\Drawing;

class VedomostController extends Controller
{

    public $studentGradeService;
    public function __construct()
    {
        $this->studentGradeService = new StudentGradeService;
    }
    public function index(Request $request)
    {
        $query = new Vedomost();
        if ($request->department) {
            $query = $query->where('department_id', $request->department);
        }
        // if ($request->full_name) {
        //     $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        // }
        if ($request->group) {
            $query = $query->where('group_id', $request->group);
        }
        if ($request->semester) {
            $query = $query->where('semester_id', $request->semester);
        }
        if ($request->subject) {
            $query = $query->where('subject_id', $request->subject);
        }

        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $vedomosts = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('admin.vedomost.index', compact('vedomosts', 'departments'));
    }
    public function index_teacher(Request $request)
    {
        $query = new Vedomost();
        if ($request->department) {
            $query = $query->where('department_id', $request->department);
        }
        if ($request->full_name) {
            $query = $query->where('teacher_name', "LIKE", "%" . $request->full_name . "%");
        }
        if ($request->group) {
            $query = $query->where('group_id', $request->group);
        }
        if ($request->semester) {
            $query = $query->where('semester_id', $request->semester);
        }
        if ($request->subject) {
            $query = $query->where('subject_id', $request->subject);
        }

        // with('department', 'teacher', 'group', 'semester', 'subject');
        $perPage = $request->get('per_page', 50);
        $vedomosts = $query->orderBy('id', 'desc')->paginate($perPage)->appends($request->query());
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();

        return view('teacher.vedomost.index', compact('vedomosts', 'departments'));
    }
    public function create()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $semesters = Semester::select('code', "name")->groupBy('code', 'name')->orderBy('code', 'asc')->get();

        return view('admin.vedomost.create', compact('departments', 'semesters'));
    }

    public function teacherCreate()
    {
        $departments = Department::where('structure_type_code', 11)->orderBy('id', 'desc')->get();
        $semesters = Semester::select('code', "name")->groupBy('code', 'name')->orderBy('code', 'asc')->get();

        return view('teacher.vedomost.create', compact('departments', 'semesters'));
    }
    function telegram_error($text)
    {
        $token = "8124753525:AAFmZKZgHaNvorfvtx3XmDdAM5WjJl8PbGo";
        if (gettype($text) != "string") {
            $text = json_encode($text);
        }
        if (env('APP_ENV') == 'local') {
            $text = "#local " . $text;
        }
        $text_array = str_split($text, 4000);
        foreach ($text_array as $val) {
            $data = [
                'chat_id' => "904664945",
                'text' => $val
            ];
            try {
                file_get_contents("https://api.telegram.org/bot$token/sendMessage?" . http_build_query($data));
            } catch (\Throwable $throwable) {
            }
        }
    }
    public function store(Request $request)
    {
        try {
            DB::beginTransaction();
            session([
                'vedomost' => [
                    'department_id' => $request->department_id,
                    'semester_code' => $request->semester_code,
                    'shakl' => $request->shakl,
                    'group_name' => $request->group_name,
                    'subject_name' => $request->subject_name,
                    'type' => $request->type,
                    'exam_percend' => $request->exam_percend,
                    'oske_percend' => $request->oske_percend,
                    'oraliq_percent' => $request->oraliq_percent,
                    'jn_percend' => $request->jn_percend,
                    'independent_percend' => $request->independent_percend,
                    'jn_percend2' => $request->jn_percend2,
                    'independent_percend2' => $request->independent_percend2,
                ]
            ]);
            $deportment = Department::where('department_hemis_id', $request->department_id ?? 0)->first();
            if (empty($deportment)) {
                DB::rollBack();
                return back()->with('error', 'Departament tanlang');
            }
            $semester = Semester::where('code', $request->semester_code ?? "")->first();
            if (empty($semester)) {
                DB::rollBack();
                return back()->with('error', 'Semester tanlang');
            }

            if (empty($request->shakl)) {
                DB::rollBack();
                return back()->with('error', 'Shakl tanlang');
            }
            if (empty($request->group_name)) {
                DB::rollBack();
                return back()->with('error', 'Guruh nomini kiriting');
            }
            if (empty($request->subject_name)) {
                DB::rollBack();
                return back()->with('error', 'Fan nomini kiriting');
            }
            if (empty($request->type)) {
                DB::rollBack();
                return back()->with('error', 'Bittalik fanmi yoki ikkitalik kiriting');
            }
            if (!isset($request->exam_percend)) {
                DB::rollBack();
                return back()->with('error', 'Test foizini kiriting');
            }
            if (!isset($request->oske_percend)) {
                DB::rollBack();
                return back()->with('error', 'Oski foizini kiriting');
            }
            if (!isset($request->oraliq_percent)) {
                DB::rollBack();
                return back()->with('error', 'Oraliq foizini kiriting');
            }
            if (!isset($request->jn_percend)) {
                DB::rollBack();
                return back()->with('error', 'Joriy foizini kiriting');
            }
            if (!isset($request->independent_percend)) {
                DB::rollBack();
                return back()->with('error', 'Mustaqil ta\'lim foizini kiriting');
            }
            if ($request->type == 2) {
                if (!isset($request->jn_percend2)) {
                    DB::rollBack();
                    return back()->with('error', '2 fan ucuhn Joriy foizini kiriting');
                }
                if (!isset($request->independent_percend)) {
                    DB::rollBack();
                    return back()->with('error', '2 fan uchun Mustaqil ta\'lim foizini kiriting');
                }
            }
            $roundJoriy = isset($request->round_jn) ? 'ROUND' : null;
            $roundIndepend = isset($request->round_independent) ? 'ROUND' : null;
            $roundOraliq = isset($request->round_oraliq) ? 'ROUND' : null;
            $roundJoriy2 = isset($request->round_jn2) ? 'ROUND' : null;
            $roundIndepend2 = isset($request->round_independent2) ? 'ROUND' : null;
            $roundOraliq2 = isset($request->round_oraliq2) ? 'ROUND' : null;
            $roundOske = isset($request->round_oske) ? 'ROUND' : null;
            $roundExam = isset($request->round_exam) ? 'ROUND' : null;

            $vedomost = Vedomost::create(
                [
                    'user_id' => $request->user()->id,
                    'department_hemis_id' => $deportment->department_hemis_id,
                    'department_id' => $deportment->id,
                    'deportment_name' => $deportment->name,
                    'semester_name' => $semester->name,
                    'semester_code' => $semester->code,
                    'group_name' => $request->group_name,
                    'subject_name' => $request->subject_name,
                    "independent_percent" => $request->independent_percend,
                    "jb_percent" => $request->jn_percend,
                    "independent_percent_secend" => $request->independent_percend2 ?? 0,
                    "jb_percent_secend" => $request->jn_percend2 ?? 0,
                    "oski_percent" => $request->oske_percend ?? 0,
                    "test_percent" => $request->exam_percend ?? 0,
                    "type" => $request->type,
                    "shakl" => $request->shakl,
                    "oraliq_percent" => $request->oraliq_percent
                ]
            );
            $shakllar = config('app.shakllar');
            $shakl = $shakllar[$vedomost->shakl - 1];
            if ($vedomost->type == 1) {
                $allStudents = collect();
                $currentDate = Carbon::now()->toDateString();
                $disallowedCodes = config('app.training_type_code'); // array of ints
                foreach ($request->group_id as $idx => $groupHemisId) {
                    $group = Group::where('group_hemis_id', $groupHemisId)->first();
                    if (empty($group)) {
                        DB::rollBack();
                        return back()->with('error', 'Guruh tanlang');
                    }
                    $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                        ->where('semester_code', $semester->code)
                        ->where('subject_id', $request->subject_one_id[$idx] ?? 0)
                        ->first();
                    if (empty($subject)) {
                        DB::rollBack();
                        return back()->with('error', 'Fan tanlang');
                    }
                    // 3) create VedomostGroup
                    VedomostGroup::create([
                        'vedomost_id'           => $vedomost->id,
                        'group_hemis_id'        => $group->group_hemis_id,
                        'subject_hemis_id'      => $subject->curriculum_subject_hemis_id,
                        'subject_hemis_id_secend'=> 0,
                        'student_hemis_ids'     => json_encode([]),
                    ]);

                    // 4) load students + their relevant grades + relations
                    $students = Student::where('group_id', $group->group_hemis_id)
                        ->with(['grades' => function($q) use($subject, $vedomost, $currentDate) {
                            $q->where('subject_id', $subject->subject_id)
                              ->where('semester_code', $vedomost->semester_code)
                              ->whereDate('lesson_date', '<=', $currentDate)
                              ->with(['oski', 'examTest']);
                        }])
                        ->get();

                    // 5) transform each student into an object with all metrics
                    $transformed = $students->map(function($student) use($vedomost, $disallowedCodes) {
                        $grades = $student->grades;

                        // helper to avg by code
                        $avgByCode = function(int $code) use($grades) {
                            return $grades->where('training_type_code', $code)
                                ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                ->map(fn($day) => round($day->avg('grade')))
                                ->avg() ?: 0;
                        };

                        // jb: exclude disallowed codes
                        $jb =$this->studentGradeService->computeAverageGrade($grades->whereNotIn('training_type_code', $disallowedCodes), $vedomost->semester_code)['average']??0;
                        // $jb = $grades->whereNotIn('training_type_code', $disallowedCodes)
                        //     ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                        //     ->filter(fn($day) => $day->where('status','pending')->where('reason','absent')->count() < $day->count())
                        //     ->map(fn($day) => round($day->map(fn($g) => match(true) {
                        //         $g->status === 'retake' && in_array($g->reason, ['absent','teacher_victim','low_grade'])
                        //             => $g->retake_grade,
                        //         $g->status === 'pending' && $g->reason === 'absent'
                        //             => $g->grade,
                        //         default => $g->grade,
                        //     })->avg()))
                        //     ->avg() ?: 0;

                        // other metrics
                        $mt       = $avgByCode(99);
                        $on       = $avgByCode(100);
                        $oski     = $grades->where('training_type_code',101)
                                          ->filter(fn($g) => optional($g->oski)->shakl == $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $oski_old = $grades->where('training_type_code',101)
                                          ->filter(fn($g) => optional($g->oski)->shakl < $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $test     = $grades->where('training_type_code',102)
                                          ->filter(fn($g) => optional($g->examTest)->shakl == $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $test_old = $grades->where('training_type_code',102)
                                          ->filter(fn($g) => optional($g->examTest)->shakl < $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        return (object) [
                            'id'            => $student->id,
                            'full_name'     => $student->full_name,
                            'student_id_number'=> $student->student_id_number,
                            'hemis_id'      => $student->hemis_id,
                            'jb'            => $jb,
                            'mt'            => $mt,
                            'on'            => $on,
                            'oski'          => $oski,
                            'oski_old'      => $oski_old,
                            'test'          => $test,
                            'test_old'      => $test_old,
                        ];
                    });
                    $allStudents = $allStudents->merge($transformed);
                }
                $allStudents = $allStudents
                    ->groupBy('student_id_number')
                    ->map(fn($grp) => $grp->sortByDesc('jb')->first())
                    ->values();
                // dd($allStudents);
                $filePath = public_path('./vedemost/one_subject.xlsx');
                if (!file_exists($filePath)) {
                    DB::rollBack();
                    return back()->with('error', "Xatolik: Fayl topilmadi! Yo‘l: " . $filePath);
                }
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();

                $group = Group::whereIn("group_hemis_id", $request->group_id)->first();
                $sheet->setCellValue('A4', $deportment->name);
                $sheet->setCellValue('Y1', $shakl['name']);
                $sheet->setCellValue('C8', $group->specialty_name);
                $sheet->setCellValue('Q8', explode('-', $semester->name)[0]);
                $sheet->setCellValue('N8', explode('-', $semester->level_name)[0]);
                $sheet->setCellValue('W8', $vedomost->group_name);
                $sheet->setCellValue('C10', $vedomost->subject_name);
                $sheet->setCellValue('D13', $subject->total_acload);
                $sheet->setCellValue('G13', $subject->credit);
                $sheet->setCellValue('G13', $subject->credit);
                $sheet->setCellValue('D19', $vedomost->jb_percent);
                $sheet->setCellValue('G19', $vedomost->independent_percent);
                $sheet->setCellValue('J19', $vedomost->oraliq_percent);
                $sheet->setCellValue('M19', $vedomost->jb_percent + $vedomost->independent_percent + $vedomost->oraliq_percent);
                $sheet->setCellValue('P19', $vedomost->oski_percent);
                $sheet->setCellValue('S19', $vedomost->test_percent);
                $sheet->setCellValue('V19', $vedomost->jb_percent + $vedomost->independent_percent + $vedomost->oraliq_percent + $vedomost->oski_percent + $vedomost->test_percent);

                $maruza_teacher = DB::table('student_grades as s')
                    ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                    ->leftJoin('students as st', 'st.hemis_id', '=', 's.student_hemis_id')
                    ->select('t.full_name AS full_name')
                    ->where('s.subject_id', $subject->subject_id)
                    ->where('s.training_type_code', 11)
                    ->where('st.group_id', $group->group_hemis_id)
                    ->first();
                // $other_teachers = DB::table('student_grades as s')
                //     ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                //     ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                //     ->where('s.subject_id', $subject->subject_id)
                //     ->where('training_type_code', '!=', 11)
                //     ->whereIn('s.group_id', $group->group_hemis_id)
                //     ->groupBy('s.employee_id')
                //     ->get();
                $sheet->setCellValue('C11', $maruza_teacher?->full_name ?? "Topilmadi bazadan");

                $startRow = 20; // Boshlang‘ich qator
                // ❗ Eski qator stilini nusxalash
                $style = $sheet->getStyle('A' . ($startRow + 1) . ':Y' . ($startRow + 1));
                // $sheet->insertNewRowBefore($startRow + 1, count($students) - 2);
                $all_jb = 0;
                $all_mt = 0;
                $all_on = 0;
                $all_joriy = 0;
                $all_oski = 0;
                $all_test = 0;
                $a_count = 0;
                $b_count = 0;
                $c_count = 0;
                $f_count = 0;
                $k_count = 0;
                $fx_count = 0;
                // $delete_rows = [];
                $row = $startRow;
                $index = 0;

                foreach ($allStudents as $student) {

                    // if (($vedomost->oski_percent != 0 and $student->oski_old >= 60) or ($vedomost->test_percent != 0 and $student->test_old >= 60)) {
                    //     // $delete_rows[] = $row;
                    //     // $sheet->removeRow($row);
                    //     continue;
                    // }
                    if ($index > 1) {
                        $sheet->insertNewRowBefore($row + 1, 1);
                    }
                    // ❗ Stilni saqlash
                    $jb = round($student->jb);
                    $all_jb += $student->jb;
                    $mt = round($student->mt) ;
                    $all_mt += $student->mt;
                    if ($vedomost->oraliq_percent == 0) {
                        $oraliq = '';
                    } else {
                        $oraliq = round($student->on) ;
                        $all_on += $student->on;
                    }

                    $all = round($jb* $vedomost->jb_percent / 100 + $mt* $vedomost->independent_percent / 100 + floatval($oraliq ?? 0)* ($vedomost->oraliq_percent ?? 0) / 100);
                    $all_bal = $all / ($vedomost->jb_percent + $vedomost->independent_percent + ($vedomost->oraliq_percent ?? 0)) * 100;
                    $all_joriy += $all_bal;
                    if ($vedomost->oski_percent == 0) {
                        $oski = '';
                    } else {
                        $oski = round($student->oski );
                        $all_oski += $student->oski;
                    }
                    if ($vedomost->test_percent == 0) {
                        $test = '';
                    } else {
                        $test = round($student->test );
                        $all_test += $student->test;
                    }


                    $deadline = Deadline::where('level_code', $semester->level_code)->first();
                    $markingScore = $this->getMarkingScore($student->hemis_id);
                    $jnLimit = $markingScore->jn_active ? $markingScore->jn_limit : 0;
                    $mtLimit = $markingScore->mt_active ? $markingScore->mt_limit : 0;
                    $onLimit = $markingScore->on_active ? $markingScore->on_limit : 0;
                    $oskiLimit = $markingScore->oski_active ? $markingScore->oski_limit : 0;
                    $testLimit = $markingScore->test_active ? $markingScore->test_limit : 0;
                    $token = config('services.hemis.token');
                    $response = Http::withoutVerifying()->withToken($token)
                        ->get("https://student.ttatf.uz/rest/v1/data/attendance-list?limit=200&page=1&_group=" . $group->group_hemis_id . "&_subject=" . $subject->subject_id . "&_student=" . $student->hemis_id);
                    $qoldirgan = 0;
                    $ozlashtirish = 0;
                    if ($response->successful()) {
                        $data = $response->json()['data'];
                        foreach ($data['items'] as $item) {
                            $qoldirgan += $item['absent_off'];
                        }
                    }
                    $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                    if ($student->qoldiq > 25 or $student->jb < $jnLimit or $student->mt < $mtLimit or ($vedomost->oraliq_percent != 0 and $student->on < $onLimit)) {
                        $ozlashtirish = -2;
                    } elseif (
                        ($vedomost->oski_percent != 0 and !isset($student->oski)) or ($vedomost->test_percent != 0 and !isset($student->test))
                    ) {
                        $ozlashtirish = -1;
                    } elseif ($student->jb >= $jnLimit and $student->mt >= $mtLimit and ($vedomost->oraliq_percent == 0 || $student->on >= $onLimit) and ($vedomost->oski_percent == 0 || $student->oski >= $oskiLimit) and ($vedomost->test_percent == 0 || $student->test >= $testLimit)) {
                        $ozlashtirish = $all + floatval($test ?? 0) * $vedomost->test_percent / 100 + floatval($oski ?? 0) * $vedomost->oski_percent / 100;
                    }
                    $ozlashtirish = round($ozlashtirish);
                    // ❗ Yangi qatorlar qo‘shish
                    $sheet->duplicateStyle($style, 'A' . $row . ':Y' . $row);
                    if ($ozlashtirish == 0) {
                        // 🔶 Och sariq fon
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFFACD'] // Och sariq
                            ]
                        ]);
                    } elseif ($ozlashtirish == -1 or $ozlashtirish == -2) {
                        // 🔴 Och qizil fon
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFA07A'] // Och qizil
                            ]
                        ]);
                    } else {
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_NONE // Rangsiz qoldirish
                            ]
                        ]);
                    }
                    $sheet->setCellValue('A' . $row, $index + 1); // №
                    $sheet->setCellValue('B' . $row, $student->full_name);
                    $sheet->setCellValueExplicit('C' . $row, $student->student_id_number, DataType::TYPE_STRING);
                    $sheet->setCellValue('D' . $row, ($jb));
                    $sheet->setCellValue('E' . $row, round($jb * $vedomost->jb_percent / 100, $roundJoriy?0:2));
                    $sheet->setCellValue('G' . $row, ($mt));
                    $sheet->setCellValue('H' . $row, round($mt* $vedomost->independent_percent / 100, $roundIndepend?0:2));
                    $sheet->setCellValue('J' . $row, (floatval($oraliq ?? 0)));
                    $sheet->setCellValue('K' . $row, $oraliq != "" ? (round($oraliq* ($vedomost->oraliq_percent ?? 0) / 100, $roundOraliq?0:2)) : "");
                    $sheet->setCellValue('M' . $row, round($all));
                    $sheet->setCellValue('N' . $row, $all_bal != "" ? round($all_bal) : "");
                    $sheet->setCellValue('P' . $row, (floatval($oski ?? 0)));
                    $sheet->setCellValue('Q' . $row, $oski != "" ? round($oski* $vedomost->oski_percent / 100, $roundOske?0:2) : "");
                    $sheet->setCellValue('S' . $row, (floatval($test ?? 0)));
                    $sheet->setCellValue('T' . $row, $test != "" ? round($test* $vedomost->test_percent / 100, $roundExam?0:2) : "");
                    $sheet->setCellValue('V' . $row, $ozlashtirish != "" ? round($ozlashtirish) : "");
                    if ($ozlashtirish === "") {
                        $bahosi = "";
                    } elseif ($ozlashtirish >= 90 && $ozlashtirish <= 100) {
                        $bahosi = "A";
                    } elseif ($ozlashtirish >= 85) {
                        $bahosi = "B+";
                    } elseif ($ozlashtirish >= 71) {
                        $bahosi = "B";
                    } elseif ($ozlashtirish >= 60) {
                        $bahosi = "C";
                    } elseif ($ozlashtirish >= 0 && $ozlashtirish <= 60) {
                        $bahosi = "F";
                    } elseif ($ozlashtirish == -1) {
                        $bahosi = "";
                    } elseif ($ozlashtirish == -2) {
                        $bahosi = "";
                    } else {
                        $bahosi = "FX";
                    }
                    $sheet->setCellValue('W' . $row, $bahosi);
                    if ($ozlashtirish >= 90 && $ozlashtirish <= 100) {
                        $izoh_baho = "a’lo";
                        $a_count += 1;
                    } elseif ($ozlashtirish >= 71 && $ozlashtirish <= 89) {
                        $izoh_baho = "yaxshi";
                        $b_count += 1;
                    } elseif ($ozlashtirish >= 60 && $ozlashtirish <= 70) {
                        $c_count += 1;
                        $izoh_baho = "o‘rta";
                    } elseif ($ozlashtirish >= 0 && $ozlashtirish <= 59) {
                        $f_count += 1;
                        $izoh_baho = "qon-siz";
                    } elseif ($ozlashtirish == -1) {
                        $k_count += 1;
                        $izoh_baho = "kelmadi";
                    } elseif ($ozlashtirish == -2) {
                        $fx_count += 1;
                        $izoh_baho = "qo‘yilmadi";
                    } else {
                        $k_count += 1;
                        $izoh_baho = "kelmadi";
                    }
                    $sheet->setCellValue('Y' . $row, $izoh_baho);
                    $index++;
                    $row++;
                }
                // rsort($delete_rows);

                // foreach ($delete_rows as $row) {
                //     $sheet->removeRow($row);
                // }
                // $row += 1;
                $count_student = $index;
                $sheet->setCellValue('D' . $row, round(($all_jb * $vedomost->jb_percent / 100) / $count_student));
                $sheet->setCellValue('G' . $row, round(($all_mt* $vedomost->independent_percent / 100) / $count_student));
                $sheet->setCellValue('J' . $row, round(($all_on* ($vedomost->oraliq_percent ?? 0) / 100) / $count_student));
                $sheet->setCellValue('N' . $row, round($all_joriy / $count_student));
                $sheet->setCellValue('P' . $row, round(($all_oski* $vedomost->oski_percent / 100) / $count_student));
                $sheet->setCellValue('S' . $row, round(($all_test* $vedomost->test_percent / 100) / $count_student));

                $sheet->setCellValue('C' . $row + 7, $count_student);
                $sheet->setCellValue('F' . $row + 7, $a_count);
                $sheet->setCellValue('H' . $row + 7, round($a_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('F' . $row + 8, $b_count);
                $sheet->setCellValue('H' . $row + 8, round($b_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('N' . $row + 7, $c_count);
                $sheet->setCellValue('P' . $row + 7, round($c_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('N' . $row + 8, $f_count);
                $sheet->setCellValue('P' . $row + 8, round($f_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('V' . $row + 7, $k_count);
                $sheet->setCellValue('W' . $row + 7, round($k_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('V' . $row + 8, $fx_count);
                $sheet->setCellValue('W' . $row + 8, round($fx_count * 1000 / $count_student) / 10);
                // Excel faylini saqlash va yuklab berish
                $fileName = 'students_list_' . now()->format('Y-m-d_H-i-s') . '.xlsx'; // Vaqtga bog'liq nomlash
                $savePath = 'public/excel_files/' . $fileName; // Laravel storage ichida saqlanadigan joy


                // QR kodni yaratish va saqlash
                $apiUrl = "https://api.qrserver.com/v1/create-qr-code";
                $text = env('APP_URL') . "/storage/excel_files/" . $fileName;
                $fullUrl = "$apiUrl/?data=$text&size=500x500";
                $client = new \GuzzleHttp\Client();
                $qrcode_path = 'app/public/qrcodes/vedemost-' . time() . '.png';
                $response = $client->request('GET', $fullUrl, [
                    'verify' => false,
                    'sink' => storage_path($qrcode_path),
                ]);
                if ($response->getStatusCode() != 200) {
                    return 'Error: ' . $response->getStatusCode();
                }
                $file_path = storage_path($qrcode_path);
                $drawing = new Drawing();
                $drawing->setName('QR Code');
                $drawing->setDescription('Student QR Code');
                $drawing->setPath($file_path);
                $drawing->setHeight(100);
                $drawing->setCoordinates('Y' . $row + 2);
                $drawing->setWorksheet($sheet);
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                Storage::put($savePath, '');
                $writer->save(storage_path('app/' . $savePath));
                $vedomost->file_path = "storage/excel_files/" . $fileName;
                $vedomost->save();
                DB::commit();
                return back()->with('success', 'Vedemost muvaffaqiyatli yaratildi');
            } else {
                $allStudents = collect();
                $currentDate = Carbon::now()->toDateString();
                $disallowedCodes = config('app.training_type_code'); // array of ints
                foreach ($request->group_id as $idx => $groupHemisId) {
                    $group = Group::where('group_hemis_id', $groupHemisId)->first();
                    if (empty($group)) {
                        DB::rollBack();
                        return back()->with('error', 'Guruh tanlang');
                    }
                    $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                        ->where('semester_code', $semester->code)
                        ->where('subject_id', $request->subject_one_id[$idx] ?? 0)
                        ->first();
                    if (empty($subject)) {
                        DB::rollBack();
                        return back()->with('error', 'Fan tanlang');
                    }
                    $subject2 = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
                        ->where('semester_code', $semester->code)
                        ->where('subject_id', $request->subject_secend_id[$idx] ?? 0)
                        ->first();

                    if (empty($subject2)) {
                        DB::rollBack();
                        return back()->with('error', 'Ikkinchi fanni tanlang');
                    }
                    $subject_ids = [];
                    $subject_ids[] = $subject->subject_id;
                    $subject_ids[] = $subject2->subject_id;
                    VedomostGroup::create([
                        'vedomost_id'           => $vedomost->id,
                        'group_hemis_id'        => $group->group_hemis_id,
                        'subject_hemis_id'      => $subject->curriculum_subject_hemis_id,
                        'subject_hemis_id_secend'=> 0,
                        'student_hemis_ids'     => json_encode([]),
                    ]);

                    // 4) load students + their relevant grades + relations
                    $students = Student::where('group_id', $group->group_hemis_id)
                        ->with(['grades' => function($q) use($vedomost, $currentDate) {
                            $q->where('semester_code', $vedomost->semester_code)
                              ->whereDate('lesson_date', '<=', $currentDate)
                              ->with(['oski', 'examTest']);
                        }])
                        ->get();

                    // 5) transform each student into an object with all metrics
                    $transformed = $students->map(function($student) use($vedomost, $disallowedCodes, $subject, $subject2) {
                        $grades = $student->grades;

                        // helper to avg by code
                        $avgByCode = function(int $code, $subject) use($grades) {
                            return $grades->where('training_type_code', $code)
                                ->where('subject_id', $subject->subject_id)
                                ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                ->map(fn($day) => round($day->avg('grade')))
                                ->avg() ?: 0;
                        };
                        $avgJbBySubject = function($subject) use($grades, $disallowedCodes, $vedomost) {

                            return $this->studentGradeService->computeAverageGrade($grades->whereNotIn('training_type_code', $disallowedCodes)->where('subject_id', $subject->subject_id), $vedomost->semester_code)['average']??0;
                            // return $grades->whereNotIn('training_type_code', $disallowedCodes)
                            //     ->where('subject_id', $subject->subject_id)
                            //     ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                            //     ->filter(fn($day) => $day->where('status','pending')->where('reason','absent')->count() < $day->count())
                            //     ->map(fn($day) => round($day->map(fn($g) => match(true) {
                            //         $g->status === 'retake' && in_array($g->reason, ['absent','teacher_victim','low_grade'])
                            //             => $g->retake_grade,
                            //         $g->status === 'pending' && $g->reason === 'absent'
                            //             => $g->grade,
                            //         default => $g->grade,
                            //     })->avg()))
                            //     ->avg() ?: 0;
                        };
                        $jb = $avgJbBySubject($subject);
                        $jb_secend = $avgJbBySubject($subject2);
                        $mt       = $avgByCode(99, $subject);
                        $mt_secend= $avgByCode(99, $subject2);
                        $on       = $avgByCode(100, $subject);
                        $oski     = $grades->where('training_type_code',101)
                                          ->where('subject_id', $subject->subject_id)
                                          ->filter(fn($g) => optional($g->oski)->shakl == $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $oski_old = $grades->where('training_type_code',101)
                                          ->where('subject_id', $subject->subject_id)
                                          ->filter(fn($g) => optional($g->oski)->shakl < $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $test     = $grades->where('training_type_code',102)
                                          ->where('subject_id', $subject->subject_id)
                                          ->filter(fn($g) => optional($g->examTest)->shakl == $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        $test_old = $grades->where('training_type_code',102)
                                          ->where('subject_id', $subject->subject_id)
                                          ->filter(fn($g) => optional($g->examTest)->shakl < $vedomost->shakl)
                                          ->groupBy(fn($g) => Carbon::parse($g->lesson_date)->toDateString())
                                          ->map(fn($day) => round($day->avg('grade')))
                                          ->avg() ?: 0;
                        return (object) [
                            'id'            => $student->id,
                            'full_name'     => $student->full_name,
                            'student_id_number'=> $student->student_id_number,
                            'hemis_id'      => $student->hemis_id,
                            'jb'            => $jb,
                            'jb_secend'     => $jb_secend,
                            'mt'            => $mt,
                            'mt_secend'     => $mt_secend,
                            'on'            => $on,
                            'oski'          => $oski,
                            'oski_old'      => $oski_old,
                            'test'          => $test,
                            'test_old'      => $test_old,
                        ];
                    });
                    $allStudents = $allStudents->merge($transformed);
                }
                $allStudents = $allStudents
                    ->groupBy('student_id_number')
                    ->map(fn($grp) => $grp->sortByDesc('jb')->first())
                    ->values();
                $filePath = public_path('./vedemost/secend_subject.xlsx');
                if (!file_exists($filePath)) {
                    DB::rollBack();
                    return back()->with('error', "Xatolik: Fayl topilmadi! Yo‘l: " . $filePath);
                }
                $spreadsheet = IOFactory::load($filePath);
                $sheet = $spreadsheet->getActiveSheet();

                $group = Group::whereIn("group_hemis_id", $request->group_id)->first();
                $sheet->setCellValue('A4', $deportment->name);
                $sheet->setCellValue('C8', $group->specialty_name);
                $sheet->setCellValue('T8', explode('-', $semester->name)[0]);
                $sheet->setCellValue('Q8', explode('-', $semester->level_name)[0]);
                $sheet->setCellValue('Z8', $vedomost->group_name);
                $sheet->setCellValue('C10', $vedomost->subject_name);
                $sheet->setCellValue('D13', $subject->total_acload);
                $sheet->setCellValue('G13', $subject->credit);
                $sheet->setCellValue('D19', $vedomost->jb_percent);
                $sheet->setCellValue('G19', $vedomost->independent_percent);
                $sheet->setCellValue('J19', $vedomost->jb_percent_secend);
                $sheet->setCellValue('M19', $vedomost->independent_percent_secend);
                $sheet->setCellValue('P19', $vedomost->jb_percent + $vedomost->independent_percent + $vedomost->jb_percent_secend + $vedomost->independent_percent_secend);
                $sheet->setCellValue('S19', $vedomost->oski_percent);
                $sheet->setCellValue('V19', $vedomost->test_percent);
                $sheet->setCellValue('Y19', $vedomost->oski_percent + $vedomost->test_percent + $vedomost->jb_percent + $vedomost->independent_percent + $vedomost->jb_percent_secend + $vedomost->independent_percent_secend);

                $maruza_teacher = DB::table('student_grades as s')
                    ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                    ->leftJoin('students as st', 'st.hemis_id', '=', 's.student_hemis_id')
                    ->select('t.full_name AS full_name')
                    ->where('s.subject_id', $subject->subject_id)
                    ->where('s.training_type_code', 11)
                    ->where('st.group_id', $group->group_hemis_id)
                    ->first();
                // $other_teachers = DB::table('student_grades as s')
                //     ->leftJoin('teachers as t', 't.hemis_id', '=', 's.employee_id')
                //     ->select(DB::raw('GROUP_CONCAT(DISTINCT t.full_name SEPARATOR ", ") AS full_names'))
                //     ->where('s.subject_id', $subject->subject_id)
                //     ->where('training_type_code', '!=', 11)
                //     ->whereIn('s.group_id', $group->group_hemis_id)
                //     ->groupBy('s.employee_id')
                //     ->get();
                $sheet->setCellValue('C11', $maruza_teacher?->full_name ?? "Topilmadi bazadan");

                // 2. Talabalar ro'yxatini joylashtirish

                // $this->telegram_error("students " . json_encode($students));

                // $students = Student::where('group_id', $request->group_id)->get();
                $startRow = 20; // Boshlang‘ich qator
                // ❗ Eski qator stilini nusxalash
                $style = $sheet->getStyle('A' . ($startRow + 1) . ':AB' . ($startRow + 1));
                // ❗ Yangi qatorlar qo‘shish
                // $sheet->insertNewRowBefore($startRow + 1, count($students) - 2);
                $all_jb = 0;
                $all_mt = 0;
                $all_jb2 = 0;
                $all_mt2 = 0;
                $all_joriy = 0;
                $all_oski = 0;
                $all_test = 0;
                $a_count = 0;
                $b_count = 0;
                $c_count = 0;
                $f_count = 0;
                $k_count = 0;
                $fx_count = 0;
                $row = $startRow;

                $index = 0;
                foreach ($allStudents as $student) {

                    // if (($vedomost->oski_percent != 0 && $student->oski_old >= 60) || ($vedomost->test_percent != 0 && $student->test_old >= 60)) {
                    //     continue;
                    // }
                    if ($index > 1) {
                        $sheet->insertNewRowBefore($row + 1, 1);
                    }

                    // ❗ Stilni saqlash
                    $jb = round($student->jb) ;
                    $all_jb += $student->jb;
                    $mt = round($student->mt);
                    $all_mt += $student->mt;
                    // if ($vedomost->oraliq_percent == 0) {
                    //     $oraliq = '';
                    // } else {
                    //     $oraliq = $student->on * ($vedomost->oraliq_percent ?? 0) / 100;
                    //     $all_on += $student->on;
                    // }
                    $jb2 = round($student->jb_secend);
                    $all_jb2 += $student->jb_secend;
                    $mt2 = round($student->mt_secend);
                    $all_mt2 += $student->mt_secend;

                    $all = round($jb* $vedomost->jb_percent / 100 + $mt * $vedomost->independent_percent / 100 + $jb2 * $vedomost->jb_percent_secend / 100 + $mt2 * $vedomost->independent_percent_secend / 100);
                    $all_bal = $all / ($vedomost->jb_percent + $vedomost->independent_percent + $vedomost->jb_percent_secend + $vedomost->independent_percent_secend) * 100;
                    $all_joriy += $all_bal;
                    if ($vedomost->oski_percent == 0) {
                        $oski = '';
                    } else {
                        $oski = round($student->oski);
                        $all_oski += $student->oski;
                    }
                    if ($vedomost->test_percent == 0) {
                        $test = '';
                    } else {
                        $test = round($student->test);
                        $all_test += $student->test;
                    }


                    $deadline = Deadline::where('level_code', $semester->level_code)->first();
                    $markingScore = $this->getMarkingScore($student->hemis_id);
                    $jnLimit = $markingScore->jn_active ? $markingScore->jn_limit : 0;
                    $mtLimit = $markingScore->mt_active ? $markingScore->mt_limit : 0;
                    $onLimit = $markingScore->on_active ? $markingScore->on_limit : 0;
                    $oskiLimit = $markingScore->oski_active ? $markingScore->oski_limit : 0;
                    $testLimit = $markingScore->test_active ? $markingScore->test_limit : 0;
                    $token = config('services.hemis.token');
                    $response = Http::withoutVerifying()->withToken($token)
                        ->get("https://student.ttatf.uz/rest/v1/data/attendance-list?limit=200&page=1&_group=" . $group->group_hemis_id . "&_subject=" . $subject->subject_id . "&_student=" . $student->hemis_id);
                    $qoldirgan = 0;
                    $ozlashtirish = 0;
                    if ($response->successful()) {
                        $data = $response->json()['data'];
                        foreach ($data['items'] as $item) {
                            $qoldirgan += $item['absent_off'];
                        }
                    }
                    $student->qoldiq = round($qoldirgan * 100 / $subject->total_acload, 2);
                    if ($student->qoldiq > 25 || $student->jb < $jnLimit || $student->mt < $mtLimit || ($vedomost->oraliq_percent != 0 && $student->on < $onLimit) || $student->jb_secend < $jnLimit || $student->mt_secend < $mtLimit) {
                        $ozlashtirish = -2;
                    } elseif (
                        ($vedomost->oski_percent != 0 && !isset($student->oski)) || ($vedomost->test_percent != 0 && !isset($student->test))
                    ) {
                        $ozlashtirish = -1;
                    } elseif ($student->jb >= $jnLimit && $student->mt >= $mtLimit && ($vedomost->oraliq_percent == 0 || $student->on >= $onLimit) && ($vedomost->oski_percent == 0 || $student->oski >= $oskiLimit) and ($vedomost->test_percent == 0 || $student->test >= $testLimit)) {
                        $ozlashtirish = $all + floatval($test ?? 0) * $vedomost->test_percent / 100 + floatval($oski ?? 0) * $vedomost->oski_percent / 100;
                    }
                    $ozlashtirish = round($ozlashtirish);
                    $sheet->duplicateStyle($style, 'A' . $row . ':Y' . $row);
                    if ($ozlashtirish == 0) {
                        // 🔶 Och sariq fon
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFFACD'] // Och sariq
                            ]
                        ]);
                    } elseif ($ozlashtirish == -1 || $ozlashtirish == -2) {
                        // 🔴 Och qizil fon
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_SOLID,
                                'startColor' => ['rgb' => 'FFA07A'] // Och qizil
                            ]
                        ]);
                    } else {
                        $sheet->getStyle('A' . $row . ':Y' . $row)->applyFromArray([
                            'fill' => [
                                'fillType' => Fill::FILL_NONE // Rangsiz qoldirish
                            ]
                        ]);
                    }
                    $sheet->setCellValue('A' . $row, $index + 1); // №
                    $sheet->setCellValue('B' . $row, $student->full_name);
                    $sheet->setCellValueExplicit('C' . $row, $student->student_id_number, DataType::TYPE_STRING);
                    $sheet->setCellValue('D' . $row, ($jb));
                    $sheet->setCellValue('E' . $row, round($jb* $vedomost->jb_percent / 100, $roundJoriy?0:2));
                    $sheet->setCellValue('G' . $row, ($mt));
                    $sheet->setCellValue('H' . $row, round($mt * $vedomost->independent_percent / 100, $roundIndepend?0:2));
                    $sheet->setCellValue('J' . $row, ($jb2));
                    $sheet->setCellValue('K' . $row, round($jb2 * $vedomost->jb_percent_secend / 100, $roundJoriy2?0:2));
                    $sheet->setCellValue('M' . $row, ($mt2));
                    $sheet->setCellValue('N' . $row, round($mt2 * $vedomost->independent_percent_secend / 100, $roundIndepend2?0:2));
                    $sheet->setCellValue('P' . $row, round($all));
                    $sheet->setCellValue('Q' . $row, $all_bal != "" ? round($all_bal) : "");
                    $sheet->setCellValue('S' . $row, (floatval($oski ?? 0)));
                    $sheet->setCellValue('T' . $row, $oski != "" ? round($oski * $vedomost->oski_percent / 100, $roundOske?0:2) : "");
                    $sheet->setCellValue('V' . $row, (floatval($test ?? 0)));
                    $sheet->setCellValue('W' . $row, $test != "" ? round($test * $vedomost->test_percent / 100, $roundExam?0:2) : "");
                    $sheet->setCellValue('Y' . $row, $ozlashtirish != "" ? round($ozlashtirish) : "");
                    if ($ozlashtirish === "") {
                        $bahosi = "";
                    } elseif ($ozlashtirish >= 90 && $ozlashtirish <= 100) {
                        $bahosi = "A";
                    } elseif ($ozlashtirish >= 85) {
                        $bahosi = "B+";
                    } elseif ($ozlashtirish >= 71) {
                        $bahosi = "B";
                    } elseif ($ozlashtirish >= 60) {
                        $bahosi = "C";
                    } elseif ($ozlashtirish >= 0 && $ozlashtirish <= 60) {
                        $bahosi = "F";
                    } elseif ($ozlashtirish == -1) {
                        $bahosi = "";
                    } elseif ($ozlashtirish == -2) {
                        $bahosi = "";
                    } else {
                        $bahosi = "FX";
                    }
                    $sheet->setCellValue('Z' . $row, $bahosi);
                    if ($ozlashtirish >= 90 && $ozlashtirish <= 100) {
                        $izoh_baho = "a’lo";
                        $a_count += 1;
                    } elseif ($ozlashtirish >= 71 && $ozlashtirish <= 89) {
                        $izoh_baho = "yaxshi";
                        $b_count += 1;
                    } elseif ($ozlashtirish >= 60 && $ozlashtirish <= 70) {
                        $c_count += 1;
                        $izoh_baho = "o‘rta";
                    } elseif ($ozlashtirish >= 0 && $ozlashtirish <= 59) {
                        $f_count += 1;
                        $izoh_baho = "qon-siz";
                    } elseif ($ozlashtirish == -1) {
                        $k_count += 1;
                        $izoh_baho = "kelmadi";
                    } elseif ($ozlashtirish == -2) {
                        $fx_count += 1;
                        $izoh_baho = "qo‘yilmadi";
                    } else {
                        $k_count += 1;
                        $izoh_baho = "kelmadi";
                    }
                    $sheet->setCellValue('AB' . $row, $izoh_baho);
                    $index++;
                    $row++;
                }
                $count_student = $index;
                $sheet->setCellValue('D' . $row, round(($all_jb* $vedomost->jb_percent / 100) / $count_student));
                $sheet->setCellValue('G' . $row, round(($all_mt * $vedomost->independent_percent / 100) / $count_student));
                $sheet->setCellValue('J' . $row, round(($all_jb2 * $vedomost->jb_percent_secend / 100) / $count_student));
                $sheet->setCellValue('M' . $row, round(($all_mt2 * $vedomost->independent_percent_secend / 100) / $count_student));
                // $sheet->setCellValue('J' . $row, round($all_on / $count_student));
                $sheet->setCellValue('Q' . $row, round($all_joriy / $count_student));
                $sheet->setCellValue('S' . $row, round(($all_oski * $vedomost->oski_percent / 100) / $count_student));
                $sheet->setCellValue('V' . $row, round($all_test / $count_student));

                $sheet->setCellValue('C' . $row + 7, $count_student);
                $sheet->setCellValue('F' . $row + 7, $a_count);
                $sheet->setCellValue('K' . $row + 7, round($a_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('F' . $row + 8, $b_count);
                $sheet->setCellValue('K' . $row + 8, round($b_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('Q' . $row + 7, $c_count);
                $sheet->setCellValue('S' . $row + 7, round($c_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('Q' . $row + 8, $f_count);
                $sheet->setCellValue('S' . $row + 8, round($f_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('Y' . $row + 7, $k_count);
                $sheet->setCellValue('Z' . $row + 7, round($k_count * 1000 / $count_student) / 10);
                $sheet->setCellValue('Y' . $row + 8, $fx_count);
                $sheet->setCellValue('Z' . $row + 8, round($fx_count * 1000 / $count_student) / 10);
                // Excel faylini saqlash va yuklab berish
                $fileName = 'students_list_' . now()->format('Y-m-d_H-i-s') . '.xlsx'; // Vaqtga bog'liq nomlash
                $savePath = 'public/excel_files/' . $fileName; // Laravel storage ichida saqlanadigan joy

                // QR kodni yaratish va saqlash
                $apiUrl = "https://api.qrserver.com/v1/create-qr-code";
                $text = env('APP_URL') . "/storage/excel_files/" . $fileName;
                $fullUrl = "$apiUrl/?data=$text&size=500x500";
                $client = new \GuzzleHttp\Client();
                $qrcode_path = 'app/public/qrcodes/vedemost-' . time() . '.png';
                $response = $client->request('GET', $fullUrl, [
                    'verify' => false,
                    'sink' => storage_path($qrcode_path),
                ]);
                if ($response->getStatusCode() != 200) {
                    return 'Error: ' . $response->getStatusCode();
                }
                $file_path = storage_path($qrcode_path);
                $drawing = new Drawing();
                $drawing->setName('QR Code');
                $drawing->setDescription('Student QR Code');
                $drawing->setPath($file_path);
                $drawing->setHeight(100);
                $drawing->setCoordinates('AB' . $row + 2);
                $drawing->setWorksheet($sheet);
                $writer = IOFactory::createWriter($spreadsheet, 'Xlsx');
                Storage::put($savePath, '');
                $writer->save(storage_path('app/' . $savePath));
                $vedomost->file_path = "storage/excel_files/" . $fileName;
                $vedomost->save();
                DB::commit();
                return back()->with('success', 'Vedemost muvaffaqiyatli yaratildi');
            }
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            DB::rollBack();
            return back()->with('error', 'Ma\'lumot topilmadi');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage() . " " . $e->getLine());
        }
    }
    private function getMarkingScore($studentHemisId)
    {
        static $cache = [];
        if (isset($cache[$studentHemisId])) {
            return $cache[$studentHemisId];
        }

        $student = Student::where('hemis_id', $studentHemisId)->first();
        $markingSystemCode = optional(optional($student)->curriculum)->marking_system_code;

        $score = $markingSystemCode
            ? MarkingSystemScore::where('marking_system_code', $markingSystemCode)->first()
            : null;

        if (!$score) {
            $score = (object) [
                'jn_limit' => 60, 'jn_active' => true,
                'mt_limit' => 60, 'mt_active' => true,
                'on_limit' => 60, 'on_active' => false,
                'oski_limit' => 60, 'oski_active' => true,
                'test_limit' => 60, 'test_active' => true,
                'total_limit' => 60, 'total_active' => true,
            ];
        }

        $cache[$studentHemisId] = $score;
        return $score;
    }

    function delete(Request $request, $id)
    {
        try {
            DB::beginTransaction();

            $independent = Vedomost::findOrFail($id);


            if ($independent->file_path) {
                Storage::disk('public')->delete($independent->file_path);
            }

            $independent->delete();

            DB::commit();
            return back()->with('success', 'Vedomost muvaffaqiyatli o\'chirildi');
        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Xatolik yuz berdi: ' . $e->getMessage());
        }
    }
}
