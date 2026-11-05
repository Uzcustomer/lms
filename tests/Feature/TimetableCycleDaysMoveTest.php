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

// Sikl jadvali: bir nechta kunni bitta surish (cycle-lecture-slots, day_indexes + delta / start_hour + mode, dry_run, force, undo).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-05 09:00', 'Asia/Tashkent')));

function dmTeacher(string $name, ?string $role = null, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Ichki kasalliklar kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ], $extra))->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

function dmAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/**
 * Ichki kasalliklar (4-kurs, sikl 6 kun) — oqim "F1 / 1-oqim": d2/d23-04a, d2/d23-04b; 16.11.2026 (dushanba) dan. Kun: ma'ruza 2 soat
 * (1–2-soat, 08:30), amaliy 4 soat — ikkala guruhcha smenasi 7-soatdan (13:30), ya'ni ma'ruza bilan amaliy orasida bo'sh soatlar bor.
 */
function dmSetup(): array
{
    Role::findOrCreate('admin', 'web');
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'subject_name' => 'Ichki kasalliklar', 'mode' => 'cycle', 'cycle_days' => 6]);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2023/2024', 'level_code' => '14', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Ichki kasalliklar',
        'semester' => 7, 'lecture' => 12, 'practice' => 24, 'created_at' => now(), 'updated_at' => now()]);
    $lektor = dmTeacher('LEKTOR L.');
    $ustoz = dmTeacher('USTOZ U.');
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 4, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Ichki kasalliklar', 'kafedra_name' => 'Ichki kasalliklar kafedrasi'];
    $ustoz2 = dmTeacher('USTOZ2 U.');
    foreach (['d2/d23-04a' => $ustoz, 'd2/d23-04b' => $ustoz2] as $g => $t) {
        TimetableCard::create($base + ['students' => 10, 'training_type' => 'practice', 'group_name' => $g,
            'teacher_id' => $t->id, 'teacher_name' => $t->full_name]);
    }
    TimetableCard::create($base + ['students' => 20, 'training_type' => 'lecture', 'group_names' => ['d2/d23-04a', 'd2/d23-04b'],
        'teacher_id' => $lektor->id, 'teacher_name' => 'LEKTOR L.']);
    $ctl = app(TimetableController::class);
    [, [$dates, $work]] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $board);
    $start = collect($work)->first(fn ($i) => $dates[$i]->toDateString() === '2026-11-16');
    $placement = TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Ichki kasalliklar', 'start_index' => $start, 'training_type' => 'practice',
        'lecture_slots' => ['shift_g' => ['d2/d23-04a' => 7, 'd2/d23-04b' => 7]]]);
    $admin = dmTeacher('ADMIN', 'admin');

    return compact('board', 'placement', 'admin', 'lektor', 'ustoz', 'ustoz2');
}

/** Guruhchaning sana bo'yicha darslari: [sana => ['lec' => [boshi, oxiri) | null, 'prc' => ...]]. */
function dmDays(array $s, string $group = 'd2/d23-04a'): array
{
    request()->attributes->remove('timetable.cycle_units.' . $s['board']->id);
    $x = app(TimetableController::class)->cycleProposalUnits($s['board'],
        ['specialty_name' => 'davolash ishi', 'course' => 4, 'subject_name' => 'Ichki kasalliklar', 'group_name' => $group], null)[0];
    $out = [];
    foreach ($x['dates'] as $d) {
        $out[$d] = ['lec' => $x['lec'][$d] ?? null, 'prc' => $x['prac'][$d] ?? null];
    }

    return $out;
}

function dmMove(array $s, Teacher $as, string $role, array $extra)
{
    return dmAs($as, $role)->postJson(route('admin.timetable.cycle-lecture-slots', $s['board']), $extra + ['specialty_name' => 'davolash ishi',
        'course' => 4, 'subject_name' => 'Ichki kasalliklar', 'group_name' => 'F1 / 1-oqim', 'training_type' => 'practice', 'view' => 'group']);
}

/** Kafedra mudiri: Ichki kasalliklar kafedrasi (HEMIS id 601). */
function dmHead(): Teacher
{
    if (!DB::table('departments')->where('department_hemis_id', 601)->exists()) {
        DB::table('departments')->insert(['department_hemis_id' => 601, 'name' => 'Ichki kasalliklar kafedrasi', 'code' => 'K1', 'structure_type_code' => 12,
            'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    return dmTeacher('MUDIR M.', 'kafedra_mudiri', ['department_hemis_id' => 601]);
}

it('admin: Ctrl bilan 4 kun, M katak 2 soat pastga — 4 kunda ma\'ruza 08:30 → 10:00, amaliy joyida, qolgan kunlar o\'zgarmaydi; Ctrl+Z qaytaradi', function () {
    $s = dmSetup();
    $before = $s['placement']->fresh()->lecture_slots;
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1, 2, 3], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    expect(array_column($r['days'], 'status'))->toBe(['ok', 'ok', 'ok', 'ok'])
        ->and(array_column($r['days'], 'date'))->toBe(['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'])
        ->and($r['days'][0])->toMatchArray(['from' => 1, 'to' => 3, 'applied' => true])
        ->and($r['applied'])->toBe(4)->and($r['undo'])->toBeString();
    foreach (['d2/d23-04a', 'd2/d23-04b'] as $g) {
        $d = dmDays($s, $g);
        foreach (['2026-11-16', '2026-11-17', '2026-11-18', '2026-11-19'] as $date) {
            expect($d[$date])->toBe(['lec' => [3, 5], 'prc' => [7, 11]]);
        }
        expect($d['2026-11-20'])->toBe(['lec' => [1, 3], 'prc' => [7, 11]])->and($d['2026-11-21'])->toBe(['lec' => [1, 3], 'prc' => [7, 11]]);
    }
    // Ctrl+Z — bitta so'rov: oldingi qiymatlar to'liq tiklanadi; ikkinchi marta — yo'q.
    dmMove($s, $s['admin'], 'admin', ['undo' => $r['undo']])->assertOk()->assertJsonPath('undone', true);
    expect($s['placement']->fresh()->lecture_slots)->toBe($before)
        ->and(dmDays($s)['2026-11-17'])->toBe(['lec' => [1, 3], 'prc' => [7, 11]]);
    dmMove($s, $s['admin'], 'admin', ['undo' => $r['undo']])->assertStatus(410);
});

it('qaytarish: blok keyin yana o\'zgargan bo\'lsa — rad etiladi (boshqa o\'zgarish yo\'qolmaydi)', function () {
    $s = dmSetup();
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    dmMove($s, $s['admin'], 'admin', ['day_indexes' => [4], 'delta' => 1, 'mode' => 'lecture'])->assertOk();
    dmMove($s, $s['admin'], 'admin', ['undo' => $r['undo']])->assertStatus(409);
    expect(dmDays($s)['2026-11-16']['lec'])->toBe([3, 5])->and(dmDays($s)['2026-11-20']['lec'])->toBe([2, 4]);
});

it('"Hammasini tanlash" + A katak: butun blokda faqat amaliy (shu guruhcha), ma\'ruza va boshqa guruhcha joyida', function () {
    $s = dmSetup();
    // Guruhcha qatori (front: group_name — guruhcha, placement_group — oqim, sub_group).
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1, 2, 3, 4, 5], 'delta' => -2, 'mode' => 'practice', 'group_name' => 'd2/d23-04a',
        'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a'])->assertOk()->json();
    expect(array_unique(array_column($r['days'], 'status')))->toBe(['ok'])->and($r['applied'])->toBe(6)
        ->and($r['days'][0])->toMatchArray(['from' => 7, 'to' => 5]);
    foreach (dmDays($s, 'd2/d23-04a') as $d) {
        expect($d)->toBe(['lec' => [1, 3], 'prc' => [5, 9]]);
    }
    foreach (dmDays($s, 'd2/d23-04b') as $d) {
        expect($d)->toBe(['lec' => [1, 3], 'prc' => [7, 11]]);
    }
    expect($s['placement']->fresh()->lecture_slots['shift_g'])->toBe(['d2/d23-04a' => 7, 'd2/d23-04b' => 7]);   // smenalar o'zgarmaydi
    // Amaliy o'qituvchisi bandligi ham tekshiriladi: 04b ham USTOZ U. da (7–10) — 04a ni 9-soatga surish to'qnashadi.
    TimetableCard::where('group_name', 'd2/d23-04b')->update(['teacher_id' => $s['ustoz']->id, 'teacher_name' => 'USTOZ U.']);
    $c = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'delta' => 4, 'mode' => 'practice', 'group_name' => 'd2/d23-04a',
        'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($c['status'])->toBe('conflict')->and($c['why'])->toContain("O'qituvchi band: d2/d23-04b, Ichki kasalliklar, 16.11");
});

it('bitta kunda to\'qnashuv: oldindan ko\'rinishda sababi bilan, "Qo\'llash" — qolganlariga, "Baribir" — o\'shanga ham', function () {
    $s = dmSetup();
    // Chorshanba 18.11, 3–4-soat: d2/d23-04a haftalik darsda (Fizika).
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 4,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Fizika', 'group_name' => 'd2/d23-04a', 'kafedra_name' => 'Fizika kafedrasi', 'day' => 3, 'pair' => 3]);
    $before = $s['placement']->fresh()->lecture_slots;
    $dry = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1, 2, 3], 'delta' => 2, 'mode' => 'lecture', 'dry_run' => 1])->assertOk()->json();
    expect(array_column($dry['days'], 'status'))->toBe(['ok', 'ok', 'conflict', 'ok'])
        ->and($dry['days'][2]['why'])->toContain('Guruh band: d2/d23-04a, Fizika')
        ->and(array_column($dry['days'], 'applied'))->toBe([false, false, false, false])
        ->and($dry['applied'])->toBe(0)->and($dry['undo'])->toBeNull()
        ->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    // "Qo'llash" — to'qnashuvsizlarga.
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1, 2, 3], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    expect(array_column($r['days'], 'applied'))->toBe([true, true, false, true])->and($r['applied'])->toBe(3);
    $d = dmDays($s);
    expect($d['2026-11-17']['lec'])->toBe([3, 5])->and($d['2026-11-18']['lec'])->toBe([1, 3]);
    // "Baribir hammasiga" — bandlikka qaramay.
    $f = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [2], 'delta' => 2, 'mode' => 'lecture', 'force' => 1])->assertOk()->json();
    expect($f['days'][0])->toMatchArray(['status' => 'conflict', 'applied' => true])->and(dmDays($s)['2026-11-18']['lec'])->toBe([3, 5]);
});

it('kun oxiriga sig\'maydigan kun — "sig\'maydi", o\'zgarmaydi; ma\'ruza amaliy bilan ustma-ust — o\'zgarmaydi (force ham)', function () {
    $s = dmSetup();
    $before = $s['placement']->fresh()->lecture_slots;
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1], 'delta' => 11, 'mode' => 'lecture', 'force' => 1])->assertOk()->json();
    expect(array_column($r['days'], 'status'))->toBe(['nofit', 'nofit'])->and($r['days'][0]['why'])->toContain('sig‘maydi')
        ->and($r['applied'])->toBe(0)->and($r['undo'])->toBeNull()->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    // 1–2 → 7–8: amaliy (7–10) bilan ustma-ust.
    $o = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'delta' => 6, 'mode' => 'lecture', 'force' => 1])->assertOk()->json();
    expect($o['days'][0]['status'])->toBe('blocked')->and($o['days'][0]['why'])->toContain('amaliy bilan ustma-ust')
        ->and($s['placement']->fresh()->lecture_slots)->toBe($before);
});

it('"Birga" (tugma bilan): kun boshi barcha tanlangan kunlarda; start_hour — hammasiga bir xil', function () {
    $s = dmSetup();
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1, 2], 'delta' => 2, 'mode' => 'both'])->assertOk()->json();
    expect(array_column($r['days'], 'status'))->toBe(['ok', 'ok'])->and($r['days'][0])->toMatchArray(['from' => 1, 'to' => 3]);
    $d = dmDays($s);
    expect($d['2026-11-17']['lec'])->toBe([3, 5])->and($d['2026-11-18']['lec'])->toBe([3, 5])->and($d['2026-11-16']['lec'])->toBe([1, 3]);
    dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1, 2, 3], 'start_hour' => 2, 'mode' => 'lecture'])->assertOk();
    $d = dmDays($s);
    expect([$d['2026-11-17']['lec'], $d['2026-11-18']['lec'], $d['2026-11-19']['lec']])->toBe([[2, 4], [2, 4], [2, 4]]);
});

it('o\'tgan va bugungi kunlar: o\'tgan — yo\'q; bugungi — admin / registrator (boshlangan bo\'lsa ham), kafedra mudiri — yo\'q', function () {
    $s = dmSetup();
    $head = dmHead();
    $reg = dmTeacher('REG R.', 'registrator_ofisi');
    // 17.11, 08:00 — 16.11 o'tgan, 17.11 dagi ma'ruza (08:30) hali boshlanmagan.
    $this->travelTo(Carbon::parse('2026-11-17 08:00', 'Asia/Tashkent'));
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1, 2], 'delta' => 2, 'mode' => 'lecture', 'dry_run' => 1])->assertOk()->json();
    expect(array_column($r['days'], 'status'))->toBe(['past', 'ok', 'ok']);
    $m = dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [0, 1, 2], 'delta' => 2, 'mode' => 'lecture', 'dry_run' => 1])->assertOk()->json();
    expect(array_column($m['days'], 'status'))->toBe(['past', 'past', 'ok'])
        ->and(array_column($m['days'], 'why'))->toBe(['O‘tgan kun.', "Bugungi darsni faqat admin va registrator ofisi ko'chiradi.", null]);
    // Registrator ofisi — darhol (ichki jadvalni boshqa tahrirlay olmasa ham); bugungi kun ham — tarixda belgi, javobda HEMIS eslatmasi.
    $g = dmMove($s, $reg, 'registrator_ofisi', ['day_indexes' => [1, 2], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    expect($g['applied'])->toBe(2)->and(dmDays($s)['2026-11-17']['lec'])->toBe([3, 5])
        ->and($g['today_moved'])->toBeTrue()->and($g['hemis_hint'])->toContain('«Bugungi darslar (HEMIS)»')
        ->and($g['days'][0]['today'])->toBeTrue()->and($g['days'][1]['today'])->toBeFalse()
        ->and(\App\Models\TimetableChangeRequest::latest('id')->first()->summary)->toContain('bugungi dars ko‘chirildi');
    // 15:00 — bugungi dars (10:00 dan) boshlangan va tugagan: admin baribir ko'chira oladi (o'tib ketgan paraga ham).
    $this->travelTo(Carbon::parse('2026-11-17 15:00', 'Asia/Tashkent'));
    $back = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => -2, 'mode' => 'lecture'])->assertOk()->json();
    expect($back['days'][0]['status'])->toBe('ok')->and(dmDays($s)['2026-11-17']['lec'])->toBe([1, 3]);
    // Kafedra mudiri — bugun yopiq, sababi bilan; kechagi kun — admin uchun ham yopiq.
    expect(dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [1], 'delta' => 1, 'mode' => 'lecture', 'dry_run' => 1])->json('days.0'))
        ->toMatchArray(['status' => 'past', 'why' => "Bugungi darsni faqat admin va registrator ofisi ko'chiradi."]);
    expect(dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'delta' => 1, 'mode' => 'lecture', 'dry_run' => 1])->json('days.0'))
        ->toMatchArray(['status' => 'past', 'why' => 'O‘tgan kun.']);
    // Registrator kun soatini sura oladi (day_edit), lekin sikl blokini joylay olmaydi.
    $plan = dmAs($reg, 'registrator_ofisi')->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'])->assertOk()->json();
    expect($plan['day_edit'])->toBeTrue()->and($plan['can_edit'])->toBeFalse();
});

it('rollar: o\'qituvchi va fan mas\'uli surolmaydi (so\'rov orqali); kafedra mudiri — o\'z kafedrasi fani, boshqasi 403', function () {
    $s = dmSetup();
    foreach ([['oqituvchi', $s['ustoz']], ['fan_masuli', dmTeacher('FM', 'fan_masuli')]] as [$role, $who]) {
        if ($role === 'oqituvchi') {
            Role::findOrCreate('oqituvchi', 'web');
            $who->assignRole('oqituvchi');
        }
        dmMove($s, $who, $role, ['day_indexes' => [1, 2], 'delta' => 2, 'mode' => 'lecture'])->assertForbidden();
    }
    $head = dmHead();
    dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [1, 2], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->assertJsonPath('applied', 2);
    TimetableCard::where('board_id', $s['board']->id)->update(['kafedra_name' => 'Jarrohlik kafedrasi']);
    dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [3], 'delta' => 2, 'mode' => 'lecture'])->assertForbidden();
});

it('tasdiq talab qilinadigan doska: kafedra mudiri — oldindan ko\'rinish (dry_run) so\'rov emas, qo\'llash — bitta so\'rov; tasdiqlangach hammasi', function () {
    $s = dmSetup();
    $head = dmHead();
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['approval_required' => true])]);
    $dry = dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [1, 2, 3], 'delta' => 2, 'mode' => 'lecture', 'dry_run' => 1])->assertOk()->json();
    expect(array_column($dry['days'], 'status'))->toBe(['ok', 'ok', 'ok'])
        ->and(\App\Models\TimetableChangeRequest::count())->toBe(0);
    $r = dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [1, 2, 3], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    expect($r['pending_approval'] ?? false)->toBeTrue()->and(dmDays($s)['2026-11-17']['lec'])->toBe([1, 3]);
    $req = \App\Models\TimetableChangeRequest::latest('id')->first();
    expect($req->summary)->toContain('2, 3, 4-kun')->toContain('+2 soatga');
    dmAs($s['admin'], 'admin')->postJson(route('admin.timetable.change-requests.approve', $req->id))->assertOk();
    $d = dmDays($s);
    expect([$d['2026-11-17']['lec'], $d['2026-11-18']['lec'], $d['2026-11-19']['lec'], $d['2026-11-20']['lec']])->toBe([[3, 5], [3, 5], [3, 5], [1, 3]]);
});

it('qaytarish kaliti faqat o\'z blokiga: boshqa blok bilan — 410, hech narsa o\'zgarmaydi', function () {
    $s = dmSetup();
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->json();
    $other = TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 4,
        'group_name' => 'F1 / 1-oqim', 'subject_name' => 'Jarrohlik', 'start_index' => 0, 'training_type' => 'practice', 'lecture_slots' => ['x' => 1]]);
    dmMove($s, $s['admin'], 'admin', ['undo' => $r['undo'], 'subject_name' => 'Jarrohlik'])->assertStatus(410);
    expect($other->fresh()->lecture_slots)->toBe(['x' => 1])->and(dmDays($s)['2026-11-17']['lec'])->toBe([3, 5]);
});

it('"Birga" oqim qatori: shu kunni o\'zi surgan guruhcha hisobga olinmaydi (uning kech darsi "sig\'maydi" demaydi)', function () {
    $s = dmSetup();
    // 04a 17.11 da o'z vaqtida: kun 7-soatdan — ma'ruza 7–8, amaliy 9–12 (kun oxirigacha).
    $s['placement']->update(['lecture_slots' => array_merge($s['placement']->lecture_slots, ['starts_g' => ['d2/d23-04a' => [1 => 7]]])]);
    expect(dmDays($s)['2026-11-17'])->toBe(['lec' => [7, 9], 'prc' => [9, 13]]);
    $r = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 2, 'mode' => 'both'])->assertOk()->json();
    expect($r['days'][0]['status'])->toBe('ok')->and(dmDays($s, 'd2/d23-04b')['2026-11-17']['lec'])->toBe([3, 5])
        ->and(dmDays($s)['2026-11-17'])->toBe(['lec' => [7, 9], 'prc' => [9, 13]]);
});

it('bitta kunni "Birga" surish — avvalgidek bandlik tekshirilmaydi (regressiya)', function () {
    $s = dmSetup();
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 4,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Fizika', 'group_name' => 'd2/d23-04a', 'kafedra_name' => 'Fizika kafedrasi', 'day' => 3, 'pair' => 3]);
    dmMove($s, $s['admin'], 'admin', ['day_index' => 2, 'start_hour' => 3])->assertOk();
    expect(dmDays($s)['2026-11-18']['lec'])->toBe([3, 5]);
});

it('Codex: tasdiq talab qilinadigan doskada bitta kunlik so\'rov dry_run bilan ham so\'rov bo\'ladi (tasdiqni chetlab o\'tmaydi)', function () {
    $s = dmSetup();
    $head = dmHead();
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['approval_required' => true])]);
    $r = dmMove($s, $head, 'kafedra_mudiri', ['day_index' => 1, 'start_hour' => 3, 'mode' => 'lecture', 'dry_run' => 1])->assertOk()->json();
    expect($r['pending_approval'] ?? false)->toBeTrue()->and(dmDays($s)['2026-11-17']['lec'])->toBe([1, 3])
        ->and(\App\Models\TimetableChangeRequest::count())->toBe(1);
    // slots_json yo'li ham.
    dmMove($s, $head, 'kafedra_mudiri', ['slots_json' => '{"1":[3,4]}', 'dry_run' => 1])->assertOk()->assertJsonPath('pending_approval', true);
    expect(\App\Models\TimetableChangeRequest::count())->toBe(2);
});

it('Codex: ko\'p kunli "Birga" — ko\'chgan amaliy darsning o\'qituvchi bandligi ham tekshiriladi', function () {
    $s = dmSetup();
    // 04a smenasiz: amaliy ma'ruzadan keyin (3–6); "Birga" +2 — ma'ruza 3–4, amaliy 5–8. USTOZ U. dushanba 5–6-soatda haftalik darsda.
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04b' => 7]]]);
    expect(dmDays($s)['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 7]]);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 3,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Anatomiya', 'group_name' => 'd2/d24-01a', 'kafedra_name' => 'Anatomiya kafedrasi', 'day' => 1, 'pair' => 5,
        'teacher_id' => $s['ustoz']->id, 'teacher_name' => 'USTOZ U.']);
    $d = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0, 1], 'delta' => 2, 'mode' => 'both', 'dry_run' => 1])->assertOk()->json('days');
    expect(array_column($d, 'status'))->toBe(['conflict', 'ok'])->and($d[0]['why'])->toContain("O'qituvchi band: d2/d24-01a, Anatomiya");
});

it('Codex: "Birga" — smenasi qotirilgan amaliy (shift_g) surilmaydi, kunga sig\'ishi shunga qarab hisoblanadi', function () {
    $s = dmSetup();
    // Ma'ruza 1–2, amaliy smenada 7–10 (qotirilgan): +3 — ma'ruza 4–5, amaliy joyida — sig'adi va kesishmaydi.
    $d = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'delta' => 3, 'mode' => 'both'])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [4, 6], 'prc' => [7, 11]]);
    // +9 — ma'ruza 10–11 amaliy bilan ustma-ust: o'zgarmaydi; +11 — kunga sig'maydi.
    expect(dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 9, 'mode' => 'both', 'dry_run' => 1])->json('days.0.status'))->toBe('blocked')
        ->and(dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 11, 'mode' => 'both', 'dry_run' => 1])->json('days.0.status'))->toBe('nofit');
});

it('Codex: oqim qatorida "Faqat amaliy" — guruhchalar har xil soatda bo\'lsa ham, kunga sig\'ishi yangi joy (bitta soat) bo\'yicha', function () {
    $s = dmSetup();
    // 04a amaliyoti 7–10, 04b — 9–12; oqim qatorida +1: ikkalasi 8-soatdan (8–11) — sig'adi (eski oraliq 7–12 ni surish noto'g'ri "sig'maydi" edi).
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04a' => 7, 'd2/d23-04b' => 9]]]);
    expect(dmDays($s, 'd2/d23-04b')['2026-11-16']['prc'])->toBe([9, 13]);
    $d = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'delta' => 1, 'mode' => 'practice'])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-16']['prc'])->toBe([8, 12])
        ->and(dmDays($s, 'd2/d23-04b')['2026-11-16']['prc'])->toBe([8, 12]);
    // +6 — 13-soatdan: sig'maydi (eng uzun amaliy 4 soat).
    $n = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 6, 'mode' => 'practice', 'dry_run' => 1])->assertOk()->json('days.0');
    expect($n['status'])->toBe('nofit')->and($n['why'])->toContain('4 soat 13-soatdan');
});

it('Codex: oqim qatorida "Faqat amaliy" start_hour — birinchi guruhcha shu soatda bo\'lsa ham, boshqasi surilsa "o\'zgarmaydi" emas', function () {
    $s = dmSetup();
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04a' => 7, 'd2/d23-04b' => 9]]]);
    $d = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'start_hour' => 7, 'mode' => 'practice'])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s, 'd2/d23-04b')['2026-11-16']['prc'])->toBe([7, 11])
        ->and(dmDays($s)['2026-11-16']['prc'])->toBe([7, 11]);
    // Hammasi allaqachon 7-soatda — o'zgarmaydi.
    expect(dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'start_hour' => 7, 'mode' => 'practice', 'dry_run' => 1])->json('days.0.status'))->toBe('same');
});

it('Codex: guruhcha qatorida "Birga" — smenali guruhchaning o\'z kun boshi (starts_g) bo\'lsa amaliy kun boshiga ergashadi, oraliqqa kiradi', function () {
    $s = dmSetup();
    // 04a: smena 7, lekin 16.11 da o'z kun boshi 3 — ma'ruza 3–4, amaliy ergashadi (5–8).
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04a' => 7, 'd2/d23-04b' => 7], 'starts_g' => ['d2/d23-04a' => [0 => 3]]]]);
    expect(dmDays($s)['2026-11-16'])->toBe(['lec' => [3, 5], 'prc' => [5, 9]]);
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both', 'day_indexes' => [0]];
    // +5: amaliy 10–13 — kunga sig'maydi (avval amaliy oraliqdan tushib qolib, faqat yozuvdan keyin aniqlanardi).
    $n = dmMove($s, $s['admin'], 'admin', $sub + ['delta' => 5, 'dry_run' => 1])->assertOk()->json('days.0');
    expect($n['status'])->toBe('nofit');
    $d = dmMove($s, $s['admin'], 'admin', $sub + ['delta' => 1])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [4, 6], 'prc' => [6, 10]]);
});

it('Codex: guruhcha qatorida "Birga" — smenali guruhcha (o\'z kun boshisiz): amaliy ma\'ruzadan keyinga o\'tadi, sig\'ishi shu bo\'yicha', function () {
    $s = dmSetup();
    // 04a: ma'ruza 1–2, amaliy smenada 7–10. Guruhcha qatorida +3: yozuv starts_g qo'yadi, smena o'chadi — ma'ruza 4–5, amaliy 6–9 (sig'adi).
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both'];
    $d = dmMove($s, $s['admin'], 'admin', $sub + ['day_indexes' => [0], 'delta' => 3])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [4, 6], 'prc' => [6, 10]])
        ->and(dmDays($s, 'd2/d23-04b')['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [7, 11]]);
    // +7: ma'ruza 8–9, amaliy 10–13 — sig'maydi.
    expect(dmMove($s, $s['admin'], 'admin', $sub + ['day_indexes' => [1], 'delta' => 7, 'dry_run' => 1])->json('days.0.status'))->toBe('nofit');
    // Shu kunga ps_g bor (amaliy 9–12) — u kun boshi bilan siljiydi (10–13): +1 ham sig'maydi.
    $s['placement']->update(['lecture_slots' => array_merge($s['placement']->fresh()->lecture_slots, ['ps_g' => ['d2/d23-04a' => [2 => 9]]])]);
    expect(dmDays($s)['2026-11-18']['prc'])->toBe([9, 13])
        ->and(dmMove($s, $s['admin'], 'admin', $sub + ['day_indexes' => [2], 'delta' => 1, 'dry_run' => 1])->json('days.0.status'))->toBe('nofit');
});

it('Codex: tasdiqlangan ko\'p kunli so\'rov qayta bajarilganda hech bir kun o\'zgarmasa — so\'rov bajarilmadi (FAILED), tasdiqlanmaydi', function () {
    $s = dmSetup();
    $head = dmHead();
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['approval_required' => true])]);
    dmMove($s, $head, 'kafedra_mudiri', ['day_indexes' => [1, 2], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->assertJsonPath('pending_approval', true);
    $req = \App\Models\TimetableChangeRequest::latest('id')->first();
    // Tasdiqqacha sanalar o'tib ketdi.
    $this->travelTo(Carbon::parse('2026-11-20 09:00', 'Asia/Tashkent'));
    dmAs($s['admin'], 'admin')->postJson(route('admin.timetable.change-requests.approve', $req->id));
    $req->refresh();
    expect($req->status)->toBe(\App\Models\TimetableChangeRequest::FAILED)
        ->and($req->error)->toContain('Hech bir kun o‘zgarmadi: 17.11 — O‘tgan kun.; 18.11 — O‘tgan kun.')
        ->and(dmDays($s)['2026-11-17']['lec'])->toBe([1, 3]);
    // To'g'ridan-to'g'ri (admin) — kun bo'yicha natija, 200.
    dmMove($s, $s['admin'], 'admin', ['day_indexes' => [1], 'delta' => 2, 'mode' => 'lecture'])->assertOk()->assertJsonPath('applied', 0);
});

it('Codex: guruhcha qatorida "Birga" oqim soatiga qaytsa — o\'z kun boshi o\'chadi, smena qayta yoqiladi; sig\'ishi yozuvdan keyingi holat bo\'yicha', function () {
    $s = dmSetup();
    // Oqim kuni 9-soatdan (ma'ruza 9–10); 04a — o'z kun boshi 1 (ma'ruza 1–2, amaliy ergashadi 3–6), smena 1.
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04a' => 1, 'd2/d23-04b' => 1], 'starts' => [0 => 9],
        'starts_g' => ['d2/d23-04a' => [0 => 1]]]]);
    expect(dmDays($s)['2026-11-16'])->toBe(['lec' => [1, 3], 'prc' => [3, 7]]);
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both', 'day_indexes' => [0]];
    // 9-soatga (oqim soati): starts_g o'chadi — ma'ruza 9–10, amaliy smenada 1–4 (avval [9,15) deb "sig'maydi" berilardi).
    $d = dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 9])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [9, 11], 'prc' => [1, 5]])
        ->and($s['placement']->fresh()->lecture_slots['starts_g'] ?? [])->toBe([]);
    // Smenasiz guruhcha — oldindan tekshiruv (oraliq siljiydi): sabab aniq.
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04b' => 1]]]);
    $n = dmMove($s, $s['admin'], 'admin', array_merge($sub, ['day_indexes' => [1], 'delta' => 7, 'dry_run' => 1]))->assertOk()->json('days.0');
    expect($n['status'])->toBe('nofit')->and($n['why'])->toBe('Kunga sig‘maydi: 6 soat 8-soatdan boshlab bo‘lmaydi (kunda 12 soat).');
});

it('Codex: guruhcha qatorida "Birga" — ma\'ruzasiz kunda kun rejasi qisib qo\'ysa (smena starts_g bilan o\'chgan), "qo\'llandi" emas — "sig\'maydi"', function () {
    $s = dmSetup();
    // Ma'ruza 4 soat (2 kun), amaliy 28: 3-kun (18.11) — faqat amaliy, 6 soat; 04a smenada 7–12.
    DB::table('manual_curriculum_subjects')->update(['lecture' => 4, 'practice' => 28]);
    expect(dmDays($s)['2026-11-18'])->toBe(['lec' => null, 'prc' => [7, 13]]);
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both', 'day_indexes' => [2]];
    $before = $s['placement']->fresh()->lecture_slots;
    // 9-soatga: starts_g = 9, smena o'chadi, kun rejasi 6 soatni 7-soatdan qo'yadi — so'ralgan soatga yetmaydi.
    $n = dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 9])->assertOk()->json('days.0');
    expect($n['status'])->toBe('nofit')->and($n['applied'])->toBeFalse()->and($n['why'])->toContain("Amaliy dars (6 soat) kun oxirigacha sig'maydi")
        ->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    // 5-soatga — sig'adi: amaliy 5–10.
    $d = dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 5])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and(dmDays($s)['2026-11-18'])->toBe(['lec' => null, 'prc' => [5, 11]]);
});

it('Codex: "faqat ma\'lumot" guruhcha ham kunga sig\'ishi tekshiriladi (kun rejasi qisib qo\'ysa — "sig\'maydi")', function () {
    $s = dmSetup();
    // 4-kurs — "faqat ma'lumot" (bandlikdan ozod). 04a: ma'ruza 1–2, amaliy smenada 7–10; guruhcha qatorida 9-soatga — kun rejasi 7 ga qisadi.
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['info_only_courses' => [4]])]);
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both', 'day_indexes' => [0]];
    $before = $s['placement']->fresh()->lecture_slots;
    $n = dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 9])->assertOk()->json('days.0');
    expect($n['status'])->toBe('nofit')->and($n['applied'])->toBeFalse()->and($s['placement']->fresh()->lecture_slots)->toBe($before);
    expect(dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 4])->json('days.0.status'))->toBe('ok')
        ->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [4, 6], 'prc' => [6, 10]]);
});

it('bugun (admin): smena qayta yoqilib amaliy o\'tib ketgan vaqtga tushsa ham — o\'zgaradi (xodimga bugun ochiq); kafedra mudiri — yo\'q', function () {
    $s = dmSetup();
    // Bugun 16.11, 11:00. Oqim kuni 9-soatdan; 04a — o'z kun boshi 5 (ma'ruza 5–6, amaliy 7–10), smena 1.
    $s['placement']->update(['lecture_slots' => ['shift_g' => ['d2/d23-04a' => 1, 'd2/d23-04b' => 1], 'starts' => [0 => 9],
        'starts_g' => ['d2/d23-04a' => [0 => 5]]]]);
    $this->travelTo(Carbon::parse('2026-11-16 11:00', 'Asia/Tashkent'));
    expect(dmDays($s)['2026-11-16'])->toBe(['lec' => [5, 7], 'prc' => [7, 11]]);
    $sub = ['group_name' => 'd2/d23-04a', 'placement_group' => 'F1 / 1-oqim', 'sub_group' => 'd2/d23-04a', 'mode' => 'both', 'day_indexes' => [0]];
    // 9-soatga (oqim soati): starts_g o'chadi, amaliy smenaga (1-soat, 08:30 — o'tgan) — admin uchun mumkin.
    $d = dmMove($s, $s['admin'], 'admin', $sub + ['start_hour' => 9])->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok')->and($d['today'])->toBeTrue()->and(dmDays($s)['2026-11-16'])->toBe(['lec' => [9, 11], 'prc' => [1, 5]]);
    expect(dmMove($s, dmHead(), 'kafedra_mudiri', $sub + ['start_hour' => 5, 'dry_run' => 1])->json('days.0.status'))->toBe('past');
});

it('"faqat ma\'lumot" guruhcha: ma\'ruza amaliy ustiga surilsa ham ustma-ust xato emas (avvalgidek), kunga sig\'ishi esa tekshiriladi', function () {
    $s = dmSetup();
    $s['board']->update(['settings' => array_merge($s['board']->settings, ['info_only_courses' => [4]])]);
    // Ma'ruza 1–2 → 7–8 (amaliy 7–10 ustiga), amaliyotga tegilmaydi (keep).
    $d = dmMove($s, $s['admin'], 'admin', ['day_indexes' => [0], 'start_hour' => 7, 'mode' => 'lecture', 'practice_place' => 'keep', 'dry_run' => 1])
        ->assertOk()->json('days.0');
    expect($d['status'])->toBe('ok');
});

it('bitta kun (day_index): o\'tgan kun — hamma uchun 422; bugun — admin qo\'llaydi (boshlangan bo\'lsa ham, tarixda belgi), kafedra mudiri — 422', function () {
    $s = dmSetup();
    $this->travelTo(Carbon::parse('2026-11-17 15:00', 'Asia/Tashkent'));
    dmMove($s, $s['admin'], 'admin', ['day_index' => 0, 'start_hour' => 3, 'mode' => 'both'])->assertUnprocessable()
        ->assertJsonPath('error', 'O‘tgan kun darsini ko‘chirib bo‘lmaydi.');
    dmMove($s, dmHead(), 'kafedra_mudiri', ['day_index' => 1, 'start_hour' => 3, 'mode' => 'both'])->assertUnprocessable()
        ->assertJsonPath('error', "Bugungi darsni faqat admin va registrator ofisi ko'chiradi.");
    dmMove($s, $s['admin'], 'admin', ['day_index' => 1, 'start_hour' => 3, 'mode' => 'both'])->assertOk()->assertJsonPath('today_moved', true);
    expect(dmDays($s)['2026-11-17']['lec'])->toBe([3, 5])
        ->and(\App\Models\TimetableChangeRequest::latest('id')->first()->summary)->toContain('bugungi dars ko‘chirildi');
});
