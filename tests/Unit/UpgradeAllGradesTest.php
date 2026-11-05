<?php

use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * grades:upgrade-all: fan davom etgan bo'lsa (darslari 26.10 yoki keyin tugagan, yoki yakuniy imtihoni —
 * Test, bo'lmasa OSKI — 26.10 yoki keyin) 26.10 dan oldingi kunlik (NB otrabotka ham), MT (26.10 gacha
 * yuklangan) va OSKI baholari oshiriladi; Test va davom etmagan fanlarga tegilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('name'),
        $t->string('department_name'), $t->string('specialty_name')]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->unsignedBigInteger('group_id'), $t->string('level_code')]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_id'), $t->unsignedBigInteger('subject_id'), $t->string('subject_name'),
        $t->string('semester_code'), $t->dateTime('lesson_date'), $t->softDeletes()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('student_hemis_id')->nullable(),
        $t->string('subject_id'), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('subject_id'), $t->string('semester_code'), $t->string('curriculum_subject_hemis_id')]);
    Schema::create('independents', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('semester_code'),
        $t->unsignedBigInteger('subject_hemis_id'), $t->string('subject_name')]);
    Schema::create('independent_submissions', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->string('student_hemis_id'), $t->timestamp('submitted_at')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->dateTime('lesson_date')->nullable(), $t->integer('training_type_code'), $t->integer('attempt')->nullable(), $t->float('grade')->nullable(),
        $t->float('retake_grade')->nullable(), $t->string('reason')->nullable(), $t->string('status')->nullable(), $t->dateTime('deadline')->nullable(),
        $t->boolean('upgraded_by_admin')->default(false), $t->decimal('admin_original_grade', 8, 2)->nullable(),
        $t->timestamp('admin_upgraded_at')->nullable(), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('groups')->insert([
        ['group_hemis_id' => 1, 'name' => 'd1/d22-03a', 'department_name' => '1-son davolash fakulteti', 'specialty_name' => 'Davolash ishi'],
        ['group_hemis_id' => 2, 'name' => 'p/xt23-01a', 'department_name' => 'Pediatriya fakulteti', 'specialty_name' => 'Xalq tabobati'],
    ]);
    DB::table('students')->insert([['hemis_id' => '10', 'group_id' => 1, 'level_code' => '15'], ['hemis_id' => '20', 'group_id' => 2, 'level_code' => '14']]);

    // [fan, darslar boshi, darslar oxiri, OSKI, Test]
    // 1 — sikl 26.10 ni kesib o'tadi; 2 — darslar 23.10, OSKI 23.10, Test 26.10; 3 — darslar 20.10, faqat OSKI 26.10;
    // 4 — darslar 15.10, OSKI 18.10, Test 19.10 (davom etmagan)
    foreach ([[1, '2026-10-16', '2026-10-27', '2026-10-27', '2026-10-29'], [2, '2026-10-15', '2026-10-23', '2026-10-23', '2026-10-26'],
        [3, '2026-10-12', '2026-10-20', '2026-10-26', null], [4, '2026-10-05', '2026-10-15', '2026-10-18', '2026-10-19']] as [$id, $from, $to, $oski, $test]) {
        foreach ([1, 2] as $group) {
            foreach ([$from, $to] as $d) {
                DB::table('schedules')->insert(['group_id' => $group, 'subject_id' => $id, 'subject_name' => "Fan $id", 'semester_code' => '9', 'lesson_date' => "$d 08:30:00"]);
            }
            DB::table('exam_schedules')->insert(['group_hemis_id' => (string) $group, 'subject_id' => (string) $id, 'oski_date' => $oski, 'test_date' => $test]);
        }
        DB::table('curriculum_subjects')->insert(['subject_id' => (string) $id, 'semester_code' => '9', 'curriculum_subject_hemis_id' => (string) (100 + $id)]);
        DB::table('independents')->insert(['id' => $id, 'group_hemis_id' => 1, 'semester_code' => '9', 'subject_hemis_id' => 100 + $id, 'subject_name' => "Fan $id"]);
        DB::table('independent_submissions')->insert(['independent_id' => $id, 'student_hemis_id' => '10', 'submitted_at' => '2026-10-14 10:00:00']);
    }
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

function allGrade(string $subject, array $row): int
{
    return DB::table('student_grades')->insertGetId($row + ['student_hemis_id' => '10', 'subject_id' => $subject, 'semester_code' => '9',
        'lesson_date' => '2026-10-15 08:30:00', 'training_type_code' => 13, 'grade' => 80, 'status' => 'recorded']);
}

it('applies all cutoff rules in one command', function () {
    $nb = ['grade' => null, 'retake_grade' => 80, 'reason' => 'absent', 'status' => 'retake'];
    $ids = [];
    foreach (['1', '2', '3', '4'] as $s) {
        $ids[$s] = [
            'daily' => allGrade($s, ['lesson_date' => '2026-10-15 08:30:00']),
            'nb' => allGrade($s, $nb + ['lesson_date' => '2026-10-14 08:30:00']),
            'mt' => allGrade($s, ['training_type_code' => 99, 'lesson_date' => null]),
        ];
    }
    $after = allGrade('1', ['lesson_date' => '2026-10-27 08:30:00']);
    $oski2 = allGrade('2', ['training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-23 09:00:00']);
    $test2 = allGrade('2', ['training_type_code' => 102, 'attempt' => 1, 'lesson_date' => '2026-10-26 09:00:00']);
    $wrong4 = allGrade('4', ['training_type_code' => 101, 'attempt' => 1, 'lesson_date' => '2026-10-18 09:00:00',
        'grade' => 85, 'upgraded_by_admin' => true, 'admin_original_grade' => 80]);
    $otherSpecialty = allGrade('2', ['student_hemis_id' => '20']);

    $this->artisan('grades:upgrade-all')->assertSuccessful();
    expect((float) DB::table('student_grades')->find($ids['1']['daily'])->grade)->toBe(80.0);   // hisobot — yozmaydi

    $this->artisan('grades:upgrade-all', ['--force' => true])->assertSuccessful();
    $this->artisan('grades:upgrade-all', ['--force' => true])->assertSuccessful();   // ikki marta oshirmaydi

    $g = fn ($id) => DB::table('student_grades')->find($id);
    foreach (['1', '2', '3'] as $s) {
        expect((float) $g($ids[$s]['daily'])->grade)->toBe(85.0)
            ->and((float) $g($ids[$s]['nb'])->retake_grade)->toBe(85.0)
            ->and((float) $g($ids[$s]['mt'])->grade)->toBe(85.0);
    }
    expect((float) $g($oski2)->grade)->toBe(85.0)
        ->and((float) $g($wrong4)->grade)->toBe(85.0);                  // --revert-finished siz qoladi
    foreach ([$ids['4']['daily'], $ids['4']['nb'], $ids['4']['mt'], $after, $test2, $otherSpecialty] as $id) {
        expect((bool) $g($id)->upgraded_by_admin)->toBeFalse();
    }

    $this->artisan('grades:upgrade-all', ['--revert-finished' => true, '--force' => true])->assertSuccessful();
    expect((float) $g($wrong4)->grade)->toBe(80.0)
        ->and((float) $g($oski2)->grade)->toBe(85.0);
});
