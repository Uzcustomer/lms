<?php

use App\Services\SubjectDifference\DifferenceCalculator;
use App\Services\SubjectDifference\GradeScale;
use App\Services\SubjectDifference\PlanBuilder;
use App\Services\SubjectDifference\RuleMatcher;
use App\Services\SubjectDifference\TextTranscriptParser;

/**
 * Fanlar farqi: baho shkalasi, matnli transkript parseri, qoida bo'yicha
 * moslashtirish va GPA. Etalon — registrator qo'lda tekshirgan ikki talaba
 * (tests/Fixtures/subject-difference/*.json).
 */
function sdFixture(string $name): array
{
    return json_decode(file_get_contents(__DIR__ . '/../Fixtures/subject-difference/' . $name . '.json'), true);
}

function sdRuleMap(array $f): array
{
    return array_map(fn ($m) => $m['tr'], (new RuleMatcher())->match($f['plan'], $f['transcript']));
}

// ─────────────── baho shkalasi ───────────────

test('balldan baho: standart shkala 90/70/60', function () {
    $s = new GradeScale();
    expect($s->fromBall(90))->toBe(5)
        ->and($s->fromBall(89.99))->toBe(4)
        ->and($s->fromBall(70))->toBe(4)
        ->and($s->fromBall(69))->toBe(3)
        ->and($s->fromBall(60))->toBe(3)
        ->and($s->fromBall(59))->toBe(2);
});

test("88 (a'lo): shkala bo'yicha 4, so'z bilan 5 — ziddiyat ko'rsatiladi", function () {
    $r = (new GradeScale())->resolve(['ball' => 88, 'grade' => null, 'grade_text' => "a'lo"]);
    expect($r['grade'])->toBe(4)
        ->and($r['source'])->toBe('ball')
        ->and($r['conflicts'])->toHaveCount(1);
});

test("'grade' rejimida transkriptda yozilgan baho olinadi", function () {
    $r = GradeScale::fromArray(['mode' => 'grade'])->resolve(['ball' => 88, 'grade' => 5]);
    expect($r['grade'])->toBe(5)->and($r['conflicts'])->not->toBeEmpty();
});

test("5 ballik tizimdan tashqari 'baho' (masalan 8) ishonchsiz deb tashlanadi", function () {
    $r = GradeScale::fromArray(['mode' => 'grade'])->resolve(['ball' => 68, 'grade' => 8]);
    expect($r['grade'])->toBe(3)->and($r['source'])->toBe('ball');
});

test("so'zli baholar", function () {
    expect(GradeScale::fromWord('72 (yaxshi)'))->toBe(4)
        ->and(GradeScale::fromWord('qoniqarsiz'))->toBe(2)
        ->and(GradeScale::fromWord("A’lo"))->toBe(5)
        ->and(GradeScale::fromWord(''))->toBeNull();
});

// ─────────────── matnli transkript ───────────────

test('pdftotext -layout natijasidan qatorlar, semestrlar va bo\'lingan nomlar', function () {
    $text = <<<'TXT'
        N                     Fan nomi / Name of course                      Yuklama / Kredit    Ball / Baho
                                                                              (Acload/ Credit)   (Score/Grade)
                                          1-semestr
        1   O'zbekistonning eng yangi tarixi / New history of Uzbekistan        60 / 2            68 / 3
        2   Tibbiyotda axborot texnologiyalari / Information technologies       120 / 4           78 / 4
                                          2-semestr
        3   Tibbiy biologiya. Umumiy genetika / Medical biology. General        210 / 7           73 / 4
            genetics
            QVP, QOP, ambulator-poliklinik bo'g'in va tez yordam hamshirasi yordamchisi / Assistant
        4   nurse of rural medical station                                       180 / 6           80 / 4
                                          VIII- semester
        5   Dermatovenerologiya / Dermatovenereology                             120 / 4           0 / 0
        Baholash tizimi / Grading system comparison:     Jami / Total     GPA
        5 = 90.00 - 100.00                                7170 / 238       3.59
        TXT;

    $rows = (new TextTranscriptParser())->parse($text);

    expect($rows)->toHaveCount(5)
        ->and($rows[0])->toMatchArray(['semester' => 1, 'hours' => 60, 'credit' => 2.0, 'ball' => 68.0, 'grade' => 3])
        ->and($rows[2]['semester'])->toBe(2)
        ->and($rows[2]['name'])->toStartWith('Tibbiy biologiya. Umumiy genetika')
        ->and($rows[3]['name'])->toStartWith('QVP, QOP')
        ->and($rows[3]['credit'])->toBe(6.0)
        ->and($rows[4])->toMatchArray(['semester' => 8, 'ball' => 0.0, 'grade' => 0]);
    // "0 / 0" — baholanmagan: baho chiqmaydi
    expect((new GradeScale())->resolve($rows[4])['grade'])->toBeNull();
});

// ─────────────── qoida bo'yicha moslashtirish ───────────────

test('nomlar normallashadi: inglizcha qism, qavs, apostrof, x/h, kirill harflar', function () {
    $m = new RuleMatcher();
    expect($m->normalize('Odam anatomiyasi / Human anatomy'))->toBe($m->normalize('Odam anatomiyasi'))
        ->and($m->normalize('Klinik anatomiya (OXTA)'))->toBe($m->normalize('Klinik anatomiya'))
        ->and($m->normalize('Operativ jarroxlik'))->toBe($m->normalize('Operativ jarrohlik'))
        ->and($m->normalize('Bolalar kasalliklari prоpedevtikasi (a)'))->toBe($m->normalize('Bolalar kasalliklari propedevtikasi'))
        ->and($m->score('Ftiziatriya, bolalar ftiziatriyasi', 'Ftizatriya. Bolalar ftizatriyasi'))->toBeGreaterThanOrEqual(RuleMatcher::THRESHOLD);
});

test("turli fanlar bog'lanmaydi", function () {
    $m = new RuleMatcher();
    expect($m->score('Patologik anatomiya', 'Patologik fiziologiya'))->toBeLessThan(RuleMatcher::THRESHOLD)
        ->and($m->score('Normal fiziologiya', 'Patologik fiziologiya'))->toBeLessThan(RuleMatcher::THRESHOLD)
        ->and($m->score('Ishlab chiqarish amaliyoti', 'Statsionar vrach yordamchisi'))->toBeLessThan(RuleMatcher::THRESHOLD);
});

test('bitta transkript fani bir nechta semestrdagi bizning fanga baho beradi, yaqin semestr tanlanadi', function () {
    $plan = [
        ['key' => 'a', 'semester' => 1, 'name' => 'Tibbiy kimyo'],
        ['key' => 'b', 'semester' => 2, 'name' => 'Tibbiy kimyo'],
        ['key' => 'c', 'semester' => 4, 'name' => 'Biokimyo'],
    ];
    $tr = [
        ['semester' => 2, 'name' => 'Tibbiy kimyo', 'ball' => 70, 'grade' => 4],
        ['semester' => 3, 'name' => 'Biokimyo', 'ball' => 69, 'grade' => 3],
        ['semester' => 4, 'name' => 'Biokimyo', 'ball' => 66, 'grade' => 3],
    ];
    $m = (new RuleMatcher())->match($plan, $tr);
    expect($m['a']['tr'])->toBe(0)->and($m['b']['tr'])->toBe(0)->and($m['c']['tr'])->toBe(2);
});

test('"0 / 0" (baholanmagan) qatordan ko\'ra ko\'chirmadagi bahosi bor qator tanlanadi', function () {
    $plan = [['key' => 'd', 'semester' => 8, 'name' => 'Dermatovenerologiya']];
    $tr = [
        ['semester' => 8, 'name' => 'Dermatovenerologiya', 'ball' => 0, 'grade' => 0],
        ['semester' => 8, 'name' => 'Dermatovenerologiya', 'ball' => 72, 'grade' => null, 'grade_text' => 'yaxshi', 'file' => 1],
    ];
    expect((new RuleMatcher())->match($plan, $tr)['d']['tr'])->toBe(1);
});

test("Talaba A: qoida bo'yicha moslik registrator Exceli bilan mos (amaliyotlar va Excel xatolaridan tashqari)", function () {
    $f = sdFixture('talaba-a');
    $map = sdRuleMap($f);

    $wrong = [];
    foreach ($f['plan'] as $p) {
        $ball = $map[$p['key']] === null ? null : $f['transcript'][$map[$p['key']]]['ball'];
        if ((float) $ball !== (float) ($p['expected_ball'] ?: 0) && !($ball === null && !$p['expected_ball'])) {
            $wrong[] = $p['name'] . ' ' . $p['semester'];
        }
    }

    // Qolganlari: 3 amaliyot va "Operativ jarroxlik" (nomi butunlay boshqa — AI ishi) hamda
    // Excel'dagi Gigiyena 3/4-semestr ballari almashib ketgani (transkriptda 3-sem 73, 4-sem 75).
    expect($wrong)->toEqualCanonicalizing([
        'Gigiyena, harbiy gigiyena. Tibbiy ekologiya. 3',
        'Gigiyena, harbiy gigiyena. Tibbiy ekologiya. 4',
        'Ishlab chiqarish amaliyoti 4',
        'Operativ jarroxlik va topografik anatomiya 6',
        'Ishlab chiqarish amaliyoti 6',
        'Ishlab chiqarish amaliyoti 8',
    ]);
});

test("Talaba B: qoida bo'yicha moslik registrator Exceli bilan mos (amaliyotlar va Excel xatosidan tashqari)", function () {
    $f = sdFixture('talaba-b');
    $map = sdRuleMap($f);
    $scale = new GradeScale();

    $wrong = [];
    foreach ($f['plan'] as $p) {
        $tr = $map[$p['key']];
        $g = $tr === null ? 0 : $scale->resolve($f['transcript'][$tr])['grade'];
        if ($g !== $p['excel_grade']) {
            $wrong[] = $p['name'] . ' ' . $p['semester'];
        }
    }

    // Normal fiziologiya 3-sem: transkriptda 70/4, Excel'da 3 (4-semestr bahosi) — Excel xatosi.
    expect($wrong)->toEqualCanonicalizing([
        'Normal fiziologiya 3',
        'Ishlab chiqarish amaliyoti 4',
        'Operativ jarroxlik va topografik anatomiya 6',
        'Ishlab chiqarish amaliyoti 6',
        'Ishlab chiqarish amaliyoti 8',
    ]);
});

// ─────────────── GPA va qaror ───────────────

test("Talaba A: to'g'ri moslik bilan GPA = 894/245 (Excel'dagi 3.81 — 'Tibbiyot kasbiga kirish' bahosi 8 deb yozilgani uchun)", function () {
    $f = sdFixture('talaba-a');
    // Excel'dagi ball bo'yicha "to'g'ri" moslik
    $map = [];
    foreach ($f['plan'] as $p) {
        $map[$p['key']] = null;
        if ($p['expected_ball']) {
            foreach ($f['transcript'] as $i => $t) {
                if ((float) $t['ball'] === (float) $p['expected_ball'] && (new RuleMatcher())->score($t['name'], $p['name']) > 0.3) {
                    $map[$p['key']] = $i;
                    break;
                }
            }
        }
    }
    // Nomi butunlay boshqa amaliyotlar va "Operativ jarroxlik" ↔ "Klinik anatomiya" (6-sem)
    $byName = fn ($name, $sem) => collect($f['transcript'])->search(fn ($t) => str_starts_with($t['name'], $name) && $t['semester'] === $sem);
    foreach ($f['plan'] as $p) {
        $map[$p['key']] = match ([$p['name'], $p['semester']]) {
            ['Ishlab chiqarish amaliyoti', 4] => $byName('Palata hamshirasi', 4),
            ['Ishlab chiqarish amaliyoti', 6] => $byName('QVP', 6),
            ['Ishlab chiqarish amaliyoti', 8] => $byName('Statsionar', 8),
            ['Operativ jarroxlik va topografik anatomiya', 6] => $byName('Klinik anatomiya', 6),
            default => $map[$p['key']],
        };
    }

    $res = (new DifferenceCalculator())->calculate($f['plan'], $map, $f['transcript'], new GradeScale(), 8);

    foreach ($res['rows'] as $row) {
        $p = collect($f['plan'])->firstWhere('key', $row['key']);
        expect($row['grade'])->toBe($p['expected_grade'], $row['name'] . ' ' . $row['semester']);
    }
    expect($res['total_credit'])->toEqual(245)
        ->and($res['total_product'])->toEqual(894)
        ->and(round($res['gpa'], 2))->toBe(3.65)
        ->and($res['decision']['passed'])->toBeTrue()
        ->and($res['decision']['kurs'])->toBe(5);
});

test("transkriptda yo'q fan — 0, farq; tanlov fan izoh bilan", function () {
    $plan = [
        ['key' => 'a', 'semester' => 1, 'name' => 'Falsafa', 'credit' => 4, 'type' => 'Majburiy'],
        ['key' => 'b', 'semester' => 1, 'name' => 'Jismoniy tarbiya', 'credit' => 2, 'type' => 'Tanlov'],
    ];
    $tr = [['semester' => 1, 'name' => 'Falsafa', 'ball' => 80, 'grade' => 4]];
    $res = (new DifferenceCalculator())->calculate($plan, ['a' => 0, 'b' => null], $tr, new GradeScale(), 2);

    expect($res['gpa'])->toEqual(round(16 / 6, 4))
        ->and($res['farq_count'])->toBe(1)
        ->and($res['rows'][1]['farq'])->toBeTrue()
        ->and(implode(' ', $res['rows'][1]['notes']))->toContain('Tanlov');
});

test("hisobdan chiqarilgan qator GPA'ga kirmaydi", function () {
    $plan = [
        ['key' => 'a', 'semester' => 1, 'name' => 'Falsafa', 'credit' => 4],
        ['key' => 'b', 'semester' => 1, 'name' => 'Ortiqcha', 'credit' => 4],
    ];
    $tr = [['semester' => 1, 'name' => 'Falsafa', 'ball' => 75, 'grade' => 4]];
    $res = (new DifferenceCalculator())->calculate($plan, ['a' => 0, 'b' => null], $tr, new GradeScale(), 2, ['b']);
    expect($res['gpa'])->toEqual(4.0)->and($res['farq_count'])->toBe(0);
});

test('qaror: GPA chegarasi, kurs va keyingi tekshiriladigan semestr', function () {
    $c = new DifferenceCalculator();
    expect($c->decision(8, 2.4, 0))->toMatchArray(['passed' => true, 'kurs' => 5, 'start_semester' => 9])
        ->and($c->decision(8, 2.399, 0))->toMatchArray(['passed' => false, 'kurs' => 5, 'next_upto' => 6])
        ->and($c->decision(2, 1.0, 0))->toMatchArray(['passed' => false, 'kurs' => 2, 'next_upto' => 1])
        // 1-kurs: GPA ahamiyatsiz, 1-semestr fanlari to'liq o'zlashtirilgan bo'lsa 2-semestrdan
        ->and($c->decision(1, 0.5, 0))->toMatchArray(['passed' => true, 'kurs' => 1, 'start_semester' => 2])
        ->and($c->decision(1, 4.0, 1))->toMatchArray(['passed' => false, 'kurs' => 1, 'start_semester' => 1]);
});

// ─────────────── akkreditatsiya → reja ───────────────

test("akkreditatsiya yozuvlaridan reja: fan+semestr bo'yicha, eng ko'p uchragan nom/kredit, tur curriculum_subjects'dan", function () {
    $records = [
        ['subject_id' => 10, 'subject_name' => 'Tibbiy kimyo', 'semester_id' => '11', 'credit' => '4.0', 'total_acload' => 120, 'student_id' => 1],
        ['subject_id' => 10, 'subject_name' => 'Tibbiy kimyo', 'semester_id' => '11', 'credit' => '4.0', 'total_acload' => 120, 'student_id' => 2],
        ['subject_id' => 10, 'subject_name' => 'Tibbiy kimyo ', 'semester_id' => '11', 'credit' => '3', 'total_acload' => 90, 'student_id' => 3],
        ['subject_id' => 10, 'subject_name' => 'Tibbiy kimyo', 'semester_id' => '12', 'credit' => '3', 'total_acload' => 90, 'student_id' => 1],
        ['subject_id' => 20, 'subject_name' => 'Ishlab chiqarish amaliyoti', 'semester_id' => '12', 'credit' => '6', 'total_acload' => 180, 'student_id' => 1],
        ['subject_id' => 30, 'subject_name' => 'Bioetika', 'semester_id' => '12', 'credit' => '2', 'total_acload' => 60, 'student_id' => 2],
    ];
    $types = ['30|12' => ['subject_type_name' => 'Tanlov', 'credit' => 2], '10|11' => ['subject_type_name' => 'Majburiy', 'credit' => 4]];

    $plan = (new PlanBuilder())->aggregate($records, $types);

    expect($plan)->toHaveCount(4)
        ->and($plan[0])->toMatchArray(['semester' => 1, 'name' => 'Tibbiy kimyo', 'credit' => 4.0, 'hours' => 120, 'type' => 'Majburiy', 'students' => 3])
        ->and(array_column($plan, 'name'))->toBe(['Tibbiy kimyo', 'Bioetika', 'Tibbiy kimyo', 'Ishlab chiqarish amaliyoti'])
        ->and($plan[1]['type'])->toBe('Tanlov');
});

test("matnli PDF (pdftotext): Talaba A transkriptining 61 qatori to'liq o'qiladi va qoida bo'yicha moslik qo'lda kiritilgan bilan bir xil", function () {
    if (!(new \Symfony\Component\Process\ExecutableFinder())->find('pdftotext')) {
        $this->markTestSkipped('pdftotext o\'rnatilmagan');
    }
    $f = sdFixture('talaba-a');
    $text = (string) shell_exec('pdftotext -layout ' . escapeshellarg(__DIR__ . '/../Fixtures/subject-difference/talaba-a-text.pdf') . ' -');
    $rows = (new TextTranscriptParser())->parse($text);

    expect($rows)->toHaveCount(61);
    foreach ($f['transcript'] as $i => $e) {
        expect([$rows[$i]['semester'], $rows[$i]['ball'], $rows[$i]['grade'], $rows[$i]['credit']])
            ->toEqual([$e['semester'], $e['ball'], $e['grade'], $e['credit']]);
    }
    // Uzun nomning davomi keyingi fanga yopishmaydi
    expect($rows[56]['name'])->toEndWith('Bolalar travmatologiyasi va ortopediyasi')
        ->and($rows[57]['name'])->toBe('Urologiya, bolalar urologiyasi');

    $fromText = (new RuleMatcher())->match($f['plan'], $rows);
    $fromFixture = (new RuleMatcher())->match($f['plan'], $f['transcript']);
    expect(array_column($fromText, 'tr'))->toBe(array_column($fromFixture, 'tr'));
});

// ─────────────── bitta transkript fani → barcha semestrlar ───────────────

test("transkriptda bitta Gistologiya — rejadagi uch semestrning hammasiga shu baho qo'yiladi", function () {
    $plan = [
        ['key' => 'g2', 'subject_id' => 7, 'semester' => 2, 'name' => 'Gistologiya, sitologiya, embriologiya'],
        ['key' => 'g3', 'subject_id' => 7, 'semester' => 3, 'name' => 'Gistologiya, sitologiya, embriologiya'],
        ['key' => 'g4', 'subject_id' => 7, 'semester' => 4, 'name' => 'Gistologiya, sitologiya, embriologiya'],
        ['key' => 'f2', 'subject_id' => 8, 'semester' => 2, 'name' => 'Falsafa'],
    ];
    // AI faqat 3-semestrni bog'lagan, qolganlarini "yo'q" degan
    $ai = [
        'g2' => ['tr' => null, 'confidence' => 0.2, 'reason' => "yo'q"],
        'g3' => ['tr' => 5, 'confidence' => 0.95, 'reason' => 'bir xil fan'],
        'g4' => ['tr' => null, 'confidence' => 0.2, 'reason' => "yo'q"],
        'f2' => ['tr' => null, 'confidence' => 0.1, 'reason' => "yo'q"],
    ];
    $out = \App\Services\SubjectDifference\SameSubjectFill::apply($plan, $ai);

    expect($out['g2']['tr'])->toBe(5)
        ->and($out['g4']['tr'])->toBe(5)
        ->and($out['g2']['filled_from'])->toBe('g3')
        ->and($out['g2']['reason'])->toContain('3-semestr')
        ->and($out['f2']['tr'])->toBeNull(); // boshqa fan — farq bo'lib qoladi
});

test("fanning bir nechta semestri transkriptda bor bo'lsa — mavjud mosliklarga tegilmaydi, bo'shiga eng yaqini", function () {
    $plan = [
        ['key' => 'b3', 'subject_id' => 9, 'semester' => 3, 'name' => 'Biokimyo'],
        ['key' => 'b4', 'subject_id' => 9, 'semester' => 4, 'name' => 'Biokimyo'],
        ['key' => 'b5', 'subject_id' => 9, 'semester' => 5, 'name' => 'Biokimyo'],
    ];
    $rule = ['b3' => ['tr' => 1, 'score' => 1.0], 'b4' => ['tr' => 2, 'score' => 1.0], 'b5' => ['tr' => null, 'score' => 0.0]];
    $out = \App\Services\SubjectDifference\SameSubjectFill::apply($plan, $rule);

    expect($out['b3']['tr'])->toBe(1)->and($out['b4']['tr'])->toBe(2)->and($out['b5']['tr'])->toBe(2);
});

test("reja tartibi: semestr, so'ng o'quv reja bloki (akkreditatsiyadagidek), so'ng rejadagi o'rni", function () {
    $records = [];
    foreach ([[1, 'Biofizika'], [2, "O'zbekistonning eng yangi tarixi"], [3, 'Tibbiy kimyo'], [4, 'Ishlab chiqarish amaliyoti']] as [$id, $name]) {
        $records[] = ['subject_id' => $id, 'subject_name' => $name, 'semester_id' => '11', 'credit' => '2', 'total_acload' => 60, 'student_id' => 1];
    }
    $types = [
        '1|11' => ['subject_type_name' => 'Majburiy', 'credit' => 2, 'subject_block_code' => '2.02', 'curriculum_subject_hemis_id' => 30],
        '2|11' => ['subject_type_name' => 'Majburiy', 'credit' => 2, 'subject_block_code' => '1.01', 'curriculum_subject_hemis_id' => 90],
        '3|11' => ['subject_type_name' => 'Majburiy', 'credit' => 2, 'subject_block_code' => '2.02', 'curriculum_subject_hemis_id' => 10],
        '4|11' => ['subject_type_name' => 'Majburiy', 'credit' => 2, 'subject_block_code' => '10.01', 'curriculum_subject_hemis_id' => 5],
    ];
    $plan = (new PlanBuilder())->aggregate($records, $types);

    expect(array_column($plan, 'name'))->toBe(["O'zbekistonning eng yangi tarixi", 'Tibbiy kimyo', 'Biofizika', 'Ishlab chiqarish amaliyoti']);
});
