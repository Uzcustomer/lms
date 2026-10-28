<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableGridSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

/** Haftalik fan kartochkalarining haftalari KTR'dan: ma'ruza haftalari va amaliy soati. */
function ktrWeeksSetup(array $hours): array
{
    $now = now();
    DB::table('departments')->insert(['department_hemis_id' => 10, 'name' => '1-son davolash', 'code' => 'F1', 'structure_type_code' => 11,
        'structure_type_name' => 'Fakultet', 'locality_type_code' => 11, 'locality_type_name' => 'M', 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('specialties')->insert(['specialty_hemis_id' => 20, 'code' => '60910200', 'name' => 'Davolash ishi', 'locality_type_code' => 11,
        'locality_type_name' => 'M', 'education_type_code' => 11, 'education_type_name' => 'Bakalavr', 'created_at' => $now, 'updated_at' => $now]);
    DB::table('curricula')->insert(['curricula_hemis_id' => 30, 'name' => 'Reja', 'specialty_hemis_id' => 20, 'department_hemis_id' => 10,
        'education_year_code' => '2025', 'education_year_name' => '2025-2026', 'current' => 1, 'education_type_code' => 11, 'education_type_name' => 'Bakalavr',
        'education_form_code' => 11, 'education_form_name' => 'Kunduzgi', 'marking_system_code' => 11, 'marking_system_name' => '5',
        'marking_system_minimum_limit' => 60, 'marking_system_gpa_limit' => 2.4, 'semester_count' => 12, 'education_period' => 6, 'created_at' => $now, 'updated_at' => $now]);
    DB::table('semesters')->insert(['semester_hemis_id' => 40, 'code' => '13', 'name' => '3-semestr', 'curriculum_hemis_id' => 30,
        'education_year' => '2026', 'level_code' => '12', 'created_at' => $now, 'updated_at' => $now]);
    $csId = DB::table('curriculum_subjects')->insertGetId(['curriculum_subject_hemis_id' => 50, 'curricula_hemis_id' => 30, 'subject_id' => 60,
        'subject_name' => 'Biokimyo', 'subject_code' => 'BIO', 'semester_code' => '13', 'semester_name' => '3-semestr', 'at_semester' => 1,
        'subject_details' => json_encode([['trainingType' => ['code' => '11', 'name' => 'Ma’ruza']], ['trainingType' => ['code' => '13', 'name' => 'Amaliy']],
            ['trainingType' => ['code' => '17', 'name' => "Mustaqil ta'lim"]]]),
        'created_at' => $now, 'updated_at' => $now]);
    DB::table('ktr_plans')->insert(['curriculum_subject_id' => $csId, 'week_count' => count($hours),
        'plan_data' => json_encode(['hours' => $hours]), 'created_at' => $now, 'updated_at' => $now]);

    $board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6, 'pairs_per_day' => 12, 'weeks' => 6]);
    TimetableGridSetting::create(['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'days' => 6, 'pairs_per_day' => 12, 'weeks' => 6]);
    $base = ['board_id' => $board->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi', 'course' => 2,
        'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 15, 'subject_name' => 'Biokimyo'];
    $lec = TimetableCard::create($base + ['training_type' => 'lecture', 'group_names' => ['g-01a', 'g-01b'], 'len_half' => 2, 'weeks' => 3]);
    $p1 = TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => 'g-01a', 'len_half' => 2, 'weeks' => 6]);
    $p2 = TimetableCard::create($base + ['training_type' => 'practice', 'group_name' => 'g-01a', 'len_half' => 2, 'weeks' => 3]);
    // Avtomatik (noto'g'ri) taqsimot: ma'ruza toq haftalarda, qo'shimcha amaliy juftda.
    foreach ([2, 4, 6] as $w) DB::table('timetable_card_overrides')->insert(['card_id' => $lec->id, 'week' => $w, 'cancelled' => true, 'created_at' => $now, 'updated_at' => $now]);
    foreach ([1, 3, 5] as $w) DB::table('timetable_card_overrides')->insert(['card_id' => $p2->id, 'week' => $w, 'cancelled' => true, 'created_at' => $now, 'updated_at' => $now]);

    return compact('board', 'lec', 'p1', 'p2');
}

function activeWeeks(TimetableCard $c, int $total = 6): array
{
    $off = DB::table('timetable_card_overrides')->where('card_id', $c->id)->where('cancelled', true)->pluck('week')->all();

    return array_values(array_diff(range(1, $total), $off));
}

it('ma\'ruza va amaliy haftalari KTR bo\'yicha belgilanadi', function () {
    // KTR: ma'ruza 1, 2, 5-haftalarda (qat'iy toq/juft emas); amaliy ma'ruzali haftada 2, qolganida 4 soat.
    $s = ktrWeeksSetup([
        '1' => ['11' => 2, '13' => 2], '2' => ['11' => 2, '13' => 2], '3' => ['11' => 0, '13' => 4],
        '4' => ['11' => 0, '13' => 4], '5' => ['11' => 2, '13' => 2], '6' => ['11' => 0, '13' => 4],
    ]);

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', ['courses' => [2]]), $s['board'])->getData(true);

    expect($res['cards'])->toBe(3)->and($res['no_ktr'])->toBe([])->and($res['mismatch_count'])->toBe(0);
    expect(activeWeeks($s['lec']))->toBe([1, 2, 5])
        ->and(activeWeeks($s['p1']))->toBe([1, 2, 3, 4, 5, 6])
        ->and(activeWeeks($s['p2']))->toBe([3, 4, 6])
        ->and($s['lec']->fresh()->weeks)->toBe(3);
});

it('sinov rejimi (dry_run) hech narsani o\'zgartirmaydi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 2], '2' => ['11' => 0, '13' => 4], '3' => ['11' => 2, '13' => 2],
        '4' => ['11' => 0, '13' => 4], '5' => ['11' => 0, '13' => 4], '6' => ['11' => 2, '13' => 2]]);

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', ['dry_run' => 1]), $s['board'])->getData(true);

    expect($res['dry_run'])->toBeTrue()->and($res['weeks_on'] + $res['weeks_off'])->toBeGreaterThan(0)
        ->and(activeWeeks($s['lec']))->toBe([1, 3, 5]);
});

it('qamrov boshqa amallar kabi: bo\'sh tanlov yoki boshqa fakultet — hech narsa o\'zgarmaydi', function () {
    $s = ktrWeeksSetup([
        '1' => ['11' => 2, '13' => 2], '2' => ['11' => 2, '13' => 2], '3' => ['11' => 0, '13' => 4],
        '4' => ['11' => 0, '13' => 4], '5' => ['11' => 2, '13' => 2], '6' => ['11' => 0, '13' => 4],
    ]);

    foreach ([['faculty_names' => []], ['faculty_names' => ['2-son davolash'], 'courses' => [2]]] as $scope) {
        $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', $scope), $s['board'])->getData(true);
        expect($res['cards'])->toBe(0);
    }
    expect(activeWeeks($s['lec']))->toBe([1, 3, 5])->and(activeWeeks($s['p2']))->toBe([2, 4, 6]);

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', ['faculty_names' => ['1-son davolash'], 'courses' => [2]]), $s['board'])->getData(true);
    expect($res['cards'])->toBe(3)->and(activeWeeks($s['lec']))->toBe([1, 2, 5]);
});

it('1 va 2 soatlik kartalardan haftaning soati aniq yig\'iladi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 2], '2' => ['11' => 0, '13' => 2], '3' => ['11' => 0, '13' => 3],
        '4' => ['11' => 0, '13' => 3], '5' => ['11' => 2, '13' => 2], '6' => ['11' => 0, '13' => 3]]);
    // Gigiyena kabi: 1 soatlik (15 hafta) va 2 soatlik (10 hafta) kartalar
    $s['p1']->update(['len_half' => 1, 'weeks' => 6]);
    $s['p2']->update(['len_half' => 2, 'weeks' => 4]);

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', []), $s['board'])->getData(true);

    expect($res['mismatch_count'])->toBe(0)
        ->and(activeWeeks($s['p2']->fresh()))->toBe([1, 2, 3, 4, 5, 6])
        ->and(activeWeeks($s['p1']->fresh()))->toBe([3, 4, 6]);
});

it('birlikda 10 tadan ko\'p karta bo\'lsa ham hammasi hisobga olinadi', function () {
    $hours = [];
    foreach (range(1, 6) as $w) {
        $hours[(string) $w] = ['11' => 2, '13' => 11];
    }
    $s = ktrWeeksSetup($hours);
    $s['p1']->update(['len_half' => 1]);
    $s['p2']->update(['len_half' => 1]);
    $extra = [];
    foreach (range(1, 9) as $i) {
        $extra[] = TimetableCard::create($s['p1']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
            'subject_name', 'training_type', 'group_name']) + ['len_half' => 1, 'weeks' => 6]);
    }

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', []), $s['board'])->getData(true);

    expect($res['mismatch_count'])->toBe(0);
    foreach (array_merge([$s['p1'], $s['p2']], $extra) as $card) {
        expect(activeWeeks($card))->toBe([1, 2, 3, 4, 5, 6]);
    }
});

it('2 soatlik asosiy karta ikki 1 soatlik qisqa kartadan ustun', function () {
    $hours = [];
    foreach (range(1, 6) as $w) {
        $hours[(string) $w] = ['11' => 2, '13' => 2];
    }
    $s = ktrWeeksSetup($hours);
    $s['p1']->update(['len_half' => 2, 'weeks' => 6]);
    $s['p2']->update(['len_half' => 1, 'weeks' => 4]);
    $p3 = TimetableCard::create($s['p2']->only(['board_id', 'faculty_name', 'specialty_name', 'course', 'oqim_label', 'lang', 'students',
        'subject_name', 'training_type', 'group_name']) + ['len_half' => 1, 'weeks' => 4]);

    $res = (new TimetableController())->ktrWeeks(Request::create('/', 'POST', []), $s['board'])->getData(true);

    expect($res['mismatch_count'])->toBe(0)
        ->and(activeWeeks($s['p1']))->toBe([1, 2, 3, 4, 5, 6])
        ->and(activeWeeks($s['p2']))->toBe([])
        ->and(activeWeeks($p3))->toBe([]);
});

it('doska ma\'lumotida guruhcha talabalar soni keladi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 2]]);
    DB::table('students')->insert([
        ['hemis_id' => 1, 'full_name' => 'A', 'short_name' => 'A', 'first_name' => 'A', 'second_name' => 'A', 'student_id_number' => '1', 'group_name' => 'g-01a', 'student_status_code' => 11, 'created_at' => now(), 'updated_at' => now()],
        ['hemis_id' => 2, 'full_name' => 'B', 'short_name' => 'B', 'first_name' => 'B', 'second_name' => 'B', 'student_id_number' => '2', 'group_name' => 'g-01a', 'student_status_code' => 11, 'created_at' => now(), 'updated_at' => now()],
    ]);
    $data = (new TimetableController())->data($s['board'])->getData(true);
    expect($data['group_students']['1-son davolash|g-01a'])->toBe(2);
});

it('raqamsiz umumiy xona ("amaliy mashg\'ulot xonasi") bandlik to\'qnashuvi bermaydi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 2]]);
    $common = ['auditorium_code' => 'AMX', 'auditorium_name' => "amaliy mashg'ulot xonasi"];
    $s['p1']->update($common + ['day' => 1, 'pair' => 1]);
    $other = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 15, 'subject_name' => 'Biokimyo',
        'training_type' => 'practice', 'group_name' => 'g-02a', 'len_half' => 2, 'weeks' => 6] + $common);
    $res = (new TimetableController())->placeCard(Request::create('/', 'POST', ['day' => 1, 'pair' => 1]), $other);
    expect($res->getStatusCode())->toBe(200)->and($other->fresh()->day)->toBe(1);

    // Raqamli xona esa band hisoblanadi
    $s['p1']->update(['auditorium_code' => 'R421', 'auditorium_name' => '421']);
    $third = $other->replicate(); $third->group_name = 'g-03a'; $third->day = null; $third->pair = null;
    $third->auditorium_code = 'R421'; $third->auditorium_name = '421'; $third->save();
    $res = (new TimetableController())->placeCard(Request::create('/', 'POST', ['day' => 1, 'pair' => 1]), $third);
    expect($res->getStatusCode())->toBe(422);
});

it('raqamsiz, lekin aniq zal ("Katta majlislar zali") bandligi tekshirilaveradi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 2]]);
    $hall = ['auditorium_code' => 'KMZ', 'auditorium_name' => 'Katta majlislar zali'];
    $s['p1']->update($hall + ['day' => 1, 'pair' => 1]);
    $other = TimetableCard::create(['board_id' => $s['board']->id, 'faculty_name' => '1-son davolash', 'specialty_name' => 'davolash ishi',
        'course' => 2, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 15, 'subject_name' => 'Biokimyo',
        'training_type' => 'practice', 'group_name' => 'g-02a', 'len_half' => 2, 'weeks' => 6] + $hall);

    $res = (new TimetableController())->placeCard(Request::create('/', 'POST', ['day' => 1, 'pair' => 1]), $other);

    expect($res->getStatusCode())->toBe(422)
        ->and($res->getData(true)['error'])->toContain('Auditoriya band')
        ->and($other->fresh()->day)->toBeNull();
});


it('sikl amaliysi ma’ruzadan keyin boshlanadi va KTR soatlaridan tashqarida bandlik bermaydi', function () {
    $s = ktrWeeksSetup(['1' => ['11' => 2, '13' => 4]]);
    DB::table('semesters')->where('curriculum_hemis_id', 30)->update(['level_code' => '15']);
    $s['board']->update(['settings' => ['semester_start' => '2026-09-14']]);
    $s['p1']->update(['course' => 5, 'teacher_id' => 77, 'teacher_name' => 'SIKL']);
    \App\Models\TimetableSubjectSetting::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
        'course' => 5, 'subject_name' => 'Biokimyo', 'mode' => 'cycle', 'cycle_days' => 1]);
    \App\Models\TimetableCyclePlacement::create(['board_id' => $s['board']->id, 'specialty_name' => 'davolash ishi',
        'course' => 5, 'group_name' => '1-son davolash / 1-oqim', 'subject_name' => 'Biokimyo', 'start_index' => 0,
        'training_type' => 'practice', 'lecture_slots' => ['starts' => ['0' => 3]]]);
    $weekly = new TimetableCard(['board_id' => $s['board']->id, 'course' => 2, 'teacher_id' => 77, 'len_half' => 2]);
    $method = new ReflectionMethod(TimetableController::class, 'weeklyCycleWarnings');
    $controller = new TimetableController();
    // Kun 3-slotdan boshlanadi: ma’ruza 3–4, amaliy 5–8.
    expect($method->invoke($controller, $weekly, 1, 1, 3))->toBe([])
        ->and($method->invoke($controller, $weekly, 1, 1, 5))->toHaveCount(1)
        ->and($method->invoke($controller, $weekly, 1, 1, 9))->toBe([]);
});

/** Ishchi reja (ma'ruza 10, amaliy 12 soat) va KTR bilan kartochka yaratish — Biokimyo qatorlari. */
function ktrCardRows(array $hours)
{
    $s = ktrWeeksSetup($hours);
    $mc = DB::table('manual_curricula')->insertGetId(['type' => 'ishchi', 'name' => 'R', 'specialty_name' => 'Davolash ishi', 'plan_year' => '2025',
        'level_code' => '12', 'created_at' => now(), 'updated_at' => now()]);
    DB::table('manual_curriculum_subjects')->insert(['manual_curriculum_id' => $mc, 'subject_name' => 'Biokimyo', 'semester' => 3,
        'lecture' => 10, 'practice' => 12, 'laboratory' => 0, 'seminar' => 0]);
    \App\Models\OqimSnapshot::create(['context_key' => 'k', 'context' => [], 'status' => 'approved', 'approved_at' => now(), 'data' => [[
        'merge_key' => 'x|Davolash ishi', 'title' => 'Davolash ishi', 'department_name' => '1-son davolash',
        'courses' => [['level_code' => 12, 'oqims' => [['label' => '1-oqim', 'lang' => 'uz', 'total' => 15, 'rows' => [['name' => 'g-01a', 'count' => 15]]]]]],
    ]]]);
    $ctl = app(TimetableController::class);
    $m = new ReflectionMethod($ctl, 'assembleRows');
    $m->setAccessible(true);
    $found = [];

    return collect($m->invokeArgs($ctl, [$s['board'], null, null, &$found]))->where('subject_name', 'Biokimyo');
}

it('kartochka yaratish: KTR bo\'lsa amaliy kartalar va ma\'ruza haftalari KTR bo\'yicha (ishchi reja soati emas)', function () {
    // KTR: ma'ruza 1, 2, 5-haftalarda; amaliy 2, 2, 4, 4, 2, 4 soat. Ishchi rejada esa ma'ruza 10, amaliy 12
    // (KTR'siz bo'lsa — 5 hafta ma'ruza, 2 soat × 6 hafta amaliy).
    $rows = ktrCardRows([
        '1' => ['11' => 2, '13' => 2], '2' => ['11' => 2, '13' => 2], '3' => ['11' => 0, '13' => 4],
        '4' => ['11' => 0, '13' => 4], '5' => ['11' => 2, '13' => 2], '6' => ['11' => 0, '13' => 4],
    ]);
    expect($rows->where('training_type', 'lecture')->pluck('weeks')->all())->toBe([3])
        ->and($rows->where('training_type', 'practice')->map(fn ($r) => [$r['len_half'], $r['weeks']])->values()->all())->toBe([[2, 6], [2, 3]]);
});

it('kartochka yaratish: KTR haftasida 2 soatdan ko\'p ma\'ruza bo\'lsa ham ma\'ruza soati kamaymaydi', function () {
    // 1-haftada 4 soat, 3-haftada 2 soat ma'ruza — 2 soatlik ma'ruza kartasi 3 marta (6 soat), 2 marta emas.
    $rows = ktrCardRows([
        '1' => ['11' => 4, '13' => 2], '2' => ['11' => 0, '13' => 2], '3' => ['11' => 2, '13' => 2],
        '4' => ['11' => 0, '13' => 2], '5' => ['11' => 0, '13' => 2], '6' => ['11' => 0, '13' => 2],
    ]);
    expect($rows->where('training_type', 'lecture')->pluck('weeks')->all())->toBe([3]);
});

it('kartochka yaratish: KTR haftasida toq (1) soat ma\'ruza bo\'lsa soat oshib ketmaydi', function () {
    // 4 hafta 1 soatdan — jami 4 soat: 2 soatlik ma'ruza 2 marta (har hafta 2 soatga yaxlitlanib 8 soat emas).
    $rows = ktrCardRows([
        '1' => ['11' => 1, '13' => 2], '2' => ['11' => 1, '13' => 2], '3' => ['11' => 1, '13' => 2],
        '4' => ['11' => 1, '13' => 2], '5' => ['11' => 0, '13' => 2], '6' => ['11' => 0, '13' => 2],
    ]);
    expect($rows->where('training_type', 'lecture')->pluck('weeks')->all())->toBe([2]);
});
