<?php

use App\Exports\AbsenceExcuseGradesExport;
use App\Models\AbsenceExcuse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sababli arizalar Excel'i: har ariza × fan bitta qator; sabab davridagi oddiy baholar — "HEMIS'da",
 * NB ustiga qo'yilganlar (otrabotka yoki ochilgan katak) — "Markda", bahosiz NB soni alohida.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->unsignedBigInteger('group_id')->nullable(), $t->string('department_name')->nullable()]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_id'), $t->unsignedBigInteger('subject_id'), $t->string('subject_name'),
        $t->string('training_type_code'), $t->string('lesson_pair_code'), $t->string('lesson_pair_name')->nullable(), $t->dateTime('lesson_date'), $t->softDeletes()]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('subject_name'),
        $t->dateTime('lesson_date'), $t->string('lesson_pair_code'), $t->string('lesson_pair_name')->nullable(), $t->integer('training_type_code'), $t->float('grade')->nullable(),
        $t->float('retake_grade')->nullable(), $t->string('reason')->nullable(), $t->softDeletes()]);
    DB::table('students')->insert(['id' => 1, 'hemis_id' => '6398', 'group_id' => 77]);

    // Ichki kasalliklar: 29, 30, 31.10 — 2 juftlikdan; Onkologiya: 30.10 — 1 juftlik; 01.11 — davrdan tashqari
    foreach (['2026-10-29', '2026-10-30', '2026-10-31', '2026-11-01'] as $d) {
        foreach (['11', '12'] as $pair) {
            DB::table('schedules')->insert(['group_id' => 77, 'subject_id' => 5, 'subject_name' => 'Ichki kasalliklar', 'training_type_code' => '13',
                'lesson_pair_code' => $pair, 'lesson_pair_name' => (string) ((int) $pair - 10), 'lesson_date' => "$d 08:30:00"]);
        }
    }
    DB::table('schedules')->insert(['group_id' => 77, 'subject_id' => 6, 'subject_name' => 'Onkologiya', 'training_type_code' => '13', 'lesson_pair_code' => '11', 'lesson_date' => '2026-10-30 08:30:00']);
    DB::table('schedules')->insert(['group_id' => 77, 'subject_id' => 6, 'subject_name' => 'Onkologiya', 'training_type_code' => '11', 'lesson_pair_code' => '12', 'lesson_date' => '2026-10-30 10:00:00']);

    $g = fn (array $row) => DB::table('student_grades')->insert($row + ['student_hemis_id' => '6398', 'subject_id' => '5', 'subject_name' => 'Ichki kasalliklar',
        'lesson_pair_code' => '11', 'training_type_code' => 13]);
    $g(['lesson_date' => '2026-10-29 08:30:00', 'grade' => 85, 'lesson_pair_name' => '1']);                                                         // HEMIS
    $g(['lesson_date' => '2026-10-29 08:30:00', 'lesson_pair_code' => '12', 'lesson_pair_name' => 'Manual', 'grade' => 100, 'reason' => 'absent']);  // nom yo'q — jadvaldan     // NB ustiga (ochilgan katak)
    $g(['lesson_date' => '2026-10-30 08:30:00', 'grade' => null, 'retake_grade' => 90, 'reason' => 'absent']);          // NB ustiga (otrabotka)
    $g(['lesson_date' => '2026-10-31 08:30:00', 'grade' => null, 'reason' => 'absent']);                                // bahosiz NB
    $g(['lesson_date' => '2026-11-01 08:30:00', 'grade' => 70]);                                                         // davrdan tashqari
    $g(['lesson_date' => '2026-10-31 09:00:00', 'training_type_code' => 101, 'grade' => 88]);                           // OSKI
});

it('builds one row per excuse and subject with HEMIS and Mark grades', function () {
    $excuse = new AbsenceExcuse(['student_id' => 1, 'student_hemis_id' => '6398', 'student_full_name' => 'Xayrullayeva Asila', 'group_name' => 'd2/d23-08c',
        'reason' => 'musobaqa_tadbir', 'start_date' => '2026-10-29', 'end_date' => '2026-10-31', 'status' => 'approved']);
    $excuse->id = 1561;

    $rows = (new AbsenceExcuseGradesExport(collect([$excuse])))->array();

    expect($rows)->toHaveCount(2);
    [$ichki, $onko] = $rows;
    expect($ichki[9])->toBe('Ichki kasalliklar')
        ->and($ichki[10])->toBe(6)                                            // 3 kun × 2 juftlik
        ->and($ichki[11])->toBe('29.10, 1-juftlik: 85; 31.10 OSKI: 88')
        ->and($ichki[12])->toBe('29.10, 2-juftlik: 100; 30.10, 1-juftlik: 90')
        ->and($ichki[13])->toBe(1)
        ->and($ichki[5])->toBe(1561)
        ->and($ichki[7])->toBe('29.10.2026 – 31.10.2026')
        ->and($onko[9])->toBe('Onkologiya')
        ->and($onko[10])->toBe(1)                                             // ma'ruza hisoblanmaydi
        ->and($onko[11])->toBe('')
        ->and($onko[12])->toBe('');

    // Haqiqiy .xlsx (uslublar, chegaralar bilan) xatosiz yaratiladi
    $xlsx = \Maatwebsite\Excel\Facades\Excel::raw(new AbsenceExcuseGradesExport(collect([$excuse])), \Maatwebsite\Excel\Excel::XLSX);
    expect(strlen($xlsx))->toBeGreaterThan(1000);
});
