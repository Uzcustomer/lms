<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(Tests\TestCase::class);

beforeEach(function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.database' => ':memory:',
        'database.connections.sqlite.foreign_key_constraints' => true,
    ]);
    DB::purge('sqlite');
    DB::reconnect('sqlite');

    Schema::create('timetable_boards', function (Blueprint $table) {
        $table->id();
        $table->string('name');
        $table->string('academic_year')->nullable();
        $table->string('semester_parity')->nullable();
        $table->unsignedInteger('days')->default(6);
        $table->unsignedInteger('pairs_per_day')->default(12);
        $table->unsignedInteger('weeks')->default(15);
        $table->json('bell_schedule')->nullable();
        $table->json('day_names')->nullable();
        $table->json('settings')->nullable();
        $table->timestamps();
    });
    Schema::create('timetable_grid_settings', function (Blueprint $table) {
        $table->id();
        $table->foreignId('board_id');
        $table->string('faculty_name')->nullable();
        $table->string('specialty_name');
        $table->unsignedInteger('course');
        $table->unsignedInteger('days');
        $table->unsignedInteger('pairs_per_day');
        $table->unsignedInteger('weeks');
        $table->timestamps();
    });
    Schema::create('timetable_cards', function (Blueprint $table) {
        $table->id();
        $table->foreignId('board_id');
        $table->string('faculty_name')->nullable();
        $table->string('specialty_name');
        $table->unsignedInteger('course');
        $table->string('oqim_label')->nullable();
        $table->string('lang')->nullable();
        $table->string('training_type');
        $table->string('group_name')->nullable();
        $table->json('group_names')->nullable();
        $table->string('subject_name');
        $table->string('kafedra_name')->nullable();
        $table->unsignedInteger('students')->default(0);
        $table->unsignedBigInteger('teacher_id')->nullable();
        $table->string('teacher_name')->nullable();
        $table->string('auditorium_code')->nullable();
        $table->string('auditorium_name')->nullable();
        $table->unsignedInteger('day')->nullable();
        $table->unsignedInteger('pair')->nullable();
        $table->unsignedInteger('start_half')->default(0);
        $table->unsignedInteger('len_half')->default(2);
        $table->unsignedInteger('weeks')->default(15);
        $table->timestamps();
    });
    Schema::create('timetable_card_overrides', function (Blueprint $table) {
        $table->id();
        $table->foreignId('card_id');
        $table->unsignedInteger('week');
        $table->unsignedInteger('day')->nullable();
        $table->unsignedInteger('pair')->nullable();
        $table->boolean('cancelled')->default(false);
        $table->timestamps();
    });
});

function autoFlowCard(TimetableBoard $board, array $attributes, ?array $activeWeeks = null): TimetableCard
{
    $card = TimetableCard::create(array_merge([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'oqim_label' => '1-oqim',
        'lang' => 'uz',
        'students' => 15,
        'len_half' => 2,
        'weeks' => 10,
    ], $attributes));

    if ($activeWeeks !== null) {
        foreach (array_diff(range(1, 10), $activeWeeks) as $week) {
            DB::table('timetable_card_overrides')->insert([
                'card_id' => $card->id,
                'week' => $week,
                'cancelled' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    return $card;
}

test('fan amaliy guruhlari keyingi fan joylashishidan oldin ma\'ruza ketiga rezerv qilinadi', function () {
    $board = TimetableBoard::create([
        'name' => 'Test',
        'academic_year' => '2026-2027',
        'days' => 2,
        'pairs_per_day' => 8,
        'weeks' => 10,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'days' => 2,
        'pairs_per_day' => 8,
        'weeks' => 10,
    ]);

    $odd = [1, 3, 5, 7, 9];
    $even = [2, 4, 6, 8, 10];
    $lecture = autoFlowCard($board, [
        'training_type' => 'lecture',
        'group_names' => ['1K-01a', '1K-01b'],
        'subject_name' => 'Odam anatomiyasi',
        'weeks' => 5,
        'teacher_id' => 1,
    ], $odd);

    $practices = [];
    foreach ([['1K-01a', 2], ['1K-01b', 3]] as [$group, $teacher]) {
        $practices[$group]['stand_in'] = autoFlowCard($board, [
            'training_type' => 'practice',
            'group_name' => $group,
            'oqim_label' => 'eski-oqim',
            'subject_name' => 'Odam anatomiyasi',
            'weeks' => 5,
            'teacher_id' => $teacher,
        ], $even);
        $practices[$group]['weekly'] = autoFlowCard($board, [
            'training_type' => 'practice',
            'group_name' => $group,
            'oqim_label' => 'eski-oqim',
            'subject_name' => 'Odam anatomiyasi',
            'teacher_id' => $teacher,
        ]);
    }

    $otherLesson = autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => "O'zbek/Rus tili",
        'teacher_id' => 8,
    ]);

    (new TimetableController)->autoPlace(
        Request::create('/', 'POST', ['reset' => true]),
        $board
    );

    $lecture = $lecture->fresh();
    expect($lecture->day)->not->toBeNull();
    foreach ($practices as $cards) {
        expect($cards['stand_in']->fresh()->day)->toBe($lecture->day)
            ->and($cards['stand_in']->fresh()->pair)->toBe($lecture->pair)
            ->and($cards['weekly']->fresh()->day)->toBe($lecture->day)
            ->and($cards['weekly']->fresh()->pair)->toBe($lecture->pair + $lecture->len_half);
    }
    expect([$otherLesson->fresh()->day, $otherLesson->fresh()->pair])
        ->not->toBe([$lecture->day, $lecture->pair + $lecture->len_half]);
});

test('fiks dars sabab ma\'ruza ketiga sig\'magan amaliy boshqa kunga yuborilmaydi', function () {
    $board = TimetableBoard::create([
        'name' => 'Test',
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
        'settings' => ['pair_same_day' => true, 'pair_consecutive' => true],
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
    ]);

    // Qo'lda/fiks joylangan boshqa fan avtomatik joylashda ko'chirilmaydi.
    autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Fiks fan',
        'day' => 1,
        'pair' => 3,
    ]);
    $lecture = autoFlowCard($board, [
        'training_type' => 'lecture',
        'group_names' => ['1K-01a'],
        'subject_name' => 'Odam anatomiyasi',
        'weeks' => 5,
    ], [1, 3, 5, 7, 9]);
    $practice = autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Odam anatomiyasi',
    ]);

    (new TimetableController)->autoPlace(Request::create('/', 'POST'), $board);

    expect($lecture->fresh()->pair)->toBe(1)
        ->and($practice->fresh()->day)->toBeNull()
        ->and($practice->fresh()->pair)->toBeNull();
});

test('joylashmagan karta diagnostikasi guruh bandligini ko\'rsatadi', function () {
    $board = TimetableBoard::create([
        'name' => 'Test',
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
    ]);
    autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Band fan',
        'day' => 1,
        'pair' => 1,
        'len_half' => 4,
    ]);
    $target = autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Diagnostika fan',
        'len_half' => 2,
    ]);

    $response = (new TimetableController)->placementDiagnostics(Request::create('/', 'GET'), $board, $target);
    $payload = $response->getData(true);

    expect($payload['status'])->toBe('unplaced')
        ->and($payload['primary_code'])->toBe('group_busy')
        ->and($payload['free_slots'])->toBe(0)
        ->and(collect($payload['details'])->pluck('code'))->toContain('group_busy');
});

test('diagnostika ma\'ruzadan keyingi majburiy slot bloklanganini ajratadi', function () {
    $board = TimetableBoard::create([
        'name' => 'Test',
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
    ]);
    TimetableGridSetting::create([
        'board_id' => $board->id,
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'days' => 1,
        'pairs_per_day' => 4,
        'weeks' => 10,
    ]);
    autoFlowCard($board, [
        'training_type' => 'lecture',
        'group_names' => ['1K-01a'],
        'subject_name' => 'Odam anatomiyasi',
        'day' => 1,
        'pair' => 1,
        'weeks' => 10,
    ]);
    autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Boshqa fan',
        'day' => 1,
        'pair' => 3,
    ]);
    $target = autoFlowCard($board, [
        'training_type' => 'practice',
        'group_name' => '1K-01a',
        'subject_name' => 'Odam anatomiyasi',
    ]);

    $response = (new TimetableController)->placementDiagnostics(Request::create('/', 'GET'), $board, $target);
    $payload = $response->getData(true);

    expect($payload['primary_code'])->toBe('chain_slot_blocked')
        ->and($payload['checked_slots'])->toBe(1)
        ->and(collect($payload['details'])->pluck('code'))->toContain('group_busy');
});
