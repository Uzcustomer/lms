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
