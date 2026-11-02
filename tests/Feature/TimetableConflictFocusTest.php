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
