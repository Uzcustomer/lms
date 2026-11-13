<?php

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

// Amaliy dars bo'linmaydi (birlik — guruh, fan, tur, o'qituvchi): haftalik joyni yozadigan barcha yo'llar yakuniy holatda birlikning shu
// haftadagi bo'laklarini bitta kunda, ketma-ket qoldiradi. Production misoli (board 7, 11-hafta): 1-son davolash 1-kurs, d1/d26-03a,
// Tibbiy biologiya. Umumiy genetika (NUROVA Z. A.) — 708676 seshanba 08:30; 708674 / 708675 chorshanba 10:00–13:20 (bo'lingan).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-16 09:00', 'Asia/Tashkent')));

function uiTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'f', 'department' => 'Biologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
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
 * Semestr 14.09.2026 dan — 11-hafta: 23.11–28.11 (seshanba 24.11, chorshanba 25.11). Qo'ng'iroq jadvali — 12 yarim para (08:30 … 17:10).
 * d1/d26-03a: 708676 (a) seshanba 08:30 (1-qator), 708674 (b) chorshanba 10:00 (3), 708675 (c) chorshanba 12:00 (5) — hammasi 2 soatdan.
 */
function uiSetup(): array
{
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12,
        'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $nurova = uiTeacher('NUROVA Z. A.');
    $room = Auditorium::create(['code' => 'R123', 'name' => '123-amaliyot xonasi', 'volume' => 17, 'active' => true]);
    $base = ['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Tibbiy biologiya. Umumiy genetika', 'group_name' => 'd1/d26-03a', 'kafedra_name' => 'Biologiya kafedrasi',
        'teacher_id' => $nurova->id, 'teacher_name' => 'NUROVA Z. A.', 'auditorium_code' => 'R123', 'auditorium_name' => '123-amaliyot xonasi'];
    $a = TimetableCard::create($base + ['day' => 2, 'pair' => 1]);
    $b = TimetableCard::create($base + ['day' => 3, 'pair' => 3]);
    $c = TimetableCard::create($base + ['day' => 3, 'pair' => 5]);

    return compact('board', 'nurova', 'room', 'base', 'a', 'b', 'c') + ['week' => 11];
}

function uiAs()
{
    return test()->withoutVite()->actingAs(uiTeacher('ADMIN', 'admin'), 'teacher')->withSession(['active_role' => 'admin']);
}

/** Bo'lakning 11-haftadagi joyi [kun, para] (haftalik yozuv, bo'lmasa shablon). */
function uiAt(TimetableCard $c, int $week = 11): array
{
    $o = TimetableCardOverride::where('card_id', $c->id)->where('week', $week)->first();
    if ($o) {
        return $o->cancelled ? [null, null] : [(int) $o->day, (int) $o->pair];
    }
    $c->refresh();

    return [(int) $c->day, (int) $c->pair];
}

it('03a: week-override — 708676 ni chorshanba 08:30 ga (natija yaxlit 08:30–13:20) ruxsat; oraliq qoladigan joyga yoki boshqa kunga — 422', function () {
    $s = uiSetup();
    $mv = fn (TimetableCard $c, int $d, int $p, array $x = []) => uiAs()->postJson(route('admin.timetable.cards.week-override', $c),
        ['week' => $s['week'], 'action' => 'move', 'day' => $d, 'pair' => $p] + $x);
    // Payshanba — boshqa kun: bo'linadi (b, c chorshanbada).
    $r = $mv($s['a'], 4, 1)->assertUnprocessable();
    expect($r->json('error'))->toBe("Amaliy dars bo'linmaydi: 6 soat (2+2+2) bir kunda ketma-ket bo'lishi kerak («Tibbiy biologiya. Umumiy genetika», d1/d26-03a, 11-hafta).")
        ->and($r->json('unit_split'))->toBeTrue()
        ->and(uiAt($s['a']))->toBe([2, 1]);
    // Chorshanba 15:00 (9-qator) — bir kun, lekin oraliq (13:20–15:00): rad.
    $mv($s['a'], 3, 9)->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, "Amaliy dars bo'linmaydi: 6 soat (2+2+2)"));
    expect(TimetableCardOverride::count())->toBe(0);
    // Chorshanba 08:30 — 08:30–09:50, 10:00–11:20, 12:00–13:20: yaxlit — ruxsat.
    $mv($s['a'], 3, 1)->assertOk();
    expect(uiAt($s['a']))->toBe([3, 1]);
    // O'rtadagi bo'lak bekor qilinsa — oraliq qoladi: rad; oxirgisi — yaxlit qoladi: ruxsat.
    uiAs()->postJson(route('admin.timetable.cards.week-override', $s['b']), ['week' => $s['week'], 'action' => 'cancel'])->assertUnprocessable();
    uiAs()->postJson(route('admin.timetable.cards.week-override', $s['c']), ['week' => $s['week'], 'action' => 'cancel'])->assertOk();
});

it('03a: avvaldan bo\'lingan birlik — joyi o\'zgarmagan amal (faqat xona) to\'siq emas; boshqa haftalar tegilmaydi', function () {
    $s = uiSetup();
    Auditorium::create(['code' => 'R117', 'name' => '117-xona', 'volume' => 20, 'active' => true]);
    // Shu joyda, boshqa xona bilan — joylar o'zgarmadi.
    uiAs()->postJson(route('admin.timetable.cards.week-override', $s['b']), ['week' => $s['week'], 'action' => 'move', 'day' => 3, 'pair' => 3,
        'auditorium_code' => 'R117'])->assertOk();
    expect(TimetableCardOverride::where('card_id', $s['b']->id)->value('auditorium_code'))->toBe('R117');
    // Bo'lingan birlikni yana boshqacha bo'lish (a — seshanba 10:00 ga) — joy o'zgardi va bo'lingan: rad.
    uiAs()->postJson(route('admin.timetable.cards.week-override', $s['a']), ['week' => $s['week'], 'action' => 'move', 'day' => 2, 'pair' => 3])
        ->assertUnprocessable();
});

it('03a: o\'qituvchi jadvali (agenda) — 708676 chorshanba 08:30: "6 soat (2+2+2) birga ko\'chiriladi", ruxsat; qo\'llash — butun birlik', function () {
    $s = uiSetup();
    $admin = uiTeacher('ADMIN', 'admin');
    $get = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query(['teacher_id' => $s['nurova']->id, 'date' => '2026-11-25',
            'range' => 'week', 'move' => 'w|' . $s['a']->id . '|' . $s['week']]))->assertOk()->json('move');
    $mv = $get();
    expect($mv['via'])->toBe('apply')
        ->and($mv['unit_note'])->toBe("6 soat (2+2+2) birga ko'chiriladi")
        // I para (08:30): 708676 — 08:30, 708674 — 10:00, 708675 — 12:00 (yaxlit). Avval: "Yangi kunda shu fandan boshqa amaliy dars bor (12:00–13:20)".
        ->and($mv['options']['2026-11-25'][1])->toMatchArray(['ok' => true, 'why' => null])
        // IV para (13:30): 13:30–17:50 — sig'adi.
        ->and($mv['options']['2026-11-25'][4]['ok'])->toBeTrue()
        // V para (15:00): 6 soat kun oxiriga sig'maydi.
        ->and($mv['options']['2026-11-25'][5])->toMatchArray(['ok' => false, 'why' => "Tanlangan vaqt jadval chegarasidan tashqarida. 6 soat (2+2+2) birga ko'chiriladi"]);
    // Qo'llash — front kabi: barcha bo'laklar shu vaqtdan ketma-ket (Takliflar "move" amallari).
    test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['week' => $s['week'], 'card_id' => $s['a']->id, 'type' => 'agenda',
            'title' => "O'qituvchi jadvalidan ko'chirish", 'actions' => [
                ['op' => 'move', 'card_id' => $s['a']->id, 'week' => $s['week'], 'day' => 3, 'pair' => 1],
                ['op' => 'move', 'card_id' => $s['b']->id, 'week' => $s['week'], 'day' => 3, 'pair' => 3],
                ['op' => 'move', 'card_id' => $s['c']->id, 'week' => $s['week'], 'day' => 3, 'pair' => 5]]])->assertOk();
    expect([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[3, 1], [3, 3], [3, 5]]);
});

it('Takliflar: birlik shu kunda eski joyi bilan ustma-ust surilsa — o\'z bo\'laklari to\'siq emas; bitta bo\'lakni boshqa kunga — rad', function () {
    $s = uiSetup();
    $s['a']->update(['day' => 3, 'pair' => 1]);   // yaxlit: chorshanba 08:30–13:20
    $apply = fn (array $acts) => uiAs()->postJson(route('admin.timetable.week-suggestions.apply', $s['board']),
        ['week' => $s['week'], 'card_id' => $s['a']->id, 'actions' => $acts]);
    // 1, 3, 5 → 3, 5, 7: a yangi joyida b ning eski joyi bilan ustma-ust (oraliq qadam) — yakuniy holat toza.
    $apply([['op' => 'move', 'card_id' => $s['a']->id, 'week' => 11, 'day' => 3, 'pair' => 3],
        ['op' => 'move', 'card_id' => $s['b']->id, 'week' => 11, 'day' => 3, 'pair' => 5],
        ['op' => 'move', 'card_id' => $s['c']->id, 'week' => 11, 'day' => 3, 'pair' => 7]])->assertOk();
    expect([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[3, 3], [3, 5], [3, 7]]);
    // Faqat c — juma: bo'linadi.
    $apply([['op' => 'move', 'card_id' => $s['c']->id, 'week' => 11, 'day' => 5, 'pair' => 1]])->assertUnprocessable()
        ->assertJsonPath('error', fn ($m) => str_contains($m, "Amaliy dars bo'linmaydi: 6 soat (2+2+2)"));
    expect(uiAt($s['c']))->toBe([3, 7]);
});

it('bo\'lingan birliklar: partial-units?split=1 va artisan --split — hafta, guruh, fan, kunlar', function () {
    $s = uiSetup();
    $j = uiAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=11&split=1')->assertOk()->json();
    expect($j['count'])->toBe(1)
        ->and($j['items'][0])->toMatchArray(['kind' => 'split', 'week' => 11, 'group' => 'd1/d26-03a', 'subject' => 'Tibbiy biologiya. Umumiy genetika',
            'days' => [2, 3], 'days_text' => 'Seshanba 08:30–09:50; Chorshanba 10:00–13:20', 'placed_hours' => 6])
        ->and($j['items'][0]['card_ids'])->toBe([$s['a']->id, $s['b']->id, $s['c']->id]);
    // split=1 siz — faqat qisman joylanganlar (eski xatti-harakat).
    expect(uiAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=11')->assertOk()->json('count'))->toBe(0);
    // Bir kunda oraliq bilan — ham bo'lingan.
    $s['a']->update(['day' => 3, 'pair' => 9]);
    expect(uiAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=11&split=1')->json('items.0.days_text'))
        ->toBe('Chorshanba 10:00–13:20, 15:00–16:20');
    $s['a']->update(['day' => 2, 'pair' => 1]);
    $this->artisan('timetable:partial-units', ['board' => $s['board']->id, '--week' => [11], '--split' => true])
        ->expectsOutputToContain('Seshanba 08:30–09:50; Chorshanba 10:00–13:20')
        ->expectsOutputToContain('Jami: 1')
        ->assertSuccessful();
});

it('"Yaxlitlash" (unit-fix merge): eng ko\'p soatli kun, birinchi bo\'lak boshidan; band bo\'lsa — oxiriga tekislab; sig\'masa — o\'zgarmaydi, sababi', function () {
    $s = uiSetup();
    $merge = fn () => uiAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge', 'card_ids' => [$s['a']->id]]);
    // Ommaviy yaxlitlash yo'q — card_ids majburiy.
    uiAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge'])->assertUnprocessable();
    // Chorshanba 13:30 (7-qator) va 08:30 (1-qator) guruh band (boshqa fan) — ikkala variant ham sig'maydi: hech narsa o'zgarmaydi.
    $t2 = uiTeacher('BOSHQA B.');
    $blk = fn (int $p) => TimetableCard::create(array_merge($s['base'], ['subject_name' => 'Anatomiya', 'teacher_id' => $t2->id, 'teacher_name' => 'BOSHQA B.',
        'auditorium_code' => null, 'auditorium_name' => null, 'day' => 3, 'pair' => $p]));
    $b7 = $blk(7);
    $b1 = $blk(1);
    // Sig'madi — 422 (hech narsa o'zgarmadi), sababi tuzilgan bloklar bilan.
    $r = $merge()->assertUnprocessable()->json();
    expect($r['done'])->toBe(0)->and($r['failed'])->toHaveCount(1)->and($r['error'])->toStartWith("Yaxlitlab bo'lmadi: ")
        ->and($r['failed'][0]['error'])->toContain("6 soat sig'maydi")->toContain('Guruh band')
        ->and([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[2, 1], [3, 3], [3, 5]])
        ->and(TimetableCardOverride::count())->toBe(0);
    // 08:30 bo'shadi — oxiriga tekislab: 708676 — 08:30, qolganlari joyida (chorshanba 08:30–13:20).
    $b1->delete();
    expect($merge()->assertOk()->json())->toMatchArray(['done' => 1, 'message' => 'Yaxlitlandi: d1/d26-03a — Chorshanba 08:30–13:20'])
        ->and([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[3, 1], [3, 3], [3, 5]]);
    // Birinchi bo'lak boshidan (13:30 bo'sh): chorshanbadagi bo'laklar joyida, seshanbadagisi ketidan — 13:30.
    TimetableCardOverride::query()->delete();
    $b7->delete();
    expect($merge()->assertOk()->json('done'))->toBe(1)
        ->and([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[3, 7], [3, 3], [3, 5]]);
});

it('Yaxlitlash: soat teng bo\'lsa — haftaning oldingi kuni; yaxlit birlik ro\'yxatda yo\'q va merge unga tegmaydi', function () {
    $s = uiSetup();
    $s['c']->delete();   // 2 + 2: seshanba 08:30 va chorshanba 10:00 — teng, seshanba tanlanadi
    expect(uiAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge', 'card_ids' => [$s['a']->id]])
        ->assertOk()->json('done'))->toBe(1)
        ->and([uiAt($s['a']), uiAt($s['b'])])->toBe([[2, 1], [2, 3]]);
    expect(uiAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge', 'card_ids' => [$s['a']->id]])
        ->assertOk()->json())->toMatchArray(['done' => 0, 'failed' => []]);
});

it('unit-place auditorium_code ("Boshqa xona bilan joylash"): butun birlik shu haftada shu xona bilan; ruxsatsiz xona — 422', function () {
    $s = uiSetup();
    Auditorium::create(['code' => 'R117', 'name' => '117-xona', 'volume' => 20, 'active' => true]);
    $j = uiAs()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['week' => 11, 'layer' => 'internal', 'day' => 4, 'pair' => 1,
        'auditorium_code' => 'R117'])->assertOk()->json('unit');
    expect(array_map(fn ($u) => [$u['day'], $u['pair']], $j))->toBe([[4, 1], [4, 3], [4, 5]])
        ->and(TimetableCardOverride::where('week', 11)->orderBy('card_id')->pluck('auditorium_code')->all())->toBe(['R117', 'R117', 'R117']);
    uiAs()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['week' => 11, 'layer' => 'internal', 'day' => 4, 'pair' => 1,
        'auditorium_code' => 'YO_Q'])->assertUnprocessable();
    // Shablonda (hafta yo'q) xona berilmaydi.
    uiAs()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['layer' => 'internal', 'day' => 4, 'pair' => 1, 'auditorium_code' => 'R117'])
        ->assertUnprocessable();
});

it('Codex: Takliflar "o\'qituvchi + ko\'chirish" — bo\'lak yangi o\'qituvchi birligida tekshiriladi (eski birlik bo\'lingan deb rad etilmaydi)', function () {
    $s = uiSetup();
    $s['a']->update(['day' => 3, 'pair' => 1]);   // yaxlit: chorshanba 08:30–13:20 (a, b, c — NUROVA)
    $t2 = uiTeacher('SHARIPOVA F. S.');
    $apply = fn (array $acts) => uiAs()->postJson(route('admin.timetable.week-suggestions.apply', $s['board']),
        ['week' => 11, 'card_id' => $s['c']->id, 'actions' => $acts]);
    // c — SHARIPOVA ga va juma 08:30 ga: NUROVA birligi (a, b) joyida, SHARIPOVA birligi — faqat c: rad etilmaydi.
    $apply([['op' => 'teacher', 'card_id' => $s['c']->id, 'teacher_id' => $t2->id],
        ['op' => 'move', 'card_id' => $s['c']->id, 'week' => 11, 'day' => 5, 'pair' => 1]])->assertOk();
    expect(uiAt($s['c']))->toBe([5, 1])->and($s['c']->fresh()->teacher_id)->toBe($t2->id);
    // SHARIPOVA ning shu guruh, shu fandagi boshqa bo'lagi payshanbada — yangi birlik bo'linadi: rad.
    $y = TimetableCard::create(array_merge($s['base'], ['teacher_id' => $t2->id, 'teacher_name' => 'SHARIPOVA F. S.', 'day' => 4, 'pair' => 1]));
    $apply([['op' => 'move', 'card_id' => $s['c']->id, 'week' => 11, 'day' => 5, 'pair' => 3]])->assertUnprocessable()
        ->assertJsonPath('error', fn ($m) => str_contains($m, "Amaliy dars bo'linmaydi: 4 soat (2+2)"));
    expect($y->id)->toBeGreaterThan(0);
});

it('Codex: agenda — bo\'laklar turli xonada: bir xonaning bandligi boshqa bo\'lak vaqtiga yozilmaydi', function () {
    $s = uiSetup();
    $s['c']->delete();
    Auditorium::create(['code' => 'R117', 'name' => '117-xona', 'volume' => 20, 'active' => true]);
    // a — 123-amaliyot xonasida, b — 117-xonada (seshanba 08:30, 10:00 — yaxlit).
    $s['b']->update(['day' => 2, 'pair' => 3, 'auditorium_code' => 'R117', 'auditorium_name' => '117-xona']);
    // Payshanba 10:00 da 123-amaliyot xonasi boshqa guruhda band — bu b ning vaqti (b esa 117-xonada): a (08:30, 123) uchun to'siq emas.
    $t2 = uiTeacher('BOSHQA B.');
    TimetableCard::create(array_merge($s['base'], ['group_name' => 'd1/d26-09a', 'teacher_id' => $t2->id, 'teacher_name' => 'BOSHQA B.', 'day' => 4, 'pair' => 3]));
    $mv = test()->withoutVite()->actingAs(uiTeacher('ADMIN2', 'admin'), 'teacher')->withSession(['active_role' => 'admin'])
        ->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query(['teacher_id' => $s['nurova']->id, 'date' => '2026-11-26',
            'range' => 'week', 'move' => 'w|' . $s['a']->id . '|11']))->assertOk()->json('move');
    expect($mv['unit_note'])->toBe("4 soat (2+2) birga ko'chiriladi")
        ->and($mv['options']['2026-11-26'][1])->toMatchArray(['ok' => true, 'why' => null]);
});


it('Yaxlitlash — kafedra mudiri (o\'z kafedrasi) tasdiq rejimida ham darhol qo\'llanadi va tarixga yoziladi; sig\'masa 422; boshqa kafedra — rad', function () {
    $s = uiSetup();
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14', 'approval_required' => true]]);
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 601, 'name' => 'Biologiya kafedrasi', 'code' => 'B1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'Mahalliy', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $head = uiTeacher('MUDIR', 'kafedra_mudiri');
    $head->forceFill(['department_hemis_id' => 601, 'department' => 'Biologiya kafedrasi'])->save();
    $as = fn () => test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri']);
    $merge = fn () => $as()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge', 'card_ids' => [$s['a']->id]]);
    // Chorshanba 08:30 band (boshqa fan, shu guruh) va 13:30 band — sig'maydi: 422, so'rov yaratilmaydi.
    $t2 = uiTeacher('BOSHQA B.');
    $blk = fn (int $p) => TimetableCard::create(array_merge($s['base'], ['subject_name' => 'Anatomiya', 'teacher_id' => $t2->id, 'teacher_name' => 'BOSHQA B.',
        'auditorium_code' => null, 'auditorium_name' => null, 'day' => 3, 'pair' => $p]));
    $b1 = $blk(1);
    $blk(7);
    expect($merge()->assertUnprocessable()->json('failed.0.conflict_items'))->not->toBeEmpty()
        ->and(\App\Models\TimetableChangeRequest::count())->toBe(0)->and(TimetableCardOverride::count())->toBe(0);
    // Bo'shadi — darhol (pending_approval emas), natija matni va tarix ("Kiritildi").
    $b1->delete();
    $r = $merge()->assertOk()->json();
    expect($r)->not->toHaveKey('pending_approval')
        ->and($r['message'])->toBe('Yaxlitlandi: d1/d26-03a — Chorshanba 08:30–13:20')
        ->and([uiAt($s['a']), uiAt($s['b']), uiAt($s['c'])])->toBe([[3, 1], [3, 3], [3, 5]])
        ->and(\App\Models\TimetableChangeRequest::where('status', 'applied')->count())->toBe(1)
        ->and(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(0);
    // Boshqa amal (oddiy joylash) — hali ham tasdiqqa boradi.
    $as()->postJson(route('admin.timetable.cards.place', $s['a']), ['day' => 5, 'pair' => 1])->assertOk()->assertJson(['pending_approval' => true]);
    TimetableCardOverride::query()->delete();
    // Boshqa kafedra fani — rad (422, sababi bilan), hech narsa o'zgarmaydi.
    TimetableCard::whereIn('id', [$s['a']->id, $s['b']->id, $s['c']->id])->update(['kafedra_name' => 'Anatomiya kafedrasi', 'day' => 2]);
    TimetableCard::whereKey($s['b']->id)->update(['day' => 3]);
    expect($merge()->assertUnprocessable()->json('failed.0.error'))->toBe('Bu fan sizning kafedrangizga tegishli emas.');
});
