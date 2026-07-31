<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;

uses(RefreshDatabase::class);

/**
 * Ma'ruzani ALMASHTIRUVCHI amaliy (ma'ruzasiz haftalarda o'tiladi) aynan
 * ma'ruzaning slotiga tushishi kerak — shunda jadval har haftada bir xil
 * ko'rinadi. Ma'ruza va uni almashtiruvchi amaliy hech qachon bir haftaga
 * tushmaydi, shuning uchun o'qituvchi/xona bandligi ular orasida to'qnashuv
 * hisoblanmaydi.
 */
function makeAlternatingBoard(int $lectureTeacher, int $practiceTeacher): array
{
    $board = TimetableBoard::create([
        'name' => 'Test', 'academic_year' => '2026-2027', 'semester' => 'kuzgi',
        'days' => 6, 'pairs_per_day' => 6, 'weeks' => 15,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id, 'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 6, 'weeks' => 15,
    ]);

    $make = fn(array $attrs) => TimetableCard::create(array_merge([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 2,
        'oqim_label' => '1-oqim',
        'lang' => 'uz',
        'subject_name' => 'Mikrobiologiya, virusologiya',
        'students' => 20,
        'len_half' => 2,
        'start_half' => 0,
    ], $attrs));

    return [
        'board' => $board,
        // Ma'ruza — 6 hafta
        'lecture' => $make(['training_type' => 'lecture', 'group_names' => ['d1/d25-01a'],
            'weeks' => 6, 'teacher_id' => $lectureTeacher, 'teacher_name' => 'Domla']),
        // Har haftalik amaliy — 15 hafta
        'weekly' => $make(['training_type' => 'practice', 'group_name' => 'd1/d25-01a',
            'weeks' => 15, 'teacher_id' => $practiceTeacher, 'teacher_name' => 'Domla']),
        // Ma'ruzani almashtiruvchi amaliy — 15 - 6 = 9 hafta
        'alternating' => $make(['training_type' => 'practice', 'group_name' => 'd1/d25-01a',
            'weeks' => 9, 'teacher_id' => $practiceTeacher, 'teacher_name' => 'Domla']),
    ];
}

test('almashtiruvchi amaliy ma\'ruza sloti bilan bir xil o\'qituvchida ham o\'sha slotga tushadi', function () {
    $s = makeAlternatingBoard(lectureTeacher: 7, practiceTeacher: 7);

    (new TimetableController())->autoPlace(
        Request::create('/', 'POST', ['reset' => true]),
        $s['board']
    );

    $lecture = $s['lecture']->fresh();
    $alternating = $s['alternating']->fresh();

    expect($lecture->day)->not->toBeNull();
    expect($alternating->day)->toBe($lecture->day);
    expect($alternating->pair)->toBe($lecture->pair);
});

test('har haftalik amaliy ma\'ruza slotiga tortilmaydi', function () {
    $s = makeAlternatingBoard(lectureTeacher: 7, practiceTeacher: 7);

    (new TimetableController())->autoPlace(
        Request::create('/', 'POST', ['reset' => true]),
        $s['board']
    );

    $lecture = $s['lecture']->fresh();
    $weekly = $s['weekly']->fresh();

    expect($weekly->day)->not->toBeNull();
    // Har haftada o'tiladi — ma'ruza bilan bir vaqtda tura olmaydi
    expect([$weekly->day, $weekly->pair])->not->toBe([$lecture->day, $lecture->pair]);
});

test('almashtiruvchi amaliy ma\'ruzaning xonasini qayta ishlata oladi', function () {
    DB::table('auditoriums')->insert([
        'code' => 'A1', 'name' => '№ 101', 'volume' => 25, 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);

    $s = makeAlternatingBoard(lectureTeacher: 7, practiceTeacher: 7);

    (new TimetableController())->autoPlace(
        Request::create('/', 'POST', ['reset' => true, 'assign_rooms' => true]),
        $s['board']
    );

    $lecture = $s['lecture']->fresh();
    $alternating = $s['alternating']->fresh();

    expect($alternating->pair)->toBe($lecture->pair);
    expect($alternating->auditorium_code)->toBe('A1');
});
