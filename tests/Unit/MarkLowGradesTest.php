<?php

use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Chegara 70 ga oshirilgandan keyin joriy semestrdagi 60–69 baholar past baho (otrabotka) bo'ladi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('level_code'), $t->string('semester_code'), $t->string('student_status_code')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('semester_code'), $t->dateTime('lesson_date'),
        $t->double('grade')->nullable(), $t->double('retake_grade')->nullable(), $t->string('reason')->nullable(), $t->string('status')->nullable(),
        $t->dateTime('deadline')->nullable(), $t->integer('training_type_code'), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('deadlines')->insert(['level_code' => '12', 'deadline_days' => 7]);
    DB::table('students')->insert([
        ['hemis_id' => '1', 'level_code' => '12', 'semester_code' => '13', 'student_status_code' => '11'],
        ['hemis_id' => '6', 'level_code' => '16', 'semester_code' => '21', 'student_status_code' => '11'],
    ]);
    Carbon::setTestNow('2026-11-05 10:00:00');
});

afterEach(function () {
    Carbon::setTestNow();
    MarkingSystemScore::forgetLmsMinimumLimit();
});

function lowGrade(array $a): int
{
    return DB::table('student_grades')->insertGetId(array_merge([
        'student_hemis_id' => '1', 'semester_code' => '13', 'lesson_date' => '2026-11-04 08:30:00', 'grade' => 65,
        'reason' => null, 'status' => 'recorded', 'training_type_code' => 13, 'deleted_at' => null,
    ], $a));
}

it('marks current-semester grades below the new limit as low grades', function () {
    $target = lowGrade([]);
    $high = lowGrade(['grade' => 75]);
    $old = lowGrade(['lesson_date' => '2026-10-01 08:30:00']);        // muddati o'tgan
    $lecture = lowGrade(['training_type_code' => 11]);
    $prevSem = lowGrade(['semester_code' => '12']);
    $retaken = lowGrade(['retake_grade' => 80]);
    $already = lowGrade(['grade' => 50, 'reason' => 'low_grade', 'status' => 'pending']);
    $sixth = lowGrade(['student_hemis_id' => '6', 'semester_code' => '21', 'grade' => 2]);

    $this->artisan('grades:mark-low')->assertSuccessful();
    expect(DB::table('student_grades')->where('reason', 'low_grade')->count())->toBe(1);   // hisobot

    $this->artisan('grades:mark-low', ['--force' => true])->assertSuccessful();

    $row = DB::table('student_grades')->find($target);
    expect([$row->reason, $row->status, substr($row->deadline, 0, 10)])->toBe(['low_grade', 'pending', '2026-11-11'])
        ->and(DB::table('student_grades')->where('reason', 'low_grade')->orderBy('id')->pluck('id')->all())->toBe([$target, $already]);
});
