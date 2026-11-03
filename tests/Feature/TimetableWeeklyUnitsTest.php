<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

beforeEach(fn () => $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent')));

/** Yarim para qatorli qo'ng'iroq jadvali: 08:30, 09:10, 10:00, 10:40, 12:00, 12:40, 13:30, 14:10, 15:00, 15:40, 16:30, 17:10. */
function wuBell(): array
{
    $pairs = [['08:30', '09:50'], ['10:00', '11:20'], ['12:00', '13:20'], ['13:30', '14:50'], ['15:00', '16:20'], ['16:30', '17:50']];
    $roman = ['I', 'II', 'III', 'IV', 'V', 'VI'];
    $out = [];
    foreach ($pairs as $i => [$s, $e]) {
        $mid = date('H:i', strtotime($s) + 40 * 60);
        $out[] = ['type' => 'pair', 'name' => $roman[$i] . '-para (1)', 'abbr' => $roman[$i], 'start' => $s, 'end' => $mid, 'print' => true];
        $out[] = ['type' => 'pair', 'name' => $roman[$i] . '-para (2)', 'abbr' => $roman[$i], 'start' => $mid, 'end' => $e, 'print' => true];
    }

    return $out;
}

function wuTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Fizika kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
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
 * 2-son davolash, 1-kurs: d2/d26-01b (rus), Tibbiy va biologik fizika (amaliy), CHORIYEVA G. Y. — 3 soat (2 + 1 bo'lak),
 * 8-haftada juma 12:00 dan (5–7-qator).
 */
function wuSetup(): array
{
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12,
        'weeks' => 15, 'bell_schedule' => wuBell(), 'settings' => ['semester_start' => '2026-09-07']]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => '2-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $chor = wuTeacher('CHORIYEVA G. Y.');
    $base = ['board_id' => $board->id, 'faculty_name' => '2-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1, 'oqim_label' => '1-oqim',
        'lang' => 'ru', 'students' => 12, 'training_type' => 'practice', 'weeks' => 15, 'subject_name' => 'Tibbiy va biologik fizika',
        'group_name' => 'd2/d26-01b (rus)', 'kafedra_name' => 'Fizika kafedrasi', 'teacher_id' => $chor->id, 'teacher_name' => 'CHORIYEVA G. Y.'];
    $a = TimetableCard::create($base + ['len_half' => 2]);
    $b = TimetableCard::create($base + ['len_half' => 1]);
    // 8-hafta, juma (5): 12:00 (5-qator) — 2 soat, 13:30 gacha davomi (7-qator) — 1 soat.
    TimetableCardOverride::create(['card_id' => $a->id, 'week' => 8, 'day' => 5, 'pair' => 5]);
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 8, 'day' => 5, 'pair' => 7]);

    return compact('board', 'chor', 'a', 'b', 'base');
}

function wuAs()
{
    return test()->withoutVite()->actingAs(wuTeacher('ADMIN', 'admin'), 'teacher')->withSession(['active_role' => 'admin']);
}

function wuPlace(TimetableCard $c): array
{
    $o = TimetableCardOverride::where('card_id', $c->id)->where('week', 8)->first();

    return $o && !$o->cancelled ? [(int) $o->day, (int) $o->pair] : [null, null];
}

it('birlik: 3 soatlik darsni 12:40 ga surish — o\'z bo\'lagi bilan "to\'qnashuv" yo\'q, bo\'laklar ketma-ket', function () {
    $s = wuSetup();
    // Avval: bitta bo'lakni surish (eski yo'l) — o'z bo'lagi "Guruh band" / "O'qituvchi band" bo'lib chiqardi.
    $old = wuAs()->postJson(route('admin.timetable.cards.week-override', $s['a']), ['week' => 8, 'action' => 'move', 'day' => 5, 'pair' => 6])->assertStatus(422)->json('error');
    expect($old)->toContain('Guruh band')->toContain("O'qituvchi band");

    $j = wuAs()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['week' => 8, 'day' => 5, 'pair' => 6, 'layer' => 'internal'])->assertOk()->json();
    expect(wuPlace($s['a']))->toBe([5, 6])->and(wuPlace($s['b']))->toBe([5, 8])
        ->and(collect($j['unit'])->pluck('pair', 'id')->all())->toBe([$s['a']->id => 6, $s['b']->id => 8]);
    // Qaysi bo'lak sudralsa ham — birlik birga: 1 soatlik bo'lakdan 12:00 ga qaytarish.
    wuAs()->postJson(route('admin.timetable.cards.unit-place', $s['b']), ['week' => 8, 'day' => 5, 'pair' => 5])->assertOk();
    expect(wuPlace($s['a']))->toBe([5, 5])->and(wuPlace($s['b']))->toBe([5, 7]);
});

it('birlik: haqiqiy to\'qnashuv (boshqa guruh yoki o\'qituvchi) — xato avvalgidek, hech narsa o\'zgarmaydi', function () {
    $s = wuSetup();
    // CHORIYEVA juma 14:10 (8-qator) da boshqa guruhda.
    $o = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-02a (rus)', 'len_half' => 2]));
    TimetableCardOverride::create(['card_id' => $o->id, 'week' => 8, 'day' => 5, 'pair' => 8]);
    $err = wuAs()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['week' => 8, 'day' => 5, 'pair' => 6])->assertStatus(422)->json('error');
    expect($err)->toContain("3 soat sig'maydi: 12:40 dan keyin 2 soat bo'sh")->toContain("O'qituvchi band")->toContain('d2/d26-02a')
        ->and(wuPlace($s['a']))->toBe([5, 5])->and(wuPlace($s['b']))->toBe([5, 7]);
});

it('birlik: 4 soatlik dars 2 soatlik bo\'sh joyga — hech narsa joylanmaydi, sababi chiqadi', function () {
    $s = wuSetup();
    $c = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-03a (rus)', 'len_half' => 2]));
    $d = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-03a (rus)', 'len_half' => 2]));
    // 03a guruhi seshanba 15:00 dan (9-qator) boshqa fanda — 13:30 dan faqat 2 soat bo'sh.
    $x = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-03a (rus)', 'subject_name' => 'Anatomiya', 'teacher_id' => null,
        'teacher_name' => null, 'len_half' => 2]));
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 8, 'day' => 2, 'pair' => 9]);
    $err = wuAs()->postJson(route('admin.timetable.cards.unit-place', $c), ['week' => 8, 'day' => 2, 'pair' => 7])->assertStatus(422)->json('error');
    expect($err)->toStartWith("4 soat sig'maydi: 13:30 dan keyin 2 soat bo'sh")->toContain('Guruh band')
        ->and(wuPlace($c))->toBe([null, null])->and(wuPlace($d))->toBe([null, null]);
    // Kun oxiri: 17:10 dan 4 soat sig'maydi.
    $err = wuAs()->postJson(route('admin.timetable.cards.unit-place', $c), ['week' => 8, 'day' => 3, 'pair' => 12])->assertStatus(422)->json('error');
    expect($err)->toBe("4 soat sig'maydi: 17:10 dan keyin 1 soat bo'sh");
    // Bo'sh joyga — ikkala bo'lak ketma-ket.
    wuAs()->postJson(route('admin.timetable.cards.unit-place', $d), ['week' => 8, 'day' => 3, 'pair' => 1])->assertOk();
    expect([wuPlace($c), wuPlace($d)])->toBe([[3, 1], [3, 3]]);
    // Olib tashlash — hammasi.
    wuAs()->postJson(route('admin.timetable.cards.unit-place', $c), ['week' => 8])->assertOk();
    expect([wuPlace($c), wuPlace($d)])->toBe([[null, null], [null, null]]);
});

it('qisman joylangan birliklar: ro\'yxat (endpoint va artisan), yaxlit joylash va yaxlit bo\'shatish', function () {
    $s = wuSetup();
    // 1 soatlik bo'lak joydan olingan — qisman: 2 soat joylangan, 1 soat qolgan.
    TimetableCardOverride::where('card_id', $s['b']->id)->where('week', 8)->delete();
    $j = wuAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=8')->assertOk()->json();
    expect($j['count'])->toBe(1)->and($j['items'][0])->toMatchArray(['group' => 'd2/d26-01b (rus)', 'subject' => 'Tibbiy va biologik fizika',
        'week' => 8, 'day' => 5, 'day_name' => 'Juma', 'placed_hours' => 2, 'remaining_hours' => 1]);
    $this->artisan('timetable:partial-units', ['board' => $s['board']->id, '--week' => 8])
        ->expectsOutputToContain('d2/d26-01b (rus)')->expectsOutputToContain('Jami: 1')->assertExitCode(0);

    // Yaxlit joylash: qolgan bo'lak joylangan qism ketidan.
    wuAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'complete'])->assertOk()->assertJson(['done' => 1]);
    expect([wuPlace($s['a']), wuPlace($s['b'])])->toBe([[5, 5], [5, 7]]);
    expect(wuAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=8')->json('count'))->toBe(0);

    // Yaxlit bo'shatish.
    TimetableCardOverride::where('card_id', $s['b']->id)->where('week', 8)->delete();
    wuAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'clear'])->assertOk()->assertJson(['done' => 1]);
    expect([wuPlace($s['a']), wuPlace($s['b'])])->toBe([[null, null], [null, null]]);
});

it('takliflar (qabul): ALIYEV SH. N., d2/d26-07b va 08a, chorshanba, 428-xona — birinchi taklif chorshanba ichida; kun o\'zgarsa "Darsni surish" emas', function () {
    $s = wuSetup();
    \App\Models\Auditorium::create(['code' => 'R428', 'name' => '428', 'volume' => 40, 'active' => true]);
    $ali = wuTeacher('ALIYEV SH. N.');
    $mk = fn (string $g) => TimetableCard::create(array_replace($s['base'], ['group_name' => $g, 'subject_name' => 'Odam anatomiyasi', 'len_half' => 2,
        'teacher_id' => $ali->id, 'teacher_name' => 'ALIYEV SH. N.', 'auditorium_code' => 'R428', 'auditorium_name' => '428']));
    [$a, $b] = [$mk('d2/d26-07b (rus)'), $mk('d2/d26-08a (rus)')];
    // Chorshanba (3), 6-para (11–12-qator): ikkalasi bir vaqtda — o'qituvchi va xona to'qnashuvi.
    foreach ([$a, $b] as $c) {
        TimetableCardOverride::create(['card_id' => $c->id, 'week' => 8, 'day' => 3, 'pair' => 11]);
    }
    $res = wuAs()->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=8&courses[]=1')->assertOk()->json();
    $card = collect($res['cards'])->firstWhere('id', $a->id);
    $sugs = collect($card['suggestions']);
    $days = fn (array $sg) => collect($sg['actions'])->where('op', 'move')->pluck('day')->unique()->values()->all();
    expect($card['conflict'])->toBeTrue()
        // Birinchi — ikkalasini chorshanba kuni ketma-ket (bir-birini to'sgan ikki dars), kun o'zgarmaydi.
        ->and($sugs[0]['type'])->toBe('pair')->and($days($sugs[0]))->toBe([3])
        ->and($sugs[0]['title'])->toContain('Ikkalasini shu kunda ketma-ket')
        // "Shu kun, boshqa vaqt" — keyin; "Darsni surish" (blocker) — faqat kun o'zgarmaydigan siljish.
        ->and($sugs->where('type', 'slot')->every(fn ($sg) => $days($sg) === [3]))->toBeTrue()
        ->and($sugs->where('type', 'blocker')->every(fn ($sg) => $days($sg) === [3]))->toBeTrue()
        ->and($sugs->filter(fn ($sg) => $days($sg) !== [3] && $days($sg) !== [])->every(fn ($sg) => $sg['type'] !== 'blocker'))->toBeTrue();
    $types = $sugs->pluck('type')->values()->all();
    expect(array_search('slot', $types))->toBeLessThan(array_search('day', $types));
    if (in_array('blocker_day', $types, true)) {
        expect(end($types))->toBe('blocker_day');
    }
});

it('takliflar (qabul): bo\'shliqsiz joylash — birinchi taklif guruhning mavjud darsiga yopishgan vaqt; bo\'shliqli taklif pastda, belgisi bilan', function () {
    $s = wuSetup();
    // 01b guruhi seshanba 08:30–09:50 (1–2) da Anatomiyada; joylanmagan 2 soatlik Biologiya (CHORIYEVA) — tashqi joyi seshanba 13:30 (7),
    // lekin CHORIYEVA 13:30 da boshqa guruhda — shu kunning boshqa vaqti takliflari.
    $an = TimetableCard::create(array_replace($s['base'], ['subject_name' => 'Anatomiya', 'teacher_id' => null, 'teacher_name' => null, 'len_half' => 2]));
    TimetableCardOverride::create(['card_id' => $an->id, 'week' => 8, 'day' => 2, 'pair' => 1]);
    $bio = TimetableCard::create(array_replace($s['base'], ['subject_name' => 'Biologiya', 'len_half' => 2, 'ext_day' => 2, 'ext_pair' => 7]));
    $x = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-05a (rus)', 'subject_name' => 'Kimyo', 'len_half' => 2]));
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 8, 'day' => 2, 'pair' => 7]);
    $res = wuAs()->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=8&courses[]=1')->assertOk()->json();
    $slots = collect(collect($res['cards'])->firstWhere('id', $bio->id)['suggestions'])->where('type', 'slot')->values();
    // Birinchi — Anatomiyaga yopishgan 10:00 (3–4), bo'shliqsiz; keyingilari (13:30 atrofida) — bo'shliq qo'shadi, kamroq qo'shadigani oldin.
    expect($slots->count())->toBeGreaterThan(1)
        ->and($slots[0])->toMatchArray(['gap' => 0, 'touch' => true])->and($slots[0]['actions'][0])->toMatchArray(['day' => 2, 'pair' => 3])
        ->and($slots->slice(1)->every(fn ($sg) => $sg['gap'] > 0))->toBeTrue()
        ->and($slots->slice(1)->pluck('gap')->values()->all())->toBe($slots->slice(1)->pluck('gap')->sort()->values()->all());
});

it('panel oynasi: xona bandligi (teacher-agenda?room_code) — xonaning shu hafta darslari va bo\'sh paralari', function () {
    $s = wuSetup();
    \App\Models\Auditorium::create(['code' => 'R12', 'name' => "Tibbiyot klasteri, 1-qavat, 12-o'quv xona", 'volume' => 10, 'active' => true]);
    // 1-bo'lak shu haftada R12 da (haftalik xona), boshqa guruh darsi — kartaning o'z xonasi R12.
    TimetableCardOverride::where('card_id', $s['a']->id)->where('week', 8)->update(['auditorium_code' => 'R12', 'auditorium_name' => "12-o'quv xona"]);
    $o = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-02a (rus)', 'len_half' => 2, 'teacher_id' => null, 'teacher_name' => null,
        'auditorium_code' => 'R12', 'auditorium_name' => "12-o'quv xona"]));
    TimetableCardOverride::create(['card_id' => $o->id, 'week' => 8, 'day' => 2, 'pair' => 3]);
    $j = wuAs()->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?room_code=R12&date=2026-10-26&range=week&layer=internal')->assertOk()->json();
    expect($j['room'])->toMatchArray(['code' => 'R12', 'volume' => 10])->and($j['teacher'])->toBeNull()
        ->and($j['title'])->toStartWith("Tibbiyot klasteri, 1-qavat, 12-o'quv xona (10 o'rin) — ")
        ->and(collect($j['entries'])->map(fn ($e) => [$e['date'], $e['start'], $e['groups']])->all())->toBe([
            ['2026-10-27', '10:00', ['d2/d26-02a (rus)']],
            ['2026-10-30', '12:00', ['d2/d26-01b (rus)']],
        ])
        // 1 soatlik bo'lak (13:30) boshqa xonada — kirmaydi; juma II para bo'sh.
        ->and($j['free']['2026-10-30'])->toContain(2)->not->toContain(3)
        ->and($j['move'])->toBeNull();
});

it('panel oynasi: joylanmagan karta o\'qituvchi jadvalida "ko\'chiriladigan dars" — bo\'sh paralarga joylash variantlari', function () {
    $s = wuSetup();
    $c = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-03a (rus)', 'len_half' => 2]));
    $j = wuAs()->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query(['teacher_id' => $s['chor']->id,
        'date' => '2026-10-26', 'range' => 'week', 'layer' => 'internal', 'move' => 'w|' . $c->id . '|8']))->assertOk()->json();
    $mv = $j['move'];
    expect($mv)->toMatchArray(['kind' => 'weekly', 'via' => 'apply', 'why' => null, 'card_id' => $c->id, 'week' => 8])
        ->and($mv['label'])->toContain('joylanmagan')
        // Juma III para (12:00) — CHORIYEVA band (o'z darsi): variant yo'q; dushanba I para — bo'sh.
        ->and($mv['options']['2026-10-26'][1]['ok'])->toBeTrue()
        ->and(isset($mv['options']['2026-10-30'][3]))->toBeFalse();
});

it('qisman joylangan: bitta kartaning "yaxlit joylash"i (card_ids) boshqa birlikka tegmaydi; sig\'maganida sabab va card_ids', function () {
    $s = wuSetup();
    TimetableCardOverride::where('card_id', $s['b']->id)->where('week', 8)->delete();   // 01b: 2 soat joylangan, 1 soat qolgan
    $c = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-04a (rus)', 'len_half' => 2]));
    $d = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-04a (rus)', 'len_half' => 1]));
    TimetableCardOverride::create(['card_id' => $c->id, 'week' => 8, 'day' => 1, 'pair' => 1]);   // 04a: 2 soat joylangan, 1 soat qolgan
    wuAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'complete', 'card_ids' => [$s['a']->id, $s['b']->id]])
        ->assertOk()->assertJson(['done' => 1, 'failed' => []]);
    expect(wuPlace($s['b']))->toBe([5, 7])->and(wuPlace($d))->toBe([null, null]);
    $left = wuAs()->getJson(route('admin.timetable.boards.partial-units', $s['board']) . '?week=8')->json('items');
    expect(array_column($left, 'group'))->toBe(['d2/d26-04a (rus)']);
    // 04a ning ketida joy yo'q (CHORIYEVA dushanba 09:50 dan band) — sabab va kartalari.
    $x = TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-09a (rus)', 'len_half' => 2]));
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 8, 'day' => 1, 'pair' => 3]);
    $r = wuAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'complete', 'card_ids' => [$c->id, $d->id]])
        ->assertOk()->json();
    expect($r['done'])->toBe(0)->and($r['failed'][0]['card_ids'])->toBe([$c->id, $d->id])
        ->and($r['failed'][0]['error'])->toContain("3 soat sig'maydi")
        ->and(wuPlace($c))->toBe([1, 1]);
});

it('Codex: kafedra mudiri unit-fix / unit-place bilan faqat o\'z kafedrasi darslarini o\'zgartiradi', function () {
    $s = wuSetup();
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Gigiyena kafedrasi', 'code' => 'G1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $head = wuTeacher('MUDIR', 'kafedra_mudiri');
    $head->forceFill(['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi'])->save();
    // Fizika (boshqa kafedra) — qisman; Gigiyena (o'z kafedrasi) — qisman.
    TimetableCardOverride::where('card_id', $s['b']->id)->where('week', 8)->delete();
    $g = array_replace($s['base'], ['group_name' => 'd2/d26-05a (rus)', 'subject_name' => 'Gigiyena', 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => null, 'teacher_name' => null]);
    $g1 = TimetableCard::create($g + ['len_half' => 2]);
    $g2 = TimetableCard::create(array_replace($g, ['len_half' => 1]));
    TimetableCardOverride::create(['card_id' => $g1->id, 'week' => 8, 'day' => 1, 'pair' => 1]);
    $as = fn () => test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri']);
    $as()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'complete'])->assertOk()->assertJson(['done' => 1]);
    expect(wuPlace($g2))->toBe([1, 3])->and(wuPlace($s['b']))->toBe([null, null]);
    // Aniq so'ralgan boshqa kafedra darsi — o'zgarmaydi, sababi bilan.
    $r = $as()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 8, 'action' => 'clear', 'card_ids' => [$s['a']->id, $s['b']->id]])
        ->assertOk()->json();
    expect($r['done'])->toBe(0)->and($r['failed'][0]['error'])->toBe('Bu fan sizning kafedrangizga tegishli emas.')
        ->and(wuPlace($s['a']))->toBe([5, 5]);
    $as()->postJson(route('admin.timetable.cards.unit-place', $s['a']), ['week' => 8, 'day' => 5, 'pair' => 6])->assertForbidden();
    expect(wuPlace($s['a']))->toBe([5, 5]);
});

/** "Vaqtincha kafedra mudiriga yozish": Fizika kafedrasi (601), mudir XOLMATOV (HEMIS 9100), «Kafedra mudiri xonasi» (KM1). */
function phSetup(): array
{
    $s = wuSetup();
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 601, 'name' => 'Fizika kafedrasi', 'code' => 'F1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $mudir = wuTeacher('XOLMATOV A. B.', 'kafedra_mudiri');
    $mudir->forceFill(['department_hemis_id' => 601, 'hemis_id' => 9100, 'is_active' => true, 'full_name' => 'XOLMATOV AZIZ BAXTIYOROVICH', 'short_name' => 'XOLMATOV A. B.'])->save();
    \App\Models\Auditorium::create(['code' => 'KM1', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true]);
    \App\Models\Auditorium::create(['code' => 'R428', 'name' => '428', 'volume' => 40, 'active' => true]);
    // To'qnashuvli karta: 07b, CHORIYEVA, 428, chorshanba (3) 10:00 (3-qator); tashqi joyi — chorshanba 10:00.
    $mk = fn (string $g) => TimetableCard::create(array_replace($s['base'], ['group_name' => $g, 'subject_name' => 'Tibbiyotda axborot texnologiyalari',
        'len_half' => 2, 'auditorium_code' => 'R428', 'auditorium_name' => '428', 'ext_day' => 3, 'ext_pair' => 3]));
    [$a, $b] = [$mk('d2/d26-07b (rus)'), $mk('d2/d26-08a (rus)')];
    foreach ([$a, $b] as $c) {
        TimetableCardOverride::create(['card_id' => $c->id, 'week' => 8, 'day' => 3, 'pair' => 3]);
    }

    return array_replace($s, ['mudir' => $mudir, 'pa' => $a, 'pb' => $b]);
}

it('vaqtincha kafedra mudiri: to\'qnashuvli karta tashqi joyiga mudir va mudir xonasi bilan yoziladi — to\'qnashuv yo\'q; qaytarish bandlik bilan', function () {
    $s = phSetup();
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    expect($ctl->internalConflicts($s['board'])['count'])->toBe(2);
    $info = wuAs()->getJson(route('admin.timetable.cards.mudir-placeholder.info', $s['pa']) . '?week=8')->assertOk()->json();
    expect($info)->toMatchArray(['ok' => true, 'why' => null, 'len' => 2])->and($info['mudir']['name'])->toBe('XOLMATOV AZIZ BAXTIYOROVICH')
        ->and($info['room'])->toBe(['code' => 'KM1', 'name' => 'Kafedra mudiri xonasi'])->and($info['slot']['text'])->toBe('Chorshanba (10:00–11:20)');
    wuAs()->postJson(route('admin.timetable.cards.mudir-placeholder', $s['pa']), ['week' => 8, 'reason' => '428 band'])->assertOk();
    $a = $s['pa']->fresh();
    expect($a->teacher_id)->toBe($s['mudir']->id)->and($a->auditorium_code)->toBe('KM1')
        ->and($a->mudir_placeholder)->toMatchArray(['teacher_id' => $s['chor']->id, 'teacher_name' => 'CHORIYEVA G. Y.', 'auditorium_code' => 'R428',
            'reason' => '428 band', 'weeks' => [8], 'mudir_name' => 'XOLMATOV AZIZ BAXTIYOROVICH'])
        ->and(wuPlace($a))->toBe([3, 3])
        // Boshqa karta (08a, CHORIYEVA, 428) ham endi to'qnashuvli emas.
        ->and($ctl->internalConflicts($s['board'])['count'])->toBe(0);
    // Tarix (jurnal): kim, qachon, qaysi karta, sabab.
    $log = \App\Models\TimetableChangeRequest::where('route_name', 'admin.timetable.cards.mudir-placeholder')->latest('id')->first();
    expect($log)->not->toBeNull()->and($log->payload['reason'])->toBe('428 band');

    // Qaytarish: CHORIYEVA shu vaqtda 08a da (428) — band, rad etiladi, hech narsa o'zgarmaydi.
    $err = wuAs()->postJson(route('admin.timetable.cards.mudir-placeholder.revert', $a))->assertStatus(422)->json('error');
    expect($err)->toContain("Asl o'qituvchiga qaytarib bo'lmaydi — band: 8-hafta:")->toContain("O'qituvchi band")
        ->and($a->fresh()->teacher_id)->toBe($s['mudir']->id);
    // 08a boshqa vaqtga — bo'sh: asl o'qituvchi va xona tiklanadi.
    TimetableCardOverride::where('card_id', $s['pb']->id)->where('week', 8)->update(['pair' => 7]);
    wuAs()->postJson(route('admin.timetable.cards.mudir-placeholder.revert', $a))->assertOk();
    $a = $a->fresh();
    expect([$a->teacher_id, $a->auditorium_code, $a->mudir_placeholder])->toBe([$s['chor']->id, 'R428', null])->and(wuPlace($a))->toBe([3, 3]);
});

it('vaqtincha kafedra mudiri: mudir topilmasa tugma o\'chiq (sababi bilan); boshqa kafedra mudiri — 403', function () {
    $s = phSetup();
    $s['mudir']->removeRole('kafedra_mudiri');
    $info = wuAs()->getJson(route('admin.timetable.cards.mudir-placeholder.info', $s['pa']) . '?week=8')->assertOk()->json();
    expect($info['ok'])->toBeFalse()->and($info['why'])->toStartWith('Kafedra mudiri topilmadi');
    wuAs()->postJson(route('admin.timetable.cards.mudir-placeholder', $s['pa']), ['week' => 8])->assertStatus(422);
    // Boshqa kafedra mudiri (Gigiyena) — Fizika fani uchun yozolmaydi.
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 502, 'name' => 'Gigiyena kafedrasi', 'code' => 'G2',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $other = wuTeacher('BOSHQA MUDIR', 'kafedra_mudiri');
    $other->forceFill(['department_hemis_id' => 502, 'department' => 'Gigiyena kafedrasi'])->save();
    test()->withoutVite()->actingAs($other, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->postJson(route('admin.timetable.cards.mudir-placeholder', $s['pa']), ['week' => 8])->assertForbidden();
});

it('o\'qituvchi jadvali sarlavhasi: to\'liq F.I.Sh. va telefon (huquq bo\'yicha); xona — nomi, binosi, sig\'imi', function () {
    $s = wuSetup();
    $ali = wuTeacher('ALIYEV SH. N.');
    $ali->forceFill(['full_name' => "ALIYEV SHERZOD NURULLO O'G'LI", 'short_name' => 'ALIYEV SH. N.', 'phone' => '998901234567'])->save();
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd2/d26-07b (rus)', 'teacher_id' => $ali->id, 'teacher_name' => 'ALIYEV SH. N.', 'len_half' => 2]));
    $url = fn ($t) => route('admin.timetable.teacher-agenda', $s['board']) . '?teacher_id=' . $t->id . '&date=2026-10-26&range=week&layer=internal';
    $j = wuAs()->getJson($url($ali))->assertOk()->json();
    expect($j['title'])->toBe("ALIYEV SHERZOD NURULLO O'G'LI — 26.10–31.10.2026")
        ->and($j['teacher'])->toMatchArray(['full_name' => "ALIYEV SHERZOD NURULLO O'G'LI", 'short_name' => 'ALIYEV SH. N.',
            'phone' => '+998 90 123 45 67', 'phone_tel' => '+998901234567']);
    // Telefoni yo'q o'qituvchi — raqam yo'q.
    expect(wuAs()->getJson($url($s['chor']))->assertOk()->json('teacher.phone'))->toBeNull();
    // O'qituvchi rolida — boshqa o'qituvchining raqami ko'rinmaydi, o'ziniki — ko'rinadi.
    Role::findOrCreate('oqituvchi', 'web');
    $s['chor']->assignRole('oqituvchi');
    $asT = fn ($t) => test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => 'oqituvchi']);
    expect($asT($s['chor'])->getJson($url($ali))->assertOk()->json('teacher.phone'))->toBeNull();
    $ali->assignRole('oqituvchi');
    expect($asT($ali)->getJson($url($ali))->assertOk()->json('teacher.phone'))->toBe('+998 90 123 45 67');
    // Xona oynasi: to'liq nomi, binosi va sig'imi.
    \App\Models\Auditorium::create(['code' => 'R12', 'name' => "12-o'quv xona", 'building_name' => 'Tibbiyot klasteri', 'volume' => 10, 'active' => true]);
    $r = wuAs()->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?room_code=R12&date=2026-10-26&range=week')->assertOk()->json();
    expect($r['title'])->toStartWith("12-o'quv xona · Tibbiyot klasteri (10 o'rin) — ")->and($r['room']['building'])->toBe('Tibbiyot klasteri');
});
