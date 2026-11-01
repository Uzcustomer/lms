<?php

use App\Models\Teacher;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableBoard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function sugSetup(): array
{
    foreach (['kafedra_mudiri', 'oquv_bolimi', 'superadmin'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    DB::table('departments')->insert(['department_hemis_id' => 501, 'name' => 'Gigiyena kafedrasi', 'code' => 'G1', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => now(), 'updated_at' => now()]);
    $mk = function (string $name, string $role, array $extra = []) {
        $t = new Teacher();
        $t->forceFill(array_merge([
            'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
            'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
            'gender' => 'm', 'department' => 'Boshqa', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
            'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
            'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
            'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ], $extra))->save();
        $t->assignRole($role);

        return $t;
    };
    $head = $mk('MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 501]);
    $oquv = $mk('OQUV', 'oquv_bolimi');
    $admin = $mk('ADMIN', 'superadmin');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15];
    $own = TimetableCard::create($base + ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi']);
    $foreign = TimetableCard::create($base + ['subject_name' => 'Anatomiya', 'group_name' => 'g-01a', 'kafedra_name' => 'Anatomiya kafedrasi']);

    return compact('head', 'oquv', 'admin', 'board', 'own', 'foreign');
}

function sugAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}


function sugTeacher(string $name, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Gigiyena kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01',
        'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ], $extra))->save();

    return $t;
}

it('takliflar: joylanmagan karta uchun boshqa kun, boshqa hafta va oxirida boshqa o\'qituvchi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $free = sugTeacher('BOSH O.');
    // own (Gigiyena, g-01a) — tashqi joyi 2/3; o'qituvchisi shu vaqtda boshqa guruhda band
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    $other = TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks', 'kafedra_name']) + ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    // 5-haftada own o'tilmaydi — "boshqa hafta" taklifi uchun
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 5, 'cancelled' => true]);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $card = collect($res['cards'])->firstWhere('id', $s['own']->id);
    expect($card)->not->toBeNull()
        ->and($card['reason'])->toContain("O'qituvchi band");
    $types = collect($card['suggestions'])->pluck('type')->all();
    expect($types)->toContain('blocker')->toContain('slot')->toContain('day')->toContain('week')->toContain('teacher')
        ->and(end($types))->toBe('teacher');
    $teacher = collect($card['suggestions'])->firstWhere('type', 'teacher');
    expect($teacher['title'])->toContain('BOSH O.')
        ->and($teacher['actions'][0])->toMatchArray(['op' => 'teacher', 'teacher_id' => $free->id]);
    // Avval yangi haftaga ko'chiriladi (server rad etishi mumkin bo'lgan qadam), keyin shu hafta bekor qilinadi —
    // ko'chirish rad etilsa, dars ikkala haftadan ham yo'qolib qolmaydi.
    $week = collect($card['suggestions'])->firstWhere('type', 'week');
    expect($week['actions'][0])->toMatchArray(['op' => 'move', 'week' => 5, 'day' => 2, 'pair' => 3])
        ->and($week['actions'][1])->toMatchArray(['op' => 'cancel', 'week' => 3]);
});

it('takliflar: ma\'ruza vaqtini siljitish oxirroqda, o\'qituvchi almashtirishdan oldin', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    sugTeacher('BOSH O.');
    $lec = $s['own'];
    $lec->update(['training_type' => 'lecture', 'ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    $other = TimetableCard::create($lec->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'len_half', 'weeks', 'kafedra_name']) + ['training_type' => 'practice', 'subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $types = collect(collect($res['cards'])->firstWhere('id', $lec->id)['suggestions'])->pluck('type')->values()->all();
    $firstLecture = array_search('lecture', $types, true);
    $firstTeacher = array_search('teacher', $types, true);
    expect($types)->toContain('blocker')->toContain('lecture')->not->toContain('slot')->not->toContain('day')
        ->and($firstLecture)->toBeLessThan($firstTeacher)
        ->and(array_search('blocker', $types, true))->toBeLessThan($firstLecture);
});

it('haftalik ko\'chirishda faqat shu hafta uchun boshqa xona beriladi', function () {
    $s = sugSetup();
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '101', 'volume' => 30, 'active' => true]);
    sugAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $s['own']),
        ['week' => 2, 'action' => 'move', 'day' => 1, 'pair' => 1, 'auditorium_code' => 'R1'])->assertOk();
    $o = TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 2)->first();
    expect([$o->day, $o->pair, $o->auditorium_code])->toBe([1, 1, 'R1'])
        ->and($s['own']->fresh()->auditorium_code)->toBeNull();
});

it('rad etilgan haftalik ko\'chirish haftalik xonani o\'chirib yubormaydi, yangi xona bilan tekshiradi', function () {
    $s = sugSetup();
    \App\Models\Auditorium::create(['code' => 'R0', 'name' => '100', 'volume' => 30, 'active' => true]);
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '101', 'volume' => 30, 'active' => true]);
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 2, 'day' => 1, 'pair' => 1, 'auditorium_code' => 'R0', 'auditorium_name' => '100']);
    // Shu guruhning boshqa darsi 2-haftada 1-kun 3-parada — u yerga ko'chirish rad etiladi
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 2, 'day' => 1, 'pair' => 3]);
    // Boshqa guruhning darsi 101-xonada 1-kun 5-parada
    $other = TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks']) + ['subject_name' => 'Biologiya', 'group_name' => 'g-02', 'auditorium_code' => 'R1', 'auditorium_name' => '101']);
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 2, 'day' => 1, 'pair' => 5]);

    $as = sugAs($s['admin'], 'superadmin');
    $as->postJson(route('admin.timetable.cards.week-override', $s['own']),
        ['week' => 2, 'action' => 'move', 'day' => 1, 'pair' => 3, 'auditorium_code' => 'R1'])->assertStatus(422);
    // Tekshiruv eski (100) emas, berilgan (101) xona bilan: 101 shu vaqtda band
    $err = $as->postJson(route('admin.timetable.cards.week-override', $s['own']),
        ['week' => 2, 'action' => 'move', 'day' => 1, 'pair' => 5, 'auditorium_code' => 'R1'])->assertStatus(422)->json('error');
    expect($err)->toContain('Auditoriya band: 101');
    $o = TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 2)->first();
    expect([$o->day, $o->pair, $o->auditorium_code])->toBe([1, 1, 'R0']);
});

function sugCard(array $s, array $attrs): TimetableCard
{
    return TimetableCard::create($attrs + $s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'len_half', 'weeks']));
}

it('takliflar: sikl fanlari haftalik joylanmaganlar qatoriga kirmaydi', function () {
    $s = sugSetup();
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
        'course' => 2, 'subject_name' => 'Anatomiya', 'mode' => 'cycle', 'cycle_days' => 1]);
    $variant = sugCard($s, ['subject_name' => 'Anatomiya (a)', 'group_name' => 'g-01b']);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $ids = collect($res['cards'])->pluck('id')->all();
    expect($ids)->toContain($s['own']->id)->not->toContain($s['foreign']->id)->not->toContain($variant->id)
        ->and($res['total'])->toBe(1);
});

it('takliflar: to\'sib turgan darsning shu haftadagi xonasi rejalashda saqlanadi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    // To'sib turgan dars: asosiy xonasi 101, 3-haftada esa 102-xonada
    $b = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.',
        'auditorium_code' => 'R1', 'auditorium_name' => '101']);
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 2, 'pair' => 3, 'auditorium_code' => 'R2', 'auditorium_name' => '102']);
    // 102-xona 3-haftada 2-kun 1–2 da band
    $z = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-02', 'auditorium_code' => 'R2', 'auditorium_name' => '102']);
    TimetableCardOverride::create(['card_id' => $z->id, 'week' => 3, 'day' => 2, 'pair' => 1]);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $bl = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->firstWhere('type', 'blocker');
    // 2-kun 1–2 emas (u yerda 102-xona band, ko'chirish rad etilardi) — 2-kun 5–6
    expect($bl['actions'][0])->toMatchArray(['card_id' => $b->id, 'week' => 3, 'day' => 2, 'pair' => 5]);
});

it('takliflar: boshqa haftada band o\'qituvchi almashtirishga taklif qilinmaydi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $x = sugTeacher('XBAND O.');
    sugTeacher('YBOSH O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    // 3-haftada o'qituvchi shu vaqtda boshqa guruhda band
    $other = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    // Karta 4-haftada joylashgan; XBAND O. o'sha vaqtda boshqa darsda — u almashtirilsa 4-haftada ustma-ust tushadi
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 4, 'day' => 1, 'pair' => 1]);
    $xc = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-02', 'teacher_id' => $x->id, 'teacher_name' => 'XBAND O.']);
    TimetableCardOverride::create(['card_id' => $xc->id, 'week' => 4, 'day' => 1, 'pair' => 1]);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $titles = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->where('type', 'teacher')->pluck('title')->implode(' | ');
    expect($titles)->toContain('YBOSH O.')->not->toContain('XBAND O.');
});

it('takliflar: to\'sib turgan dars butun blok bo\'lib (2 + 1 soat) bir kunda ko\'chadi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    $mk = fn (int $len) => TimetableCard::create($s['own']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'training_type', 'weeks', 'kafedra_name']) + ['len_half' => $len, 'subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    $b1 = $mk(2);
    $b2 = $mk(1);
    TimetableCardOverride::create(['card_id' => $b1->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    TimetableCardOverride::create(['card_id' => $b2->id, 'week' => 3, 'day' => 2, 'pair' => 5]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $blk = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->firstWhere('type', 'blocker');
    expect($blk)->not->toBeNull();
    $acts = collect($blk['actions']);
    $moves = $acts->where('op', 'move')->whereIn('card_id', [$b1->id, $b2->id])->values();
    expect($acts->where('op', 'reset')->pluck('card_id')->sort()->values()->all())->toBe([$b1->id, $b2->id])
        ->and($moves)->toHaveCount(2)
        ->and($moves[0]['day'])->toBe($moves[1]['day'])
        ->and($moves[1]['pair'])->toBe($moves[0]['pair'] + 2)
        ->and($acts->last())->toMatchArray(['op' => 'move', 'card_id' => $s['own']->id, 'day' => 2, 'pair' => 3]);

    // Takliflarni ketma-ket qo'llash ishlaydi
    foreach ($blk['actions'] as $a) {
        $body = ['week' => $a['week'], 'action' => $a['op'] === 'reset' ? 'reset' : 'move'] + ($a['op'] === 'move' ? ['day' => $a['day'], 'pair' => $a['pair']] : []);
        sugAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $a['card_id']), $body)->assertOk();
    }
    expect(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('pair'))->toBe(3);
});

it('takliflar: shablonda joylashgan blokni surish taklifi qo\'llanganda ham ishlaydi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    // To'sib turgan dars (2 + 1 soat) shablonda (barcha haftalarda) 2-kun 3–5 da — "reset" uni o'sha joyga qaytaradi
    $b1 = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'len_half' => 2, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.', 'day' => 2, 'pair' => 3]);
    $b2 = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'len_half' => 1, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.', 'day' => 2, 'pair' => 5]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $blk = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->firstWhere('type', 'blocker');
    expect($blk)->not->toBeNull();
    foreach ($blk['actions'] as $a) {
        $body = ['week' => $a['week'], 'action' => $a['op'] === 'reset' ? 'reset' : 'move'] + ($a['op'] === 'move' ? ['day' => $a['day'], 'pair' => $a['pair']] : []);
        sugAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $a['card_id']), $body)->assertOk();
    }
    expect(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('pair'))->toBe(3);
});

it('takliflar: shu kunning boshqa vaqti uchun ikki variant beriladi', function () {
    $s = sugSetup();
    $busy = sugTeacher('BAND O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    $other = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $other->id, 'week' => 3, 'day' => 2, 'pair' => 3]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $slots = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->where('type', 'slot')->values();
    expect($slots)->toHaveCount(2)
        ->and($slots[0]['actions'][0]['pair'])->not->toBe($slots[1]['actions'][0]['pair']);
});

it('takliflar: shu kundagi shu fanning alohida darsi blokka qo\'shilmaydi', function () {
    $s = sugSetup();
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    // Gigiyena g-01a: 3-haftada 2-kun 1–2 da (alohida dars) va 7–8 da; joylanmagan karta 7–8 ning ketiga (9) ulanadi
    $a = sugCard($s, ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a']);
    $b = sugCard($s, ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a']);
    TimetableCardOverride::create(['card_id' => $a->id, 'week' => 3, 'day' => 2, 'pair' => 1]);
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 2, 'pair' => 7]);
    // 9–10 da shu guruhning boshqa darsi
    $k = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-01a']);
    TimetableCardOverride::create(['card_id' => $k->id, 'week' => 3, 'day' => 2, 'pair' => 9]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $sugs = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions']);
    $slot = $sugs->firstWhere('type', 'slot');
    expect($slot)->not->toBeNull();
    // Faqat 7–8 dagi qism shu karta bilan birga ko'chadi; 1–2 dagi alohida darsga tegilmaydi
    $touched = $sugs->whereIn('type', ['slot', 'day'])->flatMap(fn ($x) => collect($x['actions'])->pluck('card_id'))->unique()->values()->all();
    expect($touched)->toContain($b->id)->not->toContain($a->id);
});

it('takliflar: dars shu fanning shu turdagi darsi bor kunga ko\'chirishga taklif qilinmaydi', function () {
    $s = sugSetup();
    TimetableGridSetting::where('board_id', $s['board']->id)->where('course', 2)->update(['days' => 3]);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    // Gigiyena g-01a: 2-kun 1–2 da (karta shunga ulanadi) va 3-kun 7–8 da
    $a = sugCard($s, ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a']);
    $b = sugCard($s, ['subject_name' => 'Gigiyena', 'group_name' => 'g-01a']);
    TimetableCardOverride::create(['card_id' => $a->id, 'week' => 3, 'day' => 2, 'pair' => 1]);
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 3, 'pair' => 7]);
    // Ulanish joyi (2-kun 3) band
    $k = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-01a']);
    TimetableCardOverride::create(['card_id' => $k->id, 'week' => 3, 'day' => 2, 'pair' => 3]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $days = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->where('type', 'day')
        ->map(fn ($x) => collect($x['actions'])->firstWhere('op', 'move')['day'])->values()->all();
    // 3-kunda Gigiyena allaqachon bor — u kunga emas, faqat 1-kunga
    expect($days)->toBe([1]);
});

it('takliflar: to\'sib turgan dars ham shu fanning darsi bor kunga surilmaydi', function () {
    $s = sugSetup();
    TimetableGridSetting::where('board_id', $s['board']->id)->where('course', 2)->update(['days' => 3]);
    $busy = sugTeacher('BAND O.');
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    // To'sib turgan Biologiya (g-01b) 2-kun 3–4 da; 2-kunning qolgan joylari shu guruhda band
    $bio = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => $busy->id, 'teacher_name' => 'BAND O.']);
    TimetableCardOverride::create(['card_id' => $bio->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    foreach ([[1, 2], [5, 8]] as [$p, $l]) {
        $f = sugCard($s, ['subject_name' => 'Kimyo ' . $p, 'group_name' => 'g-01b', 'len_half' => $l]);
        TimetableCardOverride::create(['card_id' => $f->id, 'week' => 3, 'day' => 2, 'pair' => $p]);
    }
    // 1-kunda g-01b ning boshqa Biologiya darsi bor
    $bio2 = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b']);
    TimetableCardOverride::create(['card_id' => $bio2->id, 'week' => 3, 'day' => 1, 'pair' => 9]);

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')
        ->assertOk()->json();
    $blk = collect(collect($res['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->firstWhere('type', 'blocker');
    expect($blk)->not->toBeNull()
        ->and(collect($blk['actions'])->firstWhere('card_id', $bio->id)['day'])->toBe(3);
});

it('joylanmagan kartalar Exceli sikl fanlarini ko\'rsatmaydi', function () {
    $s = sugSetup();
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
        'course' => 2, 'subject_name' => 'Anatomiya', 'mode' => 'cycle', 'cycle_days' => 1]);
    // Sikl fanining bo'lak nomli kartasi ham chiqmaydi
    sugCard($s, ['subject_name' => 'Anatomiya (a)', 'group_name' => 'g-01b']);
    $res = sugAs($s['admin'], 'superadmin')->get(route('admin.timetable.boards.unplaced-export', $s['board']) . '?layer=internal&week=3')->assertOk();
    $file = tempnam(sys_get_temp_dir(), 'unp') . '.xlsx';
    file_put_contents($file, $res->streamedContent());
    $subjects = collect(\PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet()->toArray())->slice(2)->pluck(6)->filter()->values()->all();
    @unlink($file);
    expect($subjects)->toBe(['Gigiyena']);
});

it('takliflar: to\'qnashuvli karta (bir xonada bir vaqtda) — ro\'yxatda, birinchi taklif bo\'sh xona; qo\'llangach to\'qnashuv va takliflar yo\'q', function () {
    $s = sugSetup();
    foreach ([['R215', '215', 30], ['R216', '216', 30], ['R217', '217', 5]] as [$code, $name, $vol]) {
        \App\Models\Auditorium::create(['code' => $code, 'name' => $name, 'volume' => $vol, 'active' => true]);
    }
    $base = ['board_id' => $s['board']->id, 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Odam anatomiyasi', 'kafedra_name' => 'Anatomiya kafedrasi', 'auditorium_code' => 'R215', 'auditorium_name' => '215'];
    // 7-haftada juma V para (9-yarim-slot), ikkalasi 215-xonada: 1-son davolash 1-kurs va Pediatriya 2-kurs.
    $a = TimetableCard::create($base + ['faculty_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi', 'course' => 1,
        'oqim_label' => '6-oqim', 'group_name' => 'd1/d26-06b', 'teacher_id' => sugTeacher('USTOZ A.')->id, 'teacher_name' => 'USTOZ A.']);
    $b = TimetableCard::create($base + ['faculty_name' => 'Pediatriya', 'specialty_name' => 'Pediatriya', 'course' => 2,
        'oqim_label' => '2-oqim', 'group_name' => 'p/p25-02b', 'teacher_id' => sugTeacher('USTOZ B.')->id, 'teacher_name' => 'USTOZ B.']);
    foreach ([$a, $b] as $c) {
        TimetableCardOverride::create(['card_id' => $c->id, 'week' => 7, 'day' => 5, 'pair' => 9, 'start_half' => 0, 'cancelled' => false]);
    }
    $ctl = app(\App\Http\Controllers\Admin\TimetableController::class);
    expect($ctl->internalConflicts($s['board'])['count'])->toBe(2);
    $params = ['week' => 7, 'faculty_names' => ['1-son davolash'], 'courses' => [1]];

    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?' . http_build_query($params))
        ->assertOk()->json();
    expect($res['total'])->toBe(1)->and($res['unplaced_total'])->toBe(0)->and($res['conflict_total'])->toBe(1)
        ->and($res['cards'])->toHaveCount(1);
    $card = $res['cards'][0];
    expect($card['id'])->toBe($a->id)->and($card['conflict'])->toBeTrue()
        ->and($card['reason'])->toBe('Xona 215 band: p/p25-02b · Odam anatomiyasi · Juma V para');
    // Birinchi — shu vaqtda bo'sh xona (217 sig'imi yetmaydi), keyin ikkinchi tomonning xonasi (boshqa yo'nalish belgisi bilan).
    expect($card['suggestions'][0])->toMatchArray(['type' => 'room', 'title' => 'Xonani almashtirish: 216 (30 o\'rin) — Juma, 5-para',
        'actions' => [['op' => 'move', 'card_id' => $a->id, 'week' => 7, 'day' => 5, 'pair' => 9, 'auditorium_code' => 'R216']]])
        ->and($card['suggestions'][1])->toMatchArray(['type' => 'room',
            'title' => 'Ikkinchi tomon — «Odam anatomiyasi» (p/p25-02b) xonasini almashtirish: 216 (30 o\'rin) — Juma, 5-para',
            'actions' => [['op' => 'move', 'card_id' => $b->id, 'week' => 7, 'day' => 5, 'pair' => 9, 'auditorium_code' => 'R216']],
            'note' => 'Faqat shu hafta; boshqa yo\'nalish: Pediatriya, 2-kurs'])
        ->and(collect($card['suggestions'])->pluck('type')->all())->not->toContain('teacher');

    // Qo'llash (sahifadagi kabi week-override) — to'qnashuv yo'qoladi, takliflar bo'sh.
    $act = $card['suggestions'][0]['actions'][0];
    sugAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.cards.week-override', $a),
        ['week' => $act['week'], 'action' => 'move', 'day' => $act['day'], 'pair' => $act['pair'], 'layer' => 'internal', 'auditorium_code' => $act['auditorium_code']])
        ->assertOk();
    expect(app(\App\Http\Controllers\Admin\TimetableController::class)->internalConflicts($s['board'])['count'])->toBe(0);
    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?' . http_build_query($params))
        ->assertOk()->json();
    expect($res['total'])->toBe(0)->and($res['conflict_total'])->toBe(0)->and($res['cards'])->toBe([]);
});

it('takliflar: o\'qituvchi to\'qnashuvida — mavjud qadamlar, o\'qituvchini almashtirish eng oxirida; sabab o\'qituvchi bilan', function () {
    $s = sugSetup();
    $t = sugTeacher('SHARIPOV K.');
    $free = sugTeacher('BOSH O.');
    $base = ['board_id' => $s['board']->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15, 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => $t->id, 'teacher_name' => 'SHARIPOV K.', 'day' => 2, 'pair' => 3];
    $x = TimetableCard::create($base + ['subject_name' => 'Gigiyena', 'group_name' => 'g-02a']);
    TimetableCard::create($base + ['subject_name' => 'Gigiyena', 'group_name' => 'g-02b']);
    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')->assertOk()->json();
    expect($res['conflict_total'])->toBe(2)->and($res['unplaced_total'])->toBe(2);
    $card = collect($res['cards'])->firstWhere('id', $x->id);
    $types = collect($card['suggestions'])->pluck('type')->all();
    expect($card['conflict'])->toBeTrue()->and($card['reason'])->toBe('O\'qituvchi SHARIPOV K. band: g-02b · Gigiyena · Seshanba II para')
        ->and($types)->not->toContain('room')->and($types[0])->toBe('slot')->and(end($types))->toBe('teacher');
});

it('takliflar: karta xonada ikki dars bilan to\'qnashsa — bitta tomonning xonasini almashtirish taklif qilinmaydi (to\'qnashuv qolardi)', function () {
    $s = sugSetup();
    foreach ([['R215', '215', 40], ['R216', '216', 40]] as [$code, $name, $vol]) {
        \App\Models\Auditorium::create(['code' => $code, 'name' => $name, 'volume' => $vol, 'active' => true]);
    }
    $base = ['board_id' => $s['board']->id, 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Odam anatomiyasi', 'kafedra_name' => 'Anatomiya kafedrasi', 'auditorium_code' => 'R215', 'auditorium_name' => '215'];
    $a = TimetableCard::create($base + ['faculty_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi', 'course' => 1,
        'oqim_label' => '6-oqim', 'group_name' => 'd1/d26-06b']);
    $others = [];
    foreach (['p/p25-02b', 'p/p25-02c'] as $g) {
        $others[] = TimetableCard::create($base + ['faculty_name' => 'Pediatriya', 'specialty_name' => 'Pediatriya', 'course' => 2,
            'oqim_label' => '2-oqim', 'group_name' => $g]);
    }
    foreach (array_merge([$a], $others) as $c) {
        TimetableCardOverride::create(['card_id' => $c->id, 'week' => 7, 'day' => 5, 'pair' => 9, 'start_half' => 0, 'cancelled' => false]);
    }
    $res = sugAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.boards.week-suggestions', $s['board'])
        . '?' . http_build_query(['week' => 7, 'faculty_names' => ['1-son davolash'], 'courses' => [1]]))->assertOk()->json();
    $card = $res['cards'][0];
    expect($card['id'])->toBe($a->id)
        ->and($card['reason'])->toBe('Xona 215 band: p/p25-02b · Odam anatomiyasi · Juma V para; Xona 215 band: p/p25-02c · Odam anatomiyasi · Juma V para')
        ->and($card['suggestions'][0]['actions'][0])->toMatchArray(['card_id' => $a->id, 'auditorium_code' => 'R216'])
        ->and(collect($card['suggestions'])->filter(fn ($x) => str_starts_with($x['title'], 'Ikkinchi tomon'))->all())->toBe([]);
});

// ─── Takliflar: boshqa rollar uchun ko'rish va qo'llash (tasdiq zanjiri bilan) ───

/** O'qituvchi T (own kartasi), registrator, talaba (g-01a); semestrdan oldin (barcha sanalar — ertadan keyin). */
function sapSetup(): array
{
    $s = sugSetup();
    foreach (['oqituvchi', 'registrator_ofisi', 'fan_masuli'] as $r) {
        Role::findOrCreate($r, 'web');
    }
    $s['t'] = sugTeacher('OQITUVCHI T.');
    $s['t']->assignRole('oqituvchi');
    $s['reg'] = sugTeacher('REGISTRATOR');
    $s['reg']->assignRole('registrator_ofisi');
    $s['head']->update(['department' => 'Gigiyena kafedrasi']);
    $s['own']->update(['ext_day' => 2, 'ext_pair' => 3, 'teacher_id' => $s['t']->id, 'teacher_name' => 'OQITUVCHI T.']);
    TimetableCardOverride::create(['card_id' => $s['foreign']->id, 'week' => 3, 'cancelled' => true]);
    DB::table('students')->insert(['hemis_id' => 9501, 'full_name' => 'TALABA', 'short_name' => 'T', 'first_name' => 'T', 'second_name' => 'T',
        'third_name' => 'T', 'student_id_number' => '9501', 'group_name' => 'g-01a', 'student_status_code' => 11, 'department_id' => 77,
        'department_name' => 'F1', 'created_at' => now(), 'updated_at' => now()]);
    $s['date'] = fn (int $week, int $day) => app(\App\Http\Controllers\Admin\TimetableController::class)
        ->weeklyProposalDates($s['own']->fresh(), ['week' => $week, 'day' => $day])[1];
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['date'](1, 1) . ' 09:00', 'Asia/Tashkent')->subDays(3));

    return $s;
}

function sapGet(Teacher $t, string $role, array $s): array
{
    return sugAs($t, $role)->getJson(route('admin.timetable.boards.week-suggestions', $s['board']) . '?week=3&courses[]=2')->assertOk()->json();
}

function sapBody(array $card, array $sug): array
{
    return ['week' => 3, 'card_id' => $card['id'], 'type' => $sug['type'], 'title' => $sug['title'], 'actions' => $sug['actions']];
}

it('takliflar: o\'quv bo\'limi ko\'radi, qo\'llashda 403; o\'qituvchi faqat o\'z darsini ko\'radi', function () {
    $s = sapSetup();
    $res = sapGet($s['oquv'], 'oquv_bolimi', $s);
    $card = collect($res['cards'])->firstWhere('id', $s['own']->id);
    expect($res['apply_mode'])->toBeNull()->and($card['suggestions'])->not->toBeEmpty()
        ->and(collect($card['suggestions'])->pluck('can_apply')->unique()->all())->toBe([false]);
    $sug = $card['suggestions'][0];
    sugAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertForbidden();
    sugAs($s['oquv'], 'oquv_bolimi')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), sapBody($card, $sug))->assertForbidden();
    // O'qituvchi: faqat o'z darsi (boshqa o'qituvchining unplaced kartasi ko'rinmaydi).
    $other = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'teacher_id' => sugTeacher('BOSHQA')->id, 'ext_day' => 3, 'ext_pair' => 1,
        'kafedra_name' => 'Gigiyena kafedrasi']);
    $ids = collect(sapGet($s['t'], 'oqituvchi', $s)['cards'])->pluck('id')->all();
    expect($ids)->toContain($s['own']->id)->not->toContain($other->id);
    // Kafedra mudiri — o'z kafedrasi fanlari; boshqa kafedra kartasi ko'rinmaydi (o'quv bo'limi — hammasi).
    $fz = sugCard($s, ['subject_name' => 'Fizika', 'group_name' => 'g-01c', 'kafedra_name' => 'Fizika kafedrasi', 'ext_day' => 4, 'ext_pair' => 1]);
    $headIds = collect(sapGet($s['head'], 'kafedra_mudiri', $s)['cards'])->pluck('id')->all();
    expect($headIds)->toContain($other->id)->not->toContain($fz->id)
        ->and(collect(sapGet($s['oquv'], 'oquv_bolimi', $s)['cards'])->pluck('id')->all())->toContain($fz->id);
    expect(TimetableCardOverride::where('week', 3)->where('cancelled', false)->count())->toBe(0);
});

it('takliflar: o\'qituvchi o\'z darsiga qo\'llaydi — so\'rov ikki bosqichdan o\'tadi, jadval o\'zgaradi, talabaga xabar; ikkinchi so\'rov — xato', function () {
    $s = sapSetup();
    $res = sapGet($s['t'], 'oqituvchi', $s);
    expect($res['apply_mode'])->toBe('request');
    $card = collect($res['cards'])->firstWhere('id', $s['own']->id);
    $sug = collect($card['suggestions'])->firstWhere('type', 'slot');
    expect($sug['can_apply'])->toBeTrue()->and($sug['mode'])->toBe('department');
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), sapBody($card, $sug))->assertForbidden();
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))
        ->assertOk()->assertJsonPath('pending_approval', true);
    $req = \App\Models\TimetableChangeRequest::sole();
    expect($req->route_name)->toBe('admin.timetable.week-suggestions.apply')->and($req->requester_role)->toBe('oqituvchi')
        ->and($req->approval_stage)->toBe('department')->and($req->details['card_ids'])->toBe([$s['own']->id])
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->exists())->toBeFalse();
    // Kartada bosqich: kutilayotgan so'rov kartaga bog'langan; ikkinchi so'rov — xato.
    $row = sugAs($s['t'], 'oqituvchi')->getJson(route('admin.timetable.change-requests.index', $s['board']) . '?status=pending')->json('requests.0');
    expect($row['card_ids'])->toBe([$s['own']->id])->and($row['approval_stage'])->toBe('department');
    $again = collect(collect(sapGet($s['t'], 'oqituvchi', $s)['cards'])->firstWhere('id', $s['own']->id)['suggestions'])->firstWhere('type', 'slot');
    expect($again['can_apply'])->toBeFalse()->and($again['why'])->toContain("allaqachon so'rov bor");
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, "allaqachon so'rov bor"));

    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->approval_stage)->toBe('registrar')->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->exists())->toBeFalse();
    sugAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    expect($req->fresh()->status)->toBe('approved');
    $ov = TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->first();
    expect([$ov->day, $ov->pair])->toBe([$sug['actions'][0]['day'], $sug['actions'][0]['pair']]);
    $msg = \App\Models\StudentNotification::where('student_id', DB::table('students')->where('hemis_id', 9501)->value('id'))->sole()->message;
    expect($msg)->toContain('Fan: Gigiyena')->toContain("O'qituvchi: OQITUVCHI T.")->toContain('Eski vaqt: o‘tilmaydi')->toContain('Yangi vaqt: ');
});

it('takliflar: kafedra mudiri qo\'llasa — faqat registrator tasdiqlaydi; ko\'p amalli taklif rad etilsa jadval o\'zgarmaydi', function () {
    $s = sapSetup();
    // Guruhni to'sib turgan Biologiya (boshqa o'qituvchi) — "Darsni surish" (ko'p amalli) taklifi.
    $b = sugTeacher('BOSHQA O.');
    $blk = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01a', 'teacher_id' => $b->id, 'teacher_name' => 'BOSHQA O.', 'kafedra_name' => 'Gigiyena kafedrasi']);
    TimetableCardOverride::create(['card_id' => $blk->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    $card = collect(sapGet($s['head'], 'kafedra_mudiri', $s)['cards'])->firstWhere('id', $s['own']->id);
    $sug = collect($card['suggestions'])->firstWhere('type', 'blocker');
    expect($sug)->not->toBeNull()->and(count($sug['actions']))->toBeGreaterThan(1)->and($sug['mode'])->toBe('registrar');
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertOk();
    $req = \App\Models\TimetableChangeRequest::sole();
    expect($req->approval_stage)->toBe('registrar')->and($req->requester_role)->toBe('kafedra_mudiri')
        ->and(collect($req->details['card_ids'])->sort()->values()->all())->toBe(collect([$s['own']->id, $blk->id])->sort()->values()->all())
        ->and($req->details['lesson_teacher_ids'])->toContain($b->id);
    // Dars o'qituvchisiga (so'rovni o'zi yubormagan) — xabar.
    expect(\App\Models\TeacherNotification::where('teacher_id', $b->id)->value('title'))->toContain("Takliflar");
    $before = TimetableCardOverride::orderBy('id')->get(['card_id', 'week', 'day', 'pair', 'cancelled'])->toArray();
    sugAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.reject', $req), ['note' => 'Yo‘q'])->assertOk();
    expect($req->fresh()->status)->toBe('rejected')
        ->and(TimetableCardOverride::orderBy('id')->get(['card_id', 'week', 'day', 'pair', 'cancelled'])->toArray())->toBe($before);
    // Qayta yuborib, registrator tasdiqlasa — ikkala amal ham qo'llanadi.
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertOk();
    $req2 = \App\Models\TimetableChangeRequest::where('status', 'pending')->sole();
    sugAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req2))->assertOk();
    expect($req2->fresh()->status)->toBe('approved')
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('pair'))->toBe(3)
        ->and(TimetableCardOverride::where('card_id', $blk->id)->where('week', 3)->first())->not->toMatchArray(['day' => 2, 'pair' => 3]);
    // Kafedra mudiri so'rovi ham registrator tasdig'ida qayta tekshiriladi: sana o'tgan — "Muddati o'tgan", mudirga xabar.
    $own2 = sugCard($s, ['subject_name' => 'Gigiyena', 'group_name' => 'g-01d', 'kafedra_name' => 'Gigiyena kafedrasi', 'ext_day' => 5, 'ext_pair' => 5]);
    $c2 = collect(sapGet($s['head'], 'kafedra_mudiri', $s)['cards'])->firstWhere('id', $own2->id);
    $sg2 = collect($c2['suggestions'])->firstWhere('type', 'slot');
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($c2, $sg2))->assertOk();
    $req3 = \App\Models\TimetableChangeRequest::where('status', 'pending')->sole();
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['date'](3, $sg2['actions'][0]['day']) . ' 08:00', 'Asia/Tashkent'));
    sugAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req3))->assertUnprocessable();
    expect($req3->fresh()->status)->toBe('expired')
        ->and(\App\Models\TeacherNotification::where('teacher_id', $s['head']->id)->where('title', "Vaqt o'zgarishi so'rovi muddati o'tgan")->exists())->toBeTrue()
        ->and(TimetableCardOverride::where('card_id', $own2->id)->exists())->toBeFalse();
});

it('takliflar: o\'qituvchi boshqa o\'qituvchining darsini suradigan taklifni va bugungi kunga tegadiganini qo\'llay olmaydi', function () {
    $s = sapSetup();
    $b = sugTeacher('BOSHQA O.');
    $blk = sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01a', 'teacher_id' => $b->id, 'teacher_name' => 'BOSHQA O.', 'kafedra_name' => 'Gigiyena kafedrasi']);
    TimetableCardOverride::create(['card_id' => $blk->id, 'week' => 3, 'day' => 2, 'pair' => 3]);
    $card = collect(sapGet($s['t'], 'oqituvchi', $s)['cards'])->firstWhere('id', $s['own']->id);
    $sug = collect($card['suggestions'])->firstWhere('type', 'blocker');
    expect($sug['can_apply'])->toBeFalse()->and($sug['why'])->toContain("Boshqa o'qituvchining darsini surish");
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertForbidden();
    // O'qituvchini almashtirish ham — faqat kafedra mudiri va adminlar.
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), ['week' => 3, 'card_id' => $s['own']->id,
        'actions' => [['op' => 'teacher', 'card_id' => $s['own']->id, 'teacher_id' => $b->id]]])
        ->assertForbidden()->assertJsonPath('message', fn ($m) => str_contains($m, "O'qituvchini almashtirish"));
    // Bugungi kunga tegadigan taklif: ko'rinadi, lekin qo'llab bo'lmaydi (admin ham).
    $blk->delete();
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['date'](3, 2) . ' 08:00', 'Asia/Tashkent'));
    foreach ([[$s['t'], 'oqituvchi', 'propose'], [$s['admin'], 'superadmin', 'apply']] as [$who, $role, $ep]) {
        $card = collect(sapGet($who, $role, $s)['cards'])->firstWhere('id', $s['own']->id);
        $sug = collect($card['suggestions'])->first(fn ($x) => $x['actions'][0]['day'] === 2);
        expect($sug['can_apply'])->toBeFalse()->and($sug['why'])->toContain("Bugungi yoki o'tgan");
        sugAs($who, $role)->postJson(route('admin.timetable.week-suggestions.' . $ep, $s['board']), sapBody($card, $sug))->assertUnprocessable();
    }
    expect(\App\Models\TimetableChangeRequest::count())->toBe(0)->and(TimetableCardOverride::where('card_id', $s['own']->id)->exists())->toBeFalse();
});

it('takliflar: tasdiq paytida to\'qnashuv chiqsa — "Qo\'llanmadi"; sana o\'tib ketsa — "Muddati o\'tgan"', function () {
    $s = sapSetup();
    $card = collect(sapGet($s['t'], 'oqituvchi', $s)['cards'])->firstWhere('id', $s['own']->id);
    $sug = collect($card['suggestions'])->firstWhere('type', 'slot');
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertOk();
    $req = \App\Models\TimetableChangeRequest::sole();
    // Shu orada guruhga shu vaqtda boshqa dars qo'yildi.
    $x = sugCard($s, ['subject_name' => 'Fizika', 'group_name' => 'g-01a', 'kafedra_name' => 'Fizika kafedrasi']);
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 3, 'day' => $sug['actions'][0]['day'], 'pair' => $sug['actions'][0]['pair']]);
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($req->fresh())->status->toBe('failed')->error->toContain('Guruh band')
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->exists())->toBeFalse();
    expect(\App\Models\TeacherNotification::where('teacher_id', $s['t']->id)->latest('id')->value('title'))->toBe("Vaqt o'zgarishi qo'llanmadi");
    // Muddati o'tgan.
    $x->delete();
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertOk();
    $req = \App\Models\TimetableChangeRequest::where('status', 'pending')->sole();
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.change-requests.approve', $req))->assertOk();
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['date'](3, $sug['actions'][0]['day']) . ' 08:00', 'Asia/Tashkent'));
    sugAs($s['reg'], 'registrator_ofisi')->postJson(route('admin.timetable.change-requests.approve', $req))->assertUnprocessable();
    expect($req->fresh()->status)->toBe('expired')
        ->and(\App\Models\TeacherNotification::where('teacher_id', $s['t']->id)->latest('id')->value('title'))->toBe("Vaqt o'zgarishi so'rovi muddati o'tgan");
});

it('takliflar: admin darhol, yaxlit qo\'llaydi — bitta amal o\'tmasa hech biri qo\'llanmaydi', function () {
    $s = sapSetup();
    $x = sugCard($s, ['subject_name' => 'Fizika', 'group_name' => 'g-01a', 'kafedra_name' => 'Fizika kafedrasi']);
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 3, 'day' => 4, 'pair' => 1]);
    $url = route('admin.timetable.week-suggestions.apply', $s['board']);
    // 1-amal o'tadi, 2-amal (shu guruh band vaqtiga) — o'tmaydi: hech narsa yozilmaydi.
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'move', 'card_id' => $x->id, 'week' => 3, 'day' => 5, 'pair' => 1],
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 5, 'pair' => 1],
    ]])->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, 'Guruh band'));
    expect(TimetableCardOverride::where('card_id', $x->id)->where('week', 3)->value('day'))->toBe(4)
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->exists())->toBeFalse();
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'move', 'card_id' => $x->id, 'week' => 3, 'day' => 5, 'pair' => 1],
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 4, 'pair' => 1],
    ]])->assertOk();
    expect(TimetableCardOverride::where('card_id', $x->id)->where('week', 3)->value('day'))->toBe(5)
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('day'))->toBe(4)
        ->and(\App\Models\TimetableChangeRequest::sole()->status)->toBe('applied');
});

it('takliflar: o\'qituvchini almashtirish saqlangan biriktiruvga ham yoziladi; boshqa roli admin bo\'lsa — darhol qo\'llaydi, kafedra mudiri apply\'ga — 403', function () {
    $s = sapSetup();
    $new = sugTeacher('YANGI O.');
    $u = sugTeacher('ADMIN-OQITUVCHI');
    $u->assignRole('superadmin');
    $u->assignRole('oqituvchi');
    // Faol rol — o'qituvchi, lekin admin roli ham bor: oynada "darhol", apply ishlaydi.
    expect(sapGet($u, 'oqituvchi', $s)['apply_mode'])->toBe('direct');
    sugAs($u, 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'teacher', 'card_id' => $s['own']->id, 'teacher_id' => $new->id],
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 2, 'pair' => 3],
    ]])->assertOk();
    expect($s['own']->fresh()->teacher_id)->toBe($new->id)
        ->and(DB::table('timetable_unit_assignments')->where('board_id', $s['board']->id)->where('subject_name', 'Gigiyena')->value('teacher_id'))->toBe($new->id);
    // Kafedra mudiri apply'ni to'g'ridan-to'g'ri chaqira olmaydi (doskada tasdiq rejimi yoqilgan bo'lsa ham — umumiy ushlashga tushmaydi).
    $s['board']->update(['settings' => ['approval_required' => true]]);
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 4, 'pair' => 1],
    ]])->assertForbidden();
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(0)
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('day'))->toBe(2);
    // Admin + kafedra mudiri (faol — kafedra mudiri), tasdiq rejimi yoqilgan doska: so'rovga aylanmaydi, darhol qo'llanadi.
    $hk = sugTeacher('ADMIN-MUDIR', ['department_hemis_id' => 501]);
    $hk->assignRole('superadmin');
    $hk->assignRole('kafedra_mudiri');
    sugAs($hk, 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 4, 'pair' => 1],
    ]])->assertOk()->assertJsonMissing(['pending_approval' => true]);
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(0)
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('day'))->toBe(4);
});

it('takliflar: yangi joy dastur to\'ridan tashqarida bo\'lsa — qo\'llanmaydi; kutilayotgan so\'rov band qilgan vaqtga boshqa so\'rov yuborilmaydi', function () {
    $s = sapSetup();
    $url = route('admin.timetable.week-suggestions.apply', $s['board']);
    // To'r: 6 kun, 12 katak; karta 2 katak — 7-kun ham, 12-katakdan boshlash ham chegaradan tashqarida.
    foreach ([[7, 1], [2, 12]] as [$d, $p]) {
        sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
            ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => $d, 'pair' => $p],
        ]])->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, 'jadval chegarasidan tashqarida'));
    }
    expect(TimetableCardOverride::where('card_id', $s['own']->id)->exists())->toBeFalse();
    // O'qituvchi o'z darsini 2-kun 3-katakka so'radi (kutilmoqda) — shu guruhning boshqa darsini shu vaqtga so'rab bo'lmaydi.
    $card = collect(sapGet($s['t'], 'oqituvchi', $s)['cards'])->firstWhere('id', $s['own']->id);
    $sug = collect($card['suggestions'])->first(fn ($x) => $x['actions'][0]['day'] === 2 && $x['actions'][0]['pair'] === 3);
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), sapBody($card, $sug))->assertOk();
    $fz = sugCard($s, ['subject_name' => 'Fizika', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi']);
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), ['week' => 3, 'card_id' => $fz->id,
        'actions' => [['op' => 'move', 'card_id' => $fz->id, 'week' => 3, 'day' => 2, 'pair' => 3]]])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan'));
    // Haftalik o'qituvchi so'rovi ham Takliflar bronini ko'radi.
    $fz->update(['teacher_id' => $s['t']->id, 'day' => 4, 'pair' => 1]);
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.cards.time-proposal.check', $fz), ['week' => 3, 'day' => 2, 'pair' => 3])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan'));
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(1);
});

it('takliflar: sana — boshidagi va yakuniy joy bo\'yicha; blokni qayta qo\'yishdagi oraliq "reset" (o\'tgan shablon kuni) to\'sqinlik qilmaydi', function () {
    $s = sapSetup();
    // Blok (2 karta) shablonda 1-kunda, 3-haftada 4-kunga ko'chirilgan; bugun — 3-haftaning 2-kuni (shablon kuni o'tgan).
    $mk = fn (int $p) => sugCard($s, ['subject_name' => 'Biologiya', 'group_name' => 'g-01b', 'kafedra_name' => 'Gigiyena kafedrasi', 'day' => 1, 'pair' => $p]);
    [$b1, $b2] = [$mk(1), $mk(3)];
    TimetableCardOverride::create(['card_id' => $b1->id, 'week' => 3, 'day' => 4, 'pair' => 1]);
    TimetableCardOverride::create(['card_id' => $b2->id, 'week' => 3, 'day' => 4, 'pair' => 3]);
    test()->travelTo(\Illuminate\Support\Carbon::parse($s['date'](3, 2) . ' 08:00', 'Asia/Tashkent'));
    $acts = fn (int $d) => [['op' => 'reset', 'card_id' => $b1->id, 'week' => 3], ['op' => 'reset', 'card_id' => $b2->id, 'week' => 3],
        ['op' => 'move', 'card_id' => $b1->id, 'week' => 3, 'day' => $d, 'pair' => 1], ['op' => 'move', 'card_id' => $b2->id, 'week' => 3, 'day' => $d, 'pair' => 3]];
    $url = route('admin.timetable.week-suggestions.apply', $s['board']);
    // Yakuniy joy — o'tgan kun (1-kun): rad.
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $b1->id, 'actions' => $acts(1)])
        ->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    // 4-kundan 5-kunga (ikkalasi kelgusi): o'tadi.
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $b1->id, 'actions' => $acts(5)])->assertOk();
    expect(TimetableCardOverride::where('card_id', $b1->id)->where('week', 3)->value('day'))->toBe(5)
        ->and(TimetableCardOverride::where('card_id', $b2->id)->where('week', 3)->value('day'))->toBe(5);
    // Faqat "reset" (yakuniy joy — o'tgan shablon kuni) — rad; bugungi darsni (boshidagi joyi bugun) kelgusi kunga ko'chirish — rad.
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $b1->id, 'actions' => [['op' => 'reset', 'card_id' => $b1->id, 'week' => 3]]])
        ->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    TimetableCardOverride::where('card_id', $b2->id)->where('week', 3)->update(['day' => 2]);
    sugAs($s['admin'], 'superadmin')->postJson($url, ['week' => 3, 'card_id' => $b2->id, 'actions' => [['op' => 'move', 'card_id' => $b2->id, 'week' => 3, 'day' => 6, 'pair' => 3]]])
        ->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, "Bugungi yoki o'tgan"));
    expect(TimetableCardOverride::where('card_id', $b1->id)->where('week', 3)->value('day'))->toBe(5)
        ->and(TimetableCardOverride::where('card_id', $b2->id)->where('week', 3)->value('day'))->toBe(2);
});

it('takliflar: kutilayotgan so\'rovdagi yangi xona ham bron — boshqa guruh va o\'qituvchi shu vaqtda shu xonani so\'ray olmaydi', function () {
    $s = sapSetup();
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => 'R1', 'active' => true, 'volume' => 40]);
    \App\Models\Auditorium::create(['code' => 'R2', 'name' => 'R2', 'active' => true, 'volume' => 40]);
    $mk = fn (string $g, string $room) => sugCard($s, ['subject_name' => 'Fizika ' . $g, 'group_name' => $g, 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => sugTeacher('T ' . $g)->id, 'auditorium_code' => $room, 'auditorium_name' => $room]);
    [$a, $b] = [$mk('g-05a', 'R2'), $mk('g-05b', 'R2')];
    $propose = fn ($c, string $room) => sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']),
        ['week' => 3, 'card_id' => $c->id, 'actions' => [['op' => 'move', 'card_id' => $c->id, 'week' => 3, 'day' => 4, 'pair' => 1, 'auditorium_code' => $room]]]);
    // a: 4-kun 1-katak, R1 xonada (hozirgi xonasi R2) — kutilmoqda.
    $propose($a, 'R1')->assertOk();
    // b: shu vaqtda R1 — bron bilan to'qnashadi (a ning hozirgi xonasi R2 bo'lsa ham).
    $propose($b, 'R1')->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan') && str_contains($m, 'auditoriya: R1'));
    // b: shu vaqtda R2 — bron emas (a R2 dan chiqib ketadi).
    $propose($b, 'R2')->assertOk();
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(2);
});

it('takliflar: kafedra mudiri so\'rovi ham doska qulfi ostida bronlar bilan qayta tekshiriladi (parallel so\'rov o\'tmaydi)', function () {
    $s = sapSetup();
    $mk = fn (string $g) => sugCard($s, ['subject_name' => 'Fizika ' . $g, 'group_name' => 'g-06', 'kafedra_name' => 'Gigiyena kafedrasi']);
    [$a, $b] = [$mk('a'), $mk('b')];
    $body = fn ($c) => ['week' => 3, 'card_id' => $c->id, 'actions' => [['op' => 'move', 'card_id' => $c->id, 'week' => 3, 'day' => 4, 'pair' => 1]]];
    sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), $body($a))->assertOk();
    // b ning so'rovi a dan oldin sinovdan o'tgan deb (parallel) — to'g'ridan-to'g'ri submit: qulf ostida bron bilan rad etiladi.
    $sub = \Illuminate\Http\Request::create('/', 'POST', $body($b));
    $route = (new \Illuminate\Routing\Route('POST', '/', []))->name(\App\Http\Controllers\Admin\TimetableController::SUGGESTION_ROUTE);
    $route->bind($sub);
    $route->setParameter('board', $s['board']);
    $sub->setRouteResolver(fn () => $route);
    $svc = app(\App\Services\Timetable\TimetableChangeApproval::class);
    $draft = $svc->draft($sub, $s['head'], $s['board'], 'kafedra_mudiri');
    $draft['approval_stage'] = 'registrar';
    expect(fn () => $svc->submit($draft))->toThrow(\Illuminate\Validation\ValidationException::class);
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(1);
});

it('takliflar: o\'qituvchi almashtirishda boshqa haftalarda faqat yangi o\'qituvchi bandligi tekshiriladi (kartaning o\'z xona to\'qnashuvi to\'smaydi)', function () {
    $s = sapSetup();
    $n = sugTeacher('YANGI O.');
    // own: shablonda 1-kun 5-katak, xona R9; 4-haftada shu xonada boshqa guruh darsi (kartaning o'z, eski xona to'qnashuvi).
    $s['own']->update(['day' => 1, 'pair' => 5, 'auditorium_code' => 'R9', 'auditorium_name' => 'R9']);
    $x = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-07', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => sugTeacher('Y')->id,
        'auditorium_code' => 'R9', 'auditorium_name' => 'R9']);
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 4, 'day' => 1, 'pair' => 5]);
    $body = ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [
        ['op' => 'teacher', 'card_id' => $s['own']->id, 'teacher_id' => $n->id],
        ['op' => 'move', 'card_id' => $s['own']->id, 'week' => 3, 'day' => 2, 'pair' => 3],
    ]];
    $url = route('admin.timetable.week-suggestions.apply', $s['board']);
    // Yangi o'qituvchi 5-haftada shu vaqtda band — rad (o'qituvchi to'qnashuvi).
    $z = sugCard($s, ['subject_name' => 'Fizika', 'group_name' => 'g-08', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => $n->id]);
    $zo = TimetableCardOverride::create(['card_id' => $z->id, 'week' => 5, 'day' => 1, 'pair' => 5]);
    sugAs($s['admin'], 'superadmin')->postJson($url, $body)->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, '5-hafta') && str_contains($m, "O'qituvchi band"));
    expect($s['own']->fresh()->teacher_id)->toBe($s['t']->id);
    // U bo'shasa — 4-haftadagi xona to'qnashuviga qaramay qo'llanadi.
    $zo->delete();
    sugAs($s['admin'], 'superadmin')->postJson($url, $body)->assertOk();
    expect($s['own']->fresh()->teacher_id)->toBe($n->id)
        ->and(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('day'))->toBe(2);
});

it('takliflar: o\'qituvchi almashtirish bronida yangi o\'qituvchi — shu vaqtga va boshqa haftalarda boshqa dars uni band qila olmaydi', function () {
    $s = sapSetup();
    $n = sugTeacher('YANGI O.');
    $mk = fn (string $g, array $x = []) => sugCard($s, $x + ['subject_name' => 'Fizika ' . $g, 'group_name' => $g, 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => sugTeacher('T ' . $g)->id, 'auditorium_code' => 'X' . $g, 'auditorium_name' => 'X' . $g]);
    $propose = fn (array $body) => sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), $body);
    // a: o'qituvchi → N, 3-haftada 4-kun 1-katakka; shablonda 1-kun 5-katak (boshqa haftalarda ham N shu vaqtda).
    $a = $mk('g-11', ['day' => 1, 'pair' => 5]);
    $propose(['week' => 3, 'card_id' => $a->id, 'actions' => [['op' => 'teacher', 'card_id' => $a->id, 'teacher_id' => $n->id],
        ['op' => 'move', 'card_id' => $a->id, 'week' => 3, 'day' => 4, 'pair' => 1]]])->assertOk();
    // b (boshqa guruh, xona): o'qituvchisi N — shu vaqtga so'rov (kutilayotgan almashtirish N ni band qilgan).
    $b = $mk('g-12', ['teacher_id' => $n->id]);
    $propose(['week' => 3, 'card_id' => $b->id, 'actions' => [['op' => 'move', 'card_id' => $b->id, 'week' => 3, 'day' => 4, 'pair' => 1]]])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan') && str_contains($m, 'o‘qituvchi'));
    // c: o'qituvchi → N va shu vaqtga — ham rad (kiruvchi tomonda ham yangi o'qituvchi).
    $c = $mk('g-13');
    $propose(['week' => 3, 'card_id' => $c->id, 'actions' => [['op' => 'teacher', 'card_id' => $c->id, 'teacher_id' => $n->id],
        ['op' => 'move', 'card_id' => $c->id, 'week' => 3, 'day' => 4, 'pair' => 1]]])->assertUnprocessable();
    // Boshqa hafta: 5-haftada a shablon joyida (1-kun 5-katak) N bilan — b ni 5-haftada shu vaqtga so'rab bo'lmaydi.
    $propose(['week' => 5, 'card_id' => $b->id, 'actions' => [['op' => 'move', 'card_id' => $b->id, 'week' => 5, 'day' => 1, 'pair' => 5]]])
        ->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'o‘qituvchi'));
    // Boshqa vaqt — mumkin.
    $propose(['week' => 3, 'card_id' => $b->id, 'actions' => [['op' => 'move', 'card_id' => $b->id, 'week' => 3, 'day' => 5, 'pair' => 1]]])->assertOk();
    expect(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(2);
});

it('takliflar: almashtirishning boshqa haftalari — yangi o\'qituvchi bandligi bron bilan tekshiriladi, guruh/xona broni esa to\'smaydi', function () {
    $s = sapSetup();
    $n = sugTeacher('YANGI O.');
    $mk = fn (string $g, array $x = []) => sugCard($s, $x + ['subject_name' => 'Fizika ' . $g, 'group_name' => $g, 'kafedra_name' => 'Gigiyena kafedrasi',
        'teacher_id' => sugTeacher('T ' . $g)->id, 'auditorium_code' => 'X' . $g, 'auditorium_name' => 'X' . $g]);
    $propose = fn (array $body) => sugAs($s['head'], 'kafedra_mudiri')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), $body);
    $subst = fn ($c) => ['week' => 3, 'card_id' => $c->id, 'actions' => [['op' => 'teacher', 'card_id' => $c->id, 'teacher_id' => $n->id],
        ['op' => 'move', 'card_id' => $c->id, 'week' => 3, 'day' => 4, 'pair' => 1]]];
    // b (o'qituvchisi N) 5-haftada 1-kun 5-katakka so'ragan (kutilmoqda).
    $b = $mk('g-21', ['teacher_id' => $n->id]);
    $propose(['week' => 5, 'card_id' => $b->id, 'actions' => [['op' => 'move', 'card_id' => $b->id, 'week' => 5, 'day' => 1, 'pair' => 5]]])->assertOk();
    // c shablonda 1-kun 5-katakda: o'qituvchini N ga almashtirish — 5-haftada N band (bron) — rad.
    $c = $mk('g-22', ['day' => 1, 'pair' => 5]);
    $propose($subst($c))->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan') && str_contains($m, 'o‘qituvchi'));
    // d: shu guruhga (g-23) boshqa dars 6-haftada 2-kun 1-katakka bron qilgan; keyin d o'zi (admin) shu joyga qo'yildi.
    $e = $mk('g-23');
    $propose(['week' => 6, 'card_id' => $e->id, 'actions' => [['op' => 'move', 'card_id' => $e->id, 'week' => 6, 'day' => 2, 'pair' => 1]]])->assertOk();
    $d = $mk('g-23', ['subject_name' => 'Kimyo g-23']);
    TimetableCardOverride::create(['card_id' => $d->id, 'week' => 6, 'day' => 2, 'pair' => 1]);
    // d ning o'qituvchisini N ga almashtirish: 6-haftadagi guruh broni (e) to'smaydi — faqat o'qituvchi tekshiriladi.
    $propose(['week' => 3, 'card_id' => $d->id, 'actions' => [['op' => 'teacher', 'card_id' => $d->id, 'teacher_id' => $n->id],
        ['op' => 'move', 'card_id' => $d->id, 'week' => 3, 'day' => 5, 'pair' => 3]]])->assertOk();
});

it('takliflar: yakuniy "reset" joyi ham to\'qnashuvga tekshiriladi (shablon joyida guruhning boshqa darsi)', function () {
    $s = sapSetup();
    // own: shablonda 1-kun 5-katak, 3-haftada bekor; shu haftada guruhga shu vaqtda boshqa fan qo'yilgan.
    $s['own']->update(['day' => 1, 'pair' => 5]);
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 3, 'cancelled' => true]);
    $x = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => sugTeacher('Y')->id,
        'auditorium_code' => 'R7', 'auditorium_name' => 'R7']);
    TimetableCardOverride::create(['card_id' => $x->id, 'week' => 3, 'day' => 1, 'pair' => 5]);
    $body = ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [['op' => 'reset', 'card_id' => $s['own']->id, 'week' => 3]]];
    sugAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.week-suggestions.apply', $s['board']), $body)
        ->assertUnprocessable()->assertJsonPath('error', fn ($m) => str_contains($m, 'Guruh band'));
    sugAs($s['t'], 'oqituvchi')->postJson(route('admin.timetable.week-suggestions.propose', $s['board']), $body)->assertUnprocessable();
    expect(TimetableCardOverride::where('card_id', $s['own']->id)->where('week', 3)->value('cancelled'))->toBeTrue()
        ->and(\App\Models\TimetableChangeRequest::where('status', 'pending')->count())->toBe(0);
});

it('takliflar: yakuniy "reset" joyi ham bron — ikki tomonda (kutilayotgan reset va kiruvchi reset)', function () {
    $s = sapSetup();
    // own (o'qituvchi T, g-01a): shablonda 1-kun 5-katak, 3-haftada bekor. x — shu guruhning boshqa darsi.
    $s['own']->update(['day' => 1, 'pair' => 5]);
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 3, 'cancelled' => true]);
    $x = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-01a', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => sugTeacher('Y')->id,
        'auditorium_code' => 'R7', 'auditorium_name' => 'R7']);
    $reset = ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [['op' => 'reset', 'card_id' => $s['own']->id, 'week' => 3]]];
    $moveX = ['week' => 3, 'card_id' => $x->id, 'actions' => [['op' => 'move', 'card_id' => $x->id, 'week' => 3, 'day' => 1, 'pair' => 5]]];
    $url = route('admin.timetable.week-suggestions.propose', $s['board']);
    // Kutilayotgan reset (1-kun 5-katak) — x ni shu vaqtga so'rab bo'lmaydi.
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertOk();
    sugAs($s['head'], 'kafedra_mudiri')->postJson($url, $moveX)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan'));
    // Teskari: x kutilmoqda — own ni reset qilib shu vaqtga qaytarib bo'lmaydi.
    \App\Models\TimetableChangeRequest::where('status', 'pending')->update(['status' => 'cancelled']);
    sugAs($s['head'], 'kafedra_mudiri')->postJson($url, $moveX)->assertOk();
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'bron qilgan'));
});

it('takliflar: yakuniy "reset" — bron shablon xonasi bilan (haftalik yozuvdagi xona emas), ikki tomonda', function () {
    $s = sapSetup();
    // own: shablonda 1-kun 5-katak, R1; 3-haftada 4-kun 1-katakka R2 xonada ko'chirilgan. y — boshqa guruh/o'qituvchi, xonasi R1.
    $s['own']->update(['day' => 1, 'pair' => 5, 'auditorium_code' => 'R1', 'auditorium_name' => 'R1']);
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 3, 'day' => 4, 'pair' => 1, 'auditorium_code' => 'R2', 'auditorium_name' => 'R2']);
    $y = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-31', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => sugTeacher('Y')->id,
        'auditorium_code' => 'R1', 'auditorium_name' => 'R1']);
    $reset = ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [['op' => 'reset', 'card_id' => $s['own']->id, 'week' => 3]]];
    $moveY = ['week' => 3, 'card_id' => $y->id, 'actions' => [['op' => 'move', 'card_id' => $y->id, 'week' => 3, 'day' => 1, 'pair' => 5]]];
    $url = route('admin.timetable.week-suggestions.propose', $s['board']);
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertOk();
    sugAs($s['head'], 'kafedra_mudiri')->postJson($url, $moveY)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'auditoriya: R1'));
    \App\Models\TimetableChangeRequest::where('status', 'pending')->update(['status' => 'cancelled']);
    sugAs($s['head'], 'kafedra_mudiri')->postJson($url, $moveY)->assertOk();
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertUnprocessable()->assertJsonPath('message', fn ($m) => str_contains($m, 'auditoriya: R1'));
});

it('takliflar: yakuniy "reset" — shablonda xona yo\'q bo\'lsa, haftalik yozuvdagi xona band qilinmaydi', function () {
    $s = sapSetup();
    $s['own']->update(['day' => 1, 'pair' => 5, 'auditorium_code' => null, 'auditorium_name' => null]);
    TimetableCardOverride::create(['card_id' => $s['own']->id, 'week' => 3, 'day' => 4, 'pair' => 1, 'auditorium_code' => 'R2', 'auditorium_name' => 'R2']);
    $y = sugCard($s, ['subject_name' => 'Kimyo', 'group_name' => 'g-32', 'kafedra_name' => 'Gigiyena kafedrasi', 'teacher_id' => sugTeacher('Y')->id,
        'auditorium_code' => 'R2', 'auditorium_name' => 'R2']);
    $reset = ['week' => 3, 'card_id' => $s['own']->id, 'actions' => [['op' => 'reset', 'card_id' => $s['own']->id, 'week' => 3]]];
    $moveY = ['week' => 3, 'card_id' => $y->id, 'actions' => [['op' => 'move', 'card_id' => $y->id, 'week' => 3, 'day' => 1, 'pair' => 5]]];
    $url = route('admin.timetable.week-suggestions.propose', $s['board']);
    // Kutilayotgan reset R2 ni band qilmaydi — y R2 da shu vaqtga so'ray oladi; teskarisi ham.
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertOk();
    sugAs($s['head'], 'kafedra_mudiri')->postJson($url, $moveY)->assertOk();
    \App\Models\TimetableChangeRequest::where('requester_role', 'oqituvchi')->update(['status' => 'cancelled']);
    sugAs($s['t'], 'oqituvchi')->postJson($url, $reset)->assertOk();
});
