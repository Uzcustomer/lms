<?php

use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBlockTicket;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableChangeRequest;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

/** HEMIS solishtirishdagi to'siqlar bo'yicha so'rovlar: Telegram, javob, hal qilish. */
function ticketSetup(): array
{
    config(['services.telegram.bot_token' => 'TESTTOKEN']);
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 77]])]);
    foreach (['kafedra_mudiri', 'registrator_ofisi', 'oqituvchi'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    foreach ([[501, 'Gigiyena kafedrasi'], [502, 'Anatomiya kafedrasi']] as [$id, $name]) {
        DB::table('departments')->insert(['department_hemis_id' => $id, 'name' => $name, 'code' => 'K' . $id, 'structure_type_code' => 12,
            'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }
    $mk = function (string $name, string $role, array $extra = []) {
        $t = new Teacher();
        $t->forceFill(array_merge([
            'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
            'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
            'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
            'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
            'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'is_active' => true,
            'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ], $extra))->save();
        $t->assignRole($role);

        return $t;
    };
    $head = $mk('MUDIR G.', 'kafedra_mudiri', ['department_hemis_id' => 501, 'department' => 'Gigiyena kafedrasi', 'telegram_chat_id' => '1001']);
    $other = $mk('MUDIR A.', 'kafedra_mudiri', ['department_hemis_id' => 502, 'department' => 'Anatomiya kafedrasi', 'telegram_chat_id' => '1002']);
    $reg = $mk('REGISTRATOR', 'registrator_ofisi');
    $teacher = $mk('OQITUVCHI A.', 'oqituvchi', ['telegram_chat_id' => '1003']);
    $blocker = $mk('BAND QILGAN B.', 'oqituvchi');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'start_half' => 0, 'weeks' => 15];
    $card = TimetableCard::create($base + ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => $teacher->id, 'teacher_name' => 'OQITUVCHI A.', 'day' => 2, 'pair' => 1]);
    $busy = TimetableCard::create($base + ['subject_name' => 'Anatomiya', 'group_name' => 'g-01a', 'kafedra_name' => 'Anatomiya kafedrasi',
        'day' => 2, 'pair' => 3]);

    return compact('head', 'other', 'reg', 'teacher', 'blocker', 'board', 'card', 'busy');
}

function ticketAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

function ticketItem(array $s, array $extra = []): array
{
    // Sana — kartaning kuni (2 = seshanba) keyingi haftada: HEMIS amali darsning haqiqiy sanasi bilan keladi.
    return array_merge(['card_id' => $s['card']->id, 'week' => 5, 'date' => now()->addWeek()->startOfWeek()->addDay()->toDateString(), 'start' => '08:30', 'end' => '09:50',
        'type' => 'room', 'text' => 'Xona band', 'group' => 'g-01a',
        'blocker' => ['source' => 'HEMIS', 'group' => 'x-02', 'subject' => 'Fiziologiya', 'teacher' => 'BAND QILGAN B.', 'room' => '101']], $extra);
}

function makeTicket(array $s, array $extra = []): TimetableBlockTicket
{
    ticketAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.block-tickets.send', $s['board']), ['items' => [ticketItem($s, $extra)]])
        ->assertOk()->assertJson(['created' => 1]);

    return TimetableBlockTicket::latest('id')->first();
}

it('registrator so\'rov yuboradi: o\'qituvchi, kafedra mudiri va xonani band qilganga; bugungi va takroriy o\'tkaziladi', function () {
    $s = ticketSetup();
    $res = ticketAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.block-tickets.send', $s['board']), ['items' => [
        ticketItem($s),
        ticketItem($s),                                                // takroriy
        ticketItem($s, ['date' => now()->toDateString()]),             // bugun — o'zgartirilmaydi
    ]])->assertOk()->json();

    expect($res['created'])->toBe(1)->and($res['skipped'])->toBe(2)->and($res['telegram_sent'])->toBe(2)
        ->and($res['without_telegram'])->toBe(['BAND QILGAN B.']);
    $t = TimetableBlockTicket::sole();
    expect(collect($t->recipients)->pluck('role', 'teacher_id')->all())->toBe([
        $s['teacher']->id => 'teacher', $s['head']->id => 'head', $s['blocker']->id => 'blocker',
    ])->and($t->head_id)->toBe($s['head']->id);
    Http::assertSent(fn ($r) => str_contains($r->url(), 'sendMessage') && str_contains((string) $r['reply_markup'], 'tb:' . $t->id . ':a'));

    // O'qituvchi yubora olmaydi
    ticketAs($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.block-tickets.send', $s['board']), ['items' => [ticketItem($s)]])->assertForbidden();
});

it('javob veb va Telegram tugmasi orqali yoziladi, begona javob bera olmaydi', function () {
    $s = ticketSetup();
    $t = makeTicket($s);

    ticketAs($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.block-tickets.respond', $t), ['response' => 'declined', 'note' => 'mumkin emas'])->assertOk();
    ticketAs($s['other'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.respond', $t), ['response' => 'accepted'])->assertForbidden();

    $this->postJson('/telegram/webhook/TESTTOKEN', [
        'callback_query' => ['id' => 'cq1', 'data' => 'tb:' . $t->id . ':a', 'from' => ['id' => 1001],
            'message' => ['message_id' => 77, 'chat' => ['id' => 1001], 'text' => 'xabar']],
    ]);
    $r = collect($t->fresh()->recipients)->keyBy('teacher_id');
    expect($r[$s['teacher']->id]['response'])->toBe('declined')->and($r[$s['teacher']->id]['note'])->toBe('mumkin emas')
        ->and($r[$s['head']->id]['response'])->toBe('accepted');
});

it('mudir xonani almashtiradi — LMS\'da darhol, so\'rov yopiladi; yopiq so\'rovni o\'zgartirib bo\'lmaydi', function () {
    $s = ticketSetup();
    Auditorium::create(['code' => 'A55', 'name' => '55-xona', 'active' => true, 'volume' => 30]);
    $t = makeTicket($s);

    ticketAs($s['other'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'room', 'auditorium_code' => 'A55'])->assertForbidden();
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'room', 'auditorium_code' => 'A55'])
        ->assertOk()->assertJson(['status' => 'resolved']);

    $ov = TimetableCardOverride::where('card_id', $s['card']->id)->where('week', 5)->sole();
    expect($ov->only(['day', 'pair', 'auditorium_code']))->toBe(['day' => 2, 'pair' => 1, 'auditorium_code' => 'A55'])
        ->and($s['card']->fresh()->auditorium_code)->toBeNull();

    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => 5])->assertStatus(422);
});

it('shu kunda vaqtni o\'zgartirish to\'qnashuvda bekor qilinadi, bo\'sh paraga esa qo\'llanadi', function () {
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher']);

    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => 3])->assertStatus(422);
    expect(TimetableCardOverride::count())->toBe(0)->and($t->fresh()->status)->toBe('open');

    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => 7])->assertOk();
    expect(TimetableCardOverride::sole()->only(['day', 'pair']))->toBe(['day' => 2, 'pair' => 7])
        ->and($t->fresh()->status)->toBe('resolved');
});

it('kunni o\'zgartirish registrator tasdig\'iga yuboriladi va so\'rov holati unga ergashadi', function () {
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher']);

    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'day', 'day' => 4, 'pair' => 1])
        ->assertOk()->assertJson(['status' => 'pending_registrar']);
    expect(TimetableCardOverride::count())->toBe(0);
    $cr = TimetableChangeRequest::sole();
    expect($cr->status)->toBe('pending')->and($cr->payload)->toMatchArray(['week' => 5, 'action' => 'move', 'day' => 4, 'pair' => 1]);

    $cr->update(['status' => TimetableChangeRequest::REJECTED, 'review_note' => 'yo\'q']);
    ticketAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.block-tickets.show', $t))->assertOk()
        ->assertJsonPath('ticket.status', 'open')->assertJsonPath('ticket.resolution.registrar_note', 'yo\'q');
});

it('ro\'yxat: mudir o\'z kafedrasini, o\'qituvchi o\'ziga yuborilganni ko\'radi', function () {
    $s = ticketSetup();
    makeTicket($s);
    $count = fn (Teacher $u, string $role) => count(ticketAs($u, $role)->getJson(route('admin.timetable.block-tickets.index', $s['board']))->assertOk()->json('tickets'));

    expect($count($s['reg'], 'registrator_ofisi'))->toBe(1)
        ->and($count($s['head'], 'kafedra_mudiri'))->toBe(1)
        ->and($count($s['teacher'], 'oqituvchi'))->toBe(1)
        ->and($count($s['other'], 'kafedra_mudiri'))->toBe(0);
});

/** NOT NULL ustunlarni sukut qiymatlari bilan to'ldirib yozadi (HEMIS jadvali qatori uchun). */
function tkInsert(string $table, array $data): void
{
    foreach (DB::select('SHOW COLUMNS FROM `' . $table . '`') as $col) {
        if ($col->Field === 'id' || array_key_exists($col->Field, $data) || $col->Null === 'YES' || $col->Default !== null) {
            continue;
        }
        $t = strtolower($col->Type);
        $data[$col->Field] = match (true) {
            str_contains($t, 'int') || str_contains($t, 'decimal') || str_contains($t, 'double') => 0,
            str_contains($t, 'datetime') || str_contains($t, 'timestamp') => now(),
            str_contains($t, 'date') => '2026-01-01',
            str_contains($t, 'json') => '[]',
            default => 'x',
        };
    }
    DB::table($table)->insert($data);
}

it('vaqtni o\'zgartirishda HEMIS bandligi va kutilayotgan bronlar ham tekshiriladi', function () {
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher']);
    $free = fn () => collect(ticketAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.block-tickets.show', $t))->assertOk()
        ->json('ticket.free_pairs'))->keyBy('pair');
    expect($free()->has(7))->toBeTrue()->and($free()->has(9))->toBeTrue();
    [$s7, $e7] = explode('–', $free()[7]['time']);

    // HEMIS: shu o'qituvchi shu sanada 7-katak vaqtida boshqa guruhda dars o'tadi.
    tkInsert('schedules', ['schedule_hemis_id' => 91, 'group_name' => 'boshqa-01', 'subject_name' => 'Boshqa fan', 'lesson_date' => $t->lesson_date->toDateString() . ' 00:00:00',
        'lesson_pair_start_time' => $s7 . ':00', 'lesson_pair_end_time' => $e7 . ':00', 'employee_id' => $s['teacher']->hemis_id, 'employee_name' => 'OQITUVCHI A.',
        'auditorium_code' => 'Z9', 'auditorium_name' => 'Z9']);
    expect($free()->has(7))->toBeFalse();
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => 7])
        ->assertStatus(422)->assertJsonFragment(['error' => "HEMIS: o'qituvchi band — boshqa-01 · Boshqa fan {$s7}-{$e7}"]);

    // Kutilayotgan bron: shu guruhning boshqa darsi 5-hafta 9-katakka ko'chirishni so'ragan (tasdiq kutilmoqda).
    TimetableChangeRequest::create(['board_id' => $s['board']->id, 'requester_name' => 'X', 'requester_role' => 'kafedra_mudiri',
        'route_name' => 'admin.timetable.cards.week-override', 'target_key' => 'k', 'params' => ['board' => $s['board']->id, 'card' => $s['busy']->id],
        'payload' => ['week' => 5, 'action' => 'move', 'day' => 2, 'pair' => 9, 'layer' => 'internal'], 'kind' => 'move', 'summary' => 's', 'details' => [],
        'status' => TimetableChangeRequest::PENDING]);
    expect($free()->has(9))->toBeFalse();
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => 9])->assertStatus(422);
    expect(TimetableCardOverride::count())->toBe(0)->and($t->fresh()->status)->toBe('open');
});

it('"faqat admin" doskasidagi so\'rovni boshqa rollar ko\'ra ham, o\'zgartira ham olmaydi', function () {
    $s = ticketSetup();
    Auditorium::create(['code' => 'A55', 'name' => '55-xona', 'active' => true, 'volume' => 30]);
    $t = makeTicket($s);
    $s['board']->forceFill(['admin_only' => true])->save();

    ticketAs($s['teacher'], 'oqituvchi')->getJson(route('admin.timetable.block-tickets.show', $t))->assertForbidden();
    ticketAs($s['teacher'], 'oqituvchi')->postJson(route('admin.timetable.block-tickets.respond', $t), ['response' => 'accepted'])->assertForbidden();
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'room', 'auditorium_code' => 'A55'])->assertForbidden();
    ticketAs($s['reg'], 'registrator_ofisi')->getJson(route('admin.timetable.block-tickets.index', $s['board']))->assertForbidden();
    ticketAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.block-tickets.send', $s['board']), ['items' => [ticketItem($s)]])->assertForbidden();
    expect(TimetableCardOverride::count())->toBe(0)->and($t->fresh()->status)->toBe('open');
});

it('bo\'sh xonalar darsning hozirgi joyi (ko\'chirilgan vaqti) bo\'yicha HEMIS bandligini hisobga oladi', function () {
    $s = ticketSetup();
    foreach (['R1', 'R2'] as $c) {
        Auditorium::create(['code' => $c, 'name' => $c, 'active' => true, 'volume' => 30]);
    }
    $t = makeTicket($s);
    // Dars shu haftada 7-katakka ko'chirilgan (masalan, boshqa so'rov bilan).
    TimetableCardOverride::create(['card_id' => $s['card']->id, 'week' => 5, 'day' => 2, 'pair' => 7, 'cancelled' => false]);
    $time = collect(ticketAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.block-tickets.show', $t))->json('ticket.free_pairs'))
        ->firstWhere('pair', 9)['time'] ?? null;
    $slots = (new ReflectionMethod(\App\Http\Controllers\Admin\TimetableController::class, 'hemisSlotTimes'));
    $slots->setAccessible(true);
    $st = $slots->invoke(app(\App\Http\Controllers\Admin\TimetableController::class), $s['board']);
    $date = $t->lesson_date->toDateString() . ' 00:00:00';
    // R1 — yangi vaqtda (7-katak) HEMIS'da band; R2 — faqat eski vaqtda (08:30) band.
    tkInsert('schedules', ['schedule_hemis_id' => 92, 'group_name' => 'boshqa-02', 'subject_name' => 'X', 'lesson_date' => $date,
        'lesson_pair_start_time' => $st[7][0] . ':00', 'lesson_pair_end_time' => $st[8][1] . ':00', 'employee_id' => 1, 'auditorium_code' => 'R1', 'auditorium_name' => 'R1']);
    tkInsert('schedules', ['schedule_hemis_id' => 93, 'group_name' => 'boshqa-03', 'subject_name' => 'Y', 'lesson_date' => $date,
        'lesson_pair_start_time' => '08:30:00', 'lesson_pair_end_time' => '09:50:00', 'employee_id' => 2, 'auditorium_code' => 'R2', 'auditorium_name' => 'R2']);

    $rooms = collect(ticketAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.block-tickets.show', $t))->assertOk()
        ->json('ticket.free_rooms'))->pluck('code')->all();
    expect($rooms)->not->toContain('R1')->toContain('R2');
});

it('registrator tasdig\'ini kutayotgan so\'rov bekor qilinsa — bog\'liq o\'zgarish so\'rovi ham bekor bo\'ladi', function () {
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher']);
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'day', 'day' => 4, 'pair' => 1])
        ->assertOk()->assertJson(['status' => 'pending_registrar']);

    ticketAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.block-tickets.cancel', $t))->assertOk();
    expect($t->fresh()->status)->toBe('cancelled')
        ->and(TimetableChangeRequest::sole()->status)->toBe(TimetableChangeRequest::CANCELLED);
});

it('kunni o\'zgartirish so\'rovida xabar oluvchilar (o\'qituvchi, guruh) bor; o\'tgan yoki bugungi kunga ko\'chirib bo\'lmaydi', function () {
    \Illuminate\Support\Carbon::setTestNow('2026-11-02 09:00:00');   // dushanba
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher', 'date' => '2026-11-03']);   // seshanba (kartaning kuni)

    // Dushanba — bugun: rad etiladi, bo'sh vaqt ham taklif qilinmaydi.
    expect(ticketAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.block-tickets.show', $t) . '?day=1')->json('ticket.free_pairs'))->toBe([]);
    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'day', 'day' => 1, 'pair' => 1])
        ->assertStatus(422);
    expect(TimetableChangeRequest::count())->toBe(0)->and($t->fresh()->status)->toBe('open');

    ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'day', 'day' => 4, 'pair' => 1])->assertOk();
    $d = TimetableChangeRequest::sole()->details;
    expect($d['teacher_ids'] ?? [])->toContain($s['teacher']->id)
        ->and($d['groups'] ?? [])->toContain('g-01a');
    \Illuminate\Support\Carbon::setTestNow();
});

it('vaqtni o\'zgartirishda jadvaldan tashqari yoki para boshiga to\'g\'ri kelmaydigan katak rad etiladi', function () {
    $s = ticketSetup();
    $t = makeTicket($s, ['type' => 'teacher']);
    foreach ([24, 2] as $pair) {   // 24 — jadvalda yo'q; 2 — to'liq para (2 yarim katak) uchun juft katak
        ticketAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.block-tickets.resolve', $t), ['kind' => 'time', 'pair' => $pair])->assertStatus(422);
    }
    expect(TimetableCardOverride::count())->toBe(0)->and($t->fresh()->status)->toBe('open');
});
