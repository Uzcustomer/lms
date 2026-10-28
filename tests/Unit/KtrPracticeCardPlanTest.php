<?php

use App\Http\Controllers\Admin\TimetableController;

function ktrCardPlan(array $prc, int $weeks): ?array
{
    $m = new ReflectionMethod(TimetableController::class, 'ktrPracticeCardPlan');
    $m->setAccessible(true);
    $ctl = (new ReflectionClass(TimetableController::class))->newInstanceWithoutConstructor();

    return $m->invoke($ctl, array_map(fn ($h) => ['lec' => 0, 'prc' => $h], $prc), $weeks);
}

it('KTR haftalik amaliy soatidan 2 soatlik qatlam kartalar tuziladi (TKK: 4 soatdan 7 hafta + 2 soat)', function () {
    // toq haftalarda 4 soat (oxirgisi 2), juftlarda 0
    $prc = [4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 4, 0, 2];
    expect(ktrCardPlan($prc, 15))->toBe([[2, 8], [2, 7]]);
    $sum = array_sum(array_map(fn ($c) => $c[0] * $c[1], ktrCardPlan($prc, 15)));
    expect($sum)->toBe(array_sum($prc));
});

it('toq soatli haftalar 1 soatlik karta bilan to\'ldiriladi; amaliysiz fan — null', function () {
    expect(ktrCardPlan([2, 3, 2, 3, 1], 5))->toBe([[2, 4], [1, 1], [1, 2]])
        ->and(ktrCardPlan([0, 0, 0], 3))->toBeNull()
        ->and(ktrCardPlan([4, 2, 4, 2, 4], 3))->toBe([[2, 3], [2, 2]]);
});
