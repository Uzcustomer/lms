<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TeacherNotification;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableChangeRequest;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// O'qituvchi to'qnashuvdagi (yoki joylanmagan) darsini toza joyga so'rovsiz joylaydi (TimetableTeacherConflictFix) va bugungi darsni
// ko'chirish huquqi (TimetableStaffToday): admin / registrator ofisi — bugun ochiq, boshqalar — yopiq, o'tgan kun — hamma uchun yopiq.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-16 09:00', 'Asia/Tashkent')));

function tfTeacher(string $name, ?string $role = null, array $extra = []): Teacher
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

function tfAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/**
 * Haftalik: 19.11.2026 (payshanba) — SOTTOROV (o'qituvchi) "Farmakologiya" pp/2024-01b (A) va pp/2024-01a (B) — ikkalasi IV parada
 * (o'qituvchi band — qizil). Seshanba 17.11 I para — bo'sh. Sikl: "Klinik farmakologiya" d1/d22-01b (SOTTOROV) 19.11 dan 5 kun, I–III para;
 * juma va shanba II parada SOTTOROVning haftalik darsi (pp/2024-02a) — amaliy shu kunlarda qizil to'qnashuvda.
 */
function tfSetup(bool $cycle = false): array
{
    Role::findOrCreate('admin', 'web');
    DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Farmakologiya kafedrasi', 'code' => 'F1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $t = tfTeacher('SOTTOROV S.', 'oqituvchi');
    $head = tfTeacher('MUDIR M.', 'kafedra_mudiri', ['department_hemis_id' => 501]);
    $admin = tfTeacher('ADMIN A.', 'admin');
    $reg = tfTeacher('REGISTRATOR R.', 'registrator_ofisi');
    $other = tfTeacher('BOSHQA B.', 'oqituvchi');
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    $r1 = Auditorium::create(['code' => 'R1', 'name' => '101-xona', 'volume' => 30, 'active' => true]);
    $r2 = Auditorium::create(['code' => 'R2', 'name' => '102-xona', 'volume' => 30, 'active' => true]);
    $r3 = Auditorium::create(['code' => 'R3', 'name' => '103-xona', 'volume' => 30, 'active' => true]);
    $weekly = fn (string $g, Teacher $who, ?int $day, ?int $pair, ?Auditorium $room, array $extra = []) => TimetableCard::create(array_merge([
        'board_id' => $board->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya', 'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz',
        'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Farmakologiya', 'group_name' => $g,
        'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $who->id, 'teacher_name' => $who->full_name, 'day' => $day, 'pair' => $pair,
        'auditorium_code' => $room?->code, 'auditorium_name' => $room?->name], $extra));
    $a = $weekly('pp/2024-01b', $t, 4, 7, $r1);
    $b = $weekly('pp/2024-01a', $t, 4, 7, $r2);
    $ctl = app(TimetableController::class);
    $week = collect(range(1, 15))->first(fn ($w) => $ctl->weeklyProposalDates($a, ['week' => $w, 'day' => 4])[1] === '2026-11-19');
    $s = compact('board', 't', 'head', 'admin', 'reg', 'other', 'a', 'b', 'r1', 'r2', 'r3', 'week', 'weekly');
    if ($cycle) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-01b', 'kafedra_name' => 'Farmakologiya kafedrasi',
            'teacher_id' => $t->id, 'teacher_name' => $t->full_name]);
        [, [$dates, $work]] = (new ReflectionMethod($ctl, 'cycleInternalUnits'))->invoke($ctl, $board);
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd1/d22-01b',
            'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice',
            'start_index' => collect($work)->first(fn ($i) => $dates[$i]->toDateString() === '2026-11-19')]);
        // Juma (20.11) va shanba (21.11) II parada — SOTTOROVning haftalik darsi: amaliy (I–III para) bilan o'qituvchi band.
        $weekly('pp/2024-02a', $t, 5, 3, $r3);
        $weekly('pp/2024-02b', $t, 6, 3, $r3);
    }

    return $s;
}

function tfAgenda(array $s, Teacher $as, string $role, array $q = []): array
{
    return tfAs($as, $role)->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?' . http_build_query($q + [
        'teacher_id' => $s['t']->id, 'date' => '2026-11-19', 'range' => 'week']))->assertOk()->json();
}

function tfMove(array $s, TimetableCard $c, int $day, int $pair, array $extra = []): array
{
    return ['week' => $s['week'], 'card_id' => $c->id, 'type' => 'agenda', 'title' => "O'qituvchi jadvalidan ko'chirish",
        'actions' => [['op' => 'move', 'card_id' => $c->id, 'week' => $s['week'], 'day' => $day, 'pair' => $pair] + $extra]];
}

function tfApply(array $s, array $body, ?Teacher $as = null, string $role = 'oqituvchi')
{
    return tfAs($as ?? $s['t'], $role)->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body);
}

it('haftalik: qizil to\'qnashuvdagi dars toza paraga — so\'rovsiz joylanadi, tarixda belgi, kafedra mudiriga xabar; agenda apply_fix', function () {
    $s = tfSetup();
    $key = 'w|' . $s['a']->id . '|' . $s['week'];
    $j = tfAgenda($s, $s['t'], 'oqituvchi', ['move' => $key]);
    // Seshanba 17.11 I para — toza (fix); IV para to'qnashuvi qizil.
    expect($j['move']['via'])->toBe('apply_fix')
        ->and($j['move']['options']['2026-11-17'][1])->toMatchArray(['ok' => true, 'why' => null, 'room' => null, 'fix' => true])
        ->and(collect($j['entries'])->firstWhere('key', $key))->toMatchArray(['conflict' => true, 'movable' => true, 'movable_why' => null]);
    // Boshqa xona bilan (asl xona bo'sh bo'lsa ham) — xona taklifi = ogohlantirish: so'rov orqali.
    tfApply($s, tfMove($s, $s['a'], 2, 1, ['auditorium_code' => 'R3']))->assertForbidden()
        ->assertJsonPath('why', 'Boshqa xona bilan ko‘chirish — so‘rov orqali.');
    $r = tfApply($s, tfMove($s, $s['a'], 2, 1))->assertOk()->json();
    expect($r)->toMatchArray(['ok' => true, 'fixed' => true])->and($r['notified'])->toBe(['MUDIR M.']);
    $ov = TimetableCardOverride::where('card_id', $s['a']->id)->where('week', $s['week'])->first();
    expect([$ov->day, $ov->pair])->toBe([2, 1])
        ->and(TimetableChangeRequest::where('status', 'pending')->count())->toBe(0);
    $hist = TimetableChangeRequest::where('status', 'applied')->sole();
    expect($hist->requester_role)->toBe('oqituvchi')->and($hist->summary)->toContain("To'qnashuvni o'qituvchi hal qildi")
        ->and($hist->details['flags'])->toBe(["To'qnashuvni o'qituvchi hal qildi"]);
    $n = TeacherNotification::where('teacher_id', $s['head']->id)->sole();
    expect($n->title)->toContain("To'qnashuvni o'qituvchi hal qildi")->and($n->message)->toContain('SOTTOROV S.')->toContain('Farmakologiya');
});

it('haftalik: joylanmagan dars — toza joyga so\'rovsiz; toza joylashgan va faqat sariq (xona band) dars — 403, so\'rov orqali', function () {
    $s = tfSetup();
    // Joylanmagan karta (shablonda joysiz).
    $u = $s['weekly']('pp/2024-03a', $s['t'], null, null, $s['r3']);
    tfApply($s, tfMove($s, $u, 2, 3))->assertOk()->assertJsonPath('fixed', true);
    expect(TimetableCardOverride::where('card_id', $u->id)->where('week', $s['week'])->value('pair'))->toBe(3);
    // Toza joylashgan dars (B ni boshqa kunga olib, A to'qnashuvsiz) — mijoz "fix" deb yuborsa ham server rad etadi.
    $s['b']->update(['day' => 3]);
    $key = 'w|' . $s['a']->id . '|' . $s['week'];
    expect(tfAgenda($s, $s['t'], 'oqituvchi', ['move' => $key])['move']['via'])->toBe('propose');
    tfApply($s, tfMove($s, $s['a'], 2, 1) + ['fix' => true])->assertForbidden()
        ->assertJsonPath('error', "Joylashgan darsni o'zgartirish — so'rov orqali.")
        ->assertJsonPath('why', fn ($w) => str_contains($w, "joylashgan va to'qnashuvsiz"));
    // Sariq to'qnashuv (boshqa o'qituvchi shu xonada, boshqa guruh) — "to'qnashuvdagi dars" emas.
    $s['weekly']('pp/2024-09a', $s['other'], 4, 7, $s['r1']);
    expect(tfAgenda($s, $s['t'], 'oqituvchi', ['move' => $key])['move']['via'])->toBe('propose');
    tfApply($s, tfMove($s, $s['a'], 2, 1))->assertForbidden();
    // So'rov — avvalgidek.
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), tfMove($s, $s['a'], 2, 1) + ['reason' => 'TEST — sinov'])
        ->assertOk()->assertJsonPath('pending_approval', true);
    expect(TimetableCardOverride::where('card_id', $s['a']->id)->exists())->toBeFalse();
});

it('haftalik: to\'qnashuvdagi dars, yangi joy muammoli (xona band — boshqa xona taklifi) — fix emas, 403; so\'rov yaratiladi', function () {
    $s = tfSetup();
    // Seshanba II parada 101-xona (A ning xonasi) band — boshqa xona taklif qilinadi (ogohlantirish).
    $s['weekly']('pp/2024-09a', $s['other'], 2, 3, $s['r1']);
    $j = tfAgenda($s, $s['t'], 'oqituvchi', ['move' => 'w|' . $s['a']->id . '|' . $s['week']]);
    $opt = $j['move']['options']['2026-11-17'][2];
    expect($j['move']['via'])->toBe('apply_fix')->and($opt['ok'])->toBeTrue()->and($opt['room'])->not->toBeNull()->and($opt['fix'])->toBeFalse();
    tfApply($s, tfMove($s, $s['a'], 2, 3, ['auditorium_code' => $opt['room']['code']]))->assertForbidden();
    tfApply($s, tfMove($s, $s['a'], 2, 3))->assertForbidden()->assertJsonPath('why', fn ($w) => str_contains($w, 'Yangi joy toza emas'));
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']),
        tfMove($s, $s['a'], 2, 3, ['auditorium_code' => $opt['room']['code']]) + ['reason' => 'TEST — sinov'])->assertOk()->assertJsonPath('pending_approval', true);
    expect(TimetableCardOverride::where('card_id', $s['a']->id)->exists())->toBeFalse();
});

it('haftalik: kutilayotgan so\'rovi bor karta, ma\'ruza, boshqaning darsi va bugungi dars — darhol joylash ham rad', function () {
    $s = tfSetup();
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), tfMove($s, $s['a'], 2, 3) + ['reason' => 'TEST — sinov'])
        ->assertOk();
    $req = TimetableChangeRequest::where('status', 'pending')->sole();
    tfApply($s, tfMove($s, $s['a'], 2, 1))->assertUnprocessable()
        ->assertJsonPath('error', fn ($m) => str_contains($m, "Bu kartaga allaqachon so'rov bor (#{$req->id}"));
    // Ma'ruza (o'qituvchi band — qizil bo'lsa ham).
    $lec = $s['weekly']('pp/2024-01', $s['t'], 4, 7, null, ['training_type' => 'lecture']);
    tfApply($s, tfMove($s, $lec, 2, 1))->assertForbidden()->assertJsonPath('error', fn ($m) => str_contains($m, "Ma'ruzani surish"));
    // Boshqa o'qituvchining darsi.
    $foreign = $s['weekly']('pp/2024-07a', $s['other'], 4, 7, $s['r3']);
    tfApply($s, tfMove($s, $foreign, 2, 1))->assertForbidden();
    // Bugungi dars (19.11) — o'qituvchiga yopiq.
    $this->travelTo(Carbon::parse('2026-11-19 08:00', 'Asia/Tashkent'));
    tfApply($s, tfMove($s, $s['b'], 6, 1))->assertUnprocessable()
        ->assertJsonPath('error', fn ($m) => str_contains($m, "Bugungi darsni faqat admin va registrator ofisi ko'chiradi"));
    expect(TimetableCardOverride::where('card_id', $s['b']->id)->exists())->toBeFalse();
});

it('bugungi dars: rol × (bugun boshlanmagan, bugun boshlangan, kecha) — admin / registrator ofisi bugun ko\'chiradi, boshqalar yo\'q', function (string $when) {
    $s = tfSetup();
    $s['b']->update(['day' => 3]);   // A — to'qnashuvsiz
    $this->travelTo(Carbon::parse(['before' => '2026-11-19 09:00', 'started' => '2026-11-19 14:00', 'yesterday' => '2026-11-20 09:00'][$when], 'Asia/Tashkent'));
    $key = 'w|' . $s['a']->id . '|' . $s['week'];
    $past = $when === 'yesterday';
    foreach ([[$s['admin'], 'admin'], [$s['reg'], 'registrator_ofisi']] as [$who, $role]) {
        $m = tfAgenda($s, $who, $role, ['move' => $key])['move'];
        expect($m['why'])->toBe($past ? "O'tgan kun darsi ko'chirilmaydi." : null)->and($m['via'])->toBe($past ? null : 'apply');
        if (!$past) {
            // Bugunning istalgan parasi (o'tib ketgan I para ham) — variant bor.
            expect($m['options']['2026-11-19'][1]['ok'])->toBeTrue();
        }
    }
    foreach ([[$s['t'], 'oqituvchi'], [$s['head'], 'kafedra_mudiri']] as [$who, $role]) {
        expect(tfAgenda($s, $who, $role, ['move' => $key])['move']['why'])->toBe($past ? "Bugungi va o'tgan darslar ko'chirilmaydi."
            : "Bugungi va o'tgan darslar ko'chirilmaydi. Bugungi darsni faqat admin va registrator ofisi ko'chiradi.");
    }
    // Server: admin — bugun (I paraga ham) qo'llanadi, kecha — rad; kafedra mudiri so'rovi — bugun ham rad.
    $r = tfApply($s, tfMove($s, $s['a'], 4, 1), $s['reg'], 'registrator_ofisi');
    if ($past) {
        $r->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, "O'tgan kunga tegadi"));
    } else {
        $r->assertOk()->assertJsonPath('today_moved', true);
        expect(TimetableChangeRequest::where('status', 'applied')->sole()->summary)->toContain('bugungi dars ko‘chirildi');
    }
    tfAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), tfMove($s, $s['a'], 4, 11) + ['reason' => 'TEST — sinov'])
        ->assertUnprocessable();
})->with(['before', 'started', 'yesterday']);

it('xodim tasdiqlaganda bugun to\'siq emas: o\'qituvchi so\'rovi kechikib bugunga to\'g\'ri kelsa — registrator qo\'llaydi, tarixda belgi', function () {
    $s = tfSetup();
    $s['b']->update(['day' => 3]);
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), tfMove($s, $s['a'], 4, 11) + ['reason' => 'TEST — sinov'])
        ->assertOk();
    $req = TimetableChangeRequest::where('status', 'pending')->sole();
    tfAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $this->travelTo(Carbon::parse('2026-11-19 15:00', 'Asia/Tashkent'));   // dars kuni, dars boshlangan
    tfAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved')->and($req->fresh()->summary)->toContain('bugungi dars ko‘chirildi')
        ->and(TimetableCardOverride::where('card_id', $s['a']->id)->where('week', $s['week'])->value('pair'))->toBe(11);
});

it('sikl: aralash kunlar — qizil to\'qnashuvdagi kunlar darhol (applied_days), qolgani bitta so\'rov; tarix va mudirga xabar', function () {
    $s = tfSetup(true);
    $body = ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-01b',
        'dates' => ['2026-11-20', '2026-11-21', '2026-11-23'], 'start_hour' => 7, 'reason' => 'TEST — sinov'];
    $r = tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $body)->assertOk()->json();
    expect($r['applied_days'])->toBe(['2026-11-20', '2026-11-21'])->and($r['proposed_request_id'])->not->toBeNull()
        ->and($r['message'])->toContain('2 kun darhol joylandi, 1 kun — so‘rov #' . $r['proposed_request_id']);
    $req = TimetableChangeRequest::find($r['proposed_request_id']);
    expect($req->status)->toBe('pending')->and($req->payload['dates'])->toBe(['2026-11-23'])->and($req->approval_stage)->toBe('department');
    // Darhol yozilgan kunlar: 20.11 (2-kun), 21.11 (3-kun) — 7-soatdan; 23.11 — o'zgarmagan.
    $slots = TimetableCyclePlacement::where('group_name', 'd1/d22-01b')->value('lecture_slots');
    expect($slots['ps_g']['d1/d22-01b'] ?? [])->toBe([1 => 7, 2 => 7]);
    $hist = TimetableChangeRequest::where('status', 'applied')->sole();
    expect($hist->summary)->toContain("To'qnashuvni o'qituvchi hal qildi")->and($hist->requester_role)->toBe('oqituvchi');
    expect(TeacherNotification::where('teacher_id', $s['head']->id)->where('title', 'like', "%To'qnashuvni o'qituvchi hal qildi%")->count())->toBe(1);
});

it('sikl: hamma kun to\'qnashuvda — sababsiz ham hammasi darhol; to\'qnashuvsiz kun — sababsiz 422; ma\'ruza va kutilayotgan so\'rov — so\'rov', function () {
    $s = tfSetup(true);
    $base = ['specialty_name' => 'davolash ishi', 'course' => 5, 'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-01b', 'start_hour' => 7];
    // To'qnashuvsiz kun, sababsiz — 422, hech narsa yozilmaydi.
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $base + ['dates' => ['2026-11-20', '2026-11-23']])
        ->assertUnprocessable()->assertJsonValidationErrors('reason');
    expect(TimetableCyclePlacement::where('group_name', 'd1/d22-01b')->value('lecture_slots'))->toBeNull();
    // Kartada kutilayotgan so'rov — darhol yo'q (hammasi so'rov).
    $pending = tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']),
        $base + ['dates' => ['2026-11-24'], 'reason' => 'TEST — sinov'])->assertOk()->json();
    expect($pending['applied_days'])->toBe([]);
    $r = tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $base + ['dates' => ['2026-11-20'], 'reason' => 'TEST — sinov'])
        ->assertOk()->json();
    expect($r['applied_days'])->toBe([])->and($r['proposed_request_id'])->not->toBeNull();
    TimetableChangeRequest::where('status', 'pending')->update(['status' => 'cancelled']);
    // Hamma kun to'qnashuvda — sababsiz, so'rov yaratilmaydi.
    $r = tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), $base + ['dates' => ['2026-11-20', '2026-11-21']])
        ->assertOk()->json();
    expect($r)->toMatchArray(['applied_days' => ['2026-11-20', '2026-11-21'], 'proposed_request_id' => null])
        ->and(TimetableChangeRequest::where('status', 'pending')->count())->toBe(0);
    // Agenda: to'qnashuvdagi kun — apply_fix, toza soat — fix.
    $m = tfAgenda($s, $s['t'], 'oqituvchi', ['date' => '2026-11-24', 'range' => 'day', 'move' => collect(tfAgenda($s, $s['t'], 'oqituvchi',
        ['date' => '2026-11-24', 'range' => 'day'])['entries'])->firstWhere('kind', 'cycle')['key']])['move'];
    expect($m['via'])->toBe('propose');   // 24.11 — to'qnashuvsiz
});

it('haftalik: guruh band (boshqa o\'qituvchi darsi bilan) — qizil, so\'rovsiz; kafedra mudiri roli bor o\'qituvchi boshqaning darsini — yo\'q', function () {
    $s = tfSetup();
    $s['b']->update(['day' => 3]);
    // A guruhi (pp/2024-01b) shu vaqtda boshqa o'qituvchida — guruh band.
    $s['weekly']('pp/2024-01b', $s['other'], 4, 7, $s['r3'], ['subject_name' => 'Anatomiya']);
    expect(tfAgenda($s, $s['t'], 'oqituvchi', ['move' => 'w|' . $s['a']->id . '|' . $s['week']])['move']['via'])->toBe('apply_fix');
    tfApply($s, tfMove($s, $s['a'], 2, 1))->assertOk()->assertJsonPath('fixed', true);
    // O'qituvchi + kafedra mudiri rollari (faol — o'qituvchi): o'z kafedrasidagi boshqa o'qituvchining to'qnashuvdagi darsi — so'rovsiz emas.
    $both = tfTeacher('IKKI ROL', 'oqituvchi', ['department_hemis_id' => 501]);
    Role::findOrCreate('kafedra_mudiri', 'web');
    $both->assignRole('kafedra_mudiri');
    $x = $s['weekly']('pp/2024-05a', $s['other'], 5, 7, $s['r1']);
    $s['weekly']('pp/2024-05b', $s['other'], 5, 7, $s['r2']);
    tfApply($s, tfMove($s, $x, 2, 5), $both)->assertForbidden()->assertJsonPath('why', 'Faqat o‘z darsingizni so‘rovsiz joylay olasiz.');
    expect(TimetableCardOverride::where('card_id', $x->id)->exists())->toBeFalse();
});

it('haftalik o\'qituvchi so\'rovi (time-proposal): registrator dars kuni (bugun) tasdiqlasa — qo\'llanadi', function () {
    $s = tfSetup();
    $s['b']->update(['day' => 3]);
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal', $s['a']), ['week' => $s['week'], 'day' => 6, 'pair' => 1,
        'reason' => 'TEST — sinov'])->assertOk();
    $req = TimetableChangeRequest::where('status', 'pending')->sole();
    tfAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $this->travelTo(Carbon::parse('2026-11-19 10:00', 'Asia/Tashkent'));   // eski sana — bugun
    tfAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved')
        ->and(TimetableCardOverride::where('card_id', $s['a']->id)->where('week', $s['week'])->value('day'))->toBe(6);
});

it('sikl: so\'rov qoladigan kunlar bor, lekin kafedra mudiri topilmasa — darhol kunlar ham yozilmaydi (422)', function () {
    $s = tfSetup(true);
    $s['head']->removeRole('kafedra_mudiri');
    tfAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cycle-time-proposal', $s['board']), ['specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'group_name' => 'd1/d22-01b', 'dates' => ['2026-11-20', '2026-11-23'], 'start_hour' => 7,
        'reason' => 'TEST — sinov'])->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'kafedra mudiri topilmadi'));
    expect(TimetableCyclePlacement::where('group_name', 'd1/d22-01b')->value('lecture_slots'))->toBeNull()
        ->and(TimetableChangeRequest::count())->toBe(0);
});

it('Codex: ko\'p kartali amaliy blok (to\'qnashuvdagi) butunlay toza joyga — so\'rovsiz; blokning bir qismini ajratib — yo\'q', function () {
    $s = tfSetup();
    // Ikki bo'lakli amaliy blok (pp/2024-04a, payshanba IV–V para); I bo'lak SOTTOROVning B darsi bilan bir vaqtda — o'qituvchi band (qizil).
    $p1 = $s['weekly']('pp/2024-04a', $s['t'], 4, 7, $s['r3'], ['subject_name' => 'Biologiya']);
    $p2 = $s['weekly']('pp/2024-04a', $s['t'], 4, 9, $s['r3'], ['subject_name' => 'Biologiya']);
    $both = ['week' => $s['week'], 'card_id' => $p1->id, 'actions' => [
        ['op' => 'move', 'card_id' => $p1->id, 'week' => $s['week'], 'day' => 2, 'pair' => 1],
        ['op' => 'move', 'card_id' => $p2->id, 'week' => $s['week'], 'day' => 2, 'pair' => 3]]];
    // Faqat bitta bo'lak — blok bo'linadi: rad.
    tfApply($s, ['week' => $s['week'], 'card_id' => $p1->id, 'actions' => [$both['actions'][0]]])->assertForbidden()
        ->assertJsonPath('why', fn ($w) => str_contains($w, "blokning bir qismi"));
    // Ikkala bo'lak bir kunga, lekin oraliq bilan (I va III para) — yakuniy holatda blok bo'linadi: rad.
    $gap = $both;
    $gap['actions'][1]['pair'] = 5;
    tfApply($s, $gap)->assertForbidden()->assertJsonPath('why', fn ($w) => str_contains($w, "amaliy soatlar bo'linadi"));
    // Butun blok seshanba I–II paraga — toza: so'rovsiz.
    tfApply($s, $both)->assertOk()->assertJsonPath('fixed', true);
    expect(TimetableCardOverride::where('week', $s['week'])->whereIn('card_id', [$p1->id, $p2->id])->orderBy('card_id')->get(['day', 'pair'])->toArray())
        ->toBe([['day' => 2, 'pair' => 1], ['day' => 2, 'pair' => 3]]);
});
