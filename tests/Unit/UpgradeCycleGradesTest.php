<?php

use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * grades:upgrade-cycle: chegara sanani (26.10) kesib o'tgan sikl fanlarining faqat undan oldingi
 * baholari oshiriladi; 26.10 gacha tugagan yoki keyin boshlangan sikllarga tegilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('name')]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->unsignedBigInteger('group_id'), $t->string('level_code')]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_id'), $t->unsignedBigInteger('subject_id'), $t->string('subject_name'),
        $t->string('semester_code'), $t->dateTime('lesson_date'), $t->softDeletes()]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('subject_name'),
        $t->string('semester_code'), $t->dateTime('lesson_date'), $t->integer('training_type_code'), $t->float('grade')->nullable(),
        $t->float('retake_grade')->nullable(), $t->string('reason')->nullable(), $t->string('status')->nullable(), $t->dateTime('deadline')->nullable(),
        $t->boolean('upgraded_by_admin')->default(false), $t->decimal('admin_original_grade', 8, 2)->nullable(),
        $t->timestamp('admin_upgraded_at')->nullable(), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('groups')->insert([['group_hemis_id' => 1, 'name' => 'd1/d22-01a (rus)'], ['group_hemis_id' => 2, 'name' => 'd1/d22-01']]);
    DB::table('students')->insert([['hemis_id' => '10', 'group_id' => 1, 'level_code' => '15'], ['hemis_id' => '20', 'group_id' => 2, 'level_code' => '15']]);

    // Sikllar: Akusherlik 24.10–02.11 (kesib o'tadi), Onkologiya 10.10–25.10 (tugagan), Pediatriya 27.10–05.11 (keyin boshlangan)
    foreach ([[1, 'Akusherlik', ['2026-10-24', '2026-11-02']], [2, 'Onkologiya', ['2026-10-10', '2026-10-25']], [3, 'Pediatriya', ['2026-10-27', '2026-11-05']]] as [$id, $name, $dates]) {
        foreach ([1, 2] as $group) {
            foreach ($dates as $d) {
                DB::table('schedules')->insert(['group_id' => $group, 'subject_id' => $id, 'subject_name' => $name, 'semester_code' => '19', 'lesson_date' => "$d 08:30:00"]);
            }
        }
    }
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

function cycleGrade(array $row): int
{
    return DB::table('student_grades')->insertGetId($row + ['student_hemis_id' => '10', 'subject_id' => '1', 'subject_name' => 'Akusherlik',
        'semester_code' => '19', 'lesson_date' => '2026-10-24 08:30:00', 'training_type_code' => 13, 'status' => 'recorded']);
}

it('upgrades only grades before the cutoff of cycles that span it', function () {
    $before = cycleGrade(['grade' => 80]);
    $beforeNb = cycleGrade(['grade' => null, 'retake_grade' => 72, 'reason' => 'absent', 'status' => 'retake', 'lesson_date' => '2026-10-25 08:30:00']);
    $onCutoff = cycleGrade(['grade' => 80, 'lesson_date' => '2026-10-26 08:30:00']);
    $after = cycleGrade(['grade' => 80, 'lesson_date' => '2026-10-30 08:30:00']);
    $endedCycle = cycleGrade(['grade' => 80, 'subject_id' => '2', 'subject_name' => 'Onkologiya', 'lesson_date' => '2026-10-20 08:30:00']);
    $laterCycle = cycleGrade(['grade' => 80, 'subject_id' => '3', 'subject_name' => 'Pediatriya', 'lesson_date' => '2026-10-27 08:30:00']);
    $parentGroup = cycleGrade(['grade' => 80, 'student_hemis_id' => '20']);
    $lecture = cycleGrade(['grade' => 80, 'training_type_code' => 11]);

    $this->artisan('grades:upgrade-cycle', ['groups' => ['d1/d22-01']])->assertSuccessful();
    expect((float) DB::table('student_grades')->find($before)->grade)->toBe(80.0);   // hisobot — yozmaydi

    $this->artisan('grades:upgrade-cycle', ['groups' => ['d1/d22-01'], '--force' => true])->assertSuccessful();

    $g = fn ($id) => DB::table('student_grades')->find($id);
    expect((float) $g($before)->grade)->toBe(85.0)
        ->and((float) $g($before)->admin_original_grade)->toBe(80.0)
        ->and((float) $g($beforeNb)->retake_grade)->toBe(81.0);
    foreach ([$onCutoff, $after, $endedCycle, $laterCycle, $parentGroup, $lecture] as $id) {
        expect((bool) $g($id)->upgraded_by_admin)->toBeFalse()
            ->and((float) $g($id)->grade)->toBe(80.0);
    }

    $this->artisan('grades:upgrade-cycle', ['groups' => ['d1/d22-01'], '--revert' => true, '--force' => true])->assertSuccessful();
    expect((float) $g($before)->grade)->toBe(80.0)
        ->and((bool) $g($before)->upgraded_by_admin)->toBeFalse()
        ->and((float) $g($beforeNb)->retake_grade)->toBe(72.0);
});
