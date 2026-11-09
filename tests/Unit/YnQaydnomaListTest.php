<?php

use App\Http\Controllers\Admin\YnQaydnomaListController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Vedomostlar": o'qituvchi tasdiqlagan YN qaydnomalar — dekan faqat o'z fakulteti guruhlarini ko'radi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(), $t->rememberToken(), $t->timestamps()]);
    Schema::create('roles', fn ($t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
    Schema::create('model_has_roles', fn ($t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
    Schema::create('departments', fn ($t) => [$t->id(), $t->unsignedBigInteger('department_hemis_id'), $t->string('name')]);
    Schema::create('dean_faculties', fn ($t) => [$t->id(), $t->unsignedBigInteger('teacher_id'), $t->unsignedBigInteger('department_hemis_id'), $t->timestamps()]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id')->nullable(),
        $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->string('department_name')->nullable()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id')->nullable(), $t->string('subject_id'),
        $t->string('semester_code'), $t->string('subject_name'), $t->string('semester_name')->nullable()]);
    Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->timestamps()]);
    (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();

    DB::table('teachers')->insert(['id' => 9, 'hemis_id' => 900, 'full_name' => 'DEKAN']);
    DB::table('roles')->insert(['id' => 1, 'name' => 'dekan', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => \App\Models\Teacher::class, 'model_id' => 9]);
    DB::table('departments')->insert([['department_hemis_id' => 11, 'name' => '1-son davolash'], ['department_hemis_id' => 22, 'name' => 'Pediatriya']]);
    DB::table('dean_faculties')->insert(['teacher_id' => 9, 'department_hemis_id' => 11]);
    DB::table('groups')->insert([
        ['group_hemis_id' => '77', 'name' => 'd1/d22-01a (rus)', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 11, 'department_name' => '1-son davolash'],
        ['group_hemis_id' => '88', 'name' => 'p/22-01', 'curriculum_hemis_id' => '6', 'department_hemis_id' => 22, 'department_name' => 'Pediatriya'],
    ]);
    DB::table('curriculum_subjects')->insert(['curricula_hemis_id' => '5', 'subject_id' => '27', 'semester_code' => '19',
        'subject_name' => 'Akusherlik va ginekologiya', 'semester_name' => '9-semestr']);
    foreach ([['77', 1, 'ALLAMURATOVA U.D.'], ['77', 2, 'KARIMOV A.'], ['88', 3, 'BOSHQA F.']] as [$group, $teacher, $name]) {
        $v = DB::table('document_verifications')->insertGetId(['token' => str_repeat((string) $teacher, 48)]);
        DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => $group,
            'teacher_id' => $teacher, 'teacher_name' => $name, 'document_verification_id' => $v,
            'weights' => json_encode(['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15]), 'confirmed_at' => now()]);
    }
});

it('shows the dean only the confirmed vedomosts of their faculty, one row per vedomost', function () {
    Auth::shouldUse('teacher');
    Auth::guard('teacher')->login(\App\Models\Teacher::find(9));
    session(['active_role' => 'dekan']);

    $rows = (new YnQaydnomaListController())->index(\Illuminate\Http\Request::create('/admin/yn-qaydnomalar'))->getData()['rows'];

    expect($rows->total())->toBe(1);
    $row = $rows->first();
    expect($row->group)->toBe('d1/d22-01a (rus)')
        ->and($row->subject)->toBe('Akusherlik va ginekologiya')
        ->and($row->teachers->pluck('teacher_name')->sort()->values()->all())->toBe(['ALLAMURATOVA U.D.', 'KARIMOV A.'])
        ->and($row->params)->toMatchArray(['group_hemis_id' => '77', 'weight_jn' => 50, 'weight_oski' => 15, 'weight_test' => 15]);
    expect(\App\Http\Controllers\Admin\JournalController::deanSeesGroup('77'))->toBeTrue()
        ->and(\App\Http\Controllers\Admin\JournalController::deanSeesGroup('88'))->toBeFalse();
});

it('compiles the page template', function () {
    $php = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(resource_path('views/admin/yn-qaydnomalar/index.blade.php')));
    $file = tempnam(sys_get_temp_dir(), 'blade') . '.php';
    file_put_contents($file, $php);
    exec('php -l ' . escapeshellarg($file), $out, $code);
    unlink($file);
    expect($code)->toBe(0);
});
