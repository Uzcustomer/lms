<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\CurriculumSubject;
use App\Models\Deadline;
use App\Models\Group;
use App\Models\Semester;
use App\Imports\QuizResultImport;
use App\Exports\QuizResultExport;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class QuizResultController extends Controller
{
    /**
     * Joriy guard bo'yicha route prefiksini aniqlash (admin yoki teacher).
     */
    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * Diagnostika sahifasi — yangi dizayn bilan.
     */
    public function diagnostikaPage(Request $request)
    {
        $routePrefix = $this->routePrefix();

        return view('admin.diagnostika.index', compact('routePrefix'));
    }

    /**
     * Sistemaga yuklangan natijalar sahifasi.
     */
    public function saqlanganHisobotPage(Request $request)
    {
        $routePrefix = $this->routePrefix();

        return view('admin.saqlangan-hisobot.index', compact('routePrefix'));
    }

    /**
     * Sistemaga yuklanmagan natijalar sahifasi.
     */
    public function yuklanmaganNatijalarPage(Request $request)
    {
        $routePrefix = $this->routePrefix();

        return view('admin.yuklanmagan-natijalar.index', compact('routePrefix'));
    }

    /**
     * Sistemaga yuklanmagan natijalar uchun AJAX data endpoint.
     * hemis_quiz_results dan student_grades ga yuklanmaganlarni qaytaradi.
     */
    public function yuklanmaganNatijalar(Request $request)
    {
        try {
            $query = HemisQuizResult::where('is_active', 1)
                ->whereNotExists(function ($q) {
                    $q->select(\DB::raw(1))
                      ->from('student_grades')
                      ->whereColumn('student_grades.quiz_result_id', 'hemis_quiz_results.id')
                      ->where('student_grades.reason', 'quiz_result');
                });

            // Joriy semestr filtri
            if ($request->get('current_semester', '0') == '1') {
                $currentSemesterCodes = Semester::where('current', true)
                    ->pluck('code')
                    ->unique()
                    ->toArray();

                if (!empty($currentSemesterCodes)) {
                    $query->where(function ($q) use ($currentSemesterCodes) {
                        $q->whereIn('student_id', function ($sub) use ($currentSemesterCodes) {
                            $sub->select('hemis_id')
                                ->from('students')
                                ->whereIn('semester_code', $currentSemesterCodes);
                        })->orWhereIn('student_id', function ($sub) use ($currentSemesterCodes) {
                            $sub->select('student_id_number')
                                ->from('students')
                                ->whereIn('semester_code', $currentSemesterCodes)
                                ->whereNotNull('student_id_number');
                        });
                    });
                }
            }

            // Sana filtrlari
            if ($request->filled('date_from')) {
                $query->whereDate('date_finish', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('date_finish', '<=', $request->date_to);
            }

            $results = $query->orderByDesc('date_finish')
                ->limit(10000)
                ->get();

            $data = $results->map(function ($item, $i) {
                $dateStart = '';
                $dateFinish = '';
                try {
                    if ($item->date_start) {
                        $dateStart = $item->date_start->format('d.m.Y H:i');
                    }
                } catch (\Exception $e) {
                    $dateStart = (string) $item->getRawOriginal('date_start');
                }
                try {
                    if ($item->date_finish) {
                        $dateFinish = $item->date_finish->format('d.m.Y H:i');
                    }
                } catch (\Exception $e) {
                    $dateFinish = (string) $item->getRawOriginal('date_finish');
                }

                return [
                    'id'           => $item->id,
                    'row_num'      => $i + 1,
                    'attempt_id'   => $item->attempt_id,
                    'student_id'   => $item->student_id,
                    'student_name' => $item->student_name,
                    'faculty'      => $item->faculty,
                    'direction'    => $item->direction,
                    'semester'     => $item->semester,
                    'fan_id'       => $item->fan_id,
                    'fan_name'     => $item->fan_name,
                    'quiz_type'    => $item->quiz_type,
                    'attempt_name' => $item->attempt_name,
                    'shakl'        => $item->shakl,
                    'grade'        => $item->grade,
                    'date_start'   => $dateStart,
                    'date_finish'  => $dateFinish,
                ];
            });

            return response()->json([
                'data' => $data->values(),
                'total' => $data->count(),
            ]);
        } catch (\Throwable $e) {
            Log::error('yuklanmaganNatijalar xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Diagnostika sahifasi uchun AJAX data endpoint.
     */
    public function diagnostikaData(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('faculty')) {
            $query->where('faculty', $request->faculty);
        }
        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }
        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }
        if ($request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $request->fan_name . '%');
        }
        if ($request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $request->student_name . '%');
        }
        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }
        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        // Excel export
        if ($request->input('export') === 'excel') {
            return (new QuizResultExport($request))->export();
        }

        $perPage = $request->input('per_page', 50);
        $paginated = $query->orderByDesc('date_finish')->paginate($perPage);

        $data = collect($paginated->items())->map(function ($item, $i) use ($paginated) {
            return [
                'id' => $item->id,
                'row_num' => $paginated->firstItem() + $i,
                'student_id' => $item->student_id,
                'student_name' => $item->student_name,
                'faculty' => $item->faculty,
                'direction' => $item->direction,
                'semester' => $item->semester,
                'fan_name' => $item->fan_name,
                'quiz_type' => $item->quiz_type,
                'shakl' => $item->shakl,
                'grade' => $item->grade,
                'date' => $item->date_finish ? $item->date_finish->format('d.m.Y') : '',
            ];
        });

        return response()->json([
            'data' => $data,
            'total' => $paginated->total(),
            'current_page' => $paginated->currentPage(),
            'last_page' => $paginated->lastPage(),
            'per_page' => $paginated->perPage(),
        ]);
    }

    /**
     * Tartibga solish — natijalarni jurnal shaklida ko'rsatish.
     * FISH, fakultet, yo'nalish, kurs, semestr, guruh students jadvalidan olinadi.
     * Quiz turi bo'yicha YN turi ustunida Test yoki OSKI deb ko'rsatiladi.
     * Barcha urinishlar ko'rsatiladi.
     */
    public function tartibgaSol(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        $results = $query->orderBy('student_id')->orderBy('fan_id')->orderBy('date_finish')->get();

        // Barcha student_id larni yig'ish va bulk query
        $studentIds = $results->pluck('student_id')->unique()->values()->toArray();

        $students = Student::where(function ($q) use ($studentIds) {
            $q->whereIn('hemis_id', $studentIds)
              ->orWhereIn('student_id_number', $studentIds);
        })->get();

        // Lookup yaratish (hemis_id va student_id_number bo'yicha)
        $studentLookup = [];
        foreach ($students as $student) {
            $studentLookup[$student->hemis_id] = $student;
            if ($student->student_id_number) {
                $studentLookup[$student->student_id_number] = $student;
            }
        }

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        // ====== XULOSA UCHUN MA'LUMOTLAR TAYYORLASH ======

        // 1) Allaqachon yuklangan natijalarni aniqlash (quiz_result_id orqali)
        $allResultIds = $results->pluck('id')->toArray();
        $uploadedResultIds = [];
        if (!empty($allResultIds)) {
            $uploadedResultIds = StudentGrade::where('reason', 'quiz_result')
                ->whereIn('quiz_result_id', $allResultIds)
                ->pluck('quiz_result_id')
                ->toArray();
        }
        $uploadedResultIds = array_flip($uploadedResultIds);

        // 2) Dublikatlarni aniqlash: student_id + fan_id + yn_turi + shakl
        $duplicateMap = []; // key => [result_ids]
        foreach ($results as $result) {
            $ynTuri = '-';
            if (in_array($result->quiz_type, $testTypes)) $ynTuri = 'T';
            elseif (in_array($result->quiz_type, $oskiTypes)) $ynTuri = 'O';
            if ($ynTuri === '-') continue;

            $key = $result->student_id . '|' . $result->fan_id . '|' . $ynTuri . '|' . $result->shakl;
            $duplicateMap[$key][] = $result->id;
        }

        // 3) Student lar uchun JN/MT/OSKI baholarini olish (bulk)
        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        $fanIds = $results->pluck('fan_id')->unique()->values()->toArray();

        // JN baholar (training_type_code NOT IN config list)
        $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102]);
        $jnGrades = [];
        if (!empty($studentHemisIds) && !empty($fanIds)) {
            $jnRows = StudentGrade::whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('subject_id', $fanIds)
                ->whereNotIn('training_type_code', $excludedCodes)
                ->whereNotNull('grade')
                ->get(['student_hemis_id', 'subject_id', 'grade', 'lesson_date']);

            foreach ($jnRows as $row) {
                $k = $row->student_hemis_id . '|' . $row->subject_id;
                $jnGrades[$k][] = ['grade' => $row->grade, 'date' => $row->lesson_date];
            }
        }

        // MT baholar (training_type_code = 99)
        $mtGrades = [];
        if (!empty($studentHemisIds) && !empty($fanIds)) {
            $mtRows = StudentGrade::whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('subject_id', $fanIds)
                ->where('training_type_code', 99)
                ->whereNotNull('grade')
                ->get(['student_hemis_id', 'subject_id', 'grade']);

            foreach ($mtRows as $row) {
                $k = $row->student_hemis_id . '|' . $row->subject_id;
                $mtGrades[$k][] = $row->grade;
            }
        }

        // OSKI baholar (training_type_code = 101) — YN test uchun kerak
        $oskiGrades = [];
        if (!empty($studentHemisIds) && !empty($fanIds)) {
            $oskiRows = StudentGrade::whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('subject_id', $fanIds)
                ->where('training_type_code', 101)
                ->whereNotNull('grade')
                ->get(['student_hemis_id', 'subject_id', 'grade']);

            foreach ($oskiRows as $row) {
                $k = $row->student_hemis_id . '|' . $row->subject_id;
                $oskiGrades[$k][] = $row->grade;
            }
        }

        // 4) CurriculumSubject — fanlar jadvalda bormi tekshirish
        $groupIds = $students->pluck('group_id')->unique()->toArray();
        $groups = Group::whereIn('group_hemis_id', $groupIds)->get()->keyBy('group_hemis_id');
        $curriculumHemisIds = $groups->pluck('curriculum_hemis_id')->unique()->toArray();

        $curriculumSubjects = [];
        if (!empty($curriculumHemisIds) && !empty($fanIds)) {
            $csRows = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumHemisIds)
                ->whereIn('subject_id', $fanIds)
                ->get(['curricula_hemis_id', 'subject_id', 'semester_code']);

            foreach ($csRows as $cs) {
                $curriculumSubjects[$cs->curricula_hemis_id . '|' . $cs->subject_id] = $cs->semester_code;
            }
        }

        // 5) Deadline jadval (level_code bo'yicha)
        $deadlines = Deadline::all()->keyBy('level_code');

        // ====== DATA TAYYORLASH ======
        $data = [];
        $rowNum = 0;

        foreach ($results as $result) {
            $student = $studentLookup[$result->student_id] ?? null;

            // Semestrni Moodle formatidan aniqlash: "3-SEM" -> 3, "5-sem" -> 5
            $semNum = null;
            $semLabel = $result->semester ?: ($student ? $student->semester_name : '');
            if ($semLabel && preg_match('/(\d+)/', $semLabel, $m)) {
                $semNum = (int) $m[1];
            }

            // Kursni semestr raqamidan hisoblash
            $kurs = $semNum ? (int) ceil($semNum / 2) : null;

            // YN turi aniqlash
            $ynTuri = '-';
            if (in_array($result->quiz_type, $testTypes)) {
                $ynTuri = 'Test';
            } elseif (in_array($result->quiz_type, $oskiTypes)) {
                $ynTuri = 'OSKI';
            }

            // ====== XULOSA HISOBLASH ======
            $xulosa = $this->calculateXulosa(
                $result, $student, $ynTuri,
                $uploadedResultIds, $duplicateMap,
                $jnGrades, $mtGrades, $oskiGrades,
                $curriculumSubjects, $groups, $deadlines,
                $testTypes, $oskiTypes
            );

            // Journal uchun group_db_id va semester_code aniqlash
            $groupDbId = null;
            $semesterCode = null;
            if ($student) {
                $groupModel = $groups[$student->group_id] ?? null;
                if ($groupModel) {
                    $groupDbId = $groupModel->id;
                    $csKey = $groupModel->curriculum_hemis_id . '|' . $result->fan_id;
                    $semesterCode = $curriculumSubjects[$csKey] ?? null;
                }
            }

            $rowNum++;
            $data[] = [
                'id' => $result->id,
                'row_num' => $rowNum,
                'student_id' => $result->student_id,
                'full_name' => $student ? $student->full_name : $result->student_name,
                'faculty' => $student ? $student->department_name : '-',
                'direction' => $student ? $student->specialty_name : '-',
                'kurs' => $kurs ? $kurs . '-kurs' : '-',
                'semester' => $semNum ? $semNum . '-sem' : ($semLabel ?: '-'),
                'group' => $student ? $student->group_name : '-',
                'fan_name' => $result->fan_name,
                'yn_turi' => $ynTuri,
                'shakl' => $result->shakl,
                'grade' => $result->grade,
                'date' => $result->date_finish ? $result->date_finish->format('d.m.Y') : '',
                'xulosa' => $xulosa['text'],
                'xulosa_code' => $xulosa['code'],
                'jn_avg' => $xulosa['jn_avg'],
                'mt_avg' => $xulosa['mt_avg'],
                'oski_avg' => $xulosa['oski_avg'],
                'group_db_id' => $groupDbId,
                'fan_id' => $result->fan_id,
                'semester_code' => $semesterCode,
            ];
        }

        return response()->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }

    /**
     * Har bir natija uchun xulosa hisoblash.
     */
    private function calculateXulosa(
        $result, $student, $ynTuri,
        $uploadedResultIds, $duplicateMap,
        $jnGrades, $mtGrades, $oskiGrades,
        $curriculumSubjects, $groups, $deadlines,
        $testTypes, $oskiTypes
    ) {
        $jnAvg = null;
        $mtAvg = null;
        $oskiAvg = null;

        // 1) Talaba topilmadi
        if (!$student) {
            return ['code' => 'no_student', 'text' => 'Talaba topilmadi', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 2) Quiz turi noma'lum
        if ($ynTuri === '-') {
            return ['code' => 'unknown_type', 'text' => 'Quiz turi noma\'lum', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 3) Baho noto'g'ri
        if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
            return ['code' => 'bad_grade', 'text' => 'Baho noto\'g\'ri', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 4) 1-urinish emas
        if ($result->shakl && $result->shakl !== '1-urinish') {
            return ['code' => 'not_first', 'text' => '1-urinish emas', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 5) Oldin yuklangan
        if (isset($uploadedResultIds[$result->id])) {
            return ['code' => 'uploaded', 'text' => 'Oldin yuklangan', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 6) Dublikat tekshiruvi (2O / 2T)
        $typeCode = in_array($result->quiz_type, $testTypes) ? 'T' : 'O';
        $dupKey = $result->student_id . '|' . $result->fan_id . '|' . $typeCode . '|' . $result->shakl;
        if (isset($duplicateMap[$dupKey]) && count($duplicateMap[$dupKey]) > 1) {
            $label = $typeCode === 'O' ? '2O' : '2T';
            $count = count($duplicateMap[$dupKey]);
            $text = $label . ' (' . $count . ' ta bir xil)';
            return ['code' => $label, 'text' => $text, 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 7) Jadvalda yo'q (CurriculumSubject da fan mavjudligini tekshirish)
        $group = $groups[$student->group_id] ?? null;
        if ($group) {
            $csKey = $group->curriculum_hemis_id . '|' . $result->fan_id;
            if (!isset($curriculumSubjects[$csKey])) {
                return ['code' => 'not_in_curriculum', 'text' => 'Jadvalda yo\'q', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        }

        // 8) JN va MT o'rtachalarini hisoblash
        $gradeKey = $student->hemis_id . '|' . $result->fan_id;

        // JN o'rtachasi — lesson_date bo'yicha guruhlab, har bir kun uchun o'rtacha, keyin kunlar o'rtachasi
        if (isset($jnGrades[$gradeKey])) {
            $byDate = [];
            foreach ($jnGrades[$gradeKey] as $g) {
                $d = $g['date'] ? (is_string($g['date']) ? $g['date'] : $g['date']->format('Y-m-d')) : 'null';
                $byDate[$d][] = $g['grade'];
            }
            $dateAvgs = [];
            foreach ($byDate as $grades) {
                $dateAvgs[] = array_sum($grades) / count($grades);
            }
            $jnAvg = count($dateAvgs) > 0 ? round(array_sum($dateAvgs) / count($dateAvgs), 1) : null;
        }

        // MT o'rtachasi
        if (isset($mtGrades[$gradeKey])) {
            $mtAvg = round(array_sum($mtGrades[$gradeKey]) / count($mtGrades[$gradeKey]), 1);
        }

        // OSKI o'rtachasi
        if (isset($oskiGrades[$gradeKey])) {
            $oskiAvg = round(array_sum($oskiGrades[$gradeKey]) / count($oskiGrades[$gradeKey]), 1);
        }

        // 9) YNga ruxsat tekshiruvi
        $deadline = $deadlines[$student->level_code] ?? null;
        $jnThreshold = $deadline ? $deadline->joriy : 60;
        $mtThreshold = $deadline ? $deadline->mustaqil_talim : 60;

        if ($ynTuri === 'OSKI') {
            // OSKI uchun: JN >= threshold, MT >= threshold
            if ($jnAvg !== null && $jnAvg < $jnThreshold) {
                return ['code' => 'jn_low', 'text' => 'JN yetarli emas (' . $jnAvg . '<' . $jnThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($mtAvg !== null && $mtAvg < $mtThreshold) {
                return ['code' => 'mt_low', 'text' => 'MT yetarli emas (' . $mtAvg . '<' . $mtThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        } elseif ($ynTuri === 'Test') {
            // YN test uchun: JN >= threshold, MT >= threshold, OSKI >= 60
            if ($jnAvg !== null && $jnAvg < $jnThreshold) {
                return ['code' => 'jn_low', 'text' => 'JN yetarli emas (' . $jnAvg . '<' . $jnThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($mtAvg !== null && $mtAvg < $mtThreshold) {
                return ['code' => 'mt_low', 'text' => 'MT yetarli emas (' . $mtAvg . '<' . $mtThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($oskiAvg !== null && $oskiAvg < 60) {
                return ['code' => 'oski_low', 'text' => 'OSKI yetarli emas (' . $oskiAvg . '<60)', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        }

        // 10) Hammasi joyida
        return ['code' => 'ok', 'text' => 'Yuklasa bo\'ladi', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
    }

    /**
     * Sistemaga yuklangan natijalar hisoboti.
     * student_grades jadvalidan reason='quiz_result' yozuvlar.
     * student_name, faculty, direction, semester — Students jadvalidan tartibga solingan.
     * Moodle maydonlari — hemis_quiz_results jadvalidan (quiz_result_id orqali).
     */
    public function saqlanganHisobot(Request $request)
    {
        $query = StudentGrade::where('reason', 'quiz_result')
            ->whereNotNull('quiz_result_id');

        if ($request->filled('date_from')) {
            $query->whereDate('lesson_date', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('lesson_date', '<=', $request->date_to);
        }

        $grades = $query->orderBy('student_hemis_id')->orderBy('subject_id')->orderBy('lesson_date')->get();

        // Quiz result ID lar orqali Moodle ma'lumotlarini olish
        $quizResultIds = $grades->pluck('quiz_result_id')->unique()->values()->toArray();
        $quizResults = HemisQuizResult::whereIn('id', $quizResultIds)->get()->keyBy('id');

        // Student ID lar orqali Student ma'lumotlarini olish
        $studentHemisIds = $grades->pluck('student_hemis_id')->unique()->values()->toArray();
        $students = Student::where(function ($q) use ($studentHemisIds) {
            $q->whereIn('hemis_id', $studentHemisIds)
              ->orWhereIn('student_id_number', $studentHemisIds);
        })->get();

        $studentLookup = [];
        foreach ($students as $student) {
            $studentLookup[$student->hemis_id] = $student;
            if ($student->student_id_number) {
                $studentLookup[$student->student_id_number] = $student;
            }
        }

        $data = [];
        $rowNum = 0;

        foreach ($grades as $grade) {
            $quiz = $quizResults[$grade->quiz_result_id] ?? null;
            $student = $studentLookup[$grade->student_hemis_id] ?? null;

            // Tartibga solingan qiymatlar (Student jadvalidan)
            $studentName = $student ? $student->full_name : ($quiz->student_name ?? '-');
            $faculty = $student ? $student->department_name : ($quiz->faculty ?? '-');
            $direction = $student ? $student->specialty_name : ($quiz->direction ?? '-');

            $semLabel = $grade->semester_name ?: ($student ? $student->semester_name : '');
            $semNum = null;
            if ($semLabel && preg_match('/(\d+)/', $semLabel, $m)) {
                $semNum = (int) $m[1];
            }
            $semester = $semNum ? $semNum . '-sem' : ($semLabel ?: '-');

            // Moodle formatidagi qiymatlar (hemis_quiz_results jadvalidan)
            $rowNum++;
            $data[] = [
                'id'           => $grade->id,
                'row_num'      => $rowNum,
                'attempt_id'   => $quiz->attempt_id ?? '-',
                'student_id'   => $student ? $student->id : '-',
                'student_name' => $studentName,
                'faculty'      => $faculty,
                'direction'    => $direction,
                'semester'     => $semester,
                'fan_id'       => $quiz->fan_id ?? $grade->subject_id,
                'fan_name'     => $quiz->fan_name ?? $grade->subject_name,
                'quiz_type'    => $quiz->quiz_type ?? '-',
                'attempt_name' => $quiz->attempt_name ?? '-',
                'shakl'        => $quiz->shakl ?? '-',
                'grade'        => $grade->grade,
                'date_start'   => $quiz && $quiz->date_start ? $quiz->date_start->format('d.m.Y H:i') : '',
                'date_finish'  => $quiz && $quiz->date_finish ? $quiz->date_finish->format('d.m.Y H:i') : '',
            ];
        }

        return response()->json([
            'data' => $data,
            'total' => count($data),
        ]);
    }

    public function index(Request $request)
    {
        $query = HemisQuizResult::where('is_active', 1);

        if ($request->filled('faculty')) {
            $query->where('faculty', $request->faculty);
        }

        if ($request->filled('direction')) {
            $query->where('direction', $request->direction);
        }

        if ($request->filled('semester')) {
            $query->where('semester', $request->semester);
        }

        if ($request->filled('fan_name')) {
            $query->where('fan_name', 'LIKE', '%' . $request->fan_name . '%');
        }

        if ($request->filled('student_name')) {
            $query->where('student_name', 'LIKE', '%' . $request->student_name . '%');
        }

        if ($request->filled('student_id')) {
            $query->where('student_id', $request->student_id);
        }

        if ($request->filled('quiz_type')) {
            $query->where('quiz_type', $request->quiz_type);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('date_finish', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('date_finish', '<=', $request->date_to);
        }

        $perPage = $request->input('per_page', 50);
        $results = $query->orderByDesc('date_finish')->paginate($perPage)->withQueryString();

        // Filtrlar uchun unique qiymatlarni olish
        $faculties = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('faculty')
            ->distinct()
            ->pluck('faculty')
            ->sort();

        $semesters = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('semester')
            ->distinct()
            ->pluck('semester')
            ->sort();

        $quizTypes = HemisQuizResult::where('is_active', 1)
            ->whereNotNull('quiz_type')
            ->distinct()
            ->pluck('quiz_type')
            ->sort();

        $routePrefix = $this->routePrefix();

        return view('admin.quiz_results.index', compact(
            'results', 'faculties', 'semesters', 'quizTypes', 'routePrefix'
        ));
    }

    public function exportExcel(Request $request)
    {
        return (new QuizResultExport($request))->export();
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:xlsx,xls,csv|max:10240',
        ]);

        $import = new QuizResultImport();
        Excel::import($import, $request->file('file'));

        $successCount = $import->successCount;
        $errors = $import->errors;

        $indexRoute = $this->routePrefix() . '.quiz-results.index';

        if (count($errors) > 0) {
            return redirect()->route($indexRoute)
                ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.")
                ->with('import_errors', $errors)
                ->with('error_count', count($errors));
        }

        return redirect()->route($indexRoute)
            ->with('success', "$successCount ta natija student_grades ga muvaffaqiyatli yuklandi.");
    }

    /**
     * Diagnostika — dry-run: qaysi natijalar o'tadi, qaysilari xato beradi tekshirish.
     * Faqat 1-urinish natijalar hisobga olinadi.
     * student_grades ga hech narsa yozilmaydi.
     */
    public function diagnostika(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        $ok = [];
        $errors = [];
        $skipped = 0;
        $duplicateTracker = [];

        foreach ($results as $result) {
            $row = [
                'id' => $result->id,
                'attempt_id' => $result->attempt_id,
                'student_id' => $result->student_id,
                'student_name' => $result->student_name,
                'fan_id' => $result->fan_id,
                'fan_name' => $result->fan_name,
                'grade' => $result->grade,
            ];

            // Faqat 1-urinish hisobga olinadi
            if ($result->shakl !== '1-urinish') {
                $row['error'] = "1-urinish emas ({$result->shakl}) — o'tkazib yuborildi";
                $errors[] = $row;
                $skipped++;
                continue;
            }

            // Baho validatsiyasi
            if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
                $row['error'] = "Baho noto'g'ri: {$result->grade}";
                $errors[] = $row;
                continue;
            }

            // Talabani topish (avval hemis_id, keyin student_id_number bo'yicha)
            $student = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();
            if (!$student) {
                $row['error'] = "Talaba topilmadi (student_id: {$result->student_id})";
                $errors[] = $row;
                continue;
            }

            // Guruhni topish
            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $row['error'] = "Talaba guruhida guruh topilmadi";
                $errors[] = $row;
                continue;
            }

            // Fanni topish
            $subject = null;
            if ($result->fan_id) {
                $subject = CurriculumSubject::where('subject_id', $result->fan_id)->first();
            }
            if (!$subject) {
                $row['error'] = "Fan topilmadi (fan_id: {$result->fan_id})";
                $errors[] = $row;
                continue;
            }

            // Avval yuklangan-mi tekshirish
            $existing = StudentGrade::where('quiz_result_id', $result->id)->first();
            if ($existing) {
                $row['error'] = "Bu natija avval yuklangan (grade_id: {$existing->id})";
                $errors[] = $row;
                continue;
            }

            // Bir xil talaba+fan dublikatlarni aniqlash (bir necha marta test yechganlar)
            $key = $result->student_id . '_' . $result->fan_id;
            if (isset($duplicateTracker[$key])) {
                $row['error'] = "Dublikat: bu talaba uchun shu fandan yana bir natija mavjud (#{$duplicateTracker[$key]})";
                $errors[] = $row;
                continue;
            }
            $duplicateTracker[$key] = $result->id;

            // YN turini quiz_type dan aniqlash
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            if (in_array($result->quiz_type, $oskiTypes)) {
                $row['jurnal_ustun'] = 'OSKI';
            } elseif (in_array($result->quiz_type, $testTypes)) {
                $row['jurnal_ustun'] = 'Test';
            } else {
                $row['error'] = "Quiz turi aniqlanmadi: '{$result->quiz_type}' (OSKI yoki YN test bo'lishi kerak)";
                $errors[] = $row;
                continue;
            }

            $row['student_db_name'] = $student->full_name ?? $student->fio ?? $result->student_name;
            $row['subject_name'] = $subject->subject_name;
            $ok[] = $row;
        }

        return response()->json([
            'ok_count' => count($ok),
            'error_count' => count($errors),
            'skipped_count' => $skipped,
            'ok' => $ok,
            'errors' => $errors,
        ]);
    }

    /**
     * Sistemaga yuklash — filtrlangan natijalarni student_grades ga yozish.
     */
    public function uploadToGrades(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        $successCount = 0;
        $errors = [];
        $duplicateTracker = [];

        foreach ($results as $result) {
            $rowInfo = [
                'id' => $result->id,
                'attempt_id' => $result->attempt_id,
                'student_id' => $result->student_id,
                'student_name' => $result->student_name,
                'fan_name' => $result->fan_name,
                'grade' => $result->grade,
            ];

            // Faqat 1-urinish yuklanadi
            if ($result->shakl !== '1-urinish') {
                $rowInfo['error'] = "Faqat 1-urinish yuklanadi (hozirgi: {$result->shakl})";
                $errors[] = $rowInfo;
                continue;
            }

            if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
                $rowInfo['error'] = "Baho noto'g'ri: {$result->grade}";
                $errors[] = $rowInfo;
                continue;
            }

            $student = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();
            if (!$student) {
                $rowInfo['error'] = "Talaba topilmadi (student_id: {$result->student_id})";
                $errors[] = $rowInfo;
                continue;
            }

            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $rowInfo['error'] = "Talaba guruhida guruh topilmadi";
                $errors[] = $rowInfo;
                continue;
            }

            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $student->semester_code)
                ->first();

            $subject = null;
            if ($result->fan_id) {
                $subject = CurriculumSubject::where('subject_id', $result->fan_id)->first();
            }
            if (!$subject) {
                $rowInfo['error'] = "Fan topilmadi (fan_id: {$result->fan_id})";
                $errors[] = $rowInfo;
                continue;
            }

            $existing = StudentGrade::where('quiz_result_id', $result->id)->first();
            if ($existing) {
                $rowInfo['error'] = "Bu natija avval yuklangan";
                $errors[] = $rowInfo;
                continue;
            }

            // Dublikat tekshirish
            $key = $result->student_id . '_' . $result->fan_id;
            if (isset($duplicateTracker[$key])) {
                $rowInfo['error'] = "Dublikat: bu talaba+fan juftligi takrorlangan";
                $errors[] = $rowInfo;
                continue;
            }
            $duplicateTracker[$key] = $result->id;

            // Quiz type dan training_type_code va name aniqlash
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            if (in_array($result->quiz_type, $oskiTypes)) {
                $trainingTypeCode = 101;
                $trainingTypeName = 'Oski';
            } elseif (in_array($result->quiz_type, $testTypes)) {
                $trainingTypeCode = 102;
                $trainingTypeName = 'Yakuniy test';
            } else {
                $rowInfo['error'] = "Quiz turi aniqlanmadi: '{$result->quiz_type}' (OSKI yoki YN test bo'lishi kerak)";
                $errors[] = $rowInfo;
                continue;
            }

            StudentGrade::create([
                'student_id' => $student->id,
                'student_hemis_id' => $student->hemis_id,
                'hemis_id' => 999999999,
                'semester_code' => $semester->code ?? $student->semester_code,
                'semester_name' => $semester->name ?? $student->semester_name,
                'subject_schedule_id' => 0,
                'subject_id' => $subject->subject_id,
                'subject_name' => $subject->subject_name,
                'subject_code' => $subject->subject_code ?? '',
                'training_type_code' => $trainingTypeCode,
                'training_type_name' => $trainingTypeName,
                'employee_id' => 0,
                'employee_name' => auth()->user()->name ?? 'Test markazi',
                'lesson_pair_name' => '',
                'lesson_pair_code' => '',
                'lesson_pair_start_time' => '',
                'lesson_pair_end_time' => '',
                'lesson_date' => $result->date_finish ?? now(),
                'created_at_api' => $result->created_at ?? now(),
                'reason' => 'quiz_result',
                'status' => 'recorded',
                'grade' => round($result->grade),
                'deadline' => now(),
                'quiz_result_id' => $result->id,
            ]);

            $successCount++;
        }

        return response()->json([
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Moodle quiz results cron ni qo'lda ishga tushirish.
     */
    public function triggerCron()
    {
        Setting::set('moodle_sync_requested', now()->toIso8601String());

        Log::info('quiz:trigger-cron — sync so\'raldi', [
            'user' => auth()->user()->name ?? 'unknown',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Sync so\'rov yuborildi. Moodle 5 daqiqa ichida ma\'lumotlarni yuboradi.',
        ]);
    }

    /**
     * Bitta quiz natijani o'chirish (is_active = 0).
     */
    public function destroy($id)
    {
        $result = HemisQuizResult::findOrFail($id);
        $result->update(['is_active' => 0]);

        return response()->json(['success' => true]);
    }
}
