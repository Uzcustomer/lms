<?php

use App\Services\VedomostCheckEngine;
use App\Services\VedomostGradeCalculator;
use App\Services\VedomostNames;
use App\Services\VedomostScanReader;

/**
 * Vedomost tekshiruv dvigateli (Python "vedomost_avto" qoidalari) — bazasiz,
 * tayyor tizim ma'lumoti + skaner jadvali bilan.
 */

function engine(): VedomostCheckEngine
{
    return new VedomostCheckEngine(new VedomostGradeCalculator());
}

function rules(array $extra = []): array
{
    return array_merge(['jn_mt_pass' => 60, 'jn_mt_pass_international' => 60, 'tolerance' => 0.05], $extra);
}

function expectedData(array $students, array $extra = []): array
{
    return array_merge([
        'fan' => 'Endokrinologiya',
        'guruh' => 'd1/22-03',
        'umumiy_soat' => 120,
        'kredit' => 4,
        'maruzachi' => 'Kobilov Abdumalik Ergashevich',
        'amaliyot_oqituvchilari' => 'Bozorov Ulugbek Mamatqulovich, Qurbonov Mansur Choriyevich',
        'dekan' => 'Berdiyev Akbar Xudoyberdiyevich',
        'yn_sanasi' => '07.02.2026',
        'yopilish_shakli' => 'test',
        'level_code' => '14',
        'kurs' => '4-kurs',
        'semestr' => '8-semestr',
        'talabalar' => $students,
    ], $extra);
}

function stu(int $n, string $fio, string $id, $jn, $mt, $test = null, $oski = null): array
{
    return ['n' => $n, 'fish' => $fio, 'student_id' => $id, 'jn' => $jn, 'mt' => $mt, 'on' => null, 'oski' => $oski, 'test' => $test,
        'hemis_id' => 'h' . $id, 'status_code' => '11', 'status_name' => "O'qimoqda"];
}

function scanRow(int $nr, string $fio, string $id, array $v, array $unclear = []): array
{
    return array_merge([
        'nr' => $nr, 'fio' => $fio, 'id' => $id, 'jb_pct' => '', 'jb_ball' => '', 'mt_pct' => '', 'mt_ball' => '',
        'on_pct' => '', 'on_ball' => '', 'jbmton_ball' => '', 'jbmton_pct' => '', 'oski_pct' => '', 'oski_ball' => '',
        'test_pct' => '', 'test_ball' => '', 'yn' => '', 'ects' => '', 'baho' => '', 'unclear' => $unclear, 'trace' => [], 'page' => 1,
    ], $v);
}

/** Rasmdagi 1-qator: JB 78/39, MT 90/18, JB+MT 57, Test 77/23, YN 80 B yaxshi. */
function goodValues(): array
{
    return ['jb_pct' => '78,0', 'jb_ball' => '39,0', 'mt_pct' => '90,0', 'mt_ball' => '18,0', 'jbmton_ball' => '57,0',
        'jbmton_pct' => '81', 'test_pct' => '77,0', 'test_ball' => '23,0', 'yn' => '80', 'ects' => 'B', 'baho' => 'yaxshi'];
}

function scanDoc(array $rows, array $header = []): array
{
    return [
        'header' => array_merge([
            'fan' => 'Endokrinologiya', 'guruh' => 'd1/22-03', 'soat' => '120', 'kredit' => '4',
            'maruzachi' => 'Kobilov A. E', 'amaliyot' => ['Bozorov U. M', 'Qurbonov M. Ch'], 'yn_sana' => '07.02.2026',
            'dekan' => 'A.X Berdiyev', 'kafedra_mudiri' => 'K.M.Meliqulov', 'kurs' => '4', 'semestr' => '8',
        ], $header),
        'weights' => ['jb' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30],
        'signatures' => ['oqituvchi' => true, 'dekan' => true, 'kafedra_mudiri' => true, 'muhr' => true],
        'rows' => $rows,
        'meta' => ['mode' => 'images'],
    ];
}

function ctx(array $extra = []): array
{
    return array_merge(['kafedra_mudiri' => 'Meliqulov Komil Mamatovich', 'international' => [], 'test_center' => [], 'kontingent' => []], $extra);
}

test('mos vedomost — ok, sarlavha va imzolar to\'g\'ri', function () {
    $r = engine()->build(
        expectedData([stu(1, 'ABDUXOMIDOV ASADBEK DILSHOD O\'G\'LI', '368221100230', 78, 90, 77)]),
        scanDoc([scanRow(1, "ABDUXOMIDOV ASADBEK DILSHOD O'G'LI", '368221100230', goodValues())]),
        [], rules(), ctx()
    );

    expect($r['verdict'])->toBe('ok')
        ->and($r['rows'][0]['status'])->toBe('ok')
        ->and($r['rows'][0]['system']['yn'])->toBe(80)
        ->and(collect($r['header'])->where('status', 'bad')->pluck('key')->all())->toBe([]);
});

test('farqli katak — bad, noaniq katak — ocr', function () {
    $v = goodValues();
    $v['jb_pct'] = '87';
    $r = engine()->build(
        expectedData([
            stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77),
            stu(2, 'VALIYEV VALI', '368221100002', 78, 90, 77),
        ]),
        scanDoc([
            scanRow(1, 'ALIYEV ALI', '368221100001', $v),
            scanRow(2, 'VALIYEV VALI', '368221100002', array_merge(goodValues(), ['test_pct' => '71']), ['test_pct']),
        ]),
        [], rules(), ctx()
    );

    $rows = collect($r['rows'])->keyBy('id');
    expect($rows['368221100001']['status'])->toBe('bad')
        ->and($rows['368221100001']['cells']['jb_pct'])->toBe('bad')
        ->and($rows['368221100002']['status'])->toBe('ocr')
        ->and($rows['368221100002']['cells']['test_pct'])->toBe('ocr')
        ->and($r['verdict'])->toBe('issues');
});

test('PDFda yo\'q va ortiqcha talaba (kontingent bilan)', function () {
    $r = engine()->build(
        expectedData([
            stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77),
            stu(2, 'VALIYEV VALI', '368221100002', 78, 90, 77),
        ]),
        scanDoc([
            scanRow(1, 'ALIYEV ALI', '368221100001', goodValues()),
            scanRow(2, 'BEGIMOV BEK', '368221199999', goodValues()),
        ]),
        [], rules(), ctx(['kontingent' => ['368221199999' => ['fio' => 'BEGIMOV BEK', 'group' => 'd1/22-04']]])
    );

    $byStatus = collect($r['rows'])->keyBy('status');
    expect($byStatus['missing']['id'])->toBe('368221100002')
        ->and($byStatus['extra']['flags'][0]['m'])->toContain('d1/22-04')
        ->and($r['counts']['missing'])->toBe(1)
        ->and($r['counts']['extra'])->toBe(1);
});

test('ID xato o\'qilgan bo\'lsa F.I.Sh bo\'yicha moslashtiriladi', function () {
    $r = engine()->build(
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)]),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100007', goodValues(), ['id'])]),
        [], rules(), ctx()
    );

    expect($r['rows'])->toHaveCount(1)
        ->and($r['rows'][0]['status'])->toBe('ocr')
        ->and($r['rows'][0]['flags'][0]['m'])->toContain('ID mos emas');
});

test('boshqa fan PDFi — noto\'g\'ri hujjat', function () {
    $r = engine()->build(
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)], ['fan' => 'Neyroxirurgiya']),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', goodValues())], ['fan' => 'Epidemiologiya. HE.']),
        [], rules(), ctx()
    );

    expect($r['verdict'])->toBe('wrong_document')
        ->and($r['summary'])->toContain('Neyroxirurgiya')
        ->and($r['summary'])->toContain('Epidemiologiya');
});

test('talabalarning yarmidan kami mos kelsa — noto\'g\'ri hujjat', function () {
    $r = engine()->build(
        expectedData([stu(1, 'A A', '368221100001', 78, 90, 77), stu(2, 'B B', '368221100002', 78, 90, 77), stu(3, 'C C', '368221100003', 78, 90, 77)]),
        scanDoc([scanRow(1, 'XXXXXX YYYYY', '111111111111', goodValues()), scanRow(2, 'ZZZZZZ WWWWW', '222222222222', goodValues())]),
        [], rules(), ctx()
    );

    expect($r['verdict'])->toBe('wrong_document');
});

test('xodim tuzatishi hisobotni qayta quradi va belgilanadi', function () {
    $v = goodValues();
    $v['jb_pct'] = '87';
    $args = [
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)]),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', $v)]),
    ];

    $before = engine()->build(...[...$args, [], rules(), ctx()]);
    $after = engine()->build(...[...$args, ['rows' => ['r0' => ['jb_pct' => '78']]], rules(), ctx()]);

    expect($before['rows'][0]['status'])->toBe('bad')
        ->and($after['rows'][0]['status'])->toBe('ok')
        ->and($after['rows'][0]['edited'])->toBe(['jb_pct'])
        ->and($after['rows'][0]['orig']['jb_pct'])->toBe('87')
        ->and($after['counts']['edited'])->toBe(1)
        ->and($after['verdict'])->toBe('ok');
});

test('qoida: JN o\'tmagan talabada imtihon bahosi bor', function () {
    $r = engine()->build(
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 55, 90, 77)]),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', ['jb_pct' => '55', 'jb_ball' => '0', 'mt_pct' => '90', 'mt_ball' => '18',
            'jbmton_ball' => '0', 'test_pct' => '77', 'test_ball' => '23', 'yn' => '-2', 'ects' => 'FX', 'baho' => "qo'yilmadi"])]),
        [], rules(), ctx()
    );

    expect(collect($r['rows'][0]['flags'])->pluck('m')->implode(' | '))->toContain('kiritilmasligi kerak edi');
});

test('sinov fan: Test % Joriy % ga teng bo\'lishi kerak', function () {
    $v = goodValues();
    $r = engine()->build(
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)], ['yopilish_shakli' => 'sinov']),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', $v)]),
        [], rules(), ctx()
    );

    expect(collect($r['rows'][0]['flags'])->pluck('m')->implode(' | '))->toContain('Sinov fan');
});

test('Test markazi natijasi tizimdan farq qilsa', function () {
    $r = engine()->build(
        expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)]),
        scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', goodValues())]),
        [], rules(), ctx(['test_center' => ['368221100001' => ['test' => ['grade' => 80.0, 'dalolat' => false]]]])
    );

    expect($r['rows'][0]['status'])->toBe('bad')
        ->and($r['rows'][0]['tm']['test']['status'])->toBe('bad');
});

test('imzo yoki muhr yo\'q — hujjat xatosi', function () {
    $scan = scanDoc([scanRow(1, 'ALIYEV ALI', '368221100001', goodValues())]);
    $scan['signatures']['muhr'] = false;
    $r = engine()->build(expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)]), $scan, [], rules(), ctx());

    expect($r['verdict'])->toBe('issues')
        ->and(collect($r['doc_issues'])->pluck('m')->implode(' '))->toContain('muhr');

    $fixed = engine()->build(expectedData([stu(1, 'ALIYEV ALI', '368221100001', 78, 90, 77)]), $scan,
        ['signatures' => ['muhr' => true]], rules(), ctx());
    expect($fixed['verdict'])->toBe('ok');
});

test('6-kurs formulasi', function () {
    $e = engine()->kurs6(78.0, 90.0, null, 77.0, ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30]);
    expect($e['jbmton_ball'])->toBe(57)->and($e['test_ball'])->toBe(23)->and($e['ozlashtirish'])->toBe(80);

    // 30 likda 59% → 18 chiqsa 17
    $e = engine()->kurs6(78.0, 90.0, null, 59.0, ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30]);
    expect($e['test_ball'])->toBe(17)->and($e['ozlashtirish'])->toBe(0);

    // JN+MT < 60% → -2
    $e = engine()->kurs6(50.0, 90.0, null, 77.0, ['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 0, 'test' => 30]);
    expect($e['ozlashtirish'])->toBe(-2);
});

test('skaner qatorining ichki hisobi', function () {
    $reader = app(VedomostScanReader::class);
    $rows = [scanRow(1, 'A', '1', goodValues()), scanRow(2, 'B', '2', goodValues())];
    $ratios = $reader->pageRatios($rows);

    expect($reader->rowProblems($rows[0], $ratios))->toBe([]);

    $bad = scanRow(3, 'C', '3', array_merge(goodValues(), ['jb_ball' => '44,0']));
    expect(collect($reader->rowProblems($bad, $ratios))->pluck(0)->implode(' '))->toContain('jb_pct va jb_ball');
});

test('ism va fan nomi solishtirish', function () {
    expect(VedomostNames::sameName('Sh.T. Maxmaraimov', 'MAHMARAIMOV SH. T.'))->toBeTrue()
        ->and(VedomostNames::sameName('Kobilov Abdumalik Ergashevich', 'Kobilov A. E'))->toBeTrue()
        ->and(VedomostNames::sameName('Kobilov A. E', 'Bozorov U. M'))->toBeFalse()
        ->and(VedomostNames::similarity("ALIMOVA FERUZA SHOKIROVNA", 'ALIMOVA FEʼRUZA SHOKIROVNA'))->toBeGreaterThan(0.85)
        ->and(VedomostNames::subjectScore('Neyroxirurgiya', 'Epidemiologiya. HE.'))->toBeLessThan(0.6)
        ->and(VedomostNames::subjectScore('Endokrinologiya', 'ENDOKRINOLOGIYA'))->toBe(1.0);
});
