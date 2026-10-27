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

function hsUnit(array $over): array
{
    return array_merge([
        'key' => 'g1|s1', 'subject_id' => 1, 'subject_name' => 'Fan', 'base' => 'fan',
        'codes' => ['11' => 'L', '13' => 'A', '12' => 'A'],
        'code_names' => ['11' => 'Ma\'ruza', '13' => 'Amaliy', '12' => 'Laboratoriya'],
        'ktr' => [], 'current_week' => 5, 'hemis' => [], 'plan' => [],
        'fallback_employee' => [99, 'MUDIR'], 'fallback_room' => ['KM', 'Kafedra mudiri xonasi'],
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

it('soat to\'g\'ri, soni ortiq bo\'lsa — kunlarni birlashtiradi', function () {
    // KTR: 2 ta ma'ruza (4 soat). HEMIS: 1-hafta 2 kunga 1 soatdan bo'lib qo'yilgan (soni 2).
    $u = hsUnit([
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
