<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableSubjectSetting;
use Spatie\Permission\Models\Role;

// Sikl rejimi amallari: "Bo'sh smenaga o'tkazish", "Vaqtincha kafedra mudiriga yozish" (butun blok / to'qnashuv sanalari),
// "Asl holatga qaytarish". Bugun — 02.11.2026 (blok 03.11 dan).
beforeEach(fn () => $this->travelTo(\Illuminate\Support\Carbon::parse('2026-11-02 09:00', 'Asia/Tashkent')));

function caTeacher(string $name, ?string $role = null, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill($extra + [
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Epidemiologiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

function caAs(string $role = 'admin', ?Teacher $t = null)
{
    return test()->withoutVite()->actingAs($t ?? caTeacher('ADMIN ' . $role, $role), 'teacher')->withSession(['active_role' => $role]);
}

/**
 * d2/d22-09a va d2/d22-09b — Epidemiologiya (5 kun, 03.11 dan), ikkalasida TOSHPO'LATOV I paradan: 5 sanada o'qituvchi band.
 * TOSHPO'LATOV seshanba va chorshanba IV parada (13:30) haftalik darsda ham. Kafedra mudiri — XOLMATOV, «Kafedra mudiri xonasi».
 */
function caSetup(): array
{
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 6, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-14'], 'bell_schedule' => TimetableBoard::defaultBellSchedule(6)]);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
        'subject_name' => 'Epidemiologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 701, 'name' => 'Epidemiologiya kafedrasi', 'code' => 'E1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    $mudir = caTeacher('XOLMATOV A. B.', 'kafedra_mudiri', ['department_hemis_id' => 701, 'hemis_id' => 9100, 'is_active' => true]);
    $mudir->forceFill(['full_name' => 'XOLMATOV AZIZ BAXTIYOROVICH'])->save();
    Auditorium::create(['code' => 'KM1', 'name' => 'Kafedra mudiri xonasi', 'volume' => 1, 'active' => true, 'department_hemis_id' => 701]);
    Auditorium::create(['code' => 'R305', 'name' => '305', 'volume' => 30, 'active' => true]);
    // O'quv rejasi: 30 soat amaliy (kuniga 6 soat) — kun rejasi (HEMIS darslari) shundan.
    $mc = \Illuminate\Support\Facades\DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'x', 'specialty_name' => 'davolash ishi',
        'plan_year' => '2022/2023', 'level_code' => '15', 'created_at' => now(), 'updated_at' => now()]);
    \Illuminate\Support\Facades\DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Epidemiologiya',
        'semester' => 9, 'lecture' => 0, 'practice' => 30, 'created_at' => now(), 'updated_at' => now()]);
    $tosh = caTeacher("TOSHPO'LATOV B. A.", null, ['hemis_id' => 5555]);
    $cards = [];
    foreach (['d2/d22-09a', 'd2/d22-09b'] as $g) {
        $cards[$g] = TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 5,
            'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
            'subject_name' => 'Epidemiologiya', 'group_name' => $g, 'kafedra_name' => 'Epidemiologiya kafedrasi',
            'teacher_id' => $tosh->id, 'teacher_name' => "TOSHPO'LATOV B. A.", 'auditorium_code' => 'R305', 'auditorium_name' => '305']);
        // 2026-09-14 + 50 kun = 03.11 (seshanba)
        TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'davolash ishi', 'course' => 5,
            'group_name' => $g, 'subject_name' => 'Epidemiologiya', 'start_index' => 50, 'training_type' => 'practice']);
    }
    foreach ([2 => 'pp/2024-01a', 3 => 'pp/2024-01b'] as $day => $g) {
        TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F2', 'specialty_name' => 'pediatriya', 'course' => 2, 'oqim_label' => '1-oqim',
            'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 1, 'weeks' => 15, 'subject_name' => 'Farmakologiya',
            'group_name' => $g, 'teacher_id' => $tosh->id, 'teacher_name' => "TOSHPO'LATOV B. A.", 'day' => $day, 'pair' => 4]);
    }

    return compact('board', 'cards', 'mudir', 'tosh');
}

function caConflicts(TimetableBoard $board): array
{
    $ctl = app(TimetableController::class);

    return (new ReflectionMethod($ctl, 'cycleConflicts'))->invoke($ctl, $board);
}

function caCard(TimetableBoard $board, string $group): array
{
    return collect(caAs()->postJson(route('admin.timetable.cycle-plan', $board), ['layer' => 'internal'])->assertOk()->json('cycle_cards'))
        ->first(fn ($c) => $c['group'] === $group && $c['type'] === 'practice');
}

const CA_KEY = 'davolash ishi|5|d2/d22-09b|Epidemiologiya|practice';

it('"Bo\'sh smenaga o\'tkazish": d2/d22-09b Epidemiologiya (TOSHPO\'LATOV) — 13:30 ga o\'tadi, faqat 03.11, 04.11 qoladi', function () {
    $s = caSetup();
    expect(array_keys(caConflicts($s['board'])))->toContain(CA_KEY);
    $card = caCard($s['board'], 'd2/d22-09b');
    expect($card['teacher'])->toBe(['id' => $s['tosh']->id, 'name' => "TOSHPO'LATOV B. A."])
        ->and($card['room'])->toBe(['code' => 'R305', 'name' => '305']);
    $advice = caAs()->postJson(route('admin.timetable.cycle-shift-advice', $s['board']), ['key' => CA_KEY])->assertOk()->json('advice');
    expect($advice)->toMatchArray(['kind' => 'partial', 'start' => 7])
        ->and($advice['text'])->toStartWith("13:30 ga o'tkazilsa faqat 03.11, 04.11 qoladi")
        ->and(array_column($advice['remaining'], 'date'))->toBe(['2026-11-03', '2026-11-04']);

    // Klient: qisman — tasdiqda qoladigan sanalar (03.11, 04.11) ko'rsatiladi; so'ng joriy o'qituvchi/xona saqlanib, faqat smena
    // (shift_start) force bilan yoziladi.
    $body = ['specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd2/d22-09b', 'placement_group' => $card['placement_group'],
        'subject_name' => 'Epidemiologiya', 'training_type' => 'practice', 'view' => 'group', 'layer' => 'internal',
        'teacher_id' => $s['tosh']->id, 'auditorium_code' => 'R305', 'shift_start' => 7];
    caAs()->postJson(route('admin.timetable.cycle-assign', $s['board']), $body + ['force' => 1])->assertOk()->assertJson(['shift_start' => 7]);
    // O'qituvchi va xona o'zgarmadi; sikl ichidagi to'qnashuv (09a bilan) yo'qoldi.
    expect($s['cards']['d2/d22-09b']->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $s['tosh']->id, 'auditorium_code' => 'R305'])
        ->and(caConflicts($s['board']))->toBe([]);
});

it('"Vaqtincha kafedra mudiriga yozish" (sikl): to\'qnashuv yo\'qoladi, "vaqtincha" bo\'limida; HEMIS — mudir nomiga; "Asl holatga qaytarish"', function () {
    $s = caSetup();
    expect(caConflicts($s['board']))->toHaveCount(2);
    caAs()->postJson(route('admin.timetable.cycle-mudir-placeholder', $s['board']), ['key' => CA_KEY, 'scope' => 'conflicts', 'reason' => "TOSHPO'LATOV band",
        'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => 'd2/d22-09b', 'subject_name' => 'Epidemiologiya', 'training_type' => 'practice', 'view' => 'group'])
        ->assertOk()->assertJson(['dates' => ['2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07']]);
    // Ikkala blok ham endi to'qnashuvsiz (09b darslari mudirda, TOSHPO'LATOV faqat 09a da); karta o'qituvchisi/xonasi o'zgarmaydi.
    expect(caConflicts($s['board']))->toBe([])
        ->and($s['cards']['d2/d22-09b']->fresh()->only(['teacher_id', 'auditorium_code']))->toBe(['teacher_id' => $s['tosh']->id, 'auditorium_code' => 'R305']);
    $card = caCard($s['board'], 'd2/d22-09b');
    expect($card['conflict'])->toBeNull()
        ->and($card['mudir_ph'])->toMatchArray(['teacher_id' => $s['mudir']->id, 'mudir_name' => 'XOLMATOV AZIZ BAXTIYOROVICH', 'room_code' => 'KM1',
            'room_name' => 'Kafedra mudiri xonasi', 'orig_teacher_name' => "TOSHPO'LATOV B. A.", 'reason' => "TOSHPO'LATOV band"]);
    $log = \App\Models\TimetableChangeRequest::where('route_name', 'admin.timetable.cycle-mudir-placeholder')->latest('id')->first();
    expect($log)->not->toBeNull()->and($log->payload['reason'])->toBe("TOSHPO'LATOV band");

    // HEMIS: 09b amaliyoti — mudir (HEMIS 9100) va mudir xonasi, placeholder — asl o'qituvchi.
    $ctl = app(TimetableController::class);
    $lessons = (new ReflectionMethod($ctl, 'hemisCycleLessons'))->invoke($ctl, $s['board'], [],
        TimetableCard::whereIn('id', [$s['cards']['d2/d22-09a']->id, $s['cards']['d2/d22-09b']->id])->get());
    $b = collect($lessons)->first(fn ($v, $k) => str_contains($k, '09b'));
    $a = collect($lessons)->first(fn ($v, $k) => str_contains($k, '09a'));
    $first = fn ($x) => collect($x)->flatten(1)->firstWhere('part', 'A');
    expect($first($b))->toMatchArray(['employee_id' => 9100, 'auditorium_code' => 'KM1', 'placeholder' => "TOSHPO'LATOV B. A."])
        ->and($first($a))->toMatchArray(['employee_id' => 5555, 'auditorium_code' => 'R305']);

    // Asl holatga qaytarish — belgi olib tashlanadi, to'qnashuv qaytadi (bandlik tekshirilmaydi).
    caAs()->postJson(route('admin.timetable.cycle-mudir-placeholder.revert', $s['board']), ['key' => CA_KEY])->assertOk();
    expect(caConflicts($s['board']))->toHaveCount(2)
        ->and(caCard($s['board'], 'd2/d22-09b')['mudir_ph'])->toBeNull()
        ->and(TimetableCyclePlacement::where('group_name', 'd2/d22-09b')->first()->lecture_slots['mudir_ph'] ?? null)->toBeNull();
    caAs()->postJson(route('admin.timetable.cycle-mudir-placeholder.revert', $s['board']), ['key' => CA_KEY])->assertStatus(422);
});

it('sikl mudir belgisi: butun blok; huquq — boshqa kafedra mudiri va o\'qituvchi rad etiladi', function () {
    $s = caSetup();
    $other = caTeacher('BOSHQA MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 999]);
    \Illuminate\Support\Facades\DB::table('departments')->insert(['department_hemis_id' => 999, 'name' => 'Anatomiya kafedrasi', 'code' => 'A1',
        'structure_type_code' => 12, 'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1,
        'created_at' => now(), 'updated_at' => now()]);
    caAs('kafedra_mudiri', $other)->postJson(route('admin.timetable.cycle-mudir-placeholder', $s['board']), ['key' => CA_KEY])->assertStatus(403);
    caAs('oqituvchi', $s['tosh'])->postJson(route('admin.timetable.cycle-mudir-placeholder', $s['board']), ['key' => CA_KEY])->assertStatus(403);
    // O'z kafedrasi mudiri — darhol, butun blok (dates = null).
    caAs('kafedra_mudiri', $s['mudir'])->postJson(route('admin.timetable.cycle-mudir-placeholder', $s['board']), ['key' => CA_KEY, 'scope' => 'all'])
        ->assertOk()->assertJson(['dates' => null]);
    expect(caConflicts($s['board']))->toBe([]);
});
