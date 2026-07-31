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
        'days' => 6, 'pairs_per_day' => 8, 'weeks' => WEEK_TEST_TOTAL,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 8, 'weeks' => WEEK_TEST_TOTAL,
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
