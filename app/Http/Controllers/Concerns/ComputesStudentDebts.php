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
}
