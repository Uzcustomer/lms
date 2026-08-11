<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableCard;

/**
 * "Bitta fanning paralarini bir kunga / ketma-ket qo'yish" sozlamalari qattiq
 * cheklov sifatida ishlashini tekshiradi: bir darsning kartalari bo'linmasligi,
 * sig'masa esa joylashmagan bo'lib qolishi kerak.
 */
function clusterPlace(array $segs, array $opts = [])
{
    $m = new ReflectionMethod(TimetableController::class, 'clusterPlacement');
    $m->setAccessible(true);

    return $m->invoke(
        new TimetableController(),
        $segs,
        $opts['days'] ?? 6,
        $opts['pairs'] ?? 12,
        $opts['scope'] ?? 'Davolash|2',
        $opts['group_busy'] ?? [],
        $opts['teacher_busy'] ?? [],
        $opts['room_busy'] ?? [],
        $opts['consecutive'] ?? true,
        $opts['anchors'] ?? [],
        $opts['penalty'] ?? fn(int $d, int $p) => ($d - 1) * 100 + ($p - 1) * 0.2
    );
}

function clusterSeg(string $group, int $lenHalf, array $pool = [], ?int $teacherId = 1): array
{
    $card = new TimetableCard();
    $card->specialty_name = 'Davolash';
    $card->course = 2;
    $card->training_type = 'practice';
    $card->group_name = $group;
    $card->subject_name = 'Biokimyo';
    $card->len_half = $lenHalf;
    $card->weeks = 15;
    $card->teacher_id = $teacherId;

    return [
        'card' => $card,
        'len' => $card->lenHalf(),
        'groups' => $card->occupiedGroups(),
        'teacher' => $card->teacher_id,
        'room_required' => (bool) $pool,
        'pool' => $pool,
    ];
}

/** Guruhni har kuni 3-4 va 7-8 yarim-slotlarda band qiladi (4 ta ketma-ket joy qolmaydi). */
function busyEveryOtherPair(string $scope, string $group, int $days = 6, int $pairs = 12): array
{
    $busy = [];
    for ($d = 1; $d <= $days; $d++) {
        for ($p = 3; $p <= $pairs; $p += 4) {
            $busy[$scope . '|' . $d . '|' . $p][$group] = true;
            $busy[$scope . '|' . $d . '|' . ($p + 1)][$group] = true;
        }
    }
    return $busy;
}

test('4 soatlik dars ikki kartasi bir kunda va ketma-ket joylashadi', function () {
    $spot = clusterPlace([clusterSeg('p25-01a', 2), clusterSeg('p25-01a', 2)]);

    expect($spot)->toHaveCount(2);
    expect($spot[1]['day'])->toBe($spot[0]['day']);
    expect($spot[1]['pair'])->toBe($spot[0]['pair'] + 2);
});

test('yaxlit blok sig\'masa karta joylashmagan qoladi (kunlarga bo\'linmaydi)', function () {
    $spot = clusterPlace(
        [clusterSeg('p25-01a', 2), clusterSeg('p25-01a', 2)],
        ['group_busy' => busyEveryOtherPair('Davolash|2', 'p25-01a')]
    );

    expect($spot)->toBeNull();
});

test('faqat "bir kunga" yoqilganda paralar bo\'shliq bilan ham bitta kunga tushadi', function () {
    $spot = clusterPlace(
        [clusterSeg('p25-01a', 2), clusterSeg('p25-01a', 2)],
        ['group_busy' => busyEveryOtherPair('Davolash|2', 'p25-01a'), 'consecutive' => false]
    );

    expect($spot)->toHaveCount(2);
    expect($spot[1]['day'])->toBe($spot[0]['day']);
});

test('allaqachon joylashgan para yoniga tushadi', function () {
    // 4-kun, 5–6 yarim-slot shu darsning oldingi parasi bilan band
    $spot = clusterPlace([clusterSeg('p25-02a', 2)], ['anchors' => [4 => [[5, 6]]]]);

    expect($spot)->toHaveCount(1);
    expect($spot[0]['day'])->toBe(4);
    expect($spot[0]['pair'])->toBeIn([3, 7]);
});

test('bo\'sh auditoriya bo\'lmasa blok joylashtirilmaydi', function () {
    $room = (object) ['code' => 'A1', 'name' => '№ A1', 'volume' => 30];
    $roomBusy = [];
    for ($d = 1; $d <= 6; $d++) {
        for ($p = 1; $p <= 12; $p++) {
            $roomBusy['A1|' . $d . '|' . $p] = true;
        }
    }

    $spot = clusterPlace(
        [clusterSeg('p25-01a', 2, [$room]), clusterSeg('p25-01a', 2, [$room])],
        ['room_busy' => $roomBusy]
    );

    expect($spot)->toBeNull();
});

test('o\'qituvchi band kuni o\'tkazib yuboriladi', function () {
    $teacherBusy = [];
    for ($p = 1; $p <= 12; $p++) {
        $teacherBusy['1|1|' . $p] = true;   // 1-kun to'liq band
    }

    $spot = clusterPlace(
        [clusterSeg('p25-01a', 2), clusterSeg('p25-01a', 2)],
        ['teacher_busy' => $teacherBusy]
    );

    expect($spot)->toHaveCount(2);
    expect($spot[0]['day'])->toBe(2);
});
