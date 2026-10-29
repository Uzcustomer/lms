<?php

use App\Services\VedomostCheckEngine;
use App\Services\VedomostNames;
use App\Services\VedomostScanReader;
use App\Services\VedomostTekshirishService;

/**
 * Vedomost tekshiruv dvigateli — "tizim" qiymatlari "Vedomost tekshirish" hisobidan,
 * qoidalar Python "vedomost_avto" dasturidan. Misollar — Xirurgik kasalliklar,
 * d1/d22-01 (rus), 5-kurs (OSKE 15 + Test 15) vedomostidan.
 */

function engine(): VedomostCheckEngine
{
    return new VedomostCheckEngine(new VedomostTekshirishService());
}

function rules(array $extra = []): array
{
    return array_merge(['jn_mt_pass' => 60, 'tolerance' => 0.05, 'oski_min' => 60, 'test_min' => 60], $extra);
}

function vtStu(string $fio, string $id, $jn, $mt, $oski, $test, array $extra = []): array
{
    return array_merge([
        'fish' => $fio, 'student_id' => $id, 'hemis_id' => 'h' . $id, 'status_code' => '11', 'status_name' => '', 'group' => 'd1/d22-01a (rus)',
        'raw' => ['jn' => $jn, 'mt' => $mt, 'on' => 0.0, 'oski' => (float) $oski, 'test' => (float) $test, 'dav' => 0.0, 'jnCount' => 8],
        'meta' => ['group_hemis_id' => '1139', 'group_name' => 'd1/d22-01a (rus)', 'practice_teacher' => 'IBRAGIMOV A. V.',
            'lecture_teacher' => 'MADIYEV R. Z.', 'subject_id' => '433', 'subject_name' => 'Xirurgik kasalliklar', 'shakl' => '12-shakl',
            'education_year' => '2026-2027', 'semester' => '9-semestr', 'hours' => 90, 'credit' => 3, 'divisor' => 8],
        'hemis_exam' => [],
    ], $extra);
}

function expectedData(array $students, array $extra = []): array
{
    return array_merge(['fan' => 'Xirurgik kasalliklar', 'yopilish_shakli' => 'oski_test', 'level_code' => '15',
        'dekan' => 'BERDIYEV ABDISAMAT XUDAYKULOVICH', 'yn_sanasi' => '23.10.2026', 'talabalar' => $students], $extra);
}

function pdfRow(int $nr, string $fio, string $id, array $v, array $unclear = []): array
{
    return array_merge([
        'nr' => $nr, 'fio' => $fio, 'id' => $id, 'jb_pct' => '', 'jb_ball' => '', 'mt_pct' => '', 'mt_ball' => '', 'on_pct' => '', 'on_ball' => '',
        'jbmton_ball' => '', 'jbmton_pct' => '', 'oski_pct' => '', 'oski_ball' => '', 'test_pct' => '', 'test_ball' => '', 'yn' => '',
        'ects' => '', 'baho' => '', 'unclear' => $unclear, 'trace' => [], 'page' => 1,
    ], $v);
}

/** PDF qatori: BOBOSHEROV — 83/41,5 | 88/17,6 | 59 | 100/15 | 96/14,4 | 88 B+ */
function bobo(array $over = []): array
{
    return array_merge(['jb_pct' => '83,0', 'jb_ball' => '41,5', 'mt_pct' => '88,0', 'mt_ball' => '17,6', 'jbmton_ball' => '59,0', 'jbmton_pct' => '84',
        'oski_pct' => '100,0', 'oski_ball' => '15,0', 'test_pct' => '96,0', 'test_ball' => '14,4', 'yn' => '88', 'ects' => 'B+', 'baho' => 'yaxshi'], $over);
}

function scanDoc(array $rows, array $header = [], array $meta = []): array
{
    return [
        'header' => array_merge(['fan' => 'Xirurgik kasalliklar', 'guruh' => 'd1/d22-01 (rus)', 'soat' => '90', 'kredit' => '3', 'maruzachi' => 'R.Z. Madiyev',
            'amaliyot' => ['Ibragimov A.V'], 'yn_sana' => '23.10.2026', 'dekan' => 'A.X. Berdiyev', 'kafedra_mudiri' => 'Madiyev.R.Z'], $header),
        'weights' => ['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15],
        'signatures' => ['oqituvchi' => true, 'dekan' => true, 'kafedra_mudiri' => true, 'muhr' => true],
        'rows' => $rows,
        'meta' => array_merge(['mode' => 'images'], $meta),
    ];
}

function ctx(array $extra = []): array
{
    return array_merge(['kafedra_mudiri' => 'MADIYEV RUSTAM ZAIROVICH', 'test_center' => [], 'kontingent' => []], $extra);
}

test('5-kurs: tizim qiymatlari "Vedomost tekshirish" bilan bir xil (41,5 / 17,6 / 59 / 88) — farq yo\'q', function () {
    $r = engine()->build(expectedData([vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)]),
        scanDoc([pdfRow(1, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())]), [], rules(), ctx());

    $row = $r['rows'][0];
    expect($row['vt']['E'])->toBe(41.5)->and($row['vt']['H'])->toBe(17.6)->and($row['vt']['M'])->toBe(59)->and($row['vt']['V'])->toBe(88)
        ->and($row['status'])->toBe('ok')
        ->and($r['verdict'])->toBe('ok');
});

test('1 kasrga yaxlitlangan ball (14,9 vs 14.85) farq emas — faqat 5 ustun solishtiriladi', function () {
    $r = engine()->build(expectedData([vtStu('KARIMOV ABDURASUL OTAMURODOVICH', '368221100223', 88, 90, 100, 99)]),
        scanDoc([pdfRow(1, 'KARIMOV ABDURASUL OTAMURODOVICH', '368221100223', ['jb_pct' => '88,0', 'jb_ball' => '44,0', 'mt_pct' => '90,0', 'mt_ball' => '18,0',
            'jbmton_ball' => '62,0', 'oski_pct' => '100,0', 'oski_ball' => '15,0', 'test_pct' => '99,0', 'test_ball' => '14,9', 'yn' => '92'])]),
        [], rules(), ctx());

    expect($r['rows'][0]['status'])->toBe('ok');
});

test('AI ID ni xato o\'qisa talabalar almashib ketmaydi — ism bo\'yicha moslanadi', function () {
    $sys = [
        vtStu('ABDULLAYEV SHAXBOZBEK ODILJON O‘G‘LI', '368221100925', 76, 86, 90, 75),
        vtStu('XAMRAYEVA CHAROS XALILOVNA', '368221100025', 91, 90, 100, 97),
    ];
    $r = engine()->build(expectedData($sys), scanDoc([
        // AI 7-qator ID'sini …925 o'rniga …025 (XAMRAYEVA'niki) deb o'qigan
        pdfRow(7, "ABDULLAYEV SHAXBOZBEK ODILJON O'G'LI", '368221100025', ['jb_pct' => '76,0', 'mt_ball' => '17,2', 'oski_pct' => '90,0', 'test_pct' => '75,0', 'yn' => '80']),
        pdfRow(24, 'XAMRAYEVA CHAROS XALILOVNA', '368221100025', ['jb_pct' => '91,0', 'mt_ball' => '18,0', 'oski_pct' => '100,0', 'test_pct' => '97,0', 'yn' => '94']),
    ]), [], rules(), ctx());

    $rows = collect($r['rows'])->keyBy('id');
    expect($rows['368221100925']['scan_fio'])->toBe("ABDULLAYEV SHAXBOZBEK ODILJON O'G'LI")
        ->and($rows['368221100925']['status'])->toBe('ocr')
        ->and(collect($rows['368221100925']['flags'])->pluck('m')->implode(' '))->toContain('1 raqam farqi')
        ->and($rows['368221100025']['scan_fio'])->toBe('XAMRAYEVA CHAROS XALILOVNA')
        ->and($rows['368221100025']['status'])->toBe('ok')
        ->and($r['extra'])->toBe([]);
});

test('PDFdagi haqiqiy farq — XATO (Python xabari bilan)', function () {
    // ABDURAHIMOV: PDFda OSKE 90, tizimda 0
    $r = engine()->build(expectedData([vtStu('ABDURAHIMOV AZIZBEK ADHAMJON O‘G‘LI', '368221100036', 74, 100, 0, 78)]),
        scanDoc([pdfRow(15, "ABDURAHIMOV AZIZBEK ADHAMJON O'G'LI", '368221100036', ['jb_pct' => '74,0', 'mt_ball' => '20,0', 'oski_pct' => '90,0', 'test_pct' => '78,0', 'yn' => '82'])]),
        [], rules(), ctx());

    $msgs = collect($r['rows'][0]['flags'])->pluck('m')->implode(' | ');
    expect($r['rows'][0]['status'])->toBe('bad')
        ->and($msgs)->toContain('OSKE%: PDF 90,0 ≠ HEMIS 0')
        ->and($msgs)->toContain('YN: PDF 82 ≠ HEMIS -1');
});

test('chetlashtirilgan talaba — KONTINGENTDA YO\'Q; tushib qolgan qator — PDFdan tekshiring', function () {
    $r = engine()->build(expectedData([
        vtStu('NE’MATOV NOZIMJON RUSTAMJON O‘G‘LI', '368221100048', 0, 0, 0, 0, ['status_code' => '60']),
        vtStu('QURBONOV SHAHZODXON SHUHRAT O‘G‘LI', '368221100022', 78, 86, 100, 83),
        vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96),
    ]), scanDoc([pdfRow(5, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())], [], ['missing_nrs' => [8]]), [], rules(), ctx());

    $rows = collect($r['rows'])->keyBy('id');
    expect($rows['368221100048']['status'])->toBe('missing')
        ->and($rows['368221100048']['kont_fio'])->toBeNull()
        ->and($rows['368221100022']['status'])->toBe('ocr')
        ->and(collect($rows['368221100022']['flags'])->pluck('m')->implode(' '))->toContain('8-qatorni');
});

test('boshqa fan PDFi — noto\'g\'ri hujjat', function () {
    $r = engine()->build(expectedData([vtStu('A A', '368221100001', 83, 88, 100, 96)], ['fan' => 'Neyroxirurgiya']),
        scanDoc([pdfRow(1, 'A A', '368221100001', bobo())], ['fan' => 'Epidemiologiya. HE.']), [], rules(), ctx());

    expect($r['verdict'])->toBe('wrong_document')->and($r['summary'])->toContain('Neyroxirurgiya');
});

test('xodim tuzatishi hisobotni qayta quradi', function () {
    $args = [expectedData([vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)]),
        scanDoc([pdfRow(5, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo(['test_pct' => '95,0']))])];

    $before = engine()->build(...[...$args, [], rules(), ctx()]);
    $after = engine()->build(...[...$args, ['rows' => ['r0' => ['test_pct' => '96,0']]], rules(), ctx()]);

    expect($before['rows'][0]['status'])->toBe('bad')
        ->and($after['rows'][0]['status'])->toBe('ok')
        ->and($after['rows'][0]['edited'])->toBe(['test_pct'])
        ->and($after['rows'][0]['orig']['test_pct'])->toBe('95,0');
});

test('qoida va hisob tekshiruvlari (Python entry_check / calc_check)', function () {
    // JN 52 — JN+MT 0, YN -2; PDFda OSKE/Test bahosi yo'q — qoida buzilmagan, calc_check: JN+MT HEMIS 0 ≠ hisob
    $r = engine()->build(expectedData([vtStu('FARXODOVA MUNISA BAXRIDDIN QIZI', '368221100222', 52, 0, 0, 0)]),
        scanDoc([pdfRow(17, 'FARXODOVA MUNISA BAXRIDDIN QIZI', '368221100222', ['jb_pct' => '52,0', 'mt_ball' => '0,0', 'oski_pct' => '', 'test_pct' => '0,0', 'yn' => '-2'])]),
        [], rules(), ctx());

    $msgs = collect($r['rows'][0]['flags'])->pluck('m')->implode(' | ');
    expect($msgs)->toContain('YN: PDF -2 ≠ HEMIS 0')
        ->and($msgs)->toContain('JN+MT hisobi: HEMIS 0 ≠ hisob 26');
});

test('Test markazi natijasi HEMISdan farq qilsa', function () {
    $r = engine()->build(expectedData([vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)]),
        scanDoc([pdfRow(5, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())]), [], rules(),
        ctx(['test_center' => ['368221100018' => ['test' => ['grade' => 90.0, 'dalolat' => false], 'oski' => ['grade' => 100.0, 'dalolat' => false]]]]));

    expect($r['rows'][0]['tm']['test']['status'])->toBe('bad')
        ->and($r['rows'][0]['tm']['oski']['status'])->toBe('ok')
        ->and($r['rows'][0]['status'])->toBe('bad');
});

test('sarlavha: bosh harf xato o\'qilsa "Tekshiring", imzo yo\'q bo\'lsa XATO', function () {
    $scan = scanDoc([pdfRow(1, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())], ['amaliyot' => ['Barakaya I.I']]);
    $scan['signatures']['muhr'] = false;
    $stu = vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96);
    $stu['meta']['practice_teacher'] = 'BARSKAYA L. I.';
    $r = engine()->build(expectedData([$stu]), $scan, [], rules(), ctx());

    $h = collect($r['header'])->keyBy('key');
    expect($h['amaliyot']['st'])->toBe('warn')
        ->and($h['sig_muhr']['st'])->toBe('bad')
        ->and($h['maruzachi']['st'])->toBe('ok')
        ->and($h['dekan']['st'])->toBe('ok')
        ->and($r['verdict'])->toBe('issues');
});

test('vaznlar o\'qilmasa yopilish shakli bo\'yicha olinadi', function () {
    $scan = scanDoc([pdfRow(5, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())]);
    $scan['weights'] = ['jb' => null, 'mt' => null, 'on' => null, 'oski' => null, 'test' => null];
    $r = engine()->build(expectedData([vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)]), $scan, [], rules(), ctx());

    expect($r['weights'])->toBe(['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15])
        ->and($r['rows'][0]['status'])->toBe('ok');
});

test('skaner qatorining ichki hisobi', function () {
    $reader = app(VedomostScanReader::class);
    $rows = [pdfRow(1, 'A', '1', bobo()), pdfRow(2, 'B', '2', bobo())];
    $ratios = $reader->pageRatios($rows);

    expect($reader->rowProblems($rows[0], $ratios))->toBe([]);
    $bad = pdfRow(3, 'C', '3', bobo(['jb_ball' => '44,0']));
    expect(collect($reader->rowProblems($bad, $ratios))->pluck(0)->implode(' '))->toContain('jb_pct va jb_ball');
});

test('ism va fan nomi solishtirish', function () {
    expect(VedomostNames::sameName('Sh.T. Maxmaraimov', 'MAHMARAIMOV SH. T.'))->toBeTrue()
        ->and(VedomostNames::sameName('MADIYEV RUSTAM ZAIROVICH', 'R.Z. Madiyev'))->toBeTrue()
        ->and(VedomostNames::sameName('BARSKAYA L. I.', 'Barakaya I.I'))->toBeFalse()
        ->and(VedomostNames::sameSurname('BARSKAYA L. I.', 'Barakaya I.I'))->toBeTrue()
        ->and(VedomostNames::similarity('ALIMOVA FERUZA SHOKIROVNA', 'ALIMOVA FEʼRUZA SHOKIROVNA'))->toBeGreaterThan(0.85)
        ->and(VedomostNames::subjectScore('Neyroxirurgiya', 'Epidemiologiya. HE.'))->toBeLessThan(0.6);
});

test('Excel hisobot: test markazi ustunlari bilan ham yaratiladi', function () {
    $r = engine()->build(expectedData([
        vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96),
        vtStu('ABDURAHIMOV JASUR', '368221100001', 80, 90, 0, 70),
        vtStu('TUSHGAN TALABA', '368221100004', 70, 70, 70, 70),
    ]), scanDoc([
        pdfRow(1, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo()),
        pdfRow(2, 'ABDURAHIMOV JASUR', '368221100001', bobo(['oski_pct' => '9?']), ['oski_pct']),
        pdfRow(5, 'BEGONA ODAM', '368221109999', bobo()),
    ], [], ['missing_nrs' => [3]]), [], rules(), ctx());

    $book = app(\App\Services\VedomostAiReportExcel::class)->build($r);
    $ws = $book->getSheetByName('Tekshirish');

    expect($book->getSheetNames())->toBe(['Tekshirish', 'Sarlavha', 'Xatolar', 'pdf (BQ)'])
        ->and($ws->getHighestRow())->toBe(4)
        ->and((string) $ws->getCell('B2')->getValue())->toBe('BOBOSHEROV SHOHZOD XAYRULLAYEVICH');
});

test('yopilish shakli: HEMIS shakli vedomost vaznlariga mos kelmasa XATO', function () {
    $stu = [vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)];
    $row = [pdfRow(1, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo())];

    $ok = collect(engine()->build(expectedData($stu), scanDoc($row), [], rules(), ctx())['header'])->keyBy('key');
    $bad = engine()->build(expectedData($stu, ['yopilish_shakli' => 'test']), scanDoc($row), [], rules(), ctx());
    $h = collect($bad['header'])->keyBy('key');

    expect($ok['yopilish']['st'])->toBe('ok')
        ->and($h['yopilish']['st'])->toBe('bad')
        ->and($h['yopilish']['v'])->toContain('HEMIS: Test')
        ->and($h['yopilish']['v'])->toContain('vedomostda: OSKE + Test')
        ->and(array_column($bad['header'], 'key')[1])->toBe('yopilish')
        ->and($bad['verdict'])->toBe('issues');
});

test('6-kurs: PDFdagi ball rasmiy formula bo\'yicha tekshiriladi', function () {
    $stu = [vtStu('BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', 83, 88, 100, 96)];
    $scan = scanDoc([pdfRow(1, 'BOBOSHEROV SHOHZOD XAYRULLAYEVICH', '368221100018', bobo(['jb_ball' => '40,0']))]);

    $r6 = engine()->build(expectedData($stu, ['level_code' => '16']), $scan, [], rules(), ctx());
    $r5 = engine()->build(expectedData($stu), $scan, [], rules(), ctx());
    $m6 = collect($r6['rows'][0]['flags'])->pluck('m')->implode(' | ');

    expect($r6['kurs6'])->toBeTrue()
        ->and($m6)->toContain('6-kurs: Joriy 83% → 41.5 ball bo‘lishi kerak, PDFda 40')
        ->and($r6['rows'][0]['status'])->toBe('bad')
        ->and(collect($r5['rows'][0]['flags'])->pluck('m')->implode(' | '))->not->toContain('6-kurs');
});
