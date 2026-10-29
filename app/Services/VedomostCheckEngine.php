<?php

namespace App\Services;

/**
 * Vedomost tekshiruv dvigateli: tizim ma'lumoti (expected) + skanerdan o'qilgan
 * jadval (scan) + xodim tuzatishlari (edits) → hisobot (report v2).
 *
 * Sof funksiya — bazaga murojaat qilmaydi; kerakli tashqi ma'lumot (Test markazi,
 * kontingent, kafedra mudiri) $context orqali beriladi. Shuning uchun xodim PDF
 * qiymatini tuzatganda AI'ni qayta chaqirmasdan hisobot darhol qayta quriladi.
 *
 * Qoidalar Python "vedomost_avto" dasturidan: PDF↔tizim farqlari, ball/YN hisobi,
 * imtihonga kirish qoidasi, yopilish shakliga mos kelmagan baho, sinov fani
 * (Test = Joriy), 6-kurs formulasi, Test markazi, kontingent, sarlavha.
 */
class VedomostCheckEngine
{
    public const VERSION = 2;

    /** Hisobotda ko'rsatiladigan qiymat maydonlari (tartib bilan). */
    public const FIELDS = [
        'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball',
        'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects', 'baho',
    ];

    /** Xodim tahrirlay oladigan skaner maydonlari. */
    public const EDITABLE = [
        'fio', 'id', 'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball',
        'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects', 'baho',
    ];

    public const HEADER_EDITABLE = ['fan', 'guruh', 'soat', 'kredit', 'maruzachi', 'amaliyot', 'yn_sana', 'dekan', 'kafedra_mudiri', 'kurs', 'semestr'];

    private const RANK = ['ok' => 0, 'ocr' => 1, 'bad' => 2, 'extra' => 3, 'missing' => 3];

    public function __construct(private VedomostGradeCalculator $calc)
    {
    }

    /**
     * @param  array  $settings  jn_mt_pass, jn_mt_pass_international, tolerance, oski_min, test_min
     * @param  array  $context  kafedra_mudiri, test_center[id][oski|test], kontingent[id], international[id]
     */
    public function build(array $expected, array $scan, array $edits, array $settings, array $context = []): array
    {
        $tol = (float) ($settings['tolerance'] ?? 0.05);
        [$scanRows, $header, $weights, $signatures, $editedKeys] = $this->applyEdits($scan, $edits);

        $w = [
            'jn' => (int) ($weights['jb'] ?? 0),
            'mt' => (int) ($weights['mt'] ?? 0),
            'on' => (int) ($weights['on'] ?? 0),
            'oski' => (int) ($weights['oski'] ?? 0),
            'test' => (int) ($weights['test'] ?? 0),
        ];
        $closing = (string) ($expected['yopilish_shakli'] ?? '');
        $levelCode = (string) ($expected['level_code'] ?? '');
        $kurs6 = $levelCode === '16';

        // --- talabalarni moslashtirish ---
        $system = $expected['talabalar'] ?? [];
        [$pairs, $missing, $extra] = $this->match($system, $scanRows);

        $rows = [];
        $docIssues = [];
        $tmUsed = ['oski' => false, 'test' => false];
        foreach ($context['test_center'] ?? [] as $rec) {
            $tmUsed['oski'] = $tmUsed['oski'] || isset($rec['oski']);
            $tmUsed['test'] = $tmUsed['test'] || isset($rec['test']);
        }

        foreach ($pairs as [$stu, $s, $matchNote]) {
            $rows[] = $this->row($stu, $s, $matchNote, $w, $levelCode, $kurs6, $closing, $settings, $context, $tmUsed, $tol);
        }
        foreach ($missing as $stu) {
            $flags = [['t' => 'missing', 'm' => "PDFda yo'q"]];
            if (($stu['status_code'] ?? '') === '60') {
                $flags[] = ['t' => 'info', 'm' => "Talaba chetlashtirilgan ({$stu['status_name']})"];
            }
            $rows[] = [
                'key' => null, 'n' => $stu['n'] ?? null, 'fio' => $stu['fish'] ?? '', 'id' => (string) ($stu['student_id'] ?? ''),
                'status' => 'missing', 'system' => $this->systemValues($stu, $w, $levelCode, $kurs6, $settings, $context),
                'scan' => [], 'cells' => [], 'unclear' => [], 'edited' => [], 'flags' => $flags, 'page' => null, 'tm' => [],
            ];
        }
        foreach ($extra as $s) {
            $sid = VedomostScanReader::digits($s['id'] ?? '');
            $k = $context['kontingent'][$sid] ?? null;
            $note = $k
                ? 'Boshqa guruh talabasi: ' . ($k['group'] ?? '?') . (($k['fio'] ?? '') !== '' ? ' (' . $k['fio'] . ')' : '')
                : "Kontingentda yo'q";
            $rows[] = [
                'key' => $s['key'], 'n' => $s['nr'] ?? null, 'fio' => $s['fio'] ?? '', 'id' => $s['id'] ?? '',
                'status' => 'extra', 'system' => [], 'scan' => $this->scanValues($s), 'cells' => [],
                'unclear' => $s['unclear'] ?? [], 'edited' => $editedKeys[$s['key']] ?? [],
                'flags' => [['t' => 'extra', 'm' => "PDFda bor, tizimda (bu guruhda) yo'q — {$note}"]],
                'page' => $s['page'] ?? null, 'tm' => [],
            ];
        }
        foreach ($rows as &$r) {
            $r['edited'] = $r['key'] !== null ? ($editedKeys[$r['key']] ?? []) : [];
        }
        unset($r);
        usort($rows, fn ($a, $b) => [(int) ($a['n'] ?? 999), $a['fio']] <=> [(int) ($b['n'] ?? 999), $b['fio']]);

        // --- hujjat darajasidagi tekshiruvlar ---
        $headerChecks = $this->headerChecks($expected, $header, $context);
        $docIssues = array_merge($docIssues, $this->weightIssues($w, $closing, $kurs6));
        if (($tmUsed['oski'] || $tmUsed['test']) === false && ($w['oski'] > 0 || $w['test'] > 0) && $closing !== 'sinov') {
            $docIssues[] = ['t' => 'info', 'm' => "Test markazida bu fan bo'yicha natija topilmadi — OSKE/Test Test markazi bilan solishtirilmadi."];
        }
        if ($kurs6) {
            $docIssues[] = ['t' => 'info', 'm' => '6-kurs: rasmiy shablon formulasi bilan tekshirildi (YN = ROUND(JB+MT) + nazorat bali).'];
        }
        if (!empty($scan['meta']['weights_from_ratio'])) {
            $docIssues[] = ['t' => 'ocr', 'm' => "Vaznlar sarlavhadan o'qilmadi, ball/foiz nisbatidan aniqlandi: " . implode(', ', $scan['meta']['weights_from_ratio'])];
        }
        $sigLabels = ['oqituvchi' => "o'qituvchi imzosi", 'dekan' => 'dekan imzosi', 'kafedra_mudiri' => 'kafedra mudiri imzosi', 'muhr' => 'muhr (M.O\')'];
        foreach ($sigLabels as $k => $label) {
            if (empty($signatures[$k])) {
                $docIssues[] = ['t' => 'bad', 'm' => "Skanerda {$label} topilmadi"];
            }
        }

        // --- noto'g'ri hujjat ---
        $sysN = count($system);
        $scanN = count($scanRows);
        $matchedN = count($pairs);
        $fanScore = ($header['fan'] ?? '') !== '' ? VedomostNames::subjectScore($header['fan'], $expected['fan'] ?? '') : null;
        $wrongReasons = [];
        if ($fanScore !== null && $fanScore < 0.6) {
            $wrongReasons[] = "fan nomi: tizimda «" . ($expected['fan'] ?? '') . "», skanerda «" . $header['fan'] . '»';
        }
        if ($sysN > 0 && $scanN > 0 && $matchedN / max($sysN, $scanN) < 0.5) {
            $wrongReasons[] = "talabalar: tizimdagi {$sysN} talabadan faqat {$matchedN} tasi skanerda bor";
        }

        $counts = ['total' => count($rows), 'ok' => 0, 'bad' => 0, 'ocr' => 0, 'missing' => 0, 'extra' => 0,
            'edited' => count(array_filter($rows, fn ($r) => !empty($r['edited'])))];
        foreach ($rows as $r) {
            $counts[$r['status']]++;
        }

        $headerBad = count(array_filter($headerChecks, fn ($h) => $h['status'] === 'bad'));
        $docBad = count(array_filter($docIssues, fn ($d) => $d['t'] === 'bad'));
        $docOcr = count(array_filter($docIssues, fn ($d) => $d['t'] === 'ocr'));

        if (!empty($wrongReasons)) {
            $verdict = 'wrong_document';
            $summary = "Yuklangan PDF bu vedomostga tegishli emas ko'rinadi (" . implode('; ', $wrongReasons) . '). To\'g\'ri faylni yuklang.';
        } elseif ($counts['bad'] + $counts['missing'] + $counts['extra'] + $headerBad + $docBad > 0) {
            $verdict = 'issues';
            $summary = $this->summary($counts, $headerBad + $docBad, $sysN, $scanN);
        } elseif ($counts['ocr'] + $docOcr > 0) {
            $verdict = 'review';
            $summary = "Farq topilmadi, lekin {$counts['ocr']} ta qatorni PDFdan ko'z bilan tekshirish kerak (AI aniq o'qiy olmagan).";
        } else {
            $verdict = 'ok';
            $summary = "Vedomost tizim ma'lumotiga to'liq mos keladi ({$sysN} talaba; baholar, ballar, sarlavha va imzolar tekshirildi).";
        }

        return [
            'version' => self::VERSION,
            'verdict' => $verdict,
            'summary' => $summary,
            'counts' => $counts,
            'weights' => $w,
            'controls' => $this->activeControls($w, $rows),
            'closing_form' => $closing,
            'kurs6' => $kurs6,
            'header' => $headerChecks,
            'signatures' => $signatures,
            'doc_issues' => $docIssues,
            'rows' => $rows,
            'scan_header' => $header,
            'meta' => $scan['meta'] ?? [],
        ];
    }

    // ───────────────────────── tuzatishlar ─────────────────────────

    /**
     * @return array{0: array, 1: array, 2: array, 3: array, 4: array}
     */
    private function applyEdits(array $scan, array $edits): array
    {
        $rows = [];
        $editedKeys = [];
        foreach (array_values($scan['rows'] ?? []) as $i => $r) {
            $key = 'r' . $i;
            $r['key'] = $key;
            foreach (($edits['rows'][$key] ?? []) as $f => $val) {
                if (!in_array($f, self::EDITABLE, true)) {
                    continue;
                }
                $r['orig'][$f] = $r[$f] ?? '';
                $r[$f] = (string) $val;
                $r['unclear'] = array_values(array_diff($r['unclear'] ?? [], [$f]));
                $editedKeys[$key][] = $f;
            }
            $rows[] = $r;
        }

        $header = $scan['header'] ?? [];
        foreach (($edits['header'] ?? []) as $f => $val) {
            if (in_array($f, self::HEADER_EDITABLE, true)) {
                $header[$f] = $f === 'amaliyot'
                    ? array_values(array_filter(array_map('trim', is_array($val) ? $val : explode(',', (string) $val))))
                    : (string) $val;
            }
        }

        $weights = $scan['weights'] ?? [];
        foreach (($edits['weights'] ?? []) as $k => $val) {
            if (in_array($k, ['jb', 'mt', 'on', 'oski', 'test'], true) && is_numeric($val)) {
                $weights[$k] = (int) $val;
            }
        }

        $signatures = array_merge(
            ['oqituvchi' => false, 'dekan' => false, 'kafedra_mudiri' => false, 'muhr' => false],
            $scan['signatures'] ?? []
        );
        foreach (($edits['signatures'] ?? []) as $k => $val) {
            if (array_key_exists($k, $signatures)) {
                $signatures[$k] = (bool) $val;
            }
        }

        return [$rows, $header, $weights, $signatures, $editedKeys];
    }

    // ───────────────────────── moslashtirish ─────────────────────────

    /**
     * @return array{0: array, 1: array, 2: array} [[system, scan, note]], missing, extra
     */
    private function match(array $system, array $scanRows): array
    {
        $scanById = [];
        $dup = [];
        foreach ($scanRows as $s) {
            $id = VedomostScanReader::digits($s['id'] ?? '');
            if ($id !== '') {
                if (isset($scanById[$id])) {
                    $dup[$id] = true;
                }
                $scanById[$id] ??= $s;
            }
        }

        $pairs = [];
        $usedScan = [];
        $leftSystem = [];
        foreach ($system as $stu) {
            $id = VedomostScanReader::digits($stu['student_id'] ?? '');
            if ($id !== '' && isset($scanById[$id])) {
                $s = $scanById[$id];
                $usedScan[$s['key']] = true;
                $pairs[] = [$stu, $s, isset($dup[$id]) ? ['t' => 'bad', 'm' => 'PDFda bu talaba bir necha marta uchraydi'] : null];
            } else {
                $leftSystem[] = $stu;
            }
        }

        // ID bo'yicha topilmaganlar — F.I.Sh (va ID yaqinligi) bo'yicha.
        $leftScan = array_values(array_filter($scanRows, fn ($s) => !isset($usedScan[$s['key']])));
        $missing = [];
        foreach ($leftSystem as $stu) {
            $best = null;
            $bestScore = 0.0;
            foreach ($leftScan as $i => $s) {
                $sim = VedomostNames::similarity($stu['fish'] ?? '', $s['fio'] ?? '');
                $idA = VedomostScanReader::digits($stu['student_id'] ?? '');
                $idB = VedomostScanReader::digits($s['id'] ?? '');
                $idClose = $idA !== '' && $idB !== '' && levenshtein($idA, $idB) <= 2;
                $score = $sim + ($idClose ? 0.2 : 0);
                if (($sim >= 0.85 || ($idClose && $sim >= 0.6)) && $score > $bestScore) {
                    $best = $i;
                    $bestScore = $score;
                }
            }
            if ($best === null) {
                $missing[] = $stu;
                continue;
            }
            $s = $leftScan[$best];
            unset($leftScan[$best]);
            $soft = in_array('id', $s['unclear'] ?? [], true);
            $pairs[] = [$stu, $s, [
                't' => $soft ? 'ocr' : 'bad',
                'm' => 'ID mos emas: PDFda «' . ($s['id'] ?? '') . '», tizimda «' . ($stu['student_id'] ?? '') . '» (F.I.Sh bo\'yicha topildi)',
            ]];
        }

        return [$pairs, $missing, array_values($leftScan)];
    }

    // ───────────────────────── bitta talaba ─────────────────────────

    private function row(array $stu, array $s, ?array $matchNote, array $w, string $levelCode, bool $kurs6, string $closing, array $settings, array $context, array $tmUsed, float $tol): array
    {
        $flags = $matchNote ? [$matchNote] : [];
        $unclear = $s['unclear'] ?? [];
        $sys = $this->systemValues($stu, $w, $levelCode, $kurs6, $settings, $context);
        $scan = $this->scanValues($s);
        $cells = [];

        $fieldsToCheck = ['jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'jbmton_ball'];
        if ($w['on'] > 0 || $scan['on_pct'] !== '' || $sys['on_pct'] !== null) {
            array_push($fieldsToCheck, 'on_pct', 'on_ball');
        }
        if ($w['oski'] > 0) {
            array_push($fieldsToCheck, 'oski_pct', 'oski_ball');
        }
        if ($w['test'] > 0) {
            array_push($fieldsToCheck, 'test_pct', 'test_ball');
        }
        $fieldsToCheck[] = 'yn';

        $special = is_numeric($sys['yn']) && $sys['yn'] < 0;
        foreach ($fieldsToCheck as $f) {
            $cells[$f] = $this->cellStatus($sys[$f], $scan[$f], in_array($f, $unclear, true), $f === 'yn' ? 0.5 : $tol, !empty($s['verified']));
        }
        if (!$special && is_numeric($sys['yn']) && $sys['yn'] > 0) {
            foreach (['ects', 'baho'] as $f) {
                $cells[$f] = $this->strStatus($sys[$f], $scan[$f], in_array($f, $unclear, true));
            }
        }

        $labels = $this->fieldLabels();
        foreach ($cells as $f => $st) {
            if ($st === 'bad') {
                $flags[] = ['t' => 'bad', 'm' => $labels[$f] . ': PDF ' . $this->fmt($scan[$f]) . ' ≠ tizim ' . $this->fmt($sys[$f])];
            } elseif ($st === 'ocr') {
                $flags[] = ['t' => 'ocr', 'm' => $labels[$f] . ': PDFda aniq o\'qilmadi («' . $scan[$f] . '») — PDFdan ko\'z bilan tekshiring'];
            }
        }
        if (!empty($s['verified']) && in_array('bad', $cells, true)) {
            $flags[] = ['t' => 'info', 'm' => "Ikki marta o'qildi — natija bir xil: xato vedomostning o'zida"];
        }
        if (!empty($s['repaired'])) {
            $flags[] = ['t' => 'info', 'm' => "Birinchi o'qishda xato bor edi — qayta o'qib tuzatildi"];
        }

        // F.I.Sh
        $sim = VedomostNames::similarity($stu['fish'] ?? '', $s['fio'] ?? '');
        if ($sim < 0.85) {
            $flags[] = ['t' => in_array('fio', $unclear, true) ? 'ocr' : 'bad',
                'm' => 'F.I.Sh farq qiladi (' . (int) round($sim * 100) . '%): PDF «' . ($s['fio'] ?? '') . '», tizim «' . ($stu['fish'] ?? '') . '»'];
        }

        // --- Python qoidalari (tizim baholari bo'yicha) ---
        $intl = !empty($context['international'][VedomostScanReader::digits($stu['student_id'] ?? '')]);
        $pass = (int) ($intl ? ($settings['jn_mt_pass_international'] ?? 60) : ($settings['jn_mt_pass'] ?? 60));
        $jn = $this->n($stu['jn'] ?? null);
        $mt = $this->n($stu['mt'] ?? null);
        $oski = $this->n($stu['oski'] ?? null);
        $test = $this->n($stu['test'] ?? null);

        if ($w['oski'] === 0 && $oski !== null && $oski > 0 && !$kurs6) {
            $flags[] = ['t' => 'bad', 'm' => "Tizimda OSKE = {$this->fmt($oski)} qo'yilgan, lekin vedomostda OSKE vazni 0 (fan OSKE bilan yopilmaydi)"];
        }
        if ($w['test'] === 0 && $test !== null && $test > 0 && !$kurs6) {
            $flags[] = ['t' => 'bad', 'm' => "Tizimda Test = {$this->fmt($test)} qo'yilgan, lekin vedomostda Test vazni 0 (fan Test bilan yopilmaydi)"];
        }
        $hasExam = ($w['oski'] > 0 && $oski !== null && $oski > 0) || ($w['test'] > 0 && $test !== null && $test > 0);
        if ($hasExam && (($jn !== null && $jn < $pass) || ($mt !== null && $mt < $pass))) {
            $what = [];
            if ($jn !== null && $jn < $pass) {
                $what[] = "JN {$this->fmt($jn)}%";
            }
            if ($mt !== null && $mt < $pass) {
                $what[] = "MT {$this->fmt($mt)}%";
            }
            $flags[] = ['t' => 'bad', 'm' => 'Qoida: ' . implode(', ', $what) . " (<{$pass}%) — yakuniy nazoratga kiritilmasligi kerak edi, lekin imtihon bahosi bor"];
        }
        $oskiMin = (float) ($settings['oski_min'] ?? 60);
        if ($w['oski'] > 0 && $w['test'] > 0 && $test !== null && $test > 0 && $oski !== null && $oski < $oskiMin) {
            $flags[] = ['t' => 'bad', 'm' => "Qoida: OSKE {$this->fmt($oski)}% (<{$this->fmt($oskiMin)}%) — Testga kiritilmasligi kerak edi, lekin Test bahosi bor"];
        }
        if ($closing === 'sinov' && $w['test'] > 0) {
            $tScan = $this->n($scan['test_pct']);
            $jScan = $this->n($scan['jb_pct']);
            if ($tScan !== null && $jScan !== null && abs($tScan - $jScan) > $tol) {
                $flags[] = ['t' => 'bad', 'm' => "Sinov fan: Test {$this->fmt($tScan)}% ≠ Joriy {$this->fmt($jScan)}% (joriy baho testga ko'chirilmagan)"];
            }
        }

        // --- Test markazi ---
        $tm = [];
        $sid = VedomostScanReader::digits($stu['student_id'] ?? '');
        foreach (['oski' => 'OSKE', 'test' => 'Test'] as $k => $label) {
            if ($w[$k] === 0 || !$tmUsed[$k] || ($k === 'test' && $closing === 'sinov') || $kurs6) {
                continue;
            }
            $rec = $context['test_center'][$sid][$k] ?? null;
            $sysVal = $k === 'oski' ? $oski : $test;
            if ($rec === null) {
                if ($sysVal !== null && $sysVal > 0) {
                    $tm[$k] = ['value' => null, 'status' => 'bad'];
                    $flags[] = ['t' => 'bad', 'm' => "{$label}: Test markazida natija yo'q (tizimda {$this->fmt($sysVal)})"];
                }
                continue;
            }
            $ok = abs((float) $rec['grade'] - (float) ($sysVal ?? 0)) <= $tol;
            $tm[$k] = ['value' => (float) $rec['grade'], 'status' => $ok ? 'ok' : 'bad', 'dalolat' => !empty($rec['dalolat'])];
            if (!$ok) {
                $flags[] = ['t' => 'bad', 'm' => "{$label}: Test markazi " . $this->fmt($rec['grade']) . (!empty($rec['dalolat']) ? ' (dalolatnoma)' : '') . ' ≠ tizim ' . $this->fmt($sysVal)];
            } elseif (!empty($rec['dalolat'])) {
                $flags[] = ['t' => 'info', 'm' => "{$label}: Test markazida dalolatnoma bilan " . $this->fmt($rec['grade'])];
            }
        }

        $status = 'ok';
        foreach ($flags as $f) {
            if (isset(self::RANK[$f['t']]) && self::RANK[$f['t']] > self::RANK[$status]) {
                $status = $f['t'];
            }
        }

        return [
            'key' => $s['key'], 'n' => $s['nr'] ?? ($stu['n'] ?? null), 'fio' => $stu['fish'] ?? ($s['fio'] ?? ''),
            'id' => (string) ($stu['student_id'] ?? ''), 'status' => $status,
            'system' => $sys, 'scan' => $scan, 'scan_fio' => $s['fio'] ?? '', 'scan_id' => $s['id'] ?? '',
            'orig' => $s['orig'] ?? [], 'cells' => $cells, 'unclear' => $unclear, 'edited' => [],
            'flags' => $flags, 'page' => $s['page'] ?? null, 'tm' => $tm,
        ];
    }

    /** Tizim qiymatlari: foizlar + vazn bo'yicha hisoblangan ball/YN/ECTS/baho. */
    private function systemValues(array $stu, array $w, string $levelCode, bool $kurs6, array $settings, array $context): array
    {
        $intl = !empty($context['international'][VedomostScanReader::digits($stu['student_id'] ?? '')]);
        $pass = (int) ($intl ? ($settings['jn_mt_pass_international'] ?? 60) : ($settings['jn_mt_pass'] ?? 60));

        $jn = $stu['jn'] ?? null;
        $mt = $stu['mt'] ?? null;
        $on = $stu['on'] ?? null;
        $oski = $stu['oski'] ?? null;
        $test = $stu['test'] ?? null;

        if ($kurs6) {
            $e = $this->kurs6($this->n($jn), $this->n($mt), $this->n($oski), $this->n($test), $w);
        } else {
            $e = $this->calc->compute($jn, $mt, $on, $oski, $test, $levelCode, $w, 0.0, $pass);
        }

        return [
            'jb_pct' => $this->n($jn), 'jb_ball' => $e['jb_ball'],
            'mt_pct' => $this->n($mt), 'mt_ball' => $e['mt_ball'],
            'on_pct' => $this->n($on), 'on_ball' => $e['on_ball'] ?? 0,
            'jbmton_ball' => $e['jbmton_ball'],
            'oski_pct' => $this->n($oski), 'oski_ball' => $e['oski_ball'],
            'test_pct' => $this->n($test), 'test_ball' => $e['test_ball'],
            'yn' => $e['ozlashtirish'], 'ects' => $e['ects'], 'baho' => $e['baho'],
        ];
    }

    /**
     * 6-kurs rasmiy shablon formulasi (Python vedomost_avto, kurs6):
     * JB/MT ball yaxlitlanmaydi; JN+MT = ROUND(JB+MT) (JB yoki MT <60 → 0);
     * 30 lik nazorat: ROUND(%×30/100) (<60% bo'lib 18 chiqsa → 17); 15/15: ball = %×15/100.
     * JN+MT/(wj+wm) < 60% → -2; nazorat 0 → -1; nazorat <60 → 0.
     */
    public function kurs6(?float $jn, ?float $mt, ?float $oski, ?float $test, array $w): array
    {
        $needO = $w['oski'] > 0;
        $needT = $w['test'] > 0;
        $dual = $needO && $needT;
        $ctl = function (?float $pct, int $wt) use ($dual) {
            if ($pct === null) {
                return null;
            }
            if ($dual) {
                return $pct >= 60 ? $pct * $wt / 100 : 0;
            }
            $r = (int) floor($pct * $wt / 100 + 0.5);

            return ($r == $wt * 0.6 && $pct < 60) ? $r - 1 : $r;
        };

        $jb = $jn === null ? null : ($jn >= 60 ? $jn * $w['jn'] / 100 : 0);
        $mb = $mt === null ? null : ($mt >= 60 ? $mt * $w['mt'] / 100 : 0);
        $jnmt = ($jn === null || $mt === null) ? null : (($jn < 60 || $mt < 60) ? 0 : (int) floor($jb + $mb + 0.5));
        $oE = $needO ? $ctl($oski, $w['oski']) : 0;
        $tE = $needT ? $ctl($test, $w['test']) : 0;

        $yn = null;
        if ($jnmt !== null) {
            $nPct = ($w['jn'] + $w['mt']) ? $jnmt / ($w['jn'] + $w['mt']) : 1;
            if ($nPct < 0.6) {
                $yn = -2;
            } elseif ($dual) {
                if (!$oski || !$test) {
                    $yn = -1;
                } elseif ($oski < 60 || $test < 60) {
                    $yn = 0;
                } else {
                    $yn = $jnmt + (int) floor($oE + $tE + 0.5);
                }
            } elseif ($needO || $needT) {
                $p = $needO ? $oski : $test;
                $cb = $needO ? $oE : $tE;
                $yn = !$p ? -1 : ($p < 60 ? 0 : $jnmt + $cb);
            } else {
                $yn = $jnmt;
            }
        }

        return [
            'jb_ball' => $jb, 'mt_ball' => $mb, 'on_ball' => 0, 'jbmton_ball' => $jnmt,
            'oski_ball' => $oE, 'test_ball' => $tE,
            'ozlashtirish' => $yn, 'ects' => $this->ects($yn), 'baho' => $this->baho($yn),
        ];
    }

    private function scanValues(array $s): array
    {
        $out = [];
        foreach (self::FIELDS as $f) {
            $out[$f] = trim((string) ($s[$f] ?? ''));
        }

        return $out;
    }

    private function cellStatus($sys, $scan, bool $unclear, float $tol, bool $verified): string
    {
        $e = $this->n($sys);
        $f = VedomostScanReader::num($scan);
        $scanEmpty = trim((string) $scan) === '';

        if ($e === null && $scanEmpty) {
            return 'empty';
        }
        // Ball 0 va skaner bo'sh (yoki aksincha) — vedomostda odatda bir xil.
        if ($e !== null && abs($e) < 0.001 && ($scanEmpty || ($f !== null && abs($f) < 0.001))) {
            return 'ok';
        }
        if ($f === null && !$scanEmpty) {
            return 'ocr'; // raqam emas — o'qilmagan
        }
        if ($e !== null && $f !== null && abs($e - $f) <= $tol) {
            return 'ok';
        }
        if ($unclear && !$verified) {
            return 'ocr';
        }

        return 'bad';
    }

    private function strStatus($sys, $scan, bool $unclear): string
    {
        $canon = fn ($s) => str_replace(["'", '’', 'ʼ', 'ʻ', '‘', '`', ' ', '-'], '', mb_strtolower(trim((string) $s)));
        $a = $canon($sys);
        $b = $canon($scan);
        if ($a === '' && $b === '') {
            return 'empty';
        }
        if ($a === $b || ($a === 'qoniqarsiz' && str_starts_with($b, 'qon'))) {
            return 'ok';
        }

        return $unclear ? 'ocr' : 'bad';
    }

    // ───────────────────────── sarlavha ─────────────────────────

    private function headerChecks(array $exp, array $h, array $context): array
    {
        $out = [];
        $add = function (string $key, string $label, $system, $scan, string $status, string $note = '') use (&$out) {
            $out[] = compact('key', 'label', 'system', 'scan', 'status', 'note');
        };
        $str = fn ($v) => is_array($v) ? implode(', ', $v) : trim((string) ($v ?? ''));

        // Fan nomi — qat'iy (faqat bo'shliq, apostrof turi va oxirgi nuqta kechiriladi).
        $sysFan = $str($exp['fan'] ?? '');
        $scanFan = $str($h['fan'] ?? '');
        if ($scanFan === '') {
            $add('fan', 'Fan nomi', $sysFan, '', 'bad', "Skanerda o'qilmadi");
        } else {
            $nf = fn ($x) => preg_replace('/[.\s]+$/u', '', preg_replace('/\s*([,\-–—])\s*/u', '$1', VedomostNames::uz($x)));
            if ($nf($scanFan) === $nf($sysFan)) {
                $add('fan', 'Fan nomi', $sysFan, $scanFan, 'ok');
            } elseif (mb_strtolower($nf($scanFan)) === mb_strtolower($nf($sysFan))) {
                $add('fan', 'Fan nomi', $sysFan, $scanFan, 'warn', 'Faqat katta/kichik harf farqi');
            } else {
                $add('fan', 'Fan nomi', $sysFan, $scanFan, 'bad', 'Nomi tizimdagidan farq qiladi');
            }
        }

        // Guruh
        $sysG = $str($exp['guruh'] ?? '');
        $scanG = $str($h['guruh'] ?? '');
        $gk = fn ($x) => preg_replace('/[^a-z0-9]/', '', mb_strtolower(VedomostNames::latin($x)));
        $root = $gk(preg_replace('/\s*\(.*$/', '', $sysG));
        if ($scanG === '') {
            $add('guruh', 'Guruh', $sysG, '', 'warn', "Skanerda o'qilmadi");
        } else {
            $ok = $root !== '' && (str_contains($gk($scanG), $root) || str_contains($root, $gk($scanG)));
            $add('guruh', 'Guruh', $sysG, $scanG, $ok ? 'ok' : 'bad', $ok ? '' : 'Guruh mos emas');
        }

        // Soat / kredit
        foreach (['soat' => ['umumiy_soat', 'Umumiy soat'], 'kredit' => ['kredit', 'Kredit']] as $k => [$ek, $label]) {
            $sv = $this->n($exp[$ek] ?? null);
            $pv = VedomostScanReader::num($h[$k] ?? null);
            if ($pv === null) {
                $add($k, $label, $this->fmt($sv), '', 'warn', "Skanerda o'qilmadi");
            } else {
                $add($k, $label, $this->fmt($sv), $this->fmt($pv), $sv !== null && abs($sv - $pv) < 0.01 ? 'ok' : 'bad',
                    $sv !== null && abs($sv - $pv) < 0.01 ? '' : "O'quv rejadagi qiymatdan farq qiladi");
            }
        }

        // Ma'ruzachi
        $sysM = array_values(array_filter(array_map('trim', explode(',', $str($exp['maruzachi'] ?? '')))));
        $scanM = $str($h['maruzachi'] ?? '');
        if ($scanM === '') {
            $add('maruzachi', "Ma'ruzachi", implode(', ', $sysM), '', 'bad', "Skanerda ko'rsatilmagan");
        } elseif (empty($sysM)) {
            $add('maruzachi', "Ma'ruzachi", '', $scanM, 'warn', "Tizimda ma'ruzachi aniqlanmadi");
        } else {
            $ok = collect($sysM)->contains(fn ($m) => VedomostNames::sameName($m, $scanM));
            $add('maruzachi', "Ma'ruzachi", implode(', ', $sysM), $scanM, $ok ? 'ok' : 'bad', $ok ? '' : 'Ism mos emas');
        }

        // Amaliyot o'qituvchilari
        $sysA = array_values(array_filter(array_map('trim', explode(',', $str($exp['amaliyot_oqituvchilari'] ?? '')))));
        $scanA = array_values(array_filter(array_map('trim', is_array($h['amaliyot'] ?? null) ? $h['amaliyot'] : explode(',', $str($h['amaliyot'] ?? '')))));
        $missA = array_values(array_filter($sysA, fn ($a) => !collect($scanA)->contains(fn ($b) => VedomostNames::sameName($a, $b))));
        $extraA = array_values(array_filter($scanA, fn ($b) => !collect($sysA)->contains(fn ($a) => VedomostNames::sameName($a, $b))));
        $noteA = trim(($missA ? "PDFda yo'q: " . implode(', ', $missA) . '. ' : '') . ($extraA ? "Tizimda yo'q: " . implode(', ', $extraA) : ''));
        $add('amaliyot', "Amaliyot o'qituvchilari", implode(', ', $sysA), implode(', ', $scanA),
            empty($scanA) ? 'bad' : ($missA || $extraA ? 'bad' : 'ok'), empty($scanA) ? "Skanerda ko'rsatilmagan" : $noteA);

        // Dekan
        $sysD = $str($exp['dekan'] ?? '');
        $scanD = $str($h['dekan'] ?? '');
        if ($scanD === '') {
            $add('dekan', 'Fakultet dekani', $sysD, '', 'bad', "Skanerda ko'rsatilmagan");
        } elseif ($sysD === '') {
            $add('dekan', 'Fakultet dekani', '', $scanD, 'warn', 'Tizimda dekan aniqlanmadi');
        } else {
            $ok = VedomostNames::sameName($sysD, $scanD);
            $add('dekan', 'Fakultet dekani', $sysD, $scanD, $ok ? 'ok' : 'bad', $ok ? '' : 'Ism mos emas');
        }

        // Kafedra mudiri
        $sysK = $str($context['kafedra_mudiri'] ?? '');
        $scanK = $str($h['kafedra_mudiri'] ?? '');
        if ($scanK === '') {
            $add('kafedra_mudiri', 'Kafedra mudiri', $sysK, '', 'bad', "Skanerda ko'rsatilmagan");
        } elseif ($sysK === '') {
            $add('kafedra_mudiri', 'Kafedra mudiri', '', $scanK, 'warn', 'Tizimda kafedra mudiri aniqlanmadi');
        } else {
            $ok = VedomostNames::sameName($sysK, $scanK);
            $add('kafedra_mudiri', 'Kafedra mudiri', $sysK, $scanK, $ok ? 'ok' : 'bad', $ok ? '' : 'Ism mos emas');
        }

        // YN sanasi
        $sysS = $str($exp['yn_sanasi'] ?? '');
        $scanS = $str($h['yn_sana'] ?? '');
        $dk = fn ($x) => preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $x, $m) ? sprintf('%04d%02d%02d', $m[3], $m[2], $m[1]) : '';
        if ($scanS === '') {
            $add('yn_sana', 'YaN sanasi', $sysS, '', 'warn', "Skanerda o'qilmadi");
        } elseif ($sysS === '') {
            $add('yn_sana', 'YaN sanasi', '', $scanS, 'info', 'Tizimda YN sanasi yo\'q');
        } else {
            $ok = collect(explode(',', $sysS))->contains(fn ($d) => $dk($d) !== '' && $dk($d) === $dk($scanS));
            $add('yn_sana', 'YaN sanasi', $sysS, $scanS, $ok ? 'ok' : 'warn', $ok ? '' : 'Sana tizimdagidan farq qiladi');
        }

        // Kurs / semestr
        foreach (['kurs' => 'Kurs', 'semestr' => 'Semestr'] as $k => $label) {
            $sv = $str($exp[$k] ?? '');
            $pv = $str($h[$k] ?? '');
            if ($pv === '' || $sv === '') {
                continue;
            }
            $ok = VedomostScanReader::digits($sv) === VedomostScanReader::digits($pv);
            $add($k, $label, $sv, $pv, $ok ? 'ok' : 'warn', $ok ? '' : 'Farq qiladi');
        }

        return $out;
    }

    private function weightIssues(array $w, string $closing, bool $kurs6): array
    {
        $out = [];
        $needO = in_array($closing, ['oski', 'oski_test'], true);
        $needT = in_array($closing, ['test', 'oski_test', 'sinov'], true);
        if ($needO && $w['oski'] === 0) {
            $out[] = ['t' => 'bad', 'm' => "Fan OSKE bilan yopiladi, lekin vedomostda OSKE vazni 0"];
        }
        if ($needT && $w['test'] === 0) {
            $out[] = ['t' => 'bad', 'm' => "Fan Test bilan yopiladi, lekin vedomostda Test vazni 0"];
        }
        if (!$needO && $w['oski'] > 0 && $closing !== '') {
            $out[] = ['t' => 'bad', 'm' => "Fan OSKE bilan yopilmaydi, lekin vedomostda OSKE vazni {$w['oski']}"];
        }
        if (!$needT && $w['test'] > 0 && $closing !== '' && $closing !== 'normativ') {
            $out[] = ['t' => 'bad', 'm' => "Fan Test bilan yopilmaydi, lekin vedomostda Test vazni {$w['test']}"];
        }
        $sum = array_sum($w);
        if ($sum > 0 && $sum !== 100) {
            $out[] = ['t' => 'ocr', 'm' => "Vaznlar yig'indisi {$sum} (100 bo'lishi kerak) — vaznlar to'g'ri o'qilganini tekshiring"];
        }

        return $out;
    }

    private function activeControls(array $w, array $rows): array
    {
        $out = ['jb', 'mt'];
        $hasOn = $w['on'] > 0 || collect($rows)->contains(fn ($r) => ($r['scan']['on_pct'] ?? '') !== '' || ($r['system']['on_pct'] ?? null) !== null);
        if ($hasOn) {
            $out[] = 'on';
        }
        if ($w['oski'] > 0) {
            $out[] = 'oski';
        }
        if ($w['test'] > 0) {
            $out[] = 'test';
        }

        return $out;
    }

    private function summary(array $c, int $docBad, int $sysN, int $scanN): string
    {
        $parts = [];
        if ($c['bad']) {
            $parts[] = "{$c['bad']} talabada farq";
        }
        if ($c['missing']) {
            $parts[] = "{$c['missing']} talaba PDFda yo'q";
        }
        if ($c['extra']) {
            $parts[] = "{$c['extra']} ortiqcha talaba";
        }
        if ($c['ocr']) {
            $parts[] = "{$c['ocr']} qator ko'z bilan tekshirilsin";
        }
        if ($docBad) {
            $parts[] = "{$docBad} ta sarlavha/imzo xatosi";
        }

        return ucfirst(implode(', ', $parts)) . ". Tizimda {$sysN}, skanerda {$scanN} talaba.";
    }

    public function fieldLabels(): array
    {
        return [
            'jb_pct' => 'JB %', 'jb_ball' => 'JB ball', 'mt_pct' => 'MT %', 'mt_ball' => 'MT ball',
            'on_pct' => 'ON %', 'on_ball' => 'ON ball', 'jbmton_ball' => 'JB+MT+ON ball',
            'oski_pct' => 'OSKE %', 'oski_ball' => 'OSKE ball', 'test_pct' => 'Test %', 'test_ball' => 'Test ball',
            'yn' => "O'zlashtirish (YN)", 'ects' => 'ECTS', 'baho' => 'Baho', 'fio' => 'F.I.Sh', 'id' => 'ID raqami',
        ];
    }

    private function ects($v): string
    {
        if (!is_numeric($v)) {
            return '';
        }
        if ($v == -1 || $v == -3) {
            return '';
        }
        if ($v < 0) {
            return 'FX';
        }

        return $v >= 90 ? 'A' : ($v >= 85 ? 'B+' : ($v >= 70 ? 'B' : ($v >= 60 ? 'C' : 'F')));
    }

    private function baho($v): string
    {
        if (!is_numeric($v)) {
            return '';
        }

        return match (true) {
            $v == -3 => 'davomat ≥25%',
            $v == -2 => "qo'yilmadi",
            $v == -1 => 'kelmadi',
            $v >= 90 => "a'lo",
            $v >= 70 => 'yaxshi',
            $v >= 60 => "o'rta",
            default => 'qoniqarsiz',
        };
    }

    private function n($v): ?float
    {
        return VedomostScanReader::num($v);
    }

    public function fmt($v): string
    {
        $n = VedomostScanReader::num($v);
        if ($n === null) {
            return $v === null || $v === '' ? '—' : (string) $v;
        }

        return $n == (int) $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }
}
