<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableChangeRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

// Fan mas'uli (faol rol fan_masuli): mas'ul fani kartasini HAFTALIK ichki jadvalda shu haftaga ko'chirish (vaqt + xona) — to'qnashuvdagi
// (yoki joylanmagan) dars toza joyga darhol, to'qnashuvsiz dars — so'rov (kafedra mudiri → registrator ofisi); band joy — 422.
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-16 09:00', 'Asia/Tashkent')));

function rmTeacher(string $name, ?string $role = null, array $extra = []): Teacher
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

function rmAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

/** O'qituvchini fanga mas'ul qiladi (curriculum_subjects + teacher_responsible_subjects). */
function rmResponsible(Teacher $t, string $subject): void
{
    if (!DB::table('specialties')->where('specialty_hemis_id', 9001)->exists()) {
        DB::table('specialties')->insert(['specialty_hemis_id' => 9001, 'name' => 'Pediatriya', 'code' => '60910300', 'locality_type_code' => '1',
            'locality_type_name' => 'x', 'education_type_code' => '11', 'education_type_name' => 'B']);
        DB::table('curricula')->insert(['curricula_hemis_id' => 8001, 'name' => 'R', 'specialty_hemis_id' => 9001, 'department_hemis_id' => 1,
            'education_year_code' => '2026', 'education_year_name' => '2026-2027', 'current' => 1, 'education_type_code' => '11', 'education_type_name' => 'B',
            'education_form_code' => '11', 'education_form_name' => 'K', 'marking_system_code' => '11', 'marking_system_name' => 'M',
            'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4, 'semester_count' => 12, 'education_period' => 6]);
    }
    $csId = DB::table('curriculum_subjects')->insertGetId(['curriculum_subject_hemis_id' => random_int(10000, 99999), 'curricula_hemis_id' => 8001,
        'subject_id' => 1, 'subject_name' => $subject, 'subject_code' => 'F1', 'semester_code' => '13', 'semester_name' => '3-semestr', 'at_semester' => 1]);
    DB::table('teacher_responsible_subjects')->insert(['teacher_id' => $t->id, 'curriculum_subject_id' => $csId]);
}

/**
 * Haftalik "Farmakologiya" (2-kurs): A (pp/2024-01b) va B (pp/2024-01a) — bitta o'qituvchi SOTTOROV, payshanba 19.11 IV para (yarim
 * qator 7) — o'qituvchi band (qizil); C (pp/2024-01c, BOSHQA) — seshanba I para, to'qnashuvsiz. Fan mas'uli FM — "Farmakologiya".
 */
function rmSetup(): array
{
    Role::findOrCreate('admin', 'web');
    DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Farmakologiya kafedrasi', 'code' => 'F1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $t = rmTeacher('SOTTOROV S.', 'oqituvchi');
    $other = rmTeacher('BOSHQA B.', 'oqituvchi');
    $head = rmTeacher('MUDIR M.', 'kafedra_mudiri', ['department_hemis_id' => 501]);
    $reg = rmTeacher('REGISTRATOR R.', 'registrator_ofisi');
    $admin = rmTeacher('ADMIN A.', 'admin');
    $fm = rmTeacher('MASUL F.', 'fan_masuli', ['department' => 'Boshqa kafedra']);
    rmResponsible($fm, 'Farmakologiya');
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    $r1 = Auditorium::create(['code' => 'R1', 'name' => '101-xona', 'volume' => 30, 'active' => true]);
    $r2 = Auditorium::create(['code' => 'R2', 'name' => '102-xona', 'volume' => 30, 'active' => true]);
    $r3 = Auditorium::create(['code' => 'R3', 'name' => '103-xona', 'volume' => 30, 'active' => true]);
    $small = Auditorium::create(['code' => 'RS', 'name' => '104-xona', 'volume' => 5, 'active' => true]);
    $off = Auditorium::create(['code' => 'RX', 'name' => '105-xona', 'volume' => 30, 'active' => false]);
    $card = fn (string $g, Teacher $who, ?int $day, ?int $pair, ?Auditorium $room, array $extra = []) => TimetableCard::create(array_merge([
        'board_id' => $board->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya', 'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz',
        'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15, 'subject_name' => 'Farmakologiya', 'group_name' => $g,
        'kafedra_name' => 'Farmakologiya kafedrasi', 'teacher_id' => $who->id, 'teacher_name' => $who->full_name, 'day' => $day, 'pair' => $pair,
        'auditorium_code' => $room?->code, 'auditorium_name' => $room?->name], $extra));
    $a = $card('pp/2024-01b', $t, 4, 7, $r1);
    $b = $card('pp/2024-01a', $t, 4, 7, $r2);
    $c = $card('pp/2024-01c', $other, 2, 1, $r3);
    $ctl = app(TimetableController::class);
    $week = collect(range(1, 15))->first(fn ($w) => $ctl->weeklyProposalDates($a, ['week' => $w, 'day' => 4])[1] === '2026-11-19');

    return compact('board', 't', 'other', 'head', 'reg', 'admin', 'fm', 'a', 'b', 'c', 'r1', 'r2', 'r3', 'small', 'off', 'week', 'card');
}

function rmCheck(array $s, TimetableCard $card, array $body, ?Teacher $who = null, string $role = 'fan_masuli')
{
    return rmAs($who ?? $s['fm'], $role)->postJson(route('admin.timetable.cards.responsible-move.check', $card), $body + ['week' => $s['week']]);
}

function rmMove(array $s, TimetableCard $card, array $body, ?Teacher $who = null, string $role = 'fan_masuli')
{
    return rmAs($who ?? $s['fm'], $role)->postJson(route('admin.timetable.cards.responsible-move', $card), $body + ['week' => $s['week']]);
}

it('to\'qnashuvdagi dars bo\'sh joy va bo\'sh xonaga — darhol; xona faqat shu haftada, shablon va karta xonasi o\'zgarmaydi; tarix', function () {
    $s = rmSetup();
    // A (o'qituvchi band) → seshanba 17.11, II para (yarim qator 3), 102-xona.
    $chk = rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertOk()->json();
    expect($chk['mode'])->toBe('fix')->and($chk['message'])->toContain("Darhol qo'llanadi")
        ->and($chk['new'])->toBe('Seshanba, 17.11.2026 10:00–11:20')
        ->and($chk['old'])->toContain('19.11.2026');
    // Xonalar: faol xonalar (nofaol 105 — yo'q); kichik 104 — ko'rinadi, tanlab bo'lmaydi (sig'maydi, raqamlar bilan); hozirgi xona (101) — birinchi.
    $rooms = collect($chk['rooms']);
    expect($rooms->pluck('code')->all())->not->toContain('RX')
        ->and($rooms->first()['code'])->toBe('R1')->and($rooms->first()['current'])->toBeTrue()
        ->and($rooms->firstWhere('code', 'R2')['free'])->toBeTrue()
        ->and($rooms->firstWhere('code', 'RS'))->toMatchArray(['free' => false, 'kind' => 'capacity', 'busy_items' => [],
            'cap' => ['volume' => 5, 'students' => 12, 'over' => 7]]);
    // Shu vaqtda band 103 (C) — bo'shlardan keyin, kim band qilgani bilan (tuzilgan ro'yxat).
    $chk1 = rmCheck($s, $s['a'], ['day' => 2, 'pair' => 1])->assertOk()->json();
    $r3 = collect($chk1['rooms'])->firstWhere('code', 'R3');
    expect($r3)->toMatchArray(['free' => false, 'kind' => 'busy'])->and($r3['busy'])->toContain('pp/2024-01c')
        ->and($r3['busy_items'][0])->toMatchArray(['teacher' => 'BOSHQA B.', 'teacher_full' => 'BOSHQA B.', 'groups' => ['pp/2024-01c'],
            'subject' => 'Farmakologiya', 'time' => '08:30–09:50', 'date' => '2026-11-17'])
        ->and(collect($chk1['rooms'])->reject(fn ($r) => $r['current'])->pluck('free')->all())
        ->toBe(collect($chk1['rooms'])->reject(fn ($r) => $r['current'])->pluck('free')->sortDesc()->values()->all());

    $res = rmMove($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertOk()->json();
    expect($res)->toMatchArray(['mode' => 'fix', 'fixed' => true]);
    $ov = TimetableCardOverride::where('card_id', $s['a']->id)->sole();
    expect([$ov->week, $ov->day, $ov->pair, $ov->auditorium_code])->toBe([$s['week'], 2, 3, 'R2'])
        ->and($s['a']->fresh()->only(['day', 'pair', 'auditorium_code']))->toBe(['day' => 4, 'pair' => 7, 'auditorium_code' => 'R1']);
    $hist = TimetableChangeRequest::where('status', TimetableChangeRequest::APPLIED)->sole();
    expect($hist->requester_role)->toBe('fan_masuli')->and($hist->summary)->toContain("To'qnashuvni o'qituvchi hal qildi (fan mas'uli)")
        ->and($hist->route_name)->toBe('admin.timetable.week-suggestions.apply');
});

it('to\'qnashuvsiz darsni ko\'chirish — so\'rov (sabab majburiy), jadval o\'zgarmaydi; kafedra mudiri va registrator tasdig\'idan keyin qo\'llanadi', function () {
    $s = rmSetup();
    $chk = rmCheck($s, $s['c'], ['day' => 3, 'pair' => 5, 'auditorium_code' => 'R2'])->assertOk()->json();
    expect($chk['mode'])->toBe('request')->and($chk['message'])->toContain('kafedra mudiri va registrator');
    rmMove($s, $s['c'], ['day' => 3, 'pair' => 5, 'auditorium_code' => 'R2'])->assertUnprocessable()->assertJsonValidationErrors('reason');
    $res = rmMove($s, $s['c'], ['day' => 3, 'pair' => 5, 'auditorium_code' => 'R2', 'reason' => 'Kafedra yig\'ilishi'])->assertOk()->json();
    expect($res)->toMatchArray(['mode' => 'request', 'pending_approval' => true]);
    $req = TimetableChangeRequest::where('status', TimetableChangeRequest::PENDING)->sole();
    expect([$req->route_name, $req->requester_role, $req->approval_stage])->toBe(['admin.timetable.week-suggestions.apply', 'fan_masuli', 'department'])
        ->and($req->payload['actions'][0])->toMatchArray(['op' => 'move', 'card_id' => $s['c']->id, 'day' => 3, 'pair' => 5, 'auditorium_code' => 'R2'])
        ->and(TimetableCardOverride::where('card_id', $s['c']->id)->exists())->toBeFalse();
    // Ikkinchi marta — kartada kutilayotgan so'rov bor.
    rmCheck($s, $s['c'], ['day' => 3, 'pair' => 7])->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "allaqachon so'rov bor"));
    rmAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->approval_stage)->toBe('registrar')->and(TimetableCardOverride::where('card_id', $s['c']->id)->exists())->toBeFalse();
    rmAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    $ov = TimetableCardOverride::where('card_id', $s['c']->id)->sole();
    expect($req->fresh()->status)->toBe('approved')->and([$ov->day, $ov->pair, $ov->auditorium_code])->toBe([3, 5, 'R2'])
        ->and($s['c']->fresh()->auditorium_code)->toBe('R3');
});

it('tasdiqlashda yangi joy band bo\'lib qolsa — qo\'llanmaydi', function () {
    $s = rmSetup();
    rmMove($s, $s['c'], ['day' => 3, 'pair' => 5, 'reason' => 'sabab'])->assertOk();
    $req = TimetableChangeRequest::where('status', TimetableChangeRequest::PENDING)->sole();
    // Shu orada boshqa dars shu guruh vaqtiga (guruh band).
    $s['card']('pp/2024-01c', rmTeacher('UCHINCHI U.'), 3, 5, $s['r1'], ['subject_name' => 'Anatomiya', 'kafedra_name' => 'Anatomiya kafedrasi']);
    rmAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect(TimetableCardOverride::where('card_id', $s['c']->id)->exists())->toBeFalse();
});

it('tasdiqlashda huquq qayta hisoblanadi: karta endi fan mas\'ulining mas\'ul fanida emas — qo\'llanmaydi', function () {
    $s = rmSetup();
    rmMove($s, $s['c'], ['day' => 3, 'pair' => 5, 'reason' => 'sabab'])->assertOk();
    $req = TimetableChangeRequest::where('status', TimetableChangeRequest::PENDING)->sole();
    DB::table('teacher_responsible_subjects')->where('teacher_id', $s['fm']->id)->delete();
    rmAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertStatus(422);
    expect($req->fresh()->status)->not->toBe('approved')->and(TimetableCardOverride::where('card_id', $s['c']->id)->exists())->toBeFalse();
});

it('band joy — 422 aniq sabab bilan (xona, o\'qituvchi, guruh); mas\'ul bo\'lmagan fan — 403; ma\'ruza — 403; boshqa rol — 403', function () {
    $s = rmSetup();
    // Xona band: C seshanba I para 103-xonada.
    $m = rmCheck($s, $s['a'], ['day' => 2, 'pair' => 1, 'auditorium_code' => 'R3'])->assertUnprocessable()->json();
    expect($m['mode'])->toBeNull()->and($m['message'])->toContain('Xona band');
    // O'qituvchi band: SOTTOROV payshanba IV parada B bilan — A ni B vaqtiga (boshqa xona bilan ham).
    $s['card']('pp/2024-01e', $s['t'], 5, 1, $s['r1']);
    expect(rmCheck($s, $s['a'], ['day' => 5, 'pair' => 1, 'auditorium_code' => 'R2'])->assertUnprocessable()->json('message'))->toContain("O'qituvchi band");
    // Guruh band: pp/2024-01b ning boshqa fani seshanba III parada.
    $s['card']('pp/2024-01b', $s['other'], 2, 5, $s['r1'], ['subject_name' => 'Anatomiya', 'kafedra_name' => 'Anatomiya kafedrasi']);
    expect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 5, 'auditorium_code' => 'R2'])->assertUnprocessable()->json('message'))->toContain('band');
    rmMove($s, $s['a'], ['day' => 2, 'pair' => 1, 'auditorium_code' => 'R3'])->assertUnprocessable();
    expect(TimetableCardOverride::where('card_id', $s['a']->id)->exists())->toBeFalse();
    // Mas'ul bo'lmagan fan.
    $ana = $s['card']('pp/2024-01f', $s['other'], 3, 1, $s['r1'], ['subject_name' => 'Anatomiya', 'kafedra_name' => 'Anatomiya kafedrasi']);
    rmCheck($s, $ana, ['day' => 3, 'pair' => 5])->assertForbidden();
    rmMove($s, $ana, ['day' => 3, 'pair' => 5, 'reason' => 'x'])->assertForbidden();
    // Ma'ruza — faqat ko'rish.
    $lec = $s['card']('', $s['other'], 6, 1, $s['r1'], ['training_type' => 'lecture', 'group_name' => null, 'group_names' => ['pp/2024-01a', 'pp/2024-01b']]);
    rmCheck($s, $lec, ['day' => 6, 'pair' => 5])->assertForbidden()->assertJsonPath('message', "Ma'ruzani surish — faqat kafedra mudiri va adminlar.");
    // O'qituvchi roli bu endpointga kira olmaydi.
    rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3], $s['t'], 'oqituvchi')->assertForbidden();
});

it('ko\'p bo\'lakli dars (2 + 2 soat) — yaxlit, ketma-ket ko\'chadi; joylanmagan dars — darhol', function () {
    $s = rmSetup();
    // Shablonda joylanmagan birlik: pp/2024-01g, BOSHQA, 2 + 2 soat.
    $p1 = $s['card']('pp/2024-01g', $s['other'], null, null, $s['r1']);
    $p2 = $s['card']('pp/2024-01g', $s['other'], null, null, $s['r1']);
    $chk = rmCheck($s, $p2, ['day' => 3, 'pair' => 1])->assertOk()->json();
    expect($chk['mode'])->toBe('fix')->and(array_column($chk['parts'], 'pair'))->toBe([1, 3])
        ->and($chk['new'])->toBe('Chorshanba, 18.11.2026 08:30–11:20');
    rmMove($s, $p2, ['day' => 3, 'pair' => 1])->assertOk()->assertJsonPath('mode', 'fix');
    $ovs = TimetableCardOverride::whereIn('card_id', [$p1->id, $p2->id])->orderBy('card_id')->get();
    expect($ovs->map(fn ($o) => [$o->card_id, $o->week, $o->day, $o->pair])->all())
        ->toBe([[$p1->id, $s['week'], 3, 1], [$p2->id, $s['week'], 3, 3]]);
    // Kun oxiridan oshsa — chegaradan tashqarida.
    expect(rmCheck($s, $p1, ['day' => 4, 'pair' => 11])->assertUnprocessable()->json('message'))->toContain('chegarasidan tashqarida');
});

it('xona: nofaol yoki sig\'maydigan — 422; xona bo\'sh qoldirilsa va hozirgi xona yaroqsiz — "Xona tanlang"; sariq to\'qnashuv (xona band) — darhol', function () {
    $s = rmSetup();
    // Nofaol xona — umumiy himoya (room_inactive) endpointgacha rad etadi.
    expect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'RX'])->assertUnprocessable()->json('error'))->toContain('nofaol');
    expect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'RS'])->assertUnprocessable()->json('message'))->toContain("Xona sig'maydi");
    $s['a']->update(['auditorium_code' => 'RX', 'auditorium_name' => '105-xona']);
    expect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->assertUnprocessable()->json('message'))->toBe('Xona tanlang: hozirgi xona 105-xona — nofaol.');
    // Sariq: C ning xonasi (103) shu vaqtda boshqa guruh bilan band — to'qnashuv; boshqa bo'sh xonaga — darhol.
    $s['card']('pp/2024-01h', rmTeacher('UCHINCHI U.'), 2, 1, $s['r3'], ['subject_name' => 'Anatomiya', 'kafedra_name' => 'Anatomiya kafedrasi']);
    expect(rmCheck($s, $s['c'], ['day' => 2, 'pair' => 1, 'auditorium_code' => 'R2'])->assertOk()->json('mode'))->toBe('fix');
    // Umumiy (raqamsiz) xona — sig'im tekshirilmaydi.
    Auditorium::create(['code' => 'RU', 'name' => 'Umumiy mashg‘ulot zali', 'volume' => 5, 'active' => true]);
    expect(rmCheck($s, $s['c'], ['day' => 2, 'pair' => 1, 'auditorium_code' => 'RU'])->assertOk()->json('mode'))->toBe('fix');
});

it('o\'qituvchi oqimi o\'zgarmagan: Takliflar "boshqa xona bilan" — so\'rov orqali; admin Takliflarida aniq berilgan kichik xona — rad, xonasiz — avvalgidek', function () {
    $s = rmSetup();
    $body = fn (array $act) => ['week' => $s['week'], 'card_id' => $s['a']->id, 'type' => 'slot', 'title' => 'x', 'actions' => [$act + ['op' => 'move', 'card_id' => $s['a']->id]]];
    // O'qituvchi: to'qnashuvdagi darsini boshqa xona bilan darhol — rad ("Boshqa xona bilan ko'chirish — so'rov orqali").
    rmAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body(['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2']))
        ->assertForbidden()->assertJsonPath('why', 'Boshqa xona bilan ko‘chirish — so‘rov orqali.');
    // Xonasiz — avvalgidek darhol.
    rmAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body(['day' => 2, 'pair' => 3]))->assertOk()
        ->assertJsonPath('fixed', true);
    TimetableCardOverride::query()->delete();
    // Admin: aniq berilgan kichik / nofaol xona — rad; umumiy bajaruvchi (tasdiq ham shu).
    rmAs($s['admin'], 'admin')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body(['day' => 2, 'pair' => 3, 'auditorium_code' => 'RS']))
        ->assertUnprocessable()->assertJsonPath('error', fn ($e) => str_contains($e, "Xona sig'maydi"));
    rmAs($s['admin'], 'admin')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body(['day' => 2, 'pair' => 3, 'auditorium_code' => 'RX']))
        ->assertUnprocessable()->assertJsonPath('error', fn ($e) => str_contains($e, 'nofaol'));
    rmAs($s['admin'], 'admin')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body(['day' => 2, 'pair' => 3]))->assertOk();
    // fan_masuli uchun xonalar / o'qituvchilar ro'yxati route'lari yopiq.
    rmAs($s['fm'], 'fan_masuli')->getJson(route('admin.timetable.auditoriums'))->assertForbidden();
    rmAs($s['fm'], 'fan_masuli')->getJson(route('admin.timetable.teachers'))->assertForbidden();
});

it('ko\'p bo\'lakli dars shu kunda o\'z eski joyi bilan kesishadigan joyga (1 va 3 → 3 va 5) — check ham, qo\'llash ham o\'tadi (Codex)', function () {
    $s = rmSetup();
    $who = rmTeacher('TORTINCHI T.');
    $p1 = $s['card']('pp/2024-01k', $who, 5, 1, $s['r1']);
    $p2 = $s['card']('pp/2024-01k', $who, 5, 3, $s['r1']);
    // To'qnashuv: shu o'qituvchining boshqa darsi juma I parada (o'qituvchi band) — darhol tuzatish rejimi.
    $s['card']('pp/2024-01m', $who, 5, 1, $s['r2'], ['subject_name' => 'Anatomiya', 'kafedra_name' => 'Anatomiya kafedrasi']);
    expect(rmCheck($s, $p1, ['day' => 5, 'pair' => 3])->assertOk()->json('mode'))->toBe('fix');
    rmMove($s, $p1, ['day' => 5, 'pair' => 3])->assertOk()->assertJsonPath('mode', 'fix');
    $ovs = TimetableCardOverride::whereIn('card_id', [$p1->id, $p2->id])->orderBy('card_id')->get();
    expect($ovs->map(fn ($o) => [$o->day, $o->pair, (bool) $o->cancelled])->all())->toBe([[5, 3, false], [5, 5, false]]);
});

it('fan mas\'ulining boshqa rollari (admin, boshqa kafedra mudiri) so\'rov zanjirini o\'zgartirmaydi — kafedra mudiri → registrator (Codex)', function () {
    $s = rmSetup();
    $s['fm']->assignRole('admin');
    rmMove($s, $s['c'], ['day' => 3, 'pair' => 5, 'reason' => 'sabab'])->assertOk()->assertJsonPath('mode', 'request');
    $req = TimetableChangeRequest::where('status', TimetableChangeRequest::PENDING)->sole();
    expect([$req->requester_role, $req->approval_stage])->toBe(['fan_masuli', 'department']);
    // Boshqa kafedra mudiri ham bo'lsa — baribir shu fan kafedrasi mudiri.
    $req->update(['status' => 'cancelled']);
    DB::table('departments')->insert(['department_hemis_id' => 777, 'name' => 'Anatomiya kafedrasi', 'code' => 'A1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $s['fm']->removeRole('admin');
    $s['fm']->assignRole('kafedra_mudiri');
    $s['fm']->update(['department_hemis_id' => 777, 'head_department_hemis_id' => 777]);
    rmMove($s, $s['c'], ['day' => 3, 'pair' => 7, 'reason' => 'sabab'])->assertOk()->assertJsonPath('mode', 'request');
    $req2 = TimetableChangeRequest::where('status', TimetableChangeRequest::PENDING)->sole();
    expect([$req2->requester_role, $req2->approval_stage, (int) $req2->department_hemis_id])->toBe(['fan_masuli', 'department', 501]);
    rmAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req2))->assertOk();
    rmAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req2))->assertOk();
    expect(TimetableCardOverride::where('card_id', $s['c']->id)->value('pair'))->toBe(7);
});

it('xonalar ro\'yxati: band xona busy_items bilan (o\'qituvchi qisqa ismi, guruh, fan, vaqt); bo\'sh xonalar ko\'p bo\'lsa ham kesilmaydi', function () {
    $s = rmSetup();
    // 102-xona seshanba 10:00–11:20 da boshqa fakultet darsi bilan band (UMAROV JASUR AKMALOVICH, d1/d25-07a, Normal fiziologiya).
    $s['card']('d1/d25-07a', rmTeacher('UMAROV JASUR AKMALOVICH'), 2, 3, $s['r2'], ['faculty_name' => 'F1', 'specialty_name' => 'davolash', 'course' => 1,
        'subject_name' => 'Normal fiziologiya', 'kafedra_name' => 'Fiziologiya kafedrasi']);
    foreach (range(1, 160) as $i) {
        Auditorium::create(['code' => 'F' . $i, 'name' => (300 + $i) . '-xona', 'volume' => 30, 'active' => true]);
    }
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->json('rooms'));
    $r2 = $rooms->firstWhere('code', 'R2');

    // 4 ta + 160 ta faol xona (nofaol 105 — yo'q): bo'shlar 162 (hozirgi 101 bilan), band 102 va sig'maydigan 104.
    expect($rooms->count())->toBe(164)
        ->and($rooms->where('free', true)->count())->toBe(162)
        ->and($r2)->toMatchArray(['free' => false, 'kind' => 'busy', 'cap' => null])
        ->and($r2['busy_items'])->toHaveCount(1)
        ->and($r2['busy_items'][0])->toMatchArray(['teacher' => 'UMAROV J. A.', 'teacher_full' => 'UMAROV JASUR AKMALOVICH',
            'groups' => ['d1/d25-07a'], 'subject' => 'Normal fiziologiya', 'time' => '10:00–11:20', 'date' => '2026-11-17', 'view' => 'week'])
        // Band va sig'maydigan xonalar bo'shlardan keyin (hozirgi xonadan tashqari).
        ->and($rooms->slice(1)->takeWhile(fn ($r) => $r['free'])->count())->toBe(161);

    // Hozirgi (shu haftadagi) xona — reyting bo'yicha pastroq bo'lsa ham (odatdagi 101 dan) eng tepada.
    TimetableCardOverride::create(['card_id' => $s['a']->id, 'week' => $s['week'], 'day' => 4, 'pair' => 7, 'auditorium_code' => 'F160', 'auditorium_name' => '460-xona']);
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->json('rooms'));
    expect($rooms->first())->toMatchArray(['code' => 'F160', 'current' => true])->and($rooms->get(1)['code'])->toBe('R1');
});

/** Xona nomidan joylashuv: bino, bo'lim, qavat (yaqinlik tartibi uchun) — production nomlari. */
function rmLocate(string $name): array
{
    $m = fn (string $f, ...$a) => (new ReflectionMethod(TimetableController::class, $f))->invoke(null, ...$a);

    return [$m('roomBuilding', $name), $m('roomSection', $name), $m('roomFloor', $name, true), $m('roomFloor', $name)];
}

it('xona nomidan bino / bo\'lim / qavat: "N-bino", "… klasteri", "… bo\'limi" (qavsda ham), "N-qavat" / "N-etaj" / podval, 3+ xonali raqam', function () {
    expect(rmLocate("3-bino, revmakardiologiya bo'limi, 2-qavat, 2-amaliyot xonasi"))->toBe(['3-bino', "s:revmakardiologiya bo'limi", 2, null])
        ->and(rmLocate("7-bino, gematologiya bo'limi, 3-qavat, 3-amaliyot xonasi"))->toBe(['7-bino', "s:gematologiya bo'limi", 3, null])
        ->and(rmLocate("Tibbiyot klasteri, kardioxirurgiya bo'limi, 2-qavat, 2-xona"))->toBe(['tibbiyot klasteri', "s:kardioxirurgiya bo'limi", 2, null])
        ->and(rmLocate("Tibbiyot klasteri, podval, 2-ma'ruza xonasi"))->toBe(['tibbiyot klasteri', null, 0, null])
        ->and(rmLocate('-1-qavat (podval), 1-amaliyot xonasi'))->toBe([null, null, 0, null])
        ->and(rmLocate('2-etaj 2-amaliyot xonasi'))->toBe([null, null, 2, null])
        ->and(rmLocate('1-qavat, 118-amaliyot xonasi'))->toBe([null, null, 1, 1])
        // Aniq yozilgan qavat ustun (boshqa oynalardagi eski qoida — raqamdan: 3).
        ->and(rmLocate('2-qavat, 305-auditoriya'))->toBe([null, null, 2, 3])
        ->and(rmLocate("1-qavat, 23-xona (Suyak-bo'g'im kasalliklari bo'limi)"))->toBe([null, "s:suyak-bo'g'im kasalliklari bo'limi", 1, null])
        ->and(rmLocate('404-amaliyot xonasi'))->toBe([null, null, 4, 4])
        ->and(rmLocate('306b'))->toBe([null, null, 3, 3])
        ->and(rmLocate("1-bo'lim, 1-qavat, 1-amaliyot xonasi"))->toBe([null, 'n:1', 1, null])
        // Tutuq belgisi farqsiz: ' / ‘ / ’ / `.
        ->and(rmLocate('3-bino, revmakardiologiya bo‘limi, 2-qavat')[1])->toBe("s:revmakardiologiya bo'limi")
        ->and(rmLocate('3-bino, revmakardiologiya bo’limi, 2-qavat')[1])->toBe("s:revmakardiologiya bo'limi")
        ->and(rmLocate('1-bo`lim, 2-qavat')[1])->toBe('n:1')
        ->and(rmLocate('101-xona'))->toBe([null, null, 1, 1]);
    $short = fn (?string $n) => (new ReflectionMethod(TimetableController::class, 'teacherShortName'))->invoke(null, $n);
    expect($short('UMAROV JASUR AKMALOVICH'))->toBe('UMAROV J. A.')->and($short('UMAROV J. A.'))->toBe('UMAROV J. A.')
        ->and($short("KARIMOV ALI VALI O'G'LI"))->toBe('KARIMOV A. V.')->and($short('  '))->toBeNull();
});

it('fan mas\'uli oynasida xonalar tartibi — yaqinlik birinchi (bino → bo\'lim → qavat); sabab — eng yaqin pog\'ona + ball; boshqa oynalar tartibi o\'zgarmaydi', function () {
    $s = rmSetup();
    $mk = fn (string $code, string $name, ?string $bid = null, bool $active = true) => Auditorium::create(['code' => $code, 'name' => $name, 'volume' => 30,
        'active' => $active, 'building_id' => $bid]);
    $s0 = $mk('S0', "3-bino, revmakardiologiya bo'limi, 2-qavat, 2-amaliyot xonasi", 'B1');
    $mk('S1', "3-bino, revmakardiologiya bo'limi, 2-qavat, 5-amaliyot xonasi", 'B1');
    $mk('S2', "3-bino, revmakardiologiya bo'limi, 3-qavat, 1-amaliyot xonasi", 'B1');
    $mk('S3', "3-bino, gematologiya bo'limi, 2-qavat, 3-amaliyot xonasi");
    $mk('S4', "3-bino, gematologiya bo'limi, 4-qavat, 1-xona");
    // Boshqa bino: nomlar farq qiladi — building_id bir xil bo'lsa ham (nom ustun); bo'lim va qavati bir xil bo'lsa ham.
    $mk('S5', "7-bino, revmakardiologiya bo'limi, 2-qavat, 2-amaliyot xonasi", 'B1');
    $mk('S6', "Tibbiyot klasteri, kardioxirurgiya bo'limi, 2-qavat, 2-xona");
    $mk('X1', '1-qavat, 9-xona', 'B5');
    $mk('X2', '9-bino, 2-qavat, 1-xona', 'B5');
    $s['a']->update(['auditorium_code' => 'S0', 'auditorium_name' => $s0->name]);
    // Odat: shu guruh shu fanni boshqa haftalarda S3 (shu bino) va S6 (boshqa bino) da o'tgan.
    foreach ([2 => 'S3', 3 => 'S6', 4 => 'S6'] as $w => $code) {
        TimetableCardOverride::create(['card_id' => $s['a']->id, 'week' => $w, 'day' => 4, 'pair' => 7, 'auditorium_code' => $code, 'auditorium_name' => $code]);
    }
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->assertOk()->json('rooms'))->keyBy('code');
    $order = $rooms->keys()->filter(fn ($c) => str_starts_with($c, 'S'))->values()->all();

    // Boshqa binolar: qavat farqi hisobga olinmaydi (X2 — 2-qavat, hozirgidek; X1 — 1-qavat): nomi bo'yicha.
    expect($rooms->keys()->search('X1'))->toBeLessThan($rooms->keys()->search('X2'))
        ->and($order)->toBe(['S0', 'S1', 'S2', 'S3', 'S4', 'S6', 'S5'])
        ->and($rooms['S0']['current'])->toBeTrue()
        ->and([$rooms['S1']['why'], $rooms['S2']['why'], $rooms['S3']['why'], $rooms['S4']['why'], $rooms['S6']['why'], $rooms['S5']['why']])
        ->toBe(['shu qavat', "shu bo'lim", 'shu qavat · shu fan odatda shu yerda', 'shu bino', 'shu fan odatda shu yerda', null]);

    // Boshqa oynalar (biriktirish — roomRankForUnit / rankRooms sukut): eski tartib — odatdagi xona eng tepada, "shu bino" — ball.
    $ctl = app(TimetableController::class);
    $legacy = (new ReflectionMethod($ctl, 'roomRankForUnit'))->invoke($ctl, $s['board'], collect([$s['a']->fresh()]),
        Auditorium::whereIn('code', ['S1', 'S2', 'S3', 'S4', 'S5', 'S6'])->get());
    expect(array_slice($legacy['order'], 0, 2))->toBe(['S3', 'S6'])
        ->and(array_search('S6', $legacy['order']))->toBeLessThan(array_search('S1', $legacy['order']))
        ->and($legacy['why']['S6'])->toBe('shu fan odatda shu yerda')
        ->and($legacy['why']['S1'])->toBe('shu bino');

    // Hozirgi xona — shu haftadagi (override) xona: odatdagi va yaqinroq xonalar bo'lsa ham eng tepada; nuqta — shu xona.
    TimetableCardOverride::create(['card_id' => $s['a']->id, 'week' => $s['week'], 'day' => 4, 'pair' => 7, 'auditorium_code' => 'S2', 'auditorium_name' => 'S2']);
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->assertOk()->json('rooms'));
    expect($rooms->first())->toMatchArray(['code' => 'S2', 'current' => true])
        ->and($rooms->firstWhere('code', 'S0')['why'])->toStartWith("shu bo'lim");

    // Nomda bino yo'q — building_id bo'yicha (hozirgi xona nofaol bo'lsa ham); bir xonada bor, ikkinchisida yo'q — building_id bo'yicha.
    $c0 = $mk('T0', "1-qavat, 23-xona (Suyak-bo'g'im kasalliklari bo'limi)", 'B7', false);
    $mk('T1', '1-qavat, 118-amaliyot xonasi', 'B7');
    $mk('T2', '2-qavat, 305-auditoriya', 'B7');
    $mk('T3', "7-bino, gematologiya bo'limi, 1-qavat, 3-amaliyot xonasi", 'B7');
    $mk('T4', "1-qavat, 5-xona (Suyak-bo‘g‘im kasalliklari bo‘limi)", 'B7');
    $mk('T5', '1-qavat, 6-xona', 'B9');
    $s['a']->update(['auditorium_code' => 'T0', 'auditorium_name' => $c0->name]);
    TimetableCardOverride::query()->delete();
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->assertUnprocessable()->json('rooms'))->keyBy('code');
    $order = $rooms->keys()->filter(fn ($c) => str_starts_with($c, 'T'))->values()->all();
    expect($order)->toBe(['T4', 'T1', 'T3', 'T2', 'T5'])
        ->and([$rooms['T4']['why'], $rooms['T1']['why'], $rooms['T3']['why'], $rooms['T2']['why'], $rooms['T5']['why']])
        ->toBe(['shu qavat', 'shu qavat', 'shu qavat', 'shu bino', null]);
});
