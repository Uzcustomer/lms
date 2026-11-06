<?php

use App\Services\HemisSync\SchedulePlanner;

/** Yarim-slot vaqtlari (board 7 qo'ng'iroq jadvali). */
function hsSlots(): array
{
    $t = ['08:30', '09:10', '09:50', '10:00', '10:40', '11:20', '12:00', '12:40', '13:20', '13:30', '14:10', '14:50',
        '15:00', '15:40', '16:20', '16:30', '17:10', '17:50'];
    $out = [];
    for ($i = 0; $i < 6; $i++) {
        $out[2 * $i + 1] = [$t[3 * $i], $t[3 * $i + 1]];
        $out[2 * $i + 2] = [$t[3 * $i + 1], $t[3 * $i + 2]];
    }

    return $out;
}

/** n-hafta d-kuni (1 = dushanba), semestr 2026-09-14 dan. */
function hsDate(int $week, int $day): string
{
    return date('Y-m-d', strtotime('2026-09-14 +' . (($week - 1) * 7 + $day - 1) . ' days'));
}

function hsWorkDates(int $fromWeek, int $toWeek): array
{
    $out = [];
    for ($w = $fromWeek; $w <= $toWeek; $w++) {
        for ($d = 1; $d <= 6; $d++) {
            $out[hsDate($w, $d)] = $w;
        }
    }

    return $out;
}

function hsHemis(int $id, string $date, int $slot, int $len, string $code, $emp = 7, $room = 'R1'): array
{
    $s = hsSlots();

    return ['id' => $id, 'date' => $date, 'start' => $s[$slot][0], 'end' => $s[$slot + $len - 1][1], 'code' => $code,
        'employee_id' => $emp, 'employee_name' => 'T' . $emp, 'auditorium_code' => $room, 'auditorium_name' => $room];
}

function hsPlan(int $week, int $day, int $slot, int $len, string $part, $emp = 7, $room = 'R1'): array
{
    return ['date' => hsDate($week, $day), 'week' => $week, 'slot' => $slot, 'len' => $len, 'part' => $part,
        'employee_id' => $emp, 'employee_name' => $emp ? 'T' . $emp : null, 'auditorium_code' => $room, 'auditorium_name' => $room];
}

/** Haftalar boshi (dushanba): [hafta => 'Y-m-d'] — o'tgan HEMIS darslarining haftasi uchun. */
function hsWeekStarts(int $weeks = 20): array
{
    $out = [];
    for ($w = 1; $w <= $weeks; $w++) {
        $out[$w] = hsDate($w, 1);
    }

    return $out;
}

function hsUnit(array $over): array
{
    return array_merge([
        'key' => 'g1|s1', 'subject_id' => 1, 'subject_name' => 'Fan', 'base' => 'fan',
        'codes' => ['11' => 'L', '13' => 'A', '12' => 'A'],
        'code_names' => ['11' => 'Ma\'ruza', '13' => 'Amaliy', '12' => 'Laboratoriya'],
        'ktr' => [], 'current_week' => 5, 'hemis' => [], 'plan' => [],
        'fallback_employee' => [99, 'MUDIR'], 'fallback_room' => ['KM', 'Kafedra mudiri xonasi'],
        'week_starts' => hsWeekStarts(),
    ], $over);
}

it('ortiqcha soatni keyingi haftalarda kamaytiradi, lekin darsni 1 soatdan kam qilmaydi (TKK misoli)', function () {
    $u = hsUnit([
        'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 4], ['13' => 4], ['13' => 4]],
        // 1-hafta HEMIS'da 4 o'rniga 8 soat qo'yilgan
        'hemis' => [hsHemis(1, hsDate(1, 1), 1, 4, '13'), hsHemis(2, hsDate(1, 1), 5, 4, '13')],
        'plan' => [hsPlan(1, 1, 1, 4, 'A'), hsPlan(2, 1, 1, 4, 'A'), hsPlan(3, 1, 1, 4, 'A')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 3))[0];

    expect($r['before']['hours_diff']['13'])->toBe(-4)
        ->and($r['after']['hours_diff']['13'])->toBe(0)
        ->and($r['after']['count_diff']['A'])->toBe(0);
    $byDate = [];
    foreach ($r['records'] as $rec) {
        $byDate[$rec['date']] = ($byDate[$rec['date']] ?? 0) + (strtotime($rec['end']) - strtotime($rec['start']) > 2500 ? 2 : 1);
    }
    expect($byDate)->toBe([hsDate(2, 1) => 1, hsDate(3, 1) => 3]);
});

it('amaliyni laboratoriyaga almashtiradi va yetmagan ma\'ruzani boshqa kunga qo\'yadi', function () {
    $hemis = [];
    $id = 1;
    for ($w = 1; $w <= 4; $w++) {
        if ($w !== 4) {
            $hemis[] = hsHemis($id++, hsDate($w, 1), 1, 2, '11');   // 4-hafta ma'ruza o'tilmagan
        }
        $hemis[] = hsHemis($id++, hsDate($w, 2), 1, 2, '13');       // 4-hafta lab o'rniga amaliy
    }
    $u = hsUnit([
        'ktr' => [['11' => 2, '13' => 2], ['11' => 2, '13' => 2], ['11' => 2, '13' => 2], ['11' => 2, '12' => 2], ['11' => 2, '13' => 2]],
        'hemis' => $hemis,
        'plan' => [hsPlan(5, 1, 1, 2, 'L'), hsPlan(5, 2, 1, 2, 'A')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(4, 6)))->planGroup([$u], [], hsWorkDates(5, 5))[0];

    expect($r['before']['hours_diff'])->toBe(['11' => 2, '13' => -2, '12' => 2])
        ->and($r['before']['count_diff'])->toBe(['L' => 1, 'A' => 0])
        ->and($r['after']['hours_diff'])->toBe(['11' => 0, '13' => 0, '12' => 0])
        ->and($r['after']['count_diff'])->toBe(['L' => 0, 'A' => 0]);
    $lectures = array_values(array_filter($r['records'], fn ($x) => $x['code'] === '11'));
    // Ma'ruza ustiga ma'ruza qo'yilmaydi — ikkinchi ma'ruza boshqa kunda
    expect(count(array_unique(array_column($lectures, 'date'))))->toBe(2);
    $tue = array_values(array_filter($r['records'], fn ($x) => $x['date'] === hsDate(5, 2) && $x['code'] !== '11'));
    expect($tue[0]['code'])->toBe('12');
});

it('soat to\'g\'ri, soni ortiq bo\'lsa — kunlarni birlashtiradi (Hafta almashinuvi: hafta limiti yo\'q)', function () {
    // KTR: 2 ta ma'ruza (4 soat). HEMIS: 1-hafta 2 kunga 1 soatdan bo'lib qo'yilgan (soni 2).
    $u = hsUnit([
        'mode' => 'alternate',
        'codes' => ['11' => 'L'], 'code_names' => ['11' => 'Ma\'ruza'], 'current_week' => 1,
        'ktr' => [['11' => 2], ['11' => 2], ['11' => 2]],
        'hemis' => [hsHemis(1, hsDate(1, 1), 1, 1, '11'), hsHemis(2, hsDate(1, 3), 1, 1, '11')],
        'plan' => [hsPlan(2, 1, 1, 2, 'L'), hsPlan(3, 1, 1, 2, 'L')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 3))[0];
    expect($r['before']['count_diff']['L'])->toBe(-1)
        ->and($r['after']['count_diff']['L'])->toBe(0)
        ->and($r['after']['hours_diff']['11'])->toBe(0);
});

it('kelajak yozuvlarini barcha maydonlar bo\'yicha solishtiradi; o\'qituvchi/xona yo\'q bo\'lsa — kafedra mudiri', function () {
    $u = hsUnit([
        'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 2], ['13' => 2], ['13' => 2], ['13' => 2]],
        'hemis' => [
            hsHemis(1, hsDate(1, 1), 1, 2, '13'),
            hsHemis(2, hsDate(2, 1), 1, 2, '13', 8),        // o'qituvchi boshqa
            hsHemis(3, hsDate(3, 4), 5, 2, '13'),           // LMS'da yo'q kun
        ],
        'plan' => [hsPlan(2, 1, 1, 2, 'A'), hsPlan(3, 1, 1, 2, 'A'), hsPlan(4, 1, 3, 2, 'A', null, null)],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 4))[0];
    $ops = array_map(fn ($a) => $a['op'] . ' ' . $a['date'] . ' ' . $a['start'], $r['actions']);
    expect($ops)->toBe([
        'update ' . hsDate(2, 1) . ' 08:30',
        'create ' . hsDate(3, 1) . ' 08:30',
        'delete ' . hsDate(3, 4) . ' 12:00',
        'create ' . hsDate(4, 1) . ' 10:00',
    ]);
    $last = $r['actions'][3];
    expect([$last['employee_id'], $last['auditorium_code'], $last['flags']])->toBe([99, 'KM', ['mudir', 'mudir_xonasi']]);
    // Bugun va o'tgan kunlarga tegilmaydi
    expect(array_filter($r['actions'], fn ($a) => $a['date'] <= hsDate(1, 6)))->toBe([]);
});

it('qo\'shiladigan dars guruh band vaqtiga tushmaydi, bo\'lak qo\'shnisi bilan parallel tura oladi', function () {
    $a = hsUnit(['key' => 'g|a', 'base' => 'prs', 'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 2], ['13' => 4]], 'hemis' => [hsHemis(1, hsDate(1, 1), 1, 2, '13')],
        'plan' => [hsPlan(2, 1, 1, 2, 'A')]]);
    $c = hsUnit(['key' => 'g|c', 'base' => 'prs', 'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 2], ['13' => 4]], 'hemis' => [hsHemis(2, hsDate(1, 1), 1, 2, '13')],
        'plan' => [hsPlan(2, 1, 1, 2, 'A')]]);
    // 10:00–11:20 boshqa fan bilan band — davom 08:30 dan oldin bo'lmaydi, 12:00 ham band emas, lekin 10:00 band.
    $busy = [hsDate(2, 1) => [3 => true, 4 => true]];
    $out = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$a, $c], $busy, hsWorkDates(2, 2));
    foreach ($out as $r) {
        expect($r['after']['hours_diff']['13'])->toBe(2)   // davom ettirib bo'lmadi
            ->and($r['notes'])->not->toBe([]);
    }
});

it('bo\'lak qo\'shnilari bir vaqtda davom ettiriladi', function () {
    $mk = fn ($k, $id) => hsUnit(['key' => $k, 'base' => 'prs', 'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 2], ['13' => 4]], 'hemis' => [hsHemis($id, hsDate(1, 1), 1, 2, '13')],
        'plan' => [hsPlan(2, 1, 1, 2, 'A')]]);
    $out = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$mk('g|a', 1), $mk('g|c', 2)], [], hsWorkDates(2, 2));
    foreach ($out as $r) {
        expect($r['after']['hours_diff']['13'])->toBe(0)
            ->and(array_column($r['records'], 'start'))->toBe(['08:30', '10:00']);
    }
});

it('KTR yo\'q bo\'lsa HEMIS\'dagi dars turi saqlanadi, o\'zboshimcha turga o\'zgartirilmaydi', function () {
    $u = hsUnit([
        'ktr' => null, 'current_week' => 1,
        'hemis' => [hsHemis(1, hsDate(2, 1), 1, 2, '13'), hsHemis(2, hsDate(2, 3), 3, 2, '12'), hsHemis(3, hsDate(3, 1), 1, 2, '13')],
        'plan' => [hsPlan(2, 1, 1, 2, 'A'), hsPlan(2, 3, 3, 2, 'A'), hsPlan(3, 1, 1, 2, 'A'), hsPlan(4, 1, 1, 2, 'A')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 4))[0];

    // Mavjud darslar o'z turida qoladi; HEMIS'da yo'q kunga — shu qismda eng ko'p uchragan tur (Amaliy)
    expect(array_map(fn ($a) => $a['op'] . ' ' . $a['date'] . ' ' . $a['code'], $r['actions']))->toBe(['create ' . hsDate(4, 1) . ' 13'])
        ->and($r['notes'])->toContain('KTR kiritilmagan — balans tekshirilmadi, faqat jadval solishtirildi.');
});

it('kun ichida darslar surilsa — o\'chirish + qo\'shish emas, shu turdagi darsning vaqti o\'zgartiriladi', function () {
    $u = hsUnit([
        'codes' => ['11' => 'L', '14' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '14' => 'Seminar'], 'current_week' => 1,
        'ktr' => [[], ['11' => 2, '14' => 2]],
        // HEMIS: ma'ruza 10:00 (T5), seminar 12:00 (T7)
        'hemis' => [hsHemis(1, hsDate(2, 6), 3, 2, '11', 5), hsHemis(2, hsDate(2, 6), 5, 2, '14', 7)],
        // LMS: ma'ruza 12:00 (T6), seminar 13:30 (T7)
        'plan' => [hsPlan(2, 6, 5, 2, 'L', 6), hsPlan(2, 6, 7, 2, 'A', 7)],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 2))[0];
    $acts = array_map(fn ($a) => $a['op'] . ' ' . $a['hemis_id'] . ' ' . implode('; ', $a['changes']), $r['actions']);

    expect($acts)->toBe([
        "update 1 vaqt: 10:00-11:20 → 12:00-13:20; o'qituvchi: T5 → T6",
        'update 2 vaqt: 12:00-13:20 → 13:30-14:50',
    ]);
});

/**
 * Amallarni HEMIS'ga yozuvchi tartibida qo'llaydi (o'chirish; o'zgartirish — sana, navbat, vaqt bo'yicha; qo'shish).
 * Har qadamda guruhning shu vaqtida hali boshqa dars turgan bo'lsa — to'qnashuv. Qaytaradi: [to'qnashuvlar, yakuniy holat].
 */
function hsReplay(array $hemis, array $actions): array
{
    $order = ['delete' => 0, 'update' => 1, 'create' => 2];
    usort($actions, fn ($x, $y) => [$order[$x['op']], $x['date'], $x['seq'] ?? 0, $x['start']] <=> [$order[$y['op']], $y['date'], $y['seq'] ?? 0, $y['start']]);
    $row = fn ($r) => ['date' => $r['date'], 'start' => substr($r['start'], 0, 5), 'end' => substr($r['end'], 0, 5), 'code' => (string) $r['code']];
    $state = [];
    foreach ($hemis as $h) {
        $state['h' . $h['id']] = $row($h);
    }
    $clash = [];
    $new = 0;
    foreach ($actions as $a) {
        if ($a['op'] === 'delete') {
            unset($state['h' . $a['hemis_id']]);
            continue;
        }
        $id = $a['op'] === 'update' ? 'h' . $a['hemis_id'] : 'new' . ++$new;
        foreach ($state as $o => $s) {
            if ($o !== $id && $s['date'] === $a['date'] && $s['start'] < $a['end'] && $a['start'] < $s['end']) {
                $clash[] = "{$a['op']} {$id} → {$a['start']}: {$o} {$s['start']} da hali turibdi";
            }
        }
        $state[$id] = $row($a);
    }
    $text = fn ($s) => $s['date'] . ' ' . $s['start'] . '-' . $s['end'] . ' ' . $s['code'];

    return [$clash, array_map($text, array_values($state))];
}

function hsWanted(array $records): array
{
    $out = array_map(fn ($r) => $r['date'] . ' ' . $r['start'] . '-' . $r['end'] . ' ' . $r['code'], $records);
    sort($out);

    return $out;
}

it('kun ichida kechroqqa surilgan darslar: o\'rni bo\'shaydigan dars avval yoziladi (band vaqtga ko\'chirilmaydi)', function () {
    $u = hsUnit([
        'codes' => ['11' => 'L', '14' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '14' => 'Seminar'], 'current_week' => 1,
        'ktr' => [[], ['11' => 2, '14' => 2]],
        'hemis' => [hsHemis(1, hsDate(2, 6), 3, 2, '11', 5), hsHemis(2, hsDate(2, 6), 5, 2, '14', 7)],
        'plan' => [hsPlan(2, 6, 5, 2, 'L', 6), hsPlan(2, 6, 7, 2, 'A', 7)],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 2))[0];
    [$clash, $final] = hsReplay($u['hemis'], $r['actions']);
    sort($final);
    $seq = array_column($r['actions'], 'seq', 'hemis_id');

    expect($clash)->toBe([])
        ->and($final)->toBe(hsWanted($r['records']))
        ->and($seq[2])->toBeLessThan($seq[1]);
});

it('ma\'ruza va seminar o\'rin almashsa — vaqt emas, tur o\'zgartiriladi (bir-birini kutib qolmaydi)', function () {
    $u = hsUnit([
        'codes' => ['11' => 'L', '14' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '14' => 'Seminar'], 'current_week' => 1,
        'ktr' => [[], ['11' => 2, '14' => 2]],
        // HEMIS: ma'ruza 10:00 (T5), seminar 12:00 (T7); LMS: seminar 10:00 (T7), ma'ruza 12:00 (T5)
        'hemis' => [hsHemis(1, hsDate(2, 6), 3, 2, '11', 5), hsHemis(2, hsDate(2, 6), 5, 2, '14', 7)],
        'plan' => [hsPlan(2, 6, 3, 2, 'A', 7), hsPlan(2, 6, 5, 2, 'L', 5)],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 2))[0];
    [$clash, $final] = hsReplay($u['hemis'], $r['actions']);
    sort($final);

    expect(array_map(fn ($a) => $a['op'] . ' ' . $a['hemis_id'] . ' ' . implode('; ', $a['changes']), $r['actions']))->toBe([
        "update 1 tur: Ma'ruza → Seminar; o'qituvchi: T5 → T7",
        "update 2 tur: Seminar → Ma'ruza; o'qituvchi: T7 → T5",
    ])->and($clash)->toBe([])
        ->and($final)->toBe(hsWanted($r['records']));
});

it('o\'rin almashgan darslarning vaqti aynan mos kelmasa — biri o\'chirib qayta qo\'shiladi, to\'qnashuvsiz', function () {
    $u = hsUnit([
        'codes' => ['11' => 'L', '14' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '14' => 'Seminar'], 'current_week' => 1,
        'ktr' => [[], ['11' => 1, '14' => 1]],
        // HEMIS: ma'ruza 10:00-11:20, seminar 12:00-13:20; LMS: seminar 10:40-11:20, ma'ruza 12:00-12:40
        'hemis' => [hsHemis(1, hsDate(2, 6), 3, 2, '11', 5), hsHemis(2, hsDate(2, 6), 5, 2, '14', 7)],
        'plan' => [hsPlan(2, 6, 4, 1, 'A', 7), hsPlan(2, 6, 5, 1, 'L', 5)],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 2))[0];
    [$clash, $final] = hsReplay($u['hemis'], $r['actions']);
    sort($final);

    expect($clash)->toBe([])
        ->and($final)->toBe(hsWanted($r['records']))
        ->and(array_count_values(array_column($r['actions'], 'op')))->toEqual(['delete' => 1, 'update' => 1, 'create' => 1]);
});

it('haftada KTR\'dagidan ko\'p kun bo\'lsa — o\'sha haftaning kunlari birlashtiriladi (boshqa haftaga zanjir surilmaydi), dars o\'z soatida qoladi', function () {
    // KTR: har hafta Amaliy qism 1 kun. LMS: 1-haftada chorshanba 10:00–13:20; 2-haftada seshanba 08:30 + chorshanba 10:00–13:20.
    $u = hsUnit([
        'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 0,
        'ktr' => [['13' => 4], ['13' => 6]],
        'hemis' => [hsHemis(1, hsDate(1, 3), 3, 2, '13'), hsHemis(2, hsDate(1, 3), 5, 2, '13'),
            hsHemis(3, hsDate(2, 3), 1, 2, '13'), hsHemis(4, hsDate(2, 3), 3, 2, '13'), hsHemis(5, hsDate(2, 3), 5, 2, '13')],
        'plan' => [hsPlan(1, 3, 3, 4, 'A'), hsPlan(2, 2, 1, 2, 'A'), hsPlan(2, 3, 3, 4, 'A')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 1)))->planGroup([$u], [], hsWorkDates(1, 2))[0];

    expect($r['before']['count_diff']['A'])->toBe(-1)
        ->and($r['after']['count_diff']['A'])->toBe(0)
        ->and($r['fixes'][0]['text'])->toContain(hsDate(2, 2))->toContain(hsDate(2, 3))
        // Natija HEMIS bilan aynan bir xil: 2-hafta chorshanba 08:30–13:20 — amal yo'q.
        ->and($r['actions'])->toBe([]);
});

it('LMS darsi KTR soatidan qisqa bo\'lsa — kamomad bitta kunga emas, har haftaning o\'z darsiga qo\'shiladi', function () {
    $u = hsUnit([
        'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'ktr' => [['13' => 4], ['13' => 4], ['13' => 4], ['13' => 4]],
        'hemis' => [hsHemis(1, hsDate(1, 5), 1, 4, '13')],
        // LMS kartasi 2 soatlik
        'plan' => [hsPlan(2, 5, 1, 2, 'A'), hsPlan(3, 5, 1, 2, 'A'), hsPlan(4, 5, 1, 2, 'A')],
    ]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 4))[0];

    expect($r['before']['hours_diff']['13'])->toBe(6)
        ->and($r['after']['hours_diff']['13'])->toBe(0);
    $byDate = [];
    foreach ($r['records'] as $rec) {
        $byDate[$rec['date']] = ($byDate[$rec['date']] ?? 0) + (strtotime($rec['end']) - strtotime($rec['start']) > 2500 ? 2 : 1);
    }
    expect($byDate)->toBe([hsDate(2, 5) => 4, hsDate(3, 5) => 4, hsDate(4, 5) => 4])
        ->and(implode(' ', $r['notes']))->toContain('KTR soatidan qisqa')->toContain('2-hafta: KTR 4 soat, jadvalda 2');
});

// ---------------------------------------------------------------------------------------------------------------------------
// Balans "Hemisga yozish" muddatigacha, "Har hafta" hafta limiti, balanslash darsi (eng yaqin dars kuni, bandliksiz).
// Sanalar: 8-hafta — 02.11–08.11.2026; bugun — 06.11 (juma), muddat — 8-hafta oxiri (08.11).

function hsWeekly(array $over): array
{
    return hsUnit(array_merge(['mode' => 'weekly', 'until_week' => 8, 'until_date' => hsDate(8, 7), 'current_week' => 8], $over));
}

it('Gigiyena: 8-hafta oxirigacha KTR = HEMIS (8 dars / 20 soat) — hech narsa qo\'shilmaydi, 07.11 dagi ortiqcha dars o\'chiriladi', function () {
    // KTR: 1-hafta A4; 2–7-hafta L2 + A2; 8–15-hafta A4 (jami A 48 soat, 15 dars).
    $ktr = [['13' => 4]];
    for ($w = 2; $w <= 7; $w++) {
        $ktr[] = ['11' => 2, '13' => 2];
    }
    for ($w = 8; $w <= 15; $w++) {
        $ktr[] = ['13' => 4];
    }
    $hemis = [hsHemis(1, hsDate(1, 3), 1, 4, '13')];
    $id = 2;
    for ($w = 2; $w <= 7; $w++) {
        $hemis[] = hsHemis($id++, hsDate($w, 3), 1, 2, '13');
        $hemis[] = hsHemis($id++, hsDate($w, 1), 1, 2, '11');
    }
    $hemis[] = hsHemis(50, hsDate(8, 3), 1, 4, '13');    // 04.11 — o'tilgan 8-hafta darsi
    $hemis[] = hsHemis(51, hsDate(8, 6), 3, 2, '13');    // 07.11 (shanba) — avvalgi solishtirish qo'ygan ortiqcha dars
    $u = hsWeekly([
        'codes' => ['11' => 'L', '13' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '13' => 'Amaliy'],
        'ktr' => $ktr, 'hemis' => $hemis,
        // LMS: amaliy kartalar faqat 8- va 9-haftada (shablon bo'sh) — kelgusida bitta dars (11.11, muddatdan keyin).
        'plan' => [hsPlan(8, 3, 1, 4, 'A'), hsPlan(9, 3, 1, 4, 'A')],
    ]);
    // Guruh shanba kunlari ham keladi (boshqa fan) — lekin kamomad yo'q, balanslash darsi qo'yilmaydi.
    $group = [hsDate(8, 6) => [3 => true, 4 => true], hsDate(9, 6) => [3 => true]];
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u], [], hsWorkDates(8, 15), $group)[0];

    expect($r['mode'])->toBe('weekly')
        ->and($r['balance_weeks'])->toBe(['A' => 8, 'L' => 8])
        ->and($r['unplaced_weeks'])->toBe([])
        ->and($r['before']['ktr_count'])->toBe(['L' => 6, 'A' => 8])
        ->and($r['before']['past_count'])->toBe(['L' => 6, 'A' => 8])
        ->and($r['before']['past_hours'])->toBe(['13' => 20, '11' => 12])
        ->and($r['before']['count_diff'])->toBe(['L' => 0, 'A' => 0])
        ->and($r['after']['count_diff'])->toBe(['L' => 0, 'A' => 0])
        ->and($r['after']['hours_diff'])->toBe(['11' => 0, '13' => 0])
        ->and(array_column($r['fixes'], 'type'))->toBe([]);
    $inTime = array_values(array_filter($r['actions'], fn ($a) => $a['date'] <= hsDate(8, 7)));
    expect(array_map(fn ($a) => [$a['op'], $a['date'], $a['hemis_id'], $a['reason'] ?? null], $inTime))
        ->toBe([['delete', hsDate(8, 6), 51, '8-hafta: KTR 1 dars, HEMIS 2 — ortiqcha']])
        // Muddatdan keyingi 9-hafta darsi — rejada (keyingi solishtirish), balansga kirmaydi.
        ->and(array_values(array_unique(array_column(array_filter($r['actions'], fn ($a) => $a['date'] > hsDate(8, 7)), 'date'))))->toBe([hsDate(9, 3)]);
});

/** 1..9-hafta: amaliy har hafta 2 soat (dushanba 10:00), HEMIS'da 6-hafta darsi yo'q. */
function hsMissingWeek6(array $over = []): array
{
    $hemis = [];
    foreach ([1, 2, 3, 4, 5, 7, 8] as $w) {
        $hemis[] = hsHemis($w, hsDate($w, 1), 3, 2, '13');
    }
    $plan = [];
    for ($w = 1; $w <= 9; $w++) {
        $plan[] = hsPlan($w, 1, 3, 2, 'A');
    }

    return hsWeekly(array_merge(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'ktr' => array_fill(0, 9, ['13' => 2]),
        'hemis' => $hemis, 'plan' => $plan], $over));
}

it('balanslash: 6-hafta darsi o\'tilmagan — guruh keladigan shanbaga, fanning odatdagi vaqtida (u vaqtda boshqa dars bo\'lsa ham), bandliksiz', function () {
    $u = hsMissingWeek6();
    // Shanba (07.11) guruhning boshqa darsi 10:00–11:20 — aynan shu vaqtda; bandlik tekshirilmaydi.
    $sat = hsDate(8, 6);
    $busy = [$sat => [3 => true, 4 => true]];
    $other = hsUnit(['key' => 'g1|s2', 'subject_id' => 2, 'base' => 'boshqa', 'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'],
        'mode' => 'weekly', 'ktr' => null, 'plan' => [hsPlan(8, 6, 3, 2, 'A', 8, 'R2')]]);
    [$r, $o] = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u, $other], $busy, hsWorkDates(8, 9));

    expect($r['before']['count_diff'])->toBe(['L' => 0, 'A' => 1])
        ->and($r['after']['count_diff'])->toBe(['L' => 0, 'A' => 0])
        ->and($r['after']['hours_diff'])->toBe(['13' => 0])
        ->and($r['fixes'])->toBe([['type' => 'balance_day', 'date' => $sat, 'week' => 6, 'later' => false,
            'text' => "Balanslash: amaliy qism soni yetmaydi (6-hafta darsi o'rniga): {$sat} ga 2 soat qo'shildi"]]);
    $bal = array_values(array_filter($r['actions'], fn ($a) => $a['date'] === $sat));
    expect(array_map(fn ($a) => [$a['op'], $a['start'], $a['end'], $a['employee_id'], $a['auditorium_code'], $a['balance'], $a['balance_week'], $a['blocked']], $bal))
        ->toBe([['create', '10:00', '11:20', 7, 'R1', true, 6, []]])
        // Boshqa fanning o'sha vaqtdagi darsi joyida qoladi (balanslash darsi uni band qilmaydi va undan to'silmaydi).
        ->and(array_map(fn ($a) => $a['op'] . ' ' . $a['date'] . ' ' . $a['start'], $o['actions']))->toBe(['create ' . $sat . ' 10:00']);
});

it('balanslash: shanba guruh kelmaydi — keyingi haftaning eng yaqin dars kuniga (muddatdan keyin, hozir yozilmaydi); shu qism bor kun o\'tkaziladi', function () {
    $u = hsMissingWeek6();
    // 9-hafta dushanba — shu fanning amaliy darsi (o'tkaziladi), seshanba — guruhning boshqa darsi 13:30 da.
    $tue = hsDate(9, 2);
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u], [], hsWorkDates(8, 9), [$tue => [7 => true, 8 => true]])[0];

    expect($r['fixes'])->toBe([['type' => 'balance_day', 'date' => $tue, 'week' => 6, 'later' => true,
        'text' => "Balanslash: amaliy qism soni yetmaydi (6-hafta darsi o'rniga): {$tue} ga 2 soat qo'shildi"]])
        ->and($r['after']['count_diff']['A'])->toBe(0);
    // Vaqti — fanning odatdagi (eng yaqin LMS darsi) boshlanishi, guruhning o'sha kundagi birinchi darsi emas.
    $bal = array_values(array_filter($r['actions'], fn ($a) => $a['date'] === $tue));
    expect(array_map(fn ($a) => [$a['op'], $a['start'], $a['balance']], $bal))->toBe([['create', '10:00', true]]);

    // Hech qanday mos kun yo'q (guruh faqat shu fanning amaliy kunlarida keladi) — izoh.
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u], [], hsWorkDates(8, 9))[0];
    expect(array_column($r['fixes'], 'type'))->toBe([])
        ->and($r['notes'])->toContain("Balanslash: amaliy qism — 6-hafta darsi uchun mos kun topilmadi (guruhning dars kunlarida shu qism yo'q kun qolmadi).");
});

it('balanslash: mos kunda shu fanning shu qismi bo\'lsa — o\'tkaziladi (ma\'ruza shanba kuni bor — keyingi dars kuniga, ma\'ruza vaqtida)', function () {
    // Ma'ruza har shanba 13:30 (6-hafta ma'ruzasi o'tilmagan), amaliy har dushanba 10:00.
    $hemis = [];
    $id = 1;
    for ($w = 1; $w <= 8; $w++) {
        $hemis[] = hsHemis($id++, hsDate($w, 1), 3, 2, '13');
        if ($w !== 6 && $w !== 8) {
            $hemis[] = hsHemis($id++, hsDate($w, 6), 7, 2, '11');
        }
    }
    $plan = [];
    for ($w = 1; $w <= 9; $w++) {
        $plan[] = hsPlan($w, 1, 3, 2, 'A');
        $plan[] = hsPlan($w, 6, 7, 2, 'L', 5, 'Z1');
    }
    $u = hsWeekly(['codes' => ['11' => 'L', '13' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '13' => 'Amaliy'],
        'ktr' => array_fill(0, 9, ['11' => 2, '13' => 2]), 'hemis' => $hemis, 'plan' => $plan]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u], [], hsWorkDates(8, 9))[0];

    // Shanba 07.11 — ma'ruza bor (o'tkaziladi); keyingi dars kuni — 9-hafta dushanba (faqat amaliy).
    $mon = hsDate(9, 1);
    expect($r['before']['count_diff'])->toBe(['L' => 1, 'A' => 0])
        ->and($r['fixes'])->toBe([['type' => 'balance_day', 'date' => $mon, 'week' => 6, 'later' => true,
            'text' => "Balanslash: ma'ruza soni yetmaydi (6-hafta darsi o'rniga): {$mon} ga 2 soat qo'shildi"]]);
    $bal = array_values(array_filter($r['actions'], fn ($a) => !empty($a['balance'])));
    // Ma'ruza vaqtida (13:30), ma'ruza o'qituvchisi va xonasi bilan — amaliy darsi bilan bir kunda.
    expect(array_map(fn ($a) => [$a['date'], $a['start'], $a['code'], $a['employee_id'], $a['auditorium_code']], $bal))
        ->toBe([[$mon, '13:30', '11', 5, 'Z1']]);
});

it('"Har hafta": bir haftada KTR\'dagidan ko\'p dars — ortiqchasi o\'chirishga (sababi bilan), hafta limiti saqlanadi', function () {
    // KTR: 1–3-hafta amaliy 2 soat. HEMIS: 1-hafta (o'tgan) va 2-haftada ikki kun (dushanba, chorshanba).
    $u = hsWeekly(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'until_week' => 3, 'until_date' => hsDate(3, 7), 'ktr' => array_fill(0, 3, ['13' => 2]),
        'hemis' => [hsHemis(1, hsDate(1, 1), 1, 2, '13'), hsHemis(2, hsDate(2, 1), 1, 2, '13'), hsHemis(3, hsDate(2, 3), 1, 2, '13')],
        'plan' => [hsPlan(1, 1, 1, 2, 'A'), hsPlan(2, 1, 1, 2, 'A'), hsPlan(2, 3, 1, 2, 'A'), hsPlan(3, 1, 1, 2, 'A')]]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 3))[0];

    $perWeek = [];
    foreach ($r['records'] as $rec) {
        $w = (int) floor((strtotime($rec['date']) - strtotime(hsDate(1, 1))) / 604800) + 1;
        $perWeek[$w] = ($perWeek[$w] ?? 0) + (int) round((strtotime($rec['end']) - strtotime($rec['start'])) / 2400);
    }
    expect($r['after']['count_diff']['A'])->toBe(0)
        ->and($r['after']['hours_diff']['13'])->toBe(0)
        ->and($perWeek)->toBe([2 => 2, 3 => 2])
        ->and(count(array_unique(array_column(array_filter($r['records'], fn ($x) => $x['date'] < hsDate(3, 1)), 'date'))))->toBe(1)
        ->and(array_map(fn ($a) => [$a['op'], $a['date'], $a['reason'] ?? null], array_values(array_filter($r['actions'], fn ($a) => $a['op'] === 'delete'))))
        ->toBe([['delete', hsDate(2, 1), '2-hafta: KTR 1 dars, HEMIS 2 — ortiqcha']]);

    // Jami teng, lekin hafta bo'yicha noto'g'ri: 1-hafta darsi HEMIS'da o'tilmagan, 2-haftada LMS'da ikki dars. Ortiqchasi olib
    // tashlanadi — hafta limiti; 1-hafta kamomadi — balanslash darsi (eng yaqin dars kuniga, limitdan istisno).
    $u['plan'] = [hsPlan(1, 1, 1, 2, 'A'), hsPlan(2, 1, 1, 2, 'A'), hsPlan(2, 3, 1, 2, 'A'), hsPlan(3, 1, 1, 2, 'A')];
    $u['hemis'] = [hsHemis(2, hsDate(2, 1), 1, 2, '13')];
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 3))[0];
    expect(array_column($r['fixes'], 'text'))->toBe([
        '2-hafta: KTR 1 dars, jadvalda 2 — ' . hsDate(2, 3) . ' dagi amaliy olib tashlandi (Har hafta: hafta limiti)',
        "Balanslash: amaliy qism soni yetmaydi (1-hafta darsi o'rniga): " . hsDate(2, 3) . " ga 2 soat qo'shildi",
    ])->and($r['after']['count_diff']['A'])->toBe(0);
});

it('"Har hafta": soat yetmasa (soni to\'g\'ri) — mavjud darslar davomiga, bitta kunga yig\'ilmaydi, hafta limitidan oshmaydi', function () {
    // KTR: har hafta amaliy 4 soat; 1-hafta HEMIS'da 2 soat o'tilgan (2 soat kam); LMS kartasi 2 soatlik.
    $mk = fn (string $mode) => hsWeekly(['mode' => $mode, 'codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'current_week' => 1,
        'until_week' => 3, 'until_date' => hsDate(3, 7), 'ktr' => array_fill(0, 3, ['13' => 4]),
        'hemis' => [hsHemis(1, hsDate(1, 5), 1, 2, '13')], 'plan' => [hsPlan(1, 5, 1, 2, 'A'), hsPlan(2, 5, 1, 2, 'A'), hsPlan(3, 5, 1, 2, 'A')]]);
    $byDate = function (array $r) {
        $out = [];
        foreach ($r['records'] as $rec) {
            $out[$rec['date']] = ($out[$rec['date']] ?? 0) + (int) round((strtotime($rec['end']) - strtotime($rec['start'])) / 2400);
        }

        return $out;
    };
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$mk('weekly')], [], hsWorkDates(2, 3))[0];
    expect($r['before']['hours_diff']['13'])->toBe(6)
        ->and($byDate($r))->toBe([hsDate(2, 5) => 4, hsDate(3, 5) => 4])
        ->and($r['after']['hours_diff']['13'])->toBe(2)
        ->and(implode(' ', $r['notes']))->toContain("Amaliy qism 2 soat kam — muddatgacha darslar davomiga qo'shib bo'lmadi (har hafta KTR soatidan oshmaydi");
    // "Hafta almashinuvi" — limit yo'q: qolgan 2 soat darslarga navbat bilan 1 soatdan (bitta kunga yig'ilmaydi).
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$mk('alternate')], [], hsWorkDates(2, 3))[0];
    expect($byDate($r))->toBe([hsDate(2, 5) => 5, hsDate(3, 5) => 5])
        ->and($r['after']['hours_diff']['13'])->toBe(0);
});

it('sikl fani — natija avvalgidek: muddat va hafta limiti ta\'sir qilmaydi, kamomad blok ichidagi bo\'sh kunga (bandlik bilan)', function () {
    // Sikl: 5 kunlik blok (2-hafta du–ju), KTR qatori — sikl kuni; 1-kun o'tgan, 3-kun HEMIS'da yo'q (rejada ham yo'q).
    $days = [1 => hsDate(2, 1), 2 => hsDate(2, 2), 3 => hsDate(2, 3), 4 => hsDate(2, 4), 5 => hsDate(2, 5)];
    $plan = [];
    foreach ([1, 2, 4, 5] as $d) {
        $plan[] = ['date' => $days[$d], 'week' => $d, 'slot' => 1, 'len' => 4, 'part' => 'A', 'employee_id' => 7, 'employee_name' => 'T7',
            'auditorium_code' => 'R1', 'auditorium_name' => 'R1', 'cycle' => true];
    }
    $base = ['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'cycle' => true, 'current_week' => 1,
        'ktr' => array_fill(0, 5, ['13' => 4]), 'hemis' => [hsHemis(1, $days[1], 1, 4, '13')], 'plan' => $plan,
        'work_dates' => array_flip(array_slice($days, 1, null, true)), 'block_from' => $days[1], 'block_to' => $days[5]];
    $base['work_dates'] = [$days[2] => 2, $days[3] => 3, $days[4] => 4, $days[5] => 5];
    // 3-kun 08:30–11:20 boshqa fan bilan band — sikl kamomadi bandlik bilan, bo'sh vaqtga.
    $busy = [$days[3] => [1 => true, 2 => true, 3 => true, 4 => true]];
    $plain = (new SchedulePlanner(hsSlots(), $days[1]))->planGroup([hsUnit($base)], $busy, hsWorkDates(2, 2))[0];
    $withUntil = (new SchedulePlanner(hsSlots(), $days[1]))->planGroup([hsUnit($base + ['until_week' => 1, 'until_date' => hsDate(1, 7)])],
        $busy, hsWorkDates(2, 2), [$days[3] => [1 => true]])[0];

    expect($withUntil)->toBe($plain)
        ->and($plain['mode'])->toBe('cycle')
        ->and($plain['balance_weeks'])->toBeNull()
        ->and($plain['unplaced_weeks'])->toBe([])
        ->and(array_map(fn ($f) => $f['type'] . ' ' . $f['date'], $plain['fixes']))->toBe(['new_day ' . $days[3], 'extend ' . $days[3]])
        // Band 08:30–11:20 dan keyin (bandlik tekshiriladi), balanslash belgisi yo'q.
        ->and(array_values(array_filter($plain['records'], fn ($x) => $x['date'] === $days[3]))[0]['start'])->toBe('12:00')
        ->and(array_filter($plain['records'], fn ($x) => !empty($x['balance'])))->toBe([]);
});

// ---------------------------------------------------------------------------------------------------------------------------
// Balans chegarasi H: ertangi haftadan uzluksiz joylangan oxirgi hafta (har qism). LMS'da joylanmagan haftalardagi HEMIS darslari —
// o'chirish (lms_unplaced), balanslanmaydi.

/** Gigiyena (p/st25-05b): KTR 1-hafta A4, 2–7 L2+A2, 8–15 A4; LMS amaliy — faqat 8- va 9-hafta (chorshanba 08:30, 4 soat). */
function hsGigiyena(int $untilWeek, array $extraHemis = []): array
{
    $ktr = [['13' => 4]];
    for ($w = 2; $w <= 7; $w++) {
        $ktr[] = ['11' => 2, '13' => 2];
    }
    for ($w = 8; $w <= 15; $w++) {
        $ktr[] = ['13' => 4];
    }
    $hemis = [hsHemis(1, hsDate(1, 3), 1, 4, '13')];
    $id = 2;
    for ($w = 2; $w <= 7; $w++) {
        $hemis[] = hsHemis($id++, hsDate($w, 3), 1, 2, '13');
        $hemis[] = hsHemis($id++, hsDate($w, 1), 1, 2, '11');
    }
    $hemis[] = hsHemis(50, hsDate(8, 3), 1, 4, '13');                         // 04.11 — o'tilgan
    $hemis[] = hsHemis(60, hsDate(9, 3), 1, 2, '13');                         // 11.11 — LMS bilan bir xil (08:30 + 10:00)
    $hemis[] = hsHemis(61, hsDate(9, 3), 3, 2, '13');

    return hsWeekly(['codes' => ['11' => 'L', '13' => 'A'], 'code_names' => ['11' => 'Ma\'ruza', '13' => 'Amaliy'],
        'ktr' => $ktr, 'hemis' => array_merge($hemis, $extraHemis), 'until_week' => $untilWeek, 'until_date' => hsDate($untilWeek, 7),
        'plan' => [hsPlan(8, 3, 1, 4, 'A'), hsPlan(9, 3, 1, 4, 'A')]]);
}

it('balans chegarasi: Gigiyena, muddat 15 — H = 9, kamomad yo\'q; 10–15-haftadagi HEMIS darslari — o\'chirish (LMSda joylanmagan), balanslash yo\'q', function () {
    $extra = [hsHemis(70, hsDate(8, 6), 3, 2, '13')];   // 07.11 — avvalgi solishtirishning ortiqcha darsi
    $id = 80;
    foreach ([1, 2, 4, 5, 6] as $d) {                    // 09–14.11 — avvalgi balanslash darslari (LMS'da yo'q)
        $extra[] = hsHemis($id++, hsDate(9, $d), 3, 2, '13');
    }
    for ($w = 10; $w <= 15; $w++) {                      // 10–15-hafta: chorshanba 08:30 + 10:00 (LMS'da joylanmagan)
        $extra[] = hsHemis($id++, hsDate($w, 3), 1, 2, '13');
        $extra[] = hsHemis($id++, hsDate($w, 3), 3, 2, '13');
    }
    $group = [hsDate(8, 6) => [3 => true], hsDate(9, 2) => [1 => true], hsDate(10, 1) => [1 => true]];
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([hsGigiyena(15, $extra)], [], hsWorkDates(8, 15), $group)[0];

    expect($r['balance_weeks'])->toBe(['A' => 9, 'L' => 15])
        ->and($r['unplaced_weeks'])->toBe(['A' => [10, 11, 12, 13, 14, 15]])
        // KTR 1–9 = 9 dars / 24 soat ↔ HEMIS 8 / 20 + LMS 9-hafta 1 / 4.
        ->and($r['before']['ktr_count']['A'])->toBe(9)
        ->and($r['before']['future_count']['A'])->toBe(1)
        ->and($r['before']['count_diff'])->toBe(['L' => 0, 'A' => 0])
        ->and($r['after']['hours_diff'])->toBe(['11' => 0, '13' => 0])
        ->and($r['fixes'])->toBe([])
        ->and(array_filter($r['actions'], fn ($a) => $a['op'] !== 'delete'))->toBe([]);
    $del = [];
    foreach ($r['actions'] as $a) {
        $del[$a['reason_code']][] = $a['date'];
    }
    expect(array_map(fn ($d) => array_values(array_unique($d)), $del))->toBe([
        'week_surplus' => [hsDate(8, 6), hsDate(9, 1), hsDate(9, 2), hsDate(9, 4), hsDate(9, 5), hsDate(9, 6)],
        'lms_unplaced' => array_map(fn ($w) => hsDate($w, 3), range(10, 15)),
    ])->and(count($del['lms_unplaced']))->toBe(12);
    $first = array_values(array_filter($r['actions'], fn ($a) => $a['reason_code'] === 'lms_unplaced'))[0];
    expect([$first['reason'], $first['week']])->toBe(['LMSda joylanmagan (10-hafta)', 10])
        ->and(array_values(array_filter($r['actions'], fn ($a) => $a['date'] === hsDate(9, 1)))[0]['reason'])->toBe('9-hafta: KTR 1 dars, HEMIS 6 — ortiqcha')
        // Bugun va o'tgan kunlar — hech qanday amal yo'q.
        ->and(array_filter($r['actions'], fn ($a) => $a['date'] <= hsDate(8, 5)))->toBe([]);
});

it('balans chegarasi: Gigiyena, muddat 9 — H = 9; 10–15-haftadagi HEMIS darslari muddatdan keyin (amal yozilmaydi)', function () {
    $extra = [];
    $id = 80;
    for ($w = 10; $w <= 15; $w++) {
        $extra[] = hsHemis($id++, hsDate($w, 3), 1, 2, '13');
    }
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([hsGigiyena(9, $extra)], [], hsWorkDates(8, 15))[0];

    expect($r['balance_weeks'])->toBe(['A' => 9, 'L' => 9])
        ->and($r['unplaced_weeks'])->toBe([])
        ->and($r['before']['count_diff'])->toBe(['L' => 0, 'A' => 0])
        ->and($r['fixes'])->toBe([])
        // Muddat (15.11) ichida amal yo'q; keyingilari — "hozir yozilmaydi" (solishtirish ularni muddatdan keyin deb chiqaradi).
        ->and(array_filter($r['actions'], fn ($a) => $a['date'] <= hsDate(9, 7)))->toBe([]);
});

it('balans chegarasi: uzluksizlik — 9 joylangan, 10 joylanmagan, 11 joylangan → H = 9; 11-hafta LMS darsi yoziladi, balanslanmaydi', function () {
    // KTR 1..12 amaliy 2 soat; LMS: 1..9 va 11-hafta (dushanba 10:00); HEMIS 6-hafta darsi yo'q, qolgan 1..8 bor.
    $hemis = [];
    foreach ([1, 2, 3, 4, 5, 7, 8] as $w) {
        $hemis[] = hsHemis($w, hsDate($w, 1), 3, 2, '13');
    }
    $plan = [];
    foreach ([1, 2, 3, 4, 5, 6, 7, 8, 9, 11] as $w) {
        $plan[] = hsPlan($w, 1, 3, 2, 'A');
    }
    $u = hsWeekly(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'ktr' => array_fill(0, 12, ['13' => 2]),
        'hemis' => $hemis, 'plan' => $plan, 'until_week' => 12, 'until_date' => hsDate(12, 7)]);
    // Guruh 10-hafta seshanba boshqa darsda — 9-haftada mos kun yo'q (faqat shu fanning dushanbasi).
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5)))->planGroup([$u], [], hsWorkDates(8, 12), [hsDate(10, 2) => [7 => true]])[0];

    expect($r['balance_weeks'])->toBe(['A' => 9])
        ->and($r['unplaced_weeks'])->toBe(['A' => [10, 12]])
        // 1–9: KTR 9, HEMIS 7, LMS 9-hafta 1 → 6-hafta kamomadi; balanslash darsi 10-hafta seshanbaga — chegaradan keyin, hozir yozilmaydi.
        ->and($r['before']['count_diff']['A'])->toBe(1)
        ->and($r['fixes'])->toBe([['type' => 'balance_day', 'date' => hsDate(10, 2), 'week' => 6, 'later' => true,
            'text' => "Balanslash: amaliy qism soni yetmaydi (6-hafta darsi o'rniga): " . hsDate(10, 2) . " ga 2 soat qo'shildi"]]);
    $byDate = [];
    foreach ($r['actions'] as $a) {
        $byDate[$a['date']] = $a['op'] . (!empty($a['balance_later']) ? ' later' : '');
    }
    // 9- va 11-hafta LMS darslari — qo'shiladi (11-hafta balanslanmaydi, LMS'dagidek); balanslash darsi — belgilangan (hozir yozilmaydi).
    expect($byDate)->toBe([hsDate(9, 1) => 'create', hsDate(10, 2) => 'create later', hsDate(11, 1) => 'create']);
});

it('balans chegarasi: joriy hafta LMS\'da joylanmagan — H = o\'tgan hafta; shu haftaning o\'tgan HEMIS darsi balansga kirmaydi va unga amal yo\'q', function () {
    // KTR 1–4 amaliy 2 soat; LMS: faqat 1–2-hafta; HEMIS: 1–3-hafta dushanbasi (3-hafta — o'tgan, LMS'da joylanmagan), 4-hafta (kelgusi).
    $u = hsWeekly(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'ktr' => array_fill(0, 4, ['13' => 2]),
        'until_week' => 4, 'until_date' => hsDate(4, 7), 'current_week' => 3,
        'hemis' => [hsHemis(1, hsDate(1, 1), 3, 2, '13'), hsHemis(2, hsDate(2, 1), 3, 2, '13'), hsHemis(3, hsDate(3, 1), 3, 2, '13'),
            hsHemis(4, hsDate(4, 1), 3, 2, '13')],
        'plan' => [hsPlan(1, 1, 3, 2, 'A'), hsPlan(2, 1, 3, 2, 'A')]]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(3, 3)))->planGroup([$u], [], hsWorkDates(3, 4))[0];

    expect($r['balance_weeks'])->toBe(['A' => 2])
        ->and($r['unplaced_weeks'])->toBe(['A' => [3, 4]])
        ->and($r['before']['past_count']['A'])->toBe(2)
        ->and($r['before']['count_diff']['A'])->toBe(0)
        ->and($r['fixes'])->toBe([])
        ->and(array_map(fn ($a) => [$a['op'], $a['date'], $a['reason_code']], $r['actions']))->toBe([['delete', hsDate(4, 1), 'lms_unplaced']]);
});

it('o\'chirish sababi: hafta ichida boshqa kunga ko\'chgan dars — "LMS rejasida yo\'q" (ortiqcha emas)', function () {
    $u = hsWeekly(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'ktr' => array_fill(0, 2, ['13' => 2]), 'current_week' => 1,
        'until_week' => 2, 'until_date' => hsDate(2, 7),
        'hemis' => [hsHemis(1, hsDate(1, 1), 3, 2, '13'), hsHemis(2, hsDate(2, 1), 3, 2, '13')],
        'plan' => [hsPlan(1, 1, 3, 2, 'A'), hsPlan(2, 3, 3, 2, 'A')]]);
    $r = (new SchedulePlanner(hsSlots(), hsDate(1, 6)))->planGroup([$u], [], hsWorkDates(2, 2))[0];
    $del = array_values(array_filter($r['actions'], fn ($a) => $a['op'] === 'delete'));

    expect(array_map(fn ($a) => [$a['date'], $a['reason_code'], $a['reason']], $del))->toBe([[hsDate(2, 1), 'not_in_plan', "LMS rejasida yo'q"]])
        ->and(array_values(array_column(array_filter($r['actions'], fn ($a) => $a['op'] === 'create'), 'date')))->toBe([hsDate(2, 3)]);
});

it('balanslash darsi vaqti: odatdagi boshlanish HEMIS para vaqtiga to\'g\'ri kelmasa — guruhning o\'sha kundagi birinchi darsi vaqtida', function () {
    // Amaliy odatda 09:10 dan (yarim-slot 2) — 2 soatlik dars 09:10–10:40 HEMIS'da yo'q; guruh shanba 10:00 dan keladi.
    $hemis = [];
    $plan = [];
    foreach ([1, 2, 3, 4, 5, 7, 8] as $w) {
        $hemis[] = hsHemis($w, hsDate($w, 1), 2, 2, '13');
    }
    for ($w = 1; $w <= 9; $w++) {
        $plan[] = hsPlan($w, 1, 2, 2, 'A');
    }
    $u = hsWeekly(['codes' => ['13' => 'A'], 'code_names' => ['13' => 'Amaliy'], 'ktr' => array_fill(0, 9, ['13' => 2]), 'hemis' => $hemis, 'plan' => $plan]);
    $pairs = ['08:30-09:50' => true, '10:00-11:20' => true, '12:00-13:20' => true, '13:30-14:50' => true];
    $sat = hsDate(8, 6);
    $r = (new SchedulePlanner(hsSlots(), hsDate(8, 5), $pairs))->planGroup([$u], [], hsWorkDates(8, 9), [$sat => [3 => true, 4 => true]])[0];
    $bal = array_values(array_filter($r['actions'], fn ($a) => !empty($a['balance'])));

    expect(array_map(fn ($a) => [$a['date'], $a['start'], $a['end'], $a['blocked']], $bal))->toBe([[$sat, '10:00', '11:20', []]]);
});
