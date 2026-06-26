<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN bosqich (stage) va aktiv shakllarini bitta (guruh, fan, semestr) uchun
 * hisoblaydi — JournalController::exportYnQaydnoma() dagi AYNAN bir xil mantiq
 * (faqat bosqich/activeForms uchun kerakli qism; Excel/sarlavha/o'qituvchi qismi yo'q).
 *
 * Maqsad: qaydnoma va vedomost "qo'shimcha shakl" aniqlashda BIR manbadan
 * foydalanishi (mantiq farqlanmasligi uchun). Faqat o'qiydi — hech narsani
 * o'zgartirmaydi (exportYnQaydnoma dagi sinov backfill bu yerda CHAQIRILMAYDI).
 *
 * Eslatma: V hisobi imtihon vaznlariga bog'liq. Qaydnomada operator qo'lda
 * kiritadi; vedomost sync uchun vaznlar closing_form'dan keltiriladi
 * (weightsForClosingForm()).
 */
class YnStageService
{
    /**
     * Yopilish shakliga ko'ra standart vaznlar (qaydnoma default'lari bilan mos):
     * JN=30, MT=10, ON=0 doimiy; imtihon 60 — test/oski/oski_test bo'yicha taqsimlanadi.
     *
     * @return array{jn:int,mt:int,on:int,oski:int,test:int}
     */
    public static function weightsForClosingForm(?string $closingForm): array
    {
        $base = ['jn' => 30, 'mt' => 10, 'on' => 0];

        return match ($closingForm) {
            'oski'      => $base + ['oski' => 60, 'test' => 0],
            'oski_test' => $base + ['oski' => 30, 'test' => 30],
            // 'test', 'sinov', 'normativ' va boshqalar — test imtihonli deb olinadi.
            default     => $base + ['oski' => 0, 'test' => 60],
        };
    }

    /**
     * Bitta (guruh, fan, semestr) uchun bosqichlar va aktiv shakllarni hisoblaydi.
     *
     * @return array{
     *   stages: array<string,string>,
     *   activeForms: array<string,bool>,
     *   studentScenarios: array<string,array>
     * }|null  null — guruh/fan topilmasa.
     */
    public function computeForGroupSubject(
        string $groupHemisId,
        string $subjectId,
        string $semesterCode,
        ?int $weightJn = null,
        ?int $weightMt = null,
        ?int $weightOn = null,
        ?int $weightOski = null,
        ?int $weightTest = null
    ): ?array {
        $hasSababliCol = Schema::hasColumn('student_grades', 'retake_was_sababli');
        $hasAttemptCol = Schema::hasColumn('student_grades', 'attempt');

        $group = Group::where('group_hemis_id', $groupHemisId)->first();
        if (!$group) {
            return null;
        }

        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->first();
        if (!$subject) {
            return null;
        }

        // Vaznlar berilmagan bo'lsa — closing_form'dan.
        if ($weightJn === null) {
            $w = self::weightsForClosingForm($subject->closing_form ?? null);
            $weightJn = $w['jn']; $weightMt = $w['mt']; $weightOn = $w['on'];
            $weightOski = $w['oski']; $weightTest = $w['test'];
        }

        $students = Student::where('group_id', $groupHemisId)->orderBy('full_name')->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        if (empty($studentHemisIds)) {
            return ['stages' => [], 'activeForms' => YnAttemptStatusService::activeFormsInGroup([]), 'studentScenarios' => []];
        }

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();

        // O'quv yili kodi (exportYnQaydnoma bilan bir xil).
        $educationYearCode = $curriculum?->education_year_code;
        $scheduleEducationYear = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->whereNotNull('lesson_date')
            ->whereNotNull('education_year_code')
            ->orderBy('lesson_date', 'desc')
            ->value('education_year_code');
        if ($scheduleEducationYear) {
            $educationYearCode = $scheduleEducationYear;
        }

        $excludedTrainingTypes = ["Ma'ruza", "Mustaqil ta'lim", "Oraliq nazorat", "Oski", "Yakuniy test", "Quiz test"];
        $excludedTrainingCodes = config('app.training_type_code', [11, 99, 100, 101, 102, 103]);

        // JB (amaliyot) jadval sanalari
        $jbScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->whereNotIn('training_type_name', $excludedTrainingTypes)
            ->whereNotIn('training_type_code', $excludedTrainingCodes)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')->orderBy('lesson_pair_code')
            ->get();

        $jbColumns = $jbScheduleRows->map(fn($s) => [
            'date' => \Carbon\Carbon::parse($s->lesson_date)->format('Y-m-d'),
            'pair' => $s->lesson_pair_code,
        ])->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();
        $jbLessonDates = $jbColumns->pluck('date')->unique()->sort()->values()->toArray();
        $jbPairsPerDay = [];
        foreach ($jbColumns as $col) {
            $jbPairsPerDay[$col['date']] = ($jbPairsPerDay[$col['date']] ?? 0) + 1;
        }
        $jbDatePairSet = [];
        foreach ($jbColumns as $col) {
            $jbDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }

        $gradingCutoffDate = \Carbon\Carbon::now('Asia/Tashkent')->endOfDay();
        $jbLessonDatesForAverage = array_values(array_filter($jbLessonDates, function ($date) use ($gradingCutoffDate) {
            return \Carbon\Carbon::parse($date, 'Asia/Tashkent')->startOfDay()->lte($gradingCutoffDate);
        }));
        $jbLessonDatesForAverageLookup = array_flip($jbLessonDatesForAverage);
        $totalJbDaysForAverage = count($jbLessonDatesForAverage);

        // MT jadval sanalari
        $mtScheduleRows = DB::table('schedules')
            ->where('group_id', $groupHemisId)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNull('deleted_at')
            ->when($educationYearCode !== null, fn($q) => $q->where('education_year_code', $educationYearCode))
            ->where('training_type_code', 99)
            ->whereNotNull('lesson_date')
            ->select('lesson_date', 'lesson_pair_code')
            ->orderBy('lesson_date')->orderBy('lesson_pair_code')
            ->get();

        $mtColumns = $mtScheduleRows->map(fn($s) => [
            'date' => \Carbon\Carbon::parse($s->lesson_date)->format('Y-m-d'),
            'pair' => $s->lesson_pair_code,
        ])->unique(fn($item) => $item['date'] . '_' . $item['pair'])->values();
        $mtLessonDates = $mtColumns->pluck('date')->unique()->sort()->values()->toArray();
        $mtPairsPerDay = [];
        foreach ($mtColumns as $col) {
            $mtPairsPerDay[$col['date']] = ($mtPairsPerDay[$col['date']] ?? 0) + 1;
        }
        $mtDatePairSet = [];
        foreach ($mtColumns as $col) {
            $mtDatePairSet[$col['date'] . '_' . $col['pair']] = true;
        }
        $totalMtDays = count($mtLessonDates);

        $minScheduleDate = collect()
            ->merge($jbColumns->pluck('date'))
            ->merge($mtColumns->pluck('date'))
            ->min();

        // JB/MT dars baholari (attempt=1)
        $allGradesRaw = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->whereNotIn('training_type_code', [100, 101, 102, 103])
            ->whereNotNull('lesson_date')
            ->when($hasAttemptCol, fn($q) => $q->where(function ($qq) {
                $qq->where('attempt', 1)->orWhereNull('attempt');
            }))
            ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                $q2->where('education_year_code', $educationYearCode)
                    ->orWhere(function ($q3) use ($minScheduleDate) {
                        $q3->whereNull('education_year_code')
                            ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                    });
            }))
            ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
            ->select(array_merge(
                ['student_hemis_id', 'lesson_date', 'lesson_pair_code', 'grade', 'retake_grade', 'status', 'reason'],
                $hasSababliCol ? ['retake_was_sababli'] : []
            ))
            ->orderBy('lesson_date')->orderBy('lesson_pair_code')
            ->get();

        $getEffectiveGrade = function ($row, bool $includeSababli = true) {
            $retakeGrade = $row->retake_grade;
            if (!$includeSababli && !empty($row->retake_was_sababli)) {
                $retakeGrade = null;
            }
            if ($row->grade !== null && (float) $row->grade < 60 && $retakeGrade !== null) {
                return $retakeGrade;
            }
            if ($row->status === 'pending' && $row->reason === 'low_grade' && $row->grade !== null) {
                return $row->grade;
            }
            if ($row->status === 'pending') return null;
            if ($row->reason === 'absent' && $row->grade === null) {
                return $retakeGrade !== null ? $retakeGrade : null;
            }
            if ($row->status === 'closed' && $row->reason === 'teacher_victim' && $row->grade == 0 && $retakeGrade === null) {
                return null;
            }
            if ($row->status === 'recorded') return $row->grade;
            if ($row->status === 'closed') return $row->grade;
            if ($retakeGrade !== null) return $retakeGrade;
            return null;
        };

        $jbGradesV1 = [];
        $jbGradesV2 = [];
        $mtGradesMapV1 = [];
        $mtGradesMapV2 = [];
        foreach ($allGradesRaw as $g) {
            $normalizedDate = \Carbon\Carbon::parse($g->lesson_date)->format('Y-m-d');
            $key = $normalizedDate . '_' . $g->lesson_pair_code;
            $effV1 = $getEffectiveGrade($g, false);
            $effV2 = $getEffectiveGrade($g, true);
            if (isset($jbDatePairSet[$key])) {
                if ($effV1 !== null) $jbGradesV1[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effV1;
                if ($effV2 !== null) $jbGradesV2[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effV2;
            }
            if (isset($mtDatePairSet[$key])) {
                if ($effV1 !== null) $mtGradesMapV1[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effV1;
                if ($effV2 !== null) $mtGradesMapV2[$g->student_hemis_id][$normalizedDate][$g->lesson_pair_code] = $effV2;
            }
        }

        $manualMtGrades = DB::table('student_grades')
            ->whereNull('deleted_at')
            ->whereIn('student_hemis_id', $studentHemisIds)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->where('training_type_code', 99)
            ->whereNull('lesson_date')
            ->whereNotNull('grade')
            ->select('student_hemis_id', 'grade')
            ->get()
            ->keyBy('student_hemis_id');

        $computeJnMt = function (array $jbGradesMap, array $mtGradesMapLocal) use (
            $studentHemisIds, $jbLessonDates, $jbPairsPerDay, $jbLessonDatesForAverageLookup,
            $totalJbDaysForAverage, $mtLessonDates, $mtPairsPerDay, $totalMtDays, $manualMtGrades
        ) {
            $jn = [];
            $mt = [];
            foreach ($studentHemisIds as $hemisId) {
                $dailySum = 0;
                $studentDayGrades = $jbGradesMap[$hemisId] ?? [];
                foreach ($jbLessonDates as $date) {
                    $dayGrades = $studentDayGrades[$date] ?? [];
                    $pairsInDay = $jbPairsPerDay[$date] ?? 1;
                    $gradeSum = array_sum($dayGrades);
                    $dayAverage = round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                    if (isset($jbLessonDatesForAverageLookup[$date])) {
                        $dailySum += $dayAverage;
                    }
                }
                $jn[$hemisId] = $totalJbDaysForAverage > 0
                    ? round($dailySum / $totalJbDaysForAverage, 0, PHP_ROUND_HALF_UP)
                    : 0;

                $mtDailySum = 0;
                $studentMtGrades = $mtGradesMapLocal[$hemisId] ?? [];
                foreach ($mtLessonDates as $date) {
                    $dayGrades = $studentMtGrades[$date] ?? [];
                    $pairsInDay = $mtPairsPerDay[$date] ?? 1;
                    $gradeSum = array_sum($dayGrades);
                    $mtDailySum += round($gradeSum / $pairsInDay, 0, PHP_ROUND_HALF_UP);
                }
                $mt[$hemisId] = $totalMtDays > 0
                    ? round($mtDailySum / $totalMtDays, 0, PHP_ROUND_HALF_UP)
                    : 0;

                if (isset($manualMtGrades[$hemisId])) {
                    $mt[$hemisId] = round((float) $manualMtGrades[$hemisId]->grade, 0, PHP_ROUND_HALF_UP);
                }
            }
            return [$jn, $mt];
        };

        [$calculatedJnGradesV1, $calculatedMtGradesV1] = $computeJnMt($jbGradesV1, $mtGradesMapV1);
        [$calculatedJnGradesV2, $calculatedMtGradesV2] = $computeJnMt($jbGradesV2, $mtGradesMapV2);

        $fetchOtherGradesByAttempt = function (?int $attempt) use ($studentHemisIds, $subjectId, $semesterCode, $educationYearCode, $minScheduleDate, $hasSababliCol, $hasAttemptCol) {
            return DB::table('student_grades')
                ->whereNull('deleted_at')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('subject_id', $subjectId)
                ->where('semester_code', $semesterCode)
                ->whereIn('training_type_code', [100, 101, 102, 103])
                ->when($hasAttemptCol && $attempt !== null, function ($q) use ($attempt) {
                    if ($attempt === 1) {
                        $q->where(function ($qq) {
                            $qq->where('attempt', 1)->orWhereNull('attempt');
                        });
                    } else {
                        $q->where('attempt', $attempt);
                    }
                })
                ->when($educationYearCode !== null, fn($q) => $q->where(function ($q2) use ($educationYearCode, $minScheduleDate) {
                    $q2->where('education_year_code', $educationYearCode)
                        ->orWhere(function ($q3) use ($minScheduleDate) {
                            $q3->whereNull('education_year_code')
                                ->when($minScheduleDate !== null, fn($q4) => $q4->where('lesson_date', '>=', $minScheduleDate));
                        });
                }))
                ->when($educationYearCode === null && $minScheduleDate !== null, fn($q) => $q->where('lesson_date', '>=', $minScheduleDate))
                ->select(array_merge(
                    ['student_hemis_id', 'training_type_code', 'grade', 'retake_grade', 'status', 'reason', 'quiz_result_id'],
                    $hasSababliCol ? ['retake_was_sababli'] : []
                ))
                ->get();
        };

        $otherGradesAttempt1 = $fetchOtherGradesByAttempt(1);
        $otherGradesAttempt2 = $hasAttemptCol ? $fetchOtherGradesByAttempt(2) : collect();
        $otherGradesAttempt3 = $hasAttemptCol ? $fetchOtherGradesByAttempt(3) : collect();

        $buildOtherGrades = function ($rows, bool $includeSababli) use ($getEffectiveGrade) {
            $grouped = [];
            foreach ($rows as $g) {
                $effectiveGrade = $getEffectiveGrade($g, $includeSababli);
                if ($effectiveGrade === null) continue;
                $typeCode = $g->training_type_code;
                if ($typeCode == 103 && $g->quiz_result_id) {
                    $quizType = DB::table('hemis_quiz_results')->where('id', $g->quiz_result_id)->value('quiz_type');
                    $oskiTypes = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];
                    $testTypes = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];
                    if (in_array($quizType, $oskiTypes)) {
                        $typeCode = 101;
                    } elseif (in_array($quizType, $testTypes)) {
                        $typeCode = 102;
                    }
                }
                $grouped[$g->student_hemis_id][$typeCode][] = $effectiveGrade;
            }
            $byType = [100 => [], 101 => [], 102 => []];
            foreach ($grouped as $studentId => $types) {
                foreach ([100, 101, 102] as $tc) {
                    if (isset($types[$tc]) && count($types[$tc]) > 0) {
                        $byType[$tc][$studentId] = array_sum($types[$tc]) / count($types[$tc]);
                    }
                }
            }
            return $byType;
        };

        $gradesByTypeV1 = $buildOtherGrades($otherGradesAttempt1, false);
        $gradesByTypeV2 = $buildOtherGrades($otherGradesAttempt1, true);
        $gradesByTypeAv1 = $buildOtherGrades($otherGradesAttempt2, false);
        $gradesByTypeAv2 = $buildOtherGrades($otherGradesAttempt2, true);
        $gradesByTypeBv1 = $buildOtherGrades($otherGradesAttempt3, false);
        $gradesByTypeBv2 = $buildOtherGrades($otherGradesAttempt3, true);

        // Davomat (auditoriya soatlariga nisbatan absent_off foizi)
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

        // 12a/12b fallback chain (exportYnQaydnoma bilan bir xil)
        $mergeOskiTest = function (array $primary, array $fallback) use ($studentHemisIds) {
            $result = [100 => [], 101 => [], 102 => []];
            foreach ($studentHemisIds as $hid) {
                foreach ([100, 101, 102] as $tc) {
                    $val = $primary[$tc][$hid] ?? $fallback[$tc][$hid] ?? null;
                    if ($val !== null) {
                        $result[$tc][$hid] = $val;
                    }
                }
            }
            return $result;
        };

        $gradesByType12aMain = $mergeOskiTest($gradesByTypeAv1, $gradesByTypeV2);
        $gradesByType12aQosh = $mergeOskiTest($gradesByTypeAv2, $gradesByTypeV2);
        $gradesByType12bMain = $mergeOskiTest($gradesByTypeBv1, $gradesByType12aMain);
        $gradesByType12bQosh = $mergeOskiTest($gradesByTypeBv2, $gradesByType12aQosh);

        $levelCodeForRounding = (string) ($semester?->level_code ?? '');

        $computeV = function (
            int $jnVal, int $mtVal, ?float $onRaw, ?float $oskiRaw, ?float $testRaw, float $davomatPct
        ) use ($weightJn, $weightMt, $weightOn, $weightOski, $weightTest, $levelCodeForRounding) {
            $onVal = $onRaw !== null ? (int) round((float) $onRaw) : 0;
            $oskiVal = $oskiRaw !== null ? (int) round((float) $oskiRaw) : 0;
            $testVal = $testRaw !== null ? (int) round((float) $testRaw) : 0;
            $davomatFailed = $davomatPct >= 25;
            $roundJnMtToInt = in_array($levelCodeForRounding, ['14', '15'], true);

            if ($roundJnMtToInt) {
                $eBall = $jnVal >= 60 ? (int) floor($jnVal * $weightJn / 100 + 0.5) : 0;
                $hBall = $mtVal >= 60 ? (int) floor($mtVal * $weightMt / 100 + 0.5) : 0;
                $kBall = $onVal >= 60 ? (int) floor($onVal * $weightOn / 100 + 0.5) : 0;
            } else {
                $eBall = $jnVal >= 60 ? round($jnVal * $weightJn / 100, 1) : 0;
                $hBall = $mtVal >= 60 ? round($mtVal * $weightMt / 100, 1) : 0;
                $kBall = $onVal >= 60 ? round($onVal * $weightOn / 100, 1) : 0;
            }
            if ($weightOski > 0 && $weightTest > 0) {
                $qBall = $oskiVal >= 60 ? $oskiVal * $weightOski / 100 : 0;
                $tBall = $testVal >= 60 ? $testVal * $weightTest / 100 : 0;
            } elseif ($weightOski > 0) {
                $qBall = $oskiVal >= 60 ? (int) round($oskiVal * $weightOski / 100) : 0;
                $tBall = 0;
            } elseif ($weightTest > 0) {
                $qBall = 0;
                $tBall = $testVal >= 60 ? (int) round($testVal * $weightTest / 100) : 0;
            } else {
                $qBall = 0; $tBall = 0;
            }

            $maxJbMtOn = $weightJn + $weightMt + $weightOn;
            $mSum = (($jnVal < 60) || ($mtVal < 60)) ? 0 : round($eBall + $hBall + $kBall, 1);
            $nPct = $maxJbMtOn > 0 ? $mSum / $maxJbMtOn : 0;

            if ($jnVal === 0 && $mtVal === 0) {
                $v = '';
            } elseif ($davomatFailed) {
                $v = -3;
            } elseif ($nPct < 0.6) {
                $v = -2;
            } elseif (($weightOski > 0 && $oskiVal == 0) || ($weightTest > 0 && $testVal == 0)) {
                $v = -1;
            } elseif (($weightJn > 0 && $jnVal < 60) || ($weightMt > 0 && $mtVal < 60)
                || ($weightOn > 0 && $onVal < 60)
                || ($weightOski > 0 && $oskiVal < 60) || ($weightTest > 0 && $testVal < 60)) {
                $v = 0;
            } else {
                $jbMtOnSum = (int) floor($eBall + $hBall + $kBall + 0.5);
                if ($weightOski > 0 && $weightTest > 0) {
                    $examSum = (int) floor($qBall + $tBall + 0.5);
                } elseif ($weightOski > 0) {
                    $examSum = (int) floor($qBall + 0.5);
                } elseif ($weightTest > 0) {
                    $examSum = (int) floor($tBall + 0.5);
                } else {
                    $examSum = 0;
                }
                $v = $jbMtOnSum + $examSum;
            }

            return $v;
        };

        // Har talaba uchun 6 ssenariy → bosqich (exportYnQaydnoma:9546-9589 bilan bir xil)
        $stages = [];
        $studentScenarios = [];
        foreach ($students as $stu) {
            $h = $stu->hemis_id;
            $davomatPct = (float) ($davomatByStudent[$h] ?? 0);

            $build = function (int $jn, int $mt, array $other) use ($h, $computeV, $davomatPct) {
                $on = $other[100][$h] ?? null;
                $oski = $other[101][$h] ?? null;
                $test = $other[102][$h] ?? null;
                return [
                    'v' => $computeV($jn, $mt, $on, $oski, $test, $davomatPct),
                    'jn' => $jn, 'mt' => $mt,
                    'oski' => $oski !== null ? (int) round((float) $oski) : null,
                    'test' => $test !== null ? (int) round((float) $test) : null,
                ];
            };

            $jn1 = (int) ($calculatedJnGradesV1[$h] ?? 0);
            $mt1 = (int) ($calculatedMtGradesV1[$h] ?? 0);
            $jn2 = (int) ($calculatedJnGradesV2[$h] ?? 0);
            $mt2 = (int) ($calculatedMtGradesV2[$h] ?? 0);

            $main       = $build($jn1, $mt1, $gradesByTypeV1);
            $qoshimcha  = $build($jn2, $mt2, $gradesByTypeV2);
            $aHas       = isset($gradesByTypeAv1[100][$h]) || isset($gradesByTypeAv1[101][$h]) || isset($gradesByTypeAv1[102][$h]);
            $aQHas      = isset($gradesByTypeAv2[100][$h]) || isset($gradesByTypeAv2[101][$h]) || isset($gradesByTypeAv2[102][$h]);
            $bHas       = isset($gradesByTypeBv1[100][$h]) || isset($gradesByTypeBv1[101][$h]) || isset($gradesByTypeBv1[102][$h]);
            $bQHas      = isset($gradesByTypeBv2[100][$h]) || isset($gradesByTypeBv2[101][$h]) || isset($gradesByTypeBv2[102][$h]);

            $a          = $aHas ? $build($jn2, $mt2, $gradesByType12aMain) : null;
            $aQoshimcha = $aQHas ? $build($jn2, $mt2, $gradesByType12aQosh) : null;
            $b          = $bHas ? $build($jn2, $mt2, $gradesByType12bMain) : null;
            $bQoshimcha = $bQHas ? $build($jn2, $mt2, $gradesByType12bQosh) : null;

            $stage = YnAttemptStatusService::determineStage(
                $main, $qoshimcha, $a, $aQoshimcha, $b, $bQoshimcha
            );

            $stages[$h] = $stage['stage'];
            $studentScenarios[$h] = compact('main', 'qoshimcha', 'a', 'aQoshimcha', 'b', 'bQoshimcha');
        }

        $activeForms = YnAttemptStatusService::activeFormsInGroup(
            array_map(fn($s) => ['stage' => $s], $stages)
        );

        return [
            'stages' => $stages,
            'activeForms' => $activeForms,
            'studentScenarios' => $studentScenarios,
        ];
    }
}
