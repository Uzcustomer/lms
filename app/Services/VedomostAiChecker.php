<?php

namespace App\Services;

use App\Models\Student;
use App\Models\VedomostSubmission;
use App\Services\VedomostAiProgress as Progress;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Yuklangan vedomostni (skaner PDF) tizim ma'lumoti bilan solishtiradi:
 * VedomostScanReader skanerni Gemini orqali o'qiydi, VedomostCheckEngine esa
 * Python "vedomost_avto" qoidalari bo'yicha hisobot quradi.
 *
 * ai_result ichida expected (tizim), scan (o'qilgan), context va report saqlanadi —
 * xodim PDF qiymatini tuzatganda hisobot AI'siz qayta quriladi.
 *
 * Faqat tavsiya — yakuniy qarorni registrator ofisi xodimi qiladi.
 */
class VedomostAiChecker
{
    /** Test markazidagi YN quiz turlari — Test markazi natijalarini jurnalga o'tkazishdagi ro'yxat (QuizResultController). */
    public const TM_OSKI_TYPES = ['OSKI (eng)', 'OSKI (rus)', 'OSKI (uzb)'];

    public const TM_TEST_TYPES = ['YN test (eng)', 'YN test (rus)', 'YN test (uzb)'];

    public function __construct(
        private YnQaydnomaDataService $qaydnoma,
        private VedomostMergeService $merge,
        private VedomostScanReader $reader,
        private VedomostCheckEngine $engine,
        private VedomostAiSettings $settings,
        private VedomostTekshirishService $vt,
        private YnStageService $stages
    ) {
    }

    public static function isConfigured(): bool
    {
        return VedomostGeminiClient::isConfigured();
    }

    /**
     * To'liq tekshiruv: skanerni o'qish + hisobot.
     */
    public function check(VedomostSubmission $v, bool $fresh = false): array
    {
        if (!$v->pdf_path || !Storage::disk('public')->exists($v->pdf_path)) {
            throw new RuntimeException('Skaner PDF topilmadi.');
        }

        // Birlashtirilgan vedomost: bitta o'zak guruh × o'zak fan uchun bitta varaq,
        // unda barcha guruhcha/variant talabalari jamlangan.
        $expected = $this->buildMergedExpected($v);
        if (!$expected) {
            throw new RuntimeException('Tizimda bu guruh/fan uchun YN qaydnoma ma\'lumoti topilmadi.');
        }

        // "Tizim" qiymatlari — "Vedomost tekshirish" eksporti bilan aynan bir xil hisob.
        $expected['talabalar'] = $this->vtStudents($v);
        // Shakl: sarlavhadagi shakl nomini tekshirish va qayta topshirish shakllaridagi yumshoqroq qoidalar uchun.
        $expected['form_type'] = (string) ($v->form_type ?: VedomostSubmission::FORM_12);
        $expected['shakl'] = VedomostSubmission::formLabel($v->form_type);
        if (empty($expected['talabalar'])) {
            throw new RuntimeException("Vedomost tekshirish hisobi bo'yicha talabalar topilmadi.");
        }

        Progress::log('Mark baholari yig‘ildi: ' . count($expected['talabalar']) . ' talaba · ' . $expected['shakl']
            . ' · yopilish shakli: ' . ($expected['yopilish_shakli'] ?: '—'));

        $scan = $this->reader->read(Storage::disk('public')->path($v->pdf_path), $v->id, count($expected['talabalar']), $fresh);
        $m = $scan['meta'] ?? [];
        Progress::log('PDF o‘qildi: ' . ($m['rows_read'] ?? count($scan['rows'] ?? [])) . ' qator'
            . (!empty($m['missing_nrs']) ? ' · o‘qilmagan qatorlar: ' . implode(', ', $m['missing_nrs']) : '')
            . (!empty($m['repaired']) ? ' · qayta o‘qib tuzatildi: ' . $m['repaired'] : ''), empty($m['missing_nrs']) ? 'ok' : 'warn');
        $context = $this->buildContext($v, $expected, $scan);
        Progress::log('Test markazi: ' . count($context['test_center'] ?? []) . ' talaba natijasi topildi');

        return $this->assemble($expected, $scan, $context, []);
    }

    /**
     * Xodim tuzatishlari bilan hisobotni qayta quradi (AI chaqirilmaydi).
     */
    public function recompute(array $aiResult, array $edits): array
    {
        if (($aiResult['version'] ?? 1) < VedomostCheckEngine::VERSION || empty($aiResult['scan'])) {
            throw new RuntimeException("Bu natija eski formatda — avval vedomostni qayta tekshiring.");
        }

        return $this->assemble($aiResult['expected'], $aiResult['scan'], $aiResult['context'] ?? [], $edits)
            + ['checked_model' => $aiResult['model'] ?? null];
    }

    private function assemble(array $expected, array $scan, array $context, array $edits): array
    {
        $report = $this->engine->build($expected, $scan, $edits, $this->settings->all(), $context);

        return [
            'version' => VedomostCheckEngine::VERSION,
            'verdict' => $report['verdict'],
            'summary' => $report['summary'],
            'model' => $scan['meta']['model'] ?? $this->settings->get('model'),
            'expected' => $expected,
            'scan' => $scan,
            'context' => $context,
            'edits' => $edits,
            'report' => $report,
        ];
    }

    /**
     * Har guruhcha (sibling) uchun VedomostTekshirishService::collect() — talabalarning
     * xom qiymatlari (JN, MT, ON, OSKI, Test, davomat, JN soni), eksport ustunlari uchun
     * guruh ma'lumotlari va HEMIS imtihon baholari (JN+MT/YN tekshiruv ustunlari).
     */
    private function vtStudents(VedomostSubmission $v): array
    {
        $siblings = $this->merge->siblingsOf($v);
        if (!$siblings->contains('id', $v->id)) {
            $siblings->push($v);
        }
        $siblings = $siblings->sortBy('group_name', SORT_NATURAL | SORT_FLAG_CASE)->values();
        $shakl = VedomostSubmission::formLabel($v->form_type);
        $attempt = self::formAttempt($v->form_type);

        $out = [];
        foreach ($siblings as $sib) {
            array_push($out, ...$this->studentsFor($sib, $attempt, $shakl));
        }

        return $out;
    }

    /**
     * Bitta guruh yozuvi (vedomost) uchun tizim (Mark) talabalari — AI tekshiruvi va tasdiqlangan
     * vedomostlar jamlanmasi uchun umumiy. 12-shakldan boshqa shakllarda faqat shu shaklga
     * kiradigan talabalar va shu urinish OSKE/Test bahosi.
     */
    public function studentsFor(VedomostSubmission $sib, ?array $attempt = null, ?string $shakl = null): array
    {
        $attempt ??= self::formAttempt($sib->form_type);
        $shakl ??= VedomostSubmission::formLabel($sib->form_type);
        $out = [];
        $group = \App\Models\Group::where('group_hemis_id', $sib->group_hemis_id)->first();
        if (!$group) {
            return [];
        }
        $c = $this->vt->collect($group, (string) $sib->subject_id, (string) $sib->semester_code);
        if (!$c) {
            return [];
        }
        // 12-shakldan boshqa shakllar: talabalar ro'yxati va OSKE/Test — jurnaldagi YN qaydnoma
        // eksporti bilan bir xil (YnStageService bosqich va urinish baholari).
        $stage = $attempt
            ? $this->stages->computeForGroupSubject((string) $sib->group_hemis_id, (string) $sib->subject_id, (string) $sib->semester_code)
            : null;
        $exam = \App\Models\HemisExamGrade::forComparison($c['studentHemisIds'], (string) $sib->subject_id, (string) $sib->semester_code)
            ->get()
            ->groupBy('student_hemis_id');
        $meta = [
            'group_hemis_id' => $c['groupHemisId'],
            'group_name' => $group->name ?? '',
            'practice_teacher' => $c['practiceTeacher'],
            'lecture_teacher' => $c['lectureTeacher'],
            'subject_id' => (string) $sib->subject_id,
            'subject_name' => $c['subject']->subject_name ?? '',
            'shakl' => $shakl,
            'education_year' => $c['educationYearName'] ?? ($c['curriculum']?->education_year_name ?? ''),
            'semester' => $c['semester']?->name ?? $sib->semester_code,
            'hours' => $c['totalHours'],
            'credit' => (int) ($c['subject']->credit ?? 0),
            'divisor' => $c['divisor'],
            'level_code' => (string) ($c['semester']?->level_code ?? ''),
        ];
        foreach ($c['students'] as $stu) {
            $hid = $stu->hemis_id;
            $raw = $c['raw'][$hid];
            if ($attempt) {
                $st = $stage['stages'][$hid] ?? null;
                if ($st === null || !in_array($attempt['form'], YnAttemptStatusService::formsForStage($st), true)) {
                    continue;
                }
                $raw = self::attemptRaw($raw, $stage['studentScenarios'][$hid] ?? [], $attempt['chain']);
            }
            $he = $exam[$hid] ?? collect();
            $out[] = [
                'fish' => $stu->full_name,
                'student_id' => (string) $stu->student_id_number,
                'hemis_id' => (string) $hid,
                'status_code' => (string) ($stu->student_status_code ?? ''),
                'status_name' => (string) ($stu->student_status_name ?? ''),
                'group' => $group->name ?? '',
                'raw' => $raw,
                'meta' => $meta,
                'hemis_exam' => [
                    '11' => $he->firstWhere('exam_type_code', '11')?->grade,
                    '13' => $he->firstWhere('exam_type_code', '13')?->grade,
                ],
            ];
        }

        return $out;
    }

    /**
     * Vedomost shakli → YnAttemptStatusService shakli va OSKE/Test olinadigan urinishlar zanjiri
     * (jurnal eksportidagi fallback bilan bir xil: 12a = 2-urinish ?? 1-urinish, 12b = 3 ?? 2 ?? 1).
     * 12-shakl uchun null — «Vedomost tekshirish» hisobi o'zgarishsiz ishlatiladi.
     *
     * @return array{form: string, chain: string[]}|null
     */
    public static function formAttempt(?string $formType): ?array
    {
        return match ($formType) {
            VedomostSubmission::FORM_12Q => ['form' => YnAttemptStatusService::FORM_12_QOSHIMCHA, 'chain' => ['qoshimcha', 'main']],
            VedomostSubmission::FORM_12A => ['form' => YnAttemptStatusService::FORM_12A, 'chain' => ['a', 'main']],
            VedomostSubmission::FORM_12AQ, VedomostSubmission::FORM_12AG => ['form' => YnAttemptStatusService::FORM_12A_QOSHIMCHA, 'chain' => ['aQoshimcha', 'main']],
            VedomostSubmission::FORM_12B => ['form' => YnAttemptStatusService::FORM_12B, 'chain' => ['b', 'a', 'main']],
            VedomostSubmission::FORM_12BQ, VedomostSubmission::FORM_12BG => ['form' => YnAttemptStatusService::FORM_12B_QOSHIMCHA, 'chain' => ['bQoshimcha', 'aQoshimcha', 'main']],
            default => null,
        };
    }

    /** Shu urinishning OSKE/Test bahosini «Vedomost tekshirish» xom qiymatlariga qo'yadi (JN/MT/ON/davomat o'zgarmaydi). */
    public static function attemptRaw(array $raw, array $scenarios, array $chain): array
    {
        foreach ($chain as $key) {
            if (!empty($scenarios[$key])) {
                $raw['oski'] = (float) ($scenarios[$key]['oski'] ?? 0);
                $raw['test'] = (float) ($scenarios[$key]['test'] ?? 0);
                break;
            }
        }

        return $raw;
    }

    /**
     * Tashqi ma'lumotlar: kafedra mudiri, xalqaro fakultet talabalari,
     * Test markazi natijalari va (ortiqcha talabalar uchun) kontingent.
     */
    private function buildContext(VedomostSubmission $v, array $expected, array $scan): array
    {
        $sysIds = array_values(array_filter(array_map(
            fn ($t) => VedomostScanReader::digits($t['student_id'] ?? ''),
            $expected['talabalar'] ?? []
        )));
        $scanIds = array_values(array_filter(array_map(fn ($r) => VedomostScanReader::digits($r['id'] ?? ''), $scan['rows'] ?? [])));

        return [
            'kafedra_mudiri' => $v->kafedra_mudiri_name,
            'international' => $this->internationalIds($sysIds),
            'test_center' => $this->testCenter($sysIds, $expected, VedomostSubmission::formLabel($v->form_type),
                (string) ($expected['talabalar'][0]['meta']['semester'] ?? '')),
            'kontingent' => $this->kontingent(array_values(array_diff($scanIds, $sysIds))),
        ];
    }

    private function internationalIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return Student::whereIn('student_id_number', $ids)
            ->get(['student_id_number', 'department_name'])
            ->filter(fn ($s) => $s->isInternationalFaculty())
            ->mapWithKeys(fn ($s) => [VedomostScanReader::digits($s->student_id_number) => true])
            ->all();
    }

    /**
     * Test markazi (hemis_quiz_results): talaba ID → ['oski'|'test' => [grade, dalolat, date]].
     * Fan: fan_id = subject_id yoki nom o'xshashligi ≥ 0.85; urinish vedomost shakliga mos
     * (1-urinish → 12, 2-urinish → 12a, 3-urinish → 12b; qo'shimcha/farmoyish → "…-qo'shimcha").
     * Qayta o'qish (retake) quizlari hisobga olinmaydi. Faqat YN quizlari (OSKI / YN test —
     * jurnalga o'tkaziladigan turlar bilan bir xil) va faqat shu semestr: bir fan bir necha
     * semestrda o'qitilsa (masalan, Xirurgik kasalliklar 7–9-semestr), oldingi semestr natijasi
     * shu vedomostga tushmasin.
     */
    private function testCenter(array $ids, array $expected, string $formLabel, string $semester = ''): array
    {
        if (empty($ids) || !Schema::hasTable('hemis_quiz_results')) {
            return [];
        }

        $q = DB::table('hemis_quiz_results')
            ->whereIn('student_id', $ids)
            ->where('is_active', 1)
            ->whereIn('quiz_type', array_merge(self::TM_OSKI_TYPES, self::TM_TEST_TYPES))
            ->select('student_id', 'fan_id', 'fan_name', 'quiz_type', 'shakl', 'attempt_name', 'grade', 'date_finish', 'semester');
        if (Schema::hasColumn('hemis_quiz_results', 'not_actual')) {
            $q->where(fn ($w) => $w->where('not_actual', false)->orWhereNull('not_actual'));
        }

        $subjectId = (string) ($expected['subject_id'] ?? '');
        $fan = (string) ($expected['fan'] ?? '');
        $base = self::shaklBase($formLabel);
        $semNum = self::semesterNumber($semester);

        $best = [];
        foreach ($q->get() as $r) {
            $fanOk = ($subjectId !== '' && (string) $r->fan_id === $subjectId)
                || VedomostNames::subjectScore($r->fan_name, $fan) >= 0.85;
            if (!$fanOk || self::quizForm((string) $r->shakl) !== $base
                || \App\Services\Retake\RetakeSessionCode::isRetakeQuiz($r->attempt_name, $r->shakl)) {
                continue;
            }
            $rowSem = self::semesterNumber((string) $r->semester);
            if ($semNum !== null && $rowSem !== null && $rowSem !== $semNum) {
                continue;
            }
            $kind = in_array($r->quiz_type, self::TM_OSKI_TYPES, true) ? 'oski' : 'test';
            if ($r->grade === null) {
                continue;
            }
            $dal = str_contains(mb_strtolower($r->attempt_name . ' ' . $r->shakl), 'dalolat');
            $sid = VedomostScanReader::digits($r->student_id);
            $cur = $best[$sid][$kind] ?? null;
            // Dalolatnomasiz natija afzal; keyin eng so'nggisi (Python check_tm).
            $rank = [$dal ? 0 : 1, (string) $r->date_finish];
            if ($cur === null || $rank > $cur['_rank']) {
                $best[$sid][$kind] = ['grade' => (float) $r->grade, 'dalolat' => $dal, 'date' => (string) $r->date_finish, '_rank' => $rank];
            }
        }

        foreach ($best as &$kinds) {
            foreach ($kinds as &$rec) {
                unset($rec['_rank']);
            }
        }

        return $best;
    }

    /** «9-semestr», «9», «9-sem» → 9; raqam yo'q bo'lsa null (filtrlanmaydi). */
    public static function semesterNumber(string $s): ?int
    {
        return preg_match('/(\d+)/', $s, $m) ? (int) $m[1] : null;
    }

    /**
     * Test markazi yozuvining shakli → vedomost shakli kaliti. hemis_quiz_results.shakl
     * odatda "1-urinish", "2-urinish (qo'shimcha)" ko'rinishida bo'ladi; eski yozuvlarda
     * to'g'ridan-to'g'ri "12a-shakl" bo'lishi mumkin.
     */
    public static function quizForm(string $shakl): string
    {
        if (preg_match('/^\s*(\d+)\s*-\s*urinish\b/iu', $shakl, $m)) {
            $form = [1 => '12', 2 => '12a', 3 => '12b'][(int) $m[1]] ?? ('urinish' . $m[1]);

            return $form . (\App\Http\Controllers\Admin\QuizResultController::isQoshimchaFarmoyishShakl($shakl) ? 'qoshimcha' : '');
        }

        return self::shaklBase($shakl);
    }

    public static function shaklBase(string $s): string
    {
        $t = preg_replace('/[^a-z0-9]/', '', mb_strtolower(VedomostNames::latin($s)));
        $t = preg_replace('/shakl$/', '', $t);
        $t = preg_replace('/^(\d+[ab]?)qoshimcha.*/', '$1qoshimcha', $t);

        return $t !== '' ? $t : '12';
    }

    /** Ortiqcha (guruhda yo'q) talabalar uchun kontingent ma'lumoti. */
    private function kontingent(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }

        return Student::whereIn('student_id_number', $ids)
            ->get(['student_id_number', 'full_name', 'group_name', 'student_status_code'])
            ->mapWithKeys(fn ($s) => [VedomostScanReader::digits($s->student_id_number) => [
                'fio' => $s->full_name, 'group' => $s->group_name, 'status' => (string) $s->student_status_code,
            ]])
            ->all();
    }

    /**
     * Birlashtirilgan vedomost uchun kutilgan ma'lumot — o'zak guruh × o'zak fanga
     * tegishli barcha guruhcha/variant yozuvlarining ma'lumotini bitta tuzilmaga
     * jamlaydi (talabalar birlashtiriladi, o'qituvchilar/sanalar to'planadi).
     */
    private function buildMergedExpected(VedomostSubmission $v): ?array
    {
        // Guruhcha tartibida (a, b, c) — amaliyot o'qituvchilari shu tartibda kelishi uchun.
        $siblings = $this->merge->siblingsOf($v)
            ->sortBy('group_name', SORT_NATURAL | SORT_FLAG_CASE)
            ->values();

        $parts = [];
        foreach ($siblings as $sib) {
            $data = $this->qaydnoma->buildExpectedData(
                (string) $sib->group_hemis_id,
                (string) $sib->subject_id,
                (string) $sib->semester_code
            );
            if ($data) {
                $parts[] = $data;
            }
        }

        if (empty($parts)) {
            return null;
        }
        if (count($parts) === 1) {
            return $parts[0];
        }

        $first = $parts[0];
        $groupNames = [];
        $maruzachi = [];
        $amaliyotList = [];
        $ynSanasi = [];
        $students = [];
        $seen = [];

        foreach ($parts as $p) {
            if (!empty($p['guruh'])) {
                $groupNames[$p['guruh']] = true;
            }
            foreach (explode(', ', (string) ($p['maruzachi'] ?? '')) as $name) {
                $name = trim($name);
                if ($name !== '') {
                    $maruzachi[$name] = true;
                }
            }
            // Amaliyot: har guruhchadan BITTA o'qituvchi, guruhcha tartibida (a, b, c).
            // Takror (bir o'qituvchi bir nechta guruhchada) bo'lsa bir marta ko'rsatamiz.
            $amaliyot = trim((string) ($p['amaliyot_oqituvchilari'] ?? ''));
            if ($amaliyot !== '' && !in_array($amaliyot, $amaliyotList, true)) {
                $amaliyotList[] = $amaliyot;
            }
            if (!empty($p['yn_sanasi'])) {
                $ynSanasi[$p['yn_sanasi']] = true;
            }
            foreach ($p['talabalar'] ?? [] as $row) {
                // Bir talaba ikki marta tushmasligi uchun talaba ID (yoki FISH) bo'yicha dedupe.
                $key = !empty($row['student_id']) ? 'id:' . $row['student_id'] : 'fish:' . ($row['fish'] ?? '');
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $students[] = $row;
            }
        }

        // Talabalarni qayta raqamlaymiz (birlashgan ro'yxat bo'yicha 1..N).
        foreach ($students as $i => &$row) {
            $row['n'] = $i + 1;
        }
        unset($row);

        $subgroups = array_keys($groupNames);
        sort($subgroups, SORT_NATURAL | SORT_FLAG_CASE);
        $rootGroup = $this->merge->rootGroupName($v->group_name);

        return array_merge($first, [
            'fan' => $this->merge->rootSubjectName($first['fan'] ?? $v->subject_name),
            'guruh' => $rootGroup . (count($subgroups) > 1 ? ' (' . implode(', ', $subgroups) . ')' : ''),
            'maruzachi' => empty($maruzachi) ? null : implode(', ', array_keys($maruzachi)),
            'amaliyot_oqituvchilari' => implode(', ', $amaliyotList),
            'yn_sanasi' => empty($ynSanasi) ? null : implode(', ', array_keys($ynSanasi)),
            'jami_talabalar' => count($students),
            'talabalar' => $students,
        ]);
    }
}
