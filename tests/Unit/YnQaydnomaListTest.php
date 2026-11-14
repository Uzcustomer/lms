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
    \App\Services\YnqFamily::flush();
    \Carbon\Carbon::setTestNow('2026-11-09 10:00:00');
    Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(),
        $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->unsignedBigInteger('head_department_hemis_id')->nullable(), $t->rememberToken(), $t->timestamps()]);
    Schema::create('roles', fn ($t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
    Schema::create('model_has_roles', fn ($t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
    Schema::create('departments', fn ($t) => [$t->id(), $t->unsignedBigInteger('department_hemis_id'), $t->string('name')]);
    Schema::create('dean_faculties', fn ($t) => [$t->id(), $t->unsignedBigInteger('teacher_id'), $t->unsignedBigInteger('department_hemis_id'), $t->timestamps()]);
    Schema::create('specialties', fn ($t) => [$t->id(), $t->unsignedBigInteger('specialty_hemis_id'), $t->string('name')]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id')->nullable(),
        $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->unsignedBigInteger('specialty_hemis_id')->nullable(), $t->string('department_name')->nullable(),
        $t->boolean('active')->default(true), $t->boolean('department_active')->default(true)]);
    Schema::create('semesters', fn ($t) => [$t->id(), $t->string('curriculum_hemis_id'), $t->string('code'), $t->boolean('current'), $t->string('level_code'), $t->string('level_name'),
        $t->string('semester_hemis_id')->nullable(), $t->string('education_year')->nullable()]);
    Schema::create('curriculum_weeks', fn ($t) => [$t->id(), $t->string('semester_hemis_id'), $t->date('start_date')->nullable()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id')->nullable(), $t->string('subject_id'),
        $t->string('semester_code'), $t->string('subject_name'), $t->string('semester_name')->nullable(), $t->string('closing_form')->nullable(),
        $t->string('department_id')->nullable(), $t->string('department_name')->nullable(), $t->boolean('is_active')->default(true)]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->string('subject_name')->nullable(),
        $t->unsignedBigInteger('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable(),
        $t->boolean('oski_na')->default(false), $t->boolean('test_na')->default(false)]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('subject_id'), $t->string('semester_code')->nullable(), $t->string('subject_name')->nullable(),
        $t->string('training_type_code'), $t->unsignedBigInteger('employee_id')->nullable(), $t->string('employee_name')->nullable(), $t->boolean('education_year_current')->default(true),
        $t->dateTime('lesson_date')->nullable(), $t->softDeletes()]);
    Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->timestamps()]);
    (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();
    (require base_path('database/migrations/2026_11_09_120000_add_role_to_yn_qaydnoma_confirmations.php'))->up();
    (require base_path('database/migrations/2026_11_09_200000_add_pdf_path_to_yn_qaydnoma_confirmations.php'))->up();
    (require base_path('database/migrations/2026_11_09_160000_create_yn_qaydnoma_rejections_table.php'))->up();
    (require base_path('database/migrations/2026_11_09_180000_add_teacher_ids_to_yn_qaydnoma_rejections.php'))->up();
    (require base_path('database/migrations/2026_11_14_120000_add_form_to_yn_qaydnoma_confirmations.php'))->up();
    Schema::create('yn_submissions', fn ($t) => [$t->id(), $t->string('subject_id'), $t->string('semester_code'), $t->string('group_hemis_id'), $t->timestamps()]);

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
    DB::table('semesters')->insert(['curriculum_hemis_id' => '5', 'code' => '19', 'current' => true, 'level_code' => '15', 'level_name' => '5-kurs', 'semester_hemis_id' => 'S19']);
    DB::table('curriculum_subjects')->insert([
        ['curricula_hemis_id' => '5', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'semester_name' => '9-semestr', 'closing_form' => 'oski_test', 'department_id' => '55', 'department_name' => 'Akusherlik kafedrasi'],
        ['curricula_hemis_id' => '5', 'subject_id' => '30', 'semester_code' => '19', 'subject_name' => 'Pediatriya', 'semester_name' => '9-semestr', 'closing_form' => 'oski_test', 'department_id' => '66', 'department_name' => 'Pediatriya kafedrasi'],
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
    foreach ([['77', 1, 'ALLAMURATOVA U.D.', 'oqituvchi'], ['77', 6, 'MUDIR', 'kafedra_mudiri'], ['77', 9, 'DEKAN', 'dekan']] as [$group, $teacher, $name, $role]) {
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

it('gives the registrar the Vedomost tekshirish filters and the status of any group and subject', function () {
    DB::table('teachers')->insert(['id' => 5, 'hemis_id' => 500, 'full_name' => 'REGISTRATOR']);
    DB::table('roles')->insert(['id' => 2, 'name' => 'registrator_ofisi', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 2, 'model_type' => \App\Models\Teacher::class, 'model_id' => 5]);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(5));
    session(['active_role' => 'registrator_ofisi']);
    $this->app->instance(\App\Http\Controllers\Admin\VedomostTekshirishController::class, new class extends \App\Http\Controllers\Admin\VedomostTekshirishController {
        public function filterViewData(): array
        {
            return ['faculties' => collect(), 'educationTypes' => collect(), 'kafedras' => collect(), 'dekanFacultyIds' => [], 'defaultEducationType' => ''];
        }
    });

    $view = (new YnQaydnomaListController())->index(\Illuminate\Http\Request::create('/admin/yn-qaydnomalar'));
    expect($view->name())->toBe('admin.yn-qaydnomalar.holat');

    // Filtr natijasidagi guruh × fanlar holati: tasdiqlangan, topshirilishi kerak, YN hali tugamagan
    $rows = (new YnQaydnomaListController())->statusFor(collect([['77', '27'], ['78', '27'], ['77', '30'], ['88', '27']])
        ->map(fn ($x) => (object) ['group_hemis_id' => $x[0], 'subject_id' => $x[1], 'semester_code' => '19']))
        ->keyBy(fn ($r) => $r->group . '|' . $r->subject_id);
    expect($rows['d1/d22-01a|27']->status)->toBe('dean')
        ->and($rows['d1/d22-02a|27']->status)->toBe('none')
        ->and($rows['d1/d22-02a|27']->exams_finished)->toBeTrue()
        ->and($rows['d1/d22-02a|27']->pending_teachers->all())->toBe(['KARIMOV A.'])
        ->and($rows['d1/d22-01a|30']->status)->toBe('none')
        ->and($rows['d1/d22-01a|30']->exams_finished)->toBeFalse()                    // test 12.11 da — hali tugamagan
        ->and($rows['p/22-01|27']->status)->toBe('none');

    // Sahifa uchun JSON: holat, PDF havolalari (faqat dekan tasdiqlaganida), YN tugamagani — "waiting"
    $vt = new class extends \App\Http\Controllers\Admin\VedomostTekshirishController {
        protected function searchRows(\Illuminate\Http\Request $request): array
        {
            return array_map(fn ($x) => ['group_hemis_id' => $x[0], 'subject_id' => $x[1], 'semester_code' => '19', 'subject_name' => 'Fan'],
                [['77', '27'], ['78', '27'], ['77', '30']]);
        }
    };
    $items = collect($vt->holat(\Illuminate\Http\Request::create('/x'))->getData(true)['items'])->keyBy(fn ($i) => $i['group'] . '|' . $i['subject']);
    expect($items['d1/d22-01a|Akusherlik']['status'])->toBe('dean')
        ->and($items['d1/d22-01a|Akusherlik']['pdf_view_url'])->toContain('inline=1')
        ->and($items['d1/d22-01a|Akusherlik']['dean']['name'])->toBe('DEKAN')
        ->and($items['d1/d22-02a|Akusherlik']['status'])->toBe('none')
        ->and($items['d1/d22-02a|Akusherlik']['pdf_url'])->toBeNull()
        ->and($items['d1/d22-01a|Pediatriya']['status'])->toBe('waiting');
});

it('lists a submitted vedomost even before its exams finish or when it has no exam schedule', function () {
    foreach ([['77', '30'], ['78', '41']] as [$group, $subject]) {
        $v = DB::table('document_verifications')->insertGetId(['token' => str_repeat($subject, 24)]);
        DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => $subject, 'semester_code' => '19', 'group_hemis_id' => $group,
            'teacher_id' => 3, 'teacher_name' => 'TOSHEV B.', 'role' => 'oqituvchi', 'document_verification_id' => $v,
            'weights' => json_encode(['jn' => 30, 'mt' => 10, 'on' => 0, 'oski' => 0, 'test' => 60]), 'confirmed_at' => now()]);
    }
    // Boshqa fakultet guruhidagi tasdiq dekanga chiqmaydi
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '30', 'semester_code' => '19', 'group_hemis_id' => '88',
        'teacher_id' => 4, 'teacher_name' => 'BOSHQA', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);

    $rows = collect(($this->page)(['status' => 'teacher'])['rows']->items());
    expect($rows->map(fn ($r) => $r->group . ' ' . $r->subject)->sort()->values()->all())->toBe(['d1/d22-01a Pediatriya', 'd1/d22-02a Fan #41'])
        ->and($rows->firstWhere('subject_id', '30')->test_date)->toBe('2026-11-12')
        ->and($rows->firstWhere('subject_id', '41')->level_name)->toBe('5-kurs');
});

it('shows a rejected vedomost to the dean with the reason', function () {
    DB::table('yn_qaydnoma_rejections')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '78', 'dean_id' => 9,
        'dean_name' => 'DEKAN', 'reason' => 'MT xato', 'weights' => json_encode(['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15]),
        'rejected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);

    $data = ($this->page)();
    $row = collect($data['rows']->items())->firstWhere('group', 'd1/d22-02a');
    expect($row->status)->toBe('rejected')
        ->and($row->rejection->reason)->toBe('MT xato')
        ->and($data['counts'])->toMatchArray(['none' => 0, 'rejected' => 1, 'dean' => 1]);
});

it('gives the teacher their own groups and subjects with status', function () {
    DB::table('teachers')->insert(['id' => 1, 'hemis_id' => 501, 'full_name' => 'ALLAMURATOVA U.D.']);
    DB::table('roles')->insert(['id' => 3, 'name' => 'oqituvchi', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => \App\Models\Teacher::class, 'model_id' => 1]);
    DB::table('schedules')->insert([
        ['group_id' => '77', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'training_type_code' => '13', 'employee_id' => 501, 'employee_name' => 'ALLAMURATOVA U.D.'],
        ['group_id' => '78', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'training_type_code' => '13', 'employee_id' => 501, 'employee_name' => 'ALLAMURATOVA U.D.'],
        ['group_id' => '78', 'subject_id' => '30', 'semester_code' => '19', 'subject_name' => 'Pediatriya', 'training_type_code' => '11', 'employee_id' => 501, 'employee_name' => 'ALLAMURATOVA U.D.'],   // ma'ruza
    ]);
    DB::table('yn_submissions')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '78']);
    DB::table('yn_qaydnoma_rejections')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '78', 'dean_id' => 9,
        'dean_name' => 'DEKAN', 'reason' => 'MT xato', 'weights' => json_encode(['jn' => 50, 'mt' => 20, 'on' => 0, 'oski' => 15, 'test' => 15]),
        'rejected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(1));
    session(['active_role' => 'oqituvchi']);

    $data = (new YnQaydnomaListController())->mine(\Illuminate\Http\Request::create('/admin/vedomostlarim'))->getData();
    $rows = collect($data['rows']->items())->keyBy('group');

    expect($data['isTeacher'])->toBeTrue()
        ->and($rows->keys()->all())->toBe(['d1/d22-02a', 'd1/d22-01a'])                  // rad etilgan birinchi; ma'ruza yo'q
        ->and($rows['d1/d22-02a']->status)->toBe('rejected')
        ->and($rows['d1/d22-02a']->yn_submitted)->toBeTrue()
        ->and($rows['d1/d22-02a']->params)->toMatchArray(['group_hemis_id' => '78', 'weight_oski' => 15])   // qayta ko'rib chiqish — rad etilgan vaznlar
        ->and($rows['d1/d22-01a']->status)->toBe('dean')
        ->and($rows['d1/d22-01a']->group_pk)->not->toBeNull();
});

it('keeps submitted and rejected vedomosts of a past semester visible with the current-semester filter on', function () {
    // O'tgan o'quv yili semestri (joriy emas)
    DB::table('semesters')->insert(['curriculum_hemis_id' => '5', 'code' => '17', 'current' => false, 'level_code' => '14', 'level_name' => '4-kurs', 'semester_hemis_id' => 'S17']);
    DB::table('teachers')->insert(['id' => 1, 'hemis_id' => 501, 'full_name' => 'ALLAMURATOVA U.D.']);
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '17', 'group_hemis_id' => '77', 'teacher_id' => 1,
        'teacher_name' => 'ALLAMURATOVA U.D.', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);
    DB::table('yn_qaydnoma_rejections')->insert(['subject_id' => '30', 'semester_code' => '17', 'group_hemis_id' => '78', 'dean_id' => 9,
        'dean_name' => 'DEKAN', 'teacher_ids' => json_encode([1]), 'reason' => 'Qayta tekshiring', 'weights' => '{}',
        'rejected_at' => now(), 'created_at' => now(), 'updated_at' => now()]);
    // Joriy emas, topshirilmagan — ko'rinmaydi
    DB::table('exam_schedules')->insert(['group_hemis_id' => '78', 'subject_id' => '27', 'semester_code' => '17', 'oski_date' => '2026-01-10', 'test_date' => '2026-01-12']);

    $dean = collect(($this->page)()['rows']->items());
    expect($dean->where('semester_code', '17')->map(fn ($r) => $r->group . ' ' . $r->status)->sort()->values()->all())
        ->toBe(['d1/d22-01a teacher', 'd1/d22-02a rejected']);

    DB::table('roles')->insert(['id' => 3, 'name' => 'oqituvchi', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => \App\Models\Teacher::class, 'model_id' => 1]);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(1));
    session(['active_role' => 'oqituvchi']);
    $mine = collect((new YnQaydnomaListController())->mine(\Illuminate\Http\Request::create('/admin/vedomostlarim'))->getData()['rows']->items());
    expect($mine->map(fn ($r) => $r->group . ' ' . $r->semester_code . ' ' . $r->status)->sort()->values()->all())
        ->toBe(['d1/d22-01a 17 teacher', 'd1/d22-01a 19 dean', 'd1/d22-02a 17 rejected']);   // 19 — sozlamadagi tasdiq
});

it('shows the kafedra mudiri only their kafedra subjects, waiting ones as their turn', function () {
    DB::table('teachers')->insert(['id' => 6, 'hemis_id' => 600, 'full_name' => 'MUDIR', 'department_hemis_id' => 55]);
    DB::table('roles')->insert(['id' => 4, 'name' => 'kafedra_mudiri', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 4, 'model_type' => \App\Models\Teacher::class, 'model_id' => 6]);
    // 78-guruh: o'qituvchi topshirgan, kafedra kutilmoqda
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '78', 'teacher_id' => 2,
        'teacher_name' => 'KARIMOV A.', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(6));
    session(['active_role' => 'kafedra_mudiri']);

    $data = ($this->page)();
    $rows = collect($data['rows']->items())->keyBy('group');
    expect($data['isHead'])->toBeTrue()
        ->and($data['canPdf'])->toBeFalse()
        ->and($rows->keys()->sort()->values()->all())->toBe(['d1/d22-01a', 'd1/d22-02a', 'p/22-01'])   // 55-kafedra fani — hamma fakultetda
        ->and($rows['d1/d22-02a']->status)->toBe('teacher')
        ->and($rows['d1/d22-01a']->status)->toBe('dean')
        ->and($rows['d1/d22-01a']->head->teacher_name)->toBe('MUDIR');
});

it('shows subgroups a/b as one vedomost that waits for every subgroup teacher', function () {
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('group_id'), $t->string('student_status_code')->nullable()]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->softDeletes()]);
    DB::table('groups')->insert(['group_hemis_id' => '79', 'name' => 'd1/d22-02b', 'curriculum_hemis_id' => '5', 'department_hemis_id' => 11, 'specialty_hemis_id' => 7, 'department_name' => '1-son davolash']);
    DB::table('students')->insert([['hemis_id' => 's1', 'group_id' => '78'], ['hemis_id' => 's2', 'group_id' => '79']]);
    DB::table('student_grades')->insert([['student_hemis_id' => 's1', 'subject_id' => '27', 'semester_code' => '19'], ['student_hemis_id' => 's2', 'subject_id' => '27', 'semester_code' => '19']]);
    DB::table('exam_schedules')->insert(['group_hemis_id' => '79', 'subject_id' => '27', 'semester_code' => '19', 'oski_date' => '2026-11-04', 'test_date' => '2026-11-06']);
    DB::table('schedules')->insert(['group_id' => '79', 'subject_id' => '27', 'training_type_code' => '13', 'employee_name' => 'SOBIROV B.']);
    // 02a o'qituvchisi topshirgan, 02b — hali yo'q
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '78', 'teacher_id' => 2,
        'teacher_name' => 'KARIMOV A.', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);
    \App\Services\YnqFamily::flush();

    $data = ($this->page)();
    $row = collect($data['rows']->items())->firstWhere('group', 'd1/d22-02');
    expect($row->status)->toBe('partial')
        ->and($row->member_names)->toBe(['d1/d22-02a', 'd1/d22-02b'])
        ->and($row->teachers->pluck('teacher_name')->all())->toBe(['KARIMOV A.'])
        ->and($row->pending_teachers->all())->toBe(['SOBIROV B.'])
        ->and(collect($data['rows']->items())->pluck('group')->sort()->values()->all())->toBe(['d1/d22-01a', 'd1/d22-02'])
        ->and($data['counts'])->toMatchArray(['all' => 2, 'none' => 1, 'teacher' => 0]);

    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '79', 'teacher_id' => 3,
        'teacher_name' => 'SOBIROV B.', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);
    $row = collect(($this->page)()['rows']->items())->firstWhere('group', 'd1/d22-02');
    expect($row->status)->toBe('teacher')                                   // endi kafedra mudiri navbati
        ->and($row->teachers->pluck('teacher_name')->sort()->values()->all())->toBe(['KARIMOV A.', 'SOBIROV B.']);
});

it('filters by status', function () {
    expect(collect(($this->page)(['status' => 'none'])['rows']->items())->pluck('group')->all())->toBe(['d1/d22-02a'])
        ->and(collect(($this->page)(['status' => 'dean'])['rows']->items())->pluck('group')->all())->toBe(['d1/d22-01a']);
});

it('decides finished exams by the closing form', function () {
    $row = fn (array $over) => (object) ($over + ['oski_date' => null, 'test_date' => null, 'oski_na' => false, 'test_na' => false, 'closing_form' => null]);

    expect(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski_test', 'oski_date' => '2026-11-03']), '2026-11-09'))->toBeFalse()
        ->and(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-20']), '2026-11-09'))->toBeTrue()
        ->and(YnQaydnomaListController::examsFinished($row(['closing_form' => 'oski_test', 'oski_date' => '2026-11-03', 'test_date' => '2026-11-20', 'test_na' => true]), '2026-11-09'))->toBeFalse()
        ->and(YnQaydnomaListController::examsFinished($row(['test_date' => '2026-11-09']), '2026-11-09'))->toBeTrue()
        ->and(YnQaydnomaListController::examsFinished($row([]), '2026-11-09'))->toBeFalse();
});

it('compiles the page templates', function () {
    foreach (['admin/yn-qaydnomalar/index', 'admin/yn-qaydnomalar/holat', 'admin/vedomost_tekshirish/index'] as $view) {
        $php = \Illuminate\Support\Facades\Blade::compileString(file_get_contents(resource_path("views/{$view}.blade.php")));
        $file = tempnam(sys_get_temp_dir(), 'blade') . '.php';
        file_put_contents($file, $php);
        exec('php -l ' . escapeshellarg($file), $out, $code);
        unlink($file);
        expect($code)->toBe(0);
    }
});

it('lists the qo\'shimcha / 12a forms as separate vedomosts and puts the ones the teacher must confirm on top', function () {
    DB::table('teachers')->insert(['id' => 1, 'hemis_id' => 501, 'full_name' => 'ALLAMURATOVA U.D.']);
    DB::table('roles')->insert(['id' => 3, 'name' => 'oqituvchi', 'guard_name' => 'web']);
    DB::table('model_has_roles')->insert(['role_id' => 3, 'model_type' => \App\Models\Teacher::class, 'model_id' => 1]);
    DB::table('schedules')->insert(['group_id' => '77', 'subject_id' => '27', 'semester_code' => '19', 'subject_name' => 'Akusherlik',
        'training_type_code' => '13', 'employee_id' => 501, 'employee_name' => 'ALLAMURATOVA U.D.']);
    DB::table('yn_submissions')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '77']);
    // Asosiy 12-shakl dekan tasdiqlagan (beforeEach); keyin sababli / qayta topshirganlar — 12-qo'shimcha va 12a
    app()->instance(\App\Http\Controllers\Admin\JournalController::class, new class extends \App\Http\Controllers\Admin\JournalController {
        public function __construct() {}

        public function ynqActiveForms(string $subjectId, string $semesterCode, string $groupHemisId): array
        {
            return ['12', '12q', '12a'];
        }
    });
    // 12a ni o'qituvchi allaqachon topshirgan — kafedra mudiri kutilmoqda
    DB::table('yn_qaydnoma_confirmations')->insert(['subject_id' => '27', 'semester_code' => '19', 'group_hemis_id' => '77', 'form' => '12a',
        'teacher_id' => 1, 'teacher_name' => 'ALLAMURATOVA U.D.', 'role' => 'oqituvchi', 'weights' => '{}', 'confirmed_at' => now()]);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(1));
    session(['active_role' => 'oqituvchi']);

    $data = (new YnQaydnomaListController())->mine(\Illuminate\Http\Request::create('/admin/vedomostlarim'))->getData();
    $rows = collect($data['rows']->items());
    expect($rows->map(fn ($r) => $r->group . ' ' . $r->form . ' ' . $r->status)->sort()->values()->all())
        ->toBe(['d1/d22-01a 12 dean', 'd1/d22-01a 12a teacher', 'd1/d22-01a 12q none'])
        // Tepada — faqat hali tasdiqlanmagan 12-qo'shimcha
        ->and($data['needsAction']->map(fn ($r) => $r->form . ' ' . $r->form_label)->all())->toBe(["12q 12-shakl qo'shimchasi"])
        ->and($rows->firstWhere('form', '12a')->params)->toMatchArray(['form' => '12a'])
        // O'qituvchining o'z kichik guruhi va shu shaklda tasdiqlagani
        ->and(collect($rows->firstWhere('form', '12a')->my_groups)->map(fn ($g) => $g->name . ($g->confirmed ? ' ✓' : ''))->all())->toBe(['d1/d22-01a ✓'])
        ->and(collect($rows->firstWhere('form', '12q')->my_groups)->map(fn ($g) => $g->name . ($g->confirmed ? ' ✓' : ''))->all())->toBe(['d1/d22-01a']);

    // Dekan — topshirilgan shakl (12a) alohida qator; topshirilmaganlari (hisob talab qiladi) — yo'q
    Auth::guard('teacher')->login(\App\Models\Teacher::find(9));
    session(['active_role' => 'dekan']);
    $dean = collect(($this->page)()['rows']->items())->where('group', 'd1/d22-01a');
    expect($dean->map(fn ($r) => $r->form . ' ' . $r->status)->sort()->values()->all())->toBe(['12 dean', '12a teacher']);
});
