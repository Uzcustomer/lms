<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\Auditorium;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCyclePlacement;
use App\Models\TimetableGridSetting;
use App\Models\TimetableSubjectSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

require_once __DIR__ . '/../Support/TimetableLegacyOccupancy.php';

function ubTeacher(string $name, ?string $role = null, array $extra = []): Teacher
{
    $t = new Teacher();
    $t->forceFill(array_merge([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'Patologik anatomiya kafedrasi', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ], $extra))->save();
    if ($role) {
        Role::findOrCreate($role, 'web');
        $t->assignRole($role);
    }

    return $t;
}

/**
 * Kafedra mudiri: Patologik anatomiya · 1-son davolash · 3-kurs · d1/d24-03a (haftalik, dushanba 1-para). Shu vaqtda:
 * d1/d24-03b — o'sha fan, USTOZ A (haftalik, R101); d1/d24-03c — Gistologiya sikli (07.09 dan 5 kun), USTOZ B, R102.
 */
function ubSetup(): array
{
    Role::findOrCreate('kafedra_mudiri', 'web');
    DB::table('departments')->insert([
        'department_hemis_id' => 601, 'name' => 'Patologik anatomiya kafedrasi', 'code' => 'PA', 'structure_type_code' => 12,
        'structure_type_name' => 'Kafedra', 'locality_type_code' => 11, 'locality_type_name' => 'Mahalliy', 'active' => 1,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $head = ubTeacher('MUDIR', 'kafedra_mudiri', ['department_hemis_id' => 601]);
    $a = ubTeacher('USTOZ A', null, ['department_hemis_id' => 601]);
    $b = ubTeacher('USTOZ B', null, ['department_hemis_id' => 601]);
    foreach ([['R101', 20], ['R102', 20], ['R103', 30]] as [$code, $vol]) {
        Auditorium::create(['code' => $code, 'name' => substr($code, 1), 'volume' => $vol, 'active' => true]);
    }
    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-07']]);
    $fac = '1-son davolash ishi fakulteti';
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'Davolash ishi', 'course' => 3,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15]);
    $card = fn (array $x) => TimetableCard::create(array_merge(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => 'Davolash ishi',
        'course' => 3, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 12, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
        'subject_name' => 'Patologik anatomiya', 'kafedra_name' => 'Patologik anatomiya kafedrasi'], $x));
    $unit = $card(['group_name' => 'd1/d24-03a', 'day' => 1, 'pair' => 1]);
    $card(['group_name' => 'd1/d24-03b', 'day' => 1, 'pair' => 1, 'teacher_id' => $a->id, 'teacher_name' => 'USTOZ A',
        'auditorium_code' => 'R101', 'auditorium_name' => '101']);
    TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Davolash ishi', 'course' => 3,
        'subject_name' => 'Gistologiya', 'mode' => 'cycle', 'cycle_days' => 5]);
    $card(['group_name' => 'd1/d24-03c', 'subject_name' => 'Gistologiya', 'kafedra_name' => 'Gistologiya kafedrasi',
        'teacher_id' => $b->id, 'teacher_name' => 'USTOZ B', 'auditorium_code' => 'R102', 'auditorium_name' => '102']);
    TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Davolash ishi', 'course' => 3,
        'group_name' => 'd1/d24-03c', 'subject_name' => 'Gistologiya', 'start_index' => 0, 'training_type' => 'practice']);
    $params = ['faculty_name' => $fac, 'specialty_name' => 'Davolash ishi', 'course' => 3, 'subject_name' => 'Patologik anatomiya',
        'training_type' => 'practice', 'group_name' => 'd1/d24-03a'];
    $as = fn () => test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri']);

    return compact('head', 'a', 'b', 'board', 'unit', 'params', 'as');
}

it('kafedra mudiri: unit-busy va assign-room 200 qaytaradi (Patologik anatomiya · d1/d24-03a)', function () {
    $s = ubSetup();
    $res = ($s['as'])()->getJson(route('admin.timetable.unit-busy', ['board' => $s['board']->id] + $s['params']))->assertOk()->json();
    expect($res)->not->toHaveKey('warning')
        ->and($res['teachers'])->toBe([
            (string) $s['a']->id => 'd1/d24-03b, Patologik anatomiya, 07.09–14.12',
            (string) $s['b']->id => 'd1/d24-03c, Gistologiya, 07.09',
        ])
        ->and($res['rooms'])->toBe(['R101' => 'd1/d24-03b, Patologik anatomiya, 07.09–14.12', 'R102' => 'd1/d24-03c, Gistologiya, 07.09']);

    // Bo'sh xona — darhol biriktiriladi
    ($s['as'])()->postJson(route('admin.timetable.assign-room', $s['board']), $s['params'] + ['auditorium_code' => 'R103'])
        ->assertOk()->assertJson(['ok' => true, 'auditorium_code' => 'R103', 'affected' => 1]);
    expect($s['unit']->fresh()->auditorium_code)->toBe('R103');
    // Sikl bilan band xona — ogohlantirish, "Baribir biriktirish" bilan — 200
    ($s['as'])()->postJson(route('admin.timetable.assign-room', $s['board']), $s['params'] + ['auditorium_code' => 'R102'])
        ->assertStatus(409)->assertJson(['needs_confirm' => true, 'conflicts' => 'd1/d24-03c, Gistologiya, 07.09']);
    ($s['as'])()->postJson(route('admin.timetable.assign-room', $s['board']), $s['params'] + ['auditorium_code' => 'R102', 'force' => 1])->assertOk();
    expect($s['unit']->fresh()->auditorium_code)->toBe('R102');
});

it('unitBusy xato bersa: logga yoziladi, unit-busy bo\'sh ro\'yxat + warning, biriktirish ogohlantirishsiz bajariladi', function () {
    $s = ubSetup();
    app()->bind(TimetableController::class, fn () => new class extends TimetableController {
        protected function unitBusy(TimetableBoard $board, $unitCards): array
        {
            throw new RuntimeException('sinov xatosi');
        }
    });
    $logged = [];
    Log::listen(function ($e) use (&$logged) {
        if ($e->level === 'error') {
            $logged[] = [$e->message, $e->context];
        }
    });

    $res = ($s['as'])()->getJson(route('admin.timetable.unit-busy', ['board' => $s['board']->id] + $s['params']))->assertOk()->json();
    expect($res)->toBe(['teachers' => [], 'rooms' => [], 'warning' => "Bandlik tekshiruvi vaqtincha ishlamadi — ro'yxat tekshirilmagan."]);
    // Band xona ham — ogohlantirishsiz biriktiriladi; o'qituvchi ham
    ($s['as'])()->postJson(route('admin.timetable.assign-room', $s['board']), $s['params'] + ['auditorium_code' => 'R102'])->assertOk();
    expect($s['unit']->fresh()->auditorium_code)->toBe('R102');
    ($s['as'])()->postJson(route('admin.timetable.assign-teacher', $s['board']), $s['params'] + ['teacher_id' => $s['b']->id])->assertOk();
    expect((int) $s['unit']->fresh()->teacher_id)->toBe($s['b']->id);

    expect($logged)->toHaveCount(3)
        ->and($logged[0][0])->toBe('unitBusy failed')
        ->and($logged[0][1])->toMatchArray(['board' => $s['board']->id, 'role' => 'kafedra_mudiri', 'cards' => 1, 'message' => 'sinov xatosi',
            'unit' => '1-son davolash ishi fakulteti · Davolash ishi · 3-kurs · Patologik anatomiya · practice · d1/d24-03a'])
        ->and($logged[0][1]['at'])->toContain('TimetableUnitBusyTest.php:');
});

/**
 * Katta sintetik doska: 4 ta dastur (har xil boshlanish sanasi, birida ta'til), 3200 haftalik karta (80 o'qituvchi, 60 xona),
 * 400 haftalik istisno (ko'chirish, bekor qilish, faqat xona), 5-kurs sikli (2 fan, 48 guruhcha, oqim ma'ruzalari).
 */
function ubBigBoard(): array
{
    mt_srand(4242);
    $board = TimetableBoard::create(['name' => 'Katta', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 15, 'settings' => ['semester_start' => '2026-09-07']]);
    $programs = [['F1', 'Davolash ishi', 1, '2026-09-07', null], ['F1', 'Davolash ishi', 2, '2026-09-14', null],
        ['F2', 'Pediatriya', 1, '2026-09-07', [['from' => '2026-11-02', 'to' => '2026-11-08']]], ['F2', 'Pediatriya', 2, null, null]];
    foreach ($programs as [$fac, $spec, $course, $start, $breaks]) {
        TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => $spec, 'course' => $course,
            'days' => 6, 'pairs_per_day' => 12, 'weeks' => 15, 'start_date' => $start, 'weekly_breaks' => $breaks]);
    }
    foreach (range(1, 60) as $r) {
        Auditorium::create(['code' => 'B' . $r, 'name' => (string) (100 + $r), 'volume' => $r % 7 === 0 ? 0 : 10 + $r % 25, 'active' => true]);
    }
    Auditorium::create(['code' => 'KM', 'name' => 'Kafedra mudiri xonasi', 'volume' => 10, 'active' => true]);
    $now = now();
    $rows = [];
    for ($i = 0; $i < 3200; $i++) {
        [$fac, $spec, $course] = $programs[$i % 4];
        $rows[] = ['board_id' => $board->id, 'faculty_name' => $fac, 'specialty_name' => $spec, 'course' => $course,
            'oqim_label' => (1 + $i % 10) . '-oqim', 'lang' => 'uz', 'students' => $i % 13 === 0 ? 0 : 8 + $i % 9,
            'training_type' => $i % 5 === 0 ? 'lecture' : 'practice', 'len_half' => $i % 11 === 0 ? 4 : 2, 'start_half' => 0, 'weeks' => 15,
            'subject_name' => 'Fan ' . ($i % 40), 'group_name' => 'g' . intdiv($i, 4) . ($i % 9 === 0 ? ' + g' . (intdiv($i, 4) + 1) : ''),
            'kafedra_name' => 'K', 'teacher_id' => 1000 + mt_rand(1, 80), 'teacher_name' => 'T',
            'auditorium_code' => mt_rand(0, 9) ? 'B' . mt_rand(1, 60) : ($i % 2 ? 'KM' : null), 'auditorium_name' => null,
            'day' => mt_rand(0, 12) ? mt_rand(1, 6) : null, 'pair' => 1 + 2 * mt_rand(0, 5), 'created_at' => $now, 'updated_at' => $now];
    }
    foreach (array_chunk($rows, 500) as $chunk) {
        DB::table('timetable_cards')->insert($chunk);
    }
    $ids = TimetableCard::where('board_id', $board->id)->pluck('id')->all();
    $ov = [];
    foreach (array_rand(array_flip($ids), 400) as $n => $id) {
        $kind = $n % 4;
        $ov[$id . '|' . ($w = mt_rand(1, 15))] = ['card_id' => $id, 'week' => $w, 'cancelled' => $kind === 0,
            'day' => in_array($kind, [1, 2], true) ? mt_rand(1, 6) : null, 'pair' => in_array($kind, [1, 2], true) ? 1 + 2 * mt_rand(0, 5) : null,
            'auditorium_code' => $kind >= 2 ? 'B' . mt_rand(1, 60) : null, 'auditorium_name' => null, 'created_at' => $now, 'updated_at' => $now];
    }
    DB::table('timetable_card_overrides')->insert(array_values($ov));
    // 5-kurs sikli: 2 fan × 48 guruhcha (8 oqim), oqim ma'ruzalari; bloklar surilgan.
    foreach (['Sikl A', 'Sikl B'] as $subj) {
        TimetableSubjectSetting::create(['board_id' => $board->id, 'specialty_name' => 'Davolash ishi', 'course' => 5,
            'subject_name' => $subj, 'mode' => 'cycle', 'cycle_days' => 5]);
    }
    for ($g = 0; $g < 48; $g++) {
        $oqim = (1 + intdiv($g, 6)) . '-oqim';
        foreach (['Sikl A', 'Sikl B'] as $si => $subj) {
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'Davolash ishi', 'course' => 5,
                'oqim_label' => $oqim, 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice', 'len_half' => 2, 'weeks' => 15,
                'subject_name' => $subj, 'group_name' => 'c' . $g, 'kafedra_name' => 'K', 'teacher_id' => 1000 + mt_rand(1, 80), 'teacher_name' => 'S',
                'auditorium_code' => 'B' . mt_rand(1, 60)]);
            TimetableCyclePlacement::create(['board_id' => $board->id, 'specialty_name' => 'Davolash ishi', 'course' => 5,
                'group_name' => 'c' . $g, 'subject_name' => $subj, 'start_index' => ($g % 6) * 2 + $si * 14, 'training_type' => 'practice']);
        }
    }
    foreach (range(1, 8) as $o) {
        foreach (['Sikl A', 'Sikl B'] as $subj) {
            TimetableCard::create(['board_id' => $board->id, 'faculty_name' => 'F1', 'specialty_name' => 'Davolash ishi', 'course' => 5,
                'oqim_label' => $o . '-oqim', 'lang' => 'uz', 'students' => 60, 'training_type' => 'lecture', 'len_half' => 2, 'weeks' => 15,
                'subject_name' => $subj, 'group_name' => null, 'kafedra_name' => 'K', 'teacher_id' => 1000 + mt_rand(1, 80), 'teacher_name' => 'L',
                'auditorium_code' => 'B' . mt_rand(1, 60)]);
        }
    }

    return ['board' => $board, 'ids' => $ids];
}

it('katta doska (3000+ karta): unit-busy < 1 s va < 64 MB xotira, natija eski (butun doska) hisob bilan bir xil', function () {
    $s = ubSetup();
    $big = ubBigBoard();
    $board = $big['board'];
    $ctl = app(TimetableController::class);
    $busy = new ReflectionMethod($ctl, 'unitBusy');
    $sample = [];
    // Sikl guruhchasi (5 kun), oqim ma'ruzasi, haftalik kartalar (istisnolilari ham)
    $sample[] = TimetableCard::where('board_id', $board->id)->where('group_name', 'c7')->where('subject_name', 'Sikl A')->get();
    $sample[] = TimetableCard::where('board_id', $board->id)->where('training_type', 'lecture')->where('subject_name', 'Sikl B')->where('oqim_label', '2-oqim')->get();
    foreach (array_filter($big['ids'], fn ($i) => $i % 400 === 0, ARRAY_FILTER_USE_KEY) as $id) {
        $sample[] = TimetableCard::whereKey($id)->get();
    }
    foreach (DB::table('timetable_card_overrides')->limit(6)->pluck('card_id') as $id) {
        $sample[] = TimetableCard::whereKey($id)->get();
    }
    foreach ($sample as $cards) {
        request()->attributes->remove('timetable.cycle_units.' . $board->id);
        expect($busy->invoke($ctl, $board, $cards))->toBe(legacyUnitBusy($board, $cards), 'kartalar: ' . $cards->pluck('id')->implode(','));
    }
    expect((new ReflectionMethod($ctl, 'weeklyConflicts'))->invoke($ctl, $board))->toBe(legacyWeeklyConflicts($board));

    // HTTP: sikl guruhchasi va haftalik karta uchun unit-busy — vaqt va eng yuqori xotira (so'rov davomida).
    $head = $s['head'];
    $weeklyCard = TimetableCard::where('board_id', $board->id)->where('training_type', 'practice')->whereNotNull('day')
        ->where('group_name', 'not like', '%+%')->orderBy('id')->first();
    foreach ([['Sikl A', 'c7', 'F1', 'Davolash ishi', 5], [$weeklyCard->subject_name, $weeklyCard->group_name, $weeklyCard->faculty_name,
        $weeklyCard->specialty_name, $weeklyCard->course]] as [$subj, $grp, $fac, $spec, $course]) {
        $params = ['faculty_name' => $fac, 'specialty_name' => $spec, 'course' => $course, 'subject_name' => $subj, 'training_type' => 'practice', 'group_name' => $grp];
        gc_collect_cycles();
        memory_reset_peak_usage();
        $base = memory_get_usage();
        $t0 = microtime(true);
        $res = test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
            ->getJson(route('admin.timetable.unit-busy', ['board' => $board->id] + $params))->assertOk()->json();
        $elapsed = microtime(true) - $t0;
        $peakMb = (memory_get_peak_usage() - $base) / 1048576;
        fwrite(STDERR, sprintf("unit-busy %s: %.3f s, %.1f MB\n", $grp, $elapsed, $peakMb));
        expect($res)->not->toHaveKey('warning')->and($res['teachers'])->not->toBe([])
            ->and($elapsed)->toBeLessThan(1.0)->and($peakMb)->toBeLessThan(64.0);
    }
    // Butun doska to'qnashuvlari (sahifa yuklanganda, HEMIS, hisobotlar) — haftama-hafta, yengil qatorlar.
    gc_collect_cycles();
    memory_reset_peak_usage();
    $base = memory_get_usage();
    $t0 = microtime(true);
    $res = test()->withoutVite()->actingAs($head, 'teacher')->withSession(['active_role' => 'kafedra_mudiri'])
        ->getJson(route('admin.timetable.boards.conflicts', $board))->assertOk()->json();
    $elapsed = microtime(true) - $t0;
    $peakMb = (memory_get_peak_usage() - $base) / 1048576;
    fwrite(STDERR, sprintf("conflicts: %.3f s, %.1f MB, %d karta\n", $elapsed, $peakMb, count($res['weekly'])));
    expect(count($res['weekly']))->toBeGreaterThan(50)->and($peakMb)->toBeLessThan(64.0);
});

it('katta doska: "Bugungi darslar (HEMIS)" bitta fakultet uchun < 10 s va < 128 MB (HEMIS so\'rovlari — faqat bugun va qamrov guruhlari)', function () {
    $big = ubBigBoard();
    $board = $big['board'];
    Role::findOrCreate('registrator_ofisi', 'web');
    $reg = ubTeacher('REG', 'registrator_ofisi');
    $this->travelTo(\Illuminate\Support\Carbon::parse('2026-09-21 10:00'));   // dushanba
    $today = '2026-09-21';
    $cols = collect(DB::select('SHOW COLUMNS FROM `groups`'))->filter(fn ($c) => $c->Null === 'NO' && $c->Default === null && $c->Field !== 'id');
    $names = array_merge(array_map(fn ($k) => 'g' . $k, range(0, 800)), array_map(fn ($k) => 'c' . $k, range(0, 47)));
    $rows = [];
    foreach ($names as $n => $name) {
        $r = ['group_hemis_id' => 10000 + $n, 'name' => $name, 'department_name' => 'F1', 'curriculum_hemis_id' => 1];
        foreach ($cols as $c) {
            $r[$c->Field] ??= str_contains(strtolower($c->Type), 'int') ? 0 : 'x';
        }
        $rows[] = $r;
    }
    DB::table('groups')->insert($rows);
    // HEMIS: har guruhda bugun 3 ta dars (biri vaqti, biri xonasi farqli), davomat — har uchinchi guruhda.
    $ts = \Illuminate\Support\Carbon::parse($today, 'UTC')->timestamp;
    $n = fn ($v) => ['id' => $v, 'code' => (string) $v, 'name' => (string) $v, 'structureType' => ['code' => '1', 'name' => 'x']];
    $item = fn (int $id, int $g, string $subject, string $st, string $en, string $room) => ['id' => $id, 'subject' => ['id' => crc32($subject) % 100000, 'name' => $subject, 'code' => 'S'],
        'semester' => ['code' => '11', 'name' => '1'], 'educationYear' => ['code' => '2026', 'name' => '2026-2027', 'current' => true],
        'group' => ['id' => $g, 'name' => 'g', 'educationLang' => ['code' => '11', 'name' => 'uz']], 'faculty' => $n(1), 'department' => $n(2),
        'auditorium' => ['code' => $room, 'name' => $room, 'auditoriumType' => ['code' => '1', 'name' => 'x'], 'building' => ['id' => 1, 'name' => 'B']],
        'trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'lessonPair' => ['code' => '1', 'name' => '1', 'start_time' => $st, 'end_time' => $en],
        'employee' => ['id' => 1, 'name' => 'E'], 'lesson_date' => $ts, '_week' => 1];
    $page = fn (array $items) => \Illuminate\Support\Facades\Http::response(['success' => true, 'data' => ['items' => $items, 'pagination' => ['pageCount' => 1]]]);
    \Illuminate\Support\Facades\Http::preventStrayRequests();
    \Illuminate\Support\Facades\Http::fake([
        '*schedule-list*' => function ($req) use ($item, $page) {
            $g = (int) $req->data()['_group'];
            return $page([$item($g * 10 + 1, $g, 'Fan ' . ($g % 40), '08:30', '09:50', 'B1'), $item($g * 10 + 2, $g, 'Fan ' . (($g + 3) % 40), '12:00', '13:20', 'B' . ($g % 60)),
                $item($g * 10 + 3, $g, 'Sikl A', '10:00', '11:20', 'B2')]);
        },
        '*attendance-control-list*' => fn ($req) => $page((int) $req->data()['_group'] % 3 ? [] : [['_subject_schedule' => (int) $req->data()['_group'] * 10 + 1,
            'group' => ['id' => (int) $req->data()['_group']], 'lesson_date' => $ts]]),
        '*student-grade-list*' => fn () => $page([]),
    ]);

    gc_collect_cycles();
    memory_reset_peak_usage();
    $base = memory_get_usage();
    $t0 = microtime(true);
    $res = test()->withoutVite()->actingAs($reg, 'teacher')->withSession(['active_role' => 'registrator_ofisi'])
        ->postJson(route('admin.timetable.hemis-today', $board), ['faculty_names' => ['F1']])->assertOk()->json();
    $elapsed = microtime(true) - $t0;
    $peakMb = (memory_get_peak_usage() - $base) / 1048576;
    $sent = count(\Illuminate\Support\Facades\Http::recorded());
    fwrite(STDERR, sprintf("hemis-today F1: %.2f s, %.1f MB, %d ta dars, %d ta HEMIS so'rovi, %s\n", $elapsed, $peakMb, $res['counts']['lessons'], $sent, json_encode($res['counts'])));
    expect($res['today'])->toBe($today)->and($res['counts']['updates'])->toBeGreaterThan(0)->and($elapsed)->toBeLessThan(10.0)->and($peakMb)->toBeLessThan(128.0);
    \Illuminate\Support\Facades\Http::assertNotSent(fn ($req) => (int) $req->data()['lesson_date_from'] !== \Illuminate\Support\Carbon::parse($today)->startOfDay()->timestamp
        || (int) $req->data()['_group'] < 10000);
});
