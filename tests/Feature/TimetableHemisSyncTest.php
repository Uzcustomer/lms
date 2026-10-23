<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

/** Jadvalga yozuv: majburiy (NOT NULL, sukutsiz) ustunlar soxta qiymat bilan to'ldiriladi. */
function hsInsert(string $table, array $data): void
{
    foreach (DB::select('SHOW COLUMNS FROM `' . $table . '`') as $col) {
        if ($col->Field === 'id' || array_key_exists($col->Field, $data) || $col->Null === 'YES' || $col->Default !== null) {
            continue;
        }
        $t = strtolower($col->Type);
        $data[$col->Field] = match (true) {
            str_contains($t, 'int') || str_contains($t, 'decimal') || str_contains($t, 'double') => 0,
            str_contains($t, 'datetime') || str_contains($t, 'timestamp') => now(),
            str_contains($t, 'date') => '2026-01-01',
            str_contains($t, 'json') => '[]',
            default => 'x',
        };
    }
    DB::table($table)->insert($data);
}

function hsSetup(): array
{
    Role::findOrCreate('registrator_ofisi', 'web');
    Role::findOrCreate('kafedra_mudiri', 'web');
    $mk = function (string $name, string $role, array $extra = []) {
        $t = new Teacher();
        $t->forceFill(array_merge([
            'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
            'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
            'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
            'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
            'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'is_active' => true,
            'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ], $extra))->save();
        $t->assignRole($role);

        return $t;
    };
    $reg = $mk('REG', 'registrator_ofisi');
    $mudir = $mk('MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 501, 'hemis_id' => 9001]);
    $teacher = $mk('USTOZ', 'kafedra_mudiri', ['department_hemis_id' => 777, 'hemis_id' => 5001]);
    \App\Models\Auditorium::create(['code' => 'KM1', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true]);

    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 4,
        'settings' => ['semester_start' => '2026-09-14']]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 4]);
    hsInsert('groups', ['group_hemis_id' => 1596, 'name' => 'p/st26-01a', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    $details = json_encode([
        ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 8],
    ]);
    hsInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 1, 'curricula_hemis_id' => 250, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi',
        'semester_code' => '11', 'semester_name' => '1', 'subject_details' => $details, 'department_id' => 501, 'department_name' => 'Anatomiya', 'at_semester' => 1]);
    $csId = DB::table('curriculum_subjects')->value('id');
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => $csId, 'week_count' => 4,
        'plan_data' => json_encode(['hours' => ['1' => ['13' => 2], '2' => ['13' => 2], '3' => ['13' => 2], '4' => ['13' => 2]]]),
        'created_at' => now(), 'updated_at' => now()]);

    $card = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'rus', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Odam anatomiyasi', 'group_name' => 'p/st26-01a (rus)', 'kafedra_name' => 'Anatomiya',
        'day' => 1, 'pair' => 3, 'teacher_id' => $teacher->id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '71', 'auditorium_name' => '423']);

    return compact('reg', 'board', 'card', 'csId');
}

function hsSchedule(int $id, string $date, string $start, string $end, int $emp = 5001, string $room = '71'): void
{
    hsInsert('schedules', ['schedule_hemis_id' => $id, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi', 'semester_code' => '11',
        'group_id' => 1596, 'group_name' => 'p/st26-01a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => $date . ' 00:00:00', 'lesson_pair_start_time' => $start, 'lesson_pair_end_time' => $end,
        'employee_id' => $emp, 'employee_name' => 'E' . $emp, 'auditorium_code' => $room, 'auditorium_name' => $room]);
}

it('registrator HEMIS bilan solishtirishda balans va kelajak amallarini oladi', function () {
    $s = hsSetup();
    // 1-hafta: dars 2 soat o'rniga 4 soat qo'yilgan; 2-hafta: o'qituvchi boshqa; 3-hafta: yo'q; 4-hafta: boshqa kun.
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(3, '2026-09-21', '10:00:00', '11:20:00', 6006);
    hsSchedule(4, '2026-10-08', '10:00:00', '11:20:00');

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['faculty_names' => ['F1'], 'specialty_names' => ['stomatologiya'],
            'courses' => [1], 'today' => '2026-09-19'])
        ->assertOk()->json();

    // registrator rolida "today" e'tiborsiz qolmasligi uchun — faqat admin; registratorda bugungi sana.
    expect($res['today'])->toBe(now()->toDateString());
});

it('admin sinov sanasi bilan: ortiqcha soat keyingi haftada kamaytiriladi, mudir xonasi qo\'yiladi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(3, '2026-09-21', '10:00:00', '11:20:00', 6006);
    hsSchedule(4, '2026-10-08', '10:00:00', '11:20:00');
    $s['card']->update(['auditorium_code' => null, 'auditorium_name' => null]);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();

    expect($res['groups'])->toHaveCount(1);
    $u = $res['groups'][0]['units'][0];
    expect($u['before']['hours_diff']['13'])->toBe(-2)
        ->and($u['after']['hours_diff']['13'])->toBe(0)
        ->and($u['after']['count_diff']['A'])->toBe(0);
    $ops = array_map(fn ($a) => $a['op'] . ' ' . $a['date'] . ' ' . $a['start'] . '-' . $a['end'], $u['actions']);
    expect($ops)->toBe([
        'update 2026-09-21 10:00-10:40',   // 2- va 3-hafta 1 soatga qisqaradi (2 soat ortiqcha), xona — mudir xonasi
        'create 2026-09-28 10:00-10:40',
        'create 2026-10-05 10:00-11:20',
        'delete 2026-10-08 10:00-11:20',
    ]);
    expect($u['actions'][0]['changes'])->toHaveCount(3)
        ->and($u['actions'][1]['auditorium_code'])->toBe('KM1')
        ->and($u['actions'][1]['employee_id'])->toBe(5001)
        ->and($u['actions'][1]['flags'])->toBe(['mudir_xonasi']);
});

it('solishtirish Excelga yuklanadi; boshqa rollarga yopiq', function () {
    $s = hsSetup();
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $r = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1]]);
    $r->assertOk();
    expect($r->headers->get('content-type'))->toContain('spreadsheetml');

    $mudir = Teacher::where('full_name', 'MUDIR')->first();
    test()->withoutVite()->actingAs($mudir, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1]])->assertForbidden();
});

it('guruh HEMIS\'da fakulteti va to\'liq nomi bo\'yicha topiladi', function () {
    $s = hsSetup();
    // "1K-01a" ikki fakultetda — F1 kartasi F1 guruhiga bog'lanadi (F2 niki keyin yozilgan)
    hsInsert('groups', ['group_hemis_id' => 2001, 'name' => '1K-01a', 'department_name' => 'F1', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    hsInsert('groups', ['group_hemis_id' => 2002, 'name' => '1K-01a', 'department_name' => 'F2', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 2, 'specialty_hemis_id' => 1]);
    // HEMIS'dagi nomi til qo'shimchasi bilan
    hsInsert('groups', ['group_hemis_id' => 2003, 'name' => 'p/st26-02a (rus)', 'department_name' => 'F1', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    $base = $s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students', 'training_type', 'len_half',
        'weeks', 'subject_name', 'kafedra_name', 'day', 'pair']);
    TimetableCard::create($base + ['group_name' => "1K-01a (o'z)", 'pair' => 5]);
    TimetableCard::create($base + ['group_name' => 'p/st26-02a (rus)', 'pair' => 7]);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1]])->assertOk()->json();
    $ids = array_column($res['groups'], 'group_hemis_id', 'group');
    expect($ids['1K-01a'] ?? null)->toBe(2001)
        ->and($ids['p/st26-02a'] ?? null)->toBe(2003)
        ->and($ids['p/st26-01a'] ?? null)->toBe(1596)
        ->and($res['groups_not_in_hemis'])->toBe([]);
});

it('zaxira xona fanning kafedrasidan; boshqa turdagi va boshqa guruhdagi HEMIS darslari o\'qituvchi bandligida hisobga olinadi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Boshqa kafedraning va faol bo'lmagan "Kafedra mudiri xonasi" — tanlanmaydi; fan kafedrasi (501) niki olinadi
    \App\Models\Auditorium::forceCreate(['code' => 'KM777', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true, 'department_hemis_id' => 777]);
    \App\Models\Auditorium::forceCreate(['code' => 'KM501X', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => false, 'department_hemis_id' => 501]);
    \App\Models\Auditorium::forceCreate(['code' => 'KM501', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true, 'department_hemis_id' => 501]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(3, '2026-09-21', '10:00:00', '11:20:00', 6006);
    hsSchedule(4, '2026-10-08', '10:00:00', '11:20:00');
    // Shu fanning rejaga kirmagan turi (mustaqil ta'lim) — o'sha o'qituvchi 28.09 10:00 da band
    hsInsert('schedules', ['schedule_hemis_id' => 50, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi', 'semester_code' => '11',
        'group_id' => 1596, 'group_name' => 'p/st26-01a', 'training_type_code' => '17', 'training_type_name' => "Mustaqil ta'lim",
        'lesson_date' => '2026-09-28 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '99', 'auditorium_name' => '99']);
    // Boshqa guruh (hisobotda yo'q) — o'sha o'qituvchi 05.10 10:00 da band
    hsInsert('schedules', ['schedule_hemis_id' => 60, 'subject_id' => 999, 'subject_name' => 'Boshqa fan', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-10-05 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '98', 'auditorium_name' => '98']);
    $s['card']->update(['auditorium_code' => null, 'auditorium_name' => null]);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $u = $res['groups'][0]['units'][0];
    $byDate = [];
    foreach ($u['actions'] as $a) {
        $byDate[$a['op'] . ' ' . $a['date']] = $a;
    }
    expect($byDate['create 2026-09-28']['auditorium_code'])->toBe('KM501')
        ->and(implode(' ', $byDate['create 2026-09-28']['blocked']))->toContain("o'qituvchi band")
        ->and(implode(' ', $byDate['create 2026-10-05']['blocked']))->toContain('p/st26-09a')
        ->and(implode(' ', $u['notes']))->toContain('rejaga kirmagan turdagi 1 ta');
});

it('"Kafedra mudiri xonasi" har doim qo\'yiladi va ustma-ust tushsa ham to\'siq bo\'lmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Fan kafedrasining (501) va kafedrasiz xona yo'q — boshqa kafedraniki baribir qo'yiladi
    \App\Models\Auditorium::where('code', 'KM1')->delete();
    \App\Models\Auditorium::forceCreate(['code' => 'KM777', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true, 'department_hemis_id' => 777]);
    \App\Models\Auditorium::forceCreate(['code' => 'KM888', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true, 'department_hemis_id' => 888]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(3, '2026-09-21', '10:00:00', '11:20:00', 6006);
    hsSchedule(4, '2026-10-08', '10:00:00', '11:20:00');
    // Boshqa guruh HEMIS'da shu vaqtda o'sha "Kafedra mudiri xonasi"da (boshqa o'qituvchi bilan)
    hsInsert('schedules', ['schedule_hemis_id' => 70, 'subject_id' => 999, 'subject_name' => 'Boshqa fan', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-28 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 7007, 'employee_name' => 'E7007', 'auditorium_code' => 'KM777', 'auditorium_name' => 'Kafedra mudiri xonasi']);
    $create = function () use ($s) {
        $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
            ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
            ->assertOk()->json();

        return collect($res['groups'][0]['units'][0]['actions'])->first(fn ($a) => $a['op'] . ' ' . $a['date'] === 'create 2026-09-28');
    };

    // Xona biriktirilmagan — o'rinbosar sifatida
    $s['card']->update(['auditorium_code' => null, 'auditorium_name' => null]);
    $a = $create();
    expect($a['auditorium_code'])->toBe('KM777')->and($a['flags'])->toBe(['mudir_xonasi'])->and($a['blocked'])->toBe([]);

    // Kartaning o'zida shu xona qo'yilgan (o'rinbosar belgisisiz) — baribir to'siq emas
    $s['card']->update(['auditorium_code' => 'KM777', 'auditorium_name' => 'Kafedra mudiri xonasi']);
    $a = $create();
    expect($a['auditorium_code'])->toBe('KM777')->and($a['flags'])->toBe([])->and($a['blocked'])->toBe([]);
});

it('bir necha guruhga umumiy karta va HEMIS\'dagi oqim darsi o\'qituvchi/xona to\'qnashuvi hisoblanmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    $s['card']->update(['group_name' => 'p/st26-01a (rus) + p/st26-01b (rus)', 'group_names' => ['p/st26-01a (rus)', 'p/st26-01b (rus)']]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    // Hisobotda yo'q guruh HEMIS'da o'sha darsda (o'sha o'qituvchi, o'sha xona) — oqim
    hsInsert('schedules', ['schedule_hemis_id' => 80, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-28 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '71', 'auditorium_name' => '423']);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();

    expect($res['groups'])->toHaveCount(2);
    $creates = 0;
    $blocked = [];
    foreach ($res['groups'] as $g) {
        foreach ($g['units'] as $u) {
            foreach ($u['actions'] as $a) {
                $creates += $a['op'] === 'create' ? 1 : 0;
                if ($a['blocked']) {
                    $blocked[] = $g['group'] . ' ' . $a['date'] . ': ' . implode('; ', $a['blocked']);
                }
            }
        }
    }
    expect($creates)->toBeGreaterThan(2)->and($blocked)->toBe([]);
});

it('faqat tanlangan haftagacha amallar chiqadi; sukut — joriy hafta oxirigacha', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);

    $two = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])->assertOk()->json();
    $u = $two['groups'][0]['units'][0];
    expect(array_column($u['actions'], 'date'))->toBe(['2026-09-21'])
        ->and($u['actions_later'])->toBe(2)
        ->and($two['groups'][0]['until'])->toBe('2026-09-27');

    // Sukut: joriy (2-) hafta — bugun 2026-09-22
    $def = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-22'])->assertOk()->json();
    expect($def['until_week'])->toBe(2)->and($def['current_week'])->toBe(2)
        // 2-hafta dushanbasidagi dars HEMIS'ga kiritilmagan (o'tib ketdi) — shu haftaning o'zida yangi kun qo'shiladi
        ->and(array_map(fn ($a) => $a['op'] . ' ' . $a['date'], $def['groups'][0]['units'][0]['actions']))->toBe(['create 2026-09-23']);
});
