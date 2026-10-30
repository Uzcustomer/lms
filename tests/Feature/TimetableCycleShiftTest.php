<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

require_once __DIR__ . '/../Support/TimetableLegacyOccupancy.php';

function csTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Farmakologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

/**
 * Pediatriya 5-kurs, Klinik farmakologiya sikli (5 kun): p/p22-02a (2-oqim) va p/p22-03c (3-oqim) — bitta o'qituvchi (YOQUBOV),
 * ikkala oqim bloki bir xil sanalarda (14.09–18.09). $shifts — [guruhcha => smena soati].
 */
function csSetup(array $shifts = []): array
{
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $t = csTeacher('YOQUBOV U. B.');
    $cards = [];
    foreach (['p/p22-02a' => '2-oqim', 'p/p22-03c' => '3-oqim'] as $g => $oqim) {
        $cards[$g] = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
            'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
            'teacher_id' => $t->id, 'teacher_name' => 'YOQUBOV U. B.']);
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / ' . $oqim,
            'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice',
            'lecture_slots' => isset($shifts[$g]) ? ['shift_g' => [$g => $shifts[$g]]] : null]);
    }

    return ['board' => $board, 'teacher' => $t, 'cards' => $cards];
}

function csCall(TimetableBoard $board, string $method, ...$args)
{
    $ctl = app(TimetableController::class);
    request()->attributes->remove('timetable.cycle_units.' . $board->id);

    return (new ReflectionMethod($ctl, $method))->invoke($ctl, $board, ...$args);
}

it('smena: bir o\'qituvchi, ikki guruhcha, bir xil sanalar — biri I para, biri IV para (13:30): to\'qnashuv va ogohlantirish yo\'q', function () {
    $s = csSetup(['p/p22-02a' => 1, 'p/p22-03c' => 7]);
    expect(csCall($s['board'], 'cycleConflicts'))->toBe([])
        ->and(app(TimetableController::class)->internalConflicts($s['board'])['count'])->toBe(0)
        ->and(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get()))->toBe(['teachers' => [], 'rooms' => []]);
    // Mark (ichki jadval): 03c amaliy darslari 7-soatdan, 02a — 1-soatdan
    $admin = csTeacher('ADMIN', 'admin');
    $plan = test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json();
    $dp = fn (string $g) => collect($plan['rows'])->firstWhere('group', $g)['blocks'][0]['day_plan'];
    expect(collect($dp('p/p22-03c'))->pluck('ps')->unique()->values()->all())->toBe([7])
        ->and(collect($dp('p/p22-02a'))->pluck('ps')->unique()->values()->all())->toBe([1]);
    assertBusyMatchesLegacy($s['board']);
});

it('smena: ikkalasi ham I parada — to\'qnashuv; ogohlantirishda haqiqiy kesishgan sanalar; smena o\'zgartirilsa yo\'qoladi', function () {
    $s = csSetup();
    $c = csCall($s['board'], 'cycleConflicts');
    expect($c['Pediatriya|5|p/p22-03c|Klinik farmakologiya|practice']['text'])
        ->toBe("O'qituvchi YOQUBOV U. B. band: p/p22-02a, Klinik farmakologiya, 14.09–18.09")
        ->and(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get())['teachers'])
        ->toBe([$s['teacher']->id => 'p/p22-02a, Klinik farmakologiya, 14.09–18.09']);

    // "O'qituvchi biriktirish" (sikl bloki): 03c ga IV para smenasi — saqlanadi, ogohlantirishsiz
    $head = csTeacher('MUDIR', 'admin');
    $params = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'p/p22-03c', 'placement_group' => 'Ped / 3-oqim',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group'];
    $as = fn () => test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'admin']);
    $opt = $as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $params)->assertOk()->json();
    expect($opt['shift_start'])->toBe(0)->and((array) $opt['busy_teachers'])->toHaveKey((string) $s['teacher']->id)
        ->and($opt['shift_options'][3])->toBe(['start' => 7, 'label' => 'IV para']);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['teacher_id' => $s['teacher']->id, 'shift_start' => 7])
        ->assertOk()->assertJson(['shift_start' => 7]);
    expect(csCall($s['board'], 'cycleConflicts'))->toBe([]);
    $opt = $as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $params)->assertOk()->json();
    expect($opt['shift_start'])->toBe(7)->and((array) $opt['busy_teachers'])->toBe([]);

    // Smena I paraga qaytarilsa va boshqa o'qituvchidan YOQUBOVga o'tkazilsa — ogohlantirish (shu so'rovdagi smena bilan)
    \App\Models\TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->update(['teacher_id' => null, 'teacher_name' => null]);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['teacher_id' => $s['teacher']->id, 'shift_start' => 0])
        ->assertStatus(409)->assertJson(['needs_confirm' => true]);
    // Ogohlantirishda smena saqlanmaydi (bekor qilinadi)
    expect(TimetableCyclePlacement::where('group_name', 'Ped / 3-oqim')->first()->lecture_slots['shift_g'])->toBe(['p/p22-03c' => 7]);
});

it('sana: guruhchaning fanlari har xil oqimda — joylashuv fan oqimidan olinadi (birinchi kartaning oqimidan emas)', function (bool $otherFirst) {
    $s = csSetup();
    // 02a ning boshqa sikl fani (birinchi karta, 1-oqim) va 1-oqimning Klinik farmakologiya joylashuvi (boshqa sanalar).
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Gematologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $first = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Gematologiya', 'group_name' => 'p/p22-02a', 'kafedra_name' => 'K']);
    if ($otherFirst) {
        TimetableCard::whereKey($first->id)->update(['id' => 1]);   // eng birinchi karta (aks holda — oxirgi)
    }
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 1-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 21, 'training_type' => 'practice']);   // 05.10–09.10
    // 02a ning Klinik farmakologiyasi — 2-oqimda (14.09): 03c bilan kesishadi, sanalar 14.09–18.09
    expect(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get())['teachers'])
        ->toBe([$s['teacher']->id => 'p/p22-02a, Klinik farmakologiya, 14.09–18.09']);
    $admin = csTeacher('ADMIN', 'admin');
    $plan = test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json();
    $row = collect($plan['rows'])->firstWhere('group', 'p/p22-02a');
    expect(collect($row['blocks'])->firstWhere('subject', 'Klinik farmakologiya')['from'])->toBe(0);
})->with(['boshqa fan kartasi birinchi' => [true], 'boshqa fan kartasi oxirgi' => [false]]);

it('xona: bir oqimdagi guruhcha xonasi — bir smenada band (ro\'yxatda yo\'q, saqlab bo\'lmaydi), boshqa smenada bo\'sh', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '101', 'volume' => 30, 'active' => true]);
    foreach (['p/p22-02a' => 'R1', 'p/p22-02b' => null] as $g => $room) {
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
            'oqim_label' => '2-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
            'auditorium_code' => $room, 'auditorium_name' => $room ? '101' : null]);
    }
    $pl = TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 2-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice']);
    $admin = csTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $params = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'p/p22-02b', 'placement_group' => 'Ped / 2-oqim',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group'];
    $rooms = fn () => collect($as()->postJson(route('admin.timetable.cycle-assign-options', $board), $params)->assertOk()->json('rooms'))->pluck('code')->all();
    expect($rooms())->not->toContain('R1');
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['auditorium_code' => 'R1'])->assertStatus(422);
    // 02b — IV para smenasi: 02a bilan bir vaqtda emas
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['shift_start' => 7])->assertOk();
    expect($rooms())->toContain('R1');
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['auditorium_code' => 'R1'])->assertOk();
    expect($pl->fresh()->lecture_slots['shift_g'])->toBe(['p/p22-02b' => 7])
        ->and(csCall($board, 'cycleConflicts'))->toBe([]);
});

/** Qo'ng'iroq jadvali: $n ta to'liq para (80 daqiqa) — kun to'ri $n × 2 qator. */
function csBells(TimetableBoard $board, int $n): void
{
    $starts = ['08:30', '10:00', '12:00', '13:30', '15:00', '16:30', '18:00', '19:30'];
    $board->update(['bell_schedule' => array_map(fn ($t) => ['type' => 'pair', 'start' => $t,
        'end' => \Illuminate\Support\Carbon::parse($t)->addMinutes(80)->format('H:i')], array_slice($starts, 0, $n))]);
}

/** Pediatriya 5-kurs Klinik farmakologiya soatlari (ishchi reja): amaliy 30 soat — har kun 6 soat amaliy. */
function csHours(): void
{
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'Pediatriya',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 0, 'practice' => 30, 'created_at' => now(), 'updated_at' => now()]);
}

it('smena: tashqi jadval / oqim qatori uchun tanlov yo\'q va saqlanmaydi; sig\'maydigan para (V, VI) berilmaydi va rad etiladi', function () {
    $s = csSetup();
    csBells($s['board'], 6);
    $admin = csTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $flow = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 3-oqim', 'subject_name' => 'Klinik farmakologiya',
        'training_type' => 'practice', 'view' => 'flow', 'layer' => 'external'];
    expect($as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $flow)->assertOk()->json('shift_options'))->toBe([]);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $flow + ['shift_start' => 7])
        ->assertStatus(422)->assertJson(['error' => 'Smena faqat ichki jadvalda guruhcha uchun belgilanadi.']);

    // Ichki jadval, guruhcha: 6 para (12 qator), kunlik amaliy 6 soat — eng kechi IV para (7-qator)
    $params = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'p/p22-03c', 'placement_group' => 'Ped / 3-oqim',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal'];
    expect($as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $params)->assertOk()->json('shift_options'))->toBe([
        ['start' => 1, 'label' => 'I para (08:30)'], ['start' => 3, 'label' => 'II para (10:00)'],
        ['start' => 5, 'label' => 'III para (12:00)'], ['start' => 7, 'label' => 'IV para (13:30)'],
    ]);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['shift_start' => 9])->assertStatus(422);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['shift_start' => 7])->assertOk();
    expect(TimetableCyclePlacement::where('group_name', 'Ped / 3-oqim')->first()->lecture_slots['shift_g'])->toBe(['p/p22-03c' => 7]);
});

it('haftalik jadvaldagi sikl bandligi (cycleBusyByDate) — Mark bilan bir xil kun to\'ri: 8 para, smena V para — 9–14-qatorlar', function () {
    $s = csSetup(['p/p22-03c' => 9]);
    csBells($s['board'], 8);
    csHours();
    $busy = csCall($s['board'], 'cycleBusyByDate');
    $mine = array_values(array_filter($busy['2026-09-14'] ?? [], fn ($x) => str_contains($x[6], 'Ped / 3-oqim')));
    expect(array_map(fn ($x) => [$x[0], $x[1]], $mine))->toBe([[9, 15]]);
    // Mark/bandlik (cycleOccupancy) bilan bir xil
    [$units, $calendar] = csCall($s['board'], 'placedCycleUnits');
    $occ = (new ReflectionMethod(TimetableController::class, 'cycleOccupancy'))->invoke(app(TimetableController::class), $s['board'],
        array_values(array_filter($units, fn ($u) => $u['unit'] === 'p/p22-03c')), $calendar);
    expect([$occ[0]['from'], $occ[0]['to']])->toBe([9, 15]);
});

/** Butun doska: har bir joylangan ichki sikl birligi sanasi (from) — "Sikl jadvali" (cyclePlan, ichki qatlam) blokiniki bilan bir xil. */
function csAssertUnitsMatchPlan(TimetableBoard $board): int
{
    $plan = test()->withoutVite()->actingAs(csTeacher('ADMIN' . random_int(1, 99999), 'admin'), 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $board), ['layer' => 'internal'])->assertOk()->json();
    $fromPlan = [];
    foreach ($plan['rows'] as $row) {
        foreach ($row['blocks'] as $b) {
            $fromPlan[$row['group'] . '|' . $b['subject']] = $b['from'];
        }
    }
    [$units] = csCall($board, 'cycleInternalUnits');
    $fromUnits = [];
    foreach ($units as $u) {
        if ($u['placement']) {
            $fromUnits[$u['group'] . '|' . $u['subject']] = $u['from'];
        }
    }
    ksort($fromPlan);
    ksort($fromUnits);
    expect($fromUnits)->toBe($fromPlan);

    return count($fromPlan);
}

it('sana (board 7 holati): oqimda 8 ta fan bloki, guruhchalarda turli o\'qituvchilar — ogohlantirishda Mark\'dagi 30.10–10.11', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    $subjects = ['Ichki kasalliklar propedevtikasi', 'Pediatriya', 'Xirurgiya', 'Nevrologiya', 'Dermatologiya', 'Oftalmologiya', 'LOR',
        'Klinik farmakologiya'];
    foreach ($subjects as $subj) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
            'subject_name' => $subj, 'mode' => 'cycle', 'cycle_days' => $subj === 'Klinik farmakologiya' ? 10 : 5]);
    }
    $yoqubov = csTeacher('YOQUBOV U. B.');
    $groups = ["p/p22-02a (o'z)" => '2-oqim', "p/p22-02b (o'z)" => '2-oqim', "p/p22-03c (o'z)" => '3-oqim', "p/p22-03a (o'z)" => '3-oqim'];
    foreach ($groups as $g => $oqim) {
        foreach ($subjects as $i => $subj) {
            // 02a ning birinchi kartasi (Ichki kasalliklar) — boshqa oqimda (1-oqim)
            $label = $g === "p/p22-02a (o'z)" && $i === 0 ? '1-oqim' : $oqim;
            $t = $subj === 'Klinik farmakologiya' && in_array($g, ["p/p22-02a (o'z)", "p/p22-03c (o'z)"], true) ? $yoqubov : csTeacher('T ' . $g . ' ' . $i);
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
                'oqim_label' => $label, 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
                'subject_name' => $subj, 'group_name' => $g, 'kafedra_name' => 'K', 'teacher_id' => $t->id, 'teacher_name' => $t->full_name]);
        }
    }
    // Oqim bloklari ketma-ket (har biri 5 kun); Klinik farmakologiya — 30.10 (46-kun) dan 10 kun. 1-oqimda shu fanlar 15.12 (92-kun) dan.
    foreach (['2-oqim', '3-oqim', '1-oqim'] as $oq) {
        foreach ($subjects as $i => $subj) {
            TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / ' . $oq,
                'subject_name' => $subj, 'training_type' => 'practice',
                'start_index' => $oq === '1-oqim' ? 92 : ($subj === 'Klinik farmakologiya' ? 46 : $i * 6)]);
        }
    }
    $card = TimetableCard::where('board_id', $board->id)->where('group_name', "p/p22-03c (o'z)")->where('subject_name', 'Klinik farmakologiya')->get();
    expect(csCall($board, 'unitBusy', $card)['teachers'][$yoqubov->id])->toBe("p/p22-02a (o'z), Klinik farmakologiya, 30.10–10.11")
        ->and(csCall($board, 'cycleConflicts')["Pediatriya|5|p/p22-03c (o'z)|Klinik farmakologiya|practice"]['text'])
        ->toBe("O'qituvchi YOQUBOV U. B. band: p/p22-02a (o'z), Klinik farmakologiya, 30.10–10.11");
    expect(csAssertUnitsMatchPlan($board))->toBe(32);
});

it('butun doska: ichki sikl birliklari sanalari cyclePlan bloklari bilan bir xil (tasodifiy oqimlar, guruhcha joylashuvlari)', function (int $seed) {
    mt_srand($seed);
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    $subjects = ['Klinik farmakologiya', 'Ichki kasalliklar', 'Pediatriya', 'Xirurgiya', 'Nevrologiya', 'Dermatologiya', 'Oftalmologiya', 'LOR'];
    foreach ($subjects as $i => $subj) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
            'subject_name' => $subj, 'mode' => 'cycle', 'cycle_days' => 5 + $i % 4]);
    }
    $groups = ["p/p22-02a (o'z)", "p/p22-02b (o'z)", "p/p22-03c (o'z)", "p/p22-03a (o'z)"];
    foreach ($groups as $g) {
        foreach ($subjects as $subj) {
            $oqim = mt_rand(0, 3) ? (str_contains($g, '-02') ? '2-oqim' : '3-oqim') : (mt_rand(0, 1) ? '1-oqim' : null);
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
                'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
                'subject_name' => $subj, 'group_name' => $g, 'kafedra_name' => 'K']);
        }
    }
    foreach (['Ped / 1-oqim', 'Ped / 2-oqim', 'Ped / 3-oqim', ...$groups] as $row) {
        foreach ($subjects as $subj) {
            if (mt_rand(0, str_starts_with($row, 'Ped') ? 4 : 0) === 1 || (str_starts_with($row, 'Ped') && mt_rand(0, 3))) {
                TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => $row,
                    'subject_name' => $subj, 'start_index' => mt_rand(0, 80), 'training_type' => mt_rand(0, 3) ? 'practice' : null]);
            }
        }
    }
    expect(csAssertUnitsMatchPlan($board))->toBeGreaterThan(0);
})->with(range(1, 6));

it('kafedra mudiri: sikl jadvali "Kafedraniki" qamrovida ochiladi — faqat o\'z kafedrasi bloklari', function () {
    $s = csSetup();
    TimetableCard::where('board_id', $s['board']->id)->where('group_name', 'p/p22-02a')->update(['kafedra_name' => 'Boshqa kafedra']);
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 701, 'name' => 'Farmakologiya kafedrasi', 'code' => 'F',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $head = csTeacher('MUDIR F.', 'kafedra_mudiri');
    $head->forceFill(['department_hemis_id' => 701])->save();
    $plan = test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'kafedra' => 1])->assertOk()->json();
    expect(collect($plan['rows'])->filter(fn ($r) => $r['blocks'])->pluck('group')->values()->all())->toBe(['p/p22-03c']);
});

it('ma\'ruza vaqtini o\'zgartirish (oqim, guruhcha, eski belgilar) guruhcha smenasini o\'chirmaydi', function () {
    $s = csSetup(['p/p22-03c' => 7]);
    $admin = csTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $p = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 3-oqim', 'subject_name' => 'Klinik farmakologiya',
        'training_type' => 'practice', 'view' => 'group'];
    $slots = fn () => TimetableCyclePlacement::where('group_name', 'Ped / 3-oqim')->first()->lecture_slots;
    $as()->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), $p + ['day_index' => 0, 'start_hour' => 5])->assertOk();
    expect($slots())->toBe(['shift_g' => ['p/p22-03c' => 7], 'starts' => [5], 'starts_g' => []]);
    $as()->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), $p + ['day_index' => 1, 'start_hour' => 3, 'sub_group' => 'p/p22-03c'])->assertOk();
    expect($slots())->toBe(['shift_g' => ['p/p22-03c' => 7], 'starts' => [5], 'starts_g' => ['p/p22-03c' => ['1' => 3]]]);
    $as()->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), $p + ['slots_json' => json_encode(['0' => [1, 2]])])->assertOk();
    expect($slots()['shift_g'])->toBe(['p/p22-03c' => 7]);
});

it('smena ma\'ruzadan oldin (02a/03b): ma\'ruza 12:00, 02a amaliy 8:30–11:20, 03b amaliy ma\'ruzadan keyin — to\'qnashuv yo\'q; Mark, bandlik, HEMIS bir xil', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    csBells($board, 6);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    // Reja: 10 soat ma'ruza, 20 soat amaliy — har kun ma'ruza 2 + amaliy 4 soat
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'Pediatriya',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 10, 'practice' => 20, 'created_at' => now(), 'updated_at' => now()]);
    $t = csTeacher('YOQUBOV U. B.');
    $lector = csTeacher('LEKTOR');
    $base = ['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5, 'oqim_label' => '2-oqim',
        'lang' => 'uz', 'students' => 10, 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'kafedra_name' => 'K'];
    foreach (['p/p22-02a', 'p/p22-03b'] as $g) {
        TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => $g, 'teacher_id' => $t->id, 'teacher_name' => 'YOQUBOV U. B.']);
    }
    TimetableCard::create($base + ['training_type' => 'lecture', 'group_name' => null, 'group_names' => ['p/p22-02a', 'p/p22-03b'],
        'students' => 20, 'teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR']);
    // Oqim ma'ruzasi har kuni 12:00 (5-soat); 02a — I para smenasi, 03b — IV para
    TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 2-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice',
        'lecture_slots' => ['starts' => [5, 5, 5, 5, 5], 'shift_g' => ['p/p22-02a' => 1, 'p/p22-03b' => 7]]]);

    $admin = csTeacher('ADMIN', 'admin');
    $plan = test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $board), ['layer' => 'internal'])->assertOk()->json();
    $day = fn (string $g) => collect(collect($plan['rows'])->firstWhere('group', $g)['blocks'][0]['day_plan'])
        ->map(fn ($d) => [$d['lec'], $d['start'], $d['prc'], $d['ps']])->unique()->values()->all();
    expect($day('p/p22-02a'))->toBe([[2, 5, 4, 1]])      // amaliy 1–4 (8:30–11:20), ma'ruza 5–6 (12:00–13:20)
        ->and($day('p/p22-03b'))->toBe([[2, 5, 4, 7]]);  // ma'ruza 5–6, amaliy 7–10 (13:30–16:20)

    // Bandlik: bitta amaliy o'qituvchi — kesishmaydi
    expect(csCall($board, 'cycleConflicts'))->toBe([]);
    [$units, $calendar] = csCall($board, 'placedCycleUnits');
    $occ = collect((new ReflectionMethod(TimetableController::class, 'cycleOccupancy'))->invoke(app(TimetableController::class), $board, $units, $calendar))
        ->where('date', '2026-09-14');
    $span = fn (string $g, string $kind) => $occ->filter(fn ($e) => str_starts_with($e['label'], $g) && str_starts_with($e['load_key'], $kind))
        ->map(fn ($e) => [$e['from'], $e['to']])->values()->all();
    expect($span('p/p22-02a', 'c'))->toBe([[1, 5]])->and($span('p/p22-02a', 'l'))->toBe([[5, 7]])
        ->and($span('p/p22-03b', 'c'))->toBe([[7, 11]])->and($span('p/p22-03b', 'l'))->toBe([[5, 7]]);

    // HEMIS: 02a amaliy 1-soatdan (8:30), ma'ruza 5-soatdan (12:00); 03b amaliy 7-soatdan (13:30)
    $lessons = (new ReflectionMethod(TimetableController::class, 'hemisCycleLessons'))
        ->invoke(app(TimetableController::class), $board, [], TimetableCard::where('board_id', $board->id)->get());
    $first = function (string $g, string $part) use ($lessons) {
        foreach ($lessons as $gk => $bySubject) {
            if (str_contains($gk, mb_strtolower(substr($g, -3)))) {
                foreach ($bySubject as $list) {
                    foreach ($list as $l) {
                        if ($l['part'] === $part && $l['date'] === '2026-09-14') {
                            return [$l['slot'], $l['len']];
                        }
                    }
                }
            }
        }

        return null;
    };
    expect($first('p/p22-02a', 'A'))->toBe([1, 4])->and($first('p/p22-02a', 'L'))->toBe([5, 2])
        ->and($first('p/p22-03b', 'A'))->toBe([7, 4]);

    // Smena ma'ruza bilan kesishsa (II para: 3–6 va ma'ruza 5–6) — amaliy ma'ruzadan keyin (7–10)
    TimetableCyclePlacement::where('board_id', $board->id)->update(['lecture_slots' => json_encode(['starts' => [5, 5, 5, 5, 5],
        'shift_g' => ['p/p22-02a' => 3, 'p/p22-03b' => 1]])]);
    [$units, $calendar] = csCall($board, 'placedCycleUnits');
    $occ = collect((new ReflectionMethod(TimetableController::class, 'cycleOccupancy'))->invoke(app(TimetableController::class), $board, $units, $calendar))
        ->where('date', '2026-09-14');
    $span = fn (string $g, string $kind) => $occ->filter(fn ($e) => str_starts_with($e['label'], $g) && str_starts_with($e['load_key'], $kind))
        ->map(fn ($e) => [$e['from'], $e['to']])->values()->all();
    expect($span('p/p22-02a', 'c'))->toBe([[7, 11]])->and($span('p/p22-03b', 'c'))->toBe([[1, 5]]);

    // Ikkala smena ham I para bo'lsa — amaliy darslar ustma-ust: to'qnashuv
    TimetableCyclePlacement::where('board_id', $board->id)->update(['lecture_slots' => json_encode(['starts' => [5, 5, 5, 5, 5],
        'shift_g' => ['p/p22-02a' => 1, 'p/p22-03b' => 1]])]);
    expect(array_keys(csCall($board, 'cycleConflicts')))->toBe(['Pediatriya|5|p/p22-02a|Klinik farmakologiya|practice',
        'Pediatriya|5|p/p22-03b|Klinik farmakologiya|practice']);
});
