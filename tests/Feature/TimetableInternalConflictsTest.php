<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

require_once __DIR__ . '/../Support/TimetableLegacyOccupancy.php';

// To'qnashuvlar faqat kelgusi sanalar bo'yicha hisoblanadi (o'tganlari — kulrang): sinov sanalari (2026-sentabr) oldinda bo'lsin.
beforeEach(fn () => $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent')));

// Har fixture oxirida: yengil unitBusy va haftalik to'qnashuvlar eski (butun doska, modellar) hisobi bilan aynan bir xil.
afterEach(function () {
    foreach (TimetableBoard::all() as $board) {
        assertBusyMatchesLegacy($board);
    }
});

function icTeacher(string $name, ?string $role = null): Teacher
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

function icConflicts(TimetableBoard $board, string $method = 'cycleConflicts'): array
{
    $ctl = app(TimetableController::class);

    return (new ReflectionMethod($ctl, $method))->invoke($ctl, $board);
}

/**
 * 5-kurs sikli (Klinik farmakologiya, 5 kun): F1 1-oqimi guruhchalari. $groups — [guruhcha => [o'qituvchi, xona, talaba]],
 * $starts — [qator nomi => blok boshi (kalendar indeksi)].
 */
function icSetup(array $groups, array $starts): array
{
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $cards = [];
    foreach ($groups as $g => [$teacher, $room, $students]) {
        $cards[$g] = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => $students, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
            'teacher_id' => $teacher?->id, 'teacher_name' => $teacher?->full_name,
            'auditorium_code' => $room?->code, 'auditorium_name' => $room?->name]);
    }
    foreach ($starts as $row => $from) {
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'group_name' => $row, 'subject_name' => 'Klinik farmakologiya', 'start_index' => $from, 'training_type' => 'practice']);
    }

    return compact('board', 'cards');
}

it('sikl: bir o\'qituvchi ikki guruhchada kesishgan davrda — ikkala blok ham to\'qnashuv; o\'qituvchi almashtirilsa yo\'qoladi', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.');
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$sharipov, null, 10]],
        // 05a: 14.09 dan, 05b: 16.09 dan (5 kundan) — 16–18.09 kesishadi
        ['d22-05a' => 0, 'd22-05b' => 2]);
    $c = icConflicts($s['board']);

    expect(array_keys($c))->toBe(['davolash ishi|5|d22-05a|Klinik farmakologiya|practice', 'davolash ishi|5|d22-05b|Klinik farmakologiya|practice'])
        ->and($c['davolash ishi|5|d22-05a|Klinik farmakologiya|practice']['text'])
        ->toBe("O'qituvchi SHARIPOV K. SH. band: d22-05b, Klinik farmakologiya, 16.09–18.09")
        ->and($c['davolash ishi|5|d22-05b|Klinik farmakologiya|practice']['text'])
        ->toBe("O'qituvchi SHARIPOV K. SH. band: d22-05a, Klinik farmakologiya, 16.09–18.09")
        ->and($c['davolash ishi|5|d22-05a|Klinik farmakologiya|practice']['items'][0])
        ->toMatchArray(['type' => 'teacher', 'who' => 'SHARIPOV K. SH.', 'from' => '2026-09-16', 'to' => '2026-09-18', 'group' => 'd22-05a']);

    // Davrlar kesishmasa — to'qnashuv yo'q
    TimetableCyclePlacement::where('group_name', 'd22-05b')->update(['start_index' => 7]);
    expect(icConflicts($s['board']))->toBe([]);

    // Kesishadi, lekin 05b ga boshqa o'qituvchi — belgi yo'qoladi (joylashuv o'zgarmaydi)
    TimetableCyclePlacement::where('group_name', 'd22-05b')->update(['start_index' => 2]);
    expect(icConflicts($s['board']))->toHaveCount(2);
    $other = icTeacher('BOSHQA');
    $s['cards']['d22-05b']->update(['teacher_id' => $other->id, 'teacher_name' => 'BOSHQA']);
    expect(icConflicts($s['board']))->toBe([])
        ->and(TimetableCyclePlacement::where('board_id', $s['board']->id)->count())->toBe(2);
});

it('sikl: oqim ma\'ruzasi va "A + B" umumiy kartasi — bitta dars; boshqa oqimning ma\'ruzasi bir vaqtda — to\'qnashuv', function () {
    $t = icTeacher('USTOZ');
    $lector = icTeacher('LEKTOR');
    // Ikkala guruhcha bitta umumiy amaliy kartada (bitta dars), ma'ruza — oqimga (2 kun × 2 soat, keyin amaliy)
    $s = icSetup(['d22-05a + d22-05b' => [$t, null, 20]], ['F1 / 1-oqim' => 0]);
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 4, 'practice' => 26, 'created_at' => now(), 'updated_at' => now()]);
    $lecture = fn (string $oqim, array $groups) => TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 20, 'training_type' => 'lecture',
        'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'group_names' => $groups,
        'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR']);
    $lecture('1-oqim', ['d22-05a', 'd22-05b']);
    // Amaliyni ham ma'ruzachi o'qisa — vaqti boshqa (ma'ruzadan keyin), to'qnashuv emas
    $s['cards']['d22-05a + d22-05b']->update(['teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR']);
    expect(icConflicts($s['board']))->toBe([]);

    // 2-oqim: o'sha ma'ruzachi, o'sha kunlarda, o'sha soatda — ikki xil ma'ruza (karta) to'qnashadi
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '2-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd22-06a', 'kafedra_name' => 'Farmakologiya kafedrasi']);
    $lecture('2-oqim', ['d22-06a']);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 2-oqim', 'subject_name' => 'Klinik farmakologiya', 'start_index' => 1, 'training_type' => 'practice']);
    $c = icConflicts($s['board']);
    expect(array_keys($c))->toBe(['davolash ishi|5|d22-05a|Klinik farmakologiya|practice', 'davolash ishi|5|d22-05b|Klinik farmakologiya|practice',
        'davolash ishi|5|d22-06a|Klinik farmakologiya|practice'])
        // 1-oqim ma'ruzasi 14–15.09 (1–2-soat), 2-oqimniki 15–16.09: 15.09 — ikki ma'ruza, 16.09 — 2-oqim ma'ruzasi va
        // 1-oqim amaliyoti (ma'ruzasiz kun 1-soatdan) bir vaqtda
        ->and($c['davolash ishi|5|d22-06a|Klinik farmakologiya|practice']['text'])
        ->toBe("O'qituvchi LEKTOR band: d22-05a, Klinik farmakologiya; d22-05b, Klinik farmakologiya, 15.09–16.09")
        ->and(collect($c['davolash ishi|5|d22-06a|Klinik farmakologiya|practice']['items'])->pluck('time')->all())->toBe(['1–2-soat']);
});

it('sikl: bitta xonada 3 guruhcha, sig\'imi 1 guruhchaga yetadi — xona to\'qnashuvi', function () {
    $room = Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 12, 'active' => true]);
    [$a, $b, $c] = [icTeacher('A'), icTeacher('B'), icTeacher('C')];
    $s = icSetup(['d22-05a' => [$a, $room, 10], 'd22-05b' => [$b, $room, 10], 'd22-05c' => [$c, $room, 10]], ['F1 / 1-oqim' => 0]);
    $conf = icConflicts($s['board']);

    expect($conf)->toHaveCount(3)
        ->and($conf['davolash ishi|5|d22-05a|Klinik farmakologiya|practice']['text'])
        ->toBe("Xona 305 sig'imidan ko'p (12 o'rin, 30 talaba): d22-05b, Klinik farmakologiya; d22-05c, Klinik farmakologiya, 14.09–18.09")
        ->and($conf['davolash ishi|5|d22-05c|Klinik farmakologiya|practice']['items'][0]['type'])->toBe('room');

    // Sig'im yetarli bo'lsa — to'qnashuv yo'q; "Kafedra mudiri xonasi" va umumiy xona tekshirilmaydi
    $room->update(['volume' => 40]);
    expect(icConflicts($s['board']))->toBe([]);
    $room->update(['volume' => 12, 'name' => 'Kafedra mudiri xonasi']);
    foreach ($s['cards'] as $card) {
        $card->update(['auditorium_name' => 'Kafedra mudiri xonasi']);
    }
    expect(icConflicts($s['board']))->toBe([]);
});

it('haftalik: findWeekConflicts bilan — bir vaqtda bitta o\'qituvchi ikki guruhda; ko\'chirilgan haftada yo\'q, almashtirilsa yo\'qoladi', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 3, 'settings' => ['semester_start' => '2026-09-14']]);
    $t = icTeacher('USTOZ');
    $mk = fn (string $g, string $room) => TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 3,
        'subject_name' => 'Anatomiya', 'group_name' => $g, 'kafedra_name' => 'K', 'teacher_id' => $t->id, 'teacher_name' => 'USTOZ',
        'auditorium_code' => $room, 'auditorium_name' => $room, 'day' => 2, 'pair' => 3]);
    $a = $mk('d2-01a', '101');
    $b = $mk('d2-01b', '102');
    // 3-haftada b boshqa kunga ko'chirilgan
    \App\Models\TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 4, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    // Boshqa o'qituvchining shu vaqtdagi darsi — to'qnashmaydi
    TimetableCard::create($a->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students', 'training_type',
        'len_half', 'weeks', 'kafedra_name', 'day', 'pair']) + ['subject_name' => 'Gistologiya', 'group_name' => 'd2-02a',
        'teacher_id' => icTeacher('BOSHQA')->id, 'teacher_name' => 'BOSHQA', 'auditorium_code' => '103', 'auditorium_name' => '103']);
    $w = icConflicts($board, 'weeklyConflicts');

    expect(array_keys($w))->toBe([$a->id, $b->id])
        ->and(array_keys($w[$a->id]))->toBe([1, 2])
        ->and($w[$a->id][1])->toMatchArray(['date' => '2026-09-15', 'day' => 2, 'pair' => 3])
        ->and($w[$a->id][1]['text'])->toStartWith("O'qituvchi band: USTOZ — F1, 2-kurs · d2-01b · Anatomiya (amaliy)");

    $all = app(TimetableController::class)->internalConflicts($board);
    expect($all['count'])->toBe(4)
        ->and($all['items'][0])->toMatchArray(['kind' => 'weekly', 'type' => 'teacher', 'date' => '2026-09-15', 'time' => 'Seshanba, 2-para'])
        // Qamrov: boshqa yo'nalish tanlansa — chiqmaydi
        ->and(app(TimetableController::class)->internalConflicts($board, ['specialty_names' => ['pediatriya']])['count'])->toBe(0);

    $b->update(['teacher_id' => icTeacher('YANGI')->id, 'teacher_name' => 'YANGI']);
    expect(icConflicts($board, 'weeklyConflicts'))->toBe([]);
});

it('biriktirish: sikl blokiga band o\'qituvchi — ogohlantirish (kim bilan, qachon), "Baribir" bilan saqlanadi; ro\'yxatda band', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.');
    $other = icTeacher('BOSHQA');
    // d22-05c — SHARIPOV o'sha sanalarda 13:30 smenasida: 05b ni boshqa smenaga o'tkazib bo'lmaydi (avtomatik smena chiqish emas).
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$other, null, 10], 'd22-05c' => [$sharipov, null, 10]],
        ['d22-05a' => 0, 'd22-05b' => 2, 'd22-05c' => 2]);
    TimetableCyclePlacement::where('group_name', 'd22-05c')->update(['lecture_slots' => json_encode(['shift_g' => ['d22-05c' => 7]])]);
    $admin = icTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $body = ['specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd22-05b', 'subject_name' => 'Klinik farmakologiya',
        'training_type' => 'practice', 'view' => 'group'];

    // Ro'yxatda: SHARIPOV 05a bilan 16–18.09 da band
    $opts = $as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $body)->assertOk()->json();
    expect($opts['busy_teachers'])->toBe([(string) $sharipov->id => 'd22-05a, Klinik farmakologiya, 16.09–18.09']);

    $res = $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $body + ['teacher_id' => $sharipov->id])->assertStatus(409);
    expect($res->json('needs_confirm'))->toBeTrue()
        ->and($res->json('error'))->toContain("O'qituvchi SHARIPOV K. SH.: d22-05a, Klinik farmakologiya, 16.09–18.09")
        ->and($s['cards']['d22-05b']->fresh()->teacher_id)->toBe($other->id)
        ->and(icConflicts($s['board']))->toBe([]);

    // "Baribir biriktirish" — saqlanadi, ikkala blok to'qnashuvli bo'ladi
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $body + ['teacher_id' => $sharipov->id, 'force' => 1])->assertOk();
    expect($s['cards']['d22-05b']->fresh()->teacher_id)->toBe($sharipov->id)
        ->and(icConflicts($s['board']))->toHaveCount(2);
    // Ichki sikl jadvali: blok joyida (placed), lekin to'qnashuv belgisi bilan — "joylashgan" hisobiga kirmaydi
    $plan = $as()->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'])->assertOk()->json();
    $card = collect($plan['cycle_cards'])->firstWhere('group', 'd22-05b');
    $block = collect($plan['rows'])->firstWhere('group', 'd22-05b')['blocks'][0];
    expect($card['placed'])->toBeTrue()
        ->and($card['conflict'])->toBe("O'qituvchi SHARIPOV K. SH. band: d22-05a, Klinik farmakologiya, 16.09–18.09")
        ->and($block['conflict'])->toBe($card['conflict']);
    // Shu o'qituvchi qayta saqlansa (o'zgarish yo'q) — ogohlantirilmaydi
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $body + ['teacher_id' => $sharipov->id])->assertOk();
});

it('biriktirish: haftalik — band o\'qituvchi va boshqa haftada band xona uchun ogohlantirish; unit-busy ro\'yxati', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 3, 'settings' => ['semester_start' => '2026-09-14']]);
    $busy = icTeacher('BAND');
    $free = icTeacher('BOSH');
    $room = Auditorium::create(['code' => 'R7', 'name' => '7-xona', 'volume' => 30, 'active' => true]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 3, 'kafedra_name' => 'K', 'day' => 2, 'pair' => 3];
    TimetableCard::create($base + ['subject_name' => 'Anatomiya', 'group_name' => 'd2-01a']);
    // BAND o'qituvchi shu vaqtda boshqa guruhda; 7-xona faqat 2-haftada (ko'chirilgan) boshqa darsda
    TimetableCard::create($base + ['subject_name' => 'Gistologiya', 'group_name' => 'd2-02a', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND']);
    $moved = TimetableCard::create(array_merge($base, ['subject_name' => 'Biologiya', 'group_name' => 'd2-03a', 'day' => 5, 'pair' => 1,
        'auditorium_code' => 'R7', 'auditorium_name' => '7-xona']));
    \App\Models\TimetableCardOverride::create(['card_id' => $moved->id, 'week' => 2, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    $admin = icTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Anatomiya',
        'training_type' => 'practice', 'group_name' => 'd2-01a'];

    expect($as()->getJson(route('admin.timetable.unit-busy', ['board' => $board->id] + $unit))->assertOk()->json())
        ->toBe(['teachers' => [(string) $busy->id => 'd2-02a, Gistologiya, 15.09–29.09'], 'rooms' => ['R7' => 'd2-03a, Biologiya, 22.09']]);

    $as()->postJson(route('admin.timetable.assign-teacher', $board), $unit + ['teacher_id' => $busy->id])->assertStatus(409)
        ->assertJsonPath('needs_confirm', true)
        ->assertJsonPath('error', "O'qituvchi BAND shu davrda boshqa guruhcha bilan band: d2-02a, Gistologiya, 15.09–29.09");
    $as()->postJson(route('admin.timetable.assign-teacher', $board), $unit + ['teacher_id' => $free->id])->assertOk();
    // "Baribir biriktirish" — band o'qituvchi saqlanadi
    $as()->postJson(route('admin.timetable.assign-teacher', $board), $unit + ['teacher_id' => $busy->id, 'force' => 1])->assertOk();
    expect(TimetableCard::where('group_name', 'd2-01a')->value('teacher_id'))->toBe($busy->id);
    // Haftalik jadvalda xona to'qnashuvi avvalgidek qat'iy rad etiladi (ko'chirilgan hafta ham) — "Baribir" yo'q
    $as()->postJson(route('admin.timetable.assign-room', $board), $unit + ['auditorium_code' => 'R7', 'force' => 1])->assertStatus(422);
});

it('biriktirish: sikl guruhchasiga xona — sig\'imdan oshsa ogohlantirish, yetsa yo\'q; "Baribir" bilan saqlanadi', function () {
    $room = Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 12, 'active' => true]);
    $s = icSetup(['d22-05a' => [icTeacher('A'), $room, 10], 'd22-05b' => [icTeacher('B'), null, 10]], ['F1 / 1-oqim' => 0]);
    $admin = icTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $unit = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Klinik farmakologiya',
        'training_type' => 'practice', 'group_name' => 'd22-05b'];

    $as()->postJson(route('admin.timetable.assign-room', $s['board']), $unit + ['auditorium_code' => 'R305'])->assertStatus(409)
        ->assertJsonPath('error', 'Xona «305» shu davrda boshqa guruhcha bilan band: d22-05a, Klinik farmakologiya, 14.09–18.09');
    expect($s['cards']['d22-05b']->fresh()->auditorium_code)->toBeNull();
    // Sig'im ikkala guruhchaga yetsa — ogohlantirishsiz
    $room->update(['volume' => 25]);
    $as()->postJson(route('admin.timetable.assign-room', $s['board']), $unit + ['auditorium_code' => 'R305'])->assertOk();
    // Sig'im yetmasa, "Baribir" — saqlanadi va xona to'qnashuvi belgilanadi
    $s['cards']['d22-05b']->update(['auditorium_code' => null, 'auditorium_name' => null]);
    $room->update(['volume' => 12]);
    $as()->postJson(route('admin.timetable.assign-room', $s['board']), $unit + ['auditorium_code' => 'R305', 'force' => 1])->assertOk();
    expect(icConflicts($s['board']))->toHaveCount(2);
});

it('Excel: unplaced-export va butun doska hisobotida to\'qnashuvli darslar "To\'qnashuv" sababi bilan', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 2, 'settings' => ['semester_start' => '2026-09-14']]);
    $t = icTeacher('USTOZ');
    foreach (['d2-01a', 'd2-01b'] as $g) {
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 2,
            'subject_name' => 'Anatomiya', 'group_name' => $g, 'kafedra_name' => 'K', 'teacher_id' => $t->id, 'teacher_name' => 'USTOZ',
            'auditorium_code' => $g, 'auditorium_name' => $g, 'day' => 2, 'pair' => 3]);
    }
    $admin = icTeacher('ADMIN', 'admin');
    $sheet = function ($resp, ?string $title = null) {
        $file = tempnam(sys_get_temp_dir(), 'ic') . '.xlsx';
        file_put_contents($file, $resp instanceof \Illuminate\Testing\TestResponse ? $resp->streamedContent() : file_get_contents($resp));
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
        @unlink($file);

        return ($title ? $book->getSheetByName($title) : $book->getActiveSheet())->toArray(null, false, false, false);
    };
    $export = fn (array $q) => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->get(route('admin.timetable.boards.unplaced-export', ['board' => $board->id] + $q))->assertOk();

    // Shu hafta: ikkala karta joyida, lekin to'qnashuvli — sababi bilan
    $rows = array_slice($sheet($export(['layer' => 'internal', 'week' => 1])), 2);
    expect(array_map(fn ($r) => [$r[5], $r[12], $r[13]], $rows))->toBe([
        ['d2-01a', 'Seshanba, 2-para', "To'qnashuv (1-hafta): O'qituvchi band: USTOZ — F1, 2-kurs · d2-01b · Anatomiya (amaliy)"],
        ['d2-01b', 'Seshanba, 2-para', "To'qnashuv (1-hafta): O'qituvchi band: USTOZ — F1, 2-kurs · d2-01a · Anatomiya (amaliy)"],
    ]);
    // Shablon ko'rinishi — barcha haftalar; tashqi qatlam — ichki jadval to'qnashuvi qo'shilmaydi (u yerda oddiy joylanmagan)
    expect(array_slice($sheet($export(['layer' => 'internal'])), 2)[0][13])->toStartWith("To'qnashuv (1–2-hafta):")
        ->and(implode(' ', array_column(array_slice($sheet($export(['layer' => 'external'])), 2), 13)))->not->toContain("To'qnashuv");

    // Butun doska hisoboti: Batafsil — "To'qnashuv: ..." (haftalar birlashgan), Yig'ma — har hafta sanaladi
    $file = app(TimetableController::class)->buildUnplacedReport($board);
    $det = $sheet($file, 'Batafsil (haftalik)');
    $sum = $sheet($file, 'Yig\'ma');
    @unlink($file);
    expect(array_map(fn ($r) => [$r[4], $r[7], $r[11], $r[12]], array_slice($det, 1)))->toBe([
        ['d2-01a', '1–2', 'Seshanba, 2-para', "To'qnashuv: O'qituvchi band: USTOZ — F1, 2-kurs · d2-01b · Anatomiya (amaliy)"],
        ['d2-01b', '1–2', 'Seshanba, 2-para', "To'qnashuv: O'qituvchi band: USTOZ — F1, 2-kurs · d2-01a · Anatomiya (amaliy)"],
    ])->and($sum[1])->toBe(['F1', 'davolash ishi', 2, 2, 2, 4]);
});

it('Excel: butun doska hisobotining Sikl varag\'ida to\'qnashuvli blok sababi bilan', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.');
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$sharipov, null, 10], 'd22-05c' => [icTeacher('C'), null, 10]],
        ['d22-05a' => 0, 'd22-05b' => 2]);   // 05c — joylanmagan
    $file = app(TimetableController::class)->buildUnplacedReport($s['board']);
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    @unlink($file);
    $rows = array_slice($book->getSheetByName('Sikl (joylanmagan)')->toArray(null, false, false, false), 1);
    expect($book->getSheetByName('Sikl (joylanmagan)')->getCell('H1')->getValue())->toBe('Sabab')
        ->and(array_map(fn ($r) => [$r[3], $r[7]], $rows))->toBe([
            ['d22-05a', "To'qnashuv: O'qituvchi SHARIPOV K. SH. band: d22-05b, Klinik farmakologiya, 16.09–18.09"],
            ['d22-05b', "To'qnashuv: O'qituvchi SHARIPOV K. SH. band: d22-05a, Klinik farmakologiya, 16.09–18.09"],
            ['d22-05c', 'Joylanmagan'],
        ]);
});

it('sikl xonasi: sig\'im bir vaqtdagi eng yuqori yuklama bo\'yicha; "A + B" umumiy karta talabalari bir marta', function () {
    // Codex: bitta uzun dars (1–6-soat) bir-biriga kesishmaydigan ikki qisqa dars (1–2 va 3–6) bilan bir xonada — har vaqtda 20 talaba
    $room = Auditorium::create(['code' => 'R', 'name' => '305', 'volume' => 20, 'active' => true]);
    $other = fn (string $c) => Auditorium::create(['code' => $c, 'name' => $c . '-xona', 'volume' => 50, 'active' => true]);
    [$x1, $x2, $x3] = [$other('X1'), $other('X2'), $other('X3')];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    // Kunlik reja: 1–2-kun — 2 soat ma'ruza + 4 soat amaliy, keyin 6 soat amaliy
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 4, 'practice' => 26, 'created_at' => now(), 'updated_at' => now()]);
    $card = fn (string $oqim, string $type, array $groups, $room) => TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 10, 'training_type' => $type,
        'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'kafedra_name' => 'K', 'auditorium_code' => $room->code,
        'auditorium_name' => $room->name] + ($type === 'lecture' ? ['group_names' => $groups] : ['group_name' => $groups[0]]));
    // 16.09 da: 05a (1-oqim) 1-kuni — ma'ruzasi R da (1–2); 06a (2-oqim) 1-kuni — amaliyoti R da (3–6); 07a (3-oqim) 3-kuni — amaliyoti R da (1–6)
    $card('1-oqim', 'lecture', ['d22-05a'], $room);
    $card('1-oqim', 'practice', ['d22-05a'], $x1);
    $card('2-oqim', 'lecture', ['d22-06a'], $x2);
    $card('2-oqim', 'practice', ['d22-06a'], $room);
    $card('3-oqim', 'lecture', ['d22-07a'], $x3);
    $c7 = $card('3-oqim', 'practice', ['d22-07a'], $room);
    foreach (['F1 / 1-oqim' => 2, 'F1 / 2-oqim' => 2, 'F1 / 3-oqim' => 0] as $row => $from) {
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'group_name' => $row, 'subject_name' => 'Klinik farmakologiya', 'start_index' => $from, 'training_type' => 'practice']);
    }
    expect(icConflicts($board))->toBe([]);
    // Biriktirishda ham: 07a ga R xonasini qayta biriktirish — ogohlantirishsiz (eng yuqori yuklama 10 + 10 = 20)
    $c7->update(['auditorium_code' => null, 'auditorium_name' => null]);
    $admin = icTeacher('ADMIN', 'admin');
    test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.assign-room', $board), ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
            'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'group_name' => 'd22-07a', 'auditorium_code' => 'R'])->assertOk();
    // Sig'im 19 bo'lsa — haqiqatan oshadi
    $room->update(['volume' => 19]);
    expect(icConflicts($board))->toHaveKey('davolash ishi|5|d22-07a|Klinik farmakologiya|practice');

    // "A + B" umumiy amaliy kartasi (20 talaba) 25 o'rinli xonada — bitta dars, 40 emas
    $big = Auditorium::create(['code' => 'B25', 'name' => '25-xona', 'volume' => 25, 'active' => true]);
    $s = icSetup(['d23-01a + d23-01b' => [icTeacher('U'), $big, 20]], ['F1 / 1-oqim' => 0]);
    expect(icConflicts($s['board']))->toBe([]);
    $big->update(['volume' => 15]);
    expect(array_keys(icConflicts($s['board'])))->toBe(['davolash ishi|5|d23-01a|Klinik farmakologiya|practice', 'davolash ishi|5|d23-01b|Klinik farmakologiya|practice']);
});

it('to\'qnashuvlar: tuzilgan ro\'yxat (kind, teacher_id, groups, sanalar), o\'tgan va qisman o\'tgan sanalar, rang darajasi', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.');
    $room = Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 12, 'active' => true]);
    // 05a va 05b: bitta o'qituvchi, 16–18.09 kesishadi (qizil); 05c va 05d: bitta xonada, sig'im yetmaydi (sariq).
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$sharipov, null, 10],
        'd22-05c' => [icTeacher('C'), $room, 10], 'd22-05d' => [icTeacher('D'), $room, 10]],
        ['d22-05a' => 0, 'd22-05b' => 2, 'd22-05c' => 7, 'd22-05d' => 7]);
    $c = icConflicts($s['board']);
    $a = $c['davolash ishi|5|d22-05a|Klinik farmakologiya|practice'];
    expect($a['level'])->toBe('red')
        ->and($a['items'][0])->toMatchArray(['kind' => 'teacher_busy', 'teacher_id' => $sharipov->id, 'card_teacher_id' => $sharipov->id,
            'groups' => ['d22-05b'], 'lecture' => false, 'date_from' => '2026-09-16', 'date_to' => '2026-09-18', 'past' => false])
        ->and($a['items'][0]['detail'])->toContain("O'qituvchi SHARIPOV K. SH. band: d22-05b")->toContain("boshqa o'qituvchi biriktiring");
    $cc = $c['davolash ishi|5|d22-05c|Klinik farmakologiya|practice'];
    expect($cc['level'])->toBe('yellow')->and($cc['items'][0])->toMatchArray(['kind' => 'room_capacity', 'room' => 'R305', 'groups' => ['d22-05d']]);

    // Qisman o'tgan (bugun 17.09): faqat kelgusi sanalar — 17–18.09.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-17 09:00', 'Asia/Tashkent'));
    $a = icConflicts($s['board'])['davolash ishi|5|d22-05a|Klinik farmakologiya|practice'];
    expect($a['items'][0])->toMatchArray(['date_from' => '2026-09-17', 'date_to' => '2026-09-18', 'past' => false, 'dates' => '17.09–18.09'])
        ->and($a['text'])->toContain('17.09–18.09');
    // Butunlay o'tgan: belgi (matn, rang) yo'q — ro'yxatda "o'tgan"; kelgusilar hisobida (internalConflicts) — chiqmaydi.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-19 09:00', 'Asia/Tashkent'));
    $a = icConflicts($s['board'])['davolash ishi|5|d22-05a|Klinik farmakologiya|practice'];
    expect($a['text'])->toBe('')->and($a['level'])->toBeNull()->and($a['items'][0]['past'])->toBeTrue()
        ->and($a['items'][0]['detail'])->toContain("(o'tgan)");
    $all = app(TimetableController::class)->internalConflicts($s['board']);
    expect(collect($all['items'])->pluck('group')->unique()->sort()->values()->all())->toBe(['d22-05c', 'd22-05d']);
});

it('to\'qnashuvlar: sikl rejasida bloklarda tuzilgan ro\'yxat; "Faqat meniki" — faqat o\'zi band bo\'lgan va o\'z xonasi', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.', 'oqituvchi');
    $room = Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 12, 'active' => true]);
    $other = icTeacher('BOSHQA', 'oqituvchi');
    // 05a: SHARIPOV, R305; 05b: SHARIPOV (o'qituvchi band); 05c: BOSHQA, R305 (05a bilan xona sig'imi).
    $s = icSetup(['d22-05a' => [$sharipov, $room, 10], 'd22-05b' => [$sharipov, null, 10], 'd22-05c' => [$other, $room, 10]],
        ['d22-05a' => 0, 'd22-05b' => 2, 'd22-05c' => 0]);
    $admin = icTeacher('ADMIN', 'admin');
    $plan = fn ($who, string $role, array $extra = []) => test()->withoutVite()->actingAs($who, 'teacher')->withSession(['active_role' => $role])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'] + $extra)->assertOk()->json();
    $block = fn (array $p, string $g) => collect($p['rows'])->firstWhere('group', $g)['blocks'][0];
    $a = $block($plan($admin, 'admin'), 'd22-05a');
    expect(collect($a['conflicts'])->pluck('kind')->sort()->values()->all())->toBe(['room_capacity', 'teacher_busy'])
        ->and($a['conflict_level'])->toBe('red');
    // BOSHQA "Faqat meniki": 05c — faqat o'z xonasi sig'imi (SHARIPOV bandligi — boshqa o'qituvchi, ko'rinmaydi).
    $c = $block($plan($other, 'oqituvchi', ['mine' => 1]), 'd22-05c');
    expect(collect($c['conflicts'])->pluck('kind')->all())->toBe(['room_capacity'])->and($c['conflict_level'])->toBe('yellow');
    // SHARIPOV "Faqat meniki": 05a — o'zi band + o'z xonasi; 05b — o'zi band.
    $p = $plan($sharipov, 'oqituvchi', ['mine' => 1]);
    expect(collect($block($p, 'd22-05a')['conflicts'])->pluck('kind')->sort()->values()->all())->toBe(['room_capacity', 'teacher_busy'])
        ->and(collect($block($p, 'd22-05b')['conflicts'])->pluck('kind')->all())->toBe(['teacher_busy']);
    // Panel kartasi ham shu (qamrovli) ro'yxat bilan.
    $card = collect($plan($other, 'oqituvchi', ['mine' => 1])['cycle_cards'])->firstWhere('group', 'd22-05c');
    expect($card['conflict_level'])->toBe('yellow')->and($card['conflict'])->toContain("Xona 305 sig'imidan ko'p");
});

/** 1-oqim (05a, 05b) ma'ruzasi (LEKTOR, R-ZAL) va amaliyoti (USTOZ); 2-oqim ma'ruzasi shu vaqtda (LEKTOR) — ma'ruza to'qnashuvi. */
function icLectureSetup(Teacher $lector, Teacher $practice, ?Auditorium $hall = null): array
{
    // 05b — boshqa amaliy o'qituvchisi (bitta o'qituvchi ikki guruhchada bir vaqtda — o'zi to'qnashuv bo'lardi).
    $s = icSetup(['d22-05a' => [$practice, null, 10], 'd22-05b' => [icTeacher('USTOZ2'), null, 10]], ['F1 / 1-oqim' => 0]);
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Klinik farmakologiya',
        'semester' => 9, 'lecture' => 4, 'practice' => 26, 'created_at' => now(), 'updated_at' => now()]);
    $lecture = fn (string $oqim, array $groups, int $students) => TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1',
        'specialty_name' => 'davolash ishi', 'course' => 5, 'oqim_label' => $oqim, 'lang' => 'uz', 'students' => $students, 'training_type' => 'lecture',
        'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Klinik farmakologiya', 'group_names' => $groups, 'kafedra_name' => 'Farmakologiya kafedrasi',
        'teacher_id' => $lector->id, 'teacher_name' => $lector->full_name, 'auditorium_code' => $hall?->code, 'auditorium_name' => $hall?->name]);
    $s['lecture'] = $lecture('1-oqim', ['d22-05a', 'd22-05b'], 20);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '2-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd22-06a', 'kafedra_name' => 'Farmakologiya kafedrasi']);
    $lecture('2-oqim', ['d22-06a'], 10);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'F1 / 2-oqim', 'subject_name' => 'Klinik farmakologiya', 'start_index' => 1, 'training_type' => 'practice']);

    return $s;
}

it('to\'qnashuvlar: faqat boshqa o\'qituvchiga tegishli (ma\'ruza o\'qituvchisi band) — amaliy o\'qituvchisi kartasida oddiy rang; ma\'ruzachining o\'ziga ko\'rinadi', function () {
    $lector = icTeacher('LEKTOR', 'oqituvchi');
    $ustoz = icTeacher('USTOZ', 'oqituvchi');
    $s = icLectureSetup($lector, $ustoz);
    $admin = icTeacher('ADMIN', 'admin');
    $plan = fn ($who, string $role, array $extra = []) => test()->withoutVite()->actingAs($who, 'teacher')->withSession(['active_role' => $role])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'] + $extra)->assertOk()->json();
    $block = fn (array $p, string $g) => collect($p['rows'])->firstWhere('group', $g)['blocks'][0] ?? null;
    // Admin: 05a blokida ma'ruza o'qituvchisi bandligi (ma'ruza belgisi bilan), qizil.
    $a = $block($plan($admin, 'admin'), 'd22-05a');
    expect($a['conflict_level'])->toBe('red')->and(collect($a['conflicts'])->firstWhere('kind', 'teacher_busy'))
        ->toMatchArray(['teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR', 'lecture' => true, 'lecture_id' => $s['lecture']->id]);
    // USTOZ "Faqat meniki": 05a — faqat boshqaga (LEKTOR) tegishli to'qnashuv: ro'yxat bo'sh, rang oddiy, panel kartasida yo'q.
    $p = $plan($ustoz, 'oqituvchi', ['mine' => 1]);
    $u = $block($p, 'd22-05a');
    expect($u['conflicts'])->toBe([])->and($u['conflict'])->toBeNull()->and($u['conflict_level'])->toBeNull()
        ->and(collect($p['cycle_cards'])->firstWhere('group', 'd22-05a')['conflict'])->toBeNull();
    // LEKTOR "Faqat meniki": o'zi o'qiydigan ma'ruza — bandligi ko'rinadi.
    $l = $block($plan($lector, 'oqituvchi', ['mine' => 1]), 'd22-05a');
    expect(collect($l['conflicts'])->pluck('kind')->all())->toContain('teacher_busy')->and($l['conflict_level'])->toBe('red');
});

it('to\'qnashuvlar: ma\'ruza zali sig\'imi — ma\'ruzachiga ko\'rinadi, amaliy o\'qituvchisiga yo\'q; amaliyot ma\'ruza bilan ustma-ust — lecture_overlap (qizil)', function () {
    $hall = Auditorium::create(['code' => 'ZAL', 'name' => 'Zal', 'volume' => 15, 'active' => true]);
    $lector = icTeacher('LEKTOR', 'oqituvchi');
    $ustoz = icTeacher('USTOZ', 'oqituvchi');
    $s = icLectureSetup($lector, $ustoz, $hall);
    // 2-oqim ma'ruzasini boshqa ma'ruzachiga — faqat zal sig'imi qoladi (1-oqim 20 + 2-oqim 10 > 15).
    TimetableCard::where('oqim_label', '2-oqim')->where('training_type', 'lecture')->update(['teacher_id' => icTeacher('BOSHQA')->id, 'teacher_name' => 'BOSHQA']);
    $plan = fn ($who, array $extra = []) => test()->withoutVite()->actingAs($who, 'teacher')->withSession(['active_role' => 'oqituvchi'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'mine' => 1] + $extra)->assertOk()->json();
    $block = fn (array $p, string $g) => collect($p['rows'])->firstWhere('group', $g)['blocks'][0] ?? null;
    // 14.09 — 1-oqim o'zi (20 > 15), 15.09 — ikkala oqim (30 > 15): ikkalasi ham zal sig'imi.
    expect(collect($block($plan($lector), 'd22-05a')['conflicts'])->pluck('kind')->unique()->values()->all())->toBe(['room_capacity'])
        ->and($block($plan($lector), 'd22-05a')['conflict_level'])->toBe('yellow')
        ->and($block($plan($ustoz), 'd22-05a')['conflicts'])->toBe([]);
    // 05a shu davrda boshqa sikl fanida ham (Ichki kasalliklar, AMALIYCHI, soatlarsiz — 1–6-soat amaliy): uning amaliyoti
    // Klinik farmakologiya ma'ruzasi (1–2-soat) bilan ustma-ust — lecture_overlap (qizil), amaliy–amaliy — group_busy ikkala tomonda.
    $amaliychi = icTeacher('AMALIYCHI', 'oqituvchi');
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Ichki kasalliklar', 'mode' => 'cycle', 'cycle_days' => 5]);
    TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Ichki kasalliklar', 'group_name' => 'd22-05a', 'kafedra_name' => 'Ichki kasalliklar kafedrasi',
        'teacher_id' => $amaliychi->id, 'teacher_name' => 'AMALIYCHI']);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'group_name' => 'd22-05a', 'subject_name' => 'Ichki kasalliklar', 'start_index' => 0, 'training_type' => 'practice']);
    $subjectBlock = fn (array $p, string $g, string $subject) => collect($p['rows'])->where('group', $g)->flatMap(fn ($r) => $r['blocks'])
        ->firstWhere('subject', $subject);
    $b = $subjectBlock($plan($amaliychi), 'd22-05a', 'Ichki kasalliklar');
    expect(collect($b['conflicts'])->pluck('kind')->sort()->values()->all())->toBe(['group_busy', 'lecture_overlap'])
        ->and($b['conflict_level'])->toBe('red')
        ->and(collect($b['conflicts'])->firstWhere('kind', 'lecture_overlap'))
        ->toMatchArray(['type' => 'group', 'groups' => ['d22-05a'], 'date_from' => '2026-09-14', 'date_to' => '2026-09-15'])
        ->and(collect($b['conflicts'])->firstWhere('kind', 'lecture_overlap')['detail'])
        ->toBe("Amaliy dars ma'ruza bilan ustma-ust: d22-05a — d22-05a, Klinik farmakologiya ma'ruzasi, 14.09–15.09 — amaliyot vaqtini ma'ruzadan keyinga suring");
    // Rang: faqat ma'ruza bilan ustma-ust ham qizil; faqat xona — sariq; hammasi o'tgan — null.
    expect(TimetableController::conflictLevel([['kind' => 'lecture_overlap', 'past' => false]]))->toBe('red')
        ->and(TimetableController::conflictLevel([['kind' => 'room_busy', 'past' => false], ['kind' => 'teacher_busy', 'past' => true]]))->toBe('yellow')
        ->and(TimetableController::conflictLevel([['kind' => 'teacher_busy', 'past' => true]]))->toBeNull();
    // USTOZ (Klinik farmakologiya amaliyoti): guruh band (Ichki kasalliklar amaliyoti), ma'ruza tomonida ustma-ust yozilmaydi.
    $u = $subjectBlock($plan($ustoz), 'd22-05a', 'Klinik farmakologiya');
    expect(collect($u['conflicts'])->pluck('kind')->unique()->values()->all())->toBe(['group_busy'])->and($u['conflict_level'])->toBe('red');
    // Boshqa fan davri kesishmasa — guruh to'qnashuvlari yo'q.
    TimetableCyclePlacement::where('subject_name', 'Ichki kasalliklar')->update(['start_index' => 10]);
    expect($subjectBlock($plan($amaliychi), 'd22-05a', 'Ichki kasalliklar')['conflicts'])->toBe([]);
});

it('to\'qnashuvlar: sikl rejasida o\'tgan to\'qnashuv qizil emas (kulrang "o\'tgan"), admin hammasini ko\'radi', function () {
    $sharipov = icTeacher('SHARIPOV K. SH.');
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$sharipov, null, 10]], ['d22-05a' => 0, 'd22-05b' => 2]);
    $admin = icTeacher('ADMIN', 'admin');
    $get = fn () => collect(test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal'])->assertOk()->json('rows'))->firstWhere('group', 'd22-05a')['blocks'][0];
    expect($get()['conflict_level'])->toBe('red');
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-25 09:00', 'Asia/Tashkent'));
    $b = $get();
    expect($b['conflict'])->toBeNull()->and($b['conflict_level'])->toBeNull()->and($b['conflicts'][0]['past'])->toBeTrue();
});

it('haftalik: /conflicts?items=1&week=N — shu hafta tuzilgan ro\'yxati (o\'tgani past), oddiy so\'rov — faqat kelgusi matn', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 3, 'settings' => ['semester_start' => '2026-09-14']]);
    $t = icTeacher('USTOZ');
    $mk = fn (string $g, string $room) => TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 3,
        'subject_name' => 'Anatomiya', 'group_name' => $g, 'kafedra_name' => 'K', 'teacher_id' => $t->id, 'teacher_name' => 'USTOZ',
        'auditorium_code' => $room, 'auditorium_name' => $room, 'day' => 2, 'pair' => 3]);
    $a = $mk('d2-01a', '101');
    $mk('d2-01b', '102');
    $admin = icTeacher('ADMIN', 'admin');
    $get = fn (array $q = []) => test()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->getJson(route('admin.timetable.boards.conflicts', $board) . ($q ? '?' . http_build_query($q) : ''))->assertOk()->json();
    // Bugun 21.09: 1-hafta (15.09) o'tgan, 2-hafta (22.09) kelgusi.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-21 09:00', 'Asia/Tashkent'));
    $w1 = $get(['items' => 1, 'week' => 1]);
    expect($w1['week'])->toBe(1)->and(array_keys($w1['weekly_items'][$a->id]))->toBe([1])
        ->and($w1['weekly_items'][$a->id][1][0])->toMatchArray(['kind' => 'teacher_busy', 'type' => 'teacher', 'teacher_id' => $t->id,
            'teacher_name' => 'USTOZ', 'groups' => ['d2-01b'], 'past' => true]);
    $w2 = $get(['items' => 1, 'week' => 2])['weekly_items'][$a->id][2][0];
    expect($w2)->toMatchArray(['kind' => 'teacher_busy', 'past' => false, 'date_from' => '2026-09-22', 'date_to' => '2026-09-22'])
        ->and($w2['detail'])->toContain('USTOZ');
    // Oddiy so'rov: faqat kelgusi haftalar matni.
    expect(array_keys($get()['weekly'][$a->id]))->toBe([2, 3]);
});

it('cycle-week: tanlangan haftadagi sikl darslari — ma\'ruza bir marta (oqim guruhlari bilan), amaliy kun rejasi bo\'yicha, so\'rash huquqi, hafta chegarasi', function () {
    $hall = Auditorium::create(['code' => 'ZAL', 'name' => 'Zal', 'volume' => 15, 'active' => true]);
    $lector = icTeacher('LEKTOR', 'oqituvchi');
    $ustoz = icTeacher('USTOZ', 'oqituvchi');
    $s = icLectureSetup($lector, $ustoz, $hall);
    // Qo'ng'iroqlar — 40 daqiqalik yarim paralar: panjara qatori = akademik soat.
    $s['board']->update(['bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-soat',
        'start' => sprintf('%02d:00', 8 + $i), 'end' => sprintf('%02d:40', 8 + $i)], range(0, 11))]);
    $admin = icTeacher('ADMIN', 'admin');
    $week = fn ($who, string $role, array $body) => test()->withoutVite()->actingAs($who, 'teacher')->withSession(['active_role' => $role])
        ->postJson(route('admin.timetable.cycle-week', $s['board']), $body)->assertOk()->json();
    $all = collect($week($admin, 'admin', ['week' => 1])['lessons']);
    // Ma'ruzalar: 1-oqim 14.09 va 15.09 (05a + 05b birga, bitta dars), 2-oqim 15.09 va 16.09 (06a) — LEKTOR, Zal.
    $lec = $all->where('training_type', 'lecture')->values();
    expect($lec->map(fn ($l) => [$l['date'], $l['flow'], $l['group_names'], $l['day'], $l['pair'], $l['len_half']])->all())->toBe([
        ['2026-09-14', 'F1 / 1-oqim', ['d22-05a', 'd22-05b'], 1, 1, 2],
        ['2026-09-15', 'F1 / 1-oqim', ['d22-05a', 'd22-05b'], 2, 1, 2],
        ['2026-09-15', 'F1 / 2-oqim', ['d22-06a'], 2, 1, 2],
        ['2026-09-16', 'F1 / 2-oqim', ['d22-06a'], 3, 1, 2],
    ])->and($lec[0])->toMatchArray(['cycle' => true, 'row_hours' => 1, 'teacher_id' => $lector->id, 'teacher_name' => 'LEKTOR', 'teacher_full_name' => 'LEKTOR', 'auditorium_name' => 'Zal',
        'auditorium_volume' => 15, 'subject_name' => 'Klinik farmakologiya', 'ask' => false]);
    // Fan kesimi: barcha Klinik farmakologiya guruhlari.
    expect($all->pluck('group_name')->unique()->sort()->values()->all())->toBe(['d22-05a', 'd22-05b', 'd22-06a']);
    // 05a amaliyoti: 14.09 — ma'ruzadan keyin 3-soatdan 4 soat; 16.09 — 1-soatdan 6 soat (ma'ruza yo'q).
    $p05a = $all->where('training_type', 'practice')->where('group_name', 'd22-05a')->values();
    expect($p05a->map(fn ($l) => [$l['date'], $l['pair'], $l['len_half']])->all())->toBe([
        ['2026-09-14', 3, 4], ['2026-09-15', 3, 4], ['2026-09-16', 1, 6], ['2026-09-17', 1, 6], ['2026-09-18', 1, 6],
    ])->and($p05a[0])->toMatchArray(['teacher_id' => $ustoz->id, 'key' => 'davolash ishi|5|d22-05a|Klinik farmakologiya|practice'])
        ->and($p05a[0]['dp'])->toMatchArray(['k' => 1, 'lec' => 2, 'prc' => 4, 'start' => 1, 'ps' => 3]);
    // So'rash: o'qituvchining o'z amaliy darsi (ertadan); boshqalarniki va ma'ruza — yo'q.
    $mine = collect($week($ustoz, 'oqituvchi', ['week' => 1])['lessons']);
    expect($mine->where('group_name', 'd22-05a')->where('training_type', 'practice')->pluck('ask')->unique()->all())->toBe([true])
        ->and($mine->where('group_name', 'd22-05b')->pluck('ask')->unique()->all())->toBe([false])
        ->and($mine->where('training_type', 'lecture')->pluck('ask')->unique()->all())->toBe([false])
        // Ma'ruzachining o'z ma'ruzasi ham — so'rash faqat amaliy dars uchun.
        ->and(collect($week($lector, 'oqituvchi', ['week' => 1])['lessons'])->where('teacher_id', $lector->id)->pluck('ask')->unique()->all())->toBe([false]);
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-16 09:00', 'Asia/Tashkent'));
    expect(collect($week($ustoz, 'oqituvchi', ['week' => 1])['lessons'])->where('group_name', 'd22-05a')->where('training_type', 'practice')
        ->mapWithKeys(fn ($l) => [$l['date'] => $l['ask']])->all())
        ->toBe(['2026-09-14' => false, '2026-09-15' => false, '2026-09-16' => false, '2026-09-17' => true, '2026-09-18' => true]);
    // To'liq 80 daqiqalik paralar (1 para = 2 soat): panjara qatoriga aylantiriladi, asl soatlar — hour / hours.
    $s['board']->update(['bell_schedule' => \App\Models\TimetableBoard::defaultBellSchedule(6)]);
    $p = collect($week($admin, 'admin', ['week' => 1])['lessons'])->where('group_name', 'd22-05a')->where('date', '2026-09-14')->values();
    expect($p->map(fn ($l) => [$l['training_type'], $l['pair'], $l['len_half'], $l['hour'], $l['hours'], $l['row_hours']])->all())
        ->toBe([['lecture', 1, 1, 1, 2, 2], ['practice', 2, 2, 3, 4, 2]]);
    // 2-hafta: 1-oqim bloki tugagan (14–18.09), 2-oqim — 15–19.09: darslar yo'q.
    expect($week($admin, 'admin', ['week' => 2])['lessons'])->toBe([])
        // Qamrov (kurs) va tashqi qatlam — bo'sh.
        ->and($week($admin, 'admin', ['week' => 1, 'courses' => [4]])['lessons'])->toBe([])
        ->and($week($admin, 'admin', ['week' => 1, 'layer' => 'external']))->toMatchArray(['lessons' => []]);
});

it('to\'qnashuvlar: guruh bandligi qamrov bo\'yicha — boshqa yo\'nalishdagi bir xil nomli guruhcha (Codex)', function () {
    $s = icSetup(['d22-05a' => [icTeacher('USTOZ'), null, 10]], ['d22-05a' => 0]);
    // Pediatriyada ham "d22-05a" (nom fakultet/yo'nalishlar bo'ylab takrorlanadi) — shu kunlarda boshqa sikl fani, boshqa o'qituvchi.
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'pediatriya', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $card = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'pediatriya', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Pediatriya', 'group_name' => 'd22-05a', 'kafedra_name' => 'Pediatriya kafedrasi',
        'teacher_id' => icTeacher('BOSHQA')->id, 'teacher_name' => 'BOSHQA']);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'pediatriya', 'course' => 5,
        'group_name' => 'd22-05a', 'subject_name' => 'Pediatriya', 'start_index' => 0, 'training_type' => 'practice']);
    expect(icConflicts($s['board']))->toBe([]);
    // O'sha yo'nalishda (davolash ishi) bo'lsa — guruh band.
    $card->update(['specialty_name' => 'davolash ishi']);
    TimetableSubjectSetting::where('subject_name', 'Pediatriya')->update(['specialty_name' => 'davolash ishi']);
    TimetableCyclePlacement::where('subject_name', 'Pediatriya')->update(['specialty_name' => 'davolash ishi']);
    $c = icConflicts($s['board']);
    expect(collect($c)->flatMap(fn ($x) => array_column($x['items'], 'kind'))->unique()->values()->all())->toBe(['group_busy']);
});

it('cycle-week: umumiy "A + B" amaliy kartasi — har guruhcha birligida takrorlanmaydi, bitta dars (Codex)', function () {
    $t = icTeacher('USTOZ', 'oqituvchi');
    $s = icSetup(['d22-05a + d22-05b' => [$t, null, 20]], ['F1 / 1-oqim' => 0]);
    $res = collect(test()->withoutVite()->actingAs(icTeacher('ADMIN', 'admin'), 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-week', $s['board']), ['week' => 1])->assertOk()->json('lessons'));
    expect($res)->toHaveCount(5)
        ->and($res->pluck('date')->all())->toBe(['2026-09-14', '2026-09-15', '2026-09-16', '2026-09-17', '2026-09-18'])
        ->and($res->pluck('group_names')->unique()->values()->all())->toBe([['d22-05a', 'd22-05b']])
        ->and($res->pluck('id')->unique()->values()->all())->toBe([$s['cards']['d22-05a + d22-05b']->id]);
});
