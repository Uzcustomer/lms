<?php

namespace App\Services;

/**
 * Vedomost tekshiruv dvigateli — Python "vedomost_avto" dasturining run_engine /
 * header_checks mantig'i. "Tizim" (HEMIS) tomoni — "Vedomost tekshirish" eksporti
 * bilan AYNAN bir xil qiymatlar (VedomostTekshirishService), PDF tomoni — skanerdan
 * o'qilgan jadval (+ xodim tuzatishlari).
 *
 * Sof funksiya: bazaga murojaat qilmaydi — tashqi ma'lumot (Test markazi, kontingent,
 * kafedra mudiri) $context orqali keladi. Shu bois xodim PDF qiymatini tuzatganda
 * hisobot AI'siz darhol qayta quriladi.
 */
class VedomostCheckEngine
{
    public const VERSION = 3;

    /** PDF (BQ) jadvali maydonlari. */
    public const PDF_FIELDS = [
        'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball', 'jbmton_ball', 'jbmton_pct',
        'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects', 'baho',
    ];

    /** Xodim tahrirlay oladigan PDF maydonlari. */
    public const EDITABLE = ['fio', 'id', ...self::PDF_FIELDS];

    /**
     * Xodim faqat shu hisobot ichida o'zgartira oladigan Mark qiymatlari (VT ustuni => xom maydon).
     * Ballar, JN+MT va YN shulardan qayta hisoblanadi; jurnal/Mark/HEMIS'ga hech narsa yozilmaydi.
     */
    public const SYSTEM_EDITABLE = ['D' => 'jn', 'G' => 'mt', 'J' => 'on', 'P' => 'oski', 'S' => 'test'];

    public const HEADER_EDITABLE = ['fan', 'guruh', 'soat', 'kredit', 'maruzachi', 'amaliyot', 'yn_sana', 'dekan', 'kafedra_mudiri', 'kurs', 'semestr', 'shakl'];

    /**
     * Python cmp(): PDF ↔ HEMIS solishtiriladigan 5 ustun.
     * kalit => [yorliq, PDF maydoni, HEMIS (VT) ustuni]
     */
    public const COMPARE = [
        'jn' => ['JN%', 'jb_pct', 'D'],
        'mtb' => ['MT ball', 'mt_ball', 'H'],
        'oski' => ['OSKE%', 'oski_pct', 'P'],
        'test' => ['Test%', 'test_pct', 'S'],
        'yn' => ['YN', 'yn', 'V'],
    ];

    /** "Vedomost tekshirish" Excel ustunlari (A..AW). */
    public const VT_HEADERS = [
        'A' => '#', 'B' => 'FIO', 'C' => 'ID raqami', 'D' => 'JN', 'E' => 'JN ball', 'F' => '', 'G' => 'MT', 'H' => 'MT ball',
        'I' => '', 'J' => 'ON', 'K' => 'ON ball', 'L' => '', 'M' => 'JN+MT', 'N' => 'JN+MT %', 'O' => '', 'P' => 'OSKI',
        'Q' => 'OSKI ball', 'R' => '', 'S' => 'Test', 'T' => 'Test ball', 'U' => '', 'V' => 'YN natija', 'W' => 'ECTS',
        'X' => "Amaliyot o'q.", 'Y' => 'Baho', 'Z' => 'Talaba HEMIS ID', 'AA' => 'Fan ID', 'AB' => 'Fan nomi',
        'AC' => 'Qaydnoma turi', 'AD' => "O'quv yili", 'AE' => 'Semestr', 'AF' => 'Guruh HEMIS ID', 'AG' => 'Guruh nomi',
        'AH' => 'Soat', 'AI' => 'Kredit', 'AJ' => "Ma'ruzachi", 'AK' => 'JN soni', 'AL' => 'Divisor', 'AM' => 'Davomat %',
        'AN' => 'jn_vazn', 'AO' => 'mt_vazn', 'AP' => 'on_vazn', 'AQ' => 'oski_vazn', 'AR' => 'test_vazn', 'AS' => 'Zanjir',
        'AT' => 'JN+MT tekshiruv', 'AU' => 'JN+MT izoh', 'AV' => 'YN tekshiruv', 'AW' => 'YN izoh',
    ];

    public const STATUS_TXT = [
        'ok' => 'OK', 'bad' => 'XATO', 'ocr' => 'PDFdan tekshiring', 'missing' => "KONTINGENTDA YO'Q", 'nopdf' => "PDFda yo'q",
    ];

    private const RANK = ['ok' => 0, 'ocr' => 1, 'bad' => 2, 'nopdf' => 3, 'missing' => 4];

    public function __construct(private VedomostTekshirishService $vt)
    {
    }

    /**
     * @param  array  $settings  tolerance, jn_mt_pass, oski_min, test_min
     * @param  array  $context  kafedra_mudiri, test_center[id][oski|test], kontingent[id]
     */
    public function build(array $expected, array $scan, array $edits, array $settings, array $context = []): array
    {
        $tol = (float) ($settings['tolerance'] ?? 0.05);
        [$scanRows, $header, $weights, $signatures, $editedKeys] = $this->applyEdits($scan, $edits);

        $closing = (string) ($expected['yopilish_shakli'] ?? '');
        $w = $this->weights($weights, $closing);
        $useO = $w['oski'] > 0;
        $useT = $w['test'] > 0;
        $sinov = $closing === 'sinov';
        $kurs6 = (string) ($expected['level_code'] ?? '') === '16';

        // --- tizim (VT) qatorlari ---
        $system = [];
        foreach (array_values($expected['talabalar'] ?? []) as $i => $stu) {
            $stu = $this->applySystemEdits($stu, $edits['system'][VedomostScanReader::digits($stu['student_id'] ?? '')] ?? []);
            $stu['vt'] = $this->vtRow($stu, $w, $expected, $i + 1);
            $system[] = $stu;
        }

        // --- PDF ↔ tizim moslashtirish ---
        [$pairs, $extra, $dupIds] = $this->match($system, $scanRows);
        $missingNrs = $scan['meta']['missing_nrs'] ?? [];

        $tmUsed = ['oski' => false, 'test' => false];
        foreach ($context['test_center'] ?? [] as $rec) {
            $tmUsed['oski'] = $tmUsed['oski'] || isset($rec['oski']);
            $tmUsed['test'] = $tmUsed['test'] || isset($rec['test']);
        }

        $rows = [];
        $tmTestFound = 0;
        foreach ($system as $idx => $stu) {
            $vt = $stu['vt'];
            $sid = VedomostScanReader::digits($stu['student_id'] ?? '');
            $flags = [];
            foreach ($stu['sys_edited'] ?? [] as $col => $why) {
                $flags[] = ['t' => 'info', 'm' => 'Mark qiymati faqat hisobotda o‘zgartirilgan: ' . (self::VT_HEADERS[$col] ?? $col) . ' '
                    . $this->f($stu['sys_orig'][$col] ?? null) . ' → ' . $this->f($stu['raw'][self::SYSTEM_EDITABLE[$col]] ?? null) . ($why !== '' ? " (sabab: {$why})" : '')];
            }
            $status = 'ok';
            $set = function (string $s) use (&$status) {
                if (self::RANK[$s] > self::RANK[$status]) {
                    $status = $s;
                }
            };
            $cells = [];
            [$s, $matchFlags] = $pairs[$idx] ?? [null, []];
            $expelled = ($stu['status_code'] ?? '') === '60';
            // Chetlashtirilgan va PDFda ham yo'q talaba — hisobotga chiqmaydi (vedomostda bo'lishi shart emas).
            if ($expelled && $s === null) {
                continue;
            }

            if ($s !== null) {
                foreach ($matchFlags as [$t, $m]) {
                    $flags[] = ['t' => $t, 'm' => $m];
                    if ($t !== 'info') {
                        $set($t);
                    }
                }
                if (isset($dupIds[$sid])) {
                    $flags[] = ['t' => 'bad', 'm' => 'PDFda bu talaba bir necha marta uchraydi (ID mos)'];
                    $set('bad');
                }
                $unclear = $s['unclear'] ?? [];
                // Bo'yalgan (kulrang) qatorlarda AI minus belgisini yo'qotadi: baho «kelmadi» /
                // «qo'yilmadi» yoki FX bo'la turib YN musbat/0 o'qilsa — o'qish xatosi deb olinadi.
                if (VedomostScanReader::ynConflict($s)) {
                    $unclear[] = 'yn';
                }
                // Kulrang qatorda hamma ustun 0 o'qilsa (HEMISda JN/MT bor) — o'qish xatosi ehtimoli katta.
                if (VedomostScanReader::num($s['jb_pct'] ?? null) === 0.0 && VedomostScanReader::num($s['mt_pct'] ?? null) === 0.0
                    && ((VedomostScanReader::num($vt['D']) ?? 0) > 0 || (VedomostScanReader::num($vt['G']) ?? 0) > 0)) {
                    array_push($unclear, 'jb_pct', 'mt_ball');
                }
                foreach (self::COMPARE as $key => [$label, $pf, $col]) {
                    if (($key === 'oski' && !$useO) || ($key === 'test' && !$useT)) {
                        $cells[$key] = ['status' => 'skip'];
                        continue;
                    }
                    $hemisVal = $vt[$col];
                    // Vedomost shablonida JN yoki MT o'tmagan talabaga YN = -2 (FX) yoziladi,
                    // «Vedomost tekshirish» hisobida esa 0 — bu bir xil holat, farq emas.
                    if ($key === 'yn' && VedomostScanReader::num($s[$pf] ?? null) === -2.0 && VedomostScanReader::num($hemisVal) === 0.0
                        && $this->jnMtFailed($vt, $settings)) {
                        $hemisVal = -2;
                    }
                    $cells[$key] = $this->cmp($label, $hemisVal, $s[$pf] ?? '', in_array($pf, $unclear, true), $tol, $flags, $set);
                }
                if (!empty($s['verified']) && !empty($s['problems'])) {
                    $flags[] = ['t' => 'bad', 'm' => "Vedomostning o'zida hisob xatosi (ikki marta o'qildi, bir xil): " . implode('; ', $s['problems'])];
                    $set('bad');
                } elseif (!empty($s['problems'])) {
                    $flags[] = ['t' => 'ocr', 'm' => "PDF qatorining ichki hisobi mos emas: " . implode('; ', $s['problems']) . ' — PDFdan tekshiring'];
                    $set('ocr');
                }
                if ($kurs6) {
                    foreach ($this->kurs6Checks($s, $w) as $m) {
                        $flags[] = ['t' => 'bad', 'm' => '6-kurs: ' . $m];
                        $set('bad');
                    }
                }
            } else {
                $gapNote = !empty($missingNrs) ? ' (AI PDFdagi ' . implode(', ', $missingNrs) . '-qatorni o\'qiy olmadi — PDFdan tekshiring)' : '';
                if ($expelled) {
                    $flags[] = ['t' => 'missing', 'm' => "KONTINGENTDA YO'Q va PDFda ham yo'q (Markda «chetlashgan» deb belgilangan)"];
                    $set('missing');
                } elseif ($gapNote !== '') {
                    $flags[] = ['t' => 'ocr', 'm' => "PDFda topilmadi" . $gapNote];
                    $set('ocr');
                } else {
                    $flags[] = ['t' => 'nopdf', 'm' => "PDFda yo'q"];
                    $set('nopdf');
                }
            }
            if ($expelled && $s !== null) {
                $flags[] = ['t' => 'missing', 'm' => "KONTINGENTDA YO'Q (talaba chetlashtirilgan)"];
                $set('missing');
            }

            // Test markazi ↔ HEMIS. Imtihonga kiritilmagan talabada HEMISdagi 0 qoida bo'yicha
            // to'g'ri — test markazidagi natija (topshirgan bo'lsa ham) hisobga olinmaydi.
            $tm = [];
            $skipAll = $expelled ? 'talaba chetlashtirilgan'
                : ($this->jnMtFailed($vt, $settings) ? 'JN/MT o‘tmagan — imtihonga kiritilmagan' : null);
            $oskeVt = VedomostScanReader::num($vt['P']);
            $skipTest = $skipAll ?? (($useO && $useT && $oskeVt !== null && $oskeVt < (float) ($settings['oski_min'] ?? 60))
                ? 'OSKE ' . $this->f($oskeVt) . '% — Testga kiritilmagan' : null);
            if ($useO && $tmUsed['oski'] && !$kurs6) {
                $tm['oski'] = $skipAll
                    ? $this->skipTm($context['test_center'][$sid]['oski'] ?? null, $vt['P'], $skipAll)
                    : $this->checkTm($context['test_center'][$sid]['oski'] ?? null, $vt['P'], 'OSKE', $flags, $set);
            }
            if ($useT && $sinov && !$kurs6) {
                $t = VedomostScanReader::num($vt['S']);
                $jn = VedomostScanReader::num($vt['D']);
                $yn = VedomostScanReader::num($vt['V']);
                $has = ($t !== null && $t != 0) || ($yn !== null && $yn > 0);
                $tm['test'] = ['tm' => null, 'hemis' => $t, 'status' => 'ok', 'sinov' => true, 'jn' => $jn];
                if ($has && !$this->near($t ?? 0, $jn ?? 0, $tol)) {
                    $tm['test']['status'] = 'bad';
                    $flags[] = ['t' => 'bad', 'm' => 'Sinov fan: Test ' . $this->f($t) . '% ≠ Joriy ' . $this->f($jn) . '%'];
                    $set('bad');
                }
            } elseif ($useT && $tmUsed['test'] && !$kurs6 && $skipTest) {
                $tm['test'] = $this->skipTm($context['test_center'][$sid]['test'] ?? null, $vt['S'], $skipTest);
            } elseif ($useT && $tmUsed['test'] && !$kurs6) {
                $tm['test'] = $this->checkTm($context['test_center'][$sid]['test'] ?? null, $vt['S'], 'Test', $flags, $set);
                if (($tm['test']['tm'] ?? null) !== null) {
                    $tmTestFound++;
                }
            }

            // Shu nazorat bilan yopilmasa-yu, HEMISda baho turgan bo'lsa
            foreach ([[$useO, 'P', 'OSKE', 'oski_vazn'], [$useT, 'S', 'Test', 'test_vazn']] as [$use, $col, $nm, $vn]) {
                $v = VedomostScanReader::num($vt[$col]);
                if (!$use && $v !== null && $v != 0 && !$kurs6) {
                    $flags[] = ['t' => 'bad', 'm' => "Markda {$nm} = " . $this->f($v) . " qo‘yilgan, lekin bu fan {$nm} bilan yopilmaydi ({$vn} 0)"];
                    $set('bad');
                }
            }

            foreach ($this->entryCheck($vt, $w, $settings) as $p) {
                $flags[] = ['t' => 'bad', 'm' => 'Qoida: ' . $p];
                $set('bad');
            }
            if (!$kurs6 && ($cc = $this->calcCheck($vt, $w, $settings))) {
                if ($cc['jnmtBad']) {
                    $flags[] = ['t' => 'bad', 'm' => 'JN+MT hisobi: Mark ' . $this->f($cc['jnmtHemis']) . ' ≠ hisob ' . $cc['jnmtCalc']];
                    $set('bad');
                }
                if ($cc['ynBad']) {
                    $flags[] = ['t' => 'bad', 'm' => 'YN hisobi: Mark ' . $this->f($cc['ynHemis']) . ' ≠ hisob ' . $cc['ynCalc']];
                    $set('bad');
                }
            }

            $rows[] = [
                'key' => $s['key'] ?? null,
                'n' => $vt['A'],
                'id' => $sid,
                'fio' => $stu['fish'] ?? '',
                'group' => $stu['group'] ?? '',
                'vt' => $vt,
                'pdf' => $s ? $this->pdfValues($s) : null,
                'scan_fio' => $s['fio'] ?? null,
                'scan_id' => $s['id'] ?? null,
                'page' => $s['page'] ?? null,
                'nr' => $s['nr'] ?? null,
                'unclear' => $s['unclear'] ?? [],
                'orig' => $s['orig'] ?? [],
                'sys_edited' => $stu['sys_edited'] ?? [],
                'sys_orig' => $stu['sys_orig'] ?? [],
                'edited' => $s ? ($editedKeys[$s['key']] ?? []) : [],
                'cells' => $cells,
                'tm' => $tm,
                'kont_fio' => $expelled ? null : ($stu['fish'] ?? ''),
                'status' => $status,
                'flags' => $flags,
            ];
        }

        $extraRows = [];
        foreach ($extra as $s) {
            $sid = VedomostScanReader::digits($s['id'] ?? '');
            $k = $context['kontingent'][$sid] ?? null;
            $extraRows[] = [
                'key' => $s['key'], 'id' => $s['id'] ?? '', 'fio' => $s['fio'] ?? '', 'page' => $s['page'] ?? null, 'nr' => $s['nr'] ?? null,
                'pdf' => $this->pdfValues($s), 'unclear' => $s['unclear'] ?? [], 'orig' => $s['orig'] ?? [],
                'edited' => $editedKeys[$s['key']] ?? [],
                'note' => $k ? 'Boshqa guruh talabasi: ' . ($k['group'] ?? '?') . ' (' . ($k['fio'] ?? '') . ')' : "Kontingentda yo'q",
            ];
        }

        // --- hujjat darajasidagi ogohlantirishlar (Python R['issues']) ---
        $issues = [];
        $maruza = collect($system)->pluck('vt.AJ')->filter()->unique()->values();
        if ($maruza->count() > 1) {
            $issues[] = ['t' => 'warn', 'm' => "Ma'ruzachi bir xil emas: " . $maruza->implode(' / ')];
        }
        if ($useT && !$sinov && $tmUsed['test'] === false && !$kurs6) {
            $issues[] = ['t' => 'info', 'm' => "Test markazida bu fan bo‘yicha birorta ham Test natijasi topilmadi. Agar bu sinov fan bo‘lsa, yopilish shakli \"sinov\" bo‘lishi kerak (Test % Joriy % bilan solishtiriladi)."];
        }
        if ($sinov) {
            $issues[] = ['t' => 'info', 'm' => 'Sinov fan rejimi: Test % Joriy % bilan solishtirildi, Test markazi Test fayli ishlatilmadi.'];
        }
        foreach ($this->weightIssues($w, $closing, $weights) as $wi) {
            $issues[] = $wi;
        }
        if (!empty($missingNrs)) {
            $issues[] = ['t' => 'ocr', 'm' => 'AI PDFdagi ' . implode(', ', $missingNrs) . "-qator(lar)ni o'qiy olmadi — PDFdan ko'z bilan tekshiring yoki qayta tekshiring."];
        }

        $headerChecks = $this->headerChecks($expected, $system, $header, $context, $signatures);
        // Yopilish shakli fan nomidan keyin turadi.
        $at = array_search('fan', array_column($headerChecks, 'key'), true);
        array_splice($headerChecks, $at === false ? 0 : $at + 1, 0, [$this->closingCheck($closing, $weights, $kurs6)]);

        // --- noto'g'ri hujjat ---
        $sysN = count($rows);
        $scanN = count($scanRows);
        $matchedN = count(array_filter($pairs, fn ($p) => ($p[0] ?? null) !== null));
        $wrong = [];
        if (($header['fan'] ?? '') !== '' && VedomostNames::subjectScore($header['fan'], $expected['fan'] ?? '') < 0.6) {
            $wrong[] = 'fan nomi: tizimda «' . ($expected['fan'] ?? '') . '», skanerda «' . $header['fan'] . '»';
        }
        if ($sysN > 0 && $scanN > 0 && $matchedN / max($sysN, $scanN) < 0.5) {
            $wrong[] = "talabalar: tizimdagi {$sysN} talabadan faqat {$matchedN} tasi skanerda topildi";
        }

        $counts = ['total' => $sysN, 'ok' => 0, 'bad' => 0, 'ocr' => 0, 'missing' => 0, 'nopdf' => 0, 'extra' => count($extraRows),
            'edited' => count(array_filter($rows, fn ($r) => !empty($r['edited']))) + count(array_filter($extraRows, fn ($r) => !empty($r['edited']))),
            'sys_edited' => array_sum(array_map(fn ($r) => count($r['sys_edited'] ?? []), $rows))];
        foreach ($rows as $r) {
            $counts[$r['status']]++;
        }
        // Ishchi o'quv reja bilan solishtirish faqat ko'rsatiladi — hukmga (va tasdiqlash shartiga) ta'sir qilmaydi.
        $verdictChecks = array_values(array_filter($headerChecks, fn ($h) => !str_starts_with($h['key'], 'reja')));
        $hdrBad = count(array_filter($verdictChecks, fn ($h) => $h['st'] === 'bad'));

        if ($wrong) {
            $verdict = 'wrong_document';
            $summary = "Yuklangan PDF bu vedomostga tegishli emas ko'rinadi (" . implode('; ', $wrong) . "). To'g'ri faylni yuklang.";
        } elseif ($counts['bad'] + $counts['missing'] + $counts['nopdf'] + $counts['extra'] + $hdrBad > 0) {
            $verdict = 'issues';
            $summary = $this->summary($counts, $hdrBad, $sysN, $scanN);
        } elseif ($counts['ocr'] > 0 || collect($verdictChecks)->contains('st', 'warn')) {
            $verdict = 'review';
            $summary = "Farq topilmadi, lekin {$counts['ocr']} ta qatorni PDFdan ko'z bilan tekshirish kerak.";
        } else {
            $verdict = 'ok';
            $summary = "Vedomost tizim ma'lumotiga to'liq mos keladi ({$sysN} talaba).";
        }

        return [
            'version' => self::VERSION,
            'verdict' => $verdict,
            'summary' => $summary,
            'counts' => $counts,
            'weights' => $w,
            'use' => ['oski' => $useO, 'test' => $useT, 'tm_oski' => $tmUsed['oski'], 'tm_test' => $tmUsed['test'] || $sinov],
            'closing_form' => $closing,
            'sinov' => $sinov,
            'kurs6' => $kurs6,
            'header' => $headerChecks,
            'signatures' => $signatures,
            'issues' => $issues,
            'rows' => $rows,
            'extra' => $extraRows,
            'scan_header' => $header,
            'meta' => $scan['meta'] ?? [],
        ];
    }

    // ───────────────────────── VT qatori ─────────────────────────

    /** "Vedomost tekshirish" Excel qatori (A..AW) — eksport bilan aynan bir xil hisob. */
    public function vtRow(array $stu, array $w, array $expected, int $n): array
    {
        $raw = $stu['raw'];
        $c = $this->vt->compute($raw, $w['jb'], $w['mt'], $w['on'], $w['oski'], $w['test'], (string) ($expected['level_code'] ?? ''));
        $m = $stu['meta'] ?? [];
        $dav = (float) $c['dav'];
        $fio = $stu['fish'] ?? '';
        if ($dav >= 25) {
            $fio .= ' (' . rtrim(rtrim(number_format($dav, 1, '.', ''), '0'), '.') . '% davomat)';
        }

        $row = [
            'A' => $n, 'B' => $fio, 'C' => (string) ($stu['student_id'] ?? ''),
            'D' => $c['jn'], 'E' => $c['jnBall'], 'F' => null, 'G' => $c['mt'], 'H' => $c['mtBall'], 'I' => null,
            'J' => $c['on'], 'K' => $c['onBall'], 'L' => null, 'M' => $c['sumBall'],
            'N' => $c['maxSum'] > 0 ? $c['sumBall'] / $c['maxSum'] : null, 'O' => null,
            'P' => $c['oski'], 'Q' => $c['oskiBall'], 'R' => null, 'S' => $c['test'], 'T' => $c['testBall'], 'U' => null,
            'V' => $c['yn'] === '' ? '' : $c['yn'], 'W' => $c['ects'], 'X' => $m['practice_teacher'] ?? '', 'Y' => $c['baho'],
            'Z' => (string) ($stu['hemis_id'] ?? ''), 'AA' => $m['subject_id'] ?? '', 'AB' => $m['subject_name'] ?? '',
            'AC' => $m['shakl'] ?? '', 'AD' => $m['education_year'] ?? '', 'AE' => $m['semester'] ?? '',
            'AF' => (string) ($m['group_hemis_id'] ?? ''), 'AG' => $m['group_name'] ?? '', 'AH' => $m['hours'] ?? null,
            'AI' => $m['credit'] ?? null, 'AJ' => $m['lecture_teacher'] ?? '', 'AK' => $raw['jnCount'] ?? null,
            'AL' => $m['divisor'] ?? null, 'AM' => $dav / 100,
            'AN' => $w['jb'], 'AO' => $w['mt'], 'AP' => $w['on'], 'AQ' => $w['oski'], 'AR' => $w['test'], 'AS' => $c['zanjir'],
        ];

        // HEMIS bilan taqqoslash (eksportdagidek): JN+MT — 11, YN — 13 (faqat V > 0)
        $he = $stu['hemis_exam'] ?? [];
        $h11 = $he['11'] ?? null;
        [$row['AT'], $row['AU']] = $h11 === null ? ['D', "Hemisda yo'q"]
            : ((int) $h11 !== (int) $c['sumBall'] ? ['D', "{$c['sumBall']}≠{$h11}"] : ['', '']);
        $h13 = $he['13'] ?? null;
        if (is_numeric($c['yn']) && $c['yn'] > 0) {
            [$row['AV'], $row['AW']] = $h13 === null ? ['D', "Hemisda yo'q"]
                : ((int) $h13 !== (int) $c['yn'] ? ['D', "{$c['yn']}≠{$h13}"] : ['', '']);
        } else {
            [$row['AV'], $row['AW']] = ['', ''];
        }

        return $row;
    }

    /** Vaznlar: skanerdan (yig'indisi 100 bo'lsa), aks holda yopilish shakli bo'yicha (eksport oynasidagi standart). */
    private function weights(array $scanW, string $closing): array
    {
        $w = [];
        foreach (['jb', 'mt', 'on', 'oski', 'test'] as $k) {
            $w[$k] = (int) round((float) ($scanW[$k] ?? 0));
        }
        if (array_sum($w) === 100) {
            return $w;
        }

        return self::defaultWeights($closing);
    }

    /** Yopilish shakli bo'yicha standart vaznlar («Vedomost tekshirish» eksport oynasidagidek). */
    public static function defaultWeights(?string $closing): array
    {
        return match ((string) $closing) {
            'sinov', 'test' => ['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30],
            'oski' => ['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 30, 'test' => 0],
            'oski_test' => ['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15],
            default => ['jb' => 80, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 0],
        };
    }

    // ───────────────────────── solishtirish (Python cmp) ─────────────────────────

    private function cmp(string $label, $hemis, $pdfRaw, bool $dirty, float $tol, array &$flags, callable $set): array
    {
        $he = VedomostScanReader::num($hemis);
        $raw = trim((string) $pdfRaw);
        $p = VedomostScanReader::num($raw);
        $c = ['hemis' => $he, 'pdf' => $p, 'raw' => $raw, 'delta' => ($p !== null && $he !== null) ? round($p - $he, 2) : null];

        if ($raw === '') {
            if ($he !== null && $he != 0) {
                // Vedomost Markdan chop etiladi: Markda qiymat bo'la turib PDF katagi bo'sh bo'lishi deyarli
                // uchramaydi — odatda AI kulrang qatordagi katakni o'qimagan. XATO emas, «PDFdan tekshiring».
                $flags[] = ['t' => 'ocr', 'm' => "{$label}: PDFda bo‘sh o‘qildi (Mark " . $this->f($he) . ') — PDFdan ko‘z bilan tekshiring'];
                $set('ocr');

                return $c + ['status' => 'ocr'];
            }

            return $c + ['status' => 'empty'];
        }
        if ($dirty && ($p === null || !$this->near($p, $he, $tol))) {
            $flags[] = ['t' => 'ocr', 'm' => "{$label}: PDFda aniq o‘qilmadi (\"{$raw}\") — PDFdan ko‘z bilan tekshiring"];
            $set('ocr');

            return $c + ['status' => 'ocr'];
        }
        if ($p === null) {
            $set('ocr');

            return $c + ['status' => 'ocr'];
        }
        if (!$this->near($p, $he, $tol)) {
            $flags[] = ['t' => 'bad', 'm' => "{$label}: PDF {$raw} ≠ Mark " . $this->f($he)];
            $set('bad');

            return $c + ['status' => 'bad'];
        }

        return $c + ['status' => 'ok'];
    }

    private function skipTm(?array $rec, $hemis, string $why): array
    {
        return ['tm' => $rec !== null ? (float) $rec['grade'] : null, 'hemis' => VedomostScanReader::num($hemis), 'status' => 'skip', 'delta' => null, 'note' => $why];
    }

    private function checkTm(?array $rec, $hemis, string $label, array &$flags, callable $set): array
    {
        $he = VedomostScanReader::num($hemis);
        if ($rec === null) {
            if ($he !== null && $he > 0) {
                $flags[] = ['t' => 'bad', 'm' => "{$label}: Test markazida yo‘q (Mark " . $this->f($he) . ')'];
                $set('bad');

                return ['tm' => null, 'hemis' => $he, 'status' => 'bad', 'delta' => null];
            }

            return ['tm' => null, 'hemis' => $he, 'status' => 'ok', 'delta' => null, 'nd' => true];
        }
        $tm = (float) $rec['grade'];
        $hv = $he ?? 0;
        $ok = $this->near($tm, $hv, 0.05);
        $dal = !empty($rec['dalolat']) ? ' (Dalolatnoma)' : '';
        if (!$ok) {
            $flags[] = ['t' => 'bad', 'm' => "{$label} (Test markazi{$dal}) " . $this->f($tm) . ' ≠ Mark ' . ($he !== null ? $this->f($he) : 'bo‘sh')];
            $set('bad');
        } elseif ($dal) {
            $flags[] = ['t' => 'info', 'm' => "{$label}: Test markazida Dalolatnoma bilan " . $this->f($tm)];
        }

        return ['tm' => $tm, 'hemis' => $he, 'status' => $ok ? 'ok' : 'bad', 'delta' => $he !== null ? round($tm - $hv, 2) : null, 'dal' => !empty($rec['dalolat'])];
    }

    /** Python entry_check: JN/MT o'tmagan talabada imtihon bahosi; OSKE < min bo'lsa Test bahosi. */
    private function entryCheck(array $vt, array $w, array $settings): array
    {
        $jn = VedomostScanReader::num($vt['D']);
        $mtB = VedomostScanReader::num($vt['H']);
        $oske = VedomostScanReader::num($vt['P']);
        $test = VedomostScanReader::num($vt['S']);
        $yn = VedomostScanReader::num($vt['V']);
        if (!($yn !== null && $yn > 0)) {
            return [];
        }
        $jnMin = (float) ($settings['jn_mt_pass'] ?? 60);
        $mtMin = $jnMin * $w['mt'] / 100;
        $oskeMin = (float) ($settings['oski_min'] ?? 60);
        $useO = $w['oski'] > 0;
        $useT = $w['test'] > 0;
        $hasO = $oske !== null && $oske > 0;
        $hasT = $test !== null && $test > 0;
        $jnOk = $jn !== null && $jn >= $jnMin;
        $mtOk = $mtB !== null && $mtB >= $mtMin;
        $final = $useO ? 'OSKE' : ($useT ? 'Test' : 'yakuniy');
        $out = [];
        if ((($useO && $hasO) || ($useT && $hasT)) && (!$jnOk || !$mtOk)) {
            $d = [];
            if (!$jnOk) {
                $d[] = 'JN ' . $this->f($jn) . '%';
            }
            if (!$mtOk) {
                $d[] = 'MT ' . $this->f($mtB) . ' ball';
            }
            $out[] = implode(', ', $d) . " — {$final}ga kiritilmasligi kerak edi, lekin {$final} bahosi bor";
        }
        if ($useO && $useT && $hasT && $oske !== null && $oske < $oskeMin) {
            $out[] = 'OSKE ' . $this->f($oske) . '% (<' . $this->f($oskeMin) . '%) — Testga kiritilmasligi kerak edi, lekin Test bahosi bor';
        }

        return $out;
    }

    /** Python calc_check: HEMIS JN+MT va YN qiymatlarini foiz va vaznlardan qayta hisoblash. */
    private function calcCheck(array $vt, array $w, array $settings): ?array
    {
        $jn = VedomostScanReader::num($vt['D']);
        $mt = VedomostScanReader::num($vt['G']);
        $on = VedomostScanReader::num($vt['J']) ?? 0;
        $oske = VedomostScanReader::num($vt['P']);
        $test = VedomostScanReader::num($vt['S']);
        $yn = VedomostScanReader::num($vt['V']);
        if ($yn === null || $yn < 0 || $jn === null || $mt === null) {
            return null;
        }
        // JN yoki MT o'tish chegarasidan past bo'lsa JN+MT = 0 (vedomost shablonidagi qoida) —
        // aks holda o'tmagan har bir talaba soxta «hisob xatosi» bo'lib chiqadi.
        $jnmtFail = $this->jnMtFailed($vt, $settings);
        $jnmt = $jnmtFail ? 0 : $this->floorRound($jn * $w['jb'] / 100 + $mt * $w['mt'] / 100 + $on * $w['on'] / 100);
        $fail = $jnmtFail
            || ($w['oski'] > 0 && $oske !== null && $oske < (float) ($settings['oski_min'] ?? 60))
            || ($w['test'] > 0 && $test !== null && $test < (float) ($settings['test_min'] ?? 60));
        $calc = $fail ? 0 : $this->floorRound($jnmt
            + ($w['oski'] > 0 ? ($oske ?? 0) * $w['oski'] / 100 : 0)
            + ($w['test'] > 0 ? ($test ?? 0) * $w['test'] / 100 : 0));
        $jh = VedomostScanReader::num($vt['M']);

        return [
            'jnmtCalc' => $jnmt, 'ynCalc' => $calc, 'jnmtHemis' => $jh, 'ynHemis' => $yn,
            'jnmtBad' => $jh !== null && (float) $jh !== (float) $jnmt, 'ynBad' => (float) $calc !== (float) $yn,
        ];
    }

    private function jnMtFailed(array $vt, array $settings): bool
    {
        $pass = (float) ($settings['jn_mt_pass'] ?? 60);
        $jn = VedomostScanReader::num($vt['D']);
        $mt = VedomostScanReader::num($vt['G']);

        return ($jn !== null && $jn < $pass) || ($mt !== null && $mt < $pass);
    }

    /** 6-kurs: PDFdagi ballar rasmiy shablon formulasiga mosmi (Python kurs6). */
    private function kurs6Checks(array $s, array $w): array
    {
        $n = fn ($k) => VedomostScanReader::num($s[$k] ?? null);
        $jn = $n('jb_pct');
        $mt = $n('mt_pct');
        if ($jn === null || $mt === null) {
            return [];
        }
        $out = [];
        $jbE = $jn >= 60 ? $jn * $w['jb'] / 100 : 0;
        $mbE = $mt >= 60 ? $mt * $w['mt'] / 100 : 0;
        $jnmtE = ($jn < 60 || $mt < 60) ? 0 : $this->floorRound($jbE + $mbE);
        foreach ([['Joriy', $jn, 'jb_ball', $jbE], ["Mustaqil ta’lim", $mt, 'mt_ball', $mbE]] as [$lbl, $pct, $bf, $e]) {
            $b = $n($bf);
            if ($b !== null && abs($b - $e) > 0.051) {
                $out[] = "{$lbl} " . $this->f($pct) . '% → ' . $this->f(round($e, 2)) . ' ball bo‘lishi kerak, PDFda ' . $this->f($b);
            }
        }
        $s6 = $n('jbmton_ball');
        if ($s6 !== null && $s6 != $jnmtE) {
            $out[] = 'JN+MT ' . $this->f(round($jbE, 2)) . ' + ' . $this->f(round($mbE, 2)) . " → {$jnmtE} bo‘lishi kerak, PDFda " . $this->f($s6);
        }

        return $out;
    }

    // ───────────────────────── moslashtirish ─────────────────────────

    /**
     * Python match_entries g'oyasi: ism yetakchi, ID yordamchi. AI ID'ni xato o'qisa
     * ham talaba ismi bo'yicha topiladi; ID farqi izohga yoziladi.
     *
     * @return array{0: array<int, array{0: ?array, 1: array}>, 1: array, 2: array}
     */
    private function match(array $system, array $scanRows): array
    {
        $cands = [];
        foreach ($scanRows as $si => $s) {
            $sid = VedomostScanReader::digits($s['id'] ?? '');
            foreach ($system as $ti => $stu) {
                $tid = VedomostScanReader::digits($stu['student_id'] ?? '');
                $sim = VedomostNames::similarity($stu['fish'] ?? '', VedomostNames::stripGroupSuffix($s['fio'] ?? ''));
                $idEq = $sid !== '' && $sid === $tid;
                $dist = ($sid !== '' && $tid !== '') ? levenshtein($sid, $tid) : 99;
                $ok = $sim >= 0.85 || ($idEq && $sim >= 0.5) || ($dist <= 2 && $sim >= 0.6);
                if ($ok) {
                    $cands[] = [$sim + ($idEq ? 0.3 : ($dist <= 2 ? 0.1 : 0)), $si, $ti, $dist, $sim];
                }
            }
        }
        usort($cands, fn ($a, $b) => $b[0] <=> $a[0]);

        $pairs = [];
        $usedScan = [];
        foreach ($cands as [$score, $si, $ti, $dist, $sim]) {
            if (isset($usedScan[$si]) || isset($pairs[$ti])) {
                continue;
            }
            $usedScan[$si] = true;
            $s = $scanRows[$si];
            $stu = $system[$ti];
            $flags = [];
            $raw = VedomostScanReader::digits($s['id'] ?? '');
            $tid = VedomostScanReader::digits($stu['student_id'] ?? '');
            if ($raw !== $tid) {
                $flags[] = $dist <= 2
                    ? ['ocr', "ID: PDFda " . ($s['id'] ?: '—') . " ≠ Mark {$tid} — {$dist} raqam farqi, ism mos. Ehtimol Gemini raqamni noto‘g‘ri o‘qigan; PDFdan ko‘z bilan tekshiring"]
                    : ['bad', "ID: PDFda " . ($s['id'] ?: '—') . " ≠ Mark {$tid} (PDFdan tekshiring)"];
            }
            if ($sim < 1.0 && $sim < 0.85) {
                $flags[] = [in_array('fio', $s['unclear'] ?? [], true) ? 'ocr' : 'bad',
                    'F.I.Sh farq qiladi (' . (int) round($sim * 100) . '%): PDF «' . ($s['fio'] ?? '') . '», Mark «' . ($stu['fish'] ?? '') . '»'];
            }
            $pairs[$ti] = [$s, $flags];
        }

        // Moslanmagan qator ism bo'yicha allaqachon moslangan talabaga to'g'ri kelsa — u PDFda ikki marta.
        $extra = [];
        $dup = [];
        foreach ($scanRows as $si => $s) {
            if (isset($usedScan[$si])) {
                continue;
            }
            $twin = null;
            foreach ($pairs as $ti => $_) {
                if (VedomostNames::similarity($system[$ti]['fish'] ?? '', VedomostNames::stripGroupSuffix($s['fio'] ?? '')) >= 0.9) {
                    $twin = $ti;
                    break;
                }
            }
            if ($twin !== null) {
                $dup[VedomostScanReader::digits($system[$twin]['student_id'] ?? '')] = true;
            } else {
                $extra[] = $s;
            }
        }

        return [$pairs, $extra, $dup];
    }

    // ───────────────────────── tuzatishlar ─────────────────────────

    /**
     * @return array{0: array, 1: array, 2: array, 3: array, 4: array}
     */
    /** Mark qiymatlarini faqat hisobot uchun almashtiradi; asl qiymat va sabab saqlanadi. */
    public function applySystemEdits(array $stu, array $ov): array
    {
        foreach ($ov as $col => $e) {
            $field = self::SYSTEM_EDITABLE[$col] ?? null;
            $val = is_array($e) ? ($e['v'] ?? null) : $e;
            if ($field === null || !is_numeric($val)) {
                continue;
            }
            $stu['sys_orig'][$col] = $stu['raw'][$field] ?? null;
            $stu['raw'][$field] = (float) $val;
            $stu['sys_edited'][$col] = is_array($e) ? (string) ($e['why'] ?? '') : '';
        }

        return $stu;
    }

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
            if (!empty($editedKeys[$key])) {
                // Tuzatilgan qatorda eski ichki hisob muammolari endi amal qilmaydi — qayta baholanadi.
                $r['problems'] = $this->rowProblems($r);
                $r['verified'] = false;
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
        $signatures = array_merge(['oqituvchi' => false, 'dekan' => false, 'kafedra_mudiri' => false, 'muhr' => false], $scan['signatures'] ?? []);
        foreach (($edits['signatures'] ?? []) as $k => $val) {
            if (array_key_exists($k, $signatures)) {
                $signatures[$k] = (bool) $val;
            }
        }

        return [$rows, $header, $weights, $signatures, $editedKeys];
    }

    /** Tuzatilgan qator uchun ichki hisob (ratio'siz, yig'indilar bo'yicha). */
    private function rowProblems(array $r): array
    {
        $n = fn ($k) => VedomostScanReader::num($r[$k] ?? null);
        $p = [];
        $jb = $n('jb_ball');
        $mt = $n('mt_ball');
        $jm = $n('jbmton_ball');
        if ($jb !== null && $mt !== null && $jm !== null && $jm > 0 && abs($jb + $mt + ($n('on_ball') ?? 0) - $jm) > 0.51) {
            $p[] = "JB+MT+ON ball yig‘indisi mos emas";
        }

        return $p;
    }

    private function pdfValues(array $s): array
    {
        $out = [];
        foreach (self::PDF_FIELDS as $f) {
            $out[$f] = trim((string) ($s[$f] ?? ''));
        }

        return $out;
    }

    // ───────────────────────── sarlavha (Python header_checks) ─────────────────────────

    private function headerChecks(array $exp, array $system, array $h, array $context, array $signatures): array
    {
        $out = [];
        $add = function (string $key, string $k, string $v, string $st) use (&$out) {
            $out[] = ['key' => $key, 'k' => $k, 'v' => $v, 'st' => $st];
        };
        $uz = fn ($x) => VedomostNames::uz(is_array($x) ? implode(', ', $x) : (string) ($x ?? ''));
        $hv = fn (string $col) => collect($system)->pluck('vt.' . $col)->filter(fn ($x) => $x !== null && $x !== '')->map(fn ($x) => VedomostNames::uz((string) $x))->unique()->values()->all();

        // Fan nomi — qat'iy: faqat bo'shliq, apostrof turi va oxirgi nuqta kechiriladi.
        $pf = $uz($h['fan'] ?? '');
        $hf = $hv('AB') ?: [$uz($exp['fan'] ?? '')];
        if ($pf !== '') {
            $nf = fn ($x) => preg_replace('/[.\s]+$/u', '', preg_replace('/\s*([,\-–—])\s*/u', '$1', VedomostNames::uz($x)));
            $st = collect($hf)->contains(fn ($b) => $nf($pf) === $nf($b)) ? 'ok'
                : (collect($hf)->contains(fn ($b) => mb_strtolower($nf($pf)) === mb_strtolower($nf($b))) ? 'warn' : 'bad');
            $add('fan', 'Fan nomi (PDF ↔ Mark)', "PDF: {$pf} · Mark: " . implode(' / ', $hf)
                . ($st === 'bad' ? ' — nomi Markdagidan farq qiladi' : ($st === 'warn' ? ' — faqat katta/kichik harf farqi' : '')), $st);
        } else {
            $add('fan', 'Fan nomi (PDF ↔ Mark)', 'PDF: ko‘rsatilmagan · Mark: ' . implode(' / ', $hf), 'bad');
        }

        // Soat / kredit
        $ps = VedomostScanReader::num($h['soat'] ?? null);
        $pk = VedomostScanReader::num($h['kredit'] ?? null);
        $hs = array_map(fn ($x) => VedomostScanReader::num($x), $hv('AH'));
        $hk = array_map(fn ($x) => VedomostScanReader::num($x), $hv('AI'));
        $ok = ($ps === null || in_array($ps, $hs)) && ($pk === null || in_array($pk, $hk));
        $add('soat', 'Soat / kredit', 'PDF: ' . ($ps === null ? '—' : $this->f($ps)) . ' / ' . ($pk === null ? '—' : $this->f($pk))
            . ' · Mark: ' . implode('/', array_map(fn ($x) => $this->f($x), $hs)) . ' / ' . implode('/', array_map(fn ($x) => $this->f($x), $hk)),
            ($ps === null && $pk === null) ? 'warn' : ($ok ? 'ok' : 'bad'));

        // Ishchi o'quv reja («O'quv reja to'g'riligi») — fan nomi, soat, kredit. Faqat ma'lumot uchun.
        if (isset($context['plan'])) {
            $plan = $context['plan'];
            if (empty($plan['found'])) {
                $add('reja', "O'quv reja (ishchi)", $plan['reason'] ?? 'topilmadi', 'warn');
            } else {
                $src = " · reja: {$plan['plan']}, {$plan['semester']}-semestr";
                $nfk = fn ($x) => app(\App\Services\CurriculumComparisonService::class)->normalize((string) $x);
                $rf = $plan['subject'];
                $pdfOk = $pf === '' || $nfk($pf) === $nfk($rf);
                $markOk = collect($hf)->contains(fn ($b) => $nfk($b) === $nfk($rf));
                $add('reja_fan', "Fan nomi (ishchi o'quv reja)", "Reja: {$rf} · PDF: " . ($pf ?: '—') . ' · Mark: ' . implode(' / ', $hf)
                    . (!$pdfOk ? ' — PDF rejadan farq qiladi' : (!$markOk ? ' — Mark rejadan farq qiladi' : '')) . $src,
                    !$pdfOk ? 'bad' : (!$markOk ? 'warn' : 'ok'));

                $rs = $plan['hours'];
                $rk = $plan['credit'];
                $same = fn ($a, $b) => $a === null || $b === null || abs((float) $a - (float) $b) < 0.011;
                $pdfOk = $same($ps, $rs) && $same($pk, $rk);
                $markOk = ($rs === null || empty($hs) || collect($hs)->contains(fn ($x) => $same($x, $rs)))
                    && ($rk === null || empty($hk) || collect($hk)->contains(fn ($x) => $same($x, $rk)));
                $fmt = fn ($x) => $x === null ? '—' : $this->f($x);
                $add('reja_soat', "Soat / kredit (ishchi o'quv reja)", 'Reja: ' . $fmt($rs) . ' / ' . $fmt($rk)
                    . ' · PDF: ' . $fmt($ps) . ' / ' . $fmt($pk)
                    . ' · Mark: ' . (implode('/', array_map($fmt, $hs)) ?: '—') . ' / ' . (implode('/', array_map($fmt, $hk)) ?: '—')
                    . (!$pdfOk ? ' — PDF rejadan farq qiladi' : (!$markOk ? ' — Mark rejadan farq qiladi' : '')) . $src,
                    !$pdfOk ? 'bad' : (!$markOk ? 'warn' : 'ok'));
            }
        }

        // Ma'ruzachi
        $pm = $uz($h['maruzachi'] ?? '');
        $hm = $hv('AJ');
        $add('maruzachi', "Ma'ruzachi", 'PDF: ' . ($pm ?: "ko'rsatilmagan") . ' · Mark: ' . (implode(' / ', $hm) ?: '—'),
            $pm === '' ? 'bad' : (collect($hm)->contains(fn ($b) => VedomostNames::sameName($pm, $b)) ? 'ok'
                : (collect($hm)->contains(fn ($b) => VedomostNames::sameSurname($pm, $b)) ? 'warn' : 'bad')));

        // Amaliyot o'qituvchilari
        $pa = array_values(array_filter(array_map(fn ($x) => VedomostNames::uz((string) $x), is_array($h['amaliyot'] ?? null) ? $h['amaliyot'] : explode(',', (string) ($h['amaliyot'] ?? '')))));
        $ha = $hv('X');
        $missP = array_values(array_filter($ha, fn ($b) => !collect($pa)->contains(fn ($a) => VedomostNames::sameName($a, $b))));
        $extraP = array_values(array_filter($pa, fn ($a) => !collect($ha)->contains(fn ($b) => VedomostNames::sameName($a, $b))));
        $soft = !empty($missP) && count($missP) === count($extraP)
            && collect($missP)->every(fn ($b) => collect($extraP)->contains(fn ($a) => VedomostNames::sameSurname($a, $b)));
        // Qayta topshirish shakllarida (12a/12b, qo'shimcha) imtihonni boshqa o'qituvchilar oladi va
        // bu qator ko'pincha qo'lda yoziladi yoki bo'sh qoladi — XATO emas, «Tekshiring».
        $retakeForm = ($exp['form_type'] ?? '12') !== '12';
        if ($retakeForm && (empty($pa) || $missP || $extraP)) {
            $soft = true;
        }
        $add('amaliyot', "Amaliyot o'qituvchilari", 'PDF: ' . (implode(', ', $pa) ?: "ko'rsatilmagan") . ' · Mark: ' . implode(', ', $ha)
            . ($missP ? " · PDFda yo'q: " . implode(', ', $missP) : '') . ($extraP ? " · Markda yo'q: " . implode(', ', $extraP) : ''),
            empty($pa) ? ($retakeForm ? 'warn' : 'bad') : (($missP || $extraP) ? ($soft ? 'warn' : 'bad') : 'ok'));

        // Shakl (o'ng yuqori burchak): 12a o'rniga 12-shakl yuklanib qolishining oldini oladi.
        if (!empty($exp['shakl'])) {
            $ps = $uz($h['shakl'] ?? '');
            $want = VedomostNames::formKey($exp['shakl']);
            $add('shakl', 'Vedomost shakli', 'PDF: ' . ($ps ?: "o'qilmadi") . ' · tizimda: ' . $exp['shakl'],
                $ps === '' ? 'warn' : (VedomostNames::formKey($ps) === $want ? 'ok' : 'bad'));
        }

        // Fakultet dekani
        $pd = $uz($h['dekan'] ?? '');
        $dk = $uz($exp['dekan'] ?? '');
        $add('dekan', 'Fakultet dekani', 'PDF: ' . ($pd ?: "ko'rsatilmagan") . ' · Mark: ' . ($dk ?: '—'),
            $pd === '' ? 'bad' : ($dk === '' ? 'warn' : (VedomostNames::sameName($pd, $dk) ? 'ok' : 'bad')));

        // Kafedra mudiri
        $km = $uz($h['kafedra_mudiri'] ?? '');
        $kb = $uz($context['kafedra_mudiri'] ?? '');
        $add('kafedra_mudiri', 'Kafedra mudiri', ($km ?: "ko'rsatilmagan") . ($kb ? ' · Mark: ' . $kb : ''),
            $km === '' ? 'bad' : ($kb === '' ? 'warn' : (VedomostNames::sameName($km, $kb) ? 'ok' : 'bad')));

        // Guruh / sana
        $pg = $uz($h['guruh'] ?? '');
        $hg = $hv('AG');
        $ys = $uz($h['yn_sana'] ?? '');
        $sys = $uz($exp['yn_sanasi'] ?? '');
        $dk2 = fn ($x) => preg_match('/(\d{1,2})[.\/-](\d{1,2})[.\/-](\d{4})/', $x, $m) ? sprintf('%04d%02d%02d', $m[3], $m[2], $m[1]) : '';
        $dateOk = $sys === '' || $ys === '' || collect(explode(',', $sys))->contains(fn ($d) => $dk2($d) !== '' && $dk2($d) === $dk2($ys));
        $add('guruh', 'Guruh / sana', 'PDF: ' . ($pg ?: '—') . ' · YaN sanasi: ' . ($ys ?: '—') . ' · Mark: ' . implode(', ', $hg)
            . ($sys !== '' ? ' (YN sanasi tizimda: ' . $sys . ')' : ''), $dateOk ? 'ok' : 'warn');

        // Imzolar va muhr (Python'da yo'q — mark talabi)
        $sigNames = ['oqituvchi' => "O'qituvchi imzosi", 'dekan' => 'Dekan imzosi', 'kafedra_mudiri' => 'Kafedra mudiri imzosi', 'muhr' => "Muhr (M.O')"];
        foreach ($sigNames as $k => $label) {
            $add('sig_' . $k, $label, !empty($signatures[$k]) ? 'bor' : "skanerda topilmadi", !empty($signatures[$k]) ? 'ok' : 'bad');
        }

        return $out;
    }

    /** Yopilish shakli: HEMISdagi shakl ↔ vedomost sarlavhasidagi imtihon vaznlari (OSKE / Test). */
    private function closingCheck(string $closing, array $scanW, bool $kurs6): array
    {
        $names = ['oski' => 'OSKE', 'test' => 'Test', 'oski_test' => 'OSKE + Test', 'sinov' => 'Sinov', 'normativ' => 'Normativ'];
        $row = fn (string $v, string $st) => ['key' => 'yopilish', 'k' => 'Yopilish shakli', 'v' => $v, 'st' => $st];
        $hemis = $names[$closing] ?? ($closing !== '' ? $closing : null);
        if ($hemis === null) {
            return $row("Markda yopilish shakli kiritilmagan — vaznlar faqat vedomostdan olindi", 'warn');
        }

        $read = [];
        foreach (['jb', 'mt', 'on', 'oski', 'test'] as $k) {
            $read[$k] = (int) round((float) ($scanW[$k] ?? 0));
        }
        if (array_sum($read) !== 100) {
            return $row("Mark: {$hemis} · vedomost vaznlari aniq o'qilmadi — Sarlavha varag'ida tuzating", 'warn');
        }

        $pdf = ($read['oski'] > 0 && $read['test'] > 0) ? 'OSKE + Test' : ($read['oski'] > 0 ? 'OSKE' : ($read['test'] > 0 ? 'Test' : "imtihonsiz (JN/MT)"));
        $needO = in_array($closing, ['oski', 'oski_test'], true);
        $needT = in_array($closing, ['test', 'oski_test', 'sinov', 'normativ'], true);
        $ok = ($read['oski'] > 0) === $needO && ($read['test'] > 0) === $needT;
        // 6-kurs vedomostlari rasmiy shablon bo'yicha — imtihon ustunlari bo'lmasligi mumkin.
        $st = $ok ? 'ok' : ($kurs6 ? 'warn' : 'bad');
        $v = "Mark: {$hemis} · vedomostda: {$pdf} (JN {$read['jb']} / MT {$read['mt']}"
            . ($read['on'] ? " / ON {$read['on']}" : '') . ($read['oski'] ? " / OSKE {$read['oski']}" : '') . ($read['test'] ? " / Test {$read['test']}" : '') . ')';

        return $row($v . ($ok ? '' : ' — mos emas'), $st);
    }

    private function weightIssues(array $w, string $closing, array $scanW): array
    {
        $out = [];
        $read = array_sum(array_map(fn ($k) => (int) round((float) ($scanW[$k] ?? 0)), ['jb', 'mt', 'on', 'oski', 'test']));
        if ($read !== 100) {
            $out[] = ['t' => 'ocr', 'm' => 'Vaznlar PDF sarlavhasidan aniq o‘qilmadi — yopilish shakli bo‘yicha olindi: '
                . "JN {$w['jb']} / MT {$w['mt']} / ON {$w['on']} / OSKE {$w['oski']} / Test {$w['test']} (kerak bo‘lsa Sarlavha varag‘ida tuzating)"];
        }
        $needO = in_array($closing, ['oski', 'oski_test'], true);
        $needT = in_array($closing, ['test', 'oski_test', 'sinov'], true);
        if ($needO && $w['oski'] === 0) {
            $out[] = ['t' => 'bad', 'm' => 'Fan OSKE bilan yopiladi, lekin vedomostda OSKE vazni 0'];
        }
        if ($needT && $w['test'] === 0) {
            $out[] = ['t' => 'bad', 'm' => 'Fan Test bilan yopiladi, lekin vedomostda Test vazni 0'];
        }

        return $out;
    }

    private function summary(array $c, int $hdrBad, int $sysN, int $scanN): string
    {
        $parts = [];
        foreach ([['bad', 'talabada XATO'], ['nopdf', "talaba PDFda yo'q"], ['missing', "talaba kontingentda yo'q"],
            ['extra', 'ortiqcha talaba PDFda'], ['ocr', "qator PDFdan tekshirilsin"]] as [$k, $txt]) {
            if ($c[$k]) {
                $parts[] = "{$c[$k]} {$txt}";
            }
        }
        if ($hdrBad) {
            $parts[] = "{$hdrBad} ta sarlavha/imzo xatosi";
        }

        return ucfirst(implode(', ', $parts)) . ". Tizimda {$sysN}, skanerda {$scanN} talaba.";
    }

    private function near($a, $b, float $t): bool
    {
        return $a !== null && $b !== null && abs((float) $a - (float) $b) <= $t + 1e-9;
    }

    private function floorRound(float $x): int
    {
        return (int) floor($x + 0.5 + 1e-9);
    }

    public function f($x): string
    {
        if ($x === null || $x === '') {
            return '—';
        }
        if (!is_numeric($x)) {
            return (string) $x;
        }
        $n = (float) $x;

        return $n == (int) $n ? (string) (int) $n : rtrim(rtrim(number_format($n, 2, '.', ''), '0'), '.');
    }

    public static function fieldLabels(): array
    {
        return [
            'fio' => 'F.I.Sh', 'id' => 'ID raqami',
            'jb_pct' => 'JB %', 'jb_ball' => 'JB ball', 'mt_pct' => 'MT %', 'mt_ball' => 'MT ball',
            'on_pct' => 'ON %', 'on_ball' => 'ON ball', 'jbmton_ball' => 'JB+MT ball', 'jbmton_pct' => 'JB+MT %',
            'oski_pct' => 'OSKE %', 'oski_ball' => 'OSKE ball', 'test_pct' => 'TEST %', 'test_ball' => 'TEST ball',
            'yn' => 'YN', 'ects' => 'ECTS', 'baho' => 'Baho',
        ];
    }
}
