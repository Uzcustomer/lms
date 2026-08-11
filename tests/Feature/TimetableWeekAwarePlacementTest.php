<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

/**
 * Hech qachon bir haftaga tushmaydigan darslar bitta slotni (va bitta xonani)
 * bo'lishishi kerak: toq haftalardagi ma'ruza va juft haftalardagi boshqa
 * ma'ruza, ma'ruza va uni almashtiruvchi amaliy va h.k. Har haftada o'tiladigan
 * darslar esa avvalgidek to'qnashadi.
 */
const WEEK_TEST_TOTAL = 10;
const WEEK_TEST_ODD = [1, 3, 5, 7, 9];
const WEEK_TEST_EVEN = [2, 4, 6, 8, 10];

function weekTestBoard(): TimetableBoard
{
    DB::table('auditoriums')->insert([
        ['code' => 'R1', 'name' => '№ 101', 'volume' => 40, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
        ['code' => 'R2', 'name' => '№ 102', 'volume' => 40, 'active' => 1, 'created_at' => now(), 'updated_at' => now()],
    ]);

    $board = TimetableBoard::create([
        'name' => 'Test', 'academic_year' => '2026-2027', 'semester' => 'kuzgi',
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => WEEK_TEST_TOTAL,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => WEEK_TEST_TOTAL,
    ]);

    return $board;
}

function weekTestCard(TimetableBoard $board, array $attrs, ?array $activeWeeks = null): TimetableCard
{
    $card = TimetableCard::create(array_merge([
        'board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'oqim_label' => '1-oqim', 'lang' => 'uz',
        'students' => 20, 'len_half' => 2, 'start_half' => 0,
    ], $attrs));

    // assignCardWeeks() kabi: o'tilmaydigan haftalarga "cancelled" yoziladi
    if ($activeWeeks !== null) {
        $rows = [];
        foreach (range(1, WEEK_TEST_TOTAL) as $week) {
            if (!in_array($week, $activeWeeks, true)) {
                $rows[] = ['card_id' => $card->id, 'week' => $week, 'day' => null, 'pair' => null,
                    'cancelled' => true, 'created_at' => now(), 'updated_at' => now()];
            }
        }
        if ($rows) {
            DB::table('timetable_card_overrides')->insert($rows);
        }
    }

    return $card;
}

/** splitPracticeHours() bilan bir xil: soatni 2 soatlik kartalarga bo'ladi. */
function weekTestSplitHours(int $hours): array
{
    $out = [];
    while ($hours >= 2) {
        $out[] = 2;
        $hours -= 2;
    }
    if ($hours === 1) {
        if ($out) {
            $out[count($out) - 1] = 3;
        } else {
            $out[] = 1;
        }
    }
    return $out;
}

function weekTestPlace(TimetableBoard $board): void
{
    (new TimetableController())->autoPlace(
        Request::create('/', 'POST', ['reset' => true, 'assign_rooms' => true]),
        $board
    );
}

test('toq va juft haftalardagi ma\'ruzalar bitta slot va bitta xonani bo\'lishadi', function () {
    $board = weekTestBoard();
    $psixologiya = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Psixologiya', 'weeks' => 5, 'teacher_id' => 2], WEEK_TEST_ODD);
    $gigiyena = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Gigiyena', 'weeks' => 5, 'teacher_id' => 3], WEEK_TEST_EVEN);

    weekTestPlace($board);

    $psixologiya = $psixologiya->fresh();
    $gigiyena = $gigiyena->fresh();

    expect($gigiyena->day)->toBe($psixologiya->day);
    expect($gigiyena->pair)->toBe($psixologiya->pair);
    expect($gigiyena->auditorium_code)->toBe($psixologiya->auditorium_code);
    expect((int) $psixologiya->pair)->toBe(1);   // kun boshiga zichlanadi
});

test('har haftada o\'tiladigan ikki dars baribir bir slotga tushmaydi', function () {
    $board = weekTestBoard();
    $anatomiya = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Anatomiya', 'weeks' => WEEK_TEST_TOTAL, 'teacher_id' => 4]);
    $biokimyo = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Biokimyo', 'weeks' => WEEK_TEST_TOTAL, 'teacher_id' => 5]);

    weekTestPlace($board);

    $anatomiya = $anatomiya->fresh();
    $biokimyo = $biokimyo->fresh();

    expect($anatomiya->day)->not->toBeNull();
    expect($biokimyo->day)->not->toBeNull();
    expect([$biokimyo->day, $biokimyo->pair])->not->toBe([$anatomiya->day, $anatomiya->pair]);
});

test('ma\'ruzasiz haftada ikki amaliy ma\'ruza vaqtidan boshlab yaxlit turadi', function () {
    $board = weekTestBoard();
    $lecture = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Mikrobiologiya', 'weeks' => 5, 'teacher_id' => 1], WEEK_TEST_ODD);
    $weekly = weekTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
        'subject_name' => 'Mikrobiologiya', 'weeks' => WEEK_TEST_TOTAL, 'teacher_id' => 1]);
    $standIn = weekTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
        'subject_name' => 'Mikrobiologiya', 'weeks' => 5, 'teacher_id' => 1], WEEK_TEST_EVEN);

    weekTestPlace($board);

    $lecture = $lecture->fresh();
    $weekly = $weekly->fresh();
    $standIn = $standIn->fresh();

    // Ma'ruzasiz (juft) haftada: almashtiruvchi amaliy ma'ruza o'rnida,
    // har haftalik amaliy uning ketidan — 2 + 2 = 4 soat uzluksiz.
    expect($standIn->day)->toBe($lecture->day);
    expect($standIn->pair)->toBe($lecture->pair);
    expect($weekly->day)->toBe($lecture->day);
    expect($weekly->pair)->toBe($lecture->pair + 2);
    // Ma'ruza va uni almashtiruvchi amaliy bitta xonani bo'lishadi
    expect($standIn->auditorium_code)->toBe($lecture->auditorium_code);
});

/**
 * Soat taqsimoti ixtiyoriy: reja "har haftalik" va "ma'ruzasiz haftalardagi"
 * soatni alohida chelaklarga bo'ladi, har biri 2 soatlik kartalarga bo'linadi.
 * Ma'ruzasiz haftadagi butun amaliy yuk (ikkala chelak) bir kunda, uzluksiz va
 * ma'ruza vaqtidan boshlanishi kerak — 2+2 ham, 2+4 ham, 4+4 ham.
 */
dataset('soat taqsimotlari', [
    'ma\'ruza 2 + amaliy 2 + qo\'shimcha 2' => [2, 2, 2],
    'ma\'ruza 2 + amaliy 4 + qo\'shimcha 2' => [2, 4, 2],
    'ma\'ruza 2 + amaliy 6 + qo\'shimcha 2' => [2, 6, 2],
    'ma\'ruza 2 + amaliy 2 + qo\'shimcha 4' => [2, 2, 4],
    'ma\'ruza 4 + amaliy 4 + qo\'shimcha 4' => [4, 4, 4],
]);

test('ma\'ruzasiz haftadagi amaliy yuk yaxlit va ma\'ruza vaqtidan boshlanadi',
    function (int $lectureHalves, int $weeklyHours, int $standInHours) {
        $board = weekTestBoard();
        $lecture = weekTestCard($board, ['training_type' => 'lecture', 'group_names' => ['g-01a'],
            'subject_name' => 'Mikrobiologiya', 'len_half' => $lectureHalves,
            'weeks' => 5, 'teacher_id' => 1], WEEK_TEST_ODD);

        $practices = [];
        foreach (weekTestSplitHours($weeklyHours) as $len) {
            $practices[] = weekTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
                'subject_name' => 'Mikrobiologiya', 'len_half' => $len,
                'weeks' => WEEK_TEST_TOTAL, 'teacher_id' => 1]);
        }
        foreach (weekTestSplitHours($standInHours) as $len) {
            $practices[] = weekTestCard($board, ['training_type' => 'practice', 'group_name' => 'g-01a',
                'subject_name' => 'Mikrobiologiya', 'len_half' => $len,
                'weeks' => 5, 'teacher_id' => 1], WEEK_TEST_EVEN);
        }

        weekTestPlace($board);
        $lecture = $lecture->fresh();

        // Ma'ruzasiz haftada band bo'ladigan yarim-slotlar
        $slots = [];
        foreach ($practices as $card) {
            $card = $card->fresh();
            expect($card->day)->not->toBeNull();
            expect($card->day)->toBe($lecture->day);
            for ($i = 0; $i < $card->len_half; $i++) {
                $slots[] = (int) $card->pair + $i;
            }
        }
        sort($slots);

        expect($slots)->toHaveCount($weeklyHours + $standInHours);
        expect($slots)->toBe(range($slots[0], $slots[0] + count($slots) - 1));   // uzluksiz
        expect($slots[0])->toBe((int) $lecture->pair);                            // ma'ruza vaqtidan
    }
)->with('soat taqsimotlari');

test('bir oqimdagi amaliy guruhlar ma\'ruzadan keyin parallel joylashadi', function () {
    $board = weekTestBoard();
    $lecture = weekTestCard($board, [
        'training_type' => 'lecture', 'group_names' => ['g-01a', 'g-01b'],
        'subject_name' => 'Odam anatomiyasi', 'weeks' => 5, 'teacher_id' => 1,
    ], WEEK_TEST_ODD);

    $cards = [];
    foreach ([['g-01a', 2], ['g-01b', 3]] as [$group, $teacher]) {
        $cards[$group]['weekly'] = weekTestCard($board, [
            'training_type' => 'practice', 'group_name' => $group,
            'subject_name' => 'Odam anatomiyasi', 'weeks' => WEEK_TEST_TOTAL,
            'teacher_id' => $teacher,
        ]);
        $cards[$group]['stand_in'] = weekTestCard($board, [
            'training_type' => 'practice', 'group_name' => $group,
            'subject_name' => 'Odam anatomiyasi', 'weeks' => 5,
            'teacher_id' => $teacher,
        ], WEEK_TEST_EVEN);
    }

    weekTestPlace($board);
    $lecture = $lecture->fresh();

    foreach ($cards as $groupCards) {
        $weekly = $groupCards['weekly']->fresh();
        $standIn = $groupCards['stand_in']->fresh();
        expect($standIn->day)->toBe($lecture->day)
            ->and($standIn->pair)->toBe($lecture->pair)
            ->and($weekly->day)->toBe($lecture->day)
            ->and($weekly->pair)->toBe($lecture->pair + $lecture->len_half);
    }

    expect($cards['g-01a']['weekly']->fresh()->pair)
        ->toBe($cards['g-01b']['weekly']->fresh()->pair);
});

test('amaliyga joy bo\'lmasa ma\'ruza paket bilan boshqa anchorni tanlaydi', function () {
    $board = weekTestBoard();
    $lecture = weekTestCard($board, [
        'training_type' => 'lecture', 'group_names' => ['g-01a'],
        'subject_name' => 'Odam anatomiyasi', 'weeks' => 5, 'teacher_id' => 1,
    ], WEEK_TEST_ODD);
    $weekly = weekTestCard($board, [
        'training_type' => 'practice', 'group_name' => 'g-01a',
        'subject_name' => 'Odam anatomiyasi', 'weeks' => WEEK_TEST_TOTAL,
        'teacher_id' => 2,
    ]);
    $standIn = weekTestCard($board, [
        'training_type' => 'practice', 'group_name' => 'g-01a',
        'subject_name' => 'Odam anatomiyasi', 'weeks' => 5, 'teacher_id' => 2,
    ], WEEK_TEST_EVEN);

    // Birinchi anchorning keyingi slotida amaliy o'qituvchisi band.
    weekTestCard($board, [
        'training_type' => 'practice', 'group_name' => 'other-group',
        'subject_name' => 'Fiks dars', 'weeks' => WEEK_TEST_TOTAL,
        'teacher_id' => 2, 'day' => 1, 'pair' => 3,
    ]);

    (new TimetableController())->autoPlace(
        Request::create('/', 'POST', ['assign_rooms' => true]),
        $board
    );

    $lecture = $lecture->fresh();
    $weekly = $weekly->fresh();
    $standIn = $standIn->fresh();
    expect($lecture->day)->not->toBeNull()
        ->and($standIn->day)->toBe($lecture->day)
        ->and($standIn->pair)->toBe($lecture->pair)
        ->and($weekly->day)->toBe($lecture->day)
        ->and($weekly->pair)->toBe($lecture->pair + $lecture->len_half);
});
