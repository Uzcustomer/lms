<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableCard;

/**
 * Klaster kaliti fakultetlarni ajratishi kerak.
 *
 * Guruh nomlari fakultetlar bo'ylab takrorlanadi ("25-01a (o'z)" ham 1-son, ham
 * 2-son davolashda bor). Kalit fakultetsiz bo'lganda ikki fakultetning bir xil
 * nomli guruhi bitta dars deb qabul qilinar, "bir kunga / ketma-ket" cheklovi
 * ular orasida ishlab, fan uzilib qolardi.
 */
function spreadKeyOf(array $attrs): string
{
    $m = new ReflectionMethod(TimetableController::class, 'spreadKey');
    $m->setAccessible(true);

    return $m->invoke(new TimetableController(), new TimetableCard(array_merge([
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 2,
        'oqim_label' => '1-oqim',
        'training_type' => 'practice',
        'group_name' => '25-01a',
        'subject_name' => 'Gigiyena',
    ], $attrs)));
}

it('bir xil nomli guruhlarni fakultet bo\'yicha ajratadi', function () {
    expect(spreadKeyOf(['faculty_name' => '1-son davolash']))
        ->not->toBe(spreadKeyOf(['faculty_name' => '2-son davolash']));
});

it('bir fakultetdagi bir guruh + bir fan uchun kalit bir xil', function () {
    expect(spreadKeyOf([]))->toBe(spreadKeyOf([]));
});

it('ma\'ruzani oqim, amaliyni guruh bo\'yicha ajratadi', function () {
    $lecture = spreadKeyOf(['training_type' => 'lecture', 'group_name' => null]);
    $practice = spreadKeyOf([]);

    expect($lecture)->toContain('L1-oqim')
        ->and($practice)->toContain('P25-01a')
        ->and($lecture)->not->toBe($practice);
});

it('oqimlari har xil ma\'ruzalar alohida klaster bo\'ladi', function () {
    expect(spreadKeyOf(['training_type' => 'lecture', 'oqim_label' => '1-oqim']))
        ->not->toBe(spreadKeyOf(['training_type' => 'lecture', 'oqim_label' => '2-oqim']));
});
