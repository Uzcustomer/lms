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
