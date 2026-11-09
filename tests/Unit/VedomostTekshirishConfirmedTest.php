<?php

use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\VedomostTekshirishController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost tekshirish: dekan tasdiqlagan qaydnomalar sahifa filtrlari bo'yicha; qaydnoma PDF —
 * har varaq bitta sahifa.
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
    Schema::create('specialties', fn ($t) => [$t->id(), $t->unsignedBigInteger('specialty_hemis_id'), $t->string('name')]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id'),
        $t->unsignedBigInteger('department_hemis_id'), $t->unsignedBigInteger('specialty_hemis_id'), $t->boolean('department_active')->default(true), $t->boolean('active')->default(true)]);
    Schema::create('semesters', fn ($t) => [$t->id(), $t->string('curriculum_hemis_id'), $t->string('code'), $t->boolean('current'), $t->string('level_code'), $t->string('level_name')]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('subject_name'), $t->string('semester_name')->nullable(), $t->string('department_id')->nullable()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->unsignedBigInteger('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable()]);
    Schema::create('curricula', fn ($t) => [$t->id(), $t->unsignedBigInteger('curricula_hemis_id'), $t->string('education_type_code')]);
    Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->string('subject_name')->nullable(),
        $t->string('semester_name')->nullable(), $t->string('department_name')->nullable(), $t->timestamps()]);
    (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();
    (require base_path('database/migrations/2026_11_09_120000_add_role_to_yn_qaydnoma_confirmations.php'))->up();

    DB::table('teachers')->insert(['id' => 5, 'hemis_id' => 500, 'full_name' => 'REGISTRATOR']);
    DB::table('roles')->insert(['id' => 1, 'name' => 'registrator_ofisi', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => \App\Models\Teacher::class, 'model_id' => 5]);
    DB::table('departments')->insert([['department_hemis_id' => 11, 'name' => '1-son davolash'], ['department_hemis_id' => 22, 'name' => 'Pediatriya']]);
    DB::table('specialties')->insert(['specialty_hemis_id' => 7, 'name' => 'Davolash ishi']);
    DB::table('curricula')->insert(['curricula_hemis_id' => 5, 'education_type_code' => '11']);
    DB::table('groups')->insert([
        ['group_hemis_id' => '77', 'name' => 'd1/d22-01a', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 11, 'specialty_hemis_id' => 7],
        ['group_hemis_id' => '88', 'name' => 'p/22-01', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 22, 'specialty_hemis_id' => 7],
    ]);
    DB::table('semesters')->insert(['curriculum_hemis_id' => '5', 'code' => '19', 'current' => true, 'level_code' => '15', 'level_name' => '5-kurs']);
    DB::table('curriculum_subjects')->insert(['curricula_hemis_id' => '5', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'semester_name' => '9-semestr']);
    DB::table('exam_schedules')->insert([
        ['group_hemis_id' => '77', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-03', 'test_date' => null],
        ['group_hemis_id' => '88', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-20', 'test_date' => null],
    ]);
    // Ikkala guruhda o'qituvchi va dekan tasdiqlagan
    foreach ([['77', 1, 'ALLAMURATOVA U.D.', 'oqituvchi'], ['77', 9, 'SAGIRAYEV N.J.', 'dekan'], ['88', 2, 'KARIMOV A.', 'oqituvchi'], ['88', 8, 'DEKAN P.', 'dekan']] as [$g, $id, $name, $role]) {
        DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => $g, 'teacher_id' => $id,
            'teacher_name' => $name, 'role' => $role, 'weights' => '{}', 'confirmed_at' => now()]);
    }

    Auth::shouldUse('teacher');
    Auth::guard('teacher')->login(\App\Models\Teacher::find(5));
    $this->list = fn (array $query = []) => (new VedomostTekshirishController())
        ->confirmed(\Illuminate\Http\Request::create('/admin/vedomost-tekshirish/confirmed', 'GET', $query))->getData(true)['items'];
});

it('lists dean-confirmed vedomosts without filters and narrows them with the page filters', function () {
    $all = ($this->list)();
    expect(collect($all)->pluck('group_name')->sort()->values()->all())->toBe(['d1/d22-01a', 'p/22-01'])
        ->and($all[0]['pdf_url'])->toContain('qaydnoma-pdf');

    $byFaculty = ($this->list)(['faculty_id' => 1]);   // departments.id = 1 → 1-son davolash
    expect($byFaculty)->toHaveCount(1)
        ->and($byFaculty[0]['teachers'])->toBe(['ALLAMURATOVA U.D.'])
        ->and($byFaculty[0]['dean_name'])->toBe('SAGIRAYEV N.J.');

    $byDate = ($this->list)(['yn_date_from' => '2026-11-15']);
    expect($byDate)->toHaveCount(1)
        ->and($byDate[0]['group_name'])->toBe('p/22-01');
});

it('lists a cycle subject that is missing from curriculum_subjects, with the name from the confirmation', function () {
    $v = DB::table('document_verifications')->insertGetId(['token' => str_repeat('c', 48), 'subject_name' => 'Ichki kasalliklar', 'semester_name' => '9-semestr']);
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '31', 'semester_code' => '19', 'group_hemis_id' => '77', 'teacher_id' => 9,
        'teacher_name' => 'SAGIRAYEV N.J.', 'role' => 'dekan', 'document_verification_id' => $v, 'weights' => '{}', 'confirmed_at' => now()->addMinute()]);

    $items = ($this->list)(['education_type' => '11', 'level_code' => '15']);
    expect($items)->toHaveCount(3)
        ->and($items[0]['subject_name'])->toBe('Ichki kasalliklar')     // eng so'nggi tasdiq birinchi
        ->and($items[0]['level_name'])->toBe('5-kurs');
    expect(($this->list)(['education_type' => '12']))->toBe([]);
});

it('lists dean-confirmed vedomosts of a past semester too', function () {
    DB::table('semesters')->where('code', '19')->update(['current' => false]);

    expect(($this->list)(['current_semester' => '1']))->toHaveCount(2);
});

it('hides vedomosts the dean has not confirmed yet', function () {
    DB::table('yn_qaydnoma_confirmations')->where('role', 'dekan')->where('group_hemis_id', '88')->delete();

    expect(collect(($this->list)())->pluck('group_name')->all())->toBe(['d1/d22-01a']);
});

it('makes one PDF page per sheet', function () {
    $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    foreach (['12-shakl', '12a-shakl'] as $i => $title) {
        $w = $i ? $s->createSheet() : $s->getActiveSheet();
        $w->setTitle($title)->setCellValue('A1', 'BAHOLASH QAYDNOMASI ' . $title)->setCellValue('B20', "ABDULLAYEV O\u{02BB}G\u{02BB}LI");
        $w->getPageSetup()->setPrintArea('A1:Y61');
    }

    $pdf = JournalController::qaydnomaPdf($s);
    expect($pdf)->toStartWith('%PDF')
        ->and(preg_match_all('/\/Type\s*\/Page[^s]/', $pdf))->toBe(2);
});
