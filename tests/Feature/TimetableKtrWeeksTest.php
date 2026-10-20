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
