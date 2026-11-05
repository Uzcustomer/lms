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
    Schema::create('groups', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('name'), $t->string('department_name')->default('1-son davolash fakulteti'), $t->string('specialty_name')->default('Davolash ishi')]);
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

it('takes all 4-6 course groups of the given faculty', function () {
    // 3 — 1-son davolash, 2-kurs; 4 — 2-son davolash, 5-kurs; 5 — pediatriya, 5-kurs
    DB::table('groups')->insert([['group_hemis_id' => 3, 'name' => 'd1/d25-01a', 'department_name' => '1-son davolash fakulteti'],
        ['group_hemis_id' => 4, 'name' => 'd2/d22-01a', 'department_name' => '2-son davolash fakulteti'],
        ['group_hemis_id' => 5, 'name' => 'p/d22-01a', 'department_name' => 'Pediatriya fakulteti']]);
    DB::table('students')->insert([['hemis_id' => '30', 'group_id' => 3, 'level_code' => '12'],
        ['hemis_id' => '40', 'group_id' => 4, 'level_code' => '15'], ['hemis_id' => '50', 'group_id' => 5, 'level_code' => '15']]);
    foreach ([3, 4, 5] as $group) {
        foreach (['2026-10-24', '2026-11-02'] as $d) {
            DB::table('schedules')->insert(['group_id' => $group, 'subject_id' => 1, 'subject_name' => 'Akusherlik', 'semester_code' => '19', 'lesson_date' => "$d 08:30:00"]);
        }
    }
    $ids = collect(['10', '20', '30', '40', '50'])->mapWithKeys(fn ($s) => [$s => cycleGrade(['grade' => 80, 'student_hemis_id' => $s])]);

    $this->artisan('grades:upgrade-cycle', ['--faculty' => ['1-son davolash', '2-son davolash'], '--force' => true])->assertSuccessful();

    $up = fn ($s) => (bool) DB::table('student_grades')->find($ids[$s])->upgraded_by_admin;
    expect($up('10'))->toBeTrue()      // 1-son, 5-kurs, kichik guruh
        ->and($up('20'))->toBeTrue()   // 1-son, 5-kurs, asosiy guruh ham (fakultet bo'yicha hamma guruh)
        ->and($up('40'))->toBeTrue()   // 2-son, 5-kurs
        ->and($up('30'))->toBeFalse()  // 2-kurs
        ->and($up('50'))->toBeFalse(); // boshqa fakultet
});

it('filters faculty groups by specialty', function () {
    DB::table('groups')->insert([['group_hemis_id' => 6, 'name' => 'p/d22-01a', 'department_name' => 'Pediatriya fakulteti', 'specialty_name' => 'Pediatriya ishi'],
        ['group_hemis_id' => 7, 'name' => 's/d22-01a', 'department_name' => 'Pediatriya fakulteti', 'specialty_name' => 'Stomatologiya'],
        ['group_hemis_id' => 8, 'name' => 'x/d22-01a', 'department_name' => 'Pediatriya fakulteti', 'specialty_name' => 'Xalq tabobati']]);
    DB::table('students')->insert([['hemis_id' => '60', 'group_id' => 6, 'level_code' => '15'],
        ['hemis_id' => '70', 'group_id' => 7, 'level_code' => '15'], ['hemis_id' => '80', 'group_id' => 8, 'level_code' => '15']]);
    foreach ([6, 7, 8] as $group) {
        foreach (['2026-10-24', '2026-11-02'] as $d) {
            DB::table('schedules')->insert(['group_id' => $group, 'subject_id' => 1, 'subject_name' => 'Akusherlik', 'semester_code' => '19', 'lesson_date' => "$d 08:30:00"]);
        }
    }
    $ids = collect(['60', '70', '80'])->mapWithKeys(fn ($s) => [$s => cycleGrade(['grade' => 80, 'student_hemis_id' => $s])]);

    $this->artisan('grades:upgrade-cycle', ['--faculty' => ['Pediatriya'], '--specialty' => ['pediatriya', 'Stomatologiya'], '--force' => true])->assertSuccessful();

    $up = fn ($s) => (bool) DB::table('student_grades')->find($ids[$s])->upgraded_by_admin;
    expect($up('60'))->toBeTrue()->and($up('70'))->toBeTrue()->and($up('80'))->toBeFalse();
});
