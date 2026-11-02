<?php

use App\Models\AbsenceExcuse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sababli ariza kunlarida darsda bo'lib baho olingan kunlar — adminga ogohlantirish,
 * bu kunlarga qayta topshirish ajratilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('student_grades', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
        $t->string('subject_id')->nullable();
        $t->string('subject_name');
        $t->dateTime('lesson_date');
        $t->string('lesson_pair_name')->nullable();
        $t->integer('training_type_code')->nullable();
        $t->double('grade')->nullable();
        $t->string('reason')->nullable();
        $t->softDeletes();
    });
});

function conflictGrade(array $attrs): void
{
    DB::table('student_grades')->insert(array_merge([
        'student_hemis_id' => '5531', 'subject_name' => 'Anatomiya', 'lesson_date' => '2026-10-20 08:30:00',
        'lesson_pair_name' => '1-juftlik', 'training_type_code' => 13, 'grade' => 4, 'reason' => null,
    ], $attrs));
}

it('finds the days the student attended and was graded', function () {
    conflictGrade(['lesson_date' => '2026-10-21 10:10:00', 'grade' => 4.5]);
    conflictGrade(['reason' => 'absent', 'grade' => null]);               // NB
    conflictGrade(['reason' => 'absent', 'grade' => 3]);                  // NB ustidan otrabotka
    conflictGrade(['training_type_code' => 99]);                         // mustaqil ta'lim
    conflictGrade(['deleted_at' => now()]);                               // o'chirilgan
    conflictGrade(['lesson_date' => '2026-10-25 08:30:00']);             // davrdan tashqarida
    conflictGrade(['student_hemis_id' => '9999']);                        // boshqa talaba

    $conflicts = AbsenceExcuse::gradeConflicts('5531', '2026-10-19', '2026-10-24');

    expect($conflicts->map(fn ($c) => $c->subject_name . ' ' . substr($c->lesson_date, 0, 10) . ' ' . $c->grade)->all())
        ->toBe(['Anatomiya 2026-10-21 4.5']);
});

it('counts graded days per excuse for the list page in one go', function () {
    conflictGrade(['lesson_date' => '2026-10-20 08:30:00']);
    conflictGrade(['lesson_date' => '2026-10-21 08:30:00']);
    conflictGrade(['lesson_date' => '2026-11-03 08:30:00']);                       // boshqa arizaning davri
    conflictGrade(['student_hemis_id' => '7777', 'reason' => 'absent', 'grade' => null]);
    $excuse = fn ($id, $hemis, $from, $to) => (new AbsenceExcuse)->forceFill([
        'id' => $id, 'student_hemis_id' => $hemis, 'start_date' => $from, 'end_date' => $to,
    ]);

    expect(AbsenceExcuse::gradeConflictCounts([
        $excuse(1, '5531', '2026-10-19', '2026-10-24'),
        $excuse(2, '5531', '2026-10-26', '2026-10-31'), // bahosiz
        $excuse(3, '7777', '2026-10-19', '2026-10-24'), // faqat NB
        $excuse(4, '5531', '2026-11-02', '2026-11-04'),
    ]))->toBe([1 => 2, 4 => 1]);
});

it('has no conflicts when every lesson in the range is NB', function () {
    conflictGrade(['reason' => 'absent', 'grade' => null]);

    expect(AbsenceExcuse::gradeConflicts('5531', '2026-10-19', '2026-10-24'))->toBeEmpty();
});
