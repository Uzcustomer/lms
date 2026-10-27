<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use App\Services\Timetable\BoardSnapshots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

uses(RefreshDatabase::class);

function snapSetup(): array
{
    Role::findOrCreate('superadmin', 'web');
    Role::findOrCreate('kafedra_mudiri', 'web');
    $mk = function (string $name, string $role) {
        $t = new Teacher();
        $t->forceFill([
            'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
            'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
            'gender' => 'm', 'department' => 'x', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
            'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
            'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
        ])->save();
        $t->assignRole($role);

        return $t;
    };
    $admin = $mk('ADMIN', 'superadmin');
    $head = $mk('MUDIR', 'kafedra_mudiri');
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $base = ['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 2, 'oqim_label' => '1-oqim',
        'lang' => 'uz', 'students' => 15, 'training_type' => 'practice', 'subject_name' => 'Gigiyena', 'group_name' => 'g-01a (o\'z)', 'kafedra_name' => 'G'];
    // Eski karta: 3 soat × 15 hafta, ichki 2-kun 3-para, tashqi 2/1; 5-haftada 4-kunga ko'chirilgan; o'qituvchi bor
    $old = TimetableCard::create($base + ['len_half' => 3, 'weeks' => 15, 'day' => 2, 'pair' => 3, 'ext_day' => 2, 'ext_pair' => 1,
        'teacher_id' => $head->id, 'teacher_name' => 'MUDIR']);
    TimetableCardOverride::create(['card_id' => $old->id, 'week' => 5, 'day' => 4, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    // Boshqa (joylashgan) karta — tegilmasligi kerak
    $other = TimetableCard::create(array_merge($base, ['subject_name' => 'Anatomiya', 'len_half' => 2, 'weeks' => 15, 'day' => 1, 'pair' => 1]));

    return compact('admin', 'head', 'board', 'base', 'old', 'other');
}

function snapAs(Teacher $t, string $role)
{
    return test()->withoutVite()->actingAs($t, 'teacher')->withSession(['active_role' => $role]);
}

it('nusxadan tiklash: qayta yaratilgan (1 + 2 soatga bo\'lingan) kartalar eski joyga ketma-ket, haftalik ko\'chirish bilan', function () {
    $s = snapSetup();
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    // "Kartochkalarni yaratish": eski karta o'chdi, o'rniga joylanmagan 1 + 2 soatlik kartalar
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $s['old']->delete();
    $c1 = TimetableCard::create($s['base'] + ['len_half' => 1, 'weeks' => 15]);
    $c2 = TimetableCard::create($s['base'] + ['len_half' => 2, 'weeks' => 10]);
    TimetableCardOverride::create(['card_id' => $c2->id, 'week' => 5, 'cancelled' => true]);   // yangi kartaning o'z haftalari

    $url = route('admin.timetable.snapshots.restore', [$s['board'], $snap]);
    snapAs($s['head'], 'kafedra_mudiri')->postJson($url, ['dry_run' => 0])->assertForbidden();
    $dry = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 1])->assertOk()->json();
    expect($dry['cards'])->toBe(2)->and($c1->fresh()->day)->toBeNull();

    $r = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 0])->assertOk()->json();
    expect($r['internal'])->toBe(2)->and($r['external'])->toBe(2);
    // 2 soatlik — eski boshlanishda, 1 soatlik — undan keyin
    expect($c2->fresh()->only(['day', 'pair', 'ext_day', 'ext_pair', 'teacher_id']))->toBe(['day' => 2, 'pair' => 3, 'ext_day' => 2, 'ext_pair' => 1, 'teacher_id' => $s['head']->id])
        ->and($c1->fresh()->only(['day', 'pair', 'ext_day', 'ext_pair']))->toBe(['day' => 2, 'pair' => 5, 'ext_day' => 2, 'ext_pair' => 3])
        // 5-hafta ko'chirishi: 1 soatlik kartaga (siljish bilan); 2 soatlikda 5-hafta bekor — tegilmaydi
        ->and(TimetableCardOverride::where('card_id', $c1->id)->where('week', 5)->first()?->only(['day', 'pair']))->toBe(['day' => 4, 'pair' => 3])
        ->and(TimetableCardOverride::where('card_id', $c2->id)->where('week', 5)->value('cancelled'))->toBeTrue()
        ->and($s['other']->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1])
        // Tiklashdan oldin hozirgi holat nusxasi olindi
        ->and(TimetableBoardSnapshot::where('reason', 'before_restore')->count())->toBe(1);

    // Qayta tiklash — joylashganlarga tegilmaydi
    expect(snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 1])->json('cards'))->toBe(0);
});

it('xavfli amaldan oldin va kunlik buyruq bilan nusxa olinadi; ro\'yxat faqat adminga', function () {
    $s = snapSetup();
    snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.unplace', $s['board']), ['courses' => [2], 'layer' => 'internal'])->assertOk();
    $this->artisan('timetable:snapshot')->assertExitCode(0);
    $list = snapAs($s['admin'], 'superadmin')->getJson(route('admin.timetable.snapshots.index', $s['board']))->assertOk()->json('snapshots');
    expect(array_column($list, 'reason'))->toBe(['daily', 'before_unplace'])
        ->and($list[1]['placed_count'])->toBe(2);
    snapAs($s['head'], 'kafedra_mudiri')->getJson(route('admin.timetable.snapshots.index', $s['board']))->assertForbidden();
});

it('qisman qayta yaratilgan birlik: hozir ham joyida turgan eski karta egallagan soatga yangi karta qo\'yilmaydi', function () {
    $s = snapSetup();
    // Eski holat: 2 soat (3-para) + 1 soat (5-para) bo'lib bo'lingan
    $s['old']->update(['len_half' => 2]);
    $keep = TimetableCard::create($s['base'] + ['len_half' => 1, 'weeks' => 15, 'day' => 2, 'pair' => 5, 'ext_day' => 2, 'ext_pair' => 3]);
    $before = app(BoardSnapshots::class)->collect($s['board']);
    // Sinxronlash: 2 soatlik karta (haftasi o'zgargan) qayta yaratildi, 1 soatlik saqlandi
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $s['old']->delete();
    $new = TimetableCard::create($s['base'] + ['len_half' => 2, 'weeks' => 10]);

    $r = app(BoardSnapshots::class)->restorePlacement($s['board'], $before, false, [], false);
    expect($r['cards'])->toBe(1)
        ->and($new->fresh()->only(['day', 'pair', 'ext_day', 'ext_pair']))->toBe(['day' => 2, 'pair' => 3, 'ext_day' => 2, 'ext_pair' => 1])
        ->and($keep->fresh()->only(['day', 'pair']))->toBe(['day' => 2, 'pair' => 5])
        ->and(TimetableBoardSnapshot::count())->toBe(0);
});

it('rad etilgan yoki xato so\'rov nusxa qoldirmaydi — nusxa ruxsat va tekshiruvdan keyin olinadi', function () {
    $s = snapSetup();
    Role::findOrCreate('kichik_admin', 'web');
    $s['head']->assignRole('kichik_admin');
    foreach (['boards.add-missing-groups', 'boards.merge-variant-lectures', 'boards.split-long-practice', 'weekly-layer-copy'] as $name) {
        snapAs($s['head'], 'kichik_admin')->postJson(route('admin.timetable.' . $name, $s['board']), [])->assertForbidden();
    }
    snapAs($s['head'], 'kichik_admin')->postJson(route('admin.timetable.boards.separate-practice-groups', $s['board']),
        ['specialty_name' => 'davolash ishi', 'course' => 2, 'subject_name' => 'Gigiyena'])->assertForbidden();
    snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.unplace', $s['board']), ['layer' => 'boshqa'])->assertStatus(422);

    expect(TimetableBoardSnapshot::count())->toBe(0);
});
