<?php

use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableBoardSnapshot;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use App\Models\TimetableGridSetting;
use App\Services\Timetable\BoardSnapshots;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
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

it('sinxronlashdan keyin faqat shu safar yaratilgan karta eski joyni oladi — avvaldan joylanmagan karta bo\'sh qoladi', function () {
    $s = snapSetup();
    // Shu birlikda ataylab joylanmagan 1 soatlik karta: sinxronlashda saqlanadi (id o'zgarmaydi)
    $spare = TimetableCard::create($s['base'] + ['len_half' => 1, 'weeks' => 15]);
    $before = app(BoardSnapshots::class)->collect($s['board']);
    // Sinxronlash: 3 soatlik karta (hafta soni o'zgargan) o'chirilib qayta yaratildi
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $s['old']->delete();
    $new = TimetableCard::create($s['base'] + ['len_half' => 3, 'weeks' => 10]);

    expect(app(BoardSnapshots::class)->keepPlacementOfRecreated($s['board'], $before)['cards'])->toBe(1)
        ->and($new->fresh()->only(['day', 'pair', 'ext_day', 'ext_pair']))->toBe(['day' => 2, 'pair' => 3, 'ext_day' => 2, 'ext_pair' => 1])
        ->and($spare->fresh()->only(['day', 'pair', 'ext_day', 'ext_pair']))->toBe(['day' => null, 'pair' => null, 'ext_day' => null, 'ext_pair' => null])
        ->and(TimetableBoardSnapshot::count())->toBe(0);
});

it('hafta-hafta (shablonsiz) joylangan karta: qayta yaratilsa ham, nusxadan tiklansa ham haftalik joylari qaytadi', function () {
    $s = snapSetup();
    // Ichki jadvalda shablon joyi yo'q — faqat 3- va 4-haftada joylangan
    $s['old']->update(['day' => null, 'pair' => null, 'ext_day' => null, 'ext_pair' => null]);
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    TimetableCardOverride::create(['card_id' => $s['old']->id, 'week' => 3, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    TimetableCardOverride::create(['card_id' => $s['old']->id, 'week' => 4, 'day' => 3, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    $weekOf = fn (int $cardId) => TimetableCardOverride::where('card_id', $cardId)->where('cancelled', false)->orderBy('week')
        ->get()->map(fn ($o) => $o->week . ':' . $o->day . '/' . $o->pair)->all();
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');

    // Saqlanib qolgan karta: haftalik joylari olib tashlangan — nusxadan qaytadi
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['cards'])->toBe(1)->and($r['week_moves'])->toBe(2)
        ->and($weekOf($s['old']->id))->toBe(['3:2/3', '4:3/1'])
        ->and($s['old']->fresh()->day)->toBeNull();

    // Sinxronlash: karta (hafta soni o'zgargan) qayta yaratildi — yangi karta o'sha haftalarda joylanadi
    $before = app(BoardSnapshots::class)->collect($s['board']);
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $s['old']->delete();
    $new = TimetableCard::create($s['base'] + ['len_half' => 3, 'weeks' => 10]);
    expect(app(BoardSnapshots::class)->keepPlacementOfRecreated($s['board'], $before)['cards'])->toBe(1)
        ->and($weekOf($new->id))->toBe(['3:2/3', '4:3/1'])
        ->and($new->fresh()->day)->toBeNull()
        ->and($new->fresh()->teacher_id)->toBe($s['head']->id);
});

it('qisman tiklash: saqlanib qolgan karta o\'z eski joyiga qaytadi, ko\'chirilgan kartaning eski joyini olmaydi', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $mon = TimetableCard::create($fiz + ['day' => 1, 'pair' => 1]);
    $tue = TimetableCard::create($fiz + ['day' => 2, 'pair' => 1, 'teacher_id' => $s['admin']->id, 'teacher_name' => 'ADMIN']);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    // Dushanbadagi karta chorshanbaga ko'chirildi, seshanbadagisi bo'shatildi
    $mon->update(['day' => 3]);
    $tue->update(['day' => null, 'pair' => null]);

    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['cards'])->toBe(1)
        ->and($tue->fresh()->only(['day', 'pair', 'teacher_id']))->toBe(['day' => 2, 'pair' => 1, 'teacher_id' => $s['admin']->id])
        ->and($mon->fresh()->only(['day', 'pair']))->toBe(['day' => 3, 'pair' => 1]);
});

it('«Kartochkalar» (to\'liq) qayta yaratgan bir xil karta: nusxadan hafta-hafta joylari kartama-karta qaytadi', function () {
    $s = snapSetup();
    $s['old']->update(['day' => null, 'pair' => null, 'ext_day' => null, 'ext_pair' => null]);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'before_generate');   // 5-hafta: 4-kun 1-para
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $s['old']->delete();
    $new = TimetableCard::create($s['base'] + ['len_half' => 3, 'weeks' => 15]);

    $r = app(BoardSnapshots::class)->restorePlacement($s['board'], $snap->data(), false, [], false);
    expect($r['cards'])->toBe(1)
        ->and(TimetableCardOverride::where('card_id', $new->id)->get(['week', 'day', 'pair'])->toArray())->toBe([['week' => 5, 'day' => 4, 'pair' => 1]])
        ->and($new->fresh()->teacher_id)->toBe($s['head']->id);
});

it('tiklash joy bandligini tekshiradi: eski joyni boshqa karta egallagan bo\'lsa — karta joylanmagan qoladi va hisobotda chiqadi', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $a = TimetableCard::create($fiz + ['day' => 1, 'pair' => 1]);
    $b = TimetableCard::create($fiz + ['day' => 2, 'pair' => 1]);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    // A bo'shatildi, B A'ning eski joyiga (dushanba) ko'chirildi
    $a->update(['day' => null, 'pair' => null]);
    $b->update(['day' => 1]);
    $url = route('admin.timetable.snapshots.restore', [$s['board'], $snap]);

    $dry = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 1])->assertOk()->json();
    expect($dry['cards'])->toBe(0)->and($dry['conflicts'])->toBe(1)
        ->and($dry['conflict_examples'][0])->toContain('Guruh band');
    // Konsol buyrug'i ham xuddi shu tekshiruv bilan (sinov rejimi)
    $this->artisan('timetable:restore-placement', ['board' => $s['board']->id, '--snapshot' => $snap->id])
        ->expectsOutputToContain('Tekshirildi: 1/1')
        ->expectsOutputToContain("to'qnashuv sababli yozilmadi 1")->assertExitCode(0);
    $r = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 0])->assertOk()->json();
    expect($r['conflicts'])->toBe(1)
        ->and($a->fresh()->only(['day', 'pair']))->toBe(['day' => null, 'pair' => null])
        ->and($b->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1]);
});

it('haftalik joy ham tekshiriladi: o\'sha haftada joy band bo\'lsa — o\'sha hafta yozilmaydi', function () {
    $s = snapSetup();
    $s['old']->update(['day' => null, 'pair' => null, 'ext_day' => null, 'ext_pair' => null]);
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    TimetableCardOverride::create(['card_id' => $s['old']->id, 'week' => 3, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    TimetableCardOverride::create(['card_id' => $s['old']->id, 'week' => 4, 'day' => 3, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    // Shu guruhning boshqa fani endi seshanba 3-paraga qo'yilgan (har hafta)
    TimetableCard::create(array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15, 'day' => 2, 'pair' => 3]));

    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['week_moves'])->toBe(1)->and($r['conflicts'])->toBe(1)
        ->and(TimetableCardOverride::where('card_id', $s['old']->id)->pluck('week')->all())->toBe([4]);
});

it('aralash birlik: shablondagi joy to\'lsa, qayta yaratilgan karta hafta-hafta joylangan eski karta o\'rniga o\'tadi', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $tpl = TimetableCard::create($fiz + ['day' => 1, 'pair' => 1]);   // dushanba (shablon)
    $wk = TimetableCard::create($fiz);                                   // faqat 3-haftada seshanba
    TimetableCardOverride::create(['card_id' => $wk->id, 'week' => 3, 'day' => 2, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    $before = app(BoardSnapshots::class)->collect($s['board']);
    // Sinxronlash: ikkala karta (hafta soni o'zgargan) 2 + 1 soat bo'lib qayta yaratildi
    TimetableCardOverride::where('card_id', $wk->id)->delete();
    $tpl->delete();
    $wk->delete();
    $two = TimetableCard::create(array_merge($fiz, ['weeks' => 10]));
    $one = TimetableCard::create(array_merge($fiz, ['len_half' => 1, 'weeks' => 10]));

    $r = app(BoardSnapshots::class)->keepPlacementOfRecreated($s['board'], $before);
    expect($r['cards'])->toBe(2)
        ->and($two->fresh()->only(['day', 'pair']))->toBe(['day' => 1, 'pair' => 1])
        ->and($one->fresh()->only(['day', 'pair']))->toBe(['day' => null, 'pair' => null])
        ->and(TimetableCardOverride::where('card_id', $one->id)->get(['week', 'day', 'pair'])->toArray())->toBe([['week' => 3, 'day' => 2, 'pair' => 1]]);
});

it('haftalik joy nusxadagi xonasi bilan tekshiriladi (kartaning asosiy xonasi bilan emas)', function () {
    $s = snapSetup();
    $s['old']->update(['day' => null, 'pair' => null, 'ext_day' => null, 'ext_pair' => null, 'auditorium_code' => 'R1', 'auditorium_name' => 'R1']);
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    TimetableCardOverride::create(['card_id' => $s['old']->id, 'week' => 3, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false,
        'auditorium_code' => 'R9', 'auditorium_name' => 'R9']);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    $url = route('admin.timetable.snapshots.restore', [$s['board'], $snap]);
    // Boshqa guruhning darsi shu vaqtda kartaning asosiy xonasida (R1) — haftalik joyga (R9) xalaqit bermaydi
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $blocker = TimetableCard::create(array_merge($s['base'], ['group_name' => 'g-02a (o\'z)', 'subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15,
        'day' => 2, 'pair' => 3, 'auditorium_code' => 'R1', 'auditorium_name' => 'R1']));
    $r = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 0])->assertOk()->json();
    expect($r['week_moves'])->toBe(1)->and($r['conflicts'])->toBe(0)
        ->and(TimetableCardOverride::where('card_id', $s['old']->id)->where('week', 3)->value('auditorium_code'))->toBe('R9');

    // O'sha dars nusxadagi xonada (R9) bo'lsa — haftalik joy yozilmaydi
    TimetableCardOverride::where('card_id', $s['old']->id)->delete();
    $blocker->update(['auditorium_code' => 'R9', 'auditorium_name' => 'R9']);
    $r = snapAs($s['admin'], 'superadmin')->postJson($url, ['dry_run' => 0])->assertOk()->json();
    expect($r['week_moves'])->toBe(0)->and($r['conflicts'])->toBe(1)
        ->and($r['conflict_examples'][0])->toContain('Auditoriya band')
        ->and(TimetableCardOverride::where('card_id', $s['old']->id)->count())->toBe(0);
});

it('panjaradan chiqib ketadigan joylashuv yozilmaydi (kun oxiridagi karta soati ko\'paysa)', function () {
    $s = snapSetup();
    $last = $s['board']->pairCount() - 1;
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $card = TimetableCard::create($fiz + ['day' => 3, 'pair' => $last]);   // kunning oxirgi ikki yarim-sloti
    $before = app(BoardSnapshots::class)->collect($s['board']);
    $card->delete();
    $two = TimetableCard::create(array_merge($fiz, ['weeks' => 10]));
    $one = TimetableCard::create(array_merge($fiz, ['len_half' => 1, 'weeks' => 10]));

    $r = app(BoardSnapshots::class)->keepPlacementOfRecreated($s['board'], $before,
        app(\App\Http\Controllers\Admin\TimetableController::class)->restoreConflictChecker());
    expect($r['cards'])->toBe(1)->and($r['conflicts'])->toBe(1)
        ->and($r['conflict_examples'][0])->toContain('chegarasidan tashqarida')
        ->and($two->fresh()->only(['day', 'pair']))->toBe(['day' => 3, 'pair' => $last])
        ->and($one->fresh()->only(['day', 'pair']))->toBe(['day' => null, 'pair' => null]);
});

it('shablonga qo\'yishda boshqa kartaning haftalik joyi ham tekshiriladi (o\'sha hafta o\'tilmasa — to\'qnashuv emas)', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $b = TimetableCard::create($fiz);   // faqat 3-haftada: juma 7-para
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 5, 'pair' => 7, 'start_half' => 0, 'cancelled' => false]);
    $a = TimetableCard::create(array_merge($fiz, ['subject_name' => 'Kimyo']));
    $url = route('admin.timetable.cards.place', $a);

    $err = snapAs($s['admin'], 'superadmin')->postJson($url, ['day' => 5, 'pair' => 7, 'layer' => 'internal'])->assertStatus(422)->json('error');
    expect($err)->toBe('3-hafta: Guruh band: g-01a (o\'z) — F1, 2-kurs · g-01a · Fizika (amaliy)');
    // A 3-haftada o'tilmaydi — o'sha haftadagi joy xalaqit bermaydi
    TimetableCardOverride::create(['card_id' => $a->id, 'week' => 3, 'cancelled' => true]);
    snapAs($s['admin'], 'superadmin')->postJson($url, ['day' => 5, 'pair' => 7, 'layer' => 'internal'])->assertOk();
    expect($a->fresh()->only(['day', 'pair']))->toBe(['day' => 5, 'pair' => 7]);
});

it('tiklashda shablon joyi boshqa kartaning o\'sha vaqtdagi haftalik joyi bilan ham tekshiriladi', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $a = TimetableCard::create($fiz + ['day' => 1, 'pair' => 1]);
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    // A bo'shatildi; boshqa fan faqat 3-haftada A'ning joyiga qo'yildi
    $a->update(['day' => null, 'pair' => null]);
    $b = TimetableCard::create(array_merge($fiz, ['subject_name' => 'Kimyo']));
    TimetableCardOverride::create(['card_id' => $b->id, 'week' => 3, 'day' => 1, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);

    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['conflicts'])->toBe(1)
        ->and($r['conflict_examples'][0])->toContain('3-hafta: Guruh band')
        ->and($a->fresh()->day)->toBeNull();
});

it('tiklash doskani bir marta o\'qiydi — har tekshiruvda butun doska qayta yuklanmaydi', function () {
    $s = snapSetup();
    $fiz = array_merge($s['base'], ['subject_name' => 'Fizika', 'len_half' => 2, 'weeks' => 15]);
    $tpl = [];
    foreach ([1, 2, 3, 4] as $d) {
        $tpl[] = TimetableCard::create(array_merge($fiz, ['subject_name' => 'Fan ' . $d, 'day' => $d, 'pair' => 9]));
    }
    $wk = TimetableCard::create(array_merge($fiz, ['subject_name' => 'Haftalik']));
    foreach (range(1, 8) as $w) {
        TimetableCardOverride::create(['card_id' => $wk->id, 'week' => $w, 'day' => 5, 'pair' => 1 + $w, 'start_half' => 0, 'cancelled' => false]);
    }
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    TimetableCard::whereIn('id', collect($tpl)->pluck('id'))->update(['day' => null, 'pair' => null]);
    TimetableCardOverride::where('card_id', $wk->id)->delete();

    // Butun doska bo'yicha (kun bo'yicha emas) kartalar so'rovlari
    $boardWide = 0;
    DB::listen(function ($q) use (&$boardWide) {
        if (str_contains($q->sql, 'timetable_cards') && str_contains($q->sql, '`board_id` = ?') && !str_contains($q->sql, '`day` = ?')) {
            $boardWide++;
        }
    });
    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['internal'])->toBe(4)->and($r['week_moves'])->toBe(8)->and($r['conflicts'])->toBe(0)
        ->and($boardWide)->toBeLessThanOrEqual(5);
});

it('rad etilgan haftalik joy keyingilarini to\'smaydi (birinchi tekshiruv haftalik bo\'lsa ham)', function () {
    $s = snapSetup();
    $base = array_merge($s['base'], ['len_half' => 2, 'weeks' => 15]);
    // Z: MUDIR juma 7-parada (har hafta) — X'ning o'qituvchisi band
    TimetableCard::create(array_merge($base, ['group_name' => 'g-03a', 'subject_name' => 'Z', 'day' => 5, 'pair' => 7,
        'teacher_id' => $s['head']->id, 'teacher_name' => 'MUDIR', 'auditorium_code' => 'R7', 'auditorium_name' => 'R7']));
    $x = TimetableCard::create(array_merge($base, ['group_name' => 'g-02a', 'subject_name' => 'X', 'teacher_id' => $s['head']->id, 'teacher_name' => 'MUDIR']));
    $y = TimetableCard::create(array_merge($base, ['group_name' => 'g-04a', 'subject_name' => 'Y', 'teacher_id' => $s['admin']->id, 'teacher_name' => 'ADMIN']));
    foreach ([$x, $y] as $c) {
        TimetableCardOverride::create(['card_id' => $c->id, 'week' => 3, 'day' => 5, 'pair' => 7, 'start_half' => 0, 'cancelled' => false,
            'auditorium_code' => 'R5', 'auditorium_name' => 'R5']);
    }
    $snap = app(BoardSnapshots::class)->take($s['board'], 'daily');
    TimetableCardOverride::whereIn('card_id', [$x->id, $y->id])->delete();

    // X (birinchi tekshiruv) o'qituvchisi band — rad etiladi; uning R5 xonasi Y'ga xalaqit bermasligi kerak
    $r = snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.snapshots.restore', [$s['board'], $snap]), ['dry_run' => 0])->assertOk()->json();
    expect($r['conflicts'])->toBe(1)->and($r['week_moves'])->toBe(1)
        ->and($r['conflict_examples'][0])->toContain("O'qituvchi band")
        ->and(TimetableCardOverride::where('card_id', $x->id)->count())->toBe(0)
        ->and(TimetableCardOverride::where('card_id', $y->id)->where('week', 3)->value('auditorium_code'))->toBe('R5');
});

it('kartochka yaratish va xavfli amaldan oldingi nusxa PHP xotira chegarasini 1024M ga ko\'taradi (serverdagi 128M da to\'xtardi)', function () {
    $s = snapSetup();
    $orig = ini_get('memory_limit');
    // Server chegarasi kabi past: hozirgi sarfdan biroz yuqori (undan past qilib bo'lmaydi).
    $low = (string) (memory_get_usage(true) + 64 * 1024 * 1024);
    try {
        // Kartochkalarni yaratish (tasdiqlangan oqim yo'q — 422, lekin chegara so'rov boshida ko'tariladi)
        ini_set('memory_limit', $low);
        snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.generate', $s['board']));
        expect(ini_get('memory_limit'))->toBe('1024M');

        // Xavfli amaldan oldingi nusxa (bo'shatish)
        ini_set('memory_limit', $low);
        snapAs($s['admin'], 'superadmin')->postJson(route('admin.timetable.boards.unplace', $s['board']), ['courses' => [2], 'layer' => 'internal'])->assertOk();
        expect(ini_get('memory_limit'))->toBe('1024M');
    } finally {
        ini_set('memory_limit', $orig);
    }
});

it('kunlik nusxa va tiklash buyruqlari xotira chegarasini 1024M gacha ko\'taradi, cheklovsiz (-1) bo\'lsa tegmaydi', function () {
    $s = snapSetup();
    $orig = ini_get('memory_limit');
    $low = (string) (memory_get_usage(true) + 64 * 1024 * 1024);
    try {
        ini_set('memory_limit', $low);
        $this->artisan('timetable:snapshot', ['board' => $s['board']->id])->assertExitCode(0);
        expect(ini_get('memory_limit'))->toBe('1024M');

        $snap = \App\Models\TimetableBoardSnapshot::where('board_id', $s['board']->id)->latest('id')->first();
        ini_set('memory_limit', $low);
        $this->artisan('timetable:restore-placement', ['board' => $s['board']->id, '--snapshot' => $snap->id])->assertExitCode(0);
        expect(ini_get('memory_limit'))->toBe('1024M');

        // Cheklovsiz CLI — 1024M ga tushirilmaydi
        ini_set('memory_limit', '-1');
        $this->artisan('timetable:snapshot', ['board' => $s['board']->id])->assertExitCode(0);
        expect(ini_get('memory_limit'))->toBe('-1');
    } finally {
        ini_set('memory_limit', $orig);
    }
});
