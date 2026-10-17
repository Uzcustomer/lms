<?php

require_once __DIR__ . '/helpers.php';

use App\Services\ContractCutoffs;

/*
 * Sozlamalar → «Kontrakt to'lov muddatlari — kurs kesimida»: faqat admin / registrator ofisi / buxgalteriya o'zgartiradi.
 */

beforeEach(function () {
    $this->withoutVite();
});

test('registrator ofisi kurs va superkontrakt jadvallarini saqlaydi, bo\'sh qatorlar tashlanadi', function () {
    $registrar = monTeacher(['registrator_ofisi']);

    $this->actingAs($registrar, 'teacher')->post(route('admin.settings.update.contract-cutoffs-by-course'), [
        'courses' => [1 => [['deadline' => '2027-01-01', 'percent' => 50], ['deadline' => '2026-11-01', 'percent' => 25], ['deadline' => '', 'percent' => '']]],
        'super' => [['deadline' => '2026-11-01', 'percent' => 50]],
        'super_types' => ['15'],
    ])->assertRedirect()->assertSessionHas('success');

    $saved = ContractCutoffs::byCourse();
    expect($saved['courses'][1])->toBe([['deadline' => '2026-11-01', 'percent' => 25], ['deadline' => '2027-01-01', 'percent' => 50]])
        ->and($saved['courses'][2])->toBe([])
        ->and($saved['super'])->toBe([['deadline' => '2026-11-01', 'percent' => 50]])
        ->and($saved['super_types'])->toBe(['15'])
        ->and(ContractCutoffs::scheduleFor(2, '11')['label'])->toBe('Umumiy jadval');
});

test('oddiy o\'qituvchi kurs jadvallarini o\'zgartira olmaydi', function () {
    $teacher = monTeacher(['oqituvchi']);

    $this->actingAs($teacher, 'teacher')->post(route('admin.settings.update.contract-cutoffs-by-course'), [
        'courses' => [1 => [['deadline' => '2026-11-01', 'percent' => 25]]],
    ])->assertForbidden();

    expect(ContractCutoffs::byCourse()['courses'][1])->toBe([]);
});

test('sozlamalar sahifasida kurs kesimidagi blok administratorga ko\'rinadi', function () {
    $this->actingAs(monUser(['admin']), 'web')->get(route('admin.settings'))
        ->assertOk()->assertSee('Kontrakt to\'lov muddatlari — kurs kesimida', false);
});

test('keyingi bosqichda kamroq foiz kiritilsa saqlanmaydi', function () {
    $registrar = monTeacher(['registrator_ofisi']);

    $this->actingAs($registrar, 'teacher')->post(route('admin.settings.update.contract-cutoffs-by-course'), [
        'courses' => [2 => [['deadline' => '2026-10-01', 'percent' => 25], ['deadline' => '2027-01-01', 'percent' => 20]]],
    ])->assertRedirect()->assertSessionHas('error');

    expect(ContractCutoffs::byCourse()['courses'][2])->toBe([]);
});
