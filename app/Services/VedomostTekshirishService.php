<?php

namespace App\Services;

use App\Models\Curriculum;
use App\Models\CurriculumSubject;
use App\Models\Group;
use App\Models\Semester;
use App\Models\Student;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Vedomost tekshirish" hisobi — VedomostTekshirishController::export() dan
 * O'ZGARISHSIZ ko'chirilgan (qoidalar va raqamlar aynan bir xil). Endi bu hisobni
 * Excel eksport ham, vedomost AI tekshiruvi ham ishlatadi — ikkalasi bir xil
 * "tizim" qiymatlarini ko'radi.
 *
 *  - collect(): guruh × fan × semestr bo'yicha har talabaning xom qiymatlari
 *    (JN, MT, ON, OSKI, Test, davomat, JN soni) va guruh ma'lumotlari;
 *  - compute(): xom qiymatlar + vaznlar → ballar, JN+MT, YN, ECTS, baho, zanjir.
 */
class VedomostTekshirishService
{
    /**
     * @return array|null null — fan yoki talabalar topilmasa
     */
    public function collect(Group $group, string $subjectId, string $semesterCode): ?array
    {
        // --- Effective grade helper ---
        $getEffectiveGrade = function ($row) {
            // ENG YUQORI QOIDA: asl baho < 60 va retake (otrabotka) mavjud →
            // retake ustun. Jurnal (JournalController::getEffectiveGrade) va
            // JnMtCalculator bilan bir xil — aks holda vedomostda otrabotka
            // qilingan kunlar past asl baho bilan hisoblanib, JN past chiqadi.
            if ($row->grade !== null && (float) $row->grade < 60 && $row->retake_grade !== null) {
                return (float) $row->retake_grade;
            }
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

        $groupHemisId = $group->group_hemis_id;

        $curriculum = Curriculum::where('curricula_hemis_id', $group->curriculum_hemis_id)->first();
        $educationYearCode = $curriculum?->education_year_code;

        // FAOL va closing_form to'ldirilgan qatorni afzal ko'ramiz — HEMIS sync
        // ba'zan bir xil (subject, curriculum, semester) uchun nofaol/bo'sh
        // (closing_form=NULL) dublikat yaratadi. Tartiblashsiz ->first() o'shani
        // olib, sinov/test farqini noto'g'ri aniqlashi mumkin (jurnal bilan bir xil).
        $subject = CurriculumSubject::where('curricula_hemis_id', $group->curriculum_hemis_id)
            ->where('subject_id', $subjectId)
            ->where('semester_code', $semesterCode)
            ->orderByDesc('is_active')
            ->orderByRaw('closing_form IS NULL')
            ->first();

        if (!$subject) return null;

        $semester = Semester::where('curriculum_hemis_id', $group->curriculum_hemis_id)
            ->where('code', $semesterCode)
            ->first();

        // O'qishdan chetlashganlar (HEMIS holati 60) vedomostga kirmaydi
        $notExpelled = fn ($q) => $q->whereNull('student_status_code')
            ->orWhere('student_status_code', '!=', \App\Http\Middleware\BlockExpelledStudentGrade::EXPELLED_STATUS);
        $students = Student::where('group_id', $groupHemisId)->where($notExpelled)->orderBy('full_name')->get();
        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        if (empty($studentHemisIds)) {
            $students = Student::where('group_id', $group->group_hemis_id)->where($notExpelled)->orderBy('full_name')->get();
            $studentHemisIds = $students->pluck('hemis_id')->toArray();
            if (empty($studentHemisIds)) return null;
        }

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
        // Jurnaldagi AYNAN bir xil tanlash mantig'i (MAX EMAS):
        // is_qoshimcha=0, education_year/minScheduleDate oynasi, attempt=1,
        // effectiveGrade va soxta 'sinov_yn_test' qatorini chetlatish.
        $onOskiTest = \App\Services\JournalGradeService::computeOnOskiTest(
            (string) $groupHemisId,
            (string) $subjectId,
            (string) $semesterCode,
            $studentHemisIds
        );
        $gradesByType = [
            100 => $onOskiTest['on'],
            101 => $onOskiTest['oski'],
            102 => $onOskiTest['test'],
        ];

        // SINOV fani: test bahosi student_grades ga FAQAT YN yuborilganda
        // yoziladi (reason='sinov_yn_test'). YN yuborilmagan bo'lsa, sinov
        // bahosi faqat sinov_test_grades (SinovTestGrade) da yashaydi —
        // jurnal o'shandan o'qiydi. Vedomost jurnalga teng bo'lishi uchun,
        // student_grades da test yo'q talabalar uchun uni SinovTestGrade
        // (override ?? default) yoki JN o'rtachasidan to'ldiramiz.
        //
        // MUHIM: bu backfill FAQAT closing_form === 'sinov' fanlarida ishlaydi.
        // Yopilish shakli 'test' bo'lgan fanda haqiqiy test bahosi student_grades
        // (training_type_code=102) da bo'ladi; agar bu yerda ham SinovTestGrade/JN
        // bilan to'ldirsak, imtihonga kirmagan talabaga JN o'rtachasi test o'rniga
        // tortilib qolardi. Jurnal (JournalController::show) ham aynan shu shartni
        // ('closing_form' === 'sinov') tekshiradi.
        $isSinovSubject = ($subject->closing_form ?? null) === 'sinov';
        $sinovGrades = $isSinovSubject
            ? \App\Models\SinovTestGrade::where('subject_id', (string) $subjectId)
                ->where('semester_code', (string) $semesterCode)
                ->where('group_hemis_id', (string) $groupHemisId)
                ->get()
                ->keyBy('student_hemis_id')
            : collect();
        if ($isSinovSubject && $sinovGrades->isNotEmpty()) {
            foreach ($studentHemisIds as $hid) {
                // student_grades da haqiqiy/sinov_yn_test bahosi bo'lsa — tegmaymiz.
                if (isset($gradesByType[102][$hid]) && $gradesByType[102][$hid] !== null) {
                    continue;
                }
                $sg = $sinovGrades->get($hid);
                $val = $sg ? ($sg->override_grade ?? $sg->default_grade) : null;
                if ($val === null) {
                    $val = $jnGrades[$hid] ?? null;
                }
                if ($val !== null) {
                    $gradesByType[102][(string) $hid] = (int) round((float) $val, 0, PHP_ROUND_HALF_UP);
                }
            }
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


        $raw = [];
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

            $raw[$hId] = compact('jn', 'mt', 'on', 'oski', 'test', 'dav', 'jnCount');
        }

        return compact(
            'groupHemisId', 'curriculum', 'educationYearCode', 'educationYearName', 'subject', 'semester',
            'students', 'studentHemisIds', 'raw', 'lectureTeacher', 'practiceTeacher', 'totalHours', 'divisor'
        );
    }

    /**
     * Bitta talaba: xom qiymatlar + vaznlar → Excel kataklari.
     *
     * @param  array  $raw  collect()['raw'][hemis_id]
     */
    public function compute(array $raw, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest, string $levelCode): array
    {
        ['jn' => $jn, 'mt' => $mt, 'on' => $on, 'oski' => $oski, 'test' => $test, 'dav' => $dav] = $raw;

        // Balllar:
        //  JB/MT/ON ball — yaxlitlashsiz raw qiymat (excelda format
        //  1 kasrni ko'rsatadi);
        //  OSKI/Test ball — ikkalasi ham vaznga ega bo'lsa raw qiymat,
        //  faqat bittasi vaznga ega bo'lsa butun songacha yaxlitlanadi.
        //  Istisno: 4 yoki 5 kurs talabalari uchun ON ball butun
        //  songacha half-up yaxlitlanadi; JN/MT ball esa barcha
        //  kurslarda yaxlitlanmaydi (15.6 → 15.6). Semestrda level_code
        //  qiymatlari: 11=1-kurs, 12=2-kurs, 13=3-kurs, 14=4-kurs,
        //  15=5-kurs, 16=6-kurs.
        $roundJnMtToInt = in_array($levelCode, ['14', '15'], true);

        $jnBall = $jn >= 60 ? $jn * $wJn / 100 : 0;
        $mtBall = $mt >= 60 ? $mt * $wMt / 100 : 0;
        if ($roundJnMtToInt) {
            $onBall = $on >= 60 ? (int) floor($on * $wOn / 100 + 0.5) : 0;
        } else {
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

        // M ustun (JN+MT) — shablon formulasi bilan bir xil:
        // =IF(OR(D<60, G<60), 0, ROUND(E+H, 0.1))
        // JN yoki MT 60 dan kichik bo'lsa M = 0.
        $sumBall = ($jn < 60 || $mt < 60)
            ? 0
            : (int) floor($jnBall + $mtBall + $onBall + 0.5);
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

        return compact(
            'jn', 'mt', 'on', 'oski', 'test', 'dav', 'jnBall', 'mtBall', 'onBall', 'oskiBall', 'testBall',
            'sumBall', 'maxSum', 'yn', 'ects', 'baho', 'zanjir', 'roundJnMtToInt'
        );
    }

    public function getTopTeacher(string $groupHemisId, string $subjectId, string $semesterCode, array $typeCodes): string
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

    public function calcYn(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest, float $dav, float $jnBall = 0.0, float $mtBall = 0.0, float $onBall = 0.0, float $oskiBall = 0.0, float $testBall = 0.0): string|int|float
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

        // Yakuniy ball — har GURUH alohida butun songacha (half-up) yaxlitlanadi,
        // keyin qo'shiladi: round(JN+MT+ON) + round(OSKI+Test). (4-5 kurs uchun
        // ON allaqachon alohida butun songa yaxlitlangan — yuqoriga qarang.)
        $jbMtOnSum = (int) floor($jnBall + $mtBall + $onBall + 0.5);

        if ($wOski > 0 && $wTest > 0) {
            $examSum = (int) floor($oskiBall + $testBall + 0.5);
        } elseif ($wOski > 0) {
            $examSum = (int) floor($oskiBall + 0.5);
        } elseif ($wTest > 0) {
            $examSum = (int) floor($testBall + 0.5);
        } else {
            $examSum = 0;
        }

        return $jbMtOnSum + $examSum;
    }

    public function toEcts(string|int|float $yn): string
    {
        if (!is_numeric($yn) || $yn < 0) return '';
        $yn = (float) $yn;
        if ($yn >= 90) return 'A';
        if ($yn >= 85) return 'B+';
        if ($yn >= 70) return 'B';
        if ($yn >= 60) return 'C';
        return 'F';
    }

    public function toBaho(string|int|float $yn): string
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

    public function checkZanjir(int $jn, int $mt, int $on, int|float $oski, int|float $test, int $wJn, int $wMt, int $wOn, int $wOski, int $wTest): string
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
}
