<?php

use App\Models\AbsenceExcuse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sababli ariza kunlarida darsda bo'lib baho olgan talaba arizani yubora olmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('student_grades', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
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

    expect(AbsenceExcuse::gradeConflictRows($conflicts))->toBe([
        ['date' => '21.10.2026', 'subject_name' => 'Anatomiya', 'pair' => '1-juftlik', 'grade' => '4.5'],
    ])->and(AbsenceExcuse::gradeConflictMessage($conflicts))->toContain('21.10.2026 — Anatomiya (4.5)');
});

it('has no conflicts when every lesson in the range is NB', function () {
    conflictGrade(['reason' => 'absent', 'grade' => null]);

    expect(AbsenceExcuse::gradeConflicts('5531', '2026-10-19', '2026-10-24'))->toBeEmpty();
});
