<?php

use App\Http\Controllers\Admin\TimetableController;

// "Ko'rsatish" uchun tuzilgan ma'lumot (TimetableConflictFocus): guruhlar qamrovi bilan, "A + B" — har biri, takrorlanmaydi.
function cfocus(string $method, ...$args)
{
    $ctl = app(TimetableController::class);

    return (new ReflectionMethod($ctl, $method))->invoke($ctl, ...$args);
}

it('ko\'rsatish: karta guruhlari — "A + B" umumiy karta va oqim ro\'yxati har biri alohida, qamrovi bilan', function () {
    $g = fn ($name, $fac = 'F1', $spec = 'davolash ishi', $course = 2) => ['group' => $name, 'faculty' => $fac, 'specialty' => $spec, 'course' => $course];
    expect(cfocus('focusCardGroups', (object) ['group_name' => 'd2-03a + d2-03b', 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => '2']))
        ->toBe([$g('d2-03a'), $g('d2-03b')])
        ->and(cfocus('focusCardGroups', (object) ['group_names' => '["d2-01a","d2-01b"]', 'group_name' => 'x', 'faculty_name' => 'F1',
            'specialty_name' => 'davolash ishi', 'course' => 2]))->toBe([$g('d2-01a'), $g('d2-01b')])
        ->and(cfocus('focusCardGroups', (object) ['group_name' => null, 'faculty_name' => 'F1', 'specialty_name' => 'S', 'course' => 2]))->toBe([]);
    // Sikl birligi: qator nomi ("A + B") va a'zolari; bandlik yozuvi — haftalik "fakultet|yo'nalish|kurs|guruh" yoki sikl birligi.
    expect(cfocus('focusUnitGroups', ['group' => 'd22-05a + d22-05b', 'members' => ['d22-05c'], 'faculty' => 'F1', 'specialty' => 'davolash ishi', 'course' => 5]))
        ->toBe([$g('d22-05a', course: 5), $g('d22-05b', course: 5), $g('d22-05c', course: 5)])
        ->and(cfocus('focusEntryGroups', ['kind' => 'weekly', 'groups' => ['F2|Pediatriya|3|p3-01a + p3-01b', 'yaroqsiz']], []))
        ->toBe([$g('p3-01a', 'F2', 'Pediatriya', 3), $g('p3-01b', 'F2', 'Pediatriya', 3)])
        ->and(cfocus('focusEntryGroups', ['kind' => 'cycle', 'unit' => 4], [4 => ['group' => 'd22-05a', 'faculty' => 'F1', 'specialty' => 'davolash ishi', 'course' => 5]]))
        ->toBe([$g('d22-05a', course: 5)])
        ->and(cfocus('focusEntryGroups', ['kind' => 'cycle', 'unit' => 9], []))->toBe([]);
});

it('ko\'rsatish: yagona ko\'rinish — guruhlar va kartalar takrorlanmaydi, sanalar tartiblangan, bo\'sh maydonlar null', function () {
    $show = cfocus('focusShow', ['kind' => 'room_busy', 'room_code' => 'R215', 'teacher_id' => 0,
        'groups' => [['group' => 'd2-01a', 'faculty' => 'F1', 'specialty' => 'Davolash ishi', 'course' => 2],
            ['group' => 'D2-01A', 'faculty' => 'f1', 'specialty' => 'davolash ishi', 'course' => 2],
            ['group' => 'd2-01a', 'faculty' => 'F1', 'specialty' => 'Davolash ishi', 'course' => 3]],
        'dates' => ['2026-09-18', '2026-09-16', '2026-09-17'], 'hour_from' => '3', 'card_ids' => ['7', 5, 7, null], 'view' => 'x']);
    expect($show)->toBe(['kind' => 'room_busy', 'teacher_id' => null, 'teacher_name' => null, 'room_code' => 'R215',
        'groups' => [['group' => 'd2-01a', 'faculty' => 'F1', 'specialty' => 'Davolash ishi', 'course' => 2],
            ['group' => 'd2-01a', 'faculty' => 'F1', 'specialty' => 'Davolash ishi', 'course' => 3]],
        'subject' => null, 'dates' => ['2026-09-16', '2026-09-18'], 'hour_from' => 3, 'hour_to' => null, 'card_ids' => [7, 5],
        'view' => 'week', 'week' => null])
        ->and(cfocus('focusShow', ['view' => 'cycle', 'dates' => []])['dates'])->toBe([])
        ->and(cfocus('focusShow', ['view' => 'cycle'])['view'])->toBe('cycle');
});

it('ko\'rsatish: Takliflar to\'qnashuvi — sana to\'sib turgan darsning o\'z haftasidan, hafta — kartaning o\'z haftasi (kalendarlar farq qilsa ham)', function () {
    $board = \App\Models\TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    $mk = fn (string $fac, string $g) => \App\Models\TimetableCard::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'S',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Fan', 'group_name' => $g, 'teacher_id' => 5, 'teacher_name' => 'USTOZ']);
    $c = $mk('F1', 'a-01');
    $o = $mk('F2', 'b-01');
    // To'sib turgan dars o'z kalendarida 8-hafta (02.11 dushanba) seshanbasi; karta uchun bu — 9-hafta.
    $show = cfocus('focusFromClash', $c, ['card' => $o, 'week' => 8, 'day' => 2, 'pair' => 5, 'room' => null,
        'errors' => ["O'qituvchi band: b-01"]], $board, 9);
    expect($show)->toMatchArray(['kind' => 'teacher_busy', 'teacher_id' => 5, 'teacher_name' => 'USTOZ', 'dates' => ['2026-11-03', '2026-11-03'],
        'hour_from' => 5, 'hour_to' => 7, 'card_ids' => [$c->id, $o->id], 'view' => 'week', 'week' => 9])
        ->and(array_column($show['groups'], 'group'))->toBe(['a-01', 'b-01']);
});
