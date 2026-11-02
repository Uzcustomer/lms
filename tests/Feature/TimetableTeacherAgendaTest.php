<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// O'qituvchi kun/hafta jadvali (teacher-agenda): darslar, bo'sh paralar, qamrov va bo'sh paraga ko'chirish.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-02 09:00', 'Asia/Tashkent')));

function agTeacher(string $name, ?string $role = null, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Farmakologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ], $extra))->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

function agAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/**
 * 19.11.2026 (payshanba): MUXAMMADIYEV S. B. — sikl amaliyoti d1/d22-01a (17.11 dan) va d1/d22-01b (19.11 dan), ikkalasi I paradan
 * (6 soat, I–III para); haftalik pp/2024-01a va pp/2024-01b (Farmakologiya) — ikkalasi IV parada. Bo'sh: V va VI para.
 * pp/2024-01b guruhi V parada boshqa o'qituvchida (Anatomiya); pp/2024-01b xonasi — 10 o'rin, talabalar 12.
 */
function agSetup(): array
{
    Role::findOrCreate('admin', 'web');
    DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Farmakologiya kafedrasi', 'code' => 'F1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $mux = agTeacher('MUXAMMADIYEV S. B.', 'oqituvchi');
    $head = agTeacher('MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 501]);
    $admin = agTeacher('ADMIN', 'admin');
    $other = agTeacher('BOSHQA B.', 'oqituvchi');
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $r1 = Auditorium::create(['code' => 'R1', 'name' => '101-xona', 'volume' => 30, 'active' => true]);
    $r2 = Auditorium::create(['code' => 'R2', 'name' => '102-xona', 'volume' => 10, 'active' => true]);
    $cyc = fn (string $g) => TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
        'teacher_id' => $mux->id, 'teacher_name' => 'MUXAMMADIYEV S. B.']);
    $cyc('d1/d22-01a');
    $cyc('d1/d22-01b');
    // Kalendar indeksi (sana => indeks) — sikl bloklari boshi.
    $ctl = app(TimetableController::class);
    [, [$dates, $work]] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $board);
    $ci = fn (string $d) => collect($work)->first(fn ($i) => $dates[$i]->toDateString() === $d);
    foreach (['d1/d22-01a' => '2026-11-17', 'd1/d22-01b' => '2026-11-19'] as $row => $d) {
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'group_name' => $row, 'subject_name' => 'Klinik farmakologiya', 'start_index' => $ci($d), 'training_type' => 'practice']);
    }
    $weekly = fn (string $g, string $subject, Teacher $t, int $pair, ?Auditorium $room, int $students = 12) => TimetableCard::create([
        'board_id' => $board->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya', 'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz',
        'students' => $students, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15, 'subject_name' => $subject, 'group_name' => $g,
        'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $t->id, 'teacher_name' => $t->full_name, 'day' => 4, 'pair' => $pair,
        'auditorium_code' => $room?->code, 'auditorium_name' => $room?->name]);
    $ppA = $weekly('pp/2024-01a', 'Farmakologiya', $mux, 7, $r1);
    $ppB = $weekly('pp/2024-01b', 'Farmakologiya', $mux, 7, $r2);
    $weekly('pp/2024-01b', 'Anatomiya', $other, 9, null);
    // 19.11 — kartaning qaysi haftasi (kalendar bo'yicha).
    $week = collect(range(1, 15))->first(fn ($w) => $ctl->weeklyProposalDates($ppB, ['week' => $w, 'day' => 4])[1] === '2026-11-19');

    return compact('board', 'mux', 'head', 'admin', 'other', 'ppA', 'ppB', 'r1', 'r2', 'week');
}

function agGet(array $s, Teacher $as, string $role, array $q = []): array
{
    return agAs($as, $role)->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query($q + [
        'teacher_id' => $s['mux']->id, 'date' => '2026-11-19']))->assertOk()->json();
}

it('agenda: 19.11 — sikl d1/d22-01a, 01b va haftalik pp/2024-01a/01b, to\'qnashuvlar va bo\'sh paralar', function () {
    $s = agSetup();
    $j = agGet($s, $s['admin'], 'admin');
    expect($j['title'])->toBe('MUXAMMADIYEV S. B. — 19.11.2026 (payshanba)')
        ->and(array_column($j['pairs'], 'label'))->toBe(['I para', 'II para', 'III para', 'IV para', 'V para', 'VI para'])
        ->and([$j['pairs'][0]['start'], $j['pairs'][5]['end']])->toBe(['08:30', '17:50']);
    $rows = array_map(fn ($e) => [$e['kind'], $e['type'], implode(', ', $e['groups']), $e['subject'], $e['start'] . '–' . $e['end'], $e['conflict']], $j['entries']);
    expect($rows)->toBe([
        ['cycle', 'A', 'd1/d22-01a', 'Klinik farmakologiya', '08:30–13:20', true],
        ['cycle', 'A', 'd1/d22-01b', 'Klinik farmakologiya', '08:30–13:20', true],
        ['weekly', 'A', 'pp/2024-01a', 'Farmakologiya', '13:30–14:50', true],
        ['weekly', 'A', 'pp/2024-01b', 'Farmakologiya', '13:30–14:50', true],
    ])
        ->and($j['free'])->toBe(['2026-11-19' => [5, 6]])
        ->and($j['entries'][3])->toMatchArray(['room' => '102-xona', 'room_code' => 'R2', 'week' => $s['week'], 'key' => 'w|' . $s['ppB']->id . '|' . $s['week'],
            'hidden' => false, 'layer' => 'internal']);
    // Hafta: dushanba–shanba, 17.11 dan d1/d22-01a.
    $w = agGet($s, $s['admin'], 'admin', ['range' => 'week']);
    expect(array_column($w['dates'], 'date'))->toBe(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19', '2026-11-20', '2026-11-21'])
        ->and(collect($w['entries'])->where('date', '2026-11-17')->pluck('groups')->flatten()->all())->toBe(['d1/d22-01a'])
        ->and($w['free'])->toBe(['2026-11-16' => [1, 2, 3, 4, 5, 6], '2026-11-17' => [4, 5, 6], '2026-11-18' => [4, 5, 6],
            '2026-11-19' => [5, 6], '2026-11-20' => [4, 5, 6], '2026-11-21' => [4, 5, 6]])
        ->and($w['title'])->toBe('MUXAMMADIYEV S. B. — 16.11–21.11.2026');
});

it('agenda: bo\'sh paraga ko\'chirish — guruh band, xona sig\'maydi (sababi bilan), bo\'sh xona taklifi; ko\'chirgach to\'qnashuv yo\'q', function () {
    $s = agSetup();
    $key = 'w|' . $s['ppB']->id . '|' . $s['week'];
    $j = agGet($s, $s['admin'], 'admin', ['move' => $key]);
    $opt = $j['move']['options']['2026-11-19'];
    expect($j['move'])->toMatchArray(['kind' => 'weekly', 'via' => 'apply', 'why' => null, 'week' => $s['week'], 'card_id' => $s['ppB']->id])
        ->and($j['move']['rest_weeks'][0])->toBe($s['week'])
        // V para: guruh band (boshqa o'qituvchida Anatomiya) va xona sig'maydi.
        ->and($opt[5]['ok'])->toBeFalse()
        ->and($opt[5]['why'])->toContain('Guruh band: pp/2024-01b')->toContain("Xona sig'maydi: 10 o'rin, 12 talaba (+2)")
        // VI para: faqat xona sig'maydi — reyting bo'yicha shu vaqtda bo'sh va sig'adigan xona taklif qilinadi.
        ->and($opt[6])->toMatchArray(['ok' => true, 'why' => "Xona sig'maydi: 10 o'rin, 12 talaba (+2)"])
        ->and($opt[6]['room'])->toMatchArray(['code' => 'R1', 'name' => '101-xona', 'volume' => 30]);
    // Sig'adigan bo'sh xona yo'q — VI para ham o'chiq, sababi bilan.
    $s['r1']->update(['volume' => 11]);
    $opt = agGet($s, $s['admin'], 'admin', ['move' => $key])['move']['options']['2026-11-19'];
    expect($opt[6])->toBe(['ok' => false, 'why' => "Xona sig'maydi: 10 o'rin, 12 talaba (+2)", 'room' => null, 'day' => 4, 'pair' => 11]);
    $s['r1']->update(['volume' => 30]);

    // Admin: VI paraga (101-xona bilan) — darhol; endi haftalik darslar to'qnashmaydi.
    agAs($s['admin'], 'admin')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['week' => $s['week'], 'card_id' => $s['ppB']->id,
        'type' => 'agenda', 'title' => "VI paraga ko'chirish", 'actions' => [['op' => 'move', 'card_id' => $s['ppB']->id, 'week' => $s['week'],
            'day' => 4, 'pair' => 11, 'auditorium_code' => 'R1']]])->assertOk();
    $after = agGet($s, $s['admin'], 'admin');
    $weekly = collect($after['entries'])->where('kind', 'weekly')->values();
    expect($weekly->map(fn ($e) => [implode(',', $e['groups']), $e['start'], $e['room'], $e['conflict']])->all())
        ->toBe([['pp/2024-01a', '13:30', '101-xona', false], ['pp/2024-01b', '16:30', '101-xona', false]])
        ->and($after['free'])->toBe(['2026-11-19' => [5]]);
});

it('agenda: sikl amaliyoti — kun o\'zgarmaydi, kun oxirigacha sig\'maydigan para o\'chiq; bugungi/o\'tgan dars ko\'chirilmaydi', function () {
    $s = agSetup();
    $units = fn () => collect(agGet($s, $s['admin'], 'admin')['entries'])->where('kind', 'cycle')->values();
    $key = $units()[1]['key'];
    $j = agGet($s, $s['admin'], 'admin', ['move' => $key]);
    expect($j['move'])->toMatchArray(['kind' => 'cycle', 'via' => 'apply', 'block_date' => '2026-11-19'])
        ->and($j['move']['unit'])->toMatchArray(['group_name' => 'd1/d22-01b', 'subject_name' => 'Klinik farmakologiya'])
        ->and($j['move']['options']['2026-11-19'][5])->toMatchArray(['ok' => false, 'start_hour' => 9])
        ->and($j['move']['options']['2026-11-19'][5]['why'])->toContain("kun oxirigacha sig‘maydi")
        ->and($j['move']['block']['2026-11-19'][6]['ok'])->toBeFalse();
    // O'tgan (bugungi) dars: ko'chirish yo'q.
    $this->travelTo(Carbon::parse('2026-11-19 09:00', 'Asia/Tashkent'));
    $past = agGet($s, $s['admin'], 'admin', ['move' => $key]);
    expect($past['move'])->toMatchArray(['via' => null, 'why' => "Bugungi va o'tgan darslar ko'chirilmaydi."])
        ->and((array) $past['move']['options'])->toBe([]);
    // Server ham: bugungi kunning sikl amaliyoti soati — 422.
    agAs($s['admin'], 'admin')->postJson(route('admin.timetable.cycle-practice-starts', $s['board']), ['specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-01b', 'units' => ['d1/d22-01b'], 'date' => '2026-11-19', 'scope' => 'day',
        'start_hour' => 3])->assertStatus(422)->assertJsonPath('error', "Bugungi yoki o'tgan darsning vaqtini o'zgartirib bo'lmaydi (dars sanasi: 19.11.2026).");
});

it('agenda: qamrovdan tashqari dars tafsilotsiz; o\'qituvchi o\'z darsini ko\'radi va ko\'chirish so\'rov bo\'lib ketadi', function () {
    $s = agSetup();
    // Boshqa o'qituvchi: MUXAMMADIYEV darslari — faqat vaqti.
    $j = agGet($s, $s['other'], 'oqituvchi');
    expect(collect($j['entries'])->every(fn ($e) => $e['hidden'] && $e['label'] === 'band: boshqa fakultet' && $e['subject'] === null
        && $e['groups'] === [] && $e['room'] === null && $e['key'] === null))->toBeTrue()
        ->and(count($j['entries']))->toBe(4)
        ->and($j['free'])->toBe(['2026-11-19' => [5, 6]]);
    $key = 'w|' . $s['ppB']->id . '|' . $s['week'];
    expect(agGet($s, $s['other'], 'oqituvchi', ['move' => $key])['move']['why'])->toBe("Ko'chiriladigan dars bu kunlarda yo'q.");

    // O'zi: to'liq ko'radi; ko'chirish — so'rov (kafedra mudiri → registrator).
    $mine = agGet($s, $s['mux'], 'oqituvchi', ['move' => $key]);
    expect(collect($mine['entries'])->every(fn ($e) => !$e['hidden']))->toBeTrue()
        ->and($mine['move']['via'])->toBe('propose')
        ->and($mine['move']['options']['2026-11-19'][6]['room']['code'])->toBe('R1');
    $res = agAs($s['mux'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), ['week' => $s['week'],
        'card_id' => $s['ppB']->id, 'type' => 'agenda', 'title' => "VI paraga ko'chirish", 'reason' => "To'qnashuv: IV parada ikki guruh",
        'actions' => [['op' => 'move', 'card_id' => $s['ppB']->id, 'week' => $s['week'], 'day' => 4, 'pair' => 11, 'auditorium_code' => 'R1']]])
        ->assertOk()->json();
    expect($res['pending_approval'])->toBeTrue()
        ->and(\App\Models\TimetableChangeRequest::find($res['change_request_id'])->approval_stage)->toBe('department');
    // Jadval hali o'zgarmagan.
    expect(collect(agGet($s, $s['admin'], 'admin')['entries'])->where('kind', 'weekly')->pluck('start')->all())->toBe(['13:30', '13:30']);
    // Middleware: o'qituvchi agenda va smena maslahatini ocha oladi.
    agAs($s['mux'], 'oqituvchi')->postJson(route('admin.timetable.cycle-shift-advice', $s['board']),
        ['key' => 'davolash ishi|5|d1/d22-01b|Klinik farmakologiya|practice'])->assertOk();
    agAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.cycle-shift-advice', $s['board']),
        ['key' => 'davolash ishi|5|d1/d22-01b|Klinik farmakologiya|practice'])->assertOk();
});

it('agenda: sikl ma\'ruzasi — bitta oqim (guruhchalar) bitta yozuv, boshqa oqim (xonasiz) alohida va to\'qnashuv; ko\'chirilmaydi', function () {
    $this->travelTo(Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent'));
    Role::findOrCreate('admin', 'web');
    $lektor = agTeacher('LEKTOR L.');
    $admin = agTeacher('ADMIN', 'admin');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 4, 'practice' => 26, 'created_at' => now(), 'updated_at' => now()]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'lang' => 'uz', 'len_half' => 2,
        'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'kafedra_name' => 'Farmakologiya kafedrasi'];
    foreach (['d22-05a' => '1-oqim', 'd22-05b' => '1-oqim', 'd22-06a' => '2-oqim'] as $g => $oqim) {
        TimetableCard::create($base + ['oqim_label' => $oqim, 'students' => 10, 'training_type' => 'practice', 'group_name' => $g]);
    }
    $lec = fn (string $oqim, array $groups) => TimetableCard::create($base + ['oqim_label' => $oqim, 'students' => 10 * count($groups),
        'training_type' => 'lecture', 'group_names' => $groups, 'teacher_id' => $lektor->id, 'teacher_name' => 'LEKTOR L.']);
    $lec('1-oqim', ['d22-05a', 'd22-05b']);
    $lec('2-oqim', ['d22-06a']);
    foreach (['F1 / 1-oqim' => 0, 'F1 / 2-oqim' => 1] as $row => $from) {
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'group_name' => $row, 'subject_name' => 'Klinik farmakologiya', 'start_index' => $from, 'training_type' => 'practice']);
    }
    $j = agAs($admin, 'admin')->getJson(route('admin.timetable.teacher-agenda', $board) . '?teacher_id=' . $lektor->id . '&date=2026-09-15')->assertOk()->json();
    expect(array_map(fn ($e) => [$e['kind'], $e['type'], implode(', ', $e['groups']), $e['conflict']], $j['entries']))->toBe([
        ['cycle', 'M', 'd22-05a, d22-05b', true],
        ['cycle', 'M', 'd22-06a', true],
    ]);
    $mv = agAs($admin, 'admin')->getJson(route('admin.timetable.teacher-agenda', $board) . '?' . http_build_query(['teacher_id' => $lektor->id,
        'date' => '2026-09-15', 'move' => $j['entries'][0]['key']]))->assertOk()->json('move');
    expect($mv['via'])->toBeNull()->and($mv['why'])->toContain("Sikl ma'ruzasi bu oynadan ko'chirilmaydi");
    // Ikkala oqim bir zalda, bir vaqtda (fan, o'qituvchi bir xil) — bitta oqim ma'ruzasi: bitta yozuv, to'qnashuv yo'q.
    Auditorium::create(['code' => 'Z1', 'name' => '1-zal', 'volume' => 100, 'active' => true]);
    TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture')->update(['auditorium_code' => 'Z1', 'auditorium_name' => '1-zal']);
    $one = agAs($admin, 'admin')->getJson(route('admin.timetable.teacher-agenda', $board) . '?teacher_id=' . $lektor->id . '&date=2026-09-15')->assertOk()->json();
    expect(array_map(fn ($e) => [$e['type'], implode(', ', $e['groups']), $e['room'], $e['conflict']], $one['entries']))
        ->toBe([['M', 'd22-05a, d22-05b, d22-06a', '1-zal', false]]);
});

it('agenda: sabab matni — "Auditoriya band" va sikl xonasi sig\'imi aniq raqamlar bilan', function () {
    $ctl = app(TimetableController::class);
    $f = fn (string $m) => (new ReflectionMethod($ctl, 'agendaReasonText'))->invoke($ctl, $m);
    expect($f("Xona Z8 band: 65 o'rin, 98 talaba (+33): d22-05a, Klinik farmakologiya, 15.09"))
        ->toBe("Xona sig'maydi: 65 o'rin, 98 talaba (+33) — d22-05a, Klinik farmakologiya, 15.09")
        ->and($f('Xona R2 band: d22-05a, Fan, 15.09'))->toBe('Xona band: R2 — d22-05a, Fan, 15.09')
        ->and($f('Auditoriya band: 102-xona — p-01 · Fizika'))->toBe('Xona band: 102-xona — p-01 · Fizika')
        ->and($f('Guruh band: p-01 — Fizika'))->toBe('Guruh band: p-01 — Fizika');
});

it('agenda: muqobil xona sikl darsi bilan band bo\'lsa taklif qilinmaydi (haftalik va sikl bandligi tekshiriladi)', function () {
    $s = agSetup();
    // 101-xona 19.11 da 13:30–17:50 sikl amaliyotida (boshqa o'qituvchi, d1/d22-02a, smena 7-soatdan).
    $other = agTeacher('SIKL O.');
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-02a', 'kafedra_name' => 'Farmakologiya kafedrasi',
        'teacher_id' => $other->id, 'teacher_name' => 'SIKL O.', 'auditorium_code' => 'R1', 'auditorium_name' => '101-xona']);
    $start = TimetableCyclePlacement::where('board_id', $s['board']->id)->where('group_name', 'd1/d22-01b')->value('start_index');
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd1/d22-02a',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => $start, 'training_type' => 'practice',
        'lecture_slots' => ['shift_g' => ['d1/d22-02a' => 7]]]);
    $key = 'w|' . $s['ppB']->id . '|' . $s['week'];
    // Faqat 101-xona sig'adi — u sikl darsi bilan band: VI para o'chiq.
    $opt = agGet($s, $s['admin'], 'admin', ['move' => $key])['move']['options']['2026-11-19'][6];
    expect($opt)->toMatchArray(['ok' => false, 'room' => null, 'why' => "Xona sig'maydi: 10 o'rin, 12 talaba (+2)"]);
    // Boshqa sig'adigan bo'sh xona bor — u taklif qilinadi (101-xona emas).
    Auditorium::create(['code' => 'R3', 'name' => '103-xona', 'volume' => 20, 'active' => true]);
    $opt = agGet($s, $s['admin'], 'admin', ['move' => $key])['move']['options']['2026-11-19'][6];
    expect($opt['ok'])->toBeTrue()->and($opt['room']['code'])->toBe('R3');
});

it('agenda: xona band bo\'lsa ham amaliy blok qoidasi tekshiriladi — boshqa xona taklifi blokni bo\'lmaydi', function () {
    $s = agSetup();
    // pp/2024-01b Farmakologiya amaliy bloki: IV para (MUXAMMADIYEV) + V para (boshqa o'qituvchi) — yonma-yon.
    $mk = fn (array $o) => TimetableCard::create($o + ['board_id' => $s['board']->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'kafedra_name' => 'Farmakologiya kafedrasi', 'day' => 4]);
    $other = agTeacher('YORDAMCHI Y.');
    $mk(['subject_name' => 'Farmakologiya', 'group_name' => 'pp/2024-01b', 'teacher_id' => $other->id, 'teacher_name' => 'YORDAMCHI Y.', 'pair' => 9]);
    // VI parada 102-xona boshqa guruhga band (xona muammosi) — 101-xona bo'sh.
    $mk(['subject_name' => 'Fizika', 'group_name' => 'p-09', 'teacher_id' => $other->id, 'teacher_name' => 'YORDAMCHI Y.', 'pair' => 11,
        'auditorium_code' => 'R2', 'auditorium_name' => '102-xona']);
    $opt = agGet($s, $s['admin'], 'admin', ['move' => 'w|' . $s['ppB']->id . '|' . $s['week']])['move']['options']['2026-11-19'][6];
    expect($opt['ok'])->toBeFalse()->and($opt['room'])->toBeNull()
        ->and($opt['why'])->toContain('Xona band: 102-xona')->toContain("amaliy blokning bir qismi");
});

it('agenda: bir nechta kartali haftalik ma\'ruza oqimi — bitta yozuv, istalgan karta kaliti bilan topiladi, ko\'chirilmaydi (oqim bo\'linmaydi)', function () {
    $s = agSetup();
    $hall = Auditorium::create(['code' => 'Z1', 'name' => '1-zal', 'volume' => 200, 'active' => true]);
    $lec = fn (string $spec, string $g) => TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F2', 'specialty_name' => $spec,
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 30, 'training_type' => 'lecture', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $s['mux']->id,
        'teacher_name' => 'MUXAMMADIYEV S. B.', 'day' => 4, 'pair' => 11, 'auditorium_code' => 'Z1', 'auditorium_name' => '1-zal']);
    $a = $lec('pediatriya', 'pp/2024-01a');
    $b = $lec('stomatologiya', 'st/2024-01a');
    // Stomatologiya kalendari bir hafta kech boshlanadi — 19.11 uning boshqa hafta raqami.
    \App\Models\TimetableGridSetting::create(['board_id' => $s['board']->id, 'faculty_name' => 'F2', 'specialty_name' => 'stomatologiya',
        'course' => 2, 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15, 'start_date' => '2026-09-21']);
    $j = agGet($s, $s['admin'], 'admin');
    $flow = collect($j['entries'])->firstWhere('type', 'M');
    expect(collect($j['entries'])->where('type', 'M')->count())->toBe(1)
        ->and($flow['groups'])->toBe(['pp/2024-01a', 'st/2024-01a'])->and($flow['card_ids'])->toBe([$a->id, $b->id])
        ->and($flow['conflict'])->toBeFalse();
    $week = (new ReflectionMethod(app(TimetableController::class), 'calendarWeekAt'))->invoke(app(TimetableController::class), $s['board'], $b, '2026-11-19');
    expect($week)->toBe($s['week'] - 1);
    foreach (['w|' . $a->id . '|' . $s['week'], 'w|' . $b->id . '|' . $week] as $key) {
        $mv = agGet($s, $s['admin'], 'admin', ['move' => $key])['move'];
        expect($mv['kind'])->toBe('weekly')->and($mv['via'])->toBeNull()->and((array) $mv['options'])->toBe([])
            ->and($mv['why'])->toContain("Bir nechta kartadan iborat ma'ruza oqimi");
    }
});

it('agenda: sikl birligida o\'qituvchining ikki amaliy kartasi — bitta yozuv, ko\'chirish mumkin (butun birlik ko\'chadi)', function () {
    $s = agSetup();
    $first = TimetableCard::where('board_id', $s['board']->id)->where('group_name', 'd1/d22-01b')->first();
    TimetableCard::create($first->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students', 'training_type',
        'len_half', 'weeks', 'subject_name', 'group_name', 'kafedra_name', 'teacher_id', 'teacher_name']));
    $entry = collect(agGet($s, $s['admin'], 'admin')['entries'])->first(fn ($e) => $e['kind'] === 'cycle' && $e['groups'] === ['d1/d22-01b']);
    expect(count($entry['card_ids']))->toBe(2);
    $mv = agGet($s, $s['admin'], 'admin', ['move' => $entry['key']])['move'];
    expect($mv['via'])->toBe('apply')->and($mv['why'])->toBeNull();
});

it('agenda: aralash qamrovli ma\'ruza oqimi — faqat qamrovdagi kartaning tafsilotlari (boshqa kafedra guruhi ko\'rinmaydi)', function () {
    $s = agSetup();
    Auditorium::create(['code' => 'Z1', 'name' => '1-zal', 'volume' => 200, 'active' => true]);
    $lec = fn (string $spec, string $g, string $kaf) => TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F2',
        'specialty_name' => $spec, 'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 30, 'training_type' => 'lecture',
        'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Farmakologiya', 'group_name' => $g, 'kafedra_name' => $kaf,
        'teacher_id' => $s['mux']->id, 'teacher_name' => 'MUXAMMADIYEV S. B.', 'day' => 4, 'pair' => 11, 'auditorium_code' => 'Z1', 'auditorium_name' => '1-zal']);
    // Avval qamrovdan tashqari karta (yozuvning birinchi qismi), keyin qamrovdagi.
    $foreign = $lec('stomatologiya', 'st/2024-01a', 'Boshqa kafedra');
    $own = $lec('pediatriya', 'pp/2024-01a', 'Farmakologiya kafedrasi');
    $j = agGet($s, $s['head'], 'kafedra_mudiri');
    $flow = collect($j['entries'])->firstWhere('type', 'M');
    expect($flow)->toMatchArray(['hidden' => false, 'groups' => ['pp/2024-01a'], 'card_ids' => [$own->id], 'flow_size' => 2,
        'key' => 'w|' . $own->id . '|' . $s['week'], 'label' => 'Farmakologiya (M) — pp/2024-01a'])
        ->and(json_encode($j))->not->toContain('st/2024-01a')
        ->and(collect($j['entries'])->where('type', 'M')->count())->toBe(1);
    // Oqim baribir ko'p kartali — ko'chirilmaydi.
    expect(agGet($s, $s['head'], 'kafedra_mudiri', ['move' => $flow['key']])['move']['why'])->toContain("Bir nechta kartadan iborat ma'ruza oqimi");
    // Faqat qamrovdan tashqari qism — butunlay yashirin.
    $own->delete();
    $hidden = collect(agGet($s, $s['head'], 'kafedra_mudiri')['entries'])->firstWhere('start', '16:30');
    expect($hidden)->toMatchArray(['hidden' => true, 'groups' => [], 'card_ids' => [], 'key' => null, 'label' => 'band: boshqa fakultet']);
});

it('agenda: boshqa haftaga o\'tilganda ko\'chiriladigan dars kaliti o\'sha hafta darsini tanlamaydi', function () {
    $s = agSetup();
    $key = 'w|' . $s['ppB']->id . '|' . $s['week'];
    // Keyingi hafta (26.11): ppB ning boshqa (keyingi) haftadagi darsi — ko'chiriladigan dars emas.
    $next = agGet($s, $s['admin'], 'admin', ['date' => '2026-11-26', 'range' => 'week', 'move' => $key])['move'];
    expect($next)->toMatchArray(['kind' => null, 'via' => null, 'why' => "Ko'chiriladigan dars bu kunlarda yo'q."]);
    // O'z haftasida — topiladi.
    expect(agGet($s, $s['admin'], 'admin', ['range' => 'week', 'move' => $key])['move'])->toMatchArray(['kind' => 'weekly', 'week' => $s['week']]);
});

it('agenda: "shu va keyingi haftalar" — tanlangan para va taklif qilingan xona har keyingi haftada tekshiriladi', function () {
    $s = agSetup();
    $key = 'w|' . $s['ppB']->id . '|' . $s['week'];
    // rest_for so'ralmasa — tekshiruv yo'q (og'ir).
    expect(agGet($s, $s['admin'], 'admin', ['move' => $key])['move'])->not->toHaveKey('rest');
    $ok = agGet($s, $s['admin'], 'admin', ['move' => $key, 'rest_for' => '2026-11-19|6'])['move'];
    expect($ok['rest_weeks'])->toBe(range($s['week'], 15))
        ->and($ok['rest'])->toBe(['date' => '2026-11-19', 'pair' => 6, 'ok' => true, 'why' => null]);
    // Keyingi haftada (payshanba VI para) pp/2024-01b guruhi boshqa darsda — "keyingi haftalar" rad etiladi, sababi bilan.
    $x = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya', 'course' => 2,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Fizika', 'group_name' => 'pp/2024-01b', 'kafedra_name' => 'Farmakologiya kafedrasi']);
    \App\Models\TimetableCardOverride::create(['card_id' => $x->id, 'week' => $s['week'] + 1, 'day' => 4, 'pair' => 11]);
    $bad = agGet($s, $s['admin'], 'admin', ['move' => $key, 'rest_for' => '2026-11-19|6'])['move'];
    expect($bad['options']['2026-11-19'][6]['ok'])->toBeTrue()   // shu hafta — bo'sh
        ->and($bad['rest']['ok'])->toBeFalse()
        ->and($bad['rest']['why'])->toStartWith(($s['week'] + 1) . '-hafta (26.11): ')->toContain('Guruh band: pp/2024-01b');
    // O'chiq para uchun — o'z sababi.
    $five = agGet($s, $s['admin'], 'admin', ['move' => $key, 'rest_for' => '2026-11-19|5'])['move'];
    expect($five['rest'])->toBe(['date' => '2026-11-19', 'pair' => 5, 'ok' => false, 'why' => $five['options']['2026-11-19'][5]['why']]);
});

it('agenda: bitta ma\'ruza kartasi guruhchalarda har xil soatda (xonasiz) — alohida yozuvlar, vaqt bo\'sh deb ko\'rinmaydi', function () {
    $this->travelTo(Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent'));
    Role::findOrCreate('admin', 'web');
    $lektor = agTeacher('LEKTOR L.');
    $admin = agTeacher('ADMIN', 'admin');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 4, 'practice' => 26, 'created_at' => now(), 'updated_at' => now()]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'lang' => 'uz', 'len_half' => 2,
        'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'kafedra_name' => 'Farmakologiya kafedrasi', 'oqim_label' => '1-oqim'];
    foreach (['d22-05a', 'd22-05b'] as $g) {
        TimetableCard::create($base + ['students' => 10, 'training_type' => 'practice', 'group_name' => $g]);
    }
    TimetableCard::create($base + ['students' => 20, 'training_type' => 'lecture', 'group_names' => ['d22-05a', 'd22-05b'],
        'teacher_id' => $lektor->id, 'teacher_name' => 'LEKTOR L.']);
    // 05b ning 1-kungi ma'ruzasi 7-soatdan (05a — 1-soatdan).
    TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'F1 / 1-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice',
        'lecture_slots' => ['starts_g' => ['d22-05b' => [0 => 7]]]]);
    $j = agAs($admin, 'admin')->getJson(route('admin.timetable.teacher-agenda', $board) . '?teacher_id=' . $lektor->id . '&date=2026-09-14')->assertOk()->json();
    expect(array_map(fn ($e) => [$e['type'], implode(', ', $e['groups']), $e['from'], $e['conflict']], $j['entries']))
        ->toBe([['M', 'd22-05a', 1, false], ['M', 'd22-05b', 7, false]])
        ->and($j['entries'][0]['key'])->not->toBe($j['entries'][1]['key']);
});
