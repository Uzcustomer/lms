<?php

use App\Http\Controllers\Admin\YnQaydnomaListController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "Vedomostlar": YN imtihonlari tugagan fanlar — kim topshirgan, kim topshirmagan, dekan tasdig'i.
 * Dekan faqat o'z fakulteti guruhlarini ko'radi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    \Carbon\Carbon::setTestNow('2026-11-09 10:00:00');
    Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(), $t->rememberToken(), $t->timestamps()]);
    Schema::create('roles', fn ($t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
    Schema::create('model_has_roles', fn ($t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
    Schema::create('departments', fn ($t) => [$t->id(), $t->unsignedBigInteger('department_hemis_id'), $t->string('name')]);
    Schema::create('dean_faculties', fn ($t) => [$t->id(), $t->unsignedBigInteger('teacher_id'), $t->unsignedBigInteger('department_hemis_id'), $t->timestamps()]);
    Schema::create('specialties', fn ($t) => [$t->id(), $t->unsignedBigInteger('specialty_hemis_id'), $t->string('name')]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id')->nullable(),
        $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->unsignedBigInteger('specialty_hemis_id')->nullable(), $t->string('department_name')->nullable()]);
    Schema::create('semesters', fn ($t) => [$t->id(), $t->string('curriculum_hemis_id'), $t->string('code'), $t->boolean('current'), $t->string('level_code'), $t->string('level_name')]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id')->nullable(), $t->string('subject_id'),
        $t->string('semester_code'), $t->string('subject_name'), $t->string('semester_name')->nullable(), $t->string('closing_form')->nullable()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->string('subject_name')->nullable(),
        $t->unsignedBigInteger('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable(),
        $t->boolean('oski_na')->default(false), $t->boolean('test_na')->default(false)]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('subject_id'), $t->string('training_type_code'), $t->string('employee_name')->nullable(), $t->softDeletes()]);
    Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->timestamps()]);
    (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();
    (require base_path('database/migrations/2026_11_09_120000_add_role_to_yn_qaydnoma_confirmations.php'))->up();

    DB::table('teachers')->insert(['id' => 9, 'hemis_id' => 900, 'full_name' => 'DEKAN']);
    DB::table('roles')->insert(['id' => 1, 'name' => 'dekan', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => \App\Models\Teacher::class, 'model_id' => 9]);
    DB::table('departments')->insert([['department_hemis_id' => 11, 'name' => '1-son davolash'], ['department_hemis_id' => 22, 'name' => 'Pediatriya']]);
    DB::table('dean_faculties')->insert(['teacher_id' => 9, 'department_hemis_id' => 11]);
    DB::table('specialties')->insert(['specialty_hemis_id' => 7, 'name' => 'Davolash ishi']);
    DB::table('groups')->insert([
        ['group_hemis_id' => '77', 'name' => 'd1/d22-01a', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 11, 'specialty_hemis_id' => 7, 'department_name' => '1-son davolash'],
        ['group_hemis_id' => '78', 'name' => 'd1/d22-02a', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 11, 'specialty_hemis_id' => 7, 'department_name' => '1-son davolash'],
        ['group_hemis_id' => '88', 'name' => 'p/22-01', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 22, 'specialty_hemis_id' => 7, 'department_name' => 'Pediatriya'],
    ]);
    DB::table('semesters')->insert(['curriculum_hemis_id' => '5', 'code' => '19', 'current' => true, 'level_code' => '15', 'level_name' => '5-kurs']);
    DB::table('curriculum_subjects')->insert([
        ['curricula_hemis_id' => '5', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'semester_name' => '9-semestr', 'closing_form' => 'oski_test'],
        ['curricula_hemis_id' => '5', 'subject_id' => '30', 'semester_code' => '19', 'subject_name' => 'Pediatriya', 'semester_name' => '9-semestr', 'closing_form' => 'oski_test'],
    ]);
    DB::table('exam_schedules')->insert([
        ['group_hemis_id' => '77', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-05'],   // tugagan, dekan tasdiqlagan
        ['group_hemis_id' => '78', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-04', 'test_date' => '2026-11-06'],   // tugagan, topshirilmagan
        ['group_hemis_id' => '77', 'subject_id' => '30', 'semester_code' => '19', 'oski_date' => '2026-11-06', 'test_date' => '2026-11-12'],   // test hali yo'q
        ['group_hemis_id' => '88', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-02', 'test_date' => '2026-11-03'],   // boshqa fakultet
    ]);
    DB::table('schedules')->insert([
        ['group_id' => '78', 'subject_id' => '27', 'training_type_code' => '13', 'employee_name' => 'KARIMOV A.'],
        ['group_id' => '78', 'subject_id' => '27', 'training_type_code' => '11', 'employee_name' => "MA'RUZACHI"],
    ]);
    foreach ([['77', 1, 'ALLAMURATOVA U.D.', 'oqituvchi'], ['77', 9, 'DEKAN', 'dekan']] as [$group, $teacher, $name, $role]) {
        $v = DB::table('document_verifications')->insertGetId(['token' => str_repeat((string) $teacher, 48)]);
        DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => $group,
            'teacher_id' => $teacher, 'teacher_name' => $name, 'role' => $role, 'document_verification_id' => $v,
            'weights' => json_encode(['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15]), 'confirmed_at' => now()]);
    }

    Auth::shouldUse('teacher');
    Auth::guard('teacher')->login(\App\Models\Teacher::find(9));
    session(['active_role' => 'dekan']);
    $this->page = fn (array $query = []) => (new YnQaydnomaListController())->index(\Illuminate\Http\Request::create('/admin/yn-qaydnomalar', 'GET', $query))->getData();
});

it('shows the dean every finished vedomost of their faculty with who submitted and who did not', function () {
    $data = ($this->page)();
    $rows = collect($data['rows']->items())->keyBy('group');

    expect($rows->keys()->sort()->values()->all())->toBe(['d1/d22-01a', 'd1/d22-02a'])     // test tugamagan fan va boshqa fakultet yo'q
        ->and($data['counts'])->toMatchArray(['all' => 2, 'none' => 1, 'teacher' => 0, 'dean' => 1]);

    expect($rows['d1/d22-01a']->status)->toBe('dean')
        ->and($rows['d1/d22-01a']->teachers->pluck('teacher_name')->all())->toBe(['ALLAMURATOVA U.D.'])
        ->and($rows['d1/d22-01a']->params)->toMatchArray(['weight_jn' => 50, 'weight_test' => 15]);
    expect($rows['d1/d22-02a']->status)->toBe('none')
        ->and($rows['d1/d22-02a']->pending_teachers->all())->toBe(['KARIMOV A.'])                  // ma'ruzachi emas
        ->and($rows['d1/d22-02a']->params)->toBeNull();
});

it('filters by status and group', function () {
    expect(collect(($this->page)(['status' => 'none'])['rows']->items())->pluck('group')->all())->toBe(['d1/d22-02a'])
        ->and(collect(($this->page)(['group' => '77'])['rows']->items())->pluck('group')->all())->toBe(['d1/d22-01a']);
});

it('decides finished exams by the closing form', function () {
    $row = fn (array $over) => (object) ($over + ['oski_date' => null, 'test_date' => null, 'oski_na' => false, 'test_na' => false, 'closing_form' => null]);

    expect(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski_test', 'oski_date' => '2026-11-03']), '2026-11-09'))->toBeFalse()
        ->and(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-20']), '2026-11-09'))->toBeTrue()
        ->and(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski_test', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-20', 'test_na' => true]), '2026-11-09'))->toBeFalse()
        ->and(YnQaydnomaListController::examsFinished($row(['test_date' => '2026-11-09']), '2026-11-09'))->toBeTrue()
        ->and(YnQaydnomaListController::examsFinished($row([]), '2026-11-09'))->toBeFalse();
});

it('compiles the page template', function () {
    $php = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(resource_path('views/admin/yn-qaydnomalar/index.blade.php')));
    $file = tempnam(sys_get_temp_dir(), 'blade') . '.php';
    file_put_contents($file, $php);
    exec('php -l ' . escapeshellarg($file), $out, $code);
    unlink($file);
    expect($code)->toBe(0);
});
