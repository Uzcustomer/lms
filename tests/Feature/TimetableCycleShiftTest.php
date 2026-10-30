<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

require_once __DIR__ . '/../Support/TimetableLegacyOccupancy.php';

function csTeacher(string $name, ?string $role = null): Teacher
{
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Farmakologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

/**
 * Pediatriya 5-kurs, Klinik farmakologiya sikli (5 kun): p/p22-02a (2-oqim) va p/p22-03c (3-oqim) — bitta o'qituvchi (YOQUBOV),
 * ikkala oqim bloki bir xil sanalarda (14.09–18.09). $shifts — [guruhcha => smena soati].
 */
function csSetup(array $shifts = []): array
{
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $t = csTeacher('YOQUBOV U. B.');
    $cards = [];
    foreach (['p/p22-02a' => '2-oqim', 'p/p22-03c' => '3-oqim'] as $g => $oqim) {
        $cards[$g] = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
            'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
            'teacher_id' => $t->id, 'teacher_name' => 'YOQUBOV U. B.']);
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / ' . $oqim,
            'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice',
            'lecture_slots' => isset($shifts[$g]) ? ['shift_g' => [$g => $shifts[$g]]] : null]);
    }

    return ['board' => $board, 'teacher' => $t, 'cards' => $cards];
}

function csCall(TimetableBoard $board, string $method, ...$args)
{
    $ctl = app(TimetableController::class);
    request()->attributes->remove('timetable.cycle_units.' . $board->id);

    return (new ReflectionMethod($ctl, $method))->invoke($ctl, $board, ...$args);
}

it('smena: bir o\'qituvchi, ikki guruhcha, bir xil sanalar — biri I para, biri IV para (13:30): to\'qnashuv va ogohlantirish yo\'q', function () {
    $s = csSetup(['p/p22-02a' => 1, 'p/p22-03c' => 7]);
    expect(csCall($s['board'], 'cycleConflicts'))->toBe([])
        ->and(app(TimetableController::class)->internalConflicts($s['board'])['count'])->toBe(0)
        ->and(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get()))->toBe(['teachers' => [], 'rooms' => []]);
    // Mark (ichki jadval): 03c amaliy darslari 7-soatdan, 02a — 1-soatdan
    $admin = csTeacher('ADMIN', 'admin');
    $plan = test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json();
    $dp = fn (string $g) => collect($plan['rows'])->firstWhere('group', $g)['blocks'][0]['day_plan'];
    expect(collect($dp('p/p22-03c'))->pluck('ps')->unique()->values()->all())->toBe([7])
        ->and(collect($dp('p/p22-02a'))->pluck('ps')->unique()->values()->all())->toBe([1]);
    assertBusyMatchesLegacy($s['board']);
});

it('smena: ikkalasi ham I parada — to\'qnashuv; ogohlantirishda haqiqiy kesishgan sanalar; smena o\'zgartirilsa yo\'qoladi', function () {
    $s = csSetup();
    $c = csCall($s['board'], 'cycleConflicts');
    expect($c['Pediatriya|5|p/p22-03c|Klinik farmakologiya|practice']['text'])
        ->toBe("O'qituvchi YOQUBOV U. B. band: p/p22-02a, Klinik farmakologiya, 14.09–18.09")
        ->and(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get())['teachers'])
        ->toBe([$s['teacher']->id => 'p/p22-02a, Klinik farmakologiya, 14.09–18.09']);

    // "O'qituvchi biriktirish" (sikl bloki): 03c ga IV para smenasi — saqlanadi, ogohlantirishsiz
    $head = csTeacher('MUDIR', 'admin');
    $params = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'p/p22-03c', 'placement_group' => 'Ped / 3-oqim',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group'];
    $as = fn () => test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'admin']);
    $opt = $as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $params)->assertOk()->json();
    expect($opt['shift_start'])->toBe(0)->and((array) $opt['busy_teachers'])->toHaveKey((string) $s['teacher']->id)
        ->and($opt['shift_options'][3])->toBe(['start' => 7, 'label' => 'IV para']);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['teacher_id' => $s['teacher']->id, 'shift_start' => 7])
        ->assertOk()->assertJson(['shift_start' => 7]);
    expect(csCall($s['board'], 'cycleConflicts'))->toBe([]);
    $opt = $as()->postJson(route('admin.timetable.cycle-assign-options', $s['board']), $params)->assertOk()->json();
    expect($opt['shift_start'])->toBe(7)->and((array) $opt['busy_teachers'])->toBe([]);

    // Smena I paraga qaytarilsa va boshqa o'qituvchidan YOQUBOVga o'tkazilsa — ogohlantirish (shu so'rovdagi smena bilan)
    \App\Models\TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->update(['teacher_id' => null, 'teacher_name' => null]);
    $as()->postJson(route('admin.timetable.cycle-assign', $s['board']), $params + ['teacher_id' => $s['teacher']->id, 'shift_start' => 0])
        ->assertStatus(409)->assertJson(['needs_confirm' => true]);
    // Ogohlantirishda smena saqlanmaydi (bekor qilinadi)
    expect(TimetableCyclePlacement::where('group_name', 'Ped / 3-oqim')->first()->lecture_slots['shift_g'])->toBe(['p/p22-03c' => 7]);
});

it('sana: guruhchaning fanlari har xil oqimda — joylashuv fan oqimidan olinadi (birinchi kartaning oqimidan emas)', function (bool $otherFirst) {
    $s = csSetup();
    // 02a ning boshqa sikl fani (birinchi karta, 1-oqim) va 1-oqimning Klinik farmakologiya joylashuvi (boshqa sanalar).
    TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Gematologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $first = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Gematologiya', 'group_name' => 'p/p22-02a', 'kafedra_name' => 'K']);
    if ($otherFirst) {
        TimetableCard::whereKey($first->id)->update(['id' => 1]);   // eng birinchi karta (aks holda — oxirgi)
    }
    TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 1-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 21, 'training_type' => 'practice']);   // 05.10–09.10
    // 02a ning Klinik farmakologiyasi — 2-oqimda (14.09): 03c bilan kesishadi, sanalar 14.09–18.09
    expect(csCall($s['board'], 'unitBusy', TimetableCard::whereKey($s['cards']['p/p22-03c']->id)->get())['teachers'])
        ->toBe([$s['teacher']->id => 'p/p22-02a, Klinik farmakologiya, 14.09–18.09']);
    $admin = csTeacher('ADMIN', 'admin');
    $plan = test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin'])
        ->postJson(route('admin.timetable.cycle-plan', $s['board']), ['layer' => 'internal', 'courses' => [5]])->assertOk()->json();
    $row = collect($plan['rows'])->firstWhere('group', 'p/p22-02a');
    expect(collect($row['blocks'])->firstWhere('subject', 'Klinik farmakologiya')['from'])->toBe(0);
})->with(['boshqa fan kartasi birinchi' => [true], 'boshqa fan kartasi oxirgi' => [false]]);

it('xona: bir oqimdagi guruhcha xonasi — bir smenada band (ro\'yxatda yo\'q, saqlab bo\'lmaydi), boshqa smenada bo\'sh', function () {
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14']]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5,
        'subject_name' => 'Klinik farmakologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    \App\Models\Auditorium::create(['code' => 'R1', 'name' => '101', 'volume' => 30, 'active' => true]);
    foreach (['p/p22-02a' => 'R1', 'p/p22-02b' => null] as $g => $room) {
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'Ped', 'specialty_name' => 'Pediatriya', 'course' => 5,
            'oqim_label' => '2-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Klinik farmakologiya', 'group_name' => $g, 'kafedra_name' => 'Farmakologiya kafedrasi',
            'auditorium_code' => $room, 'auditorium_name' => $room ? '101' : null]);
    }
    $pl = TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'Ped / 2-oqim',
        'subject_name' => 'Klinik farmakologiya', 'start_index' => 0, 'training_type' => 'practice']);
    $admin = csTeacher('ADMIN', 'admin');
    $as = fn () => test()->withoutVite()->actingAs($admin, 'teacher')->withSession(['active_role' => 'admin']);
    $params = ['specialty_name' => 'Pediatriya', 'course' => 5, 'group_name' => 'p/p22-02b', 'placement_group' => 'Ped / 2-oqim',
        'subject_name' => 'Klinik farmakologiya', 'training_type' => 'practice', 'view' => 'group'];
    $rooms = fn () => collect($as()->postJson(route('admin.timetable.cycle-assign-options', $board), $params)->assertOk()->json('rooms'))->pluck('code')->all();
    expect($rooms())->not->toContain('R1');
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['auditorium_code' => 'R1'])->assertStatus(422);
    // 02b — IV para smenasi: 02a bilan bir vaqtda emas
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['shift_start' => 7])->assertOk();
    expect($rooms())->toContain('R1');
    $as()->postJson(route('admin.timetable.cycle-assign', $board), $params + ['auditorium_code' => 'R1'])->assertOk();
    expect($pl->fresh()->lecture_slots['shift_g'])->toBe(['p/p22-02b' => 7])
        ->and(csCall($board, 'cycleConflicts'))->toBe([]);
});
