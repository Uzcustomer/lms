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
    expect($chk['mode'])->toBe('request')->and($chk['message'])->toContain("kafedra mudiri, registrator ofisi va o'quv bo'limiga");
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
        ->and(rmLocate('101-xona'))->toBe([null, null, 1, 1])
        // Vergulsiz nom: bino — faqat bino qismi (xonaga oid matnsiz), shu binodagi xonalar bir xil (Codex).
        ->and(rmLocate('3-bino 2-qavat 201-xona'))->toBe(['3-bino', null, 2, 2])
        ->and(rmLocate('3-bino 2-qavat 202-xona')[0])->toBe('3-bino')
        ->and(rmLocate("Tibbiyot klasteri 8 ma'ruza zali")[0])->toBe('tibbiyot klasteri')
        ->and(rmLocate('Bosh o‘quv binosi, 3-qavat, 310')[0])->toBe("bosh o'quv binosi");
    $short = fn (?string $n) => (new ReflectionMethod(TimetableController::class, 'teacherShortName'))->invoke(null, $n);
    expect($short('UMAROV JASUR AKMALOVICH'))->toBe('UMAROV J. A.')->and($short('UMAROV J. A.'))->toBe('UMAROV J. A.')
        ->and($short("KARIMOV ALI VALI O'G'LI"))->toBe('KARIMOV A. V.')->and($short('  '))->toBeNull()
        // O'zbek qo'sh harflari — bitta bosh harf: Sh, Ch, O', G' (tutuq belgisi bir xil "'").
        ->and($short('OSTANAQULOV SHAXBOZ FARXODOVICH'))->toBe('OSTANAQULOV SH. F.')->and($short('OSTANAQULOV SH. F.'))->toBe('OSTANAQULOV SH. F.')
        ->and($short('RAHIMOV CHORI O‘TKIROVICH'))->toBe("RAHIMOV CH. O'.")->and($short("ALIYEVA G'ULOMJON SAIDOVNA"))->toBe("ALIYEVA G'. S.");
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
    // Vergulsiz nom, shu bino va qavat — "shu qavat" (bino nomi xonaga oid matnsiz solishtiriladi).
    $mk('S7', '3-bino 2-qavat 201-xona');
    $mk('X1', '1-qavat, 9-xona', 'B5');
    $mk('X2', '9-bino, 2-qavat, 1-xona', 'B5');
    $s['a']->update(['auditorium_code' => 'S0', 'auditorium_name' => $s0->name]);
    // Odat: shu guruh shu fanni boshqa haftalarda S3 (shu bino) va S6 (boshqa bino) da o'tgan.
    foreach ([2 => 'S3', 3 => 'S6', 4 => 'S6'] as $w => $code) {
        TimetableCardOverride::create(['card_id' => $s['a']->id, 'week' => $w, 'day' => 4, 'pair' => 7, 'auditorium_code' => $code, 'auditorium_name' => $code]);
    }
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->assertOk()->json('rooms'))->keyBy('code');
    $order = $rooms->keys()->filter(fn ($c) => str_starts_with($c, 'S') && $c !== 'S7')->values()->all();

    // Boshqa binolar: qavat farqi hisobga olinmaydi (X2 — 2-qavat, hozirgidek; X1 — 1-qavat): nomi bo'yicha.
    expect($rooms['S7']['why'])->toBe('shu qavat')
        ->and($rooms->keys()->search('X1'))->toBeLessThan($rooms->keys()->search('X2'))
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

it('ko\'p bo\'lakli dars (2 + 2) bo\'laklari har xil xonada: part_rooms holati bilan, har bo\'lak xonasi — oddiy qator; tanlansa — ikkala bo\'lak shu xonaga', function () {
    $s = rmSetup();
    $who = rmTeacher('BESHINCHI B.');
    // 1-bo'lak — 101 (faol), 2-bo'lak — 105 (nofaol); shablonda joylanmagan birlik (darhol rejim).
    $p1 = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    $p2 = $s['card']('pp/2024-01g', $who, null, null, $s['off']);
    $res = rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->assertUnprocessable()->json();
    $rooms = collect($res['rooms']);

    expect($res['message'])->toBe('Xona tanlang: hozirgi xona 105-xona — nofaol.')
        ->and($res['part_rooms'])->toBe([['code' => 'R1', 'name' => '101-xona', 'problem' => null], ['code' => 'RX', 'name' => '105-xona', 'problem' => 'nofaol']])
        // "Hozirgi xona" qatori yo'q — 101 ro'yxatda oddiy (tanlanadigan) qator, nofaol 105 — chiqmaydi.
        ->and($rooms->where('current', true)->count())->toBe(0)
        ->and($rooms->firstWhere('code', 'R1'))->toMatchArray(['free' => true, 'kind' => null, 'current' => false])
        ->and($rooms->pluck('code')->all())->not->toContain('RX');
    // 101 tanlansa — ikkala bo'lak shu xonaga.
    expect(rmCheck($s, $p1, ['day' => 3, 'pair' => 1, 'auditorium_code' => 'R1'])->assertOk()->json('mode'))->toBe('fix');
    rmMove($s, $p1, ['day' => 3, 'pair' => 1, 'auditorium_code' => 'R1'])->assertOk();
    expect(TimetableCardOverride::whereIn('card_id', [$p1->id, $p2->id])->orderBy('card_id')->pluck('auditorium_code')->all())->toBe(['R1', 'R1']);

    // 2-bo'lak xonasi boshqa o'qituvchiga biriktirilgan — ro'yxatda kulrang, kimga biriktirilgani bilan (qisqa nomi; to'liq F.I.Sh. —
    // note_full, title uchun), tanlab bo'lmaydi; part_rooms holatida ham shu; sig'maydigani — o'z holati bilan.
    TimetableCardOverride::query()->delete();
    $owner = rmTeacher('OSTANAQULOV SHAXBOZ FARXODOVICH', 'oqituvchi');
    DB::table('auditorium_teacher')->insert(['board_id' => $s['board']->id, 'auditorium_id' => $s['r3']->id, 'teacher_id' => $owner->id,
        'is_general' => false, 'created_at' => now(), 'updated_at' => now()]);
    $p2->update(['auditorium_code' => 'R3', 'auditorium_name' => '103-xona']);
    $res = rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->json();
    expect($res['part_rooms'][1])->toBe(['code' => 'R3', 'name' => '103-xona', 'problem' => 'Biriktirilgan: OSTANAQULOV SH. F.'])
        ->and(collect($res['rooms'])->firstWhere('code', 'R3'))->toMatchArray(['free' => false, 'kind' => 'assigned',
            'note' => 'Biriktirilgan: OSTANAQULOV SH. F.', 'note_full' => 'Biriktirilgan: OSTANAQULOV SHAXBOZ FARXODOVICH', 'why' => null]);
    $p2->update(['auditorium_code' => 'RS', 'auditorium_name' => '104-xona']);
    expect(rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->json('part_rooms.1'))->toBe(['code' => 'RS', 'name' => '104-xona', 'problem' => "sig'maydi"]);
});

it('ko\'p bo\'lakli dars bitta xonada: hozirgi xona birinchi qator, ro\'yxatda takrorlanmaydi', function () {
    $s = rmSetup();
    $who = rmTeacher('OLTINCHI O.');
    $p1 = $s['card']('pp/2024-01g', $who, null, null, $s['r2']);
    $s['card']('pp/2024-01g', $who, null, null, $s['r2']);
    $res = rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->assertOk()->json();
    $rooms = collect($res['rooms']);

    expect($res['part_rooms'])->toBe([['code' => 'R2', 'name' => '102-xona', 'problem' => null]])
        ->and($rooms->where('code', 'R2')->count())->toBe(1)
        ->and($rooms->first())->toMatchArray(['code' => 'R2', 'current' => true]);
});

it('fan mas\'uli: boshqa o\'qituvchiga biriktirilgan xona — tekshiruv va saqlashda 422, kimga biriktirilgani bilan; umumiy xona — ruxsat', function () {
    $s = rmSetup();
    $owner = rmTeacher('OSTANAQULOV SHAXBOZ FARXODOVICH', 'oqituvchi');
    DB::table('auditorium_teacher')->insert(['board_id' => $s['board']->id, 'auditorium_id' => $s['r2']->id, 'teacher_id' => $owner->id,
        'is_general' => false, 'created_at' => now(), 'updated_at' => now()]);
    $msg = 'Bu xona OSTANAQULOV SH. F. ga biriktirilgan.';
    // Ro'yxatda bor — kulrang, kimga biriktirilgani bilan, tanlab bo'lmaydi (free: false), ro'yxat oxirida (band / sig'maydiganlardan ham
    // keyin); qo'lda yuborilsa — tekshiruvda ham, saqlashda ham 422.
    $rooms = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->json('rooms'));
    expect($rooms->firstWhere('code', 'R2'))->toMatchArray(['kind' => 'assigned', 'free' => false, 'current' => false, 'why' => null,
            'note' => 'Biriktirilgan: OSTANAQULOV SH. F.', 'note_full' => 'Biriktirilgan: OSTANAQULOV SHAXBOZ FARXODOVICH', 'busy_items' => [], 'cap' => null])
        ->and($rooms->last()['code'])->toBe('R2')
        // Hozirgi 101, bo'sh 103, sig'maydigan 104, biriktirilgan 102 (nofaol 105 — yo'q).
        ->and($rooms->map(fn ($r) => $r['code'] . ':' . ($r['kind'] ?? 'free'))->all())->toBe(['R1:free', 'R3:free', 'RS:capacity', 'R2:assigned'])
        ->and($rooms->pluck('code')->all())->not->toContain('RX');
    $chk = rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertUnprocessable()->json();
    expect($chk['message'])->toBe($msg)->and($chk['conflicts'])->toBe([$msg])
        ->and(collect($chk['rooms'])->firstWhere('code', 'R2')['kind'])->toBe('assigned');
    rmMove($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertUnprocessable()->assertJson(['ok' => false, 'error' => $msg, 'message' => $msg]);
    expect(TimetableCardOverride::where('card_id', $s['a']->id)->where('week', $s['week'])->value('auditorium_code'))->not->toBe('R2');
    // Umumiy (is_general) xona — biriktirilgan bo'lsa ham hammaga ochiq.
    DB::table('auditorium_teacher')->where('auditorium_id', $s['r2']->id)->update(['is_general' => true]);
    expect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertOk()->json('mode'))->toBe('fix');
});

it('band xona busy_items: ma\'ruza (ko\'p guruh) bilan band — subject faqat fan nomi, guruhlar groups\'da', function () {
    $s = rmSetup();
    // 102-xona seshanba 10:00–11:20 da boshqa yo'nalish ma'ruzasi bilan band: guruhlar vergul bilan ("p/p25-01b (o'z), p/p25-01c, …").
    $s['card']('', rmTeacher('UMAROV JASUR AKMALOVICH'), 2, 3, $s['r2'], ['faculty_name' => 'F1', 'specialty_name' => 'davolash', 'course' => 1,
        'training_type' => 'lecture', 'group_name' => null, 'group_names' => ["p/p25-01b (o'z)", 'p/p25-01c', 'p/p25-02a'], 'subject_name' => 'Normal fiziologiya',
        'kafedra_name' => 'Fiziologiya kafedrasi']);
    $r2 = collect(rmCheck($s, $s['a'], ['day' => 2, 'pair' => 3])->json('rooms'))->firstWhere('code', 'R2');
    expect($r2)->toMatchArray(['free' => false, 'kind' => 'busy'])
        ->and($r2['busy_items'])->toHaveCount(1)
        ->and($r2['busy_items'][0])->toMatchArray(['teacher' => 'UMAROV J. A.', 'subject' => 'Normal fiziologiya',
            'groups' => ["p/p25-01b (o'z)", 'p/p25-01c', 'p/p25-02a'], 'time' => '10:00–11:20']);
});

// ---- "Joylash uchun tanlash" (fan mas'uli): responsible-move/grid — bitta so'rov bilan kataklar holati ----

function rmGrid(array $s, TimetableCard $card, ?Teacher $who = null, string $role = 'fan_masuli', ?int $week = null)
{
    return rmAs($who ?? $s['fm'], $role)->getJson(route('admin.timetable.cards.responsible-move.grid', ['card' => $card, 'week' => $week ?? $s['week']]));
}

it('grid: holatlar — ok (eng yaqin bo\'sh xona), teacher, group, room, closed (kun oxiri); sabab va sabab bo\'lgan darslar', function () {
    $s = rmSetup();
    $who = rmTeacher('YETTINCHI Y.');
    $x = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);   // joylanmagan, xonasi 101
    // Guruh band: chorshanba 5-qator (12:00); o'qituvchi band: juma 3-qator (boshqa guruh).
    $g = $s['card']('pp/2024-01g', $s['other'], 3, 5, $s['r3'], ['subject_name' => 'Anatomiya']);
    $t = $s['card']('pp/2024-01h', $who, 5, 3, $s['r3'], ['subject_name' => 'Anatomiya']);
    // Shanba 1-qator: barcha mos xonalar band (101, 102, 103); 104 — kichik, 105 — nofaol.
    foreach (['R1' => 'pp/2024-02a', 'R2' => 'pp/2024-02b', 'R3' => 'pp/2024-02c'] as $code => $grp) {
        $s['card']($grp, rmTeacher('B-' . $code), 6, 1, Auditorium::where('code', $code)->first(), ['subject_name' => 'Tarix']);
    }
    $res = rmGrid($s, $x)->assertOk()->json();
    $c = fn (int $d, int $p) => $res['cells'][$d . '-' . $p];

    expect($res)->toMatchArray(['ok' => true, 'week' => $s['week'], 'days' => 6, 'pairs' => 12, 'len' => 2, 'closed' => null])
        ->and($res['parts'])->toBe([['card_id' => $x->id, 'len_half' => 2, 'offset' => 0]])
        ->and($c(2, 3))->toMatchArray(['state' => 'ok', 'reason' => null])
        ->and($c(2, 3)['best_room'])->toMatchArray(['code' => 'R1', 'name' => '101-xona', 'why' => 'hozirgi xona'])
        // 12:00 dagi guruh darsi bilan ustma-ust (shu va oldingi qatordan boshlab — 2 soat), sababi va ramka uchun karta.
        ->and($c(3, 5))->toMatchArray(['state' => 'group', 'causes' => [$g->id => 'group']])->and($c(3, 5)['reason'])->toContain('Guruh band')
        ->and($c(3, 4)['state'])->toBe('group')->and($c(3, 6)['state'])->toBe('group')->and($c(3, 7)['state'])->toBe('ok')
        ->and($c(5, 3))->toMatchArray(['state' => 'teacher', 'causes' => [$t->id => 'teacher']])->and($c(5, 3)['reason'])->toContain("O'qituvchi band: YETTINCHI Y.")
        ->and($c(6, 1))->toMatchArray(['state' => 'room'])->and($c(6, 1)['reason'])->toContain("mos bo'sh xona yo'q")
        ->and($c(6, 1)['reason'])->toContain('hozirgi xona band')
        ->and($c(2, 12))->toMatchArray(['state' => 'closed', 'reason' => "Kun oxiri: 2 soat sig'maydi."])
        ->and(count($res['cells']))->toBe(6 * 12);
    // Sariq katakdan keyingi qatorda — 101 bo'sh (shanba 1-qatordagi dars 2 soat: 1–2), 3-qatordan — yashil.
    expect($c(6, 3)['state'])->toBe('ok');
});

it('grid: guruh / o\'qituvchi holati unitPlanAt bilan mos (har katak); ko\'p bo\'lakli dars — butun birlik bo\'yicha', function () {
    $s = rmSetup();
    $who = rmTeacher('SAKKIZINCHI S.');
    $p1 = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    $p2 = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    $s['card']('pp/2024-01g', $s['other'], 3, 5, $s['r3'], ['subject_name' => 'Anatomiya']);
    $s['card']('pp/2024-01h', $who, 5, 3, $s['r3'], ['subject_name' => 'Anatomiya']);
    $res = rmGrid($s, $p1)->assertOk()->json();
    expect($res['len'])->toBe(4)->and(array_column($res['parts'], 'offset'))->toBe([0, 2]);

    $ctl = app(TimetableController::class);
    $plan = new ReflectionMethod($ctl, 'unitPlanAt');
    $unit = (new ReflectionMethod($ctl, 'weeklyUnitCards'))->invoke($ctl, $p1)->each(fn ($c) => $c->setRelation('board', $s['board']));
    $p1->setRelation('board', $s['board']);
    $checked = 0;
    foreach ($res['cells'] as $key => $cell) {
        [$d, $p] = array_map('intval', explode('-', $key));
        if ($cell['state'] === 'closed') {
            continue;
        }
        [$errors] = $plan->invoke($ctl, $p1, $unit, $s['week'], $d, $p);
        $text = implode(' ', $errors);
        $want = str_contains($text, "O'qituvchi band") ? 'teacher' : (str_contains($text, 'Guruh band') ? 'group' : 'free');
        expect([$key, in_array($cell['state'], ['ok', 'room'], true) ? 'free' : $cell['state']])->toBe([$key, $want]);
        $checked++;
    }
    // Dushanba (16.11) — bugun (beforeEach), har kunning 10–12-qatorlari — 4 soat sig'maydi: 5 kun × 9 boshlanish tekshirildi.
    // 12:00 dagi guruh darsi: 4 soatlik dars 2-, 3-, 4-, 5-, 6-qatordan boshlab ustma-ust.
    expect($checked)->toBe(45)
        ->and(collect([2, 3, 4, 5, 6])->map(fn ($p) => $res['cells']['3-' . $p]['state'])->unique()->values()->all())->toBe(['group'])
        ->and($res['cells']['3-1']['state'])->toBe('ok')->and($res['cells']['3-7']['state'])->toBe('ok')
        ->and($res['cells']['2-10']['state'])->toBe('closed');
});

it('grid: bugungi va o\'tgan kunlar — closed; huquq — mas\'ul bo\'lmagan fan, ma\'ruza va boshqa rol — 403', function () {
    $s = rmSetup();
    $who = rmTeacher('TO\'QQIZINCHI T.');
    $x = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    // Shu haftaning chorshanbasi (18.11) — bugun: dushanba–chorshanba yopiq, payshanbadan — ochiq.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-18 08:00', 'Asia/Tashkent'));
    $res = rmGrid($s, $x)->assertOk()->json();
    expect(collect(range(1, 3))->map(fn ($d) => $res['cells'][$d . '-3']['state'])->all())->toBe(['closed', 'closed', 'closed'])
        ->and($res['cells']['3-3']['reason'])->toBe("Bugungi va o'tgan kunlar — yopiq (18.11).")
        ->and($res['cells']['4-1']['state'])->toBe('ok');
    // Butun hafta o'tgan — hamma katak yopiq, sababi bilan.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-23 08:00', 'Asia/Tashkent'));
    $res = rmGrid($s, $x)->assertOk()->json();
    expect($res['closed'])->toContain("ochiq kun yo'q")->and(collect($res['cells'])->pluck('state')->unique()->all())->toBe(['closed']);
    $this->travelBack();

    $other = $s['card']('pp/2024-01g', $who, null, null, $s['r1'], ['subject_name' => 'Anatomiya']);
    rmGrid($s, $other)->assertForbidden()->assertJson(['message' => "Faqat mas'ul faningizdagi darsni o'zgartira olasiz."]);
    $lec = $s['card']('pp/2024-01g', $who, null, null, $s['r1'], ['training_type' => 'lecture']);
    rmGrid($s, $lec)->assertForbidden()->assertJson(['message' => "Ma'ruzani surish — faqat kafedra mudiri va adminlar."]);
    rmGrid($s, $x, $s['t'], 'oqituvchi')->assertForbidden();
    rmGrid($s, $x, null, 'fan_masuli', 99)->assertUnprocessable();
});

it('grid: yashil katakka joylash — override yoziladi (darhol, faqat shu hafta); sariq katakda xona tanlamasdan — rad', function () {
    $s = rmSetup();
    $who = rmTeacher('O\'NINCHI O.');
    $x = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    foreach (['R1' => 'pp/2024-02a', 'R2' => 'pp/2024-02b', 'R3' => 'pp/2024-02c'] as $code => $grp) {
        $s['card']($grp, rmTeacher('B-' . $code), 6, 1, Auditorium::where('code', $code)->first(), ['subject_name' => 'Tarix']);
    }
    $grid = rmGrid($s, $x)->assertOk()->json('cells');
    expect($grid['2-3']['state'])->toBe('ok')->and($grid['6-1']['state'])->toBe('room');

    // Yashil: best_room bilan — darhol (joylanmagan dars — fix), shu haftaning override'i, shablon o'zgarmaydi.
    $res = rmMove($s, $x, ['day' => 2, 'pair' => 3, 'auditorium_code' => $grid['2-3']['best_room']['code']])->assertOk()->json();
    expect($res)->toMatchArray(['ok' => true, 'mode' => 'fix', 'fixed' => true]);
    $ov = TimetableCardOverride::where('card_id', $x->id)->where('week', $s['week'])->sole();
    expect([$ov->day, $ov->pair, $ov->auditorium_code, (bool) $ov->cancelled])->toBe([2, 3, 'R1', false])
        ->and($x->fresh()->only(['day', 'pair']))->toBe(['day' => null, 'pair' => null]);

    // Sariq (hozirgi xona band, boshqa mos xona yo'q): xonasiz — rad, hech narsa yozilmaydi.
    TimetableCardOverride::query()->delete();
    rmMove($s, $x, ['day' => 6, 'pair' => 1])->assertUnprocessable();
    expect(TimetableCardOverride::where('card_id', $x->id)->count())->toBe(0);
    // Xonasiz dars: bo'sh vaqtda ham xona tanlanmasa — rad ("Xona tanlang"); tanlansa — joylanadi.
    $y = $s['card']('pp/2024-01h', $who, null, null, null);
    expect(rmGrid($s, $y)->json('cells.2-3.best_room.code'))->toBeIn(['R1', 'R2', 'R3']);
    rmMove($s, $y, ['day' => 2, 'pair' => 3])->assertUnprocessable()->assertJson(['message' => 'Xona tanlang: darsga xona biriktirilmagan.']);
    rmMove($s, $y, ['day' => 2, 'pair' => 3, 'auditorium_code' => 'R2'])->assertOk()->assertJson(['mode' => 'fix']);
});

it('grid: eng yaxshi 5 joy — guruh darsiga yaqin (oraliq kam), o\'qituvchi bandligi kam, yaqin xona; vaqti ustma-ust tushmaydi', function () {
    $s = rmSetup();
    $who = rmTeacher('O\'N BIRINCHI O.');
    $x = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    $s['card']('pp/2024-01g', $s['other'], 3, 5, $s['r3'], ['subject_name' => 'Anatomiya']);   // guruh darsi: chorshanba 12:00–13:20
    $best = rmGrid($s, $x)->assertOk()->json('best');
    expect($best)->toHaveCount(5)
        ->and([$best[0]['day'], $best[0]['pair']])->toBe([3, 3])->and([$best[1]['day'], $best[1]['pair']])->toBe([3, 7])
        ->and($best[0]['why'])->toContain('guruhning shu kungi darsiga ulashgan')->and($best[0]['why'])->toContain("o'qituvchi shu kuni bo'sh")
        ->and($best[0]['label'])->toBe('Chorshanba, 18.11 10:00–11:20')
        ->and($best[0]['room'])->toMatchArray(['code' => 'R1', 'why' => 'hozirgi xona'])
        ->and($best[2]['why'])->toContain('oraliq');
    // Bir kunda tanlangan joylar vaqti ustma-ust emas.
    foreach (collect($best)->groupBy('day') as $list) {
        $ps = $list->pluck('pair')->sort()->values();
        for ($i = 1; $i < $ps->count(); $i++) {
            expect($ps[$i] - $ps[$i - 1])->toBeGreaterThanOrEqual(2);
        }
    }
});

it('grid: shu haftada bo\'sh joy yo\'q — keyingi 3 hafta uchun faqat bo\'sh joylar soni (ahead); bo\'sh joy bor — ahead bo\'sh', function () {
    $s = rmSetup();
    $who = rmTeacher('O\'N IKKINCHI O.');
    $x = $s['card']('pp/2024-01g', $who, null, null, $s['r1']);
    expect(rmGrid($s, $x)->assertOk()->json('ahead'))->toBe([]);
    // Shanba (21.11) — haftaning hamma kuni bugun yoki o'tgan: bo'sh joy yo'q, keyingi haftalar — 6 kun × 11 boshlanish (2 soatlik dars).
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-21 08:00', 'Asia/Tashkent'));
    $res = rmGrid($s, $x)->assertOk()->json();
    expect(collect($res['cells'])->where('state', 'ok')->count())->toBe(0)
        ->and($res['ahead'])->toBe([['week' => $s['week'] + 1, 'ok' => 66], ['week' => $s['week'] + 2, 'ok' => 66], ['week' => $s['week'] + 3, 'ok' => 66]])
        ->and($res['best'])->toBe([]);
    // Keyingi hafta grid'i — ahead dagi son bilan bir xil.
    expect(collect(rmGrid($s, $x, null, 'fan_masuli', $s['week'] + 1)->json('cells'))->where('state', 'ok')->count())->toBe(66);
    // Semestr oxirida — faqat mavjud haftalar (15 haftalik doska): 14-hafta o'tgan (19.12, shanba) — faqat 15-hafta.
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-12-19 08:00', 'Asia/Tashkent'));
    expect(rmGrid($s, $x, null, 'fan_masuli', $s['week'] + 4)->json('ahead'))->toBe([['week' => 15, 'ok' => 66]]);
});

it('3.1/3.2: joylanmagan dars — "Darhol joylanadi (faqat shu hafta)."; ko\'p bo\'lakli darsda amaldagi joy — butun oraliq (bo\'lingan — kunlar bilan)', function () {
    $s = rmSetup();
    // Joylanmagan birlik (2 + 2): "joylanadi", "to'qnashuvni tuzatish" emas.
    $p1 = $s['card']('pp/2024-01g', $s['other'], null, null, $s['r1']);
    $p2 = $s['card']('pp/2024-01g', $s['other'], null, null, $s['r1']);
    expect(rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->assertOk()->json('message'))->toBe('Darhol joylanadi (faqat shu hafta).');
    // To'qnashuvdagi (joylangan) dars — avvalgidek.
    expect(rmCheck($s, $s['a'], ['day' => 6, 'pair' => 1])->assertOk()->json('message'))->toBe("Darhol qo'llanadi (to'qnashuvni tuzatish, faqat shu hafta).");
    // Ko'p bo'lakli joylangan dars: shanba 10:00 va 12:00 — amaldagi "Shanba, 21.11.2026 10:00–13:20" (avval faqat birinchi bo'lak: 10:00–11:20).
    $p1->update(['day' => 6, 'pair' => 3]);
    $p2->update(['day' => 6, 'pair' => 5]);
    expect(rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->assertOk()->json('old'))->toStartWith('Shanba, 21.11.2026 10:00–13:20');
    // Turli kunlarda (bo'lingan) — har kun alohida.
    $p2->update(['day' => 5, 'pair' => 1]);
    expect(rmCheck($s, $p1, ['day' => 3, 'pair' => 1])->assertOk()->json('old'))
        ->toStartWith("Juma, 20.11.2026 08:30–09:50; Shanba, 21.11.2026 10:00–11:20 (bo'lingan)");
});
