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

// Doska sozlamasi: HEMIS solishtirishda band o'qituvchi/xona o'rniga kafedra mudiri (sukut — o'chiq, to'qnashuv to'siq bo'ladi).
function hsMudirFallback(array $s): void
{
    $s['board']->update(['settings' => ['hemis_mudir_fallback' => true] + ($s['board']->settings ?? [])]);
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

it('katta qamrov: solishtirish va Excel PHP xotira chegarasini 1024M ga ko\'taradi (serverdagi 128M da 500 berardi)', function () {
    $s = hsSetup();
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi']);
    $orig = ini_get('memory_limit');
    // Server chegarasi kabi past: hozirgi sarfdan biroz yuqori (undan past qilib bo'lmaydi).
    $low = (string) (memory_get_usage(true) + 64 * 1024 * 1024);
    try {
        ini_set('memory_limit', $low);
        $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1]])->assertOk();
        expect(ini_get('memory_limit'))->toBe('1024M');

        ini_set('memory_limit', $low);
        $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1]])->assertOk();
        expect(ini_get('memory_limit'))->toBe('1024M');
    } finally {
        ini_set('memory_limit', $orig);
    }
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
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
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
    // O'qituvchi band — to'siq bartaraf etiladi: kafedra mudiri qo'yiladi, sababi va band qilgan dars saqlanadi.
    $c28 = $byDate['create 2026-09-28'];
    expect($c28['auditorium_code'])->toBe('KM501')
        ->and($c28['blocked'])->toBe([])
        ->and($c28['employee_id'])->toBe(9001)
        ->and($c28['flags'])->toContain('mudir')->toContain("to'siq sababli")
        ->and($c28['resolved'][0]['type'])->toBe('teacher')
        ->and($c28['resolved'][0]['source'])->toBe('HEMIS')
        ->and($c28['reason'])->toContain("to'siq sababli kafedra mudiri qo'yildi")
        ->and($byDate['create 2026-10-05']['resolved'][0]['group'])->toBe('p/st26-09a')
        ->and(implode(' ', $u['notes']))->toContain('rejaga kirmagan turdagi 1 ta');

    // Excel: to'siqli amal qolmadi
    $amallar = function (array $blk) use ($s) {
        $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
        file_put_contents($file, test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
            ->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0,
                'ops_filter' => 1, 'ops' => ['blocked'], 'blk_filter' => 1, 'blk' => $blk])
            ->assertOk()->baseResponse->getFile()->getContent());
        $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getSheetByName('Amallar')->getHighestDataRow();
        @unlink($file);

        return $rows - 1;
    };
    expect($amallar(['teacher']))->toBe(0)->and($amallar(['room']))->toBe(0);

    // "To'siqlar" varag'i: har to'siq alohida qator, band qilgan dars tafsiloti bilan.
    $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
    file_put_contents($file, test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->baseResponse->getFile()->getContent());
    $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getSheetByName("To'siqlar");
    @unlink($file);
    $rows = $sheet->toArray();
    expect($rows[0][10])->toBe("To'siq turi")
        ->and(collect($rows)->slice(1)->pluck(10)->all())->toContain("O'qituvchi band — bartaraf: kafedra mudiri qo'yildi")
        ->and(collect($rows)->slice(1)->pluck(12)->all())->toContain('p/st26-09a');

    // Mudirning HEMIS xodim id'si yo'q — almashtirib bo'lmaydi, to'siq qoladi. Excel to'siq turi filtri:
    // faqat "xona band" tanlansa o'qituvchi bandligi to'siqlari chiqmaydi.
    Teacher::where('hemis_id', 9001)->update(['hemis_id' => 0]);
    expect($amallar(['teacher']))->toBeGreaterThan(0)->and($amallar(['room']))->toBe(0);
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

it('"Kafedra mudiri xonasi" — aynan shu nomli auditoriya ustuvor', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    \App\Models\Auditorium::where('code', 'KM1')->delete();
    \App\Models\Auditorium::forceCreate(['code' => 'KMX', 'name' => 'Kafedra mudiri xonasi (eski bino)', 'volume' => 1, 'active' => true]);
    \App\Models\Auditorium::forceCreate(['code' => 'KME', 'name' => ' Kafedra mudiri xonasi ', 'volume' => 1, 'active' => true]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $s['card']->update(['auditorium_code' => null, 'auditorium_name' => null]);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $creates = array_values(array_filter($res['groups'][0]['units'][0]['actions'], fn ($a) => $a['op'] === 'create'));
    expect($res['fallback_room']['code'])->toBe('KME')
        ->and($creates)->not->toBeEmpty()
        ->and(array_unique(array_column($creates, 'auditorium_code')))->toBe(['KME']);
});

it('solishtirishdan oldin oxirgi import vaqti ko\'rsatiladi va HEMIS\'dan yangilash boshlanadi', function () {
    \Illuminate\Support\Facades\Queue::fake();
    \Illuminate\Support\Facades\Cache::forget(\App\Jobs\ImportSchedulesWithProgressJob::PROGRESS_KEY);
    $s = hsSetup();
    hsSchedule(1, now()->toDateString(), '10:00:00', '11:20:00');
    DB::table('schedules')->update(['updated_at' => '2026-10-25 00:42:16']);
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi']);

    $st = $as()->getJson(route('admin.timetable.hemis-sync.import-status', $s['board']))->assertOk()->json();
    expect($st['last_import_at'])->toBe('2026-10-25 00:42:16')->and($st['running'])->toBeFalse();

    $as()->postJson(route('admin.timetable.hemis-sync.import', $s['board']))->assertOk()->assertJsonPath('success', true);
    \Illuminate\Support\Facades\Queue::assertPushed(\App\Jobs\ImportSchedulesWithProgressJob::class);
    expect($as()->getJson(route('admin.timetable.hemis-sync.import-status', $s['board']))->json('running'))->toBeTrue();

    $mudir = Teacher::where('full_name', 'MUDIR')->first();
    test()->withoutVite()->actingAs($mudir, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->postJson(route('admin.timetable.hemis-sync.import', $s['board']))->assertForbidden();
});

it('oxirgi import — to\'liq va xatosiz importning vaqti; HEMIS ishlamasa import "done" bo\'lmaydi', function () {
    $s = hsSetup();
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi']);
    config(['services.hemis.token' => 't']);
    $item = ['id' => 901, 'subject' => ['id' => 474, 'name' => 'Odam anatomiyasi', 'code' => 'OA'],
        'semester' => ['code' => '11', 'name' => '1'], 'educationYear' => ['code' => '2026', 'name' => '2026-2027', 'current' => true],
        'group' => ['id' => 1596, 'name' => 'p/st26-01a', 'educationLang' => ['code' => '12', 'name' => 'Rus']],
        'faculty' => ['id' => 1, 'name' => 'F1', 'code' => 'F1', 'structureType' => ['code' => '11', 'name' => 'Fakultet']],
        'department' => ['id' => 501, 'name' => 'Anatomiya', 'code' => 'A', 'structureType' => ['code' => '12', 'name' => 'Kafedra']],
        'auditorium' => ['code' => '71', 'name' => '423', 'auditoriumType' => ['code' => '11', 'name' => 'Amaliy'], 'building' => ['id' => 1, 'name' => 'B']],
        'trainingType' => ['code' => '13', 'name' => 'Amaliy'],
        'lessonPair' => ['code' => '12', 'name' => '2', 'start_time' => '10:00', 'end_time' => '11:20'],
        'employee' => ['id' => 5001, 'name' => 'E5001'],
        'weekStartTime' => null, 'weekEndTime' => null, 'lesson_date' => now()->addDay()->startOfDay()->timestamp, '_week' => 1];
    $range = ['year' => '2026', 'from' => now()->subMonth(), 'to' => now()->addMonths(3)];
    $hemisUp = false;
    \Illuminate\Support\Facades\Http::fake(function () use (&$hemisUp, $item) {
        return $hemisUp
            ? \Illuminate\Support\Facades\Http::response(['data' => ['items' => [$item], 'pagination' => ['pageCount' => 1]]])
            : \Illuminate\Support\Facades\Http::response('x', 403);
    });
    $service = new class extends \App\Services\ScheduleImportService {
        public ?array $range = null;
        public function currentSemesterRange(): ?array { return $this->range; }
    };
    $service->range = $range;
    $key = \App\Services\ScheduleImportService::LAST_FULL_IMPORT_KEY;

    // Yozuvlar bor, to'liq import hali qayd etilmagan — taxminiy vaqt
    hsSchedule(1, now()->toDateString(), '10:00:00', '11:20:00');
    DB::table('schedules')->update(['updated_at' => now()->subDays(5)->toDateTimeString()]);
    $st = $as()->getJson(route('admin.timetable.hemis-sync.import-status', $s['board']))->json();
    expect($st['last_import_approx'])->toBeTrue();

    // HEMIS ishlamaydi — job xato bilan tugaydi, vaqt qayd etilmaydi
    expect($service->importByEducationYear(null, true, null, $range))->toBeFalse()
        ->and(\Illuminate\Support\Facades\Cache::get($key))->toBeNull();
    (new \App\Jobs\ImportSchedulesWithProgressJob('REG'))->handle($service);
    $job = \Illuminate\Support\Facades\Cache::get(\App\Jobs\ImportSchedulesWithProgressJob::PROGRESS_KEY);
    expect($job['status'])->toBe('error')->and($job['message'])->toContain("to'liq olinmadi");

    // Jurnaldan bitta guruh+fan yangilansa ham to'liq import vaqti o'zgarmaydi
    $hemisUp = true;
    (new \App\Jobs\ImportSchedulesWithProgressJob('REG'))->handle($service);
    expect(\Illuminate\Support\Facades\Cache::get(\App\Jobs\ImportSchedulesWithProgressJob::PROGRESS_KEY)['status'])->toBe('done');
    $full = \Illuminate\Support\Facades\Cache::get($key);
    expect($full)->not->toBeNull();
    test()->travel(2)->hours();
    DB::table('schedules')->update(['updated_at' => now()->toDateTimeString()]);   // bitta guruh+fan importi
    $st = $as()->getJson(route('admin.timetable.hemis-sync.import-status', $s['board']))->json();
    expect($st['last_import_at'])->toBe($full)->and($st['last_import_approx'])->toBeFalse();
});

it('hisob davri bo\'yicha KTR / HEMIS / LMS yig\'indilari; Excel filtrlari bilan', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(3, '2026-09-21', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);

    $res = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-26',
        'until_week' => 0, 'period_from' => 1, 'period_to' => 2])->assertOk()->json();
    $p = $res['groups'][0]['units'][0]['period'];
    expect([$res['period_from'], $res['period_to']])->toBe([1, 2])
        ->and($p['ktr_hours'])->toBe(['13' => 4])
        ->and($p['hemis_hours'])->toBe(['13' => 6])
        ->and($p['ktr_count'])->toBe(['L' => 0, 'A' => 2])
        ->and($p['hemis_count'])->toBe(['L' => 0, 'A' => 2])
        ->and($p['lms_count'])->toBe(['L' => 0, 'A' => 2]);

    // Sukut davr: 1-hafta — joriy hafta
    $def = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-26'])->assertOk()->json();
    expect([$def['period_from'], $def['period_to']])->toBe([1, 2]);

    $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1], 'until_week' => 0, 'period_from' => 1, 'period_to' => 2,
        'only_diff' => 1, 'ops' => ['create', 'blocked']])->assertOk();
});

it('Excel: keyingi haftalardagi farqli fan "mos" deb yashirilmaydi; amal turlari hech biri tanlanmasa — amal yo\'q', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Hammasi KTR bo'yicha; faqat 4-haftada (05.10) HEMIS'da boshqa o'qituvchi
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');
    hsSchedule(3, '2026-09-28', '10:00:00', '11:20:00');
    hsSchedule(4, '2026-10-05', '10:00:00', '11:20:00', 6006);
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $req = ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2];

    $u = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $req)->assertOk()->json('groups.0.units.0');
    expect($u['actions'])->toBe([])->and($u['actions_later'])->toBe(1);

    $sheets = function (array $extra) use ($as, $s, &$req) {
        $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
        file_put_contents($file, $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), $req + $extra)->assertOk()->baseResponse->getFile()->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        @unlink($file);

        return [$book->getSheetByName('Balans')->getHighestDataRow(), $book->getSheetByName('Amallar')->getHighestDataRow()];
    };
    // Faqat farqi borlar: fan Balans'da qoladi (keyinroq farqi bor)
    expect($sheets(['only_diff' => 1])[0])->toBeGreaterThan(1);
    // Amal turlari: hech biri — Amallar varag'ida faqat sarlavha
    $req['until_week'] = 0;
    expect($sheets(['ops_filter' => 1])[1])->toBe(1)
        ->and($sheets(['ops_filter' => 1, 'ops' => ['update']])[1])->toBe(2)
        ->and($sheets([])[1])->toBe(2)
        // Tanlangan turdagi amali yo'q fan Balans'ga ham chiqmaydi
        ->and($sheets(['ops_filter' => 1, 'ops' => ['blocked']])[0])->toBe(1)
        ->and($sheets(['ops_filter' => 1, 'ops' => ['update']])[0])->toBeGreaterThan(1);
});

it('kalendari boshqacha guruhlar: sukut hisob davri har guruhning o\'z joriy haftasi, Excel ham shunday', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Pediatriya 1-kurs 2 hafta kech boshlanadi
    TimetableGridSetting::where('board_id', $s['board']->id)->update(['weeks' => 8]);
    TimetableGridSetting::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'pediatriya', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 8, 'start_date' => '2026-09-28']);
    hsInsert('groups', ['group_hemis_id' => 1700, 'name' => 'p/pd26-01a', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 2]);
    TimetableCard::create($s['card']->only(['board_id', 'faculty_name', 'course', 'oqim_label', 'lang', 'students', 'training_type', 'len_half', 'weeks',
        'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name'])
        + ['specialty_name' => 'pediatriya', 'group_name' => 'p/pd26-01a (rus)']);
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $req = ['courses' => [1], 'today' => '2026-10-10'];

    $res = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $req)->assertOk()->json();
    $per = [];
    foreach ($res['groups'] as $g) {
        $per[$g['group']] = [$g['period_to'], $g['units'][0]['period']['to_week']];
    }
    expect($per)->toBe(['p/st26-01a' => [4, 4], 'p/pd26-01a' => [2, 2]]);

    // Excel (oyna sukutda davr yubormaydi) — har guruh o'z davri bilan
    $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
    file_put_contents($file, $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), $req)->assertOk()->baseResponse->getFile()->getContent());
    $rows = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getSheetByName('Balans')->toArray();
    @unlink($file);
    $davr = [];
    foreach (array_slice($rows, 1) as $r) {
        $davr[$r[0]] = $r[16];
    }
    expect($davr)->toBe(['p/st26-01a' => '1–4-hafta', 'p/pd26-01a' => '1–2-hafta']);
});

it('o\'qituvchi ustma-ust tushmaydi; faqat kafedra mudiriga majburan qo\'yilgan HEMIS darsi bandlik hisoblanmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Kafedra mudiri (9001, 501-kafedra) shu fanni haqiqatan o'qitadi
    $mudir = Teacher::where('full_name', 'MUDIR')->first();
    $s['card']->update(['teacher_id' => $mudir->id, 'teacher_name' => 'MUDIR']);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00', 9001);
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00', 9001);
    $other = fn (int $id, string $date, int $dept) => hsInsert('schedules', ['schedule_hemis_id' => $id, 'subject_id' => 999, 'subject_name' => 'Boshqa fan',
        'semester_code' => '11', 'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => $date . ' 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 9001, 'employee_name' => 'MUDIR', 'auditorium_code' => '98', 'auditorium_name' => '98', 'department_id' => $dept]);
    // 28.09: boshqa guruhda o'z kafedrasi (501) fani — o'qituvchi biriktirilmagani uchun mudirga majburan qo'yilgan
    $other(90, '2026-09-28', 501);
    // 05.10: boshqa kafedra (777) fanida mudirning haqiqiy darsi
    $other(91, '2026-10-05', 777);

    $u = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json('groups.0.units.0');
    $blocked = [];
    foreach ($u['actions'] as $a) {
        $blocked[$a['op'] . ' ' . $a['date']] = implode('; ', $a['blocked']);
    }
    expect($blocked['create 2026-09-28'])->toBe('')
        ->and($blocked['create 2026-10-05'])->toContain("o'qituvchi band");
});

it('Excel fan filtri: tanlanmagan fan chiqmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $rows = function (array $subjects) use ($as, $s) {
        $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
        file_put_contents($file, $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0,
            'subjects_filter' => 1, 'subjects' => $subjects])->assertOk()->baseResponse->getFile()->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        @unlink($file);

        return [$book->getSheetByName('Balans')->getHighestDataRow(), $book->getSheetByName('Amallar')->getHighestDataRow()];
    };
    // Tanlanmagan fan — na Balans'da, na Amallar'da; tanlangani — bor
    expect($rows(['Boshqa fan']))->toBe([1, 1]);
    [$bal, $act] = $rows(['Odam anatomiyasi']);
    expect($bal)->toBeGreaterThan(1)->and($act)->toBeGreaterThan(1);
});

it('Excel guruh filtri: tanlanmagan guruh chiqmaydi, hisobot qamrovi o\'zgarmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $req = ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0];
    $group = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $req)->assertOk()->json('groups.0.group');
    $rows = function (array $extra) use ($as, $s, $req) {
        $file = tempnam(sys_get_temp_dir(), 'hs') . '.xlsx';
        file_put_contents($file, $as()->post(route('admin.timetable.hemis-sync.export', $s['board']), $req + $extra)
            ->assertOk()->baseResponse->getFile()->getContent());
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        @unlink($file);

        return [$book->getSheetByName('Balans')->getHighestDataRow(), $book->getSheetByName('Amallar')->getHighestDataRow()];
    };
    $all = $rows([]);
    // Boshqa guruh tanlangan — na Balans'da, na Amallar'da; shu guruh tanlangani — filtrsizdagi bilan bir xil
    expect($all[0])->toBeGreaterThan(1)->and($all[1])->toBeGreaterThan(1)
        ->and($rows(['groups_filter' => 1, 'filter_groups' => ['boshqa-guruh']]))->toBe([1, 1])
        ->and($rows(['groups_filter' => 1, 'filter_groups' => [$group]]))->toBe($all);
});

it('vaqti shablondan farq qilgan amalda sabab yoziladi: shablon vaqtida o\'qituvchi band', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Shablon (tashqi): dushanba 08:30; ichki: 10:00 — chunki 08:30 da o'qituvchi boshqa yo'nalishda dars o'tadi.
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 1]);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'farmatsiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Falsafa', 'group_name' => 'p/f26-01a', 'kafedra_name' => 'X', 'ext_day' => 1, 'ext_pair' => 1,
        'day' => 1, 'pair' => 1, 'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '99', 'auditorium_name' => '124']);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '08:30:00', '09:50:00');   // HEMIS'da shablon vaqtida

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json();
    $a = collect($res['groups'][0]['units'][0]['actions'])->firstWhere('date', '2026-09-21');
    expect($a['op'])->toBe('update')
        ->and($a['reason'])->toContain('Tashqi jadvalda 08:30 da, ichki jadvalda 10:00 ga pastga tushirilgan')
        ->and($a['reason'])->toContain('o\'qituvchi USTOZ shu vaqtda (08:30) farmatsiya 1-kurs p/f26-01a «Falsafa»');
});

it('sabab: boshqa fakultetning bir xil nomli guruhi xalaqit deb ko\'rsatilmaydi; haftalik ko\'chirilgan xona hisobga olinadi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Shablon (tashqi): dushanba 08:30; ichki: 10:00
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 1]);
    $base = ['board_id' => $s['board']->id, 'course' => 1, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice',
        'len_half' => 2, 'weeks' => 4, 'kafedra_name' => 'X', 'ext_day' => 1, 'ext_pair' => 1, 'day' => 1, 'pair' => 1];
    // F2 fakultetida xuddi shu nomli guruh 08:30 da (boshqa o'qituvchi, boshqa xona) — xalaqit emas
    TimetableCard::create($base + ['faculty_name' => 'F2', 'specialty_name' => 'stomatologiya', 'subject_name' => 'Kimyo',
        'group_name' => 'p/st26-01a (rus)', 'auditorium_code' => '55', 'auditorium_name' => '55']);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '08:30:00', '09:50:00');
    hsSchedule(3, '2026-09-28', '08:30:00', '09:50:00');
    // 3-haftada dars B1 xonasiga ko'chirilgan; B1 08:30 da boshqa yo'nalish darsi bilan band
    \App\Models\TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => 3, 'day' => 1, 'pair' => 3,
        'cancelled' => false, 'auditorium_code' => 'B1', 'auditorium_name' => 'B1']);
    TimetableCard::create($base + ['faculty_name' => 'F1', 'specialty_name' => 'farmatsiya', 'subject_name' => 'Falsafa',
        'group_name' => 'p/f26-01a', 'auditorium_code' => 'B1', 'auditorium_name' => 'B1']);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 3])
        ->assertOk()->json();
    $unit = collect($res['groups'])->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    $acts = collect($unit['actions'])->keyBy('date');
    expect($acts['2026-09-21']['reason'])->not->toContain('guruh')->toContain('08:30 bo\'sh')
        ->and($acts['2026-09-28']['reason'])->toContain('B1-xona 08:30 da band');
});

it('sabab: shablon vaqtida o\'qituvchi sikl darsida band bo\'lsa — "bo\'sh" deb zichlash taklif qilinmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 1]);   // shablon: dushanba 08:30; ichki: 10:00
    // 5-kurs sikli: o'sha o'qituvchi 21.09 (dushanba) kuni ertalabdan sikl amaliyotida
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 1]);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Pediatriya', 'group_name' => 'g5-01a', 'kafedra_name' => 'P', 'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 7, 'training_type' => 'practice']);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '08:30:00', '09:50:00');

    $unit = collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json('groups'))->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    $reason = collect($unit['actions'])->firstWhere('date', '2026-09-21')['reason'];
    expect($reason)->toContain("o'qituvchi USTOZ shu vaqtda (08:30) sikl darsida")->toContain('Pediatriya')
        ->not->toContain("bo'sh");
});

it('sabab: zichlash shablon vaqtiga yetkaza olmasa — "Haftani zichlash" emas, qo\'lda ko\'chirish tavsiya qilinadi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Shablon 08:30 (1-yarim slot), ichki jadvalda 12:00 (5-yarim slot); 10:00 shablonda bo'sh — zichlash o'tolmaydi
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 1, 'pair' => 5]);
    hsSchedule(1, '2026-09-14', '12:00:00', '13:20:00');
    hsSchedule(2, '2026-09-21', '08:30:00', '09:50:00');

    $unit = collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json('groups'))->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    $reason = collect($unit['actions'])->firstWhere('date', '2026-09-21')['reason'];
    expect($reason)->toContain('ichki jadvalda 12:00 ga')->toContain("chiqara olmaydi")->toContain("08:30 ga qo'lda ko'chiring")
        ->not->toContain('chiqarish mumkin');
});

it('sabab: doskaning hafta kalendarlari har hafta uchun bazaga so\'rovsiz quriladi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 1]);
    foreach (['farmatsiya', 'pediatriya', 'davolash', 'tibbiy', 'biotexnologiya'] as $i => $sp) {
        TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => $sp, 'course' => 2,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 4,
            'subject_name' => 'X', 'group_name' => 'g' . $i, 'kafedra_name' => 'X', 'ext_day' => 1, 'ext_pair' => 1, 'day' => 1, 'pair' => 1]);
    }
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '08:30:00', '09:50:00');
    DB::enableQueryLog();
    test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])->assertOk();
    // 7 qamrov × 4 hafta: avval har (qamrov, hafta) uchun panjara sozlamasi so'ralardi (58 ta so'rov)
    $grid = collect(DB::getQueryLog())->pluck('query')->filter(fn ($x) => str_contains($x, 'timetable_grid_settings'))->count();
    expect($grid)->toBeLessThan(7 * 4);
});

it('sabab: yuqoriga ko\'chirilgan dars — xalaqit bo\'lsa o\'sha sabab, aks holda "zichlangan" deb taxmin qilinmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Shablon va ichki jadval: dushanba 10:00 (3-yarim slot); 2- va 3-haftada 08:30 ga ko'chirilgan
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 3]);
    foreach ([2, 3] as $w) {
        \App\Models\TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => $w, 'day' => 1, 'pair' => 1, 'cancelled' => false]);
    }
    // 2-haftada 10:00 da o'sha o'qituvchi boshqa yo'nalishda dars o'tadi (3-haftada yo'q)
    $other = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'farmatsiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Falsafa', 'group_name' => 'p/f26-01a', 'kafedra_name' => 'X', 'ext_day' => 1, 'ext_pair' => 3,
        'day' => 1, 'pair' => 3, 'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '99', 'auditorium_name' => '124']);
    \App\Models\TimetableCardOverride::create(['card_id' => $other->id, 'week' => 3, 'cancelled' => true]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');
    hsSchedule(3, '2026-09-28', '10:00:00', '11:20:00');

    $unit = collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 3])
        ->assertOk()->json('groups'))->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    $acts = collect($unit['actions'])->keyBy('date');
    expect($acts['2026-09-21']['reason'])->toContain('ichki jadvalda 08:30 ga yuqoriga ko\'chirilgan, chunki o\'qituvchi USTOZ shu vaqtda (10:00)')
        ->not->toContain('Zichlangan')
        ->and($acts['2026-09-28']['reason'])->toContain('yuqoriga ko\'chirilgan (shu hafta uchun ko\'chirilgan; 10:00 hozir bo\'sh)')
        ->not->toContain('Zichlangan');
});

it('sabab: oradagi vaqt guruhning tashqi jadvalidagi o\'tilmagan darsniki bo\'lsa — "zichlangan"', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 3]);
    \App\Models\TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => 2, 'day' => 1, 'pair' => 1, 'cancelled' => false]);
    // Guruhning 08:30 dagi boshqa darsi (tashqi jadvalda) 2-haftada o'tilmaydi
    $first = TimetableCard::create($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'group_name', 'kafedra_name']) + ['subject_name' => 'Biologiya', 'ext_day' => 1, 'ext_pair' => 1, 'day' => 1, 'pair' => 1]);
    \App\Models\TimetableCardOverride::create(['card_id' => $first->id, 'week' => 2, 'cancelled' => true]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');

    $unit = collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json('groups'))->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    expect(collect($unit['actions'])->firstWhere('date', '2026-09-21')['reason'])->toContain('Zichlangan');
});

it('faqat xona yoki o\'qituvchi farq qilsa ham sabab yoziladi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 3]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00', 6006, '55');   // boshqa o'qituvchi va xona

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json();
    $a = collect($res['groups'][0]['units'][0]['actions'])->firstWhere('date', '2026-09-21');
    expect($a['reason'])->toContain('LMS kartochkasida biriktirilgan xona: 423')
        ->and($a['reason'])->toContain('LMS kartochkasida biriktirilgan o\'qituvchi: USTOZ');
});

it('sabab: xona shu hafta uchun ichki jadvalda alohida berilgan bo\'lsa — shuni aytadi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['ext_day' => 1, 'ext_pair' => 3]);
    \App\Models\TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => 2, 'day' => 1, 'pair' => 3,
        'cancelled' => false, 'auditorium_code' => 'B1', 'auditorium_name' => 'B1']);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');   // HEMIS'da kartochka xonasi (71)

    $unit = collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 2])
        ->assertOk()->json('groups'))->flatMap(fn ($g) => $g['units'])->firstWhere('subject_name', 'Odam anatomiyasi');
    expect(collect($unit['actions'])->firstWhere('date', '2026-09-21')['reason'])
        ->toContain('Xona ichki jadvalda shu hafta uchun alohida berilgan: B1 (kartochkada — 423)');
});

it('bir xil nomli ikki guruhdan HEMIS jadvali va o\'quv rejasi borini tanlaydi', function () {
    $s = hsSetup();
    // Ikkinchi "p/st26-01a": boshqa o'quv reja (fanlari yo'q), jadvali yo'q, hemis id katta. Jadvalda u birinchi turadi —
    // avvalgi «birinchisini olish» uni tanlardi.
    $real = (array) DB::table('groups')->where('group_hemis_id', 1596)->first();
    DB::table('groups')->where('group_hemis_id', 1596)->delete();
    hsInsert('groups', ['group_hemis_id' => 9999, 'name' => 'p/st26-01a', 'curriculum_hemis_id' => 777, 'department_hemis_id' => 1,
        'specialty_hemis_id' => 1, 'department_name' => 'F1']);
    unset($real['id']);
    DB::table('groups')->insert(array_merge($real, ['department_name' => 'F1']));
    expect(DB::table('groups')->where('name', 'p/st26-01a')->orderBy('id')->value('group_hemis_id'))->toBe(9999);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'until_week' => 0])->assertOk()->json();
    expect($res['groups'][0]['group_hemis_id'])->toBe(1596)
        ->and(count($res['groups'][0]['units']) + count($res['groups'][0]['skipped']))->toBeGreaterThan(0)
        ->and($res['groups'][0]['cards_without_subject'])->toBe([]);
});

it('bir xil nomli guruhlardan o\'quv rejasida faqat o\'chirilgan fanlari borini tanlamaydi', function () {
    $s = hsSetup();
    // Ikkinchi "p/st26-01a": hemis id katta, jadvali yo'q (birinchisida ham yo'q); o'quv rejasida shu semestr fani bor,
    // lekin HEMIS'dan olib tashlangan (is_active = false) — solishtirish uni baribir hisobga olmaydi.
    hsInsert('groups', ['group_hemis_id' => 9999, 'name' => 'p/st26-01a', 'curriculum_hemis_id' => 777, 'department_hemis_id' => 1,
        'specialty_hemis_id' => 1, 'department_name' => 'F1']);
    DB::table('groups')->where('group_hemis_id', 1596)->update(['department_name' => 'F1']);
    hsInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 777, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi',
        'semester_code' => '11', 'semester_name' => '1', 'subject_details' => '[]', 'department_id' => 501, 'department_name' => 'Anatomiya',
        'at_semester' => 1, 'is_active' => false]);
    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'until_week' => 0])->assertOk()->json();
    expect($res['groups'][0]['group_hemis_id'])->toBe(1596)
        ->and($res['groups'][0]['cards_without_subject'])->toBe([]);
});

it('kafedra mudiri faqat mark\'dagi rol bo\'yicha topiladi; HEMIS lavozimi hisobga olinmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $mudir = Teacher::where('full_name', 'MUDIR')->first();
    $s['card']->update(['teacher_id' => null, 'teacher_name' => null]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $body = ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0];

    // Rol bor — mudir qo'yiladi.
    $a = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $body)->assertOk()->json('groups.0.units.0.actions.0');
    expect($a['employee_id'])->toBe(9001)->and($a['blocked'])->toBe([]);

    // Rol olib tashlandi, faqat HEMIS lavozimi "Kafedra mudiri" — topilmaydi.
    $mudir->removeRole('kafedra_mudiri');
    $mudir->forceFill(['staff_position' => 'Kafedra mudiri'])->save();
    $a = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $body)->assertOk()->json('groups.0.units.0.actions.0');
    expect(implode(' ', $a['blocked']))->toContain('kafedra mudiri ham topilmadi');
});

it('kafedra mudirlaridan HEMIS id\'si borini oladi; yagona mudirning id\'si yo\'q bo\'lsa to\'siqda nomi aytiladi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $s['card']->update(['teacher_id' => null, 'teacher_name' => null]);
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    // Bazada birinchi turgan mudirning HEMIS xodim id'si yo'q (0); ikkinchisida bor.
    $mudir = Teacher::where('full_name', 'MUDIR')->first();
    $mudir->forceFill(['hemis_id' => 0])->save();
    $m2 = $mudir->replicate()->forceFill(['full_name' => 'MUDIR2', 'hemis_id' => 9002, 'login' => 'u' . random_int(100000, 999999),
        'employee_id_number' => (string) random_int(100000, 999999)]);
    $m2->save();
    $m2->assignRole('kafedra_mudiri');
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    $body = ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0];

    $a = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $body)->assertOk()->json('groups.0.units.0.actions.0');
    expect($a['employee_id'])->toBe(9002)->and($a['blocked'])->toBe([]);

    // Faqat id'siz mudir qoldi — to'siq uning nomini aytadi.
    $m2->removeRole('kafedra_mudiri');
    $a = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $body)->assertOk()->json('groups.0.units.0.actions.0');
    expect($a['employee_id'])->toBeNull()
        ->and(implode(' ', $a['blocked']))->toContain("kafedra mudiri MUDIR ning HEMIS xodim id'si LMS'da yo'q");
});

it('joylanmagan kartalar xulosasi (HEMIS\'siz): haftalik va sikl — "Guruh bo\'yicha" joylangan sikl kartasi joylangan', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // Haftalik: hsSetup kartasi joylangan; shu guruhning ma'ruzasi joylanmagan
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'rus', 'students' => 10, 'training_type' => 'lecture', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Biologiya', 'group_name' => 'p/st26-01a (rus)', 'kafedra_name' => 'B']);
    // Sikl (5-kurs Pediatriya, 2 kun): g5-01a joylanmagan, g5-01b "Guruh bo'yicha" (guruh qatorida) joylangan
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    foreach (['g5-01a', 'g5-01b'] as $gr) {
        TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Pediatriya',
            'kafedra_name' => 'P', 'training_type' => 'practice', 'group_name' => $gr]);
    }
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'g5-01b', 'subject_name' => 'Pediatriya', 'start_index' => 7, 'training_type' => 'practice']);
    $summary = fn (array $body, string $role = 'admin', $user = null) => test()->withoutVite()->actingAs($user ?? $s['reg'], 'teacher')
        ->withSession(['active_role' => $role])
        ->postJson(route('admin.timetable.hemis-sync.unplaced-summary', $s['board']), ['today' => '2026-09-19'] + $body);

    // 19.09 — 1-hafta shanbasi; "HEMIS'ga yozish" 2-hafta oxirigacha: faqat 2-hafta
    $u = $summary(['until_week' => 2])->assertOk()->json();
    expect($u['weekly']['count'])->toBe(1)
        ->and(array_map(fn ($i) => [$i['group'], $i['subject'], $i['type'], $i['weeks']], $u['weekly']['items']))
        ->toBe([['p/st26-01a', 'Biologiya', 'lecture', [2]]])
        ->and($u['cycle']['count'])->toBe(1)
        ->and(array_map(fn ($i) => [$i['group'], $i['subject'], $i['days']], $u['cycle']['items']))->toBe([['g5-01a', 'Pediatriya', 2]]);
    // Qamrov va guruh filtri
    expect($summary(['until_week' => 2, 'courses' => [1]])->json('cycle.count'))->toBe(0)
        ->and($summary(['until_week' => 2, 'groups' => ['g5-01a']])->json())->toMatchArray(['weekly' => ['count' => 0, 'items' => []]])
        ->and($summary(['until_week' => 2, 'groups' => ['g5-01a']])->json('cycle.count'))->toBe(1);
    // Ruxsat — solishtirish bilan bir xil rollar (dars jadvaliga kira oladigan boshqa rol — yo'q)
    Role::findOrCreate('kichik_admin', 'web');
    $other = Teacher::where('full_name', 'MUDIR')->first();
    $other->assignRole('kichik_admin');
    $summary(['until_week' => 2], 'kichik_admin', $other)->assertForbidden();
});

it('joylanmagan kartalar: unplaced-summary va hemis-sync javobidagi unplaced bir xil', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'rus', 'students' => 10, 'training_type' => 'lecture', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Biologiya', 'group_name' => 'p/st26-01a (rus)', 'kafedra_name' => 'B']);
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);
    // Sukut (until_week yo'q) — joriy hafta oxirigacha; 21.09 (2-hafta dushanbasi) — 2-hafta qolgan kunlari
    foreach ([[], ['until_week' => 0], ['until_week' => 3]] as $extra) {
        $body = ['faculty_names' => ['F1'], 'specialty_names' => ['stomatologiya'], 'courses' => [1], 'today' => '2026-09-21'] + $extra;
        $summary = $as()->postJson(route('admin.timetable.hemis-sync.unplaced-summary', $s['board']), $body)->assertOk()->json();
        $report = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), $body)->assertOk()->json('unplaced');
        expect($summary)->toBe($report)
            ->and($summary['weekly']['count'])->toBe(['' => 1, 'until_week=0' => 3, 'until_week=3' => 2][http_build_query($extra)]);
    }
});

it('joylanmagan kartalar: ertadan "HEMIS\'ga yozish" haftasi oxirigacha karta-haftalar va sikl kartalari sanaladi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    // Joylangan karta (hsSetup: dushanba 3-para) va shu guruhning joylanmagan kartasi (4 hafta, 14.09 dan)
    $free = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'rus', 'students' => 10, 'training_type' => 'lecture', 'len_half' => 2, 'weeks' => 4,
        'subject_name' => 'Biologiya', 'group_name' => 'p/st26-01a (rus)', 'kafedra_name' => 'B']);
    $report = fn (array $extra = []) => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19'] + $extra)
        ->assertOk()->json('unplaced');

    // Bugun 19.09 (1-hafta shanbasi) — 1-hafta yopiq; semestr oxirigacha 2–4-haftalar
    $u = $report(['until_week' => 0]);
    expect($u['weekly']['count'])->toBe(3)
        ->and($u['weekly']['items'])->toBe([['group' => 'p/st26-01a', 'card_id' => $free->id, 'subject' => 'Biologiya', 'type' => 'lecture', 'weeks' => [2, 3, 4]]])
        ->and($u['cycle'])->toBe(['count' => 0, 'items' => []]);
    // "HEMIS'ga yozish" 2-hafta oxirigacha — faqat 2-hafta
    expect($report(['until_week' => 2])['weekly']['count'])->toBe(1);
    // 3-haftaga ko'chirilgan (kun va parasi bor) va 4-haftada bekor qilingan — joylanmagan emas
    \App\Models\TimetableCardOverride::create(['card_id' => $free->id, 'week' => 3, 'day' => 2, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    \App\Models\TimetableCardOverride::create(['card_id' => $free->id, 'week' => 4, 'cancelled' => true]);
    expect($report(['until_week' => 0])['weekly']['items'][0]['weeks'])->toBe([2]);
    // Hammasi joylangan bo'lsa — bo'sh
    $free->update(['day' => 3, 'pair' => 1]);
    expect($report(['until_week' => 0])['weekly'])->toBe(['count' => 0, 'items' => []]);
});

it('o\'tkazilgan fanlar turi: tugagan (haftalik va sikl), joylanmagan sikl, kartochkasi yo\'q', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    $skipped = fn (array $courses, string $today, string $group) => collect(test()->withoutVite()->actingAs($s['reg'], 'teacher')
        ->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => $courses, 'today' => $today, 'until_week' => 0])
        ->assertOk()->json('groups'))->firstWhere('group', $group)['skipped'] ?? [];

    // (b) Haftalik fan: 4 haftalik karta 14.09 dan — 31.12 da ertadan keyin dars yo'q
    $b = $skipped([1], '2026-12-31', 'p/st26-01a');
    expect(array_column($b, 'kind'))->toBe(['finished'])
        ->and($b[0]['reason'])->toBe('LMS ichki jadvalida ertadan keyin dars yo\'q — HEMIS\'ga tegilmaydi');

    // Haftalik fanning kartasi joylanmagan (ichki jadvalda darsi yo'q) — tugagan emas: unplaced, matni o'zgarmaydi
    $s['card']->update(['day' => null, 'pair' => null]);
    $u = $skipped([1], '2026-09-19', 'p/st26-01a');
    expect(array_column($u, 'kind'))->toBe(['unplaced'])->and($u[0]['reason'])->toBe($b[0]['reason']);
    $s['card']->update(['day' => 1, 'pair' => 3]);

    // (d) LMS doskasida kartochka yo'q (HEMIS'da dars bor)
    $s['card']->update(['subject_name' => 'Boshqa fan']);
    $d = $skipped([1], '2026-09-19', 'p/st26-01a');
    expect(array_column($d, 'kind'))->toBe(['no_card'])
        ->and($d[0]['reason'])->toBe('LMS doskasida kartochka yo\'q — HEMIS\'ga tegilmaydi');

    // Sikl fani (5-kurs Pediatriya, 2 kun)
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Pediatriya',
        'kafedra_name' => 'P', 'training_type' => 'practice', 'group_name' => 'g5-01a', 'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ']);
    hsInsert('groups', ['group_hemis_id' => 2500, 'name' => 'g5-01a', 'curriculum_hemis_id' => 260, 'department_hemis_id' => 1, 'specialty_hemis_id' => 77]);
    hsInsert('specialties', ['specialty_hemis_id' => 77, 'name' => 'davolash ishi']);
    hsInsert('curricula', ['curricula_hemis_id' => 260, 'specialty_hemis_id' => 77, 'department_hemis_id' => 1]);
    hsInsert('semesters', ['curriculum_hemis_id' => 260, 'code' => '19', 'education_year' => '2026', 'level_code' => '15']);
    hsInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 260, 'subject_id' => 900, 'subject_name' => 'Pediatriya',
        'semester_code' => '19', 'semester_name' => '9', 'department_id' => 501, 'department_name' => 'Pediatriya', 'at_semester' => 1,
        'subject_details' => json_encode([['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 12]])]);
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => DB::table('curriculum_subjects')->where('subject_id', 900)->value('id'),
        'week_count' => 2, 'plan_data' => json_encode(['hours' => ['1' => ['13' => 6], '2' => ['13' => 6]]]), 'created_at' => now(), 'updated_at' => now()]);

    // (c) Sikl ichki jadvalda joylanmagan — joylanmagan kartalar ogohlantirishida ham (guruh, fan, kun soni)
    $c = $skipped([5], '2026-09-19', 'g5-01a');
    $cyc = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json('unplaced.cycle');
    expect($cyc['count'])->toBe(1)
        ->and(array_map(fn ($i) => [$i['group'], $i['subject'], $i['days']], $cyc['items']))->toBe([['g5-01a', 'Pediatriya', 2]]);
    expect(array_column($c, 'kind'))->toBe(['unplaced'])
        ->and($c[0]['reason'])->toBe('sikl fani — LMS ichki sikl jadvalida joylanmagan, HEMIS\'ga tegilmaydi');

    // (a) Sikl 21–22.09 da — 30.09 da tugagan
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 7, 'training_type' => 'practice']);
    $a = $skipped([5], '2026-09-30', 'g5-01a');
    expect(test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-30', 'until_week' => 0])
        ->assertOk()->json('unplaced.cycle'))->toBe(['count' => 0, 'items' => []]);
    expect(array_column($a, 'kind'))->toBe(['finished'])
        ->and($a[0]['reason'])->toBe('sikl fani — ichki sikl jadvalida ertadan keyin dars yo\'q, HEMIS\'ga tegilmaydi');
});

it('sikl fani: HEMIS LMS ichki sikl jadvaliga aynan moslashtiriladi (sana, vaqt, tur KTR kuni bo\'yicha, o\'qituvchi, xona)', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // 5-kurs: Pediatriya — sikl fani (2 kun), KTR: 1-kun 2 soat ma'ruza + 4 soat amaliy, 2-kun 6 soat amaliy
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    $lecT = Teacher::where('full_name', 'MUDIR')->first();
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Pediatriya',
        'kafedra_name' => 'P'];
    TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => 'g5-01a',
        'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '71', 'auditorium_name' => '423']);
    TimetableCard::create($base + ['training_type' => 'lecture', 'group_name' => null, 'group_names' => ['g5-01a'],
        'teacher_id' => $lecT->id, 'teacher_name' => 'MUDIR', 'auditorium_code' => 'Y1', 'auditorium_name' => 'Y301']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 7, 'training_type' => 'practice']);   // 21.09

    hsInsert('groups', ['group_hemis_id' => 2500, 'name' => 'g5-01a', 'curriculum_hemis_id' => 260, 'department_hemis_id' => 1, 'specialty_hemis_id' => 77]);
    hsInsert('specialties', ['specialty_hemis_id' => 77, 'name' => 'davolash ishi']);
    hsInsert('curricula', ['curricula_hemis_id' => 260, 'specialty_hemis_id' => 77, 'department_hemis_id' => 1]);
    hsInsert('semesters', ['curriculum_hemis_id' => 260, 'code' => '19', 'education_year' => '2026', 'level_code' => '15']);
    hsInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 260, 'subject_id' => 900, 'subject_name' => 'Pediatriya',
        'semester_code' => '19', 'semester_name' => '9', 'department_id' => 501, 'department_name' => 'Pediatriya', 'at_semester' => 1,
        'subject_details' => json_encode([['trainingType' => ['code' => '11', 'name' => "Ma'ruza"], 'academic_load' => 2],
            ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 10]])]);
    $cs = DB::table('curriculum_subjects')->where('subject_id', 900)->value('id');
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => $cs, 'week_count' => 2,
        'plan_data' => json_encode(['hours' => ['1' => ['11' => 2, '13' => 4], '2' => ['13' => 6]]]), 'created_at' => now(), 'updated_at' => now()]);
    // HEMIS: 21.09 amaliy 08:30 da boshqa o'qituvchi bilan; 23.09 ortiqcha dars
    $row = fn (int $id, string $date, string $st, string $en, string $code, int $emp) => hsInsert('schedules', ['schedule_hemis_id' => $id,
        'subject_id' => 900, 'subject_name' => 'Pediatriya', 'semester_code' => '19', 'group_id' => 2500, 'group_name' => 'g5-01a',
        'training_type_code' => $code, 'training_type_name' => $code === '11' ? "Ma'ruza" : 'Amaliy', 'lesson_date' => $date . ' 00:00:00',
        'lesson_pair_start_time' => $st, 'lesson_pair_end_time' => $en, 'employee_id' => $emp, 'employee_name' => 'E' . $emp,
        'auditorium_code' => '71', 'auditorium_name' => '423']);
    $row(10, '2026-09-21', '10:00:00', '11:20:00', '13', 6006);
    $row(11, '2026-09-23', '10:00:00', '11:20:00', '13', 5001);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $g = collect($res['groups'])->firstWhere('group', 'g5-01a');
    expect($g)->not->toBeNull();
    $u = $g['units'][0];
    expect($u['cycle'])->toBeTrue()->and($u['fixes'])->toBe([]);
    $ops = array_map(fn ($a) => $a['op'] . ' ' . $a['date'] . ' ' . $a['start'] . '-' . $a['end'] . ' ' . $a['code'] . ' ' . ($a['employee_name'] ?? ''), $u['actions']);
    expect($ops)->toBe([
        'create 2026-09-21 08:30-09:50 11 MUDIR',
        'update 2026-09-21 10:00-11:20 13 USTOZ',
        'create 2026-09-21 12:00-13:20 13 USTOZ',
        'create 2026-09-22 08:30-09:50 13 USTOZ',
        'create 2026-09-22 10:00-11:20 13 USTOZ',
        'create 2026-09-22 12:00-13:20 13 USTOZ',
        'delete 2026-09-23 10:00-11:20 13 E5001',
    ]);
    expect($u['before']['hours_diff'])->toBe(['11' => 0, '13' => 0]);
});

// 5-kurs sikli (Pediatriya, 2 kun, 21.09 dan): oqimning ikki guruhchasi (g5-01a — USTOZ, g5-01b — UST2 yoki $sameTeacher bo'lsa
// USTOZ), umumiy ma'ruza (MUDIR), HEMIS'da guruhlar, fan va KTR.
function hsCycleTwoSubgroups(array $s, bool $sameTeacher = false): void
{
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    $lecT = Teacher::where('full_name', 'MUDIR')->first();
    $pa = Teacher::where('full_name', 'USTOZ')->first();
    $pb = $pa->replicate()->forceFill(['hemis_id' => 5002, 'full_name' => 'UST2', 'short_name' => 'UST2', 'login' => 'ust5002', 'employee_id_number' => '5002']);
    $pb->save();
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Pediatriya', 'kafedra_name' => 'P'];
    TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => 'g5-01a', 'teacher_id' => $pa->id, 'teacher_name' => 'USTOZ', 'auditorium_code' => '71', 'auditorium_name' => '423']);
    $tb = $sameTeacher ? $pa : $pb;
    TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => 'g5-01b', 'teacher_id' => $tb->id, 'teacher_name' => $tb->full_name, 'auditorium_code' => '72', 'auditorium_name' => '424']);
    TimetableCard::create($base + ['training_type' => 'lecture', 'group_name' => null, 'group_names' => ['g5-01a', 'g5-01b'],
        'teacher_id' => $lecT->id, 'teacher_name' => 'MUDIR', 'auditorium_code' => 'Y1', 'auditorium_name' => 'Y301']);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Pediatriya', 'start_index' => 7, 'training_type' => 'practice']);   // 21.09
    hsInsert('specialties', ['specialty_hemis_id' => 77, 'name' => 'davolash ishi']);
    hsInsert('curricula', ['curricula_hemis_id' => 260, 'specialty_hemis_id' => 77, 'department_hemis_id' => 1]);
    hsInsert('semesters', ['curriculum_hemis_id' => 260, 'code' => '19', 'education_year' => '2026', 'level_code' => '15']);
    foreach ([2500 => 'g5-01a', 2501 => 'g5-01b'] as $id => $name) {
        hsInsert('groups', ['group_hemis_id' => $id, 'name' => $name, 'curriculum_hemis_id' => 260, 'department_hemis_id' => 1, 'specialty_hemis_id' => 77]);
    }
    hsInsert('curriculum_subjects', ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 260, 'subject_id' => 900, 'subject_name' => 'Pediatriya',
        'semester_code' => '19', 'semester_name' => '9', 'department_id' => 501, 'department_name' => 'Pediatriya', 'at_semester' => 1,
        'subject_details' => json_encode([['trainingType' => ['code' => '11', 'name' => "Ma'ruza"], 'academic_load' => 2],
            ['trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'academic_load' => 10]])]);
    $cs = DB::table('curriculum_subjects')->where('subject_id', 900)->value('id');
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => $cs, 'week_count' => 2,
        'plan_data' => json_encode(['hours' => ['1' => ['11' => 2, '13' => 4], '2' => ['13' => 6]]]), 'created_at' => now(), 'updated_at' => now()]);
}

it('sikl fani: oqimning ikki guruhchasi — umumiy ma\'ruza bir-biriga to\'siq emas, amaliy har guruhchaning o\'z kartasidan', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsCycleTwoSubgroups($s);
    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $acts = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions']);
    foreach (['g5-01a' => 'USTOZ', 'g5-01b' => 'UST2'] as $group => $practiceTeacher) {
        $a = $acts($group);
        expect($a)->toHaveCount(6)
            ->and($a->pluck('blocked')->flatten()->all())->toBe([])
            ->and($a->where('code', '11')->map(fn ($x) => $x['start'] . ' ' . $x['employee_name'] . ' ' . $x['auditorium_name'])->values()->all())
            ->toBe(['08:30 MUDIR Y301'])
            ->and($a->where('code', '13')->pluck('employee_name')->unique()->values()->all())->toBe([$practiceTeacher]);
    }
});

it('sikl: ikki guruhcha bitta o\'qituvchida kesishgan davrda — HEMIS amali to\'siq (JSON\'ga kirmaydi), mudir qo\'yilmaydi', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsCycleTwoSubgroups($s, true);
    $as = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin']);

    // LMS ichki jadvali: ikkala guruhcha bloki to'qnashuvli
    $ic = app(\App\Http\Controllers\Admin\TimetableController::class)->internalConflicts($s['board']);
    expect(array_keys($ic['cycle']))->toBe(['davolash ishi|5|g5-01a|Pediatriya|practice', 'davolash ishi|5|g5-01b|Pediatriya|practice'])
        ->and($ic['cycle']['davolash ishi|5|g5-01a|Pediatriya|practice']['text'])->toBe("O'qituvchi USTOZ band: g5-01b, Pediatriya, 21.09–22.09");
    // Qizil oyna (HEMIS'siz xulosa): "To'qnashuvlar: 2 ta dars", joylanmaganlar orasida emas
    $sum = $as()->postJson(route('admin.timetable.hemis-sync.unplaced-summary', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    expect($sum['conflicts']['count'])->toBe(2)
        ->and($sum['cycle']['count'])->toBe(0)
        ->and(collect($sum['conflicts']['items'])->pluck('group')->unique()->sort()->values()->all())->toBe(['g5-01a', 'g5-01b'])
        ->and($sum['conflicts']['items'][0])->toMatchArray(['kind' => 'cycle', 'type' => 'teacher', 'subject' => 'Pediatriya', 'dates' => '21.09–22.09'])
        // O'tib ketgan to'qnashuv (HEMIS'da yopiq) — sanalmaydi
        ->and($as()->postJson(route('admin.timetable.hemis-sync.unplaced-summary', $s['board']), ['courses' => [5], 'today' => '2026-09-23', 'until_week' => 0])
            ->assertOk()->json('conflicts.count'))->toBe(0);

    $res = $as()->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $acts = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions']);
    foreach (['g5-01a' => 'g5-01b', 'g5-01b' => 'g5-01a'] as $group => $other) {
        $prc = $acts($group)->where('code', '13');
        expect($prc)->not->toBeEmpty()
            // Amaliy — to'siq: o'qituvchi almashtirilmaydi, mudir qo'yilmaydi; kim, qaysi dars bilan, qachon band
            ->and($prc->every(fn ($a) => count($a['blocked']) > 0))->toBeTrue()
            ->and($prc->pluck('employee_name')->unique()->values()->all())->toBe(['USTOZ'])
            ->and($prc->pluck('flags')->flatten()->all())->not->toContain('mudir')
            ->and($prc->first()['blocked'][0])->toContain("o'qituvchi band: USTOZ — {$other} · Pediatriya")
            // Umumiy ma'ruza (bitta o'qituvchi, bitta xona) — to'siq emas
            ->and($acts($group)->where('code', '11')->pluck('blocked')->flatten()->all())->toBe([]);
    }
});

it('sikl: guruhcha smenasi (IV para) — bitta o\'qituvchi ikki guruhchada to\'siq emas; HEMIS amaliy darslari smena vaqtidan, ma\'ruza oqim vaqtida', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsCycleTwoSubgroups($s, true);
    \App\Models\TimetableCyclePlacement::where('board_id', $s['board']->id)->update(['lecture_slots' => json_encode(['shift_g' => ['g5-01b' => 7]])]);
    expect(app(\App\Http\Controllers\Admin\TimetableController::class)->internalConflicts($s['board'])['count'])->toBe(0);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [5], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $slot = (new ReflectionMethod(\App\Http\Controllers\Admin\TimetableController::class, 'hemisSlotTimes'))
        ->invoke(app(\App\Http\Controllers\Admin\TimetableController::class), $s['board']);
    $acts = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions']);
    // KTR: 1-kun ma'ruza 2 + amaliy 4, 2-kun amaliy 6 soat (HEMIS'da har para alohida dars). 01a — oqim vaqtida (amaliy
    // ma'ruzadan keyin: 3–6 va 1–6-soatlar), 01b — amaliy 7-soatdan (7–10 va 7–12-soatlar, IV–VI para).
    expect($acts('g5-01a')->where('code', '13')->pluck('start')->unique()->sort()->values()->all())->toBe([$slot[1][0], $slot[3][0], $slot[5][0]])
        ->and($acts('g5-01b')->where('code', '13')->pluck('start')->unique()->sort()->values()->all())->toBe([$slot[7][0], $slot[9][0], $slot[11][0]])
        ->and($acts('g5-01b')->where('code', '11')->pluck('start')->unique()->values()->all())->toBe(['08:30'])
        ->and($acts('g5-01a')->pluck('blocked')->flatten()->all())->toBe([])
        ->and($acts('g5-01b')->pluck('blocked')->flatten()->all())->toBe([])
        ->and($acts('g5-01b')->where('code', '13')->pluck('employee_name')->unique()->values()->all())->toBe(['USTOZ']);
});

it('xona band — to\'siq bartaraf etiladi: «Kafedra mudiri xonasi» qo\'yiladi, o\'qituvchi o\'zgarmaydi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');
    // Boshqa guruh 28.09 10:00 da 71-xonada (boshqa o'qituvchi)
    hsInsert('schedules', ['schedule_hemis_id' => 70, 'subject_id' => 999, 'subject_name' => 'Boshqa fan', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-28 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 7777, 'employee_name' => 'E7777', 'auditorium_code' => '71', 'auditorium_name' => '423']);

    $u = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json('groups.0.units.0');
    $a = collect($u['actions'])->firstWhere('date', '2026-09-28');
    expect($a['op'])->toBe('create')
        ->and($a['blocked'])->toBe([])
        ->and($a['auditorium_code'])->toBe('KM1')
        ->and($a['employee_id'])->toBe(5001)
        ->and($a['flags'])->toContain('mudir_xonasi')->toContain("to'siq sababli")->not->toContain('mudir')
        ->and($a['resolved'][0]['type'])->toBe('room')
        ->and($a['reason'])->toContain("«Kafedra mudiri xonasi» qo'yildi");
});

it('ikki LMS darsi bir o\'qituvchida bir vaqtda — faqat bittasiga kafedra mudiri qo\'yiladi, ikkinchisi o\'qituvchida qoladi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    // Ikkinchi guruhcha: o'sha o'qituvchi, o'sha vaqt, boshqa xona (umumiy dars emas — to'qnashuv).
    TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name']),
        ['group_name' => 'p/st26-01b (rus)', 'auditorium_code' => '72', 'auditorium_name' => '424']));

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $on = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    $a = $on('p/st26-01a');
    $b = $on('p/st26-01b');
    expect([$a['blocked'], $b['blocked']])->toBe([[], []])
        ->and(collect([$a['employee_id'], $b['employee_id']])->sort()->values()->all())->toBe([5001, 9001]);
});

it('sukut (hemis_mudir_fallback o\'chiq): ikki LMS darsi bir o\'qituvchida bir vaqtda — almashtirilmaydi, ikkalasi to\'siq', function () {
    $s = hsSetup();
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name']),
        ['group_name' => 'p/st26-01b (rus)', 'auditorium_code' => '72', 'auditorium_name' => '424']));
    $run = fn () => test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $res = $run();
    $on = fn (array $res, string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    $a = $on($res, 'p/st26-01a');
    $b = $on($res, 'p/st26-01b');
    expect([$a['employee_id'], $b['employee_id']])->toBe([5001, 5001])
        ->and(array_merge($a['flags'], $b['flags']))->not->toContain('mudir')->not->toContain("to'siq sababli")
        ->and($a['resolved'] ?? [])->toBe([])
        // To'siqda: kim band, qaysi dars bilan, qaysi vaqtda, u darsning xonasi
        ->and($a['blocked'])->toBe(["o'qituvchi band: USTOZ — p/st26-01b · Odam anatomiyasi 10:00-11:20, xona 424"])
        ->and($b['blocked'][0])->toStartWith("o'qituvchi band: USTOZ — p/st26-01a · Odam anatomiyasi 10:00-11:20")
        ->and($a['blocks'][0])->toMatchArray(['type' => 'teacher', 'teacher' => 'USTOZ', 'group' => 'p/st26-01b', 'source' => 'LMS', 'time' => '10:00–11:20']);

    // Sozlama yoqilsa — avvalgidek kafedra mudiri qo'yiladi
    hsMudirFallback($s);
    $res = $run();
    expect(collect([$on($res, 'p/st26-01a')['employee_id'], $on($res, 'p/st26-01b')['employee_id']])->sort()->values()->all())->toBe([5001, 9001]);
});

it('ikki LMS darsi bir xonada bir vaqtda — faqat bittasiga «Kafedra mudiri xonasi» qo\'yiladi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    $other = Teacher::where('full_name', 'USTOZ')->first()->replicate()->forceFill(['hemis_id' => 5002, 'full_name' => 'UST2', 'short_name' => 'UST2',
        'login' => 'ust5002', 'employee_id_number' => '5002']);
    $other->save();
    // Ikkinchi guruhcha: boshqa o'qituvchi, o'sha xona, o'sha vaqt.
    TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'auditorium_code', 'auditorium_name']),
        ['group_name' => 'p/st26-01b (rus)', 'teacher_id' => $other->id, 'teacher_name' => 'UST2']));

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $on = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    $a = $on('p/st26-01a');
    $b = $on('p/st26-01b');
    expect([$a['blocked'], $b['blocked']])->toBe([[], []])
        ->and(collect([$a['auditorium_code'], $b['auditorium_code']])->sort()->values()->all())->toBe(['71', 'KM1'])
        ->and([$a['employee_id'], $b['employee_id']])->toBe([5001, 5002]);
});

it('bartarafdan keyin bandlik haqiqiy qiymatlar bilan: bir xonada ikki xil o\'qituvchi yoki bir o\'qituvchi ikki xonada qolmaydi', function (string $cTeacher, string $cRoom) {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $ust2 = Teacher::where('full_name', 'USTOZ')->first()->replicate()->forceFill(['hemis_id' => 5002, 'full_name' => 'UST2', 'short_name' => 'UST2',
        'login' => 'ust5002', 'employee_id_number' => '5002']);
    $ust2->save();
    $teachers = ['USTOZ' => $s['card']->teacher_id, 'UST2' => $ust2->id];
    // A (01a) = USTOZ · 71 (hsSetup) va B (01b) = USTOZ · 71 — bitta dars; C (01c) ular bilan to'qnashadi. Tekshiruv tartibi A, C, B.
    foreach ([['p/st26-01c', 1598, $cTeacher, $cRoom], ['p/st26-01b', 1597, 'USTOZ', '71']] as [$name, $id, $t, $code]) {
        hsInsert('groups', ['group_hemis_id' => $id, 'name' => $name, 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
        TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
            'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair']),
            ['group_name' => $name . ' (rus)', 'teacher_id' => $teachers[$t], 'teacher_name' => $t, 'auditorium_code' => $code, 'auditorium_name' => 'X' . $code]));
    }

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $acts = collect($res['groups'])->map(fn ($g) => collect($g['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00'));
    expect($acts)->toHaveCount(3)
        ->and($acts->pluck('blocked')->flatten()->all())->toBe([]);
    // Mudir va mudir xonasi ustma-ust tushishi mumkin; qolgan har bir xonada bitta o'qituvchi, har bir o'qituvchi bitta xonada.
    $real = $acts->reject(fn ($a) => $a['auditorium_code'] === 'KM1');
    foreach ($real->groupBy('auditorium_code') as $room => $inRoom) {
        expect($inRoom->pluck('employee_id')->unique()->count())->toBe(1, "xona $room");
    }
    foreach ($acts->reject(fn ($a) => (int) $a['employee_id'] === 9001)->groupBy('employee_id') as $emp => $byEmp) {
        expect($byEmp->pluck('auditorium_code')->unique()->count())->toBe(1, "o'qituvchi $emp");
    }
})->with([
    'avval o\'qituvchi almashtiriladi (C = USTOZ · 73)' => ['USTOZ', '73'],
    'avval xona almashtiriladi (C = UST2 · 71)' => ['UST2', '71'],
]);

it('almashtirishdan keyin ikkinchi tur yangi qiymat bilan qayta tekshiriladi: o\'zgarmagan umumiy dars sherigi bilan to\'qnashuv qolmaydi', function (string $cTeacher, string $cRoom) {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    $ust2 = Teacher::where('full_name', 'USTOZ')->first()->replicate()->forceFill(['hemis_id' => 5002, 'full_name' => 'UST2', 'short_name' => 'UST2',
        'login' => 'ust5002', 'employee_id_number' => '5002']);
    $ust2->save();
    $teachers = ['USTOZ' => $s['card']->teacher_id, 'UST2' => $ust2->id];
    // A (01a) = USTOZ · 71 — HEMIS'da allaqachon shunday (amal yo'q); B (01b) = USTOZ · 71 — A bilan bitta dars; C (01c) B bilan to'qnashadi.
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00');
    foreach ([['p/st26-01b', 1597, 'USTOZ', '71'], ['p/st26-01c', 1598, $cTeacher, $cRoom]] as [$name, $id, $t, $code]) {
        hsInsert('groups', ['group_hemis_id' => $id, 'name' => $name, 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
        TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
            'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair']),
            ['group_name' => $name . ' (rus)', 'teacher_id' => $teachers[$t], 'teacher_name' => $t, 'auditorium_code' => $code, 'auditorium_name' => 'X' . $code]));
    }

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $on = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    expect($on('p/st26-01a'))->toBeNull();   // A o'zgarmaydi: USTOZ · 71
    $acts = collect([$on('p/st26-01b'), $on('p/st26-01c')]);
    expect($acts->pluck('blocked')->flatten()->all())->toBe([]);
    // Yakuniy holat (A bilan birga): mudir va mudir xonasidan tashqari har xonada bitta o'qituvchi, har o'qituvchi bitta xonada.
    $all = $acts->map(fn ($a) => ['employee_id' => (int) $a['employee_id'], 'auditorium_code' => $a['auditorium_code']])
        ->push(['employee_id' => 5001, 'auditorium_code' => '71']);
    foreach ($all->reject(fn ($a) => $a['auditorium_code'] === 'KM1')->groupBy('auditorium_code') as $room => $inRoom) {
        expect($inRoom->pluck('employee_id')->unique()->count())->toBe(1, "xona $room");
    }
    foreach ($all->reject(fn ($a) => $a['employee_id'] === 9001)->groupBy('employee_id') as $emp => $byEmp) {
        expect($byEmp->pluck('auditorium_code')->unique()->count())->toBe(1, "o'qituvchi $emp");
    }
})->with([
    'avval o\'qituvchi almashadi (C = USTOZ · 73)' => ['USTOZ', '73'],
    'avval xona almashadi (C = UST2 · 71)' => ['UST2', '71'],
]);

it('hal qilinmagan to\'siq ikkinchi tur almashgandan keyin qayta tekshiriladi: endi bitta dars bo\'lsa, to\'siq yo\'qoladi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    Teacher::where('hemis_id', 9001)->update(['hemis_id' => 0]);   // mudirni qo'yib bo'lmaydi — o'qituvchi to'sig'i qoladi
    $ust2 = Teacher::where('full_name', 'USTOZ')->first()->replicate()->forceFill(['hemis_id' => 5002, 'full_name' => 'UST2', 'short_name' => 'UST2',
        'login' => 'ust5002', 'employee_id_number' => '5002']);
    $ust2->save();
    $copy = fn (array $o) => TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang',
        'students', 'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair']), $o));
    // A (01a) = USTOZ · 71 (yangi); B (01b) = USTOZ · mudir xonasi — HEMIS'da allaqachon shunday; C (01c) = UST2 · 71.
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    hsInsert('groups', ['group_hemis_id' => 1598, 'name' => 'p/st26-01c', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    $copy(['group_name' => 'p/st26-01b (rus)', 'teacher_id' => $s['card']->teacher_id, 'teacher_name' => 'USTOZ', 'auditorium_code' => null, 'auditorium_name' => null]);
    $copy(['group_name' => 'p/st26-01c (rus)', 'teacher_id' => $ust2->id, 'teacher_name' => 'UST2', 'auditorium_code' => '71', 'auditorium_name' => '423']);
    hsInsert('schedules', ['schedule_hemis_id' => 80, 'subject_id' => 474, 'subject_name' => 'Odam anatomiyasi', 'semester_code' => '11',
        'group_id' => 1597, 'group_name' => 'p/st26-01b', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-21 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'USTOZ', 'auditorium_code' => 'KM1', 'auditorium_name' => 'Kafedra mudiri xonasi']);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $on = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    expect($on('p/st26-01b'))->toBeNull();
    $a = $on('p/st26-01a');
    // Xona band (C) — mudir xonasi; endi A va B — USTOZ mudir xonasida, bitta dars: o'qituvchi to'sig'i qolmaydi.
    expect([$a['employee_id'], $a['auditorium_code']])->toBe([5001, 'KM1'])
        ->and($a['blocked'])->toBe([]);
});

it('HEMIS\'da allaqachon mudir bo\'lsa (ismi boshqacha yozilgan) — ortiqcha o\'zgartirish chiqmaydi: id bo\'yicha solishtiriladi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    hsSchedule(1, '2026-09-14', '10:00:00', '11:20:00');
    hsSchedule(2, '2026-09-21', '10:00:00', '11:20:00', 9001);   // HEMIS'da mudir (ismi "E9001" — LMS'da "MUDIR")
    // USTOZ shu vaqtda boshqa guruhda band — LMS'dagi o'qituvchi o'rniga yana mudir qo'yiladi.
    hsInsert('schedules', ['schedule_hemis_id' => 70, 'subject_id' => 999, 'subject_name' => 'Boshqa fan', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-21 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '98', 'auditorium_name' => '98']);

    $u = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json('groups.0.units.0');
    expect(collect($u['actions'])->first(fn ($a) => $a['date'] === '2026-09-21'))->toBeNull();
});

it('hal qilinmagan to\'siq oxirida yakuniy bandlik bo\'yicha qayta tekshiriladi: keyingi dars mudirga o\'tib, to\'qnashuv yo\'qolsa — to\'siq qolmaydi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // A (01a): fan kafedrasi 502 — mudiri yo'q, almashtirib bo'lmaydi. B (01b): o'sha fan boshqa o'quv rejada, kafedra 501 — mudir bor.
    DB::table('curriculum_subjects')->where('id', $s['csId'])->update(['department_id' => 502]);
    $cs = (array) DB::table('curriculum_subjects')->where('id', $s['csId'])->first();
    unset($cs['id']);
    hsInsert('curriculum_subjects', array_merge($cs, ['curriculum_subject_hemis_id' => 2, 'curricula_hemis_id' => 251, 'department_id' => 501]));
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 251, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name']),
        ['group_name' => 'p/st26-01b (rus)', 'auditorium_code' => '72', 'auditorium_name' => '424']));

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $on = fn (string $group) => collect(collect($res['groups'])->firstWhere('group', $group)['units'][0]['actions'])
        ->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');
    $a = $on('p/st26-01a');
    $b = $on('p/st26-01b');
    // B mudirga o'tdi — USTOZ endi faqat A da: A to'siqsiz.
    expect([$b['employee_id'], $b['blocked']])->toBe([9001, []])
        ->and([$a['employee_id'], $a['blocked']])->toBe([5001, []]);
});

it('umumiy dars bo\'linmaydi: u bilan to\'qnashgan alohida dars kafedra mudiriga o\'tadi, umumiy dars o\'qituvchida qoladi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // A (01a) va B (01b) — USTOZ · 71, bitta umumiy dars; C (01c) — USTOZ · 73. Ro'yxat tartibi A, C, B.
    foreach ([['p/st26-01c', 1598, '73'], ['p/st26-01b', 1597, '71']] as [$name, $id, $code]) {
        hsInsert('groups', ['group_hemis_id' => $id, 'name' => $name, 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
        TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
            'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name']),
            ['group_name' => $name . ' (rus)', 'auditorium_code' => $code, 'auditorium_name' => 'X' . $code]));
    }

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $got = collect($res['groups'])->mapWithKeys(function ($g) {
        $a = collect($g['units'][0]['actions'])->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');

        return [$g['group'] => $a['employee_id'] . ' · ' . $a['auditorium_code'] . (count($a['blocked']) ? ' ⛔' : '')];
    })->sortKeys()->all();
    expect($got)->toBe(['p/st26-01a' => '5001 · 71', 'p/st26-01b' => '5001 · 71', 'p/st26-01c' => '9001 · 73']);
});

it('umumiy darsni almashtirish kerak bo\'lsa — uning hamma guruhlari birga, o\'sha xonada kafedra mudiriga o\'tadi', function () {
    $s = hsSetup();
    hsMudirFallback($s);   // to'qnashuvda kafedra mudiriga almashtirish — doska sozlamasi bilan
    Role::findOrCreate('admin', 'web');
    $s['reg']->assignRole('admin');
    // A (01a) va B (01b) — USTOZ · 71, bitta umumiy dars; USTOZ shu vaqtda HEMIS'da boshqa guruhda (o'zgartirib bo'lmaydi).
    hsInsert('groups', ['group_hemis_id' => 1597, 'name' => 'p/st26-01b', 'curriculum_hemis_id' => 250, 'department_hemis_id' => 1, 'specialty_hemis_id' => 1]);
    TimetableCard::create(array_merge($s['card']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'subject_name', 'kafedra_name', 'day', 'pair', 'teacher_id', 'teacher_name', 'auditorium_code', 'auditorium_name']),
        ['group_name' => 'p/st26-01b (rus)']));
    hsInsert('schedules', ['schedule_hemis_id' => 70, 'subject_id' => 999, 'subject_name' => 'Boshqa fan', 'semester_code' => '11',
        'group_id' => 4444, 'group_name' => 'p/st26-09a', 'training_type_code' => '13', 'training_type_name' => 'Amaliy',
        'lesson_date' => '2026-09-21 00:00:00', 'lesson_pair_start_time' => '10:00:00', 'lesson_pair_end_time' => '11:20:00',
        'employee_id' => 5001, 'employee_name' => 'E5001', 'auditorium_code' => '98', 'auditorium_name' => '98']);

    $res = test()->withoutVite()->actingAs($s['reg'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.hemis-sync', $s['board']), ['courses' => [1], 'today' => '2026-09-19', 'until_week' => 0])
        ->assertOk()->json();
    $got = collect($res['groups'])->mapWithKeys(function ($g) {
        $a = collect($g['units'][0]['actions'])->first(fn ($a) => $a['date'] === '2026-09-21' && $a['start'] === '10:00');

        return [$g['group'] => $a['employee_id'] . ' · ' . $a['auditorium_code'] . (count($a['blocked']) ? ' ⛔' : '')];
    })->sortKeys()->all();
    expect($got)->toBe(['p/st26-01a' => '9001 · 71', 'p/st26-01b' => '9001 · 71']);
});
