<?php

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

// Nofaol xonalar (auditoriums.active = false): biriktirilmaydi va saqlanmaydi (422), tanlov ro'yxatlarida yo'q, kartada turgani —
// "(nofaol)" belgisi va ogohlantirish (/data), auditoriya sinxronidan keyin hisobot va Excel.
beforeEach(fn () => $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-01 09:00', 'Asia/Tashkent')));

function irAdmin(): Teacher
{
    $t = new Teacher();
    $t->forceFill(['hemis_id' => random_int(100000, 999999), 'full_name' => 'ADMIN', 'short_name' => 'ADMIN', 'first_name' => 'A', 'second_name' => 'A',
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010, 'gender' => 'm',
        'department' => 'x', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x', 'employee_status' => 'x', 'employee_type' => 'x',
        'contract_number' => '1', 'decree_number' => '1', 'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true,
        'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x')])->save();
    Role::findOrCreate('admin', 'web');
    $t->assignRole('admin');

    return $t;
}

function irAs()
{
    return test()->withoutVite()->actingAs(irAdmin(), 'teacher')->withSession(['active_role' => 'admin']);
}

function irSetup(): array
{
    $board = TimetableBoard::create(['name' => 'Kuzgi doska', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12,
        'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 30, 'active' => true]);
    Auditorium::create(['code' => 'R404', 'name' => '404', 'volume' => 30, 'active' => false]);
    $card = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Anatomiya', 'group_name' => 'd1/d26-01a', 'kafedra_name' => 'Anatomiya kafedrasi', 'day' => 1, 'pair' => 1,
        'auditorium_code' => 'R404', 'auditorium_name' => '404']);

    return compact('board', 'card');
}

const IR_UNIT = ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 1, 'subject_name' => 'Anatomiya',
    'training_type' => 'practice', 'oqim_label' => '1-oqim', 'group_name' => 'd1/d26-01a'];

it('nofaol xona bilan assign-room va boshqa xona endpointlari — 422 "Xona nofaol — boshqa xona tanlang"; bo\'sh (null) va faol — ruxsat', function () {
    $s = irSetup();
    $r = irAs()->postJson(route('admin.timetable.assign-room', $s['board']), IR_UNIT + ['auditorium_code' => 'R404'])->assertStatus(422);
    expect($r->json('error'))->toStartWith('Xona nofaol — boshqa xona tanlang')->and($r->json('code'))->toBe('room_inactive');
    // Haftalik yozuv, karta rekvizitlari, Takliflar — ham.
    irAs()->postJson(route('admin.timetable.cards.week-override', $s['card']), ['week' => 2, 'action' => 'move', 'day' => 2, 'pair' => 1, 'auditorium_code' => 'R404'])
        ->assertStatus(422)->assertJson(['code' => 'room_inactive']);
    irAs()->postJson(route('admin.timetable.cards.update', $s['card']), ['auditorium_code' => 'R404'])->assertStatus(422);
    irAs()->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['card_id' => $s['card']->id, 'week' => 2,
        'actions' => [['op' => 'room', 'card_id' => $s['card']->id, 'week' => 2, 'auditorium_code' => 'R404']]])->assertStatus(422)->assertJson(['code' => 'room_inactive']);
    // Faol xona va xonani olib tashlash — ruxsat.
    irAs()->postJson(route('admin.timetable.assign-room', $s['board']), IR_UNIT + ['auditorium_code' => 'R305'])->assertOk();
    expect($s['card']->fresh()->auditorium_code)->toBe('R305');
    irAs()->postJson(route('admin.timetable.assign-room', $s['board']), IR_UNIT + ['auditorium_code' => null])->assertOk();
    expect($s['card']->fresh()->auditorium_code)->toBeNull();
});

it('biriktirish oynasi: nofaol xona ro\'yxatda (reytingda) yo\'q; kartadagi nofaol xona /data da — "(nofaol)" belgisi uchun', function () {
    $s = irSetup();
    $rank = irAs()->getJson(route('admin.timetable.unit-busy', $s['board']) . '?' . http_build_query(IR_UNIT))->assertOk()->json('room_rank.order');
    expect($rank)->toContain('R305')->not->toContain('R404');
    $data = irAs()->getJson(route('admin.timetable.boards.data', $s['board']))->assertOk()->json();
    expect($data['inactive_rooms'])->toBe(['R404' => '404']);

    // Sikl biriktirish oynasi: xonalar — faqat faol; hozirgi nofaol xona — current.auditorium_active = false (nomi bilan).
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 1,
        'subject_name' => 'Anatomiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi', 'course' => 1,
        'group_name' => 'd1/d26-01a', 'subject_name' => 'Anatomiya', 'start_index' => 0, 'training_type' => 'practice']);
    $o = irAs()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), ['specialty_name' => 'davolash ishi', 'course' => 1,
        'group_name' => 'd1/d26-01a', 'subject_name' => 'Anatomiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal'])->assertOk()->json();
    expect(array_column($o['rooms'], 'code'))->toContain('R305')->not->toContain('R404')
        ->and($o['current'])->toMatchArray(['auditorium_code' => 'R404', 'auditorium_name' => '404', 'auditorium_active' => false]);
    irAs()->postJson(route('admin.timetable.cycle-assign', $s['board']), ['specialty_name' => 'davolash ishi', 'course' => 1, 'group_name' => 'd1/d26-01a',
        'subject_name' => 'Anatomiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal', 'auditorium_code' => 'R404'])->assertStatus(422);
});

it('auditoriya sinxronidan keyin: "N ta karta nofaol xonada" (doskalar bo\'yicha) va Excel ro\'yxati', function () {
    $s = irSetup();
    \App\Models\TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => 3, 'day' => 2, 'pair' => 1, 'auditorium_code' => 'R404', 'auditorium_name' => '404']);
    \Illuminate\Support\Facades\Http::fake(['*auditorium-list*' => \Illuminate\Support\Facades\Http::response(['data' => ['items' => [
        ['code' => 'R305', 'name' => '305', 'volume' => 30, 'active' => true], ['code' => 'R404', 'name' => '404', 'volume' => 30, 'active' => false],
    ], 'pagination' => ['pageCount' => 1]]])]);
    $res = irAs()->postJson(route('admin.reports.auditorium-list.sync'))->assertOk()->json();
    expect($res['inactive_room_cards_total'])->toBe(1)
        ->and($res['message'])->toContain('Dars jadvali: 1 ta karta nofaol xonada')
        ->and($res['inactive_room_cards'][0])->toMatchArray(['board_id' => $s['board']->id, 'board_name' => 'Kuzgi doska', 'count' => 1])
        ->and($res['inactive_room_cards'][0]['excel_url'])->toBe(route('admin.timetable.boards.inactive-room-cards', $s['board']));

    $r = irAs()->get(route('admin.timetable.boards.inactive-room-cards', $s['board']))->assertOk();
    expect($r->headers->get('content-type'))->toContain('spreadsheetml');
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($r->baseResponse->getFile()->getPathname());
    $rows = $book->getActiveSheet()->toArray();
    expect($rows[0][9])->toBe('Xona')->and(count($rows))->toBe(3)   // karta va 3-hafta yozuvi
        ->and([$rows[1][5], $rows[1][8], $rows[1][10]])->toBe(['Anatomiya', 'R404', 'karta'])
        ->and($rows[2][10])->toBe('3-hafta');
});
