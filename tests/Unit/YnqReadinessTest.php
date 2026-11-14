<?php

use App\Services\YnqReadiness;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN qaydnoma faqat fan tugagach: yopilish shakli talab qilgan imtihonlar (OSKI / test) o'tgan;
 * imtihonsiz fan (sinov, normativ, jadvalsiz) — oxirgi dars o'tgan.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    \Carbon\Carbon::setTestNow('2026-11-14 10:00:00');
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('curriculum_hemis_id')->nullable()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->string('closing_form')->nullable()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable(),
        $t->boolean('oski_na')->default(false), $t->boolean('test_na')->default(false)]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('subject_id'), $t->string('semester_code'), $t->dateTime('lesson_date')->nullable(), $t->softDeletes()]);

    DB::table('groups')->insert(['group_hemis_id' => '1', 'curriculum_hemis_id' => '5']);
    DB::table('curriculum_subjects')->insert([
        ['curricula_hemis_id' => '5', 'subject_id' => '10', 'semester_code' => '19', 'closing_form' => 'oski_test'],
        ['curricula_hemis_id' => '5', 'subject_id' => '11', 'semester_code' => '19', 'closing_form' => 'oski_test'],
        ['curricula_hemis_id' => '5', 'subject_id' => '12', 'semester_code' => '19', 'closing_form' => 'sinov'],
        ['curricula_hemis_id' => '5', 'subject_id' => '13', 'semester_code' => '19', 'closing_form' => 'sinov'],
        ['curricula_hemis_id' => '5', 'subject_id' => '14', 'semester_code' => '19', 'closing_form' => 'test'],
    ]);
    DB::table('exam_schedules')->insert([
        ['group_hemis_id' => '1', 'subject_id' => '10', 'semester_code' => '19', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-05'],
        ['group_hemis_id' => '1', 'subject_id' => '11', 'semester_code' => '19', 'oski_date' => '2026-11-10', 'test_date' => '2026-11-20'],
    ]);
    DB::table('schedules')->insert([
        ['group_id' => '1', 'subject_id' => '12', 'semester_code' => '19', 'lesson_date' => '2026-11-12 00:00:00', 'deleted_at' => null],
        ['group_id' => '1', 'subject_id' => '13', 'semester_code' => '19', 'lesson_date' => '2026-11-12 00:00:00', 'deleted_at' => null],
        ['group_id' => '1', 'subject_id' => '13', 'semester_code' => '19', 'lesson_date' => '2026-11-19 00:00:00', 'deleted_at' => null],
    ]);
});

it('treats a subject as finished only after its exams, or its last lesson when it has no exam', function () {
    $r = YnqReadiness::forMany([['1', '10', '19'], ['1', '11', '19'], ['1', '12', '19'], ['1', '13', '19'], ['1', '14', '19']]);

    expect($r['1|10|19'])->toBe(['finished' => true, 'reason' => null])                       // OSKI va test o'tgan
        ->and($r['1|11|19'])->toBe(['finished' => false, 'reason' => "test hali o'tmagan"])
        ->and($r['1|12|19'])->toBe(['finished' => true, 'reason' => null])                     // sinov — oxirgi dars o'tgan
        ->and($r['1|13|19'])->toBe(['finished' => false, 'reason' => 'darslar hali tugamagan'])
        ->and($r['1|14|19'])->toBe(['finished' => false, 'reason' => 'test sanasi belgilanmagan']);
});
