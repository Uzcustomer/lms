<?php

namespace App\Http\Controllers\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * O'tgan semestr qarzdorliklarini hisoblash uchun umumiy mantiq.
 *
 * Admin (ReportController) va Tyutor (TutorReportController) hisobotlari aynan
 * bir xil natija berishi uchun qarz aniqlash algoritmi shu yerda jamlangan.
 *
 * Muhim qoidalar:
 *  - O'tgan semestrlar academic_records.semester_id orqali aniqlanadi (har
 *    semestr uchun TARIXIY curriculum_id ishlatiladi — transfer/tiklangan
 *    talabalar uchun joriy reja emas, o'sha paytdagi reja muhim).
 *  - academic_records da baho mavjud bo'lsa — qarz emas.
 *  - student_subjects (biriktirish) bor bo'lsa — faqat biriktirilgan fanlar;
 *    biriktirilmagan + baho yo'q fan "noaniq" qarz deb belgilanadi.
 *  - Tanlov fanlar (subject_type_code=12) — talabaning haqiqiy tanlovi.
 */
trait ComputesStudentDebts
{
    /**
     * Talabalar to'plami uchun o'tgan semestr qarzlarini hisoblaydi.
     *
     * @param  \Illuminate\Support\Collection  $students  hemis_id, full_name,
     *         student_id_number, group_name, group_id, semester_code,
     *         curriculum_id (va ixtiyoriy image, *_name) maydonlari bilan.
     * @param  int    $minDebtCount         Minimal qarz soni (filtr).
     * @param  bool   $showCurrentSemester  Faqat joriy semestrni ko'rsatish.
     * @param  array  $currentRisksMap      [hemis_id => [...joriy xavflar]].
     * @return array  finalResults ro'yxati.
     */
    protected function computeDebtorResults($students, int $minDebtCount, bool $showCurrentSemester, array $currentRisksMap = []): array
    {
        $studentHemisIds = $students->pluck('hemis_id')->toArray();
        if (empty($studentHemisIds)) {
            return [];
        }

        // academic_records — mavjudlik (qarz aniqlash) va har semestrdagi tarixiy curriculum_id
        $arRecords = [];
        foreach (array_chunk($studentHemisIds, 1000) as $chunk) {
            $arRecords = array_merge($arRecords, DB::table('academic_records')
                ->whereIn('student_id', $chunk)
                ->select('student_id', 'subject_id', 'semester_id', 'curriculum_id')
                ->get()
                ->all());
        }

        $arExistsLookup = [];
        $studentSemCurr = []; // [hemis_id][semester_code] => curriculum_id
        foreach ($arRecords as $ar) {
            $arExistsLookup[$ar->student_id . '|' . $ar->subject_id . '|' . $ar->semester_id] = true;
            if (!isset($studentSemCurr[$ar->student_id][$ar->semester_id]) && $ar->curriculum_id) {
                $studentSemCurr[$ar->student_id][$ar->semester_id] = $ar->curriculum_id;
            }
        }
        unset($arRecords);

        // (curriculum_id, semester_code) juftliklari
        $curriculumPairs = [];
        foreach ($students as $st) {
            $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
            foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                $curriculumPairs[$currId . '|' . $semCode] = true;
            }
            if ($st->curriculum_id && $studentSemCode) {
                $curriculumPairs[$st->curriculum_id . '|' . $studentSemCode] = true;
            }
        }

        $allCurriculumIds = collect($curriculumPairs)->keys()
            ->map(fn ($k) => explode('|', $k)[0])->unique()->values()->all();
        $allSemCodes = collect($curriculumPairs)->keys()
            ->map(fn ($k) => explode('|', $k)[1])->unique()->values()->all();

        $currSubjectsQuery = DB::table('curriculum_subjects as cs')
            ->whereIn('cs.curricula_hemis_id', $allCurriculumIds ?: [0])
            ->whereIn('cs.semester_code', $allSemCodes ?: [0])
            ->where('cs.is_active', 1)
            ->where(function ($q) {
                $q->whereNull('cs.in_group')->orWhere('cs.in_group', '');
            })
            ->select(
                'cs.curricula_hemis_id', 'cs.curriculum_subject_hemis_id',
                'cs.semester_code', 'cs.semester_name',
                'cs.subject_id', 'cs.subject_name', 'cs.subject_type_code',
                'cs.credit', 'cs.total_acload'
            )
            ->distinct();

        $excludedPatterns = config('app.excluded_rating_subject_patterns', []);
        foreach ($excludedPatterns as $pattern) {
            $currSubjectsQuery->where('cs.subject_name', 'NOT LIKE', "%{$pattern}%");
        }

        $currSubjects = $currSubjectsQuery->get();
        $subjectsByPair = $currSubjects->groupBy(fn ($s) => $s->curricula_hemis_id . '|' . $s->semester_code);

        // Tanlov fanlar (subject_type_code=12) — talaba haqiqiy tanlovi
        $tanlovCsHemisIds = $currSubjects
            ->where('subject_type_code', '12')
            ->pluck('curriculum_subject_hemis_id')
            ->filter()->unique()->values()->toArray();

        $tanlovPicksMap = [];
        if (!empty($tanlovCsHemisIds)) {
            $tanlovPicks = DB::table('student_subjects')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('curriculum_subject_hemis_id', $tanlovCsHemisIds)
                ->select('student_hemis_id', 'curriculum_subject_hemis_id', 'subject_id', 'subject_name')
                ->get();
            foreach ($tanlovPicks as $tp) {
                $tanlovPicksMap[$tp->student_hemis_id . '|' . $tp->curriculum_subject_hemis_id] = [
                    'subject_id'   => $tp->subject_id,
                    'subject_name' => $tp->subject_name,
                ];
            }
        }

        // Biriktirilgan fanlar (student_subjects) — o'tgan semestrlar uchun
        $studentSubjectsMap = [];
        $studentSubjectsHasSemester = [];
        $allPastSemCodes = [];
        foreach ($studentHemisIds as $hId) {
            foreach ($studentSemCurr[$hId] ?? [] as $sc => $cId) {
                $allPastSemCodes[$sc] = true;
            }
        }
        $allPastSemCodes = array_keys($allPastSemCodes);
        if (!empty($allPastSemCodes)) {
            $ssRows = DB::table('student_subjects')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->whereIn('semester_id', $allPastSemCodes)
                ->whereNotNull('subject_id')
                ->select('student_hemis_id', 'semester_id', 'subject_id')
                ->get();
            foreach ($ssRows as $sr) {
                $k = $sr->student_hemis_id . '|' . (string) $sr->semester_id;
                $studentSubjectsMap[$k][$sr->subject_id] = true;
                $studentSubjectsHasSemester[$k] = true;
            }
        }

        $finalResults = [];
        foreach ($students as $st) {
            if (!$st->curriculum_id) continue;

            $studentSemCode = $st->semester_code ? (int) $st->semester_code : null;
            $debts = [];

            $studentPairs = []; // [sem_code => curriculum_id]
            if ($showCurrentSemester) {
                if ($studentSemCode) {
                    $studentPairs[$studentSemCode] = $st->curriculum_id;
                }
            } else {
                foreach ($studentSemCurr[$st->hemis_id] ?? [] as $semCode => $currId) {
                    if (!$studentSemCode || (int) $semCode < $studentSemCode) {
                        $studentPairs[(int) $semCode] = $currId;
                    }
                }
            }

            foreach ($studentPairs as $semCode => $currId) {
                $subjectsForSem = $subjectsByPair->get($currId . '|' . $semCode, collect());
                $subjectsForSem = $this->filterSubjectsByGroupSuffix($subjectsForSem, $st->group_name ?? '');

                foreach ($subjectsForSem as $sub) {
                    $effectiveSubjectId = $sub->subject_id;
                    $effectiveSubjectName = $sub->subject_name;
                    if ((string) $sub->subject_type_code === '12') {
                        $picked = $tanlovPicksMap[$st->hemis_id . '|' . $sub->curriculum_subject_hemis_id] ?? null;
                        if ($picked) {
                            $effectiveSubjectId = $picked['subject_id'];
                            $effectiveSubjectName = $picked['subject_name'];
                        } else {
                            continue;
                        }
                    }

                    $arKey = $st->hemis_id . '|' . $effectiveSubjectId . '|' . $sub->semester_code;
                    $hasAR = isset($arExistsLookup[$arKey]);

                    $ssKey = $st->hemis_id . '|' . (string) $sub->semester_code;
                    $hasSsForSem = $studentSubjectsHasSemester[$ssKey] ?? false;
                    $isEnrolled = !$hasSsForSem || isset($studentSubjectsMap[$ssKey][$effectiveSubjectId]);

                    if ($hasAR) {
                        continue;
                    }

                    if (!$isEnrolled) {
                        $debts[] = [
                            'subject_id'    => $effectiveSubjectId,
                            'subject_name'  => $effectiveSubjectName,
                            'semester_code' => $sub->semester_code,
                            'semester_name' => $sub->semester_name,
                            'credit'        => $sub->credit,
                            'total_acload'  => $sub->total_acload,
                            'status'        => 'noaniq',
                        ];
                        continue;
                    }

                    $debts[] = [
                        'subject_id'    => $effectiveSubjectId,
                        'subject_name'  => $effectiveSubjectName,
                        'semester_code' => $sub->semester_code,
                        'semester_name' => $sub->semester_name,
                        'credit'        => $sub->credit,
                        'total_acload'  => $sub->total_acload,
                    ];
                }
            }

            $debtCount = count($debts);
            $currentRisks = $currentRisksMap[$st->hemis_id] ?? [];
            if ($debtCount < $minDebtCount && empty($currentRisks)) continue;

            usort($debts, fn ($a, $b) => $a['semester_code'] <=> $b['semester_code']);

            $finalResults[] = [
                'hemis_id'          => $st->hemis_id,
                'full_name'         => $st->full_name ?? 'Noma\'lum',
                'student_id_number' => $st->student_id_number ?? '-',
                'student_id'        => $st->student_id_number ?? '-',
                'image'             => $st->image ?? null,
                'department_name'   => $st->department_name ?? '-',
                'specialty_name'    => $st->specialty_name ?? '-',
                'level_name'        => $st->level_name ?? '-',
                'semester_name'     => $st->semester_name ?? '-',
                'group_name'        => $st->group_name ?? '-',
                'group_id'          => $st->group_id ?? '',
                'student_type_name' => $st->student_type_name ?? null,
                'debt_count'        => $debtCount,
                'debt_count_curr'   => $debtCount,
                'debt_count_ss'     => null,
                'debt_status'       => '',
                'lesson_days'       => 0,
                'debts'             => $debts,
                'current_risks'     => $currentRisks,
                'current_risk_count' => count($currentRisks),
            ];
        }

        return $finalResults;
    }

    /**
     * Guruh suffiksi (a/b/c) bo'yicha fanlarni filtrlash.
     */
    protected function filterSubjectsByGroupSuffix($records, $groupName)
    {
        if (empty($groupName)) {
            return $records;
        }

        $groupSuffix = '';
        if (preg_match('/(\d+)([a-zA-Z])$/', trim($groupName), $m)) {
            $groupSuffix = mb_strtolower($m[2]);
        }

        if (empty($groupSuffix)) {
            return $records;
        }

        return $records->filter(function ($record) use ($groupSuffix) {
            $name = $record->subject_name ?? '';
            if (preg_match('/\(([a-zA-Zа-яА-Я])\)\s*$/u', $name, $m)) {
                return mb_strtolower($m[1]) === $groupSuffix;
            }
            return true;
        })->values();
    }

    /**
     * Joriy semestr bo'yicha xavf ostidagi talabalarni student_grades dan hisoblash.
     * Qaytaradi: [hemis_id => [['subject_name'=>..., 'reasons'=>[...]], ...]]
     */
    protected function getCurrentSemesterRisks(array $studentHemisIds, array $studentSemCodesMap = []): array
    {
        if (empty($studentHemisIds)) return [];

        if (!empty($studentSemCodesMap)) {
            $currentSemesterCodes = array_values(array_unique(array_map('strval', $studentSemCodesMap)));
        } else {
            $currentSemesterCodes = DB::table('semesters')
                ->where('current', true)
                ->pluck('code')
                ->toArray();
        }

        if (empty($currentSemesterCodes)) return [];

        $hasAttemptCol = \Illuminate\Support\Facades\Schema::hasColumn('student_grades', 'attempt');

        // Barcha joriy semestr student_grades yozuvlari
        $grades = collect();
        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            $chunk_grades = DB::table('student_grades')
                ->whereIn('student_hemis_id', $chunk)
                ->whereIn('semester_code', $currentSemesterCodes)
                ->whereNull('deleted_at')
                ->select(
                    'student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                    'training_type_code', 'grade', 'retake_grade', 'status', 'reason',
                    $hasAttemptCol ? 'attempt' : DB::raw('1 as attempt'),
                    'lesson_date', 'education_year_code'
                )
                ->get();
            $grades = $grades->merge($chunk_grades);
        }

        if ($grades->isEmpty()) return [];

        // Biriktirilganlik (enrollment) — student_subjects + JORIY O'QUV YILI bo'yicha.
        // Tiklangan talaba bir semestrni 2 marta o'qishi mumkin; faqat eng so'nggi
        // (joriy) o'quv yili biriktirilgan fanlar hozir o'qilayotgan fanlardir.
        $curYear = [];
        $hasEnrollment = [];
        $ssRowsAll = collect();
        foreach (array_chunk($studentHemisIds, 500) as $chunk) {
            $ssRows = DB::table('student_subjects')
                ->whereIn('student_hemis_id', $chunk)
                ->whereIn('semester_id', $currentSemesterCodes)
                ->whereNotNull('subject_id')
                ->select('student_hemis_id', 'semester_id', 'subject_id', 'education_year')
                ->get();
            $ssRowsAll = $ssRowsAll->merge($ssRows);
            foreach ($ssRows as $sr) {
                $hid = $sr->student_hemis_id;
                $hasEnrollment[$hid] = true;
                $y = (string) $sr->education_year;
                if ($y !== '' && (!isset($curYear[$hid]) || $y > $curYear[$hid])) {
                    $curYear[$hid] = $y;
                }
            }
        }
        $enrolledCur = [];
        foreach ($ssRowsAll as $sr) {
            $hid = $sr->student_hemis_id;
            $cy = $curYear[$hid] ?? null;
            if ($cy === null || (string) $sr->education_year === '' || (string) $sr->education_year === $cy) {
                $enrolledCur[$hid][$sr->subject_id] = true;
            }
        }

        // Baholarni joriy o'quv yili bo'yicha tozalash (eski yil baholari chiqarib tashlanadi).
        $grades = $grades->filter(function ($g) use ($curYear) {
            $cy = $curYear[$g->student_hemis_id] ?? null;
            if ($cy === null) return true;
            $gy = (string) ($g->education_year_code ?? '');
            if ($gy === '') return true;
            return $gy === $cy;
        });

        if ($grades->isEmpty()) return [];

        // Sababli absent oralilqlari (AbsenceExcuse — sana oralig'i bo'yicha, fan emas)
        $hasExcuseTable = \Illuminate\Support\Facades\Schema::hasTable('absence_excuses');
        $excuseRanges = [];
        if ($hasExcuseTable) {
            $excused = DB::table('absence_excuses')
                ->whereIn('student_hemis_id', $studentHemisIds)
                ->where('status', 'approved')
                ->select('student_hemis_id', 'start_date', 'end_date')
                ->get();
            foreach ($excused as $e) {
                $excuseRanges[$e->student_hemis_id][] = [
                    'start' => substr((string) $e->start_date, 0, 10),
                    'end' => substr((string) $e->end_date, 0, 10),
                ];
            }
        }

        // --- Davomat (jurnal mantig'i): attendances.absent_off soatlari / auditoriya soati ---
        // Jurnal show sahifasi davomatni dars SONIDAN emas, balki QOLDIRILGAN SOATLARdan
        // hisoblaydi: SUM(absent_off) / auditoriumHours * 100. MT/ON/OSKI/Test (99,100,101,102)
        // turlari va tasdiqlangan sababli ariza sanalari chiqarib tashlanadi.
        $subjectIdsForAtt = $grades->pluck('subject_id')->filter()->unique()->values()->all();

        // 1) Har talaba+fan+semestr uchun sababsiz qoldirilgan soat.
        //    Tasdiqlangan sababli ariza sanalari SQL korrelyatsiyalangan subso'rov
        //    o'rniga PHP tomonda ($excuseRanges) filtrlanadi — DATE() funksiyasi bilan
        //    indekssiz korrelyatsiyali subso'rov katta talaba to'plamida sahifani
        //    juda sekinlashtirib (timeout) yuboradi.
        $absentHours = [];
        if (!empty($subjectIdsForAtt)) {
            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $attRows = DB::table('attendances')
                    ->whereIn('student_hemis_id', $chunk)
                    ->whereIn('semester_code', $currentSemesterCodes)
                    ->whereIn('subject_id', $subjectIdsForAtt)
                    ->whereNotIn('training_type_code', [99, 100, 101, 102])
                    ->where('absent_off', '>', 0)
                    ->select('student_hemis_id', 'subject_id', 'semester_code', 'education_year_code', 'lesson_date', 'absent_off')
                    ->get();
                foreach ($attRows as $ar) {
                    // Joriy o'quv yili filtri (tiklangan talaba uchun eski yil qoldirishlarini chiqarib tashlash)
                    $cy = $curYear[$ar->student_hemis_id] ?? null;
                    $ey = (string) ($ar->education_year_code ?? '');
                    if ($cy !== null && $ey !== '' && $ey !== $cy) continue;
                    // Tasdiqlangan sababli ariza oralig'iga tushsa — sababli, hisobga olinmaydi
                    if ($hasExcuseTable && $this->isDateExcused($ar->student_hemis_id, $ar->lesson_date, $excuseRanges)) {
                        continue;
                    }
                    $k = $ar->student_hemis_id . '|' . $ar->subject_id . '|' . $ar->semester_code;
                    $absentHours[$k] = ($absentHours[$k] ?? 0) + (float) $ar->absent_off;
                }
            }
        }

        // 2) Talaba -> o'quv reja (curricula) xaritasi
        $stuCurricula = DB::table('students')
            ->whereIn('students.hemis_id', $studentHemisIds)
            ->leftJoin('groups', 'students.group_id', '=', 'groups.group_hemis_id')
            ->select('students.hemis_id', 'groups.curriculum_hemis_id')
            ->pluck('curriculum_hemis_id', 'students.hemis_id')
            ->toArray();

        // Talaba -> guruh (group_hemis_id) xaritasi — JN ni jurnal mantig'ida hisoblash uchun
        $stuGroup = DB::table('students')
            ->whereIn('hemis_id', $studentHemisIds)
            ->pluck('group_id', 'hemis_id')
            ->toArray();
        $jnGroupCache = [];

        // 3) Auditoriya soatlari: [curricula|subject|sem] va fallback [subject|sem]
        $auditMap = [];
        $auditAnyMap = [];
        if (!empty($subjectIdsForAtt)) {
            $csRows = \App\Models\CurriculumSubject::whereIn('semester_code', $currentSemesterCodes)
                ->whereIn('subject_id', $subjectIdsForAtt)
                ->orderByDesc('is_active')
                ->get(['subject_id', 'semester_code', 'curricula_hemis_id', 'total_acload', 'subject_details']);
            foreach ($csRows as $cs) {
                $h = 0.0;
                if (is_array($cs->subject_details)) {
                    foreach ($cs->subject_details as $d) {
                        $code = (string) (($d['trainingType'] ?? [])['code'] ?? '');
                        if ($code !== '' && $code !== '17') {
                            $h += (float) ($d['academic_load'] ?? 0);
                        }
                    }
                }
                if ($h <= 0) $h = (float) ($cs->total_acload ?? 0);
                $kc = $cs->curricula_hemis_id . '|' . $cs->subject_id . '|' . $cs->semester_code;
                if (!isset($auditMap[$kc])) $auditMap[$kc] = $h;
                $ka = $cs->subject_id . '|' . $cs->semester_code;
                if (!isset($auditAnyMap[$ka])) $auditAnyMap[$ka] = $h;
            }
        }

        // Talaba+fan bo'yicha guruhlash
        $grouped = $grades->groupBy(fn($g) => $g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code);

        $risks = [];

        foreach ($grouped as $key => $rows) {
            [$hemisId, $subjectId, $semCode] = explode('|', $key, 3);

            // Talabaning o'z semester_code si bilan mos kelmasa — bu fan joriy semestr emas
            if (!empty($studentSemCodesMap)) {
                $studentSemCode = $studentSemCodesMap[$hemisId] ?? null;
                if ($studentSemCode !== null && (string) $semCode !== (string) $studentSemCode) {
                    continue;
                }
            }

            // Biriktirilganlik tekshiruvi: bu fan joriy o'quv yili biriktirilganlar
            // ro'yxatida bo'lmasa — talaba hozir o'qimaydi (tiklangan, eski yil fani),
            // xavf hisoblanmaydi.
            if (($hasEnrollment[$hemisId] ?? false) && !isset($enrolledCur[$hemisId][$subjectId])) {
                continue;
            }

            $subjectName = $rows->first()->subject_name ?? 'Fan';
            $reasons = [];

            // 1. OSKI/Test (imtihon urinishlari) — faqat ENG OXIRGI urinish natijasiga qaraladi
            $examRows = $rows->whereIn('training_type_code', [101, 102]);
            if ($examRows->isNotEmpty()) {
                $byAttempt = $examRows->groupBy('attempt');
                $maxAttempt = (int) $examRows->max('attempt');
                $lastAttRows = $byAttempt->get((string)$maxAttempt) ?? $byAttempt->get($maxAttempt);
                $lastBaho = null;
                if ($lastAttRows) {
                    foreach ($lastAttRows as $r) {
                        $val = $r->retake_grade !== null ? (float)$r->retake_grade : ($r->grade !== null ? (float)$r->grade : null);
                        if ($val !== null && ($lastBaho === null || $val > $lastBaho)) {
                            $lastBaho = $val;
                        }
                    }
                }
                if ($lastBaho !== null && $lastBaho < 60) {
                    if ($maxAttempt === 1) $reasons[] = '1-urinish: V<60';
                    elseif ($maxAttempt === 2) $reasons[] = '2-urinish: V<60';
                    elseif ($maxAttempt >= 3) $reasons[] = 'Akademik qarzdor (3 urinish tugadi)';
                }
            }

            // 2. MT (training_type_code = 99)
            $mtRows = $rows->where('training_type_code', 99);
            if ($mtRows->isNotEmpty()) {
                $mtGrade = null;
                foreach ($mtRows as $r) {
                    $val = $r->grade !== null ? (float)$r->grade : null;
                    if ($val !== null && ($mtGrade === null || $val > $mtGrade)) {
                        $mtGrade = $val;
                    }
                }
                if ($mtGrade !== null && $mtGrade < 60) {
                    $reasons[] = 'MT<60';
                }
            }

            // 3. JN (kunlik baholar) — jurnaldagi AYNAN bir xil hisob:
            // computeJnAveragesForGroup (schedules rejasi bo'yicha kunlik o'rtacha,
            // yo'qolgan juftlar 0, maxraj = rejalashtirilgan dars kunlari soni).
            $jnHasGrades = $rows->contains(fn($r) =>
                !in_array((int)$r->training_type_code, [11, 99, 100, 101, 102, 103])
                && $r->lesson_date !== null
                && $r->reason !== 'absent'
                && $r->grade !== null
            );
            $gidForJn = $stuGroup[$hemisId] ?? null;
            if ($jnHasGrades && $gidForJn) {
                $jnCacheKey = $gidForJn . '|' . $subjectId . '|' . $semCode;
                if (!array_key_exists($jnCacheKey, $jnGroupCache)) {
                    try {
                        $jnGroupCache[$jnCacheKey] = \App\Http\Controllers\Admin\JournalController::computeJnAveragesForGroup(
                            (string) $subjectId, (string) $semCode, (string) $gidForJn
                        );
                    } catch (\Throwable $e) {
                        $jnGroupCache[$jnCacheKey] = [];
                    }
                }
                $jnVal = $jnGroupCache[$jnCacheKey][$hemisId] ?? null;
                if ($jnVal !== null && $jnVal > 0 && $jnVal < 60) {
                    $reasons[] = 'JN<60 (' . $jnVal . ')';
                }
            }

            // 4. Sababsiz davomat >= 25% (jurnal mantig'i: qoldirilgan soat / auditoriya soati)
            $absH = $absentHours[$hemisId . '|' . $subjectId . '|' . $semCode] ?? 0;
            if ($absH > 0) {
                $cur = $stuCurricula[$hemisId] ?? null;
                $audH = ($cur !== null) ? ($auditMap[$cur . '|' . $subjectId . '|' . $semCode] ?? 0) : 0;
                if ($audH <= 0) {
                    $audH = $auditAnyMap[$subjectId . '|' . $semCode] ?? 0;
                }
                if ($audH > 0) {
                    $pct = round(($absH / $audH) * 100, 2);
                    if ($pct >= 25) {
                        $pctLabel = rtrim(rtrim(number_format($pct, 2, '.', ''), '0'), '.');
                        $reasons[] = "Davomat≥25% ({$pctLabel}%)";
                    }
                }
            }

            if (!empty($reasons)) {
                $risks[$hemisId][] = [
                    'subject_id' => $subjectId,
                    'semester_code' => $rows->first()->semester_code ?? null,
                    'subject_name' => $subjectName,
                    'reasons' => $reasons,
                ];
            }
        }

        // Individual grafik bo'yicha belgilangan urinishlar: sana o'tib ketgan lekin
        // student_grades da mos yozuv yo'q — "o'tmagan" hisoblanadi.
        // exam_schedules.student_hemis_id = talaba → shaxsiy resit sana belgilangan.
        // attempt=2: test_resit_date / oski_resit_date
        // attempt=3: test_resit2_date / oski_resit2_date
        if (\Illuminate\Support\Facades\Schema::hasTable('exam_schedules')) {
            $today = now()->format('Y-m-d');

            // O'TGAN imtihonlar: [hid|subId|semCode] => true (eng yaxshi 101/102 bahosi >= 60).
            // Resit sanalari guruhga oldindan belgilanadi; 1-urinishda o'tgan talabaga
            // resit kerak emas — shuning uchun "baholanmagan" deb belgilanmaydi.
            $passedExam = [];
            foreach ($grades as $g) {
                if (!in_array((int) $g->training_type_code, [101, 102])) continue;
                $val = $g->retake_grade !== null ? (float) $g->retake_grade : ($g->grade !== null ? (float) $g->grade : null);
                if ($val !== null && $val >= 60) {
                    $passedExam[$g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code] = true;
                }
            }

            foreach (array_chunk($studentHemisIds, 500) as $chunk) {
                $esRows = DB::table('exam_schedules')
                    ->whereIn('student_hemis_id', $chunk)
                    ->whereIn('semester_code', $currentSemesterCodes)
                    ->select(
                        'student_hemis_id', 'subject_id', 'subject_name', 'semester_code',
                        'test_resit_date', 'oski_resit_date',
                        'test_resit2_date', 'oski_resit2_date'
                    )
                    ->get();

                // Mavjud student_grades imtihon yozuvlari: [hemis_id|subject_id|semester_code|attempt|type]
                $existingKeys = [];
                foreach ($grades as $g) {
                    if (!in_array((int)$g->training_type_code, [101, 102])) continue;
                    $att = (int) ($g->attempt ?? 1);
                    $existingKeys[$g->student_hemis_id . '|' . $g->subject_id . '|' . $g->semester_code . '|' . $att] = true;
                }

                foreach ($esRows as $es) {
                    $hid = $es->student_hemis_id;
                    $subId = $es->subject_id;
                    $semCode = $es->semester_code;
                    $subName = $es->subject_name ?? 'Fan';

                    // Semester filter per student
                    if (!empty($studentSemCodesMap)) {
                        $stu_sem = $studentSemCodesMap[$hid] ?? null;
                        if ($stu_sem !== null && (string)$semCode !== (string)$stu_sem) continue;
                    }

                    // Fanni allaqachon o'tgan (imtihon bahosi >= 60) — resit kerak emas, o'tkazib yuboramiz.
                    if (isset($passedExam[$hid . '|' . $subId . '|' . $semCode])) {
                        continue;
                    }

                    // attempt=2: test yoki oski resit sanasi o'tib ketgan va grade yo'q
                    foreach (['test_resit_date' => 102, 'oski_resit_date' => 101] as $col => $ttCode) {
                        $date = $es->$col ?? null;
                        if ($date === null) continue;
                        $dateStr = substr((string)$date, 0, 10);
                        if ($dateStr > $today) continue;
                        $key2 = $hid . '|' . $subId . '|' . $semCode . '|2';
                        if (!isset($existingKeys[$key2])) {
                            // 2-urinish sanasi o'tib ketgan, lekin baholanmagan
                            $alreadyAdded = false;
                            foreach ($risks[$hid] ?? [] as $r) {
                                if ($r['subject_name'] === $subName) { $alreadyAdded = true; break; }
                            }
                            if (!$alreadyAdded) {
                                $risks[$hid][] = [
                                    'subject_id' => $subId,
                                    'semester_code' => $semCode,
                                    'subject_name' => $subName,
                                    'reasons' => ['2-urinish: baholanmagan (grafik o\'tib ketdi)'],
                                ];
                            } else {
                                foreach ($risks[$hid] as &$r) {
                                    if ($r['subject_name'] === $subName) {
                                        if (!in_array('2-urinish: baholanmagan (grafik o\'tib ketdi)', $r['reasons'])) {
                                            $r['reasons'][] = '2-urinish: baholanmagan (grafik o\'tib ketdi)';
                                        }
                                        break;
                                    }
                                }
                                unset($r);
                            }
                        }
                    }

                    // attempt=3: resit2 sanasi o'tib ketgan va grade yo'q
                    foreach (['test_resit2_date' => 102, 'oski_resit2_date' => 101] as $col => $ttCode) {
                        $date = $es->$col ?? null;
                        if ($date === null) continue;
                        $dateStr = substr((string)$date, 0, 10);
                        if ($dateStr > $today) continue;
                        $key3 = $hid . '|' . $subId . '|' . $semCode . '|3';
                        if (!isset($existingKeys[$key3])) {
                            // 3-urinish sanasi o'tib ketgan, lekin baholanmagan → akademik qarzdor
                            $alreadyAdded = false;
                            $reason3 = 'Akademik qarzdor (3-urinish baholanmagan)';
                            foreach ($risks[$hid] ?? [] as $r) {
                                if ($r['subject_name'] === $subName) { $alreadyAdded = true; break; }
                            }
                            if (!$alreadyAdded) {
                                $risks[$hid][] = [
                                    'subject_id' => $subId,
                                    'semester_code' => $semCode,
                                    'subject_name' => $subName,
                                    'reasons' => [$reason3],
                                ];
                            } else {
                                foreach ($risks[$hid] as &$r) {
                                    if ($r['subject_name'] === $subName) {
                                        // Agar "2-urinish: V<60" yoki shunga o'xshash sabab bo'lsa — 3-urinish ustunlik qiladi
                                        if (!in_array($reason3, $r['reasons'])) {
                                            $r['reasons'][] = $reason3;
                                        }
                                        break;
                                    }
                                }
                                unset($r);
                            }
                        }
                    }
                }
            }
        }

        return $risks;
    }

    /**
     * Berilgan sana talaba uchun tasdiqlangan sababli ariza oralig'iga tushadimi?
     *
     * @param  mixed  $hemisId      Talaba hemis_id.
     * @param  mixed  $lessonDate   Dars sanasi (Y-m-d yoki datetime).
     * @param  array  $excuseRanges [hemis_id => [['start'=>Y-m-d, 'end'=>Y-m-d], ...]].
     */
    protected function isDateExcused($hemisId, $lessonDate, array $excuseRanges): bool
    {
        $ranges = $excuseRanges[$hemisId] ?? null;
        if (empty($ranges) || $lessonDate === null) {
            return false;
        }
        $d = substr((string) $lessonDate, 0, 10);
        if ($d === '') {
            return false;
        }
        foreach ($ranges as $range) {
            if ($d >= $range['start'] && $d <= $range['end']) {
                return true;
            }
        }
        return false;
    }
}
