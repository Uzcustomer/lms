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
    Schema::create('absence_excuses', function ($t) {
        $t->id();
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

    DB::table('absence_excuses')->insert(['id' => 5, 'status' => 'approved']);
    ExcuseGradeOpening::create([
        'absence_excuse_id' => 5, 'student_hemis_id' => '77', 'subject_id' => '500', 'date_from' => '2026-10-21',
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

it('ignores openings whose LMS excuse is no longer approved', function (?string $status) {
    $status === null
        ? DB::table('absence_excuses')->delete()
        : DB::table('absence_excuses')->update(['status' => $status]);

    expect(ExcuseGradeOpening::isOpenNbSlot('77', '500', '11', '2026-10-22', '1'))->toBeFalse();
})->with(['pending' => 'pending', 'rejected' => 'rejected', 'deleted' => null]);

it('lets teachers grade excused cells only on levels set to teacher retake', function () {
    Schema::create('semesters', function ($t) {
        $t->id();
        $t->string('code');
        $t->string('level_code');
    });
    Schema::create('deadlines', function ($t) {
        $t->id();
        $t->string('level_code');
        $t->integer('deadline_days')->default(3);
        $t->boolean('retake_by_test_markazi')->default(false);
        $t->boolean('retake_by_oqituvchi')->default(false);
        $t->timestamps();
    });
    DB::table('semesters')->insert([['code' => '15', 'level_code' => '13'], ['code' => '17', 'level_code' => '14'], ['code' => '19', 'level_code' => '15']]);
    DB::table('deadlines')->insert([
        ['level_code' => '13', 'retake_by_test_markazi' => true, 'retake_by_oqituvchi' => false],
        ['level_code' => '14', 'retake_by_test_markazi' => false, 'retake_by_oqituvchi' => true],
    ]);

    expect(\App\Models\Deadline::teacherCanRetake('15'))->toBeFalse()
        ->and(\App\Models\Deadline::teacherCanRetake('17'))->toBeTrue()
        ->and(\App\Models\Deadline::teacherCanRetake('19'))->toBeFalse()
        ->and(\App\Models\Deadline::teacherCanRetake(null))->toBeFalse();
});
