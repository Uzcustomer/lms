<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

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
    $s = icSetup(['d22-05a' => [$sharipov, null, 10], 'd22-05b' => [$other, null, 10]], ['d22-05a' => 0, 'd22-05b' => 2]);
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
