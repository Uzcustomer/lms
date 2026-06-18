<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\HemisQuizResult;
use App\Models\MarkingSystemScore;
use App\Models\Student;
use App\Models\StudentGrade;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use App\Imports\QuizResultImport;
use App\Exports\QuizResultExport;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;

class QuizResultController extends Controller
{
    /**
     * FISH qidiruvi uchun students jadvalidan topilgan identifikatorlar.
     * Natija ichidagi student_name ba'zan students.full_name bilan farq qiladi,
     * shu sabab hemis_id va student_id_number bo'yicha ham qidiramiz.
     */
    private function findStudentIdentifiersByName(string $name): array
    {
        $needle = trim($name);
        if ($needle === '') {
            return [];
        }

        $ids = [];
        $rows = Student::query()
            ->where('full_name', 'LIKE', '%' . $needle . '%')
            ->get(['hemis_id', 'student_id_number']);

        foreach ($rows as $student) {
            if (!empty($student->hemis_id)) {
                $ids[] = (string) $student->hemis_id;
            }
            if (!empty($student->student_id_number)) {
                $ids[] = (string) $student->student_id_number;
            }
        }

        return array_values(array_unique(array_filter($ids, fn ($v) => $v !== '')));
    }

    /**
     * Joriy guard bo'yicha route prefiksini aniqlash (admin yoki teacher).
     */
    private function routePrefix(): string
    {
        return auth()->guard('teacher')->check() ? 'teacher' : 'admin';
    }

    /**
     * "1-urinish" / "2-urinish" / "3-urinish" → 1/2/3.
     * shakl noma'lum bo'lsa attempt_number ga fallback.
     */
    public static function parseAttemptFromShakl(?string $shakl, $attemptNumber = null): int
    {
        if (is_string($shakl) && preg_match('/^(\d+)-urinish$/i', trim($shakl), $m)) {
            $n = (int) $m[1];
            if ($n >= 1 && $n <= 3) return $n;
        }
        $n = (int) ($attemptNumber ?? 1);
        return $n >= 1 ? $n : 1;
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
     * Ustunlar va xulosa logikasi diagnostika (tartibgaSol) bilan bir xil.
     */
    public function yuklanmaganNatijalar(Request $request)
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);

        try {
            // Faqat yuklanmagan natijalarni olish
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

            $totalCount = (clone $query)->count();
            if ($totalCount > 5000) {
                return response()->json([
                    'error' => "Tanlangan oraliqda {$totalCount} ta natija mavjud. Iltimos, sanani qisqartiring (5000 tagacha bo'lishi kerak).",
                ], 422);
            }

            $results = $query->orderBy('student_id')->orderBy('fan_id')->orderBy('date_finish')
                ->limit(5000)
                ->get();

            // ====== TARTIBGA SOL LOGIKASI (diagnostika bilan bir xil) ======

            // Barcha student_id larni yig'ish va bulk query
            $studentIds = $results->pluck('student_id')->unique()->values()->toArray();

            $students = Student::where(function ($q) use ($studentIds) {
                $q->whereIn('hemis_id', $studentIds)
                  ->orWhereIn('student_id_number', $studentIds);
            })->get();

            // Lookup yaratish
            $studentLookup = [];
            foreach ($students as $student) {
                $studentLookup[$student->hemis_id] = $student;
                if ($student->student_id_number) {
                    $studentLookup[$student->student_id_number] = $student;
                }
            }

            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

            // ====== XULOSA UCHUN MA'LUMOTLAR ======

            // 1) Yuklangan natijalar (bu yerda bo'sh bo'lishi kerak, lekin boshqa tekshiruvlar uchun)
            $allResultIds = $results->pluck('id')->toArray();
            $uploadedResultIds = [];
            if (!empty($allResultIds)) {
                // OSKI/Test: reason='quiz_result' bilan yangi yozuv
                // Mavzu retake: reason='absent'/'low_grade', lekin quiz_result_id bilan yozilgan
                $uploadedResultIds = StudentGrade::whereIn('quiz_result_id', $allResultIds)
                    ->whereNotNull('quiz_result_id')
                    ->pluck('quiz_result_id')
                    ->toArray();
            }
            $uploadedResultIds = array_flip($uploadedResultIds);

            // 2) Dublikatlar
            $duplicateMap = [];
            foreach ($results as $result) {
                $ynTuri = '-';
                if (in_array($result->quiz_type, $testTypes)) $ynTuri = 'T';
                elseif (in_array($result->quiz_type, $oskiTypes)) $ynTuri = 'O';
                if ($ynTuri === '-') continue;

                $key = $result->student_id . '|' . $result->fan_id . '|' . $ynTuri . '|' . $result->shakl;
                $duplicateMap[$key][] = $result->id;
            }

            // 3) JN/MT/OSKI baholar
            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $fanIds = $results->pluck('fan_id')->unique()->values()->toArray();

            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

            // 4) CurriculumSubject va Groups
            $groupIds = $students->pluck('group_id')->unique()->toArray();
            $groups = !empty($groupIds)
                ? Group::whereIn('group_hemis_id', $groupIds)->get()->keyBy('group_hemis_id')
                : collect();
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

            // Student->group mapping va semester aniqlash (JN, MT va OSKI uchun)
            $studentGroupMap = [];
            $studentSubjectSemester = [];

            if ($groups->isNotEmpty()) {
                foreach ($students as $s) {
                    $grp = $groups[$s->group_id] ?? null;
                    if (!$grp) continue;
                    $studentGroupMap[$s->hemis_id] = $s->group_id;
                    foreach ($fanIds as $fId) {
                        $csK = $grp->curriculum_hemis_id . '|' . $fId;
                        $semC = $curriculumSubjects[$csK] ?? null;
                        if (!$semC) continue;
                        $studentSubjectSemester[$s->hemis_id . '|' . $fId] = $semC;
                    }
                }
            }

            // JN baholar — jurnal logikasi bilan bir xil hisoblash (schedule-based daily average)
            $jnGrades = []; // student_hemis_id|subject_id => computed JN average (pre-computed)

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($studentGroupMap)) {
                // JB jadval sanalarini batch olish (schedules jadvalidan)
                $jbScheduleData = [];
                $uniqueGroupIds = array_unique(array_values($studentGroupMap));
                $allSemCodes = array_unique(array_values($studentSubjectSemester));

                if (!empty($uniqueGroupIds) && !empty($allSemCodes)) {
                    $jbSchedRows = DB::table('schedules')
                        ->whereNull('deleted_at')
                        ->whereIn('group_id', $uniqueGroupIds)
                        ->whereIn('subject_id', $fanIds)
                        ->whereIn('semester_code', $allSemCodes)
                        ->whereNotIn('training_type_code', $excludedCodes)
                        ->whereNotIn('training_type_name', ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"])
                        ->whereNotNull('lesson_date')
                        ->select('group_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code')
                        ->orderBy('lesson_date')
                        ->orderBy('lesson_pair_code')
                        ->get();

                    foreach ($jbSchedRows as $sr) {
                        $schedKey = $sr->group_id . '|' . $sr->subject_id . '|' . $sr->semester_code;
                        $dateStr = \Carbon\Carbon::parse($sr->lesson_date)->format('Y-m-d');
                        $pairKey = $dateStr . '_' . $sr->lesson_pair_code;

                        if (!isset($jbScheduleData[$schedKey])) {
                            $jbScheduleData[$schedKey] = ['dates' => [], 'pairsPerDay' => [], 'datePairSet' => []];
                        }
                        if (!isset($jbScheduleData[$schedKey]['datePairSet'][$pairKey])) {
                            $jbScheduleData[$schedKey]['datePairSet'][$pairKey] = true;
                            $jbScheduleData[$schedKey]['pairsPerDay'][$dateStr] = ($jbScheduleData[$schedKey]['pairsPerDay'][$dateStr] ?? 0) + 1;
                        }
                        if (!in_array($dateStr, $jbScheduleData[$schedKey]['dates'])) {
                            $jbScheduleData[$schedKey]['dates'][] = $dateStr;
                        }
                    }
                    foreach ($jbScheduleData as &$sd) {
                        sort($sd['dates']);
                    }
                    unset($sd, $jbSchedRows);
                }

                // JN baholarini olish (semester filtri, status/retake filtri — jurnal logikasi)
                $jnGradesGrouped = [];

                if (!empty($allSemCodes)) {
                    foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                        $gradeRows = DB::table('student_grades')
                            ->whereNull('deleted_at')
                            ->whereIn('student_hemis_id', $chunk)
                            ->whereIn('subject_id', $fanIds)
                            ->whereIn('semester_code', $allSemCodes)
                            ->whereNotIn('training_type_code', $excludedCodes)
                            ->whereNotNull('lesson_date')
                            ->select('student_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                            ->get();

                        foreach ($gradeRows as $gr) {
                            // Effective grade filtri (jurnal getEffectiveGrade logikasi bilan bir xil)
                            $effGrade = null;
                            if ($gr->grade !== null && (float) $gr->grade < 60 && $gr->retake_grade !== null) {
                                // ENG YUQORI QOIDA: asl baho 60 dan past + retake mavjud -> retake ustun
                                $effGrade = $gr->retake_grade;
                            } elseif ($gr->status === 'pending' && $gr->reason === 'low_grade' && $gr->grade !== null) {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'pending') {
                                continue;
                            } elseif ($gr->reason === 'absent' && $gr->grade === null) {
                                $effGrade = $gr->retake_grade !== null ? $gr->retake_grade : null;
                            } elseif ($gr->status === 'closed' && $gr->reason === 'teacher_victim' && $gr->grade == 0 && $gr->retake_grade === null) {
                                continue;
                            } elseif ($gr->status === 'recorded') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'closed') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->retake_grade !== null) {
                                $effGrade = $gr->retake_grade;
                            }
                            if ($effGrade === null) continue;

                            $gHemisId = $studentGroupMap[$gr->student_hemis_id] ?? null;
                            if (!$gHemisId) continue;

                            $schedKey = $gHemisId . '|' . $gr->subject_id . '|' . $gr->semester_code;
                            if (!isset($jbScheduleData[$schedKey])) continue;

                            $dateStr = \Carbon\Carbon::parse($gr->lesson_date)->format('Y-m-d');
                            $pairKey = $dateStr . '_' . $gr->lesson_pair_code;
                            if (!isset($jbScheduleData[$schedKey]['datePairSet'][$pairKey])) continue;

                            $gKey = $gr->student_hemis_id . '|' . $gr->subject_id;
                            $jnGradesGrouped[$gKey][$dateStr][$gr->lesson_pair_code] = (float) $effGrade;
                        }
                        unset($gradeRows);
                    }
                }

                // JN o'rtachasini hisoblash (jurnal logikasi: kunlik o'rtacha -> umumiy o'rtacha)
                $cutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->format('Y-m-d');
                foreach ($studentSubjectSemester as $gKey => $semCode) {
                    [$hId, $fId] = explode('|', $gKey);
                    $gHemisId = $studentGroupMap[$hId] ?? null;
                    if (!$gHemisId) continue;

                    $schedKey = $gHemisId . '|' . $fId . '|' . $semCode;
                    $schedData = $jbScheduleData[$schedKey] ?? null;

                    if (!$schedData || empty($schedData['dates'])) continue;

                    $jnDailySum = 0;
                    $studentJnG = $jnGradesGrouped[$gKey] ?? [];
                    $datesForAvg = array_filter($schedData['dates'], fn($d) => $d <= $cutoffDate);
                    $totalJnDays = count($datesForAvg);

                    foreach ($datesForAvg as $date) {
                        $dayG = $studentJnG[$date] ?? [];
                        $pairsInDay = $schedData['pairsPerDay'][$date] ?? 1;
                        $gradeSum = array_sum($dayG);
                        $jnDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    }

                    $jnGrades[$gKey] = $totalJnDays > 0
                        ? round($jnDailySum / $totalJnDays, 0, PHP_ROUND_HALF_UP)
                        : 0;
                }
            }

            // MT baholar — jurnal logikasi bilan bir xil hisoblash (schedule-based daily average)
            $mtGrades = [];

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($studentGroupMap)) {
                // MT jadval sanalarini batch olish (schedules jadvalidan)
                $mtScheduleData = [];
                $uniqueGroupIds = array_unique(array_values($studentGroupMap));

                if (!empty($uniqueGroupIds)) {
                    $mtSchedRows = DB::table('schedules')
                        ->whereNull('deleted_at')
                        ->whereIn('group_id', $uniqueGroupIds)
                        ->whereIn('subject_id', $fanIds)
                        ->where('training_type_code', 99)
                        ->whereNotNull('lesson_date')
                        ->select('group_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code')
                        ->orderBy('lesson_date')
                        ->orderBy('lesson_pair_code')
                        ->get();

                    foreach ($mtSchedRows as $sr) {
                        $schedKey = $sr->group_id . '|' . $sr->subject_id . '|' . $sr->semester_code;
                        $dateStr = \Carbon\Carbon::parse($sr->lesson_date)->format('Y-m-d');
                        $pairKey = $dateStr . '_' . $sr->lesson_pair_code;

                        if (!isset($mtScheduleData[$schedKey])) {
                            $mtScheduleData[$schedKey] = ['dates' => [], 'pairsPerDay' => [], 'datePairSet' => []];
                        }
                        if (!isset($mtScheduleData[$schedKey]['datePairSet'][$pairKey])) {
                            $mtScheduleData[$schedKey]['datePairSet'][$pairKey] = true;
                            $mtScheduleData[$schedKey]['pairsPerDay'][$dateStr] = ($mtScheduleData[$schedKey]['pairsPerDay'][$dateStr] ?? 0) + 1;
                        }
                        if (!in_array($dateStr, $mtScheduleData[$schedKey]['dates'])) {
                            $mtScheduleData[$schedKey]['dates'][] = $dateStr;
                        }
                    }
                    foreach ($mtScheduleData as &$sd) {
                        sort($sd['dates']);
                    }
                    unset($sd, $mtSchedRows);
                }

                // MT baholarini olish (semester filtri, status/retake filtri — jurnal logikasi)
                $mtGradesGrouped = [];
                $allSemCodes = array_unique(array_values($studentSubjectSemester));

                if (!empty($allSemCodes)) {
                    foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                        $gradeRows = DB::table('student_grades')
                            ->whereNull('deleted_at')
                            ->whereIn('student_hemis_id', $chunk)
                            ->whereIn('subject_id', $fanIds)
                            ->whereIn('semester_code', $allSemCodes)
                            ->whereNotIn('training_type_code', [100, 101, 102, 103])
                            ->whereNotNull('lesson_date')
                            ->select('student_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                            ->get();

                        foreach ($gradeRows as $gr) {
                            if ($gr->status === 'pending') continue;
                            $effGrade = null;
                            if ($gr->reason === 'absent' && $gr->grade === null) {
                                $effGrade = $gr->retake_grade !== null ? $gr->retake_grade : null;
                            } elseif ($gr->status === 'closed' && $gr->reason === 'teacher_victim' && $gr->grade == 0 && $gr->retake_grade === null) {
                                continue;
                            } elseif ($gr->status === 'recorded') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'closed') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->retake_grade !== null) {
                                $effGrade = $gr->retake_grade;
                            }
                            if ($effGrade === null) continue;

                            $gHemisId = $studentGroupMap[$gr->student_hemis_id] ?? null;
                            if (!$gHemisId) continue;

                            $schedKey = $gHemisId . '|' . $gr->subject_id . '|' . $gr->semester_code;
                            if (!isset($mtScheduleData[$schedKey])) continue;

                            $dateStr = \Carbon\Carbon::parse($gr->lesson_date)->format('Y-m-d');
                            $pairKey = $dateStr . '_' . $gr->lesson_pair_code;
                            if (!isset($mtScheduleData[$schedKey]['datePairSet'][$pairKey])) continue;

                            $gKey = $gr->student_hemis_id . '|' . $gr->subject_id;
                            $mtGradesGrouped[$gKey][$dateStr][$gr->lesson_pair_code] = (float) $effGrade;
                        }
                        unset($gradeRows);
                    }
                }

                // Manual MT baholarini olish (lesson_date IS NULL — override)
                $manualMtGrades = [];
                foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                    $manualRows = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->whereIn('student_hemis_id', $chunk)
                        ->whereIn('subject_id', $fanIds)
                        ->where('training_type_code', 99)
                        ->whereNull('lesson_date')
                        ->whereNotNull('grade')
                        ->select('student_hemis_id', 'subject_id', 'grade')
                        ->get();
                    foreach ($manualRows as $mr) {
                        $manualMtGrades[$mr->student_hemis_id . '|' . $mr->subject_id] = (float) $mr->grade;
                    }
                    unset($manualRows);
                }

                // MT o'rtachasini hisoblash (jurnal logikasi: kunlik o'rtacha -> umumiy o'rtacha)
                foreach ($studentSubjectSemester as $gKey => $semCode) {
                    [$hId, $fId] = explode('|', $gKey);
                    $gHemisId = $studentGroupMap[$hId] ?? null;
                    if (!$gHemisId) continue;

                    $schedKey = $gHemisId . '|' . $fId . '|' . $semCode;
                    $schedData = $mtScheduleData[$schedKey] ?? null;

                    if (!$schedData || empty($schedData['dates'])) {
                        if (isset($manualMtGrades[$gKey])) {
                            $mtGrades[$gKey] = round($manualMtGrades[$gKey], 0, PHP_ROUND_HALF_UP);
                        }
                        continue;
                    }

                    $mtDailySum = 0;
                    $studentMtG = $mtGradesGrouped[$gKey] ?? [];
                    $totalMtDays = count($schedData['dates']);

                    foreach ($schedData['dates'] as $date) {
                        $dayG = $studentMtG[$date] ?? [];
                        $pairsInDay = $schedData['pairsPerDay'][$date] ?? 1;
                        $gradeSum = array_sum($dayG);
                        $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    }

                    $mt = $totalMtDays > 0
                        ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                        : 0;

                    if (isset($manualMtGrades[$gKey])) {
                        $mt = round($manualMtGrades[$gKey], 0, PHP_ROUND_HALF_UP);
                    }

                    $mtGrades[$gKey] = $mt;
                }
            }

            // OSKI/Test baholar — jurnal logikasi: MAX(grade), faqat JORIY semestr, YN turi bo'yicha alohida
            // Lookup kaliti: hemis|subject_id|semester|YN_turi (O=OSKI/101, T=Test/102)
            $oskiGrades = [];
            $currentSemCodes = $students->pluck('semester_code')->filter()->unique()->values()->toArray();

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($currentSemCodes)) {
                foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                    $oskiRows = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->whereIn('student_hemis_id', $chunk)
                        ->whereIn('subject_id', $fanIds)
                        ->whereIn('semester_code', $currentSemCodes)
                        ->whereIn('training_type_code', [101, 102])
                        ->whereNotNull('grade')
                        ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'training_type_code', DB::raw('MAX(grade) as grade'))
                        ->groupBy('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'training_type_code')
                        ->get();

                    foreach ($oskiRows as $row) {
                        $ttype = (int) $row->training_type_code === 101 ? 'O' : 'T';
                        $k = $row->student_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code . '|' . $ttype;
                        $oskiGrades[$k] = (float) $row->grade;
                        $kName = $row->student_hemis_id . '|' . mb_strtolower($row->subject_name) . '|' . $row->semester_code . '|' . $ttype;
                        if (!isset($oskiGrades[$kName])) {
                            $oskiGrades[$kName] = (float) $row->grade;
                        }
                    }
                    unset($oskiRows);
                }
            }

            // Mavzu (N-mavzu) shakldagi natijalar uchun jurnal holatini oldindan tayyorlash
            // Har bir (student, fan, mavzu_n) uchun jurnaldagi state: NB / Baho / Retake / Bo'sh
            $mavzuStates = $this->buildMavzuStates($results, $studentLookup, $students);

            // ====== DATA TAYYORLASH ======
            $data = [];
            $rowNum = 0;

            foreach ($results as $result) {
                $student = $studentLookup[$result->student_id] ?? null;

                $semNum = null;
                $semLabel = $result->semester ?: ($student ? $student->semester_name : '');
                if ($semLabel && preg_match('/(\d+)/', $semLabel, $m)) {
                    $semNum = (int) $m[1];
                }
                $kurs = $semNum ? (int) ceil($semNum / 2) : null;

                $ynTuri = '-';
                if (in_array($result->quiz_type, $testTypes)) {
                    $ynTuri = 'Test';
                } elseif (in_array($result->quiz_type, $oskiTypes)) {
                    $ynTuri = 'OSKI';
                }

                // Xulosa hisoblash
                $xulosa = $this->calculateXulosa(
                    $result, $student, $ynTuri,
                    $uploadedResultIds, $duplicateMap,
                    $jnGrades, $mtGrades, $oskiGrades,
                    $curriculumSubjects, $groups,
                    $testTypes, $oskiTypes,
                    [], null, $mavzuStates
                );

                $rowNum++;
                $studentGroup = ($student && isset($groups[$student->group_id])) ? $groups[$student->group_id] : null;
                $data[] = [
                    'id' => $result->id,
                    'row_num' => $rowNum,
                    'student_id' => $result->student_id,
                    'student_hemis_id' => $student ? $student->hemis_id : null,
                    'group_local_id' => $studentGroup ? $studentGroup->id : null,
                    'semester_code' => $student ? $student->semester_code : null,
                    'full_name' => $student ? $student->full_name : $result->student_name,
                    'faculty' => $student ? $student->department_name : '-',
                    'direction' => $student ? $student->specialty_name : '-',
                    'kurs' => $kurs ? $kurs . '-kurs' : '-',
                    'semester' => $semNum ? $semNum . '-sem' : ($semLabel ?: '-'),
                    'group' => $student ? $student->group_name : '-',
                    'fan_name' => $result->fan_name,
                    'fan_id' => $result->fan_id,
                    'yn_turi' => $ynTuri,
                    'shakl' => $result->shakl,
                    'grade' => $result->grade,
                    'date' => $result->date_finish ? $result->date_finish->format('d.m.Y') : '',
                    'xulosa' => $xulosa['text'],
                    'xulosa_code' => $xulosa['code'],
                    'jn_avg' => $xulosa['jn_avg'],
                    'mt_avg' => $xulosa['mt_avg'],
                    'oski_avg' => $xulosa['oski_avg'],
                ];
            }

            return response()->json([
                'data' => $data,
                'total' => count($data),
            ]);
        } catch (\Throwable $e) {
            Log::error('yuklanmaganNatijalar xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'memory_peak_mb' => round(memory_get_peak_usage(true) / 1024 / 1024, 1),
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

        // Bitta (talaba, fan, quiz_type, shakl) bo'yicha eng yuqori bahoga ega bo'lgan
        // urinishni qoldirish (NOT EXISTS yondashuvi). Tenglik holatida — eng oxirgi
        // attempt_id (qaysi urinish keyinroq topshirilgan).
        $query->whereNotExists(function ($sub) {
            $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                ->from('hemis_quiz_results as h2')
                ->where('h2.is_active', 1)
                ->whereColumn('h2.student_id', 'hemis_quiz_results.student_id')
                ->whereColumn('h2.fan_id', 'hemis_quiz_results.fan_id')
                ->whereColumn('h2.quiz_type', 'hemis_quiz_results.quiz_type')
                ->whereColumn('h2.shakl', 'hemis_quiz_results.shakl')
                ->where(function ($q) {
                    $q->whereColumn('h2.grade', '>', 'hemis_quiz_results.grade')
                      ->orWhere(function ($q2) {
                          $q2->whereColumn('h2.grade', '=', 'hemis_quiz_results.grade')
                             ->whereColumn('h2.attempt_id', '>', 'hemis_quiz_results.attempt_id');
                      });
                });
        });

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
            $nameIds = $this->findStudentIdentifiersByName((string) $request->student_name);
            $query->where(function ($q) use ($request, $nameIds) {
                $q->where('student_name', 'LIKE', '%' . $request->student_name . '%');
                if (!empty($nameIds)) {
                    $q->orWhereIn('student_id', $nameIds);
                }
            });
        }
        if ($request->filled('shakl_search')) {
            $query->where('shakl', 'LIKE', '%' . $request->shakl_search . '%');
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
                'fan_id' => $item->fan_id,
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
        try {
            set_time_limit(300);
            ini_set('memory_limit', '512M');

            $query = HemisQuizResult::where('is_active', 1);

            $dateFrom = $request->filled('date_from') ? $request->date_from : null;
            $dateTo   = $request->filled('date_to')   ? $request->date_to   : null;
            $hasNameSearch = $request->filled('student_name');
            $hasShaklSearch = $request->filled('shakl_search');

            // Ism bilan qidirishda talabaning barcha mos yozuvlari ko'rinsin.
            // Aks holda boshqa sanadagi balandroq baho aynan kerakli yozuvni
            // yashirib yuboradi (masalan 03.06 dagi 40 bahoni 24.01 dagi 100).
            if (!$hasNameSearch) {
                // Bitta (talaba, fan, quiz_type, shakl) bo'yicha eng yuqori bahoga
                // ega bo'lgan urinishni qoldirish. Sana oralig'i tanlangan bo'lsa,
                // dedup ham shu oralig'ning o'zida ishlaydi.
                $query->whereNotExists(function ($sub) use ($dateFrom, $dateTo) {
                    $sub->select(\Illuminate\Support\Facades\DB::raw(1))
                        ->from('hemis_quiz_results as h2')
                        ->where('h2.is_active', 1)
                        ->whereColumn('h2.student_id', 'hemis_quiz_results.student_id')
                        ->whereColumn('h2.fan_id', 'hemis_quiz_results.fan_id')
                        ->whereColumn('h2.quiz_type', 'hemis_quiz_results.quiz_type')
                        ->whereColumn('h2.shakl', 'hemis_quiz_results.shakl')
                        ->where(function ($q) {
                            $q->whereColumn('h2.grade', '>', 'hemis_quiz_results.grade')
                              ->orWhere(function ($q2) {
                                  $q2->whereColumn('h2.grade', '=', 'hemis_quiz_results.grade')
                                     ->whereColumn('h2.attempt_id', '>', 'hemis_quiz_results.attempt_id');
                              });
                        });
                    if ($dateFrom) $sub->whereDate('h2.date_finish', '>=', $dateFrom);
                    if ($dateTo)   $sub->whereDate('h2.date_finish', '<=', $dateTo);
                });
            }
            if ($hasNameSearch) {
                $nameIds = $this->findStudentIdentifiersByName((string) $request->student_name);
                $query->where(function ($q) use ($request, $nameIds) {
                    $q->where('student_name', 'LIKE', '%' . $request->student_name . '%');
                    if (!empty($nameIds)) {
                        $q->orWhereIn('student_id', $nameIds);
                    }
                });
            }
            if ($hasShaklSearch) {
                $query->where('shakl', 'LIKE', '%' . $request->shakl_search . '%');
            }
            if ($request->filled('date_from')) {
                $query->whereDate('date_finish', '>=', $request->date_from);
            }
            if ($request->filled('date_to')) {
                $query->whereDate('date_finish', '<=', $request->date_to);
            }

            $results = $query->orderBy('student_id')->orderBy('fan_id')->orderBy('date_finish')->get();

            // Barcha student_id larni yig'ish va bulk query
            $studentIds = $results->pluck('student_id')->unique()->values()->toArray();

            $students = !empty($studentIds)
                ? Student::with('curriculum')->where(function ($q) use ($studentIds) {
                    $q->whereIn('hemis_id', $studentIds)
                      ->orWhereIn('student_id_number', $studentIds);
                })->get()
                : collect();

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
                // OSKI/Test: reason='quiz_result' bilan yangi yozuv
                // Mavzu retake: reason='absent'/'low_grade', lekin quiz_result_id bilan yozilgan
                $uploadedResultIds = StudentGrade::whereIn('quiz_result_id', $allResultIds)
                    ->whereNotNull('quiz_result_id')
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

            // 3) Groups va CurriculumSubject — jadval ma'lumotlarini oldindan yuklash
            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            $fanIds = $results->pluck('fan_id')->unique()->values()->toArray();

            $groupIds = $students->pluck('group_id')->unique()->toArray();
            $groups = !empty($groupIds)
                ? Group::whereIn('group_hemis_id', $groupIds)->get()->keyBy('group_hemis_id')
                : collect();
            $curriculumHemisIds = $groups->pluck('curriculum_hemis_id')->unique()->toArray();

            $curriculumSubjects = [];
            if (!empty($curriculumHemisIds) && !empty($fanIds)) {
                $csRows = CurriculumSubject::whereIn('curricula_hemis_id', $curriculumHemisIds)
                    ->whereIn('subject_id', $fanIds)
                    ->get(['curricula_hemis_id', 'subject_id', 'semester_code']);

                foreach ($csRows as $cs) {
                    $csKey = $cs->curricula_hemis_id . '|' . $cs->subject_id;
                    $curriculumSubjects[$csKey] = $cs->semester_code;
                }
            }

            // Student->group mapping va semester aniqlash (JN, MT va OSKI uchun umumiy)
            $studentGroupMap = [];
            $studentSubjectSemester = [];

            if ($groups->isNotEmpty()) {
                foreach ($students as $s) {
                    $grp = $groups[$s->group_id] ?? null;
                    if (!$grp) continue;
                    $studentGroupMap[$s->hemis_id] = $s->group_id;
                    foreach ($fanIds as $fId) {
                        $csK = $grp->curriculum_hemis_id . '|' . $fId;
                        $semC = $curriculumSubjects[$csK] ?? null;
                        if (!$semC) continue;
                        $studentSubjectSemester[$s->hemis_id . '|' . $fId] = $semC;
                    }
                }
            }

            // JN baholar — jurnal logikasi bilan bir xil hisoblash (schedule-based daily average)
            $excludedCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);
            $jnGrades = []; // student_hemis_id|subject_id => computed JN average (pre-computed)

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($studentGroupMap)) {
                // 1) JB jadval sanalarini batch olish (schedules jadvalidan)
                $jbScheduleData = [];
                $uniqueGroupIds = array_unique(array_values($studentGroupMap));
                $allSemCodes = array_unique(array_values($studentSubjectSemester));

                if (!empty($uniqueGroupIds) && !empty($allSemCodes)) {
                    $jbSchedRows = DB::table('schedules')
                        ->whereNull('deleted_at')
                        ->whereIn('group_id', $uniqueGroupIds)
                        ->whereIn('subject_id', $fanIds)
                        ->whereIn('semester_code', $allSemCodes)
                        ->whereNotIn('training_type_code', $excludedCodes)
                        ->whereNotIn('training_type_name', ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"])
                        ->whereNotNull('lesson_date')
                        ->select('group_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code')
                        ->orderBy('lesson_date')
                        ->orderBy('lesson_pair_code')
                        ->get();

                    foreach ($jbSchedRows as $sr) {
                        $schedKey = $sr->group_id . '|' . $sr->subject_id . '|' . $sr->semester_code;
                        $dateStr = \Carbon\Carbon::parse($sr->lesson_date)->format('Y-m-d');
                        $pairKey = $dateStr . '_' . $sr->lesson_pair_code;

                        if (!isset($jbScheduleData[$schedKey])) {
                            $jbScheduleData[$schedKey] = ['dates' => [], 'pairsPerDay' => [], 'datePairSet' => []];
                        }
                        if (!isset($jbScheduleData[$schedKey]['datePairSet'][$pairKey])) {
                            $jbScheduleData[$schedKey]['datePairSet'][$pairKey] = true;
                            $jbScheduleData[$schedKey]['pairsPerDay'][$dateStr] = ($jbScheduleData[$schedKey]['pairsPerDay'][$dateStr] ?? 0) + 1;
                        }
                        if (!in_array($dateStr, $jbScheduleData[$schedKey]['dates'])) {
                            $jbScheduleData[$schedKey]['dates'][] = $dateStr;
                        }
                    }
                    foreach ($jbScheduleData as &$sd) {
                        sort($sd['dates']);
                    }
                    unset($sd, $jbSchedRows);
                }

                // 2) JN baholarini olish (semester filtri, status/retake filtri — jurnal logikasi)
                $jnGradesGrouped = [];

                if (!empty($allSemCodes)) {
                    foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                        $gradeRows = DB::table('student_grades')
                            ->whereNull('deleted_at')
                            ->whereIn('student_hemis_id', $chunk)
                            ->whereIn('subject_id', $fanIds)
                            ->whereIn('semester_code', $allSemCodes)
                            ->whereNotIn('training_type_code', $excludedCodes)
                            ->whereNotNull('lesson_date')
                            ->select('student_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                            ->get();

                        foreach ($gradeRows as $gr) {
                            // Effective grade filtri (jurnal getEffectiveGrade logikasi bilan bir xil)
                            $effGrade = null;
                            if ($gr->grade !== null && (float) $gr->grade < 60 && $gr->retake_grade !== null) {
                                // ENG YUQORI QOIDA: asl baho 60 dan past + retake mavjud -> retake ustun
                                $effGrade = $gr->retake_grade;
                            } elseif ($gr->status === 'pending' && $gr->reason === 'low_grade' && $gr->grade !== null) {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'pending') {
                                continue;
                            } elseif ($gr->reason === 'absent' && $gr->grade === null) {
                                $effGrade = $gr->retake_grade !== null ? $gr->retake_grade : null;
                            } elseif ($gr->status === 'closed' && $gr->reason === 'teacher_victim' && $gr->grade == 0 && $gr->retake_grade === null) {
                                continue;
                            } elseif ($gr->status === 'recorded') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'closed') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->retake_grade !== null) {
                                $effGrade = $gr->retake_grade;
                            }
                            if ($effGrade === null) continue;

                            $gHemisId = $studentGroupMap[$gr->student_hemis_id] ?? null;
                            if (!$gHemisId) continue;

                            $schedKey = $gHemisId . '|' . $gr->subject_id . '|' . $gr->semester_code;
                            if (!isset($jbScheduleData[$schedKey])) continue;

                            $dateStr = \Carbon\Carbon::parse($gr->lesson_date)->format('Y-m-d');
                            $pairKey = $dateStr . '_' . $gr->lesson_pair_code;
                            if (!isset($jbScheduleData[$schedKey]['datePairSet'][$pairKey])) continue;

                            $gKey = $gr->student_hemis_id . '|' . $gr->subject_id;
                            $jnGradesGrouped[$gKey][$dateStr][$gr->lesson_pair_code] = (float) $effGrade;
                        }
                        unset($gradeRows);
                    }
                }

                // 3) JN o'rtachasini hisoblash (jurnal logikasi: kunlik o'rtacha -> umumiy o'rtacha)
                $cutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->format('Y-m-d');
                foreach ($studentSubjectSemester as $gKey => $semCode) {
                    [$hId, $fId] = explode('|', $gKey);
                    $gHemisId = $studentGroupMap[$hId] ?? null;
                    if (!$gHemisId) continue;

                    $schedKey = $gHemisId . '|' . $fId . '|' . $semCode;
                    $schedData = $jbScheduleData[$schedKey] ?? null;

                    if (!$schedData || empty($schedData['dates'])) continue;

                    $jnDailySum = 0;
                    $studentJnG = $jnGradesGrouped[$gKey] ?? [];
                    // Faqat cutoff sanagacha bo'lgan kunlarni hisoblash
                    $datesForAvg = array_filter($schedData['dates'], fn($d) => $d <= $cutoffDate);
                    $totalJnDays = count($datesForAvg);

                    foreach ($datesForAvg as $date) {
                        $dayG = $studentJnG[$date] ?? [];
                        $pairsInDay = $schedData['pairsPerDay'][$date] ?? 1;
                        $gradeSum = array_sum($dayG);
                        $jnDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    }

                    $jnGrades[$gKey] = $totalJnDays > 0
                        ? round($jnDailySum / $totalJnDays, 0, PHP_ROUND_HALF_UP)
                        : 0;
                }
            }

            // MT baholar — jurnal logikasi bilan bir xil hisoblash (schedule-based daily average)
            $mtGrades = []; // student_hemis_id|subject_id => computed MT average (pre-computed)

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($studentGroupMap)) {
                // 2) MT jadval sanalarini batch olish (schedules jadvalidan)
                $mtScheduleData = [];
                $uniqueGroupIds = array_unique(array_values($studentGroupMap));

                if (!empty($uniqueGroupIds)) {
                    $mtSchedRows = DB::table('schedules')
                        ->whereNull('deleted_at')
                        ->whereIn('group_id', $uniqueGroupIds)
                        ->whereIn('subject_id', $fanIds)
                        ->where('training_type_code', 99)
                        ->whereNotNull('lesson_date')
                        ->select('group_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code')
                        ->orderBy('lesson_date')
                        ->orderBy('lesson_pair_code')
                        ->get();

                    foreach ($mtSchedRows as $sr) {
                        $schedKey = $sr->group_id . '|' . $sr->subject_id . '|' . $sr->semester_code;
                        $dateStr = \Carbon\Carbon::parse($sr->lesson_date)->format('Y-m-d');
                        $pairKey = $dateStr . '_' . $sr->lesson_pair_code;

                        if (!isset($mtScheduleData[$schedKey])) {
                            $mtScheduleData[$schedKey] = ['dates' => [], 'pairsPerDay' => [], 'datePairSet' => []];
                        }
                        if (!isset($mtScheduleData[$schedKey]['datePairSet'][$pairKey])) {
                            $mtScheduleData[$schedKey]['datePairSet'][$pairKey] = true;
                            $mtScheduleData[$schedKey]['pairsPerDay'][$dateStr] = ($mtScheduleData[$schedKey]['pairsPerDay'][$dateStr] ?? 0) + 1;
                        }
                        if (!in_array($dateStr, $mtScheduleData[$schedKey]['dates'])) {
                            $mtScheduleData[$schedKey]['dates'][] = $dateStr;
                        }
                    }
                    foreach ($mtScheduleData as &$sd) {
                        sort($sd['dates']);
                    }
                    unset($sd, $mtSchedRows);
                }

                // 3) MT baholarini olish (semester filtri, status/retake filtri — jurnal logikasi)
                $mtGradesGrouped = [];
                $allSemCodes = array_unique(array_values($studentSubjectSemester));

                if (!empty($allSemCodes)) {
                    foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                        $gradeRows = DB::table('student_grades')
                            ->whereNull('deleted_at')
                            ->whereIn('student_hemis_id', $chunk)
                            ->whereIn('subject_id', $fanIds)
                            ->whereIn('semester_code', $allSemCodes)
                            ->whereNotIn('training_type_code', [100, 101, 102, 103])
                            ->whereNotNull('lesson_date')
                            ->select('student_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason')
                            ->get();

                        foreach ($gradeRows as $gr) {
                            // Effective grade filtri (jurnal getEffectiveGrade logikasi bilan bir xil)
                            $effGrade = null;
                            if ($gr->grade !== null && (float) $gr->grade < 60 && $gr->retake_grade !== null) {
                                // ENG YUQORI QOIDA: asl baho 60 dan past + retake mavjud -> retake ustun
                                $effGrade = $gr->retake_grade;
                            } elseif ($gr->status === 'pending' && $gr->reason === 'low_grade' && $gr->grade !== null) {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'pending') {
                                continue;
                            } elseif ($gr->reason === 'absent' && $gr->grade === null) {
                                $effGrade = $gr->retake_grade !== null ? $gr->retake_grade : null;
                            } elseif ($gr->status === 'closed' && $gr->reason === 'teacher_victim' && $gr->grade == 0 && $gr->retake_grade === null) {
                                continue;
                            } elseif ($gr->status === 'recorded') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->status === 'closed') {
                                $effGrade = $gr->grade;
                            } elseif ($gr->retake_grade !== null) {
                                $effGrade = $gr->retake_grade;
                            }
                            if ($effGrade === null) continue;

                            $gHemisId = $studentGroupMap[$gr->student_hemis_id] ?? null;
                            if (!$gHemisId) continue;

                            $schedKey = $gHemisId . '|' . $gr->subject_id . '|' . $gr->semester_code;
                            if (!isset($mtScheduleData[$schedKey])) continue;

                            $dateStr = \Carbon\Carbon::parse($gr->lesson_date)->format('Y-m-d');
                            $pairKey = $dateStr . '_' . $gr->lesson_pair_code;
                            if (!isset($mtScheduleData[$schedKey]['datePairSet'][$pairKey])) continue;

                            $gKey = $gr->student_hemis_id . '|' . $gr->subject_id;
                            $mtGradesGrouped[$gKey][$dateStr][$gr->lesson_pair_code] = (float) $effGrade;
                        }
                        unset($gradeRows);
                    }
                }

                // 4) Manual MT baholarini olish (lesson_date IS NULL — override)
                $manualMtGrades = [];
                foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                    $manualRows = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->whereIn('student_hemis_id', $chunk)
                        ->whereIn('subject_id', $fanIds)
                        ->where('training_type_code', 99)
                        ->whereNull('lesson_date')
                        ->whereNotNull('grade')
                        ->select('student_hemis_id', 'subject_id', 'grade')
                        ->get();
                    foreach ($manualRows as $mr) {
                        $manualMtGrades[$mr->student_hemis_id . '|' . $mr->subject_id] = (float) $mr->grade;
                    }
                    unset($manualRows);
                }

                // 5) MT o'rtachasini hisoblash (jurnal logikasi: kunlik o'rtacha -> umumiy o'rtacha)
                foreach ($studentSubjectSemester as $gKey => $semCode) {
                    [$hId, $fId] = explode('|', $gKey);
                    $gHemisId = $studentGroupMap[$hId] ?? null;
                    if (!$gHemisId) continue;

                    $schedKey = $gHemisId . '|' . $fId . '|' . $semCode;
                    $schedData = $mtScheduleData[$schedKey] ?? null;

                    if (!$schedData || empty($schedData['dates'])) {
                        if (isset($manualMtGrades[$gKey])) {
                            $mtGrades[$gKey] = round($manualMtGrades[$gKey], 0, PHP_ROUND_HALF_UP);
                        }
                        continue;
                    }

                    $mtDailySum = 0;
                    $studentMtG = $mtGradesGrouped[$gKey] ?? [];
                    $totalMtDays = count($schedData['dates']);

                    foreach ($schedData['dates'] as $date) {
                        $dayG = $studentMtG[$date] ?? [];
                        $pairsInDay = $schedData['pairsPerDay'][$date] ?? 1;
                        $gradeSum = array_sum($dayG);
                        $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    }

                    $mt = $totalMtDays > 0
                        ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                        : 0;

                    // Manual MT override (jurnal logikasi bilan bir xil)
                    if (isset($manualMtGrades[$gKey])) {
                        $mt = round($manualMtGrades[$gKey], 0, PHP_ROUND_HALF_UP);
                    }

                    $mtGrades[$gKey] = $mt;
                }
            }

            // OSKI/Test baholar — jurnal logikasi: MAX(grade), faqat JORIY semestr, YN turi bo'yicha alohida
            $oskiGrades = []; // hemis|subject_id|semester|YN_turi => max grade
            $currentSemCodes = $students->pluck('semester_code')->filter()->unique()->values()->toArray();

            if (!empty($studentHemisIds) && !empty($fanIds) && !empty($currentSemCodes)) {
                foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                    $oskiRows = DB::table('student_grades')
                        ->whereNull('deleted_at')
                        ->whereIn('student_hemis_id', $chunk)
                        ->whereIn('subject_id', $fanIds)
                        ->whereIn('semester_code', $currentSemCodes)
                        ->whereIn('training_type_code', [101, 102])
                        ->whereNotNull('grade')
                        ->select('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'training_type_code', DB::raw('MAX(grade) as grade'))
                        ->groupBy('student_hemis_id', 'subject_id', 'subject_name', 'semester_code', 'training_type_code')
                        ->get();

                    foreach ($oskiRows as $row) {
                        $ttype = (int) $row->training_type_code === 101 ? 'O' : 'T';
                        $k = $row->student_hemis_id . '|' . $row->subject_id . '|' . $row->semester_code . '|' . $ttype;
                        $oskiGrades[$k] = (float) $row->grade;
                        $kName = $row->student_hemis_id . '|' . mb_strtolower($row->subject_name) . '|' . $row->semester_code . '|' . $ttype;
                        if (!isset($oskiGrades[$kName])) {
                            $oskiGrades[$kName] = (float) $row->grade;
                        }
                    }
                    unset($oskiRows);
                }
            }

            // Mavzu (N-mavzu) shakldagi natijalar uchun jurnal holati
            $mavzuStates = $this->buildMavzuStates($results, $studentLookup, $students);

            // 4) MarkingSystemScore larni bulk yuklash (N+1 query oldini olish)
            $markingSystemCodes = $students
                ->map(fn($s) => optional($s->curriculum)->marking_system_code)
                ->filter()
                ->unique()
                ->values()
                ->toArray();

            $markingScoreLookup = [];
            if (!empty($markingSystemCodes)) {
                $allScores = MarkingSystemScore::whereIn('marking_system_code', $markingSystemCodes)->get();
                foreach ($allScores as $score) {
                    $markingScoreLookup[$score->marking_system_code] = $score;
                }
            }
            $defaultScore = MarkingSystemScore::getDefault();

            // Student hemis_id -> MarkingSystemScore mapping
            $studentScoreLookup = [];
            foreach ($students as $s) {
                $code = optional($s->curriculum)->marking_system_code;
                $studentScoreLookup[$s->hemis_id] = ($code && isset($markingScoreLookup[$code]))
                    ? $markingScoreLookup[$code]
                    : $defaultScore;
            }

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

                // Quiz semestri (Moodle quiz nomidan) talabaning LMS dagi
                // haqiqiy semestriga mos kelmasligini aniqlash — bunday qatorlar
                // jadvalda qizil belgilanadi (boshqa kurs/semestr natijasi).
                $quizSemNum = null;
                if (!empty($result->semester) && preg_match('/(\d+)/', (string) $result->semester, $qsm)) {
                    $quizSemNum = (int) $qsm[1];
                }
                $studentSemNum = null;
                if ($student && !empty($student->semester_name)
                    && preg_match('/(\d+)/', (string) $student->semester_name, $ssm)) {
                    $studentSemNum = (int) $ssm[1];
                }
                $semesterMismatch = ($quizSemNum !== null && $studentSemNum !== null
                    && $quizSemNum !== $studentSemNum);

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
                    $curriculumSubjects, $groups,
                    $testTypes, $oskiTypes,
                    $studentScoreLookup, $defaultScore, $mavzuStates
                );

                $rowNum++;
                $studentGroup = ($student && isset($groups[$student->group_id])) ? $groups[$student->group_id] : null;
                $data[] = [
                    'id' => $result->id,
                    'row_num' => $rowNum,
                    'student_id' => $result->student_id,
                    'student_hemis_id' => $student ? $student->hemis_id : null,
                    'group_local_id' => $studentGroup ? $studentGroup->id : null,
                    'semester_code' => $student ? $student->semester_code : null,
                    'full_name' => $student ? $student->full_name : $result->student_name,
                    'faculty' => $student ? $student->department_name : '-',
                    'direction' => $student ? $student->specialty_name : '-',
                    'kurs' => $kurs ? $kurs . '-kurs' : '-',
                    'semester' => $semNum ? $semNum . '-sem' : ($semLabel ?: '-'),
                    'semester_mismatch' => $semesterMismatch,
                    'student_semester' => $studentSemNum ? $studentSemNum . '-sem' : '-',
                    'group' => $student ? $student->group_name : '-',
                    'fan_name' => $result->fan_name,
                    'fan_id' => $result->fan_id,
                    'yn_turi' => $ynTuri,
                    'shakl' => $result->shakl,
                    'grade' => $result->grade,
                    'date' => $result->date_finish ? $result->date_finish->format('d.m.Y') : '',
                    'xulosa' => $xulosa['text'],
                    'xulosa_code' => $xulosa['code'],
                    'jn_avg' => $xulosa['jn_avg'],
                    'mt_avg' => $xulosa['mt_avg'],
                    'oski_avg' => $xulosa['oski_avg'],
                ];
            }

            return response()->json([
                'data' => $data,
                'total' => count($data),
            ]);
        } catch (\Throwable $e) {
            Log::error('tartibgaSol xatolik: ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            return response()->json([
                'error' => $e->getMessage(),
                'data' => [],
                'total' => 0,
            ], 500);
        }
    }

    /**
     * Har bir natija uchun xulosa hisoblash.
     * MarkingSystemScore orqali JN/MT/OSKI chegaralarini aniqlaydi.
     */
    private function calculateXulosa(
        $result, $student, $ynTuri,
        $uploadedResultIds, $duplicateMap,
        $jnGrades, $mtGrades, $oskiGrades,
        $curriculumSubjects, $groups,
        $testTypes, $oskiTypes,
        $studentScoreLookup = [], $defaultScore = null,
        $mavzuStates = []
    ) {
        $jnAvg = null;
        $mtAvg = null;
        $oskiAvg = null;

        // 1) Talaba topilmadi
        if (!$student) {
            return ['code' => 'no_student', 'text' => 'Talaba topilmadi', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // Mavzu formatini oldindan aniqlash (uploaded tekshiruvi va existingOskiGrade uchun farq qiladi)
        $isMavzuShakl = $result->shakl && preg_match('/^\d+-mavzu$/i', $result->shakl);

        // 1.5) Aynan shu Moodle natija (quiz_result_id) jurnalga yuklangan
        // Mavzu uchun: shu sanadagi student_grade da retake_grade saqlangan
        // OSKI/Test uchun: reason='quiz_result' bilan yangi yozuv yaratilgan
        if (isset($uploadedResultIds[$result->id])) {
            if ($isMavzuShakl) {
                return ['code' => 'mavzu_uploaded', 'text' => 'Jurnalga yuklangan: ' . $result->shakl, 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            return ['code' => 'uploaded', 'text' => 'Jurnalga yuklangan', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 1.6) OSKI/Test uchun: jurnalda shu YN turi (OSKI yoki Test) bahosi bor — informativ
        // (mavzuga aloqasi yo'q, mavzu JN ustuniga tushadi)
        // Faqat talabaning JORIY semestri va aynan shu YN turi bo'yicha tekshiriladi
        if (!$isMavzuShakl) {
            $existingOskiGrade = null;
            $currentSem = $student->semester_code;
            $ttype = $ynTuri === 'OSKI' ? 'O' : ($ynTuri === 'Test' ? 'T' : null);
            if ($ttype && $currentSem && $result->fan_id) {
                $gradeCheckKey = $student->hemis_id . '|' . $result->fan_id . '|' . $currentSem . '|' . $ttype;
                if (isset($oskiGrades[$gradeCheckKey])) {
                    $existingOskiGrade = $oskiGrades[$gradeCheckKey];
                }
            }
            if ($existingOskiGrade === null && $ttype && $currentSem && $result->fan_name) {
                $gradeCheckKeyName = $student->hemis_id . '|' . mb_strtolower($result->fan_name) . '|' . $currentSem . '|' . $ttype;
                if (isset($oskiGrades[$gradeCheckKeyName])) {
                    $existingOskiGrade = $oskiGrades[$gradeCheckKeyName];
                }
            }
            if ($existingOskiGrade !== null) {
                return ['code' => 'has_other_grade', 'text' => 'Bahosi bor (' . $existingOskiGrade . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $existingOskiGrade];
            }
        }

        // 2) Mavzu formati uchun maxsus xulosa
        if ($isMavzuShakl) {
            if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
                return ['code' => 'bad_grade', 'text' => 'Baho noto\'g\'ri', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }

            // Fan o'quv rejada bormi tekshirish
            $group = $groups[$student->group_id] ?? null;
            if ($group) {
                $csKey = $group->curriculum_hemis_id . '|' . $result->fan_id;
                if (!isset($curriculumSubjects[$csKey])) {
                    return ['code' => 'not_in_curriculum', 'text' => 'Fan o\'quv rejada yo\'q', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
                }
            } else {
                return ['code' => 'no_student', 'text' => 'Talaba guruhi topilmadi', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }

            // Jurnaldagi mavzu holatini ko'rsatish (NB / Baho / Retake / Bo'sh)
            preg_match('/^(\d+)-mavzu$/i', $result->shakl, $mavzuMatch);
            $mavzuN = (int) ($mavzuMatch[1] ?? 0);
            $mavzuKey = $student->hemis_id . '|' . $result->fan_id . '|' . $mavzuN;
            $state = $mavzuStates[$mavzuKey] ?? null;

            if ($state) {
                if ($state['type'] === 'retake') {
                    $orig = $state['grade'] !== null ? round($state['grade']) : null;
                    $rt = round($state['retake']);
                    $txt = $orig !== null
                        ? 'Retake bor: ' . $result->shakl . ' (' . $orig . '→' . $rt . ')'
                        : 'Retake bor: ' . $result->shakl . ' (' . $rt . ')';
                    return ['code' => 'mavzu_grade', 'text' => $txt, 'jn_avg' => null, 'mt_avg' => null, 'oski_avg' => null];
                }
                if ($state['type'] === 'grade') {
                    return ['code' => 'mavzu_grade', 'text' => 'Baho bor: ' . $result->shakl . ' (' . round($state['grade']) . ')', 'jn_avg' => null, 'mt_avg' => null, 'oski_avg' => null];
                }
                if ($state['type'] === 'nb') {
                    return ['code' => 'mavzu_nb', 'text' => 'NB bor: ' . $result->shakl, 'jn_avg' => null, 'mt_avg' => null, 'oski_avg' => null];
                }
            }

            // Jurnalda yozuv yo'q — yuklash mumkin
            return ['code' => 'mavzu', 'text' => 'Mavzu retake: ' . $result->shakl, 'jn_avg' => null, 'mt_avg' => null, 'oski_avg' => null];
        }

        // 3) Quiz turi noma'lum (OSKI yoki YN test emas va mavzu ham emas)
        if ($ynTuri === '-') {
            return ['code' => 'unknown_type', 'text' => 'Quiz turi noma\'lum', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 4) Baho noto'g'ri
        if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
            return ['code' => 'bad_grade', 'text' => 'Baho noto\'g\'ri', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
        }

        // 5) Yuklash uchun yaroqsiz urinish — faqat 1-urinish va 2-urinish (qo'shimcha
        //    matn bilan ham) yuklanadi. 3+ urinish "yaroqsiz" deb belgilanadi.
        $attemptNumXulosa = 1;
        if (preg_match('/^\s*(\d+)-urinish/iu', (string) $result->shakl, $um)) {
            $attemptNumXulosa = (int) $um[1];
        }
        if ($result->shakl && !in_array($attemptNumXulosa, [1, 2], true)) {
            return ['code' => 'not_first', 'text' => '1/2-urinish emas', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
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

        // 8) Jadvalda yo'q (CurriculumSubject da fan mavjudligini tekshirish)
        $group = $groups[$student->group_id] ?? null;
        if ($group) {
            $csKey = $group->curriculum_hemis_id . '|' . $result->fan_id;
            if (!isset($curriculumSubjects[$csKey])) {
                return ['code' => 'not_in_curriculum', 'text' => 'Jadvalda yo\'q', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        }

        // 8) JN va MT o'rtachalarini hisoblash
        $gradeKey = $student->hemis_id . '|' . $result->fan_id;

        // JN o'rtachasi (jurnal logikasi bilan oldindan hisoblangan — schedule-based daily average)
        if (isset($jnGrades[$gradeKey])) {
            $jnAvg = $jnGrades[$gradeKey];
        }

        // MT o'rtachasi (jurnal logikasi bilan oldindan hisoblangan)
        if (isset($mtGrades[$gradeKey])) {
            $mtAvg = $mtGrades[$gradeKey];
        }

        // OSKI bahosi (jurnal logikasi bilan oldindan hisoblangan — MAX)
        if (isset($oskiGrades[$gradeKey])) {
            $oskiAvg = $oskiGrades[$gradeKey];
        }

        // 9) YNga ruxsat tekshiruvi (MarkingSystemScore orqali)
        $markingScore = $studentScoreLookup[$student->hemis_id]
            ?? $defaultScore
            ?? MarkingSystemScore::getDefault();
        $jnThreshold = $markingScore->effectiveLimit('jn');
        $mtThreshold = $markingScore->effectiveLimit('mt');
        $oskiThreshold = $markingScore->effectiveLimit('oski');

        if ($ynTuri === 'OSKI') {
            // OSKI uchun: JN >= threshold, MT >= threshold
            if ($jnThreshold > 0 && $jnAvg !== null && $jnAvg < $jnThreshold) {
                return ['code' => 'jn_low', 'text' => 'JN yetarli emas (' . $jnAvg . '<' . $jnThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($mtThreshold > 0 && $mtAvg !== null && $mtAvg < $mtThreshold) {
                return ['code' => 'mt_low', 'text' => 'MT yetarli emas (' . $mtAvg . '<' . $mtThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        } elseif ($ynTuri === 'Test') {
            // YN test uchun: JN >= threshold, MT >= threshold, OSKI >= threshold
            if ($jnThreshold > 0 && $jnAvg !== null && $jnAvg < $jnThreshold) {
                return ['code' => 'jn_low', 'text' => 'JN yetarli emas (' . $jnAvg . '<' . $jnThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($mtThreshold > 0 && $mtAvg !== null && $mtAvg < $mtThreshold) {
                return ['code' => 'mt_low', 'text' => 'MT yetarli emas (' . $mtAvg . '<' . $mtThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
            if ($oskiThreshold > 0 && $oskiAvg !== null && $oskiAvg < $oskiThreshold) {
                return ['code' => 'oski_low', 'text' => 'OSKI yetarli emas (' . $oskiAvg . '<' . $oskiThreshold . ')', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
            }
        }

        // 10) Hammasi joyida
        return ['code' => 'ok', 'text' => 'Yuklasa bo\'ladi', 'jn_avg' => $jnAvg, 'mt_avg' => $mtAvg, 'oski_avg' => $oskiAvg];
    }

    /**
     * Mavzu shakldagi natijalar uchun jurnal holatini bulk yuklash.
     * Har bir (student_hemis_id, fan_id, mavzu_n) uchun state qaytaradi:
     *   ['type' => 'retake'|'grade'|'nb'|'empty', 'grade' => ?, 'retake' => ?]
     * Mavzu raqami — talabaning JORIY semestridagi xronologik amaliy darslar tartibi.
     */
    private function buildMavzuStates($results, array $studentLookup, $students): array
    {
        $states = [];

        // 1) Mavzu shakldagi natijalar uchun (student, fan, group, semester) ro'yxat
        $pairs = []; // hemis|fan_id => ['hemis'=>, 'fan'=>, 'group'=>, 'sem'=>]
        foreach ($results as $r) {
            if (!$r->shakl || !preg_match('/^\d+-mavzu$/i', $r->shakl)) continue;
            $student = $studentLookup[$r->student_id] ?? null;
            if (!$student || !$r->fan_id || !$student->semester_code || !$student->group_id) continue;
            $key = $student->hemis_id . '|' . $r->fan_id;
            if (!isset($pairs[$key])) {
                $pairs[$key] = [
                    'hemis' => $student->hemis_id,
                    'fan'   => $r->fan_id,
                    'group' => $student->group_id,
                    'sem'   => $student->semester_code,
                ];
            }
        }

        if (empty($pairs)) return $states;

        $excludedTypes = [11, 17, 99, 100, 101, 102, 103];

        // 2) Schedules — har (group, fan, semester) uchun xronologik dars sanalari
        $groupIds = array_unique(array_column($pairs, 'group'));
        $fanIds   = array_unique(array_column($pairs, 'fan'));
        $semCodes = array_unique(array_column($pairs, 'sem'));

        $scheduleRows = DB::table('schedules')
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', $excludedTypes)
            ->whereIn('group_id', $groupIds)
            ->whereIn('subject_id', $fanIds)
            ->whereIn('semester_code', $semCodes)
            ->select('group_id', 'subject_id', 'semester_code', DB::raw('DATE(lesson_date) as d'))
            ->distinct()
            ->orderBy('d')
            ->get();

        $datesByTriple = []; // group|fan|sem => [d1, d2, ...]
        foreach ($scheduleRows as $row) {
            $k = $row->group_id . '|' . $row->subject_id . '|' . $row->semester_code;
            $datesByTriple[$k][] = $row->d;
        }

        // 3) student_grades — hozirgi semestr, mavzu rolidagi yozuvlar
        $hemisIds = array_unique(array_column($pairs, 'hemis'));
        $gradeRows = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereNotIn('training_type_code', $excludedTypes)
            ->whereIn('student_hemis_id', $hemisIds)
            ->whereIn('subject_id', $fanIds)
            ->whereIn('semester_code', $semCodes)
            ->select('student_hemis_id', 'subject_id', 'semester_code', 'lesson_date', 'grade', 'retake_grade', 'reason', 'status')
            ->get();

        $gradesByDate = []; // hemis|fan|sem|date => [rows]
        foreach ($gradeRows as $g) {
            $date = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            $k = $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code . '|' . $date;
            $gradesByDate[$k][] = $g;
        }

        // 4) Har bir pair uchun mavzu_n ni state ga aylantirish
        foreach ($results as $r) {
            if (!$r->shakl || !preg_match('/^(\d+)-mavzu$/i', $r->shakl, $m)) continue;
            $student = $studentLookup[$r->student_id] ?? null;
            if (!$student || !$r->fan_id) continue;

            $mavzuN = (int) $m[1];
            $tripleKey = $student->group_id . '|' . $r->fan_id . '|' . $student->semester_code;
            $dates = $datesByTriple[$tripleKey] ?? [];

            if (!isset($dates[$mavzuN - 1])) continue;
            $targetDate = $dates[$mavzuN - 1];

            $gKey = $student->hemis_id . '|' . $r->fan_id . '|' . $student->semester_code . '|' . $targetDate;
            $rows = $gradesByDate[$gKey] ?? [];

            if (empty($rows)) continue;

            $maxGrade = null;
            $maxRetake = null;
            $hasNb = false;

            foreach ($rows as $gr) {
                if ($gr->reason === 'absent' && $gr->grade === null && $gr->retake_grade === null) {
                    $hasNb = true;
                    continue;
                }
                if ($gr->grade !== null) {
                    $maxGrade = $maxGrade === null ? $gr->grade : max($maxGrade, $gr->grade);
                }
                if ($gr->retake_grade !== null) {
                    $maxRetake = $maxRetake === null ? $gr->retake_grade : max($maxRetake, $gr->retake_grade);
                }
            }

            $stateKey = $student->hemis_id . '|' . $r->fan_id . '|' . $mavzuN;
            if ($maxRetake !== null) {
                $states[$stateKey] = ['type' => 'retake', 'grade' => $maxGrade, 'retake' => $maxRetake];
            } elseif ($maxGrade !== null) {
                $states[$stateKey] = ['type' => 'grade', 'grade' => $maxGrade, 'retake' => null];
            } elseif ($hasNb) {
                $states[$stateKey] = ['type' => 'nb', 'grade' => null, 'retake' => null];
            }
        }

        return $states;
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

        if ($request->filled('shakl_search')) {
            $query->where('shakl', 'LIKE', '%' . $request->shakl_search . '%');
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
            'subject_overrides' => 'nullable|array',
            'semester_overrides' => 'nullable|array',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        // subject_overrides — manual o'zgartirilgan fan_id larni saqlovchi map
        // Format: { "<original_fan_id>_<group_id>": <new_subject_id> }
        $subjectOverrides = $request->input('subject_overrides', []);
        // semester_overrides — modalda qo'lda tanlangan semestr kodlari
        // Format: { "<original_fan_id>_<group_id>": <semester_code> }
        $semesterOverrides = $request->input('semester_overrides', []);

        $successCount = 0;
        $errors = [];
        $warnings = [];
        $duplicateTracker = [];
        // Variant (a/b/c) fanlar uchun jurnal roster keshi: "subjectId|semester" => [hemis_id => true]
        $variantRosterCache = [];

        // 4+ qarz (kursdan qoldirilgan) talabalarni aniqlab olamiz — bunday
        // talabalarga OSKI/Test bahosi yuklanmaydi (YN kunini belgilash
        // sahifasidagi qizil "4 tadan ortiq qarz" mantig'i bilan bir xil).
        $heldBackMap = $this->computeHeldBackDebts(
            $results->pluck('student_id')->filter()->unique()->values()->all()
        );

        foreach ($results as $result) {
            $rowInfo = [
                'id' => $result->id,
                'attempt_id' => $result->attempt_id,
                'student_id' => $result->student_id,
                'student_name' => $result->student_name,
                'fan_name' => $result->fan_name,
                'grade' => $result->grade,
            ];

            // YN turi override (modaldan) — "mavzu_N" bo'lsa, mavzu retake sifatida yuklanadi
            // (NB shakl yoki noaniq quiz_type uchun foydalanuvchi qo'lda tanlagan bo'ladi)
            $ynTuriOverridesAll = $request->input('yn_turi_overrides', []);
            $studentForKey = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();
            $ynOverrideKey = $result->fan_id . '_' . ($studentForKey->group_id ?? '');
            $ynManualOverride = $ynTuriOverridesAll[$ynOverrideKey] ?? null;

            if ($ynManualOverride && preg_match('/^mavzu_(\d+)$/i', $ynManualOverride, $mvm)) {
                $this->uploadMavzuRetake($result, $rowInfo, $successCount, $errors, $subjectOverrides, (int) $mvm[1]);
                continue;
            }

            // Mavzu formatini aniqlash — shakl "N-mavzu" bo'lsa, mavzu retake sifatida yuklanadi
            $isMavzuShakl = $result->shakl && preg_match('/^\d+-mavzu$/i', $result->shakl);
            if ($isMavzuShakl) {
                $this->uploadMavzuRetake($result, $rowInfo, $successCount, $errors, $subjectOverrides);
                continue;
            }

            // 1-urinish va 2-urinish (qo'shimcha bilan ham) yuklanadi. Shakl boshidagi
            // "N-urinish" raqamiga qarab attempt aniqlanadi.
            $shaklRaw = (string) ($result->shakl ?? '');
            $attemptNum = 1;
            if (preg_match('/^\s*(\d+)-urinish/iu', $shaklRaw, $um)) {
                $attemptNum = (int) $um[1];
            }
            if (!$request->input('skip_shakl_filter') && !in_array($attemptNum, [1, 2], true)) {
                $rowInfo['error'] = "Faqat 1-urinish va 2-urinish yuklanadi (hozirgi: {$shaklRaw})";
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

            // Semestr override (modaldan) — foydalanuvchi to'g'ri semestrni tanlagan
            $semOverrideKey = $result->fan_id . '_' . ($student->group_id ?? '');
            $semOverride = $semesterOverrides[$semOverrideKey] ?? null;

            // Semestr filtri — natija semestri talaba joriy semestriga mos kelmasa
            // (masalan 5-semestr natijasi 6-semestrga, yoki aksincha) — yuklamaymiz.
            // Diagnostika jadvalidagi SEMESTR ustuni shu mantiq asosida.
            // Foydalanuvchi modalda semestrni qo'lda tanlagan bo'lsa — bu tekshiruv
            // o'tkazib yuboriladi (override = aniq qaror).
            if ($semOverride === null) {
                $resultSemNum = null;
                if (!empty($result->semester) && preg_match('/(\d+)/', (string) $result->semester, $sm)) {
                    $resultSemNum = (int) $sm[1];
                }
                $studentSemNum = null;
                if (!empty($student->semester_name) && preg_match('/(\d+)/', (string) $student->semester_name, $ssm)) {
                    $studentSemNum = (int) $ssm[1];
                }
                if ($resultSemNum !== null && $studentSemNum !== null && $resultSemNum !== $studentSemNum) {
                    $rowInfo['error'] = "Semestr mos kelmadi: natija {$resultSemNum}-semestr, talaba {$studentSemNum}-semestrda — yuklanmadi";
                    $errors[] = $rowInfo;
                    continue;
                }
            }

            $group = Group::where('group_hemis_id', $student->group_id)->first();
            if (!$group) {
                $rowInfo['error'] = "Talaba guruhida guruh topilmadi";
                $errors[] = $rowInfo;
                continue;
            }

            // Fan uchun CurriculumSubject topish (override bilan)
            // Override mavjud bo'lsa — manual tanlangan subject_id ishlatiladi
            $overrideKey = $result->fan_id . '_' . ($student->group_id ?? '');
            $targetFanId = $subjectOverrides[$overrideKey] ?? $result->fan_id;

            $subject = null;
            if ($targetFanId && $group) {
                $subject = CurriculumSubject::where('subject_id', $targetFanId)
                    ->where('curricula_hemis_id', $group->curriculum_hemis_id)
                    ->first();
            }
            if (!$subject && $targetFanId) {
                $subject = CurriculumSubject::where('subject_id', $targetFanId)->first();
            }
            if (!$subject) {
                $rowInfo['error'] = "Fan topilmadi (fan_id: {$targetFanId})";
                $errors[] = $rowInfo;
                continue;
            }

            // Semester — override bo'lsa foydalanuvchi tanlagan semestr, aks holda
            // talabaning hozirgi semestri (jurnal shu semestrni ko'rsatadi).
            $semesterCode = $semOverride ?? $student->semester_code;
            $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
                ->where('code', $semesterCode)
                ->first();

            // Variant fan tekshiruvi: fan nomi (a)/(b)/(c) bilan tugasa — bu
            // subgrouplarga bo'lingan fan. Moodle faqat fan nomini yuboradi,
            // shuning uchun talaba aynan tanlangan variantda (jurnal bo'yicha)
            // bor-yo'qligini tekshiramiz. Jurnal roster JournalController bilan
            // bir xil: student_subjects YOKI student_grades (subject_id + semestr).
            if (preg_match('/\(([a-zA-Zа-яА-Я])\)\s*$/u', (string) $subject->subject_name)) {
                $rosterKey = $subject->subject_id . '|' . $semesterCode;
                if (!isset($variantRosterCache[$rosterKey])) {
                    $roster = [];
                    foreach (DB::table('student_subjects')
                        ->where('subject_id', $subject->subject_id)
                        ->where('semester_id', $semesterCode)
                        ->pluck('student_hemis_id') as $hid) {
                        $roster[(string) $hid] = true;
                    }
                    foreach (DB::table('student_grades')
                        ->where('subject_id', $subject->subject_id)
                        ->where('semester_code', $semesterCode)
                        ->whereNull('deleted_at')
                        ->distinct()
                        ->pluck('student_hemis_id') as $hid) {
                        $roster[(string) $hid] = true;
                    }
                    $variantRosterCache[$rosterKey] = $roster;
                }
                if (!isset($variantRosterCache[$rosterKey][(string) $student->hemis_id])) {
                    $rowInfo['error'] = "Talaba jurnalda \"{$subject->subject_name}\" variantida yo'q — fanning boshqa shaklida (variant) bo'lishi mumkin";
                    $warnings[] = $rowInfo;
                    continue;
                }
            }

            $existing = StudentGrade::where('quiz_result_id', $result->id)->first();
            if ($existing) {
                $rowInfo['error'] = "Bu natija avval yuklangan";
                $errors[] = $rowInfo;
                continue;
            }

            // Dublikat tekshirish (training_type ham hisobga olinadi — bir talaba
            // bir fandan OSKI va Test ikkalasini ham topshirgan bo'lishi mumkin)
            $dedupTypeHint = '';
            if (in_array($result->quiz_type, ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'])) {
                $dedupTypeHint = 'oski';
            } elseif (in_array($result->quiz_type, ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'])) {
                $dedupTypeHint = 'test';
            }
            $key = $result->student_id . '_' . $result->fan_id . '_' . $dedupTypeHint;
            if (isset($duplicateTracker[$key])) {
                $rowInfo['error'] = "Dublikat: bu talaba+fan+tur juftligi takrorlangan";
                $errors[] = $rowInfo;
                continue;
            }
            $duplicateTracker[$key] = $result->id;

            // Quiz type dan training_type_code va name aniqlash
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
            $shaklLower = mb_strtolower($result->shakl ?? '');
            $ynTuriOverrides = $request->input('yn_turi_overrides', []);
            $ynOverrideKey = $result->fan_id . '_' . ($student->group_id ?? '');
            $manualYnTuri = $ynTuriOverrides[$ynOverrideKey] ?? null;

            // Shakl (urinish) override — modaldan foydalanuvchi 12 / 12a / 12b
            // ni qo'lda tanlagan bo'lsa, attempt shu tanlovga qarab yoziladi.
            // Tanlanmagan bo'lsa Moodle shaklidan aniqlangan $attemptNum ishlatiladi.
            $attemptOverrides = $request->input('attempt_overrides', []);
            $attemptOverrideRaw = $attemptOverrides[$ynOverrideKey] ?? null;
            $effectiveAttempt = $attemptNum;
            if ($attemptOverrideRaw !== null && $attemptOverrideRaw !== ''
                && in_array((int) $attemptOverrideRaw, [1, 2, 3], true)) {
                $effectiveAttempt = (int) $attemptOverrideRaw;
            }

            if ($manualYnTuri === 'oski' || in_array($result->quiz_type, $oskiTypes) || $shaklLower === 'oski' || stripos($result->quiz_type ?? '', 'OSKI') !== false) {
                $trainingTypeCode = 101;
                $trainingTypeName = 'Oski';
            } elseif ($manualYnTuri === 'test' || in_array($result->quiz_type, $testTypes) || stripos($shaklLower, 'test') !== false || stripos($result->quiz_type ?? '', 'test') !== false) {
                $trainingTypeCode = 102;
                $trainingTypeName = 'Yakuniy test';
            } else {
                $rowInfo['error'] = "Quiz turi aniqlanmadi: '{$result->quiz_type}' (OSKI yoki YN test bo'lishi kerak)";
                $errors[] = $rowInfo;
                continue;
            }

            try {
                $created = StudentGrade::create([
                    'student_id' => $student->id,
                    'student_hemis_id' => $student->hemis_id,
                    'hemis_id' => 999999999,
                    'semester_code' => $semester->code ?? $semesterCode,
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
                    'is_final' => true,
                    'attempt' => $effectiveAttempt,
                    'is_qoshimcha' => $hasQoshimchaPre = (preg_match('/\(.*qo\'?shimcha.*\)/iu', $shaklRaw) || mb_stripos($shaklRaw, 'farmoyish') !== false),
                ]);

                // Qo'shimcha yuklanganda — bu talaba qo'shimcha farmoyish orqali
                // topshirdi. Shu attempt'dagi asosiy (is_qoshimcha=0) qatorni hamda
                // attempt=1 qo'shimcha bo'lsa, ortiqcha auto attempt=2 ni o'chiramiz.
                if ($hasQoshimchaPre) {
                    DB::table('student_grades')
                        ->where('student_hemis_id', $student->hemis_id)
                        ->where('subject_id', $subject->subject_id)
                        ->where('training_type_code', $trainingTypeCode)
                        ->where('semester_code', $semester->code ?? $semesterCode)
                        ->where('attempt', $attemptNum)
                        ->where('is_qoshimcha', 0)
                        ->where('id', '!=', $created->id ?? 0)
                        ->delete();

                    if ($effectiveAttempt === 1) {
                        DB::table('student_grades')
                            ->where('student_hemis_id', $student->hemis_id)
                            ->where('subject_id', $subject->subject_id)
                            ->where('training_type_code', $trainingTypeCode)
                            ->where('semester_code', $semester->code ?? $semesterCode)
                            ->where('attempt', 2)
                            ->where('is_qoshimcha', 0)
                            ->delete();
                    }
                }

                if (!$created || !$created->exists || !$created->id) {
                    throw new \RuntimeException('StudentGrade saqlanmadi');
                }

                $verify = StudentGrade::where('quiz_result_id', $result->id)->exists();
                if (!$verify) {
                    throw new \RuntimeException('Yozuv DB da topilmadi');
                }

                $successCount++;
            } catch (\Throwable $e) {
                Log::error('uploadToGrades create failed', [
                    'quiz_result_id' => $result->id,
                    'student_id' => $student->id,
                    'subject_id' => $subject->subject_id,
                    'error' => $e->getMessage(),
                ]);
                $rowInfo['error'] = "Saqlashda xato: " . $e->getMessage();
                $errors[] = $rowInfo;
                continue;
            }
        }

        return response()->json([
            'success_count' => $successCount,
            'error_count' => count($errors),
            'errors' => $errors,
            'warning_count' => count($warnings),
            'warnings' => $warnings,
        ]);
    }

    /**
     * Berilgan talabalardan 4+ qarzga ega (kursdan qoldirilgan) bo'lganlarini
     * aniqlaydi. Qarz = o'tgan semestrlardagi topshirilmagan fanlar +
     * joriy semestrda OSKI/Test 1-urinishdan yiqilgan fanlar — YN kunini
     * belgilash sahifasidagi "4 tadan ortiq qarz" mantig'i bilan bir xil.
     *
     * @param  array  $studentIdValues  hemis_id yoki student_id_number qiymatlari
     * @return array<string,array{names:string[],count:int}>  hemis_id => qarz ma'lumoti
     */
    private function computeHeldBackDebts(array $studentIdValues): array
    {
        $studentIdValues = array_values(array_filter(array_unique($studentIdValues)));
        if (empty($studentIdValues)) {
            return [];
        }

        // student_id qiymati hemis_id yoki student_id_number bo'lishi mumkin.
        $students = Student::where(function ($q) use ($studentIdValues) {
            $q->whereIn('hemis_id', $studentIdValues)
              ->orWhereIn('student_id_number', $studentIdValues);
        })->get(['hemis_id', 'semester_code']);
        if ($students->isEmpty()) {
            return [];
        }
        $hemisIds = $students->pluck('hemis_id')->map(fn($v) => (string) $v)->unique()->values()->all();

        // O'tgan semestrlardagi qarzlar — YN sahifasidagi AYNI metod.
        $pastDebts = \App\Http\Controllers\Admin\AcademicScheduleController::computeStudentPastSemesterDebts($hemisIds);

        // Joriy semestrdagi qarzlar — talabaning o'z semester_code'idagi
        // OSKI/Test 1-urinishidan yiqilgan (COALESCE(retake_grade,grade) < 60) fanlar.
        $currentDebts = []; // hemis_id => [subject_id => subject_name]
        try {
            $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');
            $q = DB::table('student_grades as sg')
                ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
                ->whereIn('sg.student_hemis_id', $hemisIds)
                ->whereColumn('sg.semester_code', 'st.semester_code')
                ->whereIn('sg.training_type_code', [101, 102])
                ->whereNull('sg.deleted_at')
                ->whereRaw('COALESCE(sg.retake_grade, sg.grade) < 60');
            if ($hasAttemptCol) {
                $q->where(function ($x) {
                    $x->where('sg.attempt', 1)->orWhereNull('sg.attempt');
                });
            }
            foreach ($q->select('sg.student_hemis_id', 'sg.subject_id', 'sg.subject_name')->distinct()->get() as $r) {
                $currentDebts[(string) $r->student_hemis_id][(string) $r->subject_id] = (string) $r->subject_name;
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning('computeHeldBackDebts: joriy qarz so\'rovi xatolik: ' . $e->getMessage());
        }

        $result = [];
        foreach ($hemisIds as $hid) {
            $pastList = $pastDebts[$hid] ?? [];
            $currentList = $currentDebts[$hid] ?? [];
            $total = count($pastList) + count($currentList);
            if ($total < 4) {
                continue;
            }
            $names = [];
            foreach ($pastList as $d) {
                $names[] = (string) ($d['subject_name'] ?? '');
            }
            foreach ($currentList as $nm) {
                $names[] = $nm;
            }
            $result[$hid] = [
                'names' => array_values(array_filter(array_unique($names))),
                'count' => $total,
            ];
        }
        return $result;
    }

    /**
     * JN mavzu retake bahosini tegishli jurnal yozuviga qo'yish (retake_grade).
     *
     * Logika:
     * - Moodle quiz_name dan N-mavzu olinadi (shakl field)
     * - Talabaning guruhi uchun shu fan va semester bo'yicha Amaliy dars sanalari
     *   (training_type_code NOT IN [11, 17, 99, 100, 101, 102, 103]) xronologik olinadi
     * - N-chi sana = mavzu sanasi
     * - Shu sanadagi student_grades (JN type) yozuvi topiladi
     * - Holatga qarab:
     *     NB (reason=absent, grade=null): retake_grade = Moodle grade
     *     Mavjud baho < Moodle: retake_grade yangilanadi, reason=low_grade
     *     Mavjud baho >= Moodle: skip
     *     YN qulflangan: skip
     */
    private function uploadMavzuRetake($result, array $rowInfo, int &$successCount, array &$errors, array $subjectOverrides = [], int $mavzuNOverride = 0): void
    {
        // Mavzu raqamini aniqlash: foydalanuvchi modaldan tanlagan bo'lsa undan, aks holda shakl'dan
        if ($mavzuNOverride > 0) {
            $mavzuN = $mavzuNOverride;
        } else {
            if (!preg_match('/^(\d+)-mavzu$/i', $result->shakl, $m)) {
                $rowInfo['error'] = "Mavzu raqami topilmadi: '{$result->shakl}'";
                $errors[] = $rowInfo;
                return;
            }
            $mavzuN = (int) $m[1];
        }

        if ($result->grade === null || $result->grade < 0 || $result->grade > 100) {
            $rowInfo['error'] = "Baho noto'g'ri: {$result->grade}";
            $errors[] = $rowInfo;
            return;
        }

        // Talaba topish
        $student = Student::where('hemis_id', $result->student_id)
            ->orWhere('student_id_number', $result->student_id)
            ->first();
        if (!$student) {
            $rowInfo['error'] = "Talaba topilmadi (student_id: {$result->student_id})";
            $errors[] = $rowInfo;
            return;
        }

        // Guruh
        $group = Group::where('group_hemis_id', $student->group_id)->first();
        if (!$group) {
            $rowInfo['error'] = "Talaba guruhi topilmadi";
            $errors[] = $rowInfo;
            return;
        }

        // Fan ID ni override qilish (Qayta yuklash modal'idan kelsa)
        $overrideKey = $result->fan_id . '_' . $student->group_id;
        $targetFanId = $subjectOverrides[$overrideKey] ?? $result->fan_id;

        // Fan o'quv rejada bormi
        $subject = CurriculumSubject::where('subject_id', $targetFanId)
            ->where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->first();
        if (!$subject) {
            $rowInfo['error'] = "Fan o'quv rejada yo'q (fan_id: {$targetFanId})";
            $errors[] = $rowInfo;
            return;
        }

        $semesterCode = $student->semester_code;

        // Amaliy (JN) dars sanalari — xronologik
        $lessonDates = DB::table('schedules')
            ->where('group_id', $student->group_id)
            ->where('subject_id', $targetFanId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [11, 17, 99, 100, 101, 102, 103])
            ->whereNull('deleted_at')
            ->select(DB::raw('DATE(lesson_date) as d'))
            ->distinct()
            ->orderBy('d')
            ->pluck('d')
            ->toArray();

        if (empty($lessonDates) || !isset($lessonDates[$mavzuN - 1])) {
            $total = count($lessonDates);
            $rowInfo['error'] = "Mavzu {$mavzuN} uchun dars sanasi topilmadi (jami {$total} ta amaliy)";
            $errors[] = $rowInfo;
            return;
        }

        $targetDate = $lessonDates[$mavzuN - 1];
        $moodleGrade = (int) round((float) $result->grade);

        // Shu sanadagi jurnal yozuvlari (JN type) — bir kunda bir nechta juftlik bo'lishi mumkin
        $sgRecords = DB::table('student_grades')
            ->where('student_hemis_id', $student->hemis_id)
            ->where('subject_id', $targetFanId)
            ->where('semester_code', $semesterCode)
            ->whereDate('lesson_date', $targetDate)
            ->whereNotIn('training_type_code', [11, 17, 99, 100, 101, 102, 103])
            ->whereNull('deleted_at')
            ->get();

        if ($sgRecords->isEmpty()) {
            $rowInfo['error'] = "Jurnal yozuvi topilmadi (sana {$targetDate})";
            $errors[] = $rowInfo;
            return;
        }

        $moodleGrade = (int) round((float) $result->grade);
        $updatedAnyPair = false;
        $skipReasons = [];

        foreach ($sgRecords as $sg) {
            $isNb = $sg->reason === 'absent' && $sg->grade === null;
            $updates = null;

            // NB sababli/sababsizligini aniqlaymiz — retake multiplier va YN-qulf
            // bypass uchun kerak. absent_on > 0 — sababli (yashil/ko'k NB);
            // yoki tasdiqlangan sababli ariza shu sanani qamrasa — sababli.
            $isSababli = false;
            if ($isNb) {
                $isSababli = DB::table('attendances')
                    ->where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $targetFanId)
                    ->whereDate('lesson_date', $targetDate)
                    ->where('lesson_pair_code', $sg->lesson_pair_code)
                    ->where('absent_on', '>', 0)
                    ->exists();

                if (!$isSababli) {
                    $isSababli = \App\Models\AbsenceExcuse::where('status', 'approved')
                        ->where('student_hemis_id', $student->hemis_id)
                        ->whereDate('start_date', '<=', $targetDate)
                        ->whereDate('end_date', '>=', $targetDate)
                        ->exists();
                }
            }

            // YN qulflangan juftlik — odatda o'tkazib yuboriladi. ISTISNO:
            // NB SABABLI bo'lsa va retake aynan Farmoyish (Sababli ariza) makeup
            // sanalari oralig'ida topshirilgan bo'lsa — YN qulfiga qaramay yuklanadi.
            if (!empty($sg->is_yn_locked)) {
                if (!$isNb || !$isSababli) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: YN qulflangan";
                    continue;
                }
                if (!$this->retakeWithinMakeupWindow($student, $targetFanId, $result)) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: Farmoyishda qayta topshirish kunlari bilan mos kelmadi";
                    continue;
                }
            }

            if ($isNb) {
                $multiplier = $isSababli ? 1.0 : 0.8;
                $retakeValue = round($moodleGrade * $multiplier, 2);

                // Agar mavjud retake baho yangisidan katta yoki teng bo'lsa — skip
                if ($sg->retake_grade !== null && $sg->retake_grade >= $retakeValue) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: mavjud retake ({$sg->retake_grade}) >= yangi ({$retakeValue})";
                    continue;
                }

                $label = $isSababli ? 'sababli ×1.0' : 'sababsiz ×0.8';
                $updates = [
                    'retake_grade'         => $retakeValue,
                    'retake_comment'       => "Moodle mavzu {$result->shakl}: {$moodleGrade} {$label} = {$retakeValue} (quiz_result#{$result->id})",
                    'retake_was_sababli'   => $isSababli,
                    'reason'               => 'absent',
                    'status'               => 'closed',
                    'quiz_result_id'       => $result->id,
                    'updated_at'           => now(),
                ];
            } else {
                // NB emas — oddiy baho bor. Faqat baho 60 dan past bo'lsa retake qabul qilinadi,
                // retake_grade = Moodle × 0.8 (diagonal ko'rinishda saqlanadi)
                $currentValue = $sg->retake_grade ?? $sg->grade;

                if ($currentValue === null) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: baho yo'q va NB emas";
                    continue;
                }

                if ($currentValue >= 60) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: baho ({$currentValue}) 60 dan yuqori — retake kerak emas";
                    continue;
                }

                // Baho < 60 — retake × 0.8
                $retakeValue = round($moodleGrade * 0.8, 2);

                // Yangi retake hozirgi asl bahodan past bo'lmasligi kerak (aks holda qabul qilinmaydi)
                if ($sg->grade !== null && $retakeValue <= $sg->grade) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: yangi retake ({$retakeValue}) <= asl baho ({$sg->grade})";
                    continue;
                }

                // Mavjud retake'dan ham past bo'lmasligi kerak
                if ($sg->retake_grade !== null && $retakeValue <= $sg->retake_grade) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: mavjud retake ({$sg->retake_grade}) >= yangi ({$retakeValue})";
                    continue;
                }

                $updates = [
                    'retake_grade'         => $retakeValue,
                    'retake_comment'       => "Moodle mavzu {$result->shakl}: {$moodleGrade} ×0.8 = {$retakeValue} (quiz_result#{$result->id})",
                    'retake_was_sababli'   => false,
                    'reason'               => $sg->reason ?? 'low_grade',
                    'status'               => 'closed',
                    'quiz_result_id'       => $result->id,
                    'updated_at'           => now(),
                ];
            }

            if (!$updates) continue;

            try {
                $affected = DB::table('student_grades')->where('id', $sg->id)->update($updates);
                if ($affected === 0) {
                    $skipReasons[] = "juftlik {$sg->lesson_pair_code}: update 0 qator (yozuv yo'qolgan?)";
                    continue;
                }
                $updatedAnyPair = true;
            } catch (\Throwable $e) {
                Log::error('uploadMavzuRetake update failed', [
                    'sg_id' => $sg->id,
                    'quiz_result_id' => $result->id,
                    'error' => $e->getMessage(),
                ]);
                $skipReasons[] = "juftlik {$sg->lesson_pair_code}: saqlashda xato — " . $e->getMessage();
            }
        }

        if ($updatedAnyPair) {
            $successCount++;
        } else {
            // Hech qaysi juftlik yangilanmadi
            $rowInfo['error'] = implode('; ', $skipReasons) ?: 'Barcha juftliklar skip qilindi';
            $errors[] = $rowInfo;
        }
    }

    /**
     * Retake (Moodle quiz) aynan Sababli ariza (Farmoyish) bo'yicha shu fanga
     * belgilangan makeup sanalari oralig'ida topshirilganmi — tekshiradi.
     * Faqat tasdiqlangan ariza (status=approved) va JN turdagi makeup'lar hisobga
     * olinadi. YN qulflangan bo'lsa ham sababli retake'ni shu shart bilan o'tkazadi.
     */
    private function retakeWithinMakeupWindow($student, $subjectId, $result): bool
    {
        try {
            if (empty($result->date_finish)) {
                return false;
            }
            $retakeDate = \Carbon\Carbon::parse($result->date_finish)->toDateString();

            $makeups = \App\Models\AbsenceExcuseMakeup::whereHas('absenceExcuse', function ($q) use ($student) {
                    $q->where('status', 'approved')
                      ->where('student_hemis_id', (string) $student->hemis_id);
                })
                ->where('subject_id', (string) $subjectId)
                ->where('assessment_type', 'jn')
                ->whereNotNull('makeup_date')
                ->get(['makeup_date', 'makeup_end_date']);

            foreach ($makeups as $mk) {
                $from = \Carbon\Carbon::parse($mk->makeup_date)->toDateString();
                $to = $mk->makeup_end_date
                    ? \Carbon\Carbon::parse($mk->makeup_end_date)->toDateString()
                    : $from;
                if ($retakeDate >= $from && $retakeDate <= $to) {
                    return true;
                }
            }
            return false;
        } catch (\Throwable $e) {
            Log::warning('retakeWithinMakeupWindow xatosi: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Oldin yuklangan natijalarni qayta yuklash.
     * Eski student_grades yozuvlarini o'chirib, yangidan yaratadi (to'g'ri semester_code bilan).
     * subject_overrides berilgan bo'lsa, har bir (fan_id, group_id) juftligi uchun
     * boshqa subject_id ga yuklash mumkin.
     */
    public function reUploadToGrades(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
            'subject_overrides' => 'nullable|array',
            'semester_overrides' => 'nullable|array',
        ]);

        // Qayta yuklashdan oldin eski yozuvlarni tozalash. Ikki xil mantiq:
        // - OSKI/Test (reason='quiz_result') — yangi qator sifatida qo'shilgan: butunlay o'chiriladi
        // - Mavzu retake — mavjud jurnal qatoriga retake_grade qo'yilgan: faqat retake
        //   maydonlari tozalanadi, qator qoladi (NB/past baho asl holatiga qaytadi)
        $existingRows = StudentGrade::whereIn('quiz_result_id', $request->ids)
            ->whereNotNull('quiz_result_id')
            ->get();

        foreach ($existingRows as $sg) {
            if ($sg->reason === 'quiz_result') {
                // OSKI/Test yozuvi — butunlay o'chirish
                $sg->delete();
            } else {
                // Mavzu retake — retake maydonlarni tozalash
                $revertReason = $sg->reason;
                if ($sg->reason === 'low_grade' && $sg->grade === null) {
                    // low_grade faqat retake uchun qo'yilgan bo'lsa — reason ni ham null qilish
                    $revertReason = null;
                }
                DB::table('student_grades')->where('id', $sg->id)->update([
                    'retake_grade'         => null,
                    'retake_comment'       => null,
                    'retake_was_sababli'   => null,
                    'quiz_result_id'       => null,
                    'reason'               => $revertReason,
                    'updated_at'           => now(),
                ]);
            }
        }

        // uploadToGrades ni chaqirish (endi eski yozuvlar tozalangan — qayta yuklanadi)
        $request->merge([
            'skip_shakl_filter' => true,
            'yn_turi_overrides' => $request->input('yn_turi_overrides', []),
        ]);
        return $this->uploadToGrades($request);
    }

    /**
     * Qayta yuklash uchun preview: tanlangan natijalarni (fan_id, group_id) bo'yicha
     * guruhlab, har bir guruh uchun curriculum'da mavjud bo'lgan fanlar ro'yxatini qaytaradi.
     * Frontend modalda foydalanuvchiga fan_id ni o'zgartirish imkonini beradi.
     */
    public function getReuploadPreview(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        // (fan_id, group_id) bo'yicha guruhlash. Talabaning hozirgi semester_code'i ham saqlanadi.
        $groups = [];
        foreach ($results as $r) {
            $student = Student::where('hemis_id', $r->student_id)
                ->orWhere('student_id_number', $r->student_id)
                ->first();
            if (!$student || !$student->group_id) {
                continue;
            }
            $key = $r->fan_id . '_' . $student->group_id;
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'key' => $key,
                    'original_fan_id' => $r->fan_id,
                    'original_fan_name' => $r->fan_name,
                    'group_hemis_id' => $student->group_id,
                    'group_name' => $student->group_name ?? '',
                    'curriculum_hemis_id' => null,
                    'semester_code' => $student->semester_code ?? null,
                    'semester_name' => $student->semester_name ?? null,
                    'grade_count' => 0,
                    'sample_grades' => [],
                    'available_subjects' => [],
                    'quiz_type' => $r->quiz_type,
                    'shakl' => $r->shakl,
                ];
            }
            $groups[$key]['grade_count']++;
            if (count($groups[$key]['sample_grades']) < 5) {
                $groups[$key]['sample_grades'][] = [
                    'student_name' => $r->student_name,
                    'grade' => $r->grade,
                ];
            }
        }

        // Har bir guruh uchun curriculum_hemis_id ni biriktirish
        $curriculumLookup = [];   // group_key => curriculum_hemis_id
        $semesterLookup = [];     // group_key => semester_code
        foreach ($groups as &$g) {
            $group = Group::where('group_hemis_id', $g['group_hemis_id'])->first();
            if ($group) {
                $g['curriculum_hemis_id'] = $group->curriculum_hemis_id;
                $curriculumLookup[$g['key']] = $group->curriculum_hemis_id;
            }
            if ($g['semester_code']) {
                $semesterLookup[$g['key']] = $g['semester_code'];
            }
        }
        unset($g);

        // Har bir guruh uchun curriculum'dagi BARCHA semestr fanlarini olamiz —
        // semestr bo'yicha guruhlangan. Modal foydalanuvchiga semestrni tanlash
        // (noto'g'ri bo'lsa to'g'rilash) va o'sha semestr fanidan tanlash imkonini beradi.
        foreach ($groups as &$g) {
            $g['available_semesters'] = [];
            $g['subjects_by_semester'] = [];
            $g['available_subjects'] = [];
            if (!$g['curriculum_hemis_id']) continue;

            $allSubjects = CurriculumSubject::where('curricula_hemis_id', $g['curriculum_hemis_id'])
                ->select('subject_id', 'subject_name', 'subject_code', 'semester_code', 'semester_name')
                ->orderBy('semester_code')
                ->orderBy('subject_name')
                ->get();

            $bySem = [];     // semester_code => [subject_id => subject]
            $semNames = [];  // semester_code => semester_name
            foreach ($allSubjects as $s) {
                $sc = (string) $s->semester_code;
                if ($sc === '') continue;
                if (!isset($bySem[$sc])) {
                    $bySem[$sc] = [];
                    $semNames[$sc] = $s->semester_name ?: ($sc . '-semestr');
                }
                if (isset($bySem[$sc][$s->subject_id])) continue;
                $bySem[$sc][$s->subject_id] = [
                    'subject_id' => $s->subject_id,
                    'subject_name' => $s->subject_name,
                    'subject_code' => $s->subject_code,
                    'semester_name' => $s->semester_name,
                    'lesson_count' => 0,
                ];
            }
            ksort($bySem, SORT_NUMERIC);
            foreach ($bySem as $sc => $subs) {
                $g['subjects_by_semester'][$sc] = array_values($subs);
                $g['available_semesters'][] = ['code' => $sc, 'name' => $semNames[$sc]];
            }
            $g['available_subjects'] = $g['subjects_by_semester'][(string) $g['semester_code']] ?? [];
        }
        unset($g);

        // Har bir (group, subject, semester) uchun mavzu (amaliy dars) sonini hisoblash —
        // barcha semestrlar bo'yicha (semestr modalda o'zgartirilishi mumkin).
        $excludedSchedTypes = [11, 17, 99, 100, 101, 102, 103];
        $countTriples = [];
        foreach ($groups as $g) {
            foreach ($g['subjects_by_semester'] as $sc => $subs) {
                foreach ($subs as $s) {
                    $key = $g['group_hemis_id'] . '|' . $s['subject_id'] . '|' . $sc;
                    $countTriples[$key] = [
                        'group' => $g['group_hemis_id'],
                        'subject' => $s['subject_id'],
                        'sem' => $sc,
                    ];
                }
            }
        }
        $lessonCounts = [];
        if (!empty($countTriples)) {
            $cGroups = array_unique(array_column($countTriples, 'group'));
            $cSubjects = array_unique(array_column($countTriples, 'subject'));
            $cSems = array_unique(array_filter(array_column($countTriples, 'sem')));

            if (!empty($cGroups) && !empty($cSubjects) && !empty($cSems)) {
                $countRows = DB::table('schedules')
                    ->whereNull('deleted_at')
                    ->whereNotIn('training_type_code', $excludedSchedTypes)
                    ->whereIn('group_id', $cGroups)
                    ->whereIn('subject_id', $cSubjects)
                    ->whereIn('semester_code', $cSems)
                    ->select('group_id', 'subject_id', 'semester_code', DB::raw('COUNT(DISTINCT DATE(lesson_date)) as cnt'))
                    ->groupBy('group_id', 'subject_id', 'semester_code')
                    ->get();

                foreach ($countRows as $r) {
                    $k = $r->group_id . '|' . $r->subject_id . '|' . $r->semester_code;
                    $lessonCounts[$k] = (int) $r->cnt;
                }
            }
        }

        foreach ($groups as &$g) {
            foreach ($g['subjects_by_semester'] as $sc => &$subs) {
                foreach ($subs as &$s) {
                    $k = $g['group_hemis_id'] . '|' . $s['subject_id'] . '|' . $sc;
                    $s['lesson_count'] = $lessonCounts[$k] ?? 0;
                }
                unset($s);
            }
            unset($subs);
            $g['available_subjects'] = $g['subjects_by_semester'][(string) $g['semester_code']] ?? [];
        }
        unset($g);

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
        foreach ($groups as &$g) {
            $qt = $g['quiz_type'] ?? '';
            $sh = $g['shakl'] ?? '';
            $shLower = mb_strtolower($sh);
            // NB shakli: talaba qatnashmagan — foydalanuvchi fan va YN turini o'zi tanlasin
            if ($shLower === 'nb') {
                $g['yn_turi'] = null;
            } elseif (preg_match('/^\d+-mavzu$/i', $sh)) {
                $g['yn_turi'] = 'jn_mavzu';
                $g['mavzu_shakl'] = $sh;
            } elseif (in_array($qt, $oskiTypes) || $shLower === 'oski' || stripos($qt, 'OSKI') !== false) {
                $g['yn_turi'] = 'oski';
            } elseif (in_array($qt, $testTypes) || stripos($shLower, 'test') !== false || stripos($qt, 'test') !== false) {
                $g['yn_turi'] = 'test';
            } else {
                $g['yn_turi'] = null;
            }
        }
        unset($g);

        return response()->json([
            'success' => true,
            'groups' => array_values($groups),
        ]);
    }

    /**
     * Quiz natija bahosini tahrirlash (hemis_quiz_results da grade yangilash).
     */
    public function updateGrade(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:hemis_quiz_results,id',
            'grade' => 'required|numeric|min:0|max:100',
        ]);

        DB::table('hemis_quiz_results')
            ->where('id', $request->id)
            ->update([
                'grade' => $request->grade,
                'updated_at' => now(),
            ]);

        return response()->json(['success' => true]);
    }

    /**
     * Quiz natijasining fan_id va fan_name ni yangilash (inline edit).
     */
    public function updateFanId(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:hemis_quiz_results,id',
            'fan_id' => 'required|integer',
        ]);

        $subject = CurriculumSubject::where('subject_id', $request->fan_id)->first();
        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => "Fan topilmadi (ID: {$request->fan_id})",
            ], 404);
        }

        DB::table('hemis_quiz_results')
            ->where('id', $request->id)
            ->update([
                'fan_id' => $request->fan_id,
                'fan_name' => $subject->subject_name,
                'updated_at' => now(),
            ]);

        return response()->json([
            'success' => true,
            'fan_id' => $request->fan_id,
            'fan_name' => $subject->subject_name,
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
     * Tanlangan quiz natijalarning baholarini student_grades dan o'chirish.
     * Agar bir xil subject_id da turli fan nomlari bo'lsa — conflict qaytaradi.
     */
    public function deleteGrades(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
            'confirmed_fan_names' => 'nullable|array', // conflict tasdiqlanganda yuboriladi
            'subject_overrides' => 'nullable|array',   // { "<fan_id>_<group_id>": <subject_id> }
            'yn_turi_overrides' => 'nullable|array',   // { "<fan_id>_<group_id>": "oski"|"test" }
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();

        // Override yo'li (modal orqali fan + YN turi tanlangan bo'lsa):
        // student_grades dan (student_hemis_id + subject_id + training_type_code + semester_code)
        // bo'yicha o'chiramiz — bu orphan qatorlarni ham tutadi (quiz_result_id mos kelmasa ham).
        $subjectOverrides = $request->input('subject_overrides', []);
        $ynTuriOverrides = $request->input('yn_turi_overrides', []);
        if (!empty($subjectOverrides) || !empty($ynTuriOverrides)) {
            $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
            $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

            $deletedCount = 0;
            $errors = [];
            foreach ($results as $result) {
                $student = Student::where('hemis_id', $result->student_id)
                    ->orWhere('student_id_number', $result->student_id)
                    ->first();
                if (!$student) continue;

                $key = $result->fan_id . '_' . ($student->group_id ?? '');
                $targetSubjectId = $subjectOverrides[$key] ?? $result->fan_id;

                $ynOverride = $ynTuriOverrides[$key] ?? null;
                if ($ynOverride === 'oski') {
                    $trainingTypeCode = 101;
                } elseif ($ynOverride === 'test') {
                    $trainingTypeCode = 102;
                } elseif (in_array($result->quiz_type, $oskiTypes)) {
                    $trainingTypeCode = 101;
                } elseif (in_array($result->quiz_type, $testTypes)) {
                    $trainingTypeCode = 102;
                } else {
                    $errors[] = [
                        'id' => $result->id,
                        'student_name' => $result->student_name,
                        'fan_name' => $result->fan_name,
                        'error' => 'YN turi aniqlanmadi',
                    ];
                    continue;
                }

                $removed = DB::table('student_grades')
                    ->where('student_hemis_id', $student->hemis_id)
                    ->where('subject_id', $targetSubjectId)
                    ->where('training_type_code', $trainingTypeCode)
                    ->where('semester_code', $student->semester_code)
                    ->delete();

                if ($removed > 0) {
                    $deletedCount += $removed;
                } else {
                    $errors[] = [
                        'id' => $result->id,
                        'student_name' => $result->student_name,
                        'fan_name' => $result->fan_name,
                        'error' => 'Bu fan + YN turi kombinatsiyasi uchun baho topilmadi',
                    ];
                }
            }

            return response()->json([
                'status' => 'success',
                'deleted_count' => $deletedCount,
                'error_count' => count($errors),
                'errors' => $errors,
            ]);
        }

        // Subject ID bo'yicha fan nomlarini guruhlash
        $subjectFanNames = [];
        foreach ($results as $result) {
            if ($result->fan_id) {
                $subjectFanNames[$result->fan_id][$result->fan_name] = true;
            }
        }

        // Bir xil subject_id da ikki xil fan_name bormi tekshirish
        $conflicts = [];
        foreach ($subjectFanNames as $fanId => $names) {
            if (count($names) > 1) {
                $conflicts[$fanId] = array_keys($names);
            }
        }

        // Agar conflict bor va hali tasdiqlanmagan bo'lsa — modal uchun qaytarish
        if (!empty($conflicts) && !$request->has('confirmed_fan_names')) {
            return response()->json([
                'status' => 'conflict',
                'conflicts' => $conflicts,
                'message' => 'Bir xil subject_id da turli fan nomlari topildi. Qaysi fanlarni o\'chirmoqchisiz?',
            ]);
        }

        // O'chirilishi kerak bo'lgan fan nomlarini aniqlash
        $confirmedFanNames = $request->input('confirmed_fan_names', []);

        $deletedCount = 0;
        $errors = [];

        foreach ($results as $result) {
            // Agar conflict bor va foydalanuvchi faqat ma'lum fan nomlarini tanlagan bo'lsa
            if (!empty($confirmedFanNames) && isset($conflicts[$result->fan_id])) {
                if (!in_array($result->fan_name, $confirmedFanNames)) {
                    continue; // Bu fan nomi tanlanmagan — o'tkazib yuborish
                }
            }

            // 1) OSKI/Test (reason='quiz_result') yozuvlarini o'chirish
            $deletedOski = StudentGrade::where('quiz_result_id', $result->id)
                ->where('reason', 'quiz_result')
                ->delete();

            // 2) Mavzu retake yozuvlarini tiklash (yozuv qoladi, retake maydonlari tozalanadi)
            $mavzuRows = StudentGrade::where('quiz_result_id', $result->id)
                ->where(function ($q) {
                    $q->whereNull('reason')->orWhereIn('reason', ['absent', 'low_grade']);
                })
                ->get();

            $revertedMavzu = 0;
            foreach ($mavzuRows as $sg) {
                DB::table('student_grades')->where('id', $sg->id)->update([
                    'retake_grade'         => null,
                    'retake_comment'       => null,
                    'retake_was_sababli'   => null,
                    'quiz_result_id'       => null,
                    'updated_at'           => now(),
                ]);
                $revertedMavzu++;
            }

            $totalRemoved = $deletedOski + $revertedMavzu;
            if ($totalRemoved > 0) {
                $deletedCount += $totalRemoved;
            } else {
                $errors[] = [
                    'id' => $result->id,
                    'student_name' => $result->student_name,
                    'fan_name' => $result->fan_name,
                    'error' => 'Sistemada baho topilmadi (yuklangan emas yoki allaqachon o\'chirilgan)',
                ];
            }
        }

        return response()->json([
            'status' => 'success',
            'deleted_count' => $deletedCount,
            'error_count' => count($errors),
            'errors' => $errors,
        ]);
    }

    /**
     * Tanlangan natijalar uchun student_grades da mavjud OSKI/Test baholarini solishtirish.
     */
    public function compareGrades(Request $request)
    {
        $request->validate([
            'ids' => 'required|array|min:1',
            'ids.*' => 'integer|exists:hemis_quiz_results,id',
            'subject_overrides' => 'nullable|array',
            'yn_turi_overrides' => 'nullable|array',
        ]);

        $results = HemisQuizResult::whereIn('id', $request->ids)->get();
        $subjectOverrides = $request->input('subject_overrides', []);
        $ynTuriOverrides = $request->input('yn_turi_overrides', []);

        $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
        $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

        $comparisons = [];

        foreach ($results as $result) {
            $student = Student::where('hemis_id', $result->student_id)
                ->orWhere('student_id_number', $result->student_id)
                ->first();

            if (!$student) continue;

            $key = $result->fan_id . '_' . ($student->group_id ?? '');
            $targetSubjectId = $subjectOverrides[$key] ?? $result->fan_id;
            $ynOverride = $ynTuriOverrides[$key] ?? null;

            // Quiz turini aniqlash (override > quiz_type)
            $trainingTypeCode = null;
            $typeName = '-';
            if ($ynOverride === 'oski') {
                $trainingTypeCode = 101;
                $typeName = 'OSKI';
            } elseif ($ynOverride === 'test') {
                $trainingTypeCode = 102;
                $typeName = 'Test';
            } elseif (in_array($result->quiz_type, $oskiTypes)) {
                $trainingTypeCode = 101;
                $typeName = 'OSKI';
            } elseif (in_array($result->quiz_type, $testTypes)) {
                $trainingTypeCode = 102;
                $typeName = 'Test';
            }
            if (!$trainingTypeCode) continue;

            // Resolved subject nomi (override yoki original)
            $resolvedFanName = $result->fan_name;
            if ((int) $targetSubjectId !== (int) $result->fan_id) {
                $cs = CurriculumSubject::where('subject_id', $targetSubjectId)->first();
                if ($cs) {
                    $resolvedFanName = $cs->subject_name;
                }
            }

            // Mavjud baholarni topish (soft-deleted ham — orphan tozalash uchun)
            $existingGrades = StudentGrade::withTrashed()
                ->where('student_hemis_id', $student->hemis_id)
                ->where('subject_id', $targetSubjectId)
                ->where('training_type_code', $trainingTypeCode)
                ->where('semester_code', $student->semester_code)
                ->get();

            if ($existingGrades->isEmpty()) continue;

            foreach ($existingGrades as $eg) {
                $comparisons[] = [
                    'quiz_result_id' => $result->id,
                    'student_grade_id' => $eg->id,
                    'student_id' => $result->student_id,
                    'student_name' => $student->full_name ?? $result->student_name,
                    'fan_name' => $resolvedFanName,
                    'fan_id' => $targetSubjectId,
                    'type' => $typeName,
                    'moodle_grade' => $result->grade,
                    'existing_grade' => $eg->grade,
                    'existing_reason' => $eg->reason,
                    'existing_date' => $eg->lesson_date ? \Carbon\Carbon::parse($eg->lesson_date)->format('d.m.Y') : '',
                    'quiz_result_id_ref' => $eg->quiz_result_id,
                    'is_deleted' => !is_null($eg->deleted_at),
                ];
            }
        }

        return response()->json([
            'comparisons' => $comparisons,
            'total' => count($comparisons),
        ]);
    }

    /**
     * Bitta student_grade yozuvini o'chirish (ID bo'yicha).
     */
    public function deleteStudentGrade(Request $request)
    {
        $request->validate([
            'student_grade_id' => 'nullable|integer',
            'student_grade_ids' => 'nullable|array',
            'student_grade_ids.*' => 'integer',
        ]);

        if ($request->filled('student_grade_ids')) {
            // Har bir qatorni alohida o'chirish — LogsActivity (deleted hook)
            // audit log yozishi uchun. Mass delete model eventlarini chetlab o'tadi.
            // Soft-deleted qatorlar uchun forceDelete (DB dan butunlay olib tashlash).
            $grades = StudentGrade::withTrashed()
                ->whereIn('id', $request->student_grade_ids)
                ->get();
            $deleted = 0;
            foreach ($grades as $grade) {
                if ($grade->trashed()) {
                    $grade->forceDelete();
                } else {
                    $grade->delete();
                }
                $deleted++;
            }
            return response()->json([
                'success' => true,
                'deleted_count' => $deleted,
                'message' => $deleted . ' ta baho o\'chirildi',
            ]);
        }

        if (!$request->filled('student_grade_id')) {
            return response()->json(['success' => false, 'message' => 'ID kerak'], 400);
        }

        $grade = StudentGrade::withTrashed()->find($request->student_grade_id);
        if (!$grade) {
            return response()->json(['success' => false, 'message' => 'Baho topilmadi'], 404);
        }

        if ($grade->trashed()) {
            $grade->forceDelete();
        } else {
            $grade->delete();
        }

        return response()->json(['success' => true, 'message' => 'Baho o\'chirildi']);
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

    /**
     * Kunlik monitoring sahifasi (Moodle ↔ LMS sync ↔ Mark reconciliation).
     */
    public function kunlikMonitoringPage(Request $request)
    {
        $routePrefix = $this->routePrefix();
        return view('admin.kunlik-monitoring.index', compact('routePrefix'));
    }

    /**
     * Berilgan attempt_id lar ichidan BAKALAVRIATdan boshqa ta'lim turidagi
     * (ordinatura, magistratura, ...) talabalarga tegishlilarini aniqlaydi.
     * Kunlik monitoring faqat bakalavriat bo'yicha hisoblanishi uchun bular
     * hisobdan chiqarib tashlanadi.
     *
     * Ta'lim turi hemis_quiz_results.student_id -> students (hemis_id yoki
     * student_id_number) bog'lanishidan olinadi. Agar talaba topilmasa yoki
     * ta'lim turi noma'lum bo'lsa, attempt chiqarib tashlanmaydi — shunda
     * haqiqiy sync gap yashirilmaydi (faqat ANIQ bakalavr bo'lmaganlar olib
     * tashlanadi).
     *
     * @param int[] $attemptIds
     * @return array<int,true> hisobga olinmaydigan attempt_id lar to'plami
     */
    private function nonBakalavrAttemptIds(array $attemptIds): array
    {
        $excluded = [];
        if (empty($attemptIds)) {
            return $excluded;
        }

        // attempt_id -> student_id (hemis_quiz_results'dagi identifikator)
        $qr = DB::table('hemis_quiz_results')
            ->whereIn('attempt_id', $attemptIds)
            ->whereNotNull('student_id')
            ->select('attempt_id', 'student_id')
            ->get();

        if ($qr->isEmpty()) {
            return $excluded;
        }

        $studentIds = $qr->pluck('student_id')
            ->map(fn ($v) => (string) $v)
            ->filter(fn ($v) => $v !== '')
            ->unique()
            ->all();

        // student_id (hemis_id YOKI student_id_number) -> education_type_name
        $eduByKey = [];
        if (!empty($studentIds)) {
            Student::query()
                ->whereIn('hemis_id', $studentIds)
                ->orWhereIn('student_id_number', $studentIds)
                ->get(['hemis_id', 'student_id_number', 'education_type_name'])
                ->each(function ($s) use (&$eduByKey) {
                    $edu = (string) ($s->education_type_name ?? '');
                    if (!empty($s->hemis_id)) {
                        $eduByKey[(string) $s->hemis_id] = $edu;
                    }
                    if (!empty($s->student_id_number)) {
                        $eduByKey[(string) $s->student_id_number] = $edu;
                    }
                });
        }

        foreach ($qr as $row) {
            $edu = $eduByKey[(string) $row->student_id] ?? null;
            // Faqat ta'lim turi ANIQ bo'lib, bakalavr BO'LMAGAN holatlar chiqariladi.
            if ($edu !== null && $edu !== '' && stripos($edu, 'bakalavr') === false) {
                $excluded[(int) $row->attempt_id] = true;
            }
        }

        return $excluded;
    }

    /**
     * Kunlik monitoring uchun AJAX data. Moodle WS'dan kunlik attempt_id
     * ro'yxati olinadi, LMS'dagi hemis_quiz_results va student_grades bilan
     * solishtirilib, har kun uchun yo'qotish statistikasi qaytariladi.
     */
    public function kunlikMonitoringData(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d',
        ]);

        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        try {
            $svc = app(\App\Services\MoodleSyncMonitorService::class);
            $resp = $svc->getDailySummary($dateFrom, $dateTo);

            if (!($resp['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $resp['error'] ?? 'Moodle WS error',
                ], 502);
            }

            $days = $resp['days'] ?? [];

            // Barcha attempt_id larni bitta listga yig'amiz — bulk query uchun.
            $allMoodleIds = [];
            foreach ($days as $d) {
                foreach (($d['attempt_ids'] ?? []) as $aid) {
                    $allMoodleIds[(int) $aid] = true;
                }
            }
            $allMoodleIds = array_keys($allMoodleIds);

            // Faqat bakalavriat: ANIQ bakalavr bo'lmagan attempt'larni chiqaramiz.
            $excludedIds = $this->nonBakalavrAttemptIds($allMoodleIds);

            // Hozirgi LMS holatini bulk olamiz: attempt_id => hemis_quiz_results.id.
            // Hajm cheklangan (62 kun * kunlik attempts), shuning uchun get() yetarli.
            $syncedMap = []; // attempt_id => quiz_result_id
            if (!empty($allMoodleIds)) {
                $rows = DB::table('hemis_quiz_results')
                    ->whereIn('attempt_id', $allMoodleIds)
                    ->select('attempt_id', 'id')
                    ->get();
                foreach ($rows as $r) {
                    $syncedMap[(int) $r->attempt_id] = (int) $r->id;
                }
            }

            // Mark stage: qaysi quiz_result_id'lar uchun student_grades mavjud.
            $gradedQuizResultIds = [];
            if (!empty($syncedMap)) {
                $qrIds = array_values($syncedMap);
                $rows = DB::table('student_grades')
                    ->whereIn('quiz_result_id', $qrIds)
                    ->whereNotNull('quiz_result_id')
                    ->pluck('quiz_result_id');
                foreach ($rows as $qrId) {
                    $gradedQuizResultIds[(int) $qrId] = true;
                }
            }

            // Har kun uchun statistika yig'amiz.
            $result = [];
            $totMoodle = 0;
            $totSynced = 0;
            $totGraded = 0;

            foreach ($days as $d) {
                $date = (string) $d['date'];
                $attemptIds = array_map('intval', (array) ($d['attempt_ids'] ?? []));
                if (!empty($excludedIds)) {
                    $attemptIds = array_values(array_filter($attemptIds, fn ($a) => !isset($excludedIds[$a])));
                }
                // Bakalavr bo'lmaganlar chiqarilgani uchun WS'ning 'count' i emas,
                // filtrlangan ro'yxat soni ishlatiladi.
                $moodleCount = count($attemptIds);

                $syncedCount = 0;
                $gradedCount = 0;
                foreach ($attemptIds as $aid) {
                    if (isset($syncedMap[$aid])) {
                        $syncedCount++;
                        $qrId = $syncedMap[$aid];
                        if (isset($gradedQuizResultIds[$qrId])) {
                            $gradedCount++;
                        }
                    }
                }

                $syncGap = $moodleCount - $syncedCount;
                $markGap = $syncedCount - $gradedCount;

                $status = 'ok';
                if ($syncGap > 0) {
                    $status = 'sync_gap';
                } elseif ($markGap > 0) {
                    $status = 'mark_gap';
                }

                $result[] = [
                    'date'         => $date,
                    'moodle_count' => $moodleCount,
                    'synced_count' => $syncedCount,
                    'graded_count' => $gradedCount,
                    'sync_gap'     => $syncGap,
                    'mark_gap'     => $markGap,
                    'status'       => $status,
                ];

                $totMoodle += $moodleCount;
                $totSynced += $syncedCount;
                $totGraded += $gradedCount;
            }

            return response()->json([
                'success' => true,
                'days' => $result,
                'totals' => [
                    'moodle_count' => $totMoodle,
                    'synced_count' => $totSynced,
                    'graded_count' => $totGraded,
                    'sync_gap'     => $totMoodle - $totSynced,
                    'mark_gap'     => $totSynced - $totGraded,
                ],
            ]);
        } catch (\Throwable $e) {
            Log::error('kunlikMonitoringData failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ], 500);
        }
    }

    /**
     * Diagnostika endpointi — eski va yangi Moodle WS funksiyalarini
     * sinab ko'rib, raw javobni qaytaradi. "Access control exception"
     * kabi xatolarni aniqlashtirish uchun.
     */
    public function kunlikMonitoringDiagnose(Request $request)
    {
        $svc = app(\App\Services\MoodleSyncMonitorService::class);
        return response()->json($svc->diagnose());
    }

    /**
     * Kunlik monitoring uchun Excel eksport — 3 sahifa: kunlik xulosa,
     * sync gap (attempt_id ro'yxati) va mark gap (talaba/fan/baho).
     */
    public function kunlikMonitoringExport(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date_format:Y-m-d',
            'date_to'   => 'required|date_format:Y-m-d',
        ]);

        ini_set('memory_limit', '512M');
        set_time_limit(300);

        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $svc = app(\App\Services\MoodleSyncMonitorService::class);
        $resp = $svc->getDailySummary($dateFrom, $dateTo);
        if (!($resp['ok'] ?? false)) {
            return response()->json([
                'success' => false,
                'error' => $resp['error'] ?? 'Moodle WS error',
            ], 502);
        }

        $days = $resp['days'] ?? [];

        // Bulk: barcha attempt_id larni yig'ish
        $allMoodleIds = [];
        $perDay = []; // date => [attempt_ids]
        foreach ($days as $d) {
            $ids = array_map('intval', (array) ($d['attempt_ids'] ?? []));
            $perDay[$d['date']] = $ids;
            foreach ($ids as $aid) {
                $allMoodleIds[$aid] = true;
            }
        }
        $allMoodleIds = array_keys($allMoodleIds);

        // Faqat bakalavriat: ANIQ bakalavr bo'lmagan attempt'larni chiqaramiz.
        $excludedIds = $this->nonBakalavrAttemptIds($allMoodleIds);

        // Sync qilinganlar (attempt_id => row)
        $syncedByAttempt = collect();
        if (!empty($allMoodleIds)) {
            $syncedByAttempt = DB::table('hemis_quiz_results')
                ->whereIn('attempt_id', $allMoodleIds)
                ->select('id', 'attempt_id', 'student_id', 'student_name', 'fan_name', 'quiz_type', 'attempt_name', 'date_finish', 'grade')
                ->get()
                ->keyBy('attempt_id');
        }

        $gradedIds = [];
        if ($syncedByAttempt->isNotEmpty()) {
            $qrIds = $syncedByAttempt->pluck('id')->map(fn($v) => (int) $v)->all();
            $rows = DB::table('student_grades')
                ->whereIn('quiz_result_id', $qrIds)
                ->whereNotNull('quiz_result_id')
                ->pluck('quiz_result_id');
            foreach ($rows as $qrId) {
                $gradedIds[(int) $qrId] = true;
            }
        }

        // Kun bo'yicha summary va sync/mark gap ro'yxatlari
        $summaryDays = [];
        $missingSync = []; // date => [attempt_ids]
        $missingMark = []; // date => [{attempt_id, student_id, ...}]

        foreach ($days as $d) {
            $date = (string) $d['date'];
            $attemptIds = $perDay[$date] ?? [];
            if (!empty($excludedIds)) {
                $attemptIds = array_values(array_filter($attemptIds, fn ($a) => !isset($excludedIds[$a])));
            }
            // Faqat bakalavriat — filtrlangan ro'yxat soni.
            $moodleCount = count($attemptIds);

            $syncedAttemptIds = [];
            $markedDayCount = 0;
            $dayMissingMark = [];

            foreach ($attemptIds as $aid) {
                if ($syncedByAttempt->has($aid)) {
                    $syncedAttemptIds[] = $aid;
                    $row = $syncedByAttempt->get($aid);
                    $qrId = (int) $row->id;
                    if (isset($gradedIds[$qrId])) {
                        $markedDayCount++;
                    } else {
                        $dayMissingMark[] = [
                            'attempt_id'   => (int) $row->attempt_id,
                            'student_id'   => $row->student_id,
                            'student_name' => $row->student_name,
                            'fan_name'     => $row->fan_name,
                            'quiz_type'    => $row->quiz_type,
                            'attempt_name' => $row->attempt_name,
                            'date_finish'  => $row->date_finish,
                            'grade'        => $row->grade,
                        ];
                    }
                }
            }

            $dayMissingSync = array_values(array_diff($attemptIds, $syncedAttemptIds));
            $syncedCount = count($syncedAttemptIds);
            $syncGap = $moodleCount - $syncedCount;
            $markGap = $syncedCount - $markedDayCount;

            $status = 'ok';
            if ($syncGap > 0) $status = 'sync_gap';
            elseif ($markGap > 0) $status = 'mark_gap';

            $summaryDays[] = [
                'date'         => $date,
                'moodle_count' => $moodleCount,
                'synced_count' => $syncedCount,
                'graded_count' => $markedDayCount,
                'sync_gap'     => $syncGap,
                'mark_gap'     => $markGap,
                'status'       => $status,
            ];

            if (!empty($dayMissingSync)) {
                $missingSync[$date] = $dayMissingSync;
            }
            if (!empty($dayMissingMark)) {
                $missingMark[$date] = $dayMissingMark;
            }
        }

        $filename = 'kunlik-monitoring_' . $dateFrom . '_' . $dateTo . '.xlsx';

        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\KunlikMonitoringExport($summaryDays, $missingSync, $missingMark, $dateFrom, $dateTo),
            $filename
        );
    }

    /**
     * Bitta kun ichida yo'qotilgan attemptlar tafsiloti — sync va mark
     * bosqichlarida tushib qolgan attempt_id ro'yxati va sinxronlanganlar
     * uchun talaba/fan ma'lumotlari.
     */
    public function kunlikMonitoringMissing(Request $request)
    {
        $request->validate([
            'date' => 'required|date_format:Y-m-d',
        ]);
        $date = $request->input('date');

        try {
            $svc = app(\App\Services\MoodleSyncMonitorService::class);
            $resp = $svc->getDailySummary($date, $date);
            if (!($resp['ok'] ?? false)) {
                return response()->json([
                    'success' => false,
                    'error' => $resp['error'] ?? 'Moodle WS error',
                ], 502);
            }

            $days = $resp['days'] ?? [];
            $moodleIds = [];
            foreach ($days as $d) {
                if ($d['date'] === $date) {
                    $moodleIds = array_map('intval', (array) ($d['attempt_ids'] ?? []));
                    break;
                }
            }

            // Faqat bakalavriat: ANIQ bakalavr bo'lmagan attempt'larni chiqaramiz.
            $excludedIds = $this->nonBakalavrAttemptIds($moodleIds);
            if (!empty($excludedIds)) {
                $moodleIds = array_values(array_filter($moodleIds, fn ($a) => !isset($excludedIds[$a])));
            }

            if (empty($moodleIds)) {
                return response()->json([
                    'success' => true,
                    'date' => $date,
                    'moodle_count' => 0,
                    'missing_sync' => [],
                    'missing_mark' => [],
                ]);
            }

            // hemis_quiz_results ichidagilar — attempt_id => row.
            $synced = DB::table('hemis_quiz_results')
                ->whereIn('attempt_id', $moodleIds)
                ->select('id', 'attempt_id', 'student_id', 'student_name', 'fan_name', 'quiz_type', 'attempt_name', 'date_finish', 'grade')
                ->get()
                ->keyBy('attempt_id');

            $syncedAttemptIds = $synced->keys()->map(fn($v) => (int) $v)->all();
            $missingSyncIds = array_values(array_diff($moodleIds, $syncedAttemptIds));

            // Hozir grade'i bor quiz_result_id lar.
            $gradedIds = [];
            if ($synced->isNotEmpty()) {
                $qrIds = $synced->pluck('id')->map(fn($v) => (int) $v)->all();
                $rows = DB::table('student_grades')
                    ->whereIn('quiz_result_id', $qrIds)
                    ->whereNotNull('quiz_result_id')
                    ->pluck('quiz_result_id');
                foreach ($rows as $qrId) {
                    $gradedIds[(int) $qrId] = true;
                }
            }

            $missingMark = [];
            foreach ($synced as $row) {
                if (!isset($gradedIds[(int) $row->id])) {
                    $missingMark[] = [
                        'attempt_id'   => (int) $row->attempt_id,
                        'student_id'   => $row->student_id,
                        'student_name' => $row->student_name,
                        'fan_name'     => $row->fan_name,
                        'quiz_type'    => $row->quiz_type,
                        'attempt_name' => $row->attempt_name,
                        'date_finish'  => $row->date_finish,
                        'grade'        => $row->grade,
                    ];
                }
            }

            return response()->json([
                'success' => true,
                'date' => $date,
                'moodle_count' => count($moodleIds),
                'missing_sync' => $missingSyncIds,
                'missing_mark' => $missingMark,
            ]);
        } catch (\Throwable $e) {
            Log::error('kunlikMonitoringMissing failed', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'date' => $date,
            ]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ')',
            ], 500);
        }
    }
}
