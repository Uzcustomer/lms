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

// Sikl jadvali: "Faqat ma'ruza" surilganda to'sib turgan amaliyotni joylash (practice_place: swap / after / gap / pair; keep — eski xato)
// va teskari holat — "Faqat amaliy" ma'ruza ustiga (lecture_swap).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-05 09:00', 'Asia/Tashkent')));

function psTeacher(string $name, ?string $role = null, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Travmatologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ], $extra))->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

/**
 * Travmatologiya va ortopediya (2-son davolash, 4-kurs, sikl 6 kun), oqim "2-son davolash / 3-oqim": d2/d23-08a, 08b, 09a, 09b; 16.11.2026
 * (dushanba) dan. Qo'ng'iroq: 6 para (I 08:30, II 10:00, III 12:00, IV 13:30, V 15:00, VI 16:30), 1 para = 2 soat qatori. Ma'ruza har kuni
 * 2 soat (I para), amaliy jami $practice soat (KTRsiz: kuniga 4 soatdan — 2 bo'lsa faqat 1-kunda 2 soat) ma'ruzadan keyin (II paradan);
 * 08b — o'z smenasida (IV para, 13:30).
 */
function psSetup(int $practice = 2): array
{
    Role::findOrCreate('admin', 'web');
    $bell = [['08:30', '09:50'], ['10:00', '11:20'], ['12:00', '13:20'], ['13:30', '14:50'], ['15:00', '16:20'], ['16:30', '17:50']];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 6, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-para', 'abbr' => ['I', 'II', 'III', 'IV', 'V', 'VI'][$i],
            'start' => $bell[$i][0], 'end' => $bell[$i][1]], range(0, 5))]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'subject_name' => 'Travmatologiya va ortopediya', 'mode' => 'cycle', 'cycle_days' => 6]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2023/2024', 'level_code' => '14', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Travmatologiya va ortopediya',
        'semester' => 7, 'lecture' => 12, 'practice' => $practice, 'created_at' => now(), 'updated_at' => now()]);
    $lektor = psTeacher('LEKTOR L.');
    $base = ['board_id' => $board->id, 'faculty_name' => '2-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 4, 'oqim_label' => '3-oqim',
        'lang' => 'uz', 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Travmatologiya va ortopediya', 'kafedra_name' => 'Travmatologiya kafedrasi'];
    $t = [];
    foreach (['08a', '08b', '09a', '09b'] as $g) {
        $t[$g] = psTeacher('USTOZ ' . $g);
        TimetableCard::create($base + ['students' => 10, 'training_type' => 'practice', 'group_name' => 'd2/d23-' . $g,
            'teacher_id' => $t[$g]->id, 'teacher_name' => $t[$g]->full_name]);
    }
    TimetableCard::create($base + ['students' => 40, 'training_type' => 'lecture', 'group_names' => ['d2/d23-08a', 'd2/d23-08b', 'd2/d23-09a', 'd2/d23-09b'],
        'teacher_id' => $lektor->id, 'teacher_name' => 'LEKTOR L.']);
    $ctl = app(TimetableController::class);
    [, [$dates, $work]] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $board);
    $start = collect($work)->first(fn ($i) => $dates[$i]->toDateString() === '2026-11-16');
    $placement = TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'group_name' => '2-son davolash / 3-oqim', 'subject_name' => 'Travmatologiya va ortopediya', 'start_index' => $start, 'training_type' => 'practice',
        'lecture_slots' => ['shift_g' => ['d2/d23-08b' => 7]]]);
    $admin = psTeacher('ADMIN', 'admin');

    return compact('board', 'placement', 'admin', 'lektor', 't');
}

/** Guruhchaning sana bo'yicha darslari: [sana => ['lec' => [boshi, oxiri) | null, 'prc' => ...]]. */
function psDays(array $s, string $g): array
{
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    $x = app(TimetableController::class)->cycleProposalUnits($s['board'], ['specialty_name' => 'davolash ishi', 'course' => 4,
        'subject_name' => 'Travmatologiya va ortopediya', 'group_name' => 'd2/d23-' . $g], null)[0];
    $out = [];
    foreach ($x['dates'] as $d) {
        $out[$d] = ['lec' => $x['lec'][$d] ?? null, 'prc' => $x['prac'][$d] ?? null];
    }

    return $out;
}

function psMove(array $s, array $extra, ?Teacher $as = null, string $role = 'admin')
{
    return test()->withoutVite()->actingAs($as ?? $s['admin'], 'teacher')->withSession(['active_role' => $role])
        ->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), $extra + ['specialty_name' => 'davolash ishi', 'course' => 4,
            'subject_name' => 'Travmatologiya va ortopediya', 'group_name' => '2-son davolash / 3-oqim', 'training_type' => 'practice', 'view' => 'group']);
}

/** Dushanba (16.11), $hour-soatdan (yarim para; III para = 5) 2 soat: o'qituvchi boshqa guruhda haftalik darsda. */
function psWeeklyBusy(array $s, Teacher $t, int $hour): void
{
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => '2-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 3,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Anatomiya', 'group_name' => 'd2/d24-01a', 'kafedra_name' => 'Anatomiya kafedrasi', 'day' => 1, 'pair' => $hour,
        'teacher_id' => $t->id, 'teacher_name' => $t->full_name]);
}

it('boshlang\'ich holat: ma\'ruza I para, amaliy II para (08b — IV para); 4 soatlik — 3–6-soat', function () {
    $s = psSetup();
    expect(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 5]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [7, 9]])
        ->and(psDays($s, '08a')['2026-11-17'])->toBe(['lec' => [1, 3], 'prc' => null]);
    expect(psDays(psSetup(12), '09b')['2026-11-18'])->toBe(['lec' => [1, 3], 'prc' => [3, 7]]);
});

it('o\'rin almashish: ma\'ruza 08:30 → 10:00 — 08a, 09a, 09b amaliyoti 10:00 → 08:30; 08b o\'zgarmaydi; boshqa kunlar joyida; Ctrl+Z', function () {
    $s = psSetup();
    $before = $s['placement']->fresh()->lecture_slots;
    $dry = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($dry)->toMatchArray(['status' => 'ok', 'from' => 1, 'to' => 3, 'applied' => false, 'lecture' => null])
        ->and($dry['swaps'])->toBe([
            ['unit' => 'd2/d23-08a', 'from' => 3, 'to' => 1, 'len' => 2, 'how' => 'swap', 'busy' => false, 'why' => null],
            ['unit' => 'd2/d23-09a', 'from' => 3, 'to' => 1, 'len' => 2, 'how' => 'swap', 'busy' => false, 'why' => null],
            ['unit' => 'd2/d23-09b', 'from' => 3, 'to' => 1, 'len' => 2, 'how' => 'swap', 'busy' => false, 'why' => null],
        ])
        ->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    $r = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk()->json();
    expect($r['days'][0])->toMatchArray(['status' => 'ok', 'applied' => true]);
    foreach (['08a', '09a', '09b'] as $g) {
        expect(psDays($s, $g)['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [1, 3]])
            ->and(psDays($s, $g)['2026-11-17'])->toBe(['lec' => [1, 3], 'prc' => null]);
    }
    expect(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 9]]);
    // Ctrl+Z — ma'ruza ham, amaliyot ham bitta so'rov bilan.
    psMove($s, ['undo' => $r['undo']])->assertOk();
    expect($s['placement']->fresh()->lecture_slots)->toBe($before)
        ->and(psDays($s, '09a')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 5]]);
});

it('o\'rin almashish o\'chiq (keep yoki berilmagan) — avvalgidek xato, hech narsa o\'zgarmaydi', function () {
    $s = psSetup();
    $before = $s['placement']->fresh()->lecture_slots;
    foreach ([['practice_place' => 'keep'], []] as $opt) {
        $d = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture'] + $opt)->assertOk()->json('days.0');
        expect($d['status'])->toBe('blocked')->and($d['why'])->toContain('amaliy bilan ustma-ust (d2/d23-08a)')->and($d['swaps'])->toBe([]);
    }
    // Bitta kunlik (day_index) yo'l ham.
    psMove($s, ['day_index' => 0, 'start_hour' => 3, 'mode' => 'lecture'])->assertStatus(422);
    expect($s['placement']->fresh()->lecture_slots)->toBe($before);
});

it('bitta kunlik (day_index) yo\'l ham o\'rin almashadi va swaps qaytaradi', function () {
    $s = psSetup();
    $r = psMove($s, ['day_index' => 0, 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk()->json();
    expect(array_column($r['swaps'], 'unit'))->toBe(['d2/d23-08a', 'd2/d23-09a', 'd2/d23-09b'])
        ->and(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [1, 3]]);
});

it('4 soatlik amaliy 2 soatlik ma\'ruza joyiga sig\'maydi — ma\'ruzadan keyin (12:00); 08b kesishmaydi — joyida', function () {
    $s = psSetup(12);
    $d = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(array_column($d['swaps'], 'unit'))->toBe(['d2/d23-08a', 'd2/d23-09a', 'd2/d23-09b'])
        ->and($d['swaps'][0])->toMatchArray(['from' => 3, 'to' => 5, 'len' => 4, 'how' => 'after', 'why' => 'nofit']);
    expect(psDays($s, '09b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [5, 9]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 11]]);
});

it('ma\'ruzadan keyin kun oxiriga sig\'masa — usul "after" xato beradi, "swap" eski ma\'ruza joyiga qo\'yadi', function () {
    $s = psSetup(12);
    // Ma'ruza V paraga (9–10): 08a/09a/09b amaliyoti (3–6) kesishmaydi; 08b (7–10) kesishadi.
    $r = psMove($s, ['day_indexes' => [0, 2], 'start_hour' => 9, 'mode' => 'lecture', 'practice_place' => 'after', 'dry_run' => 1])->assertOk()->json('days');
    expect(array_column($r, 'status'))->toBe(['blocked', 'blocked'])
        ->and($r[0]['why'])->toContain("Amaliy (d2/d23-08b) joylashmadi: 4 soat 11-soatdan kun oxirigacha sig'maydi (kunda 12 soat).");
    $w = psMove($s, ['day_indexes' => [0], 'start_hour' => 9, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk()->json('days.0');
    expect($w['status'])->toBe('ok')->and($w['swaps'])->toBe([['unit' => 'd2/d23-08b', 'from' => 7, 'to' => 1, 'len' => 4, 'how' => 'swap', 'busy' => false, 'why' => null]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [9, 11], 'prc' => [1, 5]])
        ->and(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [9, 11], 'prc' => [3, 7]]);
});

it('ko\'chgan amaliyot o\'qituvchisi band — keyingi variant (ma\'ruzadan keyin); hammasi band — to\'qnashuv ("Baribir" bilan qo\'llanadi)', function () {
    $s = psSetup();
    // 09b o'qituvchisi dushanba I parada band.
    psWeeklyBusy($s, $s['t']['09b'], 1);
    $d = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')
        ->and(collect($d['swaps'])->firstWhere('unit', 'd2/d23-09b'))->toMatchArray(['from' => 3, 'to' => 5, 'how' => 'after', 'busy' => false, 'why' => 'busy'])
        ->and(collect($d['swaps'])->firstWhere('unit', 'd2/d23-09a'))->toMatchArray(['to' => 1, 'how' => 'swap']);
    // III parada ham band — boshqa variant yo'q: to'qnashuv (birinchi nomzod — eski ma'ruza joyi).
    psWeeklyBusy($s, $s['t']['09b'], 5);
    $c = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($c['status'])->toBe('conflict')->and($c['why'])->toContain("Amaliy (d2/d23-09b): O'qituvchi band: d2/d24-01a, Anatomiya")
        ->and(collect($c['swaps'])->firstWhere('unit', 'd2/d23-09b'))->toMatchArray(['to' => 1, 'busy' => true, 'why' => null]);
    $f = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap', 'force' => 1])->assertOk()->json('days.0');
    expect($f['applied'])->toBeTrue()->and(psDays($s, '09b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [1, 3]]);
});

it('o\'rin almashish: eski joy band — keyin va oldindan asl joyga yaqinrog\'i (oldinga)', function () {
    $s = psSetup();
    // 08b (13:30, 7–8-soat) — ma'ruza 8-soatga (8–9) — kesishadi; eski ma'ruza joyi (1-soat) band: keyin — 11 (asl joydan 4), oldin — 5 (2).
    psWeeklyBusy($s, $s['t']['08b'], 1);
    $d = psMove($s, ['day_indexes' => [0], 'start_hour' => 8, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')
        ->and($d['swaps'])->toBe([['unit' => 'd2/d23-08b', 'from' => 7, 'to' => 5, 'len' => 2, 'how' => 'before', 'busy' => false, 'why' => 'busy']]);
});

it('ma\'ruza para chegarasidan boshlanmasa — amaliyot ma\'ruzadan keyingi birinchi para chegarasidan', function () {
    $s = psSetup();
    // Ma'ruza 2-soatga (2–3): eski joy (1–2) kesishadi, oldinda joy yo'q; keyin — 4 emas, III para (5-soat, 12:00).
    $d = psMove($s, ['day_indexes' => [0], 'start_hour' => 2, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(array_values(array_unique(array_column($d['swaps'], 'to'))))->toBe([5])
        ->and(array_values(array_unique(array_column($d['swaps'], 'why'))))->toBe(['nofit']);
});

it('"Ma\'ruzadan keyin" va "Aniq para" usullari; para ma\'ruza bilan ustma-ust — xato; band — sababi bilan to\'qnashuv', function () {
    $s = psSetup();
    $a = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'after', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($a['status'])->toBe('ok')->and(array_values(array_unique(array_column($a['swaps'], 'to'))))->toBe([5])
        ->and(array_values(array_unique(array_column($a['swaps'], 'how'))))->toBe(['after']);
    $p = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'pair', 'practice_pair' => 6, 'dry_run' => 1])
        ->assertOk()->json('days.0');
    expect($p['status'])->toBe('ok')->and(array_values(array_unique(array_column($p['swaps'], 'to'))))->toBe([11])
        ->and(array_values(array_unique(array_column($p['swaps'], 'how'))))->toBe(['pair']);
    $x = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'pair', 'practice_pair' => 2, 'dry_run' => 1])
        ->assertOk()->json('days.0');
    expect($x['status'])->toBe('blocked')->and($x['why'])->toContain("Amaliy (d2/d23-08a) joylashmadi: tanlangan vaqt 10:00–11:20 yangi ma'ruza bilan ustma-ust.");
    psWeeklyBusy($s, $s['t']['08a'], 11);
    $b = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'pair', 'practice_pair' => 6, 'dry_run' => 1])
        ->assertOk()->json('days.0');
    expect($b['status'])->toBe('conflict')->and($b['why'])->toContain("Amaliy (d2/d23-08a): O'qituvchi band: d2/d24-01a, Anatomiya")
        ->and($b['why'])->not->toContain('d2/d23-09a');
});

it('"Oraliq bilan: 1 para": ma\'ruza I para — amaliy III paradan (12:00); 4 soatlik amaliy kun oxiriga sig\'masa — xato', function () {
    $s = psSetup();
    // Avval ma'ruza II paraga (amaliy almashib I paraga), so'ng yana I paraga — endi amaliy I parada to'sib turadi.
    psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk();
    $r = psMove($s, ['day_indexes' => [0], 'start_hour' => 1, 'mode' => 'lecture', 'practice_place' => 'gap', 'practice_gap' => 1])->assertOk()->json('days.0');
    expect($r['status'])->toBe('ok')->and(array_values(array_unique(array_column($r['swaps'], 'to'))))->toBe([5])
        ->and(array_values(array_unique(array_column($r['swaps'], 'how'))))->toBe(['gap'])
        ->and(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [5, 7]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [7, 9]]);

    $s4 = psSetup(12);
    // 2-kun: 4 soatlik amaliy (3–6), ma'ruza III paraga (5–6) — kesishadi; 2 para oraliq → 11-soatdan, sig'maydi.
    $e = psMove($s4, ['day_indexes' => [1], 'start_hour' => 5, 'mode' => 'lecture', 'practice_place' => 'gap', 'practice_gap' => 2])->assertOk()->json('days.0');
    expect($e['status'])->toBe('blocked')->and($e['why'])->toContain("Amaliy (d2/d23-08a) joylashmadi: 4 soat 11-soatdan kun oxirigacha sig'maydi")
        ->and($e['applied'])->toBeFalse()
        ->and(psDays($s4, '08a')['2026-11-17'])->toBe(['lec' => [1, 3], 'prc' => [3, 7]]);
});

it('ko\'p kun (Ctrl): har kun alohida — band kun qo\'llanmaydi, qolganlari almashadi; Ctrl+Z hammasini tiklaydi', function () {
    $s = psSetup(12);
    // Dushanba III para: 09b o'qituvchisi band — 4 soatlik amaliyotning yagona varianti (ma'ruzadan keyin) band.
    psWeeklyBusy($s, $s['t']['09b'], 5);
    $before = $s['placement']->fresh()->lecture_slots;
    $r = psMove($s, ['day_indexes' => [0, 1, 2], 'delta' => 2, 'mode' => 'lecture', 'practice_place' => 'swap'])->assertOk()->json();
    expect(array_column($r['days'], 'status'))->toBe(['conflict', 'ok', 'ok'])->and(array_column($r['days'], 'applied'))->toBe([false, true, true])
        ->and($r['days'][0]['why'])->toContain('Amaliy (d2/d23-09b): O\'qituvchi band');
    expect(psDays($s, '09b')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 7]])
        ->and(psDays($s, '09b')['2026-11-17'])->toBe(['lec' => [3, 5], 'prc' => [5, 9]])
        ->and(psDays($s, '08a')['2026-11-18'])->toBe(['lec' => [3, 5], 'prc' => [5, 9]])
        ->and(psDays($s, '08a')['2026-11-19'])->toBe(['lec' => [1, 3], 'prc' => null]);
    psMove($s, ['undo' => $r['undo']])->assertOk();
    expect($s['placement']->fresh()->lecture_slots)->toBe($before);
});

it('teskari holat: "Faqat amaliy" ma\'ruza ustiga — lecture_swap bilan ma\'ruza bo\'shagan joyga, boshqa guruhchalar ham almashadi', function () {
    $s = psSetup();
    $body = ['day_indexes' => [0], 'start_hour' => 1, 'mode' => 'practice', 'group_name' => 'd2/d23-08a', 'placement_group' => '2-son davolash / 3-oqim',
        'sub_group' => 'd2/d23-08a'];
    // Odatdagidek — xato.
    $x = psMove($s, $body + ['dry_run' => 1])->assertOk()->json('days.0');
    expect($x['status'])->toBe('blocked')->and($x['why'])->toContain('Ma’ruza vaqti bilan ustma-ust');
    // lecture_swap: ma'ruza 3-soatga (08a amaliyoti bo'shatgan joy), 09a va 09b amaliyoti ham 1-soatga; 08b — joyida.
    $d = psMove($s, $body + ['lecture_swap' => 1, 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and($d['lecture'])->toBe(['from' => 1, 'to' => 3, 'len' => 2])
        ->and(array_column($d['swaps'], 'unit'))->toBe(['d2/d23-08a', 'd2/d23-09a', 'd2/d23-09b'])
        ->and(array_column($d['swaps'], 'how'))->toBe(['pin', 'swap', 'swap']);
    $r = psMove($s, $body + ['lecture_swap' => 1, 'practice_place' => 'swap'])->assertOk()->json();
    foreach (['08a', '09a', '09b'] as $g) {
        expect(psDays($s, $g)['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [1, 3]]);
    }
    expect(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 9]]);
    psMove($s, ['undo' => $r['undo']])->assertOk();
    expect(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 5]]);
    // Boshqa guruhchalar "keep" bilan — ularning amaliyoti yangi ma'ruza bilan kesishadi: xato.
    $k = psMove($s, $body + ['lecture_swap' => 1, 'dry_run' => 1])->assertOk()->json('days.0');
    expect($k['status'])->toBe('blocked')->and($k['why'])->toContain('amaliy bilan ustma-ust (d2/d23-09a)');
    // Ma'ruza bilan kesishmasa — odatdagi "Faqat amaliy" (lecture yo'q).
    $n = psMove($s, array_merge($body, ['start_hour' => 9, 'lecture_swap' => 1, 'practice_place' => 'swap', 'dry_run' => 1]))->assertOk()->json('days.0');
    expect($n)->toMatchArray(['status' => 'ok', 'lecture' => null, 'swaps' => []]);
});

it('bugun: birga ko\'chadigan amaliyotning yangi vaqti o\'tib ketgan bo\'lsa — o\'zgarmaydi; kelgusi vaqtga — mumkin', function () {
    $s = psSetup();
    // Ma'ruza bugun (16.11) III parada (12:00), amaliy IV parada (13:30); hozir 10:30.
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-08b' => 7], 'starts' => [0 => 5]]]);
    expect(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [5, 7], 'prc' => [7, 9]]);
    $this->travelTo(Carbon::parse('2026-11-16 10:30', 'Asia/Tashkent'));
    // Ma'ruza IV paraga — to'sib turgan amaliyot I paraga (08:30, o'tib ketgan): o'zgarmaydi.
    $p = psMove($s, ['day_indexes' => [0], 'start_hour' => 7, 'mode' => 'lecture', 'practice_place' => 'pair', 'practice_pair' => 1, 'dry_run' => 1])
        ->assertOk()->json('days.0');
    expect($p['status'])->toBe('blocked')->and($p['why'])->toBe('Bugun birga ko‘chadigan dars boshlangan (yoki yangi vaqti o‘tib ketgan).');
    // O'rin almashish — amaliyot 12:00 ga (hali boshlanmagan): mumkin.
    $ok = psMove($s, ['day_indexes' => [0], 'start_hour' => 7, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($ok['status'])->toBe('ok')->and(array_values(array_unique(array_column($ok['swaps'], 'to'))))->toBe([5]);
});

it('o\'qituvchi (ma\'ruzachi) so\'rovi: tekshiruvda o\'rin almashish rejasi, so\'rovda saqlanadi; tasdiqda ma\'ruza va amaliyot birga', function () {
    $s = psSetup();
    Role::findOrCreate('oqituvchi', 'web');
    $s['lektor']->assignRole('oqituvchi');
    $body = ['specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => 'Travmatologiya va ortopediya', 'group_name' => 'd2/d23-08a',
        'training_type' => 'lecture', 'date' => '2026-11-16', 'start_hour' => 3];
    $as = fn () => test()->withoutVite()->actingAs($s['lektor'], 'teacher')->withSession(['active_role' => 'oqituvchi']);
    // O'rin almashishsiz — avvalgidek xato.
    $as()->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body)->assertUnprocessable()
        ->assertJsonFragment(['start_hour' => ["Ma'ruza 10:00–11:20 amaliy bilan ustma-ust (d2/d23-08a) · Ma'ruza 10:00–11:20 amaliy bilan ustma-ust (d2/d23-09a) · Ma'ruza 10:00–11:20 amaliy bilan ustma-ust (d2/d23-09b)"]]);
    $c = $as()->postJson(route('admin.timetable.cycle-time-proposal.check', $s['board']), $body + ['practice_place' => 'swap'])->assertOk()->json();
    expect($c['swaps'])->toBe('Amaliyot o‘rin almashadi: d2/d23-08a 10:00 → 08:30, d2/d23-09a 10:00 → 08:30, d2/d23-09b 10:00 → 08:30');
    $before = $s['placement']->fresh()->lecture_slots;
    DB::table('departments')->insert(['department_hemis_id' => 701, 'name' => 'Travmatologiya kafedrasi', 'code' => 'K7', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    psTeacher('MUDIR M.', 'kafedra_mudiri', ['department_hemis_id' => 701]);
    $as()->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $body + ['practice_place' => 'swap', 'reason' => 'Klinika'])->assertOk();
    $req = \App\Models\TimetableChangeRequest::latest('id')->first();
    expect($req->payload)->toMatchArray(['training_type' => 'lecture', 'start_hour' => 3, 'practice_place' => 'swap'])
        ->and($req->summary)->toContain('Amaliyot o‘rin almashadi: d2/d23-08a 10:00 → 08:30')
        ->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    // Tasdiq (so'rov qayta bajariladi): ma'ruza va amaliyot birga.
    test()->withoutVite()->actingAs($s['admin'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-practice-starts', $s['board']), $req->payload)->assertOk();
    expect(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [1, 3]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 9]]);
});

it('kafedra mudiri: o\'z kafedrasida darhol; tasdiq talab qilinadigan doskada — so\'rov (tavsifda amaliyot usuli), tasdiqlangach ma\'ruza va amaliyot birga', function () {
    $s = psSetup();
    DB::table('departments')->insert(['department_hemis_id' => 701, 'name' => 'Travmatologiya kafedrasi', 'code' => 'K7', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $head = psTeacher('MUDIR M.', 'kafedra_mudiri', ['department_hemis_id' => 701]);
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['approval_required' => true])]);
    $dry = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'swap', 'dry_run' => 1], $head, 'kafedra_mudiri')
        ->assertOk()->json('days.0');
    expect($dry['status'])->toBe('ok')->and(count($dry['swaps']))->toBe(3)->and(\App\Models\TimetableChangeRequest::count())->toBe(0);
    $r = psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'lecture', 'practice_place' => 'gap', 'practice_gap' => 1], $head, 'kafedra_mudiri')
        ->assertOk()->json();
    expect($r['pending_approval'] ?? false)->toBeTrue()->and(psDays($s, '08a')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 5]]);
    $req = \App\Models\TimetableChangeRequest::latest('id')->first();
    expect($req->summary)->toContain("1-kun — ma'ruza 3-soatdan boshlanadi; to'sib turgan amaliyot — 1 para oraliq bilan");
    test()->withoutVite()->actingAs($s['admin'], 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.change-requests.approve', $req->id))->assertOk();
    // Ma'ruza II para (3–4), amaliy 1 para oraliq bilan — IV paradan (7–8); 08b (7–8) kesishmaydi — joyida.
    expect(psDays($s, '09a')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 9]])
        ->and(psDays($s, '08b')['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [7, 9]]);
    // Teskari holat tavsifi.
    psMove($s, ['day_indexes' => [0], 'start_hour' => 3, 'mode' => 'practice', 'group_name' => 'd2/d23-08a', 'placement_group' => '2-son davolash / 3-oqim',
        'sub_group' => 'd2/d23-08a', 'lecture_swap' => 1, 'practice_place' => 'swap'], $head, 'kafedra_mudiri')->assertOk();
    expect(\App\Models\TimetableChangeRequest::latest('id')->first()->summary)
        ->toContain("amaliy 3-soatdan boshlanadi; ma'ruza bilan o'rin almashadi; to'sib turgan amaliyot — o'rin almashadi");
});
