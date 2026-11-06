<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Sikl fan sozlamasi fakultet va o'quv reja bo'yicha: (fakultet + o'quv reja) → (fakultet) → (umumiy). Umumiy yozuv — ('', 0).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-05 09:00', 'Asia/Tashkent')));

function scsTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Ichki kasalliklar kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

function scsAs(Teacher $t, string $role = 'admin')
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/**
 * Davolash ishi, 4-kurs, "Ichki kasalliklar" (sikl, umumiy 18 kun). Oqimlar:
 *  - 2-son davolash / 1-oqim: d2/d23-01a, 01b (o'quv reja 501);
 *  - Xalqaro / 1-oqim: xd/22-01a, 01b (601 — 8-semestr); Xalqaro / 2-oqim: xd/23-01a, 01b (701 — 7-semestr);
 *  - Xalqaro / 3-oqim: xd/22-03a (601) + xd/23-03a (701) — aralash.
 * Har oqimda blok joylangan (har xil kunlardan).
 */
function scsSetup(): array
{
    Role::findOrCreate('admin', 'web');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 7, 'weeks' => 30, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'subject_name' => 'Ichki kasalliklar', 'mode' => 'cycle', 'cycle_days' => 18]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2023/2024', 'level_code' => '14', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Ichki kasalliklar',
        'semester' => 7, 'lecture' => 26, 'practice' => 100, 'created_at' => now(), 'updated_at' => now()]);
    $d2 = '2-son davolash fakulteti';
    $xd = 'Xalqaro ta\'lim fakulteti';
    $streams = [
        [$d2, '1-oqim', ['d2/d23-01a' => 501, 'd2/d23-01b' => 501]],
        [$xd, '1-oqim', ['xd/22-01a' => 601, 'xd/22-01b' => 601]],
        [$xd, '2-oqim', ['xd/23-01a' => 701, 'xd/23-01b' => 701]],
        [$xd, '3-oqim', ['xd/22-03a' => 601, 'xd/23-03a' => 701]],
    ];
    $gid = 1000;
    foreach ($streams as [$fac, $oqim, $groups]) {
        foreach ($groups as $g => $cid) {
            DB::table('groups')->insert(['group_hemis_id' => ++$gid, 'name' => $g, 'department_hemis_id' => 1, 'department_name' => $fac,
                'department_code' => 'F', 'department_structure_type_code' => '11', 'department_structure_type_name' => 'Fakultet',
                'department_locality_type_code' => '11', 'department_locality_type_name' => 'M', 'department_active' => true,
                'specialty_hemis_id' => 1, 'specialty_code' => 'S', 'specialty_name' => 'davolash ishi', 'education_lang_code' => '11',
                'education_lang_name' => 'uz', 'curriculum_hemis_id' => $cid, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'davolash ishi', 'course' => 4,
                'oqim_label' => $oqim, 'lang' => 'uz', 'len_half' => 2, 'weeks' => 30, 'subject_name' => 'Ichki kasalliklar',
                'kafedra_name' => 'Ichki kasalliklar kafedrasi', 'students' => 10, 'training_type' => 'practice', 'group_name' => $g]);
        }
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'davolash ishi', 'course' => 4,
            'oqim_label' => $oqim, 'lang' => 'uz', 'len_half' => 2, 'weeks' => 30, 'subject_name' => 'Ichki kasalliklar',
            'kafedra_name' => 'Ichki kasalliklar kafedrasi', 'students' => 20, 'training_type' => 'lecture', 'group_names' => array_keys($groups)]);
    }
    $placements = [];
    foreach ([[$d2, '1-oqim', 0], [$xd, '1-oqim', 25], [$xd, '2-oqim', 50], [$xd, '3-oqim', 75]] as [$fac, $oqim, $start]) {
        $placements[$fac . '|' . $oqim] = TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
            'group_name' => $fac . ' / ' . $oqim, 'subject_name' => 'Ichki kasalliklar', 'start_index' => $start, 'training_type' => 'practice']);
    }

    return ['board' => $board, 'admin' => scsTeacher('ADMIN', 'admin'), 'd2' => $d2, 'xd' => $xd, 'placements' => $placements];
}

/** Oqim => [blok kunlari, ogohlantirish] — sikl rejasidan (tashqi qatlam, oqim qatorlari). */
function scsPlan(array $s): array
{
    $plan = scsAs($s['admin'])->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'external', 'view' => 'flow'])->assertOk()->json();
    $out = [];
    foreach ($plan['rows'] as $row) {
        foreach ($row['blocks'] ?? [] as $b) {
            if ($b['type'] === 'practice') {
                $out[$row['group']] = [$b['days'], $b['scope_note'] ?? null];
            }
        }
    }
    ksort($out);

    return $out;
}

/** Sikl birliklari (bandlik, HEMIS, kun rejasi manbai): guruhcha => kunlar. */
function scsUnits(array $s): array
{
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    request()->attributes->remove('timetable.cycle_setting_index.' . $s['board']->id);
    $ctl = app(TimetableController::class);
    [$units] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $s['board']);
    $out = [];
    foreach ($units as $u) {
        $out[$u['group']] = (int) $u['days'];
    }
    ksort($out);

    return $out;
}

function scsSave(array $s, array $body)
{
    return scsAs($s['admin'])->postJson(route('admin.timetable.subject-setting.save', $s['board']), $body + ['specialty_name' => 'davolash ishi',
        'course' => 4, 'subject_name' => 'Ichki kasalliklar', 'mode' => 'cycle']);
}

it('qidiruv ustuvorligi: (fakultet + o\'quv reja) → (fakultet) → (umumiy); boshqa fakultet o\'zgarmaydi; aralash oqim — fakultet, ogohlantirish bilan', function () {
    $s = scsSetup();
    $d2 = $s['d2'] . ' / 1-oqim';
    [$x1, $x2, $x3] = [$s['xd'] . ' / 1-oqim', $s['xd'] . ' / 2-oqim', $s['xd'] . ' / 3-oqim'];
    expect(scsPlan($s))->toBe([$d2 => [18, null], $x1 => [18, null], $x2 => [18, null], $x3 => [18, null]]);
    // Fakultet darajasi — faqat Xalqaro.
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15])->assertOk();
    expect(scsPlan($s))->toBe([$d2 => [18, null], $x1 => [15, null], $x2 => [15, null], $x3 => [15, null]]);
    // O'quv reja darajasi: xd/22 — 13, xd/23 — 21; aralash oqim — fakultet (15) va ogohlantirish.
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    expect(scsPlan($s))->toBe([$d2 => [18, null], $x1 => [13, null], $x2 => [21, null], $x3 => [15, "o'quv rejalar har xil — fakultet sozlamasi olindi"]]);
    // Sikl birliklari (bandlik, HEMIS solishtirish, KTR kun rejasi) — xuddi shu; guruhcha qatori oqimniki bilan bir xil.
    expect(scsUnits($s))->toBe(['d2/d23-01a' => 18, 'd2/d23-01b' => 18, 'xd/22-01a' => 13, 'xd/22-01b' => 13, 'xd/22-03a' => 15,
        'xd/23-01a' => 21, 'xd/23-01b' => 21, 'xd/23-03a' => 15]);
    // Fakultet qiymati olib tashlansa (bo'sh) — aralash oqim umumiyga (18) tushadi, ogohlantirish qoladi; o'quv rejalar o'z qiymatida.
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => null])->assertOk();
    expect(scsPlan($s))->toBe([$d2 => [18, null], $x1 => [13, null], $x2 => [21, null], $x3 => [18, "o'quv rejalar har xil — fakultet sozlamasi olindi"]]);
    // Umumiy o'zgarsa — aniqlovchisi yo'q oqimlarga (2-son davolash, aralash) ta'sir qiladi, aniqlovchililarga — yo'q.
    scsSave($s, ['cycle_days' => 20])->assertOk();
    expect(scsPlan($s))->toBe([$d2 => [20, null], $x1 => [13, null], $x2 => [21, null], $x3 => [20, "o'quv rejalar har xil — fakultet sozlamasi olindi"]]);
    // Saqlash: umumiy yozuv bitta, aniqlovchilar alohida; ro'yxatda (mijoz) — umumiy yozuv ichida "scoped".
    $rows = TimetableSubjectSetting::where('board_id', $s['board']->id)->orderBy('curriculum_hemis_id')->get(['faculty_name', 'curriculum_hemis_id', 'cycle_days', 'mode'])->toArray();
    expect($rows)->toBe([
        ['faculty_name' => '', 'curriculum_hemis_id' => 0, 'cycle_days' => 20, 'mode' => 'cycle'],
        ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13, 'mode' => 'cycle'],
        ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'mode' => 'cycle'],
    ]);
    $data = scsAs($s['admin'])->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()->json('subject_settings');
    expect($data)->toHaveCount(1)->and($data[0]['cycle_days'])->toBe(20)
        ->and($data[0]['scoped'])->toBe([['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13, 'lecture' => null],
            ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'lecture' => null]]);
});

it('saqlashdan oldin (dry_run): faqat ta\'sirlangan joylangan bloklar ro\'yxati, hech narsa saqlanmaydi; boshqa fakultet bloklari ro\'yxatda yo\'q', function () {
    $s = scsSetup();
    $dry = scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21, 'dry_run' => 1])->assertOk()->json();
    expect($dry['dry_run'])->toBeTrue()
        ->and(array_map(fn ($a) => [$a['row'], $a['faculty'], $a['from'], $a['to']], $dry['affected']))
        ->toBe([[$s['xd'] . ' / 2-oqim', $s['xd'], 18, 21]])
        ->and(TimetableSubjectSetting::count())->toBe(1);
    // Fakultet darajasi: Xalqaro oqimlari (aralash ham), 2-son davolash — yo'q.
    $fac = scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15, 'dry_run' => 1])->assertOk()->json('affected');
    expect(array_column($fac, 'row'))->toBe([$s['xd'] . ' / 1-oqim', $s['xd'] . ' / 2-oqim', $s['xd'] . ' / 3-oqim']);
    // Qiymat o'zgarmasa — ro'yxat bo'sh.
    expect(scsSave($s, ['cycle_days' => 18, 'dry_run' => 1])->assertOk()->json('affected'))->toBe([]);
    // Haqiqiy saqlash — o'sha ro'yxat, yozuv paydo bo'ladi.
    $real = scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk()->json();
    expect($real['dry_run'])->toBeFalse()->and(array_column($real['affected'], 'to'))->toBe([21])
        ->and(TimetableSubjectSetting::count())->toBe(2);
});

it('aniqlovchili sozlama faqat sikl rejimidagi fanga; fakultetsiz o\'quv reja — yo\'q; fan rejimi faqat umumiy yozuvda', function () {
    $s = scsSetup();
    scsSave($s, ['curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertUnprocessable();
    TimetableSubjectSetting::query()->update(['mode' => 'normal', 'cycle_days' => null]);
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 21])->assertUnprocessable()
        ->assertJsonPath('error', 'Fakultet / o‘quv reja bo‘yicha kunlar soni faqat sikl rejimidagi fanga beriladi.');
    // Umumiy yozuv rejimi o'zgarsa — aniqlovchili yozuvlarga tegilmaydi va ular rejim manbai emas.
    TimetableSubjectSetting::query()->update(['mode' => 'cycle', 'cycle_days' => 18]);
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15])->assertOk();
    scsSave($s, ['mode' => 'normal', 'cycle_days' => null])->assertOk();
    expect(TimetableSubjectSetting::general()->count())->toBe(0)->and(TimetableSubjectSetting::count())->toBe(1)
        ->and(scsPlan($s))->toBe([]);
});

it('joylash (cyclePlaceOne) va qo\'shni bloklar kunlari — qator tarkibi bo\'yicha', function () {
    $s = scsSetup();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13])->assertOk();
    $place = fn (string $row, int $start) => scsAs($s['admin'])->postJson(route('admin.timetable.cycle-place', $s['board']), [
        'specialty_name' => 'davolash ishi', 'course' => 4, 'group_name' => $row, 'subject_name' => 'Ichki kasalliklar',
        'training_type' => 'practice', 'view' => 'flow', 'layer' => 'external', 'action' => 'place', 'start_index' => $start, 'pair' => 1]);
    $place($s['xd'] . ' / 2-oqim', 100)->assertOk();
    $place($s['xd'] . ' / 1-oqim', 100)->assertOk();
    $blocks = [];
    foreach (scsAs($s['admin'])->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'external', 'view' => 'flow'])->json('rows') as $row) {
        foreach ($row['blocks'] ?? [] as $b) {
            $blocks[$row['group']] = $b;
        }
    }
    // Blok uzunligi (kalendar kunlari, yakshanbalar bilan) — o'z sozlamasi bo'yicha: 21 va 13 o'quv kuni.
    $work = fn ($b) => $b['days'];
    expect($work($blocks[$s['xd'] . ' / 2-oqim']))->toBe(21)->and($work($blocks[$s['xd'] . ' / 1-oqim']))->toBe(13)
        ->and($blocks[$s['xd'] . ' / 2-oqim']['to'] - $blocks[$s['xd'] . ' / 2-oqim']['from'])
        ->toBeGreaterThan($blocks[$s['xd'] . ' / 1-oqim']['to'] - $blocks[$s['xd'] . ' / 1-oqim']['from']);
});

it('"Darslar" oynasi: fakultetlar va o\'quv rejalar (nomi, prefiks, guruhlar soni, saqlangan kunlar)', function () {
    $s = scsSetup();
    DB::table('curricula')->insert(['curricula_hemis_id' => 701, 'name' => 'Davolash ishi (xalqaro, 2023)', 'specialty_hemis_id' => 1,
        'department_hemis_id' => 1, 'education_year_code' => '2026', 'education_year_name' => '2026-2027', 'current' => true,
        'education_type_code' => '11', 'education_type_name' => 'B', 'education_form_code' => '11', 'education_form_name' => 'K',
        'marking_system_code' => '11', 'marking_system_name' => 'M', 'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4,
        'semester_count' => 12, 'education_period' => 6, 'created_at' => now(), 'updated_at' => now()]);
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    $j = scsAs($s['admin'])->getJson(route('admin.timetable.subject-setting.scopes', $s['board']) . '?' . http_build_query([
        'specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => 'Ichki kasalliklar']))->assertOk()->json();
    expect($j['mode'])->toBe('cycle')->and($j['days'])->toBe(18)
        ->and(array_column($j['faculties'], 'name'))->toBe([$s['d2'], $s['xd']]);
    $xd = $j['faculties'][1];
    expect($xd['days'])->toBeNull()
        ->and(array_map(fn ($c) => [$c['id'], $c['prefix'], $c['groups'], $c['days']], $xd['curricula']))
        ->toBe([[601, 'xd/22', 3, null], [701, 'xd/23', 3, 21]])
        ->and($xd['curricula'][1]['name'])->toBe('Davolash ishi (xalqaro, 2023)');
});

it('regressiya: boshqa fakultet (2-son davolash) bloki — kunlar, joylashuv, ma\'ruza joyi va kun rejasi bit-bit o\'zgarmaydi', function () {
    $s = scsSetup();
    $block = function (string $layer) use ($s) {
        foreach (scsAs($s['admin'])->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => $layer])->assertOk()->json('rows') as $row) {
            if (str_starts_with((string) $row['group'], 'd2/d23-01a') || $row['group'] === $s['d2'] . ' / 1-oqim') {
                return $row['blocks'] ?? [];
            }
        }

        return null;
    };
    $before = [$block('external'), $block('internal')];
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 701, 'cycle_days' => 21])->assertOk();
    expect($before[0])->not->toBeEmpty()->and([$block('external'), $block('internal')])->toBe($before)
        ->and($s['placements'][$s['d2'] . '|1-oqim']->fresh()->start_index)->toBe(0);
});

it('HEMIS fan nomi tuzatilganda: fakultet yozuvi yangi nomga o\'tadi — yangi nomning umumiy yozuvi bilan to\'qnashmaydi', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 7, 'weeks' => 30, 'settings' => ['semester_start' => '2026-09-14']]);
    $mk = fn (string $subject, string $fac, int $cid, int $days) => TimetableSubjectSetting::create(['board_id' => $board->id,
        'specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => $subject, 'faculty_name' => $fac, 'curriculum_hemis_id' => $cid,
        'mode' => 'cycle', 'cycle_days' => $days]);
    $mk('Ichki kasallik', '', 0, 18);
    $mk('Ichki kasallik', 'Xalqaro ta\'lim fakulteti', 0, 21);
    $mk('Ichki kasallik', 'Xalqaro ta\'lim fakulteti', 601, 13);
    $mk('Ichki kasalliklar', '', 0, 10);                                   // yangi nomning umumiy yozuvi allaqachon bor
    $mk('Ichki kasalliklar', 'Xalqaro ta\'lim fakulteti', 601, 14);        // va shu o'quv reja yozuvi ham

    $m = new ReflectionMethod(TimetableController::class, 'renameSubjectRefs');
    $m->invoke(app(TimetableController::class), $board, [['davolash ishi', 4, 'Ichki kasallik', 'Ichki kasalliklar']], []);

    $rows = TimetableSubjectSetting::where('board_id', $board->id)->orderBy('faculty_name')->orderBy('curriculum_hemis_id')->get()
        ->map(fn ($s) => [$s->subject_name, $s->faculty_name, $s->curriculum_hemis_id, $s->cycle_days])->all();
    expect($rows)->toBe([
        ['Ichki kasalliklar', '', 0, 10],                                   // umumiy: yangi nomniki qoladi
        ['Ichki kasalliklar', 'Xalqaro ta\'lim fakulteti', 0, 21],          // fakultet yozuvi — ko'chdi (o'chib ketmadi)
        ['Ichki kasalliklar', 'Xalqaro ta\'lim fakulteti', 601, 14],        // o'quv reja: yangi nomniki qoladi
    ]);
});

it('guruhchaning o\'z joylashuvi (aralash oqimda): uzunlik guruhcha qatori bo\'yicha — sikl rejasi, birliklar va saqlash ro\'yxati bir xil (Codex)', function () {
    $s = scsSetup();
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13])->assertOk();
    // Aralash 3-oqimdagi xd/22-03a (601) — o'z joylashuvi bilan; xd/23-03a — oqimnikidan.
    $own = TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'group_name' => 'xd/22-03a', 'subject_name' => 'Ichki kasalliklar', 'start_index' => 110, 'training_type' => 'practice']);
    $internal = [];
    foreach (scsAs($s['admin'])->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'])->assertOk()->json('rows') as $row) {
        foreach ($row['blocks'] ?? [] as $b) {
            if ($b['type'] === 'practice' && in_array($row['group'], ['xd/22-03a', 'xd/23-03a'], true)) {
                $internal[$row['group']] = [$b['days'], $b['from'], $b['scope_note'] ?? null];
            }
        }
    }
    ksort($internal);
    expect($internal)->toBe(['xd/22-03a' => [13, 110, null], 'xd/23-03a' => [15, 75, "o'quv rejalar har xil — fakultet sozlamasi olindi"]]);
    $units = scsUnits($s);
    expect([$units['xd/22-03a'], $units['xd/23-03a']])->toBe([13, 15]);
    // Saqlashdan oldingi ro'yxat ham shu qator bo'yicha: 601 o'zgarsa — guruhchaning o'z bloki (13 → 14).
    $dry = scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 14, 'dry_run' => 1])->assertOk()->json('affected');
    expect(collect($dry)->firstWhere('placement_id', $own->id))->toMatchArray(['row' => 'xd/22-03a', 'from' => 13, 'to' => 14]);
});

it('"A + B" umumiy amaliy kartasi (group_names bo\'sh): o\'quv reja har guruhcha bo\'yicha aniqlanadi (Codex)', function () {
    $s = scsSetup();
    $gid = 2000;
    foreach (['xd/22-05a', 'xd/22-05b'] as $g) {
        DB::table('groups')->insert(['group_hemis_id' => ++$gid, 'name' => $g, 'department_hemis_id' => 1, 'department_name' => $s['xd'],
            'department_code' => 'F', 'department_structure_type_code' => '11', 'department_structure_type_name' => 'Fakultet',
            'department_locality_type_code' => '11', 'department_locality_type_name' => 'M', 'department_active' => true,
            'specialty_hemis_id' => 1, 'specialty_code' => 'S', 'specialty_name' => 'davolash ishi', 'education_lang_code' => '11',
            'education_lang_name' => 'uz', 'curriculum_hemis_id' => 601, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
    }
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => $s['xd'], 'specialty_name' => 'davolash ishi', 'course' => 4,
        'oqim_label' => '4-oqim', 'lang' => 'uz', 'len_half' => 2, 'weeks' => 30, 'subject_name' => 'Ichki kasalliklar',
        'kafedra_name' => 'Ichki kasalliklar kafedrasi', 'students' => 20, 'training_type' => 'practice', 'group_name' => 'xd/22-05a + xd/22-05b']);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'group_name' => $s['xd'] . ' / 4-oqim', 'subject_name' => 'Ichki kasalliklar', 'start_index' => 110, 'training_type' => 'practice']);
    scsSave($s, ['faculty_name' => $s['xd'], 'cycle_days' => 15])->assertOk();
    scsSave($s, ['faculty_name' => $s['xd'], 'curriculum_hemis_id' => 601, 'cycle_days' => 13])->assertOk();
    expect(scsPlan($s)[$s['xd'] . ' / 4-oqim'])->toBe([13, null]);
    $units = scsUnits($s);
    expect([$units['xd/22-05a'], $units['xd/22-05b']])->toBe([13, 13]);
    // "Darslar" oynasida ham ikkala guruh 601 o'quv rejasida (aniqlanmagan qator yo'q).
    $xd = collect(scsAs($s['admin'])->getJson(route('admin.timetable.subject-setting.scopes', $s['board']) . '?' . http_build_query([
        'specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => 'Ichki kasalliklar']))->assertOk()->json('faculties'))->firstWhere('name', $s['xd']);
    expect(array_map(fn ($c) => [$c['id'], $c['groups']], $xd['curricula']))->toBe([[601, 5], [701, 3]]);
});
