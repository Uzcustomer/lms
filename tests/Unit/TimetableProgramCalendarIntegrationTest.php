<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Isolated timetable schema: the application's older MySQL-only migrations cannot run on SQLite.
uses(Tests\TestCase::class);

beforeEach(function () {
    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    DB::purge('sqlite');
    Schema::create('groups', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->string('department_name');
        $t->unsignedBigInteger('curriculum_hemis_id');
        $t->boolean('active')->default(true);
        $t->unsignedBigInteger('group_hemis_id')->nullable();
    });
    Schema::create('timetable_boards', function (Blueprint $t) {
        $t->id();
        $t->string('name');
        $t->integer('days');
        $t->integer('weeks');
        $t->integer('pairs_per_day');
        $t->string('academic_year');
        $t->string('semester_parity');
        $t->json('settings')->nullable();
        $t->json('bell_schedule')->nullable();
        $t->timestamps();
    });
    Schema::create('timetable_grid_settings', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('board_id');
        $t->string('faculty_name')->nullable();
        $t->string('specialty_name');
        $t->integer('course');
        $t->integer('days');
        $t->integer('weeks');
        $t->integer('pairs_per_day');
        $t->date('start_date')->nullable();
        $t->timestamps();
        $t->unique(['board_id', 'faculty_name', 'specialty_name', 'course'], 'ttgs_unique_faculty');
    });
    Schema::create('timetable_cards', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('board_id');
        $t->string('faculty_name');
        $t->string('specialty_name');
        $t->integer('course');
        $t->string('subject_name');
        $t->string('group_name');
        $t->json('group_names')->nullable();
        $t->string('training_type');
        $t->integer('day')->nullable();
        $t->integer('pair')->nullable();
        $t->integer('len_half')->default(2);
        $t->integer('teacher_id')->nullable();
        $t->string('teacher_name')->nullable();
        $t->string('auditorium_code')->nullable();
        $t->timestamps();
    });
    $this->board = TimetableBoard::create(['name' => 'T', 'days' => 6, 'weeks' => 15, 'pairs_per_day' => 12,
        'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'settings' => ['semester_start' => '2026-09-14']]);
    $this->base = ['board_id' => $this->board->id, 'faculty_name' => 'Xalqaro', 'specialty_name' => 'davolash ishi', 'course' => 1];
    TimetableGridSetting::create($this->base + ['days' => 6, 'weeks' => 15, 'pairs_per_day' => 12]);
    (require base_path('database/migrations/2026_10_28_140000_add_curriculum_to_timetable_grids.php'))->up();
    DB::table('groups')->insert([
        ['name' => 'day-01a', 'department_name' => 'Xalqaro', 'curriculum_hemis_id' => 101],
        ['name' => 'pimu-01a', 'department_name' => 'Xalqaro', 'curriculum_hemis_id' => 202],
    ]);
    $this->daytime = TimetableCard::create($this->base + ['subject_name' => 'Anatomiya', 'group_name' => 'day-01a (rus)', 'training_type' => 'practice', 'day' => 2, 'pair' => 3]);
    $this->pimu = TimetableCard::create($this->base + ['subject_name' => 'Anatomiya', 'group_name' => 'pimu-01a (rus)', 'training_type' => 'practice', 'day' => 4, 'pair' => 5]);
    $this->controller = new TimetableController;
});

function programInvoke($controller, string $method, ...$args)
{
    return (new ReflectionMethod($controller, $method))->invoke($controller, ...$args);
}

it('saves 17 weeks for PIMU without changing the daytime calendar or any existing card', function () {
    $before = DB::table('timetable_cards')->get()->toJson();
    $request = Request::create('/', 'POST', $this->base + ['curriculum_hemis_id' => 202, 'days' => 6, 'weeks' => 17,
        'pairs_per_day' => 12, 'start_date' => '2026-09-21', 'weekly_breaks' => []]);
    $response = $this->controller->saveGrid($request, $this->board);
    expect($response->getData(true))->toMatchArray(['ok' => true, 'regenerated' => false])
        ->and(programInvoke($this->controller, 'cardGrid', $this->board, $this->pimu)['weeks'])->toBe(17)
        ->and(programInvoke($this->controller, 'cardGrid', $this->board, $this->daytime)['weeks'])->toBe(15)
        ->and(DB::table('timetable_cards')->get()->toJson())->toBe($before)
        ->and(TimetableGridSetting::where('curriculum_hemis_id', 0)->sole()->weeks)->toBe(15);
    expect(programInvoke($this->controller, 'weekStartDate', $this->board, $this->pimu, 1)->toDateString())->toBe('2026-09-21')
        ->and(programInvoke($this->controller, 'weekStartDate', $this->board, $this->daytime, 1)->toDateString())->toBe('2026-09-14');
});

it('rejects a foreign program and destructive regeneration before changing anything', function () {
    $before = DB::table('timetable_cards')->get()->toJson();
    $body = $this->base + ['curriculum_hemis_id' => 303, 'days' => 6, 'weeks' => 17, 'pairs_per_day' => 12];
    expect($this->controller->saveGrid(Request::create('/', 'POST', $body), $this->board)->status())->toBe(422);
    $body['curriculum_hemis_id'] = 202;
    $body['regenerate'] = 1;
    expect($this->controller->saveGrid(Request::create('/', 'POST', $body), $this->board)->status())->toBe(422)
        ->and(DB::table('timetable_cards')->get()->toJson())->toBe($before)
        ->and(TimetableGridSetting::count())->toBe(1);
});

it('does not guess a curriculum for ambiguous or mixed-plan groups', function () {
    DB::table('groups')->insert(['name' => 'pimu-01a', 'department_name' => 'Xalqaro', 'curriculum_hemis_id' => 303]);
    expect(programInvoke($this->controller, 'cardCurriculumId', $this->pimu))->toBe(0);
    $this->daytime->group_names = ['day-01a', 'pimu-01a'];
    expect(programInvoke($this->controller, 'cardCurriculumId', $this->daytime))->toBe(0);
});

it('resolves two active same-name groups by the curriculum their active students study', function () {
    // HEMIS'da bir xil nomli ikki faol guruh (eskisi nofaol qilinmagan): pp/21-01 → 157 va 235.
    DB::table('groups')->insert([
        ['name' => 'pp/21-01', 'department_name' => 'Xalqaro', 'curriculum_hemis_id' => 157, 'group_hemis_id' => 14],
        ['name' => 'pp/21-01', 'department_name' => 'Xalqaro', 'curriculum_hemis_id' => 235, 'group_hemis_id' => 1169],
    ]);
    Schema::create('students', function (Blueprint $t) {
        $t->id();
        $t->unsignedBigInteger('group_id')->nullable();
        $t->unsignedBigInteger('curriculum_id')->nullable();
        $t->string('student_status_code')->nullable();
    });
    $students = fn (int $group, int $curriculum, int $n, string $status = '11') => DB::table('students')->insert(
        array_fill(0, $n, ['group_id' => $group, 'curriculum_id' => $curriculum, 'student_status_code' => $status]));
    $card = TimetableCard::create($this->base + ['specialty_name' => 'pediatriya ishi', 'course' => 6,
        'subject_name' => 'Vaksinoprofilaktika', 'group_name' => 'pp/21-01 (rus)', 'training_type' => 'practice']);
    $resolve = function () use ($card) {
        $c = new TimetableController;
        $map = programInvoke($c, 'hemisGroupCurricula');

        return [programInvoke($c, 'cardCurriculumId', $card), programInvoke($c, 'groupCurriculum', 'pp/21-01 (rus)', 'Xalqaro'),
            $map['pp/21-01'], $map['base:pp/21-01']];
    };

    // Talabalar yo'q — kalendar uchun noaniq (0); fanlar xaritasi avvalgidek yangi (oxirgi) guruhni oladi.
    expect($resolve())->toBe([0, 235, 235, 235]);

    // Faol talabalar eski id'li guruhda (157) o'qiydi; ikkinchi guruhdagilar o'qimaydi (status 60) — hisobga olinmaydi.
    // Kalendar, talabalar soni va fanlar bitta rejadan olinadi.
    $students(14, 157, 20);
    $students(1169, 235, 25, '60');
    expect($resolve())->toBe([157, 157, 157, 157]);

    // Ikkala guruhda ham faol talaba bor — ko'pchilik (157) hal qiladi; kalendar va xaritalar bir xil qaror oladi
    // (aks holda dastur filtri 235 fanlarini, guruh xaritasi 157 fanlarini chiqarib, guruh kartasiz qolardi).
    $students(1169, 235, 1);
    expect($resolve())->toBe([157, 157, 157, 157]);

    // Faol talabalar ikki rejaga teng bo'lingan — kalendar tasodifiy reja olmaydi (0, dastur filtri yo'q);
    // xaritalar avvalgidek yangi (oxirgi) guruhni oladi.
    $students(1169, 235, 19);
    expect($resolve())->toBe([0, 235, 235, 235]);

    // Faqat nomzod rejalar hisoblanadi: boshqa rejadagi talabalar ustunlik bermaydi.
    DB::table('students')->where('curriculum_id', 157)->update(['curriculum_id' => 999]);
    expect($resolve())->toBe([235, 235, 235, 235]);
});

it('keeps teacher and room conflict checks global across curricula', function () {
    $this->daytime->teacher_id = 77;
    $this->daytime->teacher_name = 'Teacher';
    $this->pimu->teacher_id = 77;
    $this->pimu->teacher_name = 'Teacher';
    $errors = programInvoke($this->controller, 'clashWith', $this->daytime, 'R1', $this->pimu, 'R1', 'Room 1');
    expect(implode(' ', $errors))->toContain('O\'qituvchi band')->toContain('Auditoriya band');
});

it('separates the subject-capacity pool while preserving separate teachers and rooms', function () {
    $another = clone $this->pimu;
    expect(programInvoke($this->controller, 'sameCycleCapacityPool', $this->daytime, $this->pimu))->toBeFalse()
        ->and(programInvoke($this->controller, 'sameCycleCapacityPool', $another, $this->pimu))->toBeTrue();
    $another->faculty_name = 'Other';
    expect(programInvoke($this->controller, 'sameCycleCapacityPool', $another, $this->pimu))->toBeFalse();
});

it('detects real-date conflicts between different local week numbers and stops at the shorter term', function () {
    TimetableGridSetting::create($this->base + ['curriculum_hemis_id' => 202, 'weeks' => 17, 'days' => 6,
        'pairs_per_day' => 12, 'start_date' => '2026-09-21', 'weekly_breaks' => []]);
    $this->daytime->teacher_id = 77;
    $this->daytime->teacher_name = 'Teacher';
    $this->pimu->teacher_id = 77;
    $this->pimu->teacher_name = 'Teacher';
    $this->pimu->day = 2;
    $this->pimu->pair = 3;
    $state = ['cards' => [$this->daytime->id => $this->daytime, $this->pimu->id => $this->pimu], 'ov' => []];
    // Daytime week 1 is September 14: PIMU has not started yet.
    expect(programInvoke($this->controller, 'findWeekConflicts', $this->daytime, 1, 2, 3, 0, $state))->toBe([]);
    // Daytime week 2 and PIMU week 1 both fall on September 22.
    expect(implode(' ', programInvoke($this->controller, 'findWeekConflicts', $this->daytime, 2, 2, 3, 0, $state)))->toContain('O\'qituvchi band');
    // PIMU week 17 must not collide with a daytime template whose term already ended.
    expect(programInvoke($this->controller, 'findWeekConflicts', $this->pimu, 17, 2, 3, 0, $state))->toBe([]);
});

it('splits a mixed lecture flow before assigning independent program durations', function () {
    $flows = programInvoke($this->controller, 'programOqims', [['label' => '1-oqim', 'rows' => [
        ['name' => 'day-01a (rus)', 'count' => 15], ['name' => 'pimu-01a (rus)', 'count' => 12],
    ]]], 'Xalqaro');
    expect($flows)->toHaveCount(2)->and(array_column($flows, 'curriculum_hemis_id'))->toBe([101, 202])
        ->and(array_column($flows, 'total'))->toBe([15, 12]);
});

it('selects KTR only from the requested curriculum instead of another program with the same subject', function () {
    $key = programInvoke($this->controller, 'cycleKtrKey', 'davolash ishi', 1, 'Anatomiya');
    $plans = [$key => [['prc' => 6]], 'curriculum:101|'.$key => [['prc' => 2]], 'curriculum:202|'.$key => [['prc' => 4]]];
    expect(programInvoke($this->controller, 'ktrPlanForRow', $plans, 'Xalqaro', 'davolash ishi', 1, 'Anatomiya', 202))->toBe([['prc' => 4]])
        ->and(programInvoke($this->controller, 'ktrPlanForRow', $plans, 'Xalqaro', 'davolash ishi', 1, 'Anatomiya', 303))->toBeNull();
});

it('loads only matching groups and resources for a conflict check', function () {
    $this->daytime->teacher_id = 77;
    $this->daytime->save();
    $this->pimu->teacher_id = 77;
    $this->pimu->save();
    TimetableCard::create($this->base + ['subject_name' => 'Other', 'group_name' => 'unrelated', 'training_type' => 'practice']);
    $state = programInvoke($this->controller, 'calendarConflictState', $this->daytime);
    expect(array_keys($state['cards']))->toBe([$this->daytime->id, $this->pimu->id]);
});
