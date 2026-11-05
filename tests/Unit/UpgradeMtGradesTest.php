<?php

use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * grades:upgrade-mt: sikli 26.10 yoki keyin tugagan fanlarda MT fayli 26.10 dan oldin yuklangan
 * bo'lsa MT bahosi oshiriladi; keyin yuklangan, fayli yo'q, sikli tugagan MT ga tegilmaydi.
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
        $t->string('semester_code'), $t->dateTime('lesson_date'), $t->softDeletes()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('subject_id'), $t->string('semester_code'), $t->string('curriculum_subject_hemis_id')]);
    Schema::create('independents', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('semester_code'),
        $t->unsignedBigInteger('subject_hemis_id'), $t->string('subject_name')]);
    Schema::create('independent_submissions', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->string('student_hemis_id'), $t->timestamp('submitted_at')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->dateTime('lesson_date')->nullable(), $t->integer('training_type_code'), $t->float('grade')->nullable(), $t->float('retake_grade')->nullable(),
        $t->string('reason')->nullable(), $t->string('status')->nullable(), $t->dateTime('deadline')->nullable(), $t->boolean('upgraded_by_admin')->default(false),
        $t->decimal('admin_original_grade', 8, 2)->nullable(), $t->timestamp('admin_upgraded_at')->nullable(), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('groups')->insert(['group_hemis_id' => 1, 'name' => 'd1/d22-03a']);
    foreach (['10', '11', '12', '13', '14'] as $s) {
        DB::table('students')->insert(['hemis_id' => $s, 'group_id' => 1, 'level_code' => '15']);
    }
    // 1 — sikl 16.10–27.10 (olinadi); 2 — sikl 10.10–24.10 (tugagan)
    foreach ([[1, 'Klinik farmakologiya', '2026-10-16', '2026-10-27'], [2, 'Onkologiya', '2026-10-10', '2026-10-24']] as [$id, $name, $from, $to]) {
        DB::table('schedules')->insert([['group_id' => 1, 'subject_id' => $id, 'subject_name' => $name, 'semester_code' => '9', 'lesson_date' => "$from 08:30:00"],
            ['group_id' => 1, 'subject_id' => $id, 'subject_name' => $name, 'semester_code' => '9', 'lesson_date' => "$to 08:30:00"]]);
        DB::table('curriculum_subjects')->insert(['subject_id' => (string) $id, 'semester_code' => '9', 'curriculum_subject_hemis_id' => (string) (100 + $id)]);
        DB::table('independents')->insert(['id' => $id, 'group_hemis_id' => 1, 'semester_code' => '9', 'subject_hemis_id' => 100 + $id, 'subject_name' => $name]);
    }
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

function mtGrade(string $student, float $grade, string $subject = '1'): int
{
    return DB::table('student_grades')->insertGetId(['student_hemis_id' => $student, 'subject_id' => $subject, 'semester_code' => '9',
        'lesson_date' => null, 'training_type_code' => 99, 'grade' => $grade, 'status' => 'recorded']);
}

it('upgrades MT uploaded before the cutoff in cycles ending on or after it', function () {
    $before = mtGrade('10', 80);
    $low = mtGrade('11', 65);
    $after = mtGrade('12', 80);
    $noFile = mtGrade('13', 80);
    $ended = mtGrade('14', 80, '2');
    DB::table('independent_submissions')->insert([['independent_id' => 1, 'student_hemis_id' => '10', 'submitted_at' => '2026-10-20 10:00:00'],
        ['independent_id' => 1, 'student_hemis_id' => '11', 'submitted_at' => '2026-10-25 23:00:00'],
        ['independent_id' => 1, 'student_hemis_id' => '12', 'submitted_at' => '2026-10-26 09:00:00'],
        ['independent_id' => 2, 'student_hemis_id' => '14', 'submitted_at' => '2026-10-15 10:00:00']]);

    $this->artisan('grades:upgrade-mt', ['groups' => ['d1/d22-03']])->assertSuccessful();
    expect((float) DB::table('student_grades')->find($before)->grade)->toBe(80.0);   // hisobot — yozmaydi

    $this->artisan('grades:upgrade-mt', ['groups' => ['d1/d22-03'], '--force' => true])->assertSuccessful();

    $g = fn ($id) => DB::table('student_grades')->find($id);
    expect((float) $g($before)->grade)->toBe(85.0)
        ->and((float) $g($before)->admin_original_grade)->toBe(80.0)
        ->and((float) $g($low)->grade)->toBe(75.0);
    foreach ([$after, $noFile, $ended] as $id) {
        expect((bool) $g($id)->upgraded_by_admin)->toBeFalse();
    }

    $this->artisan('grades:upgrade-mt', ['groups' => ['d1/d22-03'], '--revert' => true, '--force' => true])->assertSuccessful();
    expect((float) $g($low)->grade)->toBe(65.0)
        ->and($g($low)->reason)->toBeNull()          // MT — otrabotka qarzi qo'yilmaydi
        ->and($g($low)->status)->toBe('recorded')
        ->and((float) $g($before)->grade)->toBe(80.0);
});
