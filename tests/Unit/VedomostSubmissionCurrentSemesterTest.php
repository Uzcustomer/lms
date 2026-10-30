<?php

use App\Http\Controllers\Admin\VedomostSubmissionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * «Joriy semestr» filtri: har guruhning O'Z joriy semestri (umumiy «yil|semestr» juftligi emas).
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    DB::purge('sqlite');
    Schema::create('students', function ($t) {
        $t->id();
        $t->string('group_id')->nullable();
        $t->string('semester_code')->nullable();
        $t->integer('student_status_code')->nullable();
    });
    Schema::create('groups', function ($t) {
        $t->id();
        $t->string('group_hemis_id');
        $t->string('curriculum_hemis_id')->nullable();
        $t->boolean('active')->default(true);
    });
    Schema::create('semesters', function ($t) {
        $t->id();
        $t->string('curriculum_hemis_id');
        $t->string('code');
        $t->string('education_year')->nullable();
        $t->boolean('current')->default(false);
    });
});

test('joriy semestr: boshqa guruh shu semestrda bo\'lsa ham, o\'tgan semestr vedomosti chiqmaydi', function () {
    // A guruh hozir 18 (8-semestr), B guruh (bahorda boshlagan kohorta) hozir 17 (7-semestr).
    DB::table('groups')->insert([['group_hemis_id' => 'A', 'curriculum_hemis_id' => 'c1'], ['group_hemis_id' => 'B', 'curriculum_hemis_id' => 'c2']]);
    DB::table('students')->insert([
        ['group_id' => 'A', 'semester_code' => '18', 'student_status_code' => 11],
        ['group_id' => 'B', 'semester_code' => '17', 'student_status_code' => 11],
    ]);
    DB::table('semesters')->insert([
        ['curriculum_hemis_id' => 'c1', 'code' => '17', 'education_year' => '2026-2027', 'current' => false],
        ['curriculum_hemis_id' => 'c1', 'code' => '18', 'education_year' => '2026-2027', 'current' => true],
        ['curriculum_hemis_id' => 'c2', 'code' => '17', 'education_year' => '2026-2027', 'current' => true],
    ]);

    $ctrl = app(VedomostSubmissionController::class);
    $current = (new ReflectionMethod($ctrl, 'currentGroupSemesters'))->invoke($ctrl);
    expect($current)->toBe(['2026-2027|17' => ['B'], '2026-2027|18' => ['A']]);

    // A guruhning o'tgan (17) semestr vedomosti — B tufayli «2026-2027|17» juftligi bor bo'lsa ham — chiqmaydi.
    Schema::create('vedomost_submissions', function ($t) {
        $t->id();
        $t->string('group_hemis_id');
        $t->string('curriculum_hemis_id')->nullable();
        $t->string('education_year');
        $t->string('semester_code');
        $t->string('group_name')->nullable();
        $t->string('subject_name')->nullable();
        $t->date('deadline')->nullable();
    });
    Schema::create('curricula', function ($t) {
        $t->id();
        $t->string('curricula_hemis_id');
        $t->string('department_hemis_id')->nullable();
        $t->string('education_type_code')->nullable();
        $t->string('education_type_name')->nullable();
    });
    Schema::create('departments', function ($t) {
        $t->id();
        $t->string('department_hemis_id')->nullable();
        $t->string('name')->nullable();
    });
    Schema::table('semesters', function ($t) {
        $t->string('level_name')->nullable();
        $t->string('level_code')->nullable();
    });
    DB::table('vedomost_submissions')->insert([
        ['group_hemis_id' => 'A', 'curriculum_hemis_id' => 'c1', 'education_year' => '2026-2027', 'semester_code' => '17'],
        ['group_hemis_id' => 'A', 'curriculum_hemis_id' => 'c1', 'education_year' => '2026-2027', 'semester_code' => '18'],
        ['group_hemis_id' => 'B', 'curriculum_hemis_id' => 'c2', 'education_year' => '2026-2027', 'semester_code' => '17'],
    ]);
    $filtered = fn (array $q) => (new ReflectionMethod($ctrl, 'filteredQuery'))->invoke($ctrl, Request::create('/', 'GET', $q))[0]
        ->reorder()->orderBy('vs.id')->pluck('vs.id')->all();

    expect($filtered([]))->toBe([2, 3])
        ->and($filtered(['current_semester' => '0']))->toBe([1, 2, 3]);

    $hint = (new ReflectionMethod($ctrl, 'currentSemesterHint'))->invoke($ctrl);
    expect($hint)->toBe('2026-2027 · 7, 8-semestr');
});
