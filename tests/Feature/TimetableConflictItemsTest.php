<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use Illuminate\Support\Carbon;
use Spatie\Permission\Models\Role;

// To'qnashuv sababi tuzilgan ro'yxat (conflict_items) — front bloklari uchun ("Xona band: 123-amaliyot xonasi", "O'qituvchi band: NUROVA Z. A.",
// "Guruh band: d1/d26-03a", sig'im va kun oxiri alohida). Production misoli: 11-hafta, d1/d26-03a ↔ 01a (NUROVA Z. A., 123-amaliyot xonasi).
beforeEach(fn () => $this->travelTo(Carbon::parse('2026-11-16 09:00', 'Asia/Tashkent')));

function ciTeacher(string $name, ?string $role = null): Teacher
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

function ciAs()
{
    return test()->withoutVite()->actingAs(ciTeacher('ADMIN', 'admin'), 'teacher')->withSession(['active_role' => 'admin']);
}

/** 11-hafta: 23.11–28.11; 01a (708662) — chorshanba 10:00, NUROVA, 123-amaliyot xonasi (17 o'rin). 03a — joylanmagan (40 talaba). */
function ciSetup(): array
{
    $times = ['08:30', '09:10', '10:00', '10:40', '12:00', '12:40', '13:30', '14:10', '15:00', '15:40', '16:30', '17:10'];
    $ends = ['09:10', '09:50', '10:40', '11:20', '12:40', '13:20', '14:10', '14:50', '15:40', '16:20', '17:10', '17:50'];
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12,
        'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'],
        'bell_schedule' => array_map(fn ($i) => ['type' => 'pair', 'no' => $i + 1, 'name' => ($i + 1) . '-yarim', 'start' => $times[$i], 'end' => $ends[$i]], range(0, 11))]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $nurova = ciTeacher('NUROVA Z. A.');
    Auditorium::create(['code' => 'R123', 'name' => '123-amaliyot xonasi', 'volume' => 17, 'active' => true]);
    $base = ['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 1, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Tibbiy biologiya. Umumiy genetika', 'kafedra_name' => 'Biologiya kafedrasi',
        'teacher_id' => $nurova->id, 'teacher_name' => 'NUROVA Z. A.', 'auditorium_code' => 'R123', 'auditorium_name' => '123-amaliyot xonasi'];
    $blocker = TimetableCard::create($base + ['group_name' => 'd1/d26-01a', 'day' => 3, 'pair' => 3]);
    $card = TimetableCard::create(array_replace($base, ['group_name' => 'd1/d26-03a', 'students' => 40]));

    return compact('board', 'nurova', 'base', 'blocker', 'card');
}

it('unit-place 422 conflict_items: bitta dars ikki turda (o\'qituvchi va xona) band — har biri o\'z yozuvi; sig\'im — alohida (rad sababi emas)', function () {
    $s = ciSetup();
    $r = ciAs()->postJson(route('admin.timetable.cards.unit-place', $s['card']), ['week' => 11, 'layer' => 'internal', 'day' => 3, 'pair' => 3])
        ->assertUnprocessable();
    expect($r->json('error'))->toContain("O'qituvchi band: NUROVA Z. A.")->toContain('Auditoriya band: 123-amaliyot xonasi');
    $items = collect($r->json('conflict_items'));
    $teacher = $items->firstWhere('kind', 'teacher');
    expect($items->pluck('kind')->all())->toBe(['teacher', 'room', 'capacity'])
        ->and($teacher)->toMatchArray(['who' => 'NUROVA Z. A.', 'card_id' => $s['blocker']->id, 'piece_id' => $s['card']->id, 'groups' => ['d1/d26-01a'],
            'subject' => 'Tibbiy biologiya. Umumiy genetika', 'training_type' => 'practice', 'teacher' => 'NUROVA Z. A.', 'teacher_id' => $s['nurova']->id,
            'room_name' => '123-amaliyot xonasi', 'room_code' => 'R123', 'date' => '2026-11-25', 'time' => '10:00–11:20', 'piece_pair' => 3])
        ->and($items->firstWhere('kind', 'room'))->toMatchArray(['who' => '123-amaliyot xonasi', 'card_id' => $s['blocker']->id])
        ->and($items->firstWhere('kind', 'capacity'))->toMatchArray(['who' => '123-amaliyot xonasi', 'volume' => 17, 'students' => 40, 'over' => 23, 'blocking' => false]);
});

it('conflict_items: guruh band (boshqa fan, boshqa o\'qituvchi) va kun oxiri (4 soat 17:10 dan)', function () {
    $s = ciSetup();
    $other = ciTeacher('SHARIPOVA F. S.');
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-03a', 'subject_name' => 'Anatomiya', 'teacher_id' => $other->id,
        'teacher_name' => 'SHARIPOVA F. S.', 'auditorium_code' => null, 'auditorium_name' => null, 'day' => 4, 'pair' => 1]));
    $r = ciAs()->postJson(route('admin.timetable.cards.unit-place', $s['card']), ['week' => 11, 'layer' => 'internal', 'day' => 4, 'pair' => 1])->assertUnprocessable();
    expect(collect($r->json('conflict_items'))->firstWhere('kind', 'group'))->toMatchArray(['who' => 'd1/d26-03a', 'subject' => 'Anatomiya',
        'teacher' => 'SHARIPOVA F. S.', 'time' => '08:30–09:50']);
    // 4 soatlik dars (2 bo'lak) 17:10 dan — kun oxiri.
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-03a', 'students' => 40]));
    $r = ciAs()->postJson(route('admin.timetable.cards.unit-place', $s['card']), ['week' => 11, 'layer' => 'internal', 'day' => 5, 'pair' => 12])->assertUnprocessable();
    expect(collect($r->json('conflict_items'))->firstWhere('kind', 'day_end'))->toMatchArray(['hours' => 4, 'free' => 1, 'from' => '17:10']);
});

it('week-override 422 va unit-fix (Yaxlitlash) natijasi ham conflict_items bilan', function () {
    $s = ciSetup();
    $r = ciAs()->postJson(route('admin.timetable.cards.week-override', $s['card']), ['week' => 11, 'action' => 'move', 'day' => 3, 'pair' => 3])->assertUnprocessable();
    expect(collect($r->json('conflict_items'))->pluck('kind')->all())->toBe(['teacher', 'room']);
    // Bo'lingan birlik (seshanba + chorshanba), yaxlitlashning ikkala varianti 01a bilan to'qnashadi.
    $s['card']->update(['day' => 2, 'pair' => 1, 'students' => 15]);
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-03a', 'day' => 4, 'pair' => 3]));
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-03a', 'day' => 4, 'pair' => 5]));
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-09a', 'day' => 4, 'pair' => 7]));
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'd1/d26-09b', 'day' => 4, 'pair' => 1]));
    $f = ciAs()->postJson(route('admin.timetable.boards.unit-fix', $s['board']), ['week' => 11, 'action' => 'merge', 'card_ids' => [$s['card']->id]])
        ->assertUnprocessable()->json('failed.0');
    expect($f['conflict_items'][0])->toMatchArray(['kind' => 'teacher', 'who' => 'NUROVA Z. A.', 'groups' => ['d1/d26-09a']]);
});

it('xona NOMI: "Ko\'rsatish" (show.room_name) va bandlik matni ("Xona 123-amaliyot xonasi band: …"), agenda yozuvida o\'qituvchi', function () {
    $s = ciSetup();
    $s['card']->update(['day' => 3, 'pair' => 3]);
    $j = ciAs()->getJson(route('admin.timetable.boards.conflicts', $s['board']) . '?items=1&week=11')->assertOk()->json();
    $room = collect(data_get($j, 'weekly_items.' . $s['card']->id . '.11', []))->firstWhere('kind', 'room_busy');
    expect($room)->not->toBeNull()
        ->and($room['room'])->toBe('R123')
        ->and($room['room_name'])->toBe('123-amaliyot xonasi')
        ->and($room['show']['room_name'])->toBe('123-amaliyot xonasi');
    $ctl = app(TimetableController::class);
    $texts = (new ReflectionMethod($ctl, 'ownBusyTexts'))->invoke($ctl, ['teachers' => [], 'rooms' => ['R123' => 'd1/d26-01a · Fan · 10:00']], [], ['R123']);
    expect($texts)->toBe(['Xona 123-amaliyot xonasi band: d1/d26-01a · Fan · 10:00']);
    $show = (new ReflectionMethod($ctl, 'focusShow'))->invoke($ctl, ['kind' => 'room_busy', 'room_code' => 'R123', 'groups' => [], 'dates' => ['2026-11-25']]);
    expect($show['room_name'])->toBe('123-amaliyot xonasi');
    // Xona kunlik ro'yxati (teacher-agenda?room_code) — har darsda o'qituvchi.
    $a = ciAs()->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?room_code=R123&date=2026-11-25&range=day&layer=internal')->assertOk()->json('entries');
    expect(collect($a)->pluck('teacher')->unique()->values()->all())->toBe(['NUROVA Z. A.']);
});

it('Codex: xona kunlik ro\'yxati — birlashgan ma\'ruzada o\'qituvchi faqat qamrovdagi qismdan; qamrovdan tashqari dars yashirin (o\'qituvchisiz)', function () {
    $s = ciSetup();
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 601, 'name' => 'Biologiya kafedrasi', 'code' => 'B1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $head = ciTeacher('MUDIR', 'kafedra_mudiri');
    $head->forceFill(['department_hemis_id' => 601])->save();
    $outT = ciTeacher('BEGONA O.');
    $inT = ciTeacher('ICHKI I.');
    // Bir zal, bir vaqt, bir fan — bitta ma'ruza yozuviga birlashadi; birinchisi (kichik id) boshqa kafedrada.
    $lec = fn (string $g, string $spec, string $kaf, Teacher $t) => TimetableCard::create(array_replace($s['base'], ['training_type' => 'lecture',
        'subject_name' => 'Biologiya', 'group_name' => $g, 'specialty_name' => $spec, 'kafedra_name' => $kaf, 'teacher_id' => $t->id,
        'teacher_name' => $t->full_name, 'day' => 3, 'pair' => 7]));
    $lec('k1/d26-01a', 'kimyo', 'Kimyo kafedrasi', $outT);
    $lec('d1/d26-05a', 'davolash ishi', 'Biologiya kafedrasi', $inT);
    // Boshqa kafedraning alohida darsi shu xonada (08:30) — yashirin.
    TimetableCard::create(array_replace($s['base'], ['group_name' => 'k1/d26-02a', 'specialty_name' => 'kimyo', 'kafedra_name' => 'Kimyo kafedrasi',
        'subject_name' => 'Kimyo', 'teacher_id' => $outT->id, 'teacher_name' => 'BEGONA O.', 'day' => 3, 'pair' => 1]));
    $entries = collect(test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->getJson(route('admin.timetable.teacher-agenda', $s['board']) . '?room_code=R123&date=2026-11-25&range=day&layer=internal')->assertOk()->json('entries'));
    $lecture = $entries->firstWhere('start', '13:30');
    expect($lecture['hidden'])->toBeFalse()->and($lecture['teacher'])->toBe('ICHKI I.')
        ->and(json_encode($entries->all()))->not->toContain('BEGONA');
    expect($entries->firstWhere('start', '08:30'))->toMatchArray(['hidden' => true, 'label' => 'band: boshqa fakultet']);
});

it('Codex: band qilgan dars boshqa kalendarli dasturda — yozuvda uning shu sanadagi haftasi, kuni, parasi va xonasi (with_*)', function () {
    $s = ciSetup();
    $s['card']->update(['day' => 3, 'pair' => 3]);
    // Pediatriya kalendari bir hafta kech boshlanadi: 25.11 — uning 10-haftasi. Shablonda juma 15:00, 200-xona; 10-haftada — chorshanba 10:00, 123-xona.
    TimetableGridSetting::create(['board_id' => $s['board']->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'pediatriya', 'course' => 1,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15, 'start_date' => '2026-09-21']);
    Auditorium::create(['code' => 'R200', 'name' => '200-xona', 'volume' => 30, 'active' => true]);
    $other = TimetableCard::create(array_replace($s['base'], ['specialty_name' => 'pediatriya', 'group_name' => 'p1/p26-01a', 'day' => 5, 'pair' => 9,
        'auditorium_code' => 'R200', 'auditorium_name' => '200-xona']));
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 10, 'day' => 3, 'pair' => 3, 'start_half' => 0, 'cancelled' => false,
        'auditorium_code' => 'R123', 'auditorium_name' => '123-amaliyot xonasi']);
    $s['blocker']->delete();
    $j = ciAs()->getJson(route('admin.timetable.boards.conflicts', $s['board']) . '?items=1&week=11')->assertOk()->json();
    $items = collect(data_get($j, 'weekly_items.' . $s['card']->id . '.11', []));
    $room = $items->firstWhere('kind', 'room_busy');
    expect($room)->not->toBeNull()
        ->and($room)->toMatchArray(['with_card' => $other->id, 'with_week' => 10, 'with_day' => 3, 'with_pair' => 3, 'with_room' => 'R123',
            'with_room_name' => '123-amaliyot xonasi', 'date_from' => '2026-11-25'])
        ->and($items->firstWhere('kind', 'teacher_busy'))->toMatchArray(['with_week' => 10, 'with_pair' => 3]);
});
