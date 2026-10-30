<?php

use App\Models\ExcuseGradeOpening;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN ga yuborilgandan keyin ham faqat sababli ariza ochgan NB katagiga baho qo'yiladi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');
    Schema::create('excuse_grade_openings', function ($t) {
        $t->id();
        $t->unsignedBigInteger('absence_excuse_id')->nullable();
        $t->string('student_hemis_id');
        $t->string('subject_id');
        $t->date('date_from');
        $t->date('date_to');
        $t->dateTime('deadline');
        $t->string('status');
        $t->timestamps();
    });
    Schema::create('student_grades', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
        $t->string('subject_id');
        $t->string('semester_code');
        $t->dateTime('lesson_date');
        $t->string('lesson_pair_code');
        $t->integer('training_type_code');
        $t->string('reason')->nullable();
        $t->double('grade')->nullable();
        $t->timestamps();
    });

    ExcuseGradeOpening::create([
        'student_hemis_id' => '77', 'subject_id' => '500', 'date_from' => '2026-10-21',
        'date_to' => '2026-10-23', 'deadline' => now()->addDays(3), 'status' => 'active',
    ]);
    $row = ['student_hemis_id' => '77', 'subject_id' => '500', 'semester_code' => '11', 'training_type_code' => 13];
    DB::table('student_grades')->insert([
        $row + ['lesson_date' => '2026-10-22 00:00:00', 'lesson_pair_code' => '1', 'reason' => 'absent', 'grade' => null],
        $row + ['lesson_date' => '2026-10-22 00:00:00', 'lesson_pair_code' => '2', 'reason' => null, 'grade' => 72],
        $row + ['lesson_date' => '2026-10-24 00:00:00', 'lesson_pair_code' => '1', 'reason' => 'absent', 'grade' => null],
    ]);
});

it('opens only ungraded NB cells inside an active excuse opening', function () {
    expect(ExcuseGradeOpening::isOpenNbSlot('77', '500', '11', '2026-10-22', '1'))->toBeTrue()
        ->and(ExcuseGradeOpening::isOpenNbSlot('77', '500', '11', '2026-10-22', '2'))->toBeFalse()
        ->and(ExcuseGradeOpening::isOpenNbSlot('77', '500', '11', '2026-10-24', '1'))->toBeFalse()
        ->and(ExcuseGradeOpening::isOpenNbSlot('78', '500', '11', '2026-10-22', '1'))->toBeFalse();
});

it('closes once the excuse opening deadline passes', function () {
    ExcuseGradeOpening::query()->update(['deadline' => now()->subMinute()]);

    expect(ExcuseGradeOpening::isOpenNbSlot('77', '500', '11', '2026-10-22', '1'))->toBeFalse();
});
