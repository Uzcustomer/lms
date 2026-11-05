<?php

use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * grades:upgrade-nb-oski: fan (oxirgi dars / OSKI / Test eng kechi) 26.10 yoki keyin tugagan bo'lsa —
 * 26.10 dan oldin otrabotka qilingan NB va topshirilgan OSKI oshiriladi; 26.10 gacha butunlay
 * tugagan fanlarga tegilmaydi, avval oshirib yuborilganlari --revert-finished bilan qaytadi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('name'),
        $t->string('department_name')->default('1-son davolash fakulteti'), $t->string('specialty_name')->default('Davolash ishi')]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->unsignedBigInteger('group_id'), $t->string('level_code')]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_id'), $t->unsignedBigInteger('subject_id'), $t->string('subject_name'),
        $t->dateTime('lesson_date'), $t->softDeletes()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('student_hemis_id')->nullable(),
        $t->string('subject_id'), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable()]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('subject_name'),
        $t->dateTime('lesson_date'), $t->integer('training_type_code'), $t->integer('attempt')->nullable(), $t->float('grade')->nullable(),
        $t->float('retake_grade')->nullable(), $t->dateTime('retake_graded_at')->nullable(), $t->string('reason')->nullable(),
        $t->string('status')->nullable(), $t->dateTime('deadline')->nullable(), $t->boolean('upgraded_by_admin')->default(false),
        $t->decimal('admin_original_grade', 8, 2)->nullable(), $t->timestamp('admin_upgraded_at')->nullable(), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('groups')->insert([['group_hemis_id' => 1, 'name' => 'd1/d22-03a'], ['group_hemis_id' => 2, 'name' => 'd1/d25-01a']]);
    DB::table('students')->insert([['hemis_id' => '10', 'group_id' => 1, 'level_code' => '15'], ['hemis_id' => '20', 'group_id' => 2, 'level_code' => '12']]);

    // 1 — darslar 20.10 gacha, OSKI 24.10, Test 27.10 → fan 27.10 da tugaydi (olinadi)
    // 2 — darslar 15.10, OSKI 18.10, Test 19.10 → 26.10 gacha tugagan (tegilmaydi)
    // 3 — faqat OSKI, 20.10 → tugagan (tegilmaydi)
    foreach ([[1, '2026-10-20', '2026-10-24', '2026-10-27'], [2, '2026-10-15', '2026-10-18', '2026-10-19'], [3, '2026-10-12', '2026-10-20', null]] as [$id, $last, $oski, $test]) {
        foreach ([1, 2] as $group) {
            DB::table('schedules')->insert(['group_id' => $group, 'subject_id' => $id, 'subject_name' => "Fan $id", 'lesson_date' => "$last 08:30:00"]);
            DB::table('exam_schedules')->insert(['group_hemis_id' => (string) $group, 'subject_id' => (string) $id, 'oski_date' => $oski, 'test_date' => $test]);
        }
    }
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

function nbOskiGrade(array $row): int
{
    return DB::table('student_grades')->insertGetId($row + ['student_hemis_id' => '10', 'subject_id' => '1', 'subject_name' => 'Fan 1',
        'lesson_date' => '2026-10-15 08:30:00', 'training_type_code' => 13, 'status' => 'recorded']);
}

it('upgrades NB retakes and OSKI before the cutoff only for subjects that end on or after it', function () {
    $nb = ['grade' => null, 'reason' => 'absent', 'status' => 'retake'];
    $nbBefore = nbOskiGrade($nb + ['retake_grade' => 80, 'retake_graded_at' => '2026-10-20 12:00:00']);
    $nbSababli = nbOskiGrade($nb + ['retake_grade' => 90, 'retake_graded_at' => '2026-10-25 23:00:00']);
    $nbAfter = nbOskiGrade($nb + ['retake_grade' => 80, 'retake_graded_at' => '2026-10-26 09:00:00']);
    $plain = nbOskiGrade(['grade' => 80]);
    $oski = nbOskiGrade(['grade' => 80, 'training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-24 09:00:00']);
    $test = nbOskiGrade(['grade' => 80, 'training_type_code' => 102, 'attempt' => 1, 'lesson_date' => '2026-10-27 09:00:00']);
    // Tugagan fanlar
    $finishedNb = nbOskiGrade($nb + ['subject_id' => '2', 'retake_grade' => 80, 'retake_graded_at' => '2026-10-16 12:00:00']);
    $finishedOski = nbOskiGrade(['grade' => 80, 'subject_id' => '2', 'training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-18 09:00:00']);
    $oskiOnly = nbOskiGrade(['grade' => 80, 'subject_id' => '3', 'training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-20 09:00:00']);
    // Avvalgi versiya tugagan fanda oshirib yuborgan OSKI
    $wronglyUpgraded = nbOskiGrade(['grade' => 85, 'upgraded_by_admin' => true, 'admin_original_grade' => 80, 'subject_id' => '2',
        'training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-18 10:00:00', 'student_hemis_id' => '10']);
    $course2 = nbOskiGrade(['grade' => 80, 'training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-24 09:00:00', 'student_hemis_id' => '20']);

    $this->artisan('grades:upgrade-nb-oski', ['--faculty' => ['1-son davolash']])->assertSuccessful();
    expect((float) DB::table('student_grades')->find($oski)->grade)->toBe(80.0);   // hisobot — yozmaydi

    $this->artisan('grades:upgrade-nb-oski', ['--faculty' => ['1-son davolash'], '--force' => true])->assertSuccessful();

    $g = fn ($id) => DB::table('student_grades')->find($id);
    expect((float) $g($nbBefore)->retake_grade)->toBe(85.0)
        ->and((float) $g($nbSababli)->retake_grade)->toBe(95.0)
        ->and((float) $g($oski)->grade)->toBe(85.0)
        ->and((float) $g($oski)->admin_original_grade)->toBe(80.0)
        ->and((float) $g($wronglyUpgraded)->grade)->toBe(85.0);       // --revert-finished siz qoladi
    foreach ([$nbAfter, $plain, $test, $finishedNb, $finishedOski, $oskiOnly, $course2] as $id) {
        expect((bool) $g($id)->upgraded_by_admin)->toBeFalse();
    }

    $this->artisan('grades:upgrade-nb-oski', ['--faculty' => ['1-son davolash'], '--revert-finished' => true, '--force' => true])->assertSuccessful();
    expect((float) $g($wronglyUpgraded)->grade)->toBe(80.0)
        ->and((bool) $g($wronglyUpgraded)->upgraded_by_admin)->toBeFalse()
        ->and((float) $g($oski)->grade)->toBe(85.0);                   // to'g'ri oshirilgani qoladi

    $this->artisan('grades:upgrade-nb-oski', ['--faculty' => ['1-son davolash'], '--revert' => true, '--force' => true])->assertSuccessful();
    expect((float) $g($nbBefore)->retake_grade)->toBe(80.0)
        ->and((float) $g($oski)->grade)->toBe(80.0);
});
