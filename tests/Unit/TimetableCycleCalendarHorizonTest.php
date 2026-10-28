<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use Illuminate\Support\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpKernel\Exception\HttpException;

uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    Schema::create('timetable_subject_settings', function (Blueprint $t) {
        $t->id(); $t->integer('board_id'); $t->string('specialty_name');
        $t->integer('course'); $t->string('subject_name'); $t->string('mode'); $t->integer('cycle_days');
    });
    DB::table('timetable_subject_settings')->insert(['board_id' => 1, 'specialty_name' => 'Pediatriya',
        'course' => 6, 'subject_name' => 'Hospital', 'mode' => 'cycle', 'cycle_days' => 56]);
    $this->board = new TimetableBoard(['days' => 6, 'weeks' => 15]);
    $this->board->id = 1;
    $this->span = ['specialty_name' => 'Pediatriya', 'course' => 6, 'subject_name' => 'Hospital', 'start_index' => 43];
    $this->horizon = function (?int $index = null, int $days = 0) {
        return (new ReflectionMethod(TimetableController::class, 'cycleCalendarWorkDays'))
            ->invoke(new TimetableController, $this->board, Carbon::parse('2026-09-14'),
                ['2026-10-01' => true], $index, $days);
    };
});

it('keeps the full long cycle visible after saving with the legacy settings storage', function () {
    $requested = ($this->horizon)(43, 56);
    $this->board->settings = ['cycle_placements' => [$this->span]];
    expect(($this->horizon)())->toBe($requested)->toBeGreaterThan(90);
});

it('uses the same horizon for database and legacy placements', function () {
    $this->board->settings = ['cycle_placements' => [$this->span]];
    $legacy = ($this->horizon)();
    Schema::create('timetable_cycle_placements', function (Blueprint $t) {
        $t->id(); $t->integer('board_id'); $t->string('specialty_name');
        $t->integer('course'); $t->string('subject_name'); $t->integer('start_index');
    });
    DB::table('timetable_cycle_placements')->insert($this->span + ['board_id' => 1]);
    $this->board->settings = [];
    expect(($this->horizon)())->toBe($legacy);
});

it('rejects oversized and negative requested indices before calendar allocation', function () {
    expect(fn () => ($this->horizon)(PHP_INT_MAX, 56))->toThrow(HttpException::class);
    expect(fn () => ($this->horizon)(-1, 56))->toThrow(HttpException::class);
});

it('ignores unsupported stored indices and supports the maximum cross-year index', function () {
    $this->board->settings = ['cycle_placements' => [array_replace($this->span, ['start_index' => PHP_INT_MAX])]];
    expect(($this->horizon)())->toBe(90);
    expect(($this->horizon)(730, 120))->toBeGreaterThan(730)->toBeLessThan(900);
});
