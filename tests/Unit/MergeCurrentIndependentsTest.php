<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Joriy semestrda bitta fandagi bir nechta MT topshirig'ini birlashtirish: fayl, baho va
 * baho tarixi bitta topshiriqqa ko'chadi; talabaning ikkinchi fayli bo'lsa — tegilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('students', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('semester_code'), $t->string('student_status_code')]);
    Schema::create('independents', fn ($t) => [$t->id(), $t->unsignedBigInteger('user_id')->default(0), $t->string('group_hemis_id'),
        $t->string('group_name')->nullable(), $t->string('subject_hemis_id'), $t->string('subject_name')->nullable(),
        $t->string('semester_code'), $t->string('teacher_name')->nullable()]);
    Schema::create('independent_submissions', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->unsignedBigInteger('student_id')]);
    Schema::create('independent_grade_history', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->unsignedBigInteger('student_id')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id')->nullable(), $t->unsignedBigInteger('student_id')]);

    DB::table('students')->insert([
        ['group_id' => '700', 'semester_code' => '11', 'student_status_code' => '11'],
        ['group_id' => '800', 'semester_code' => '13', 'student_status_code' => '11'],
    ]);
});

function mergeInd(string $group, string $subject, string $sem, array $extra = []): int
{
    return DB::table('independents')->insertGetId(array_merge([
        'group_hemis_id' => $group, 'group_name' => 'G' . $group, 'subject_hemis_id' => $subject,
        'subject_name' => 'Fan ' . $subject, 'semester_code' => $sem, 'teacher_name' => 'Ustoz',
    ], $extra));
}

it('moves files, grades and history onto one assignment in the current semester', function () {
    $a = mergeInd('700', 'S', '11');
    $b = mergeInd('700', 'S', '11');
    $c = mergeInd('700', 'S', '11');
    DB::table('independent_submissions')->insert([
        ['independent_id' => $b, 'student_id' => 1], ['independent_id' => $b, 'student_id' => 2],
        ['independent_id' => $c, 'student_id' => 3],
    ]);
    DB::table('student_grades')->insert([['independent_id' => $a, 'student_id' => 1], ['independent_id' => $c, 'student_id' => 3]]);
    DB::table('independent_grade_history')->insert(['independent_id' => $a, 'student_id' => 1]);

    // O'tgan semestr va qo'lda yaratilganlar — tegilmaydi
    $old1 = mergeInd('700', 'S', '9'); $old2 = mergeInd('700', 'S', '9');
    DB::table('student_grades')->insert([['independent_id' => $old1, 'student_id' => 1], ['independent_id' => $old2, 'student_id' => 1]]);
    $m1 = mergeInd('700', 'M', '11', ['user_id' => 7]); $m2 = mergeInd('700', 'M', '11', ['user_id' => 7]);

    $this->artisan('independents:merge-current')->assertSuccessful();
    expect(DB::table('independents')->count())->toBe(7);   // hisobot

    $this->artisan('independents:merge-current', ['--force' => true])->assertSuccessful();

    // b — fayli eng ko'p, qoladi
    expect(DB::table('independents')->orderBy('id')->pluck('id')->all())->toBe([$b, $old1, $old2, $m1, $m2])
        ->and(DB::table('independent_submissions')->pluck('independent_id')->unique()->values()->all())->toBe([$b])
        ->and(DB::table('student_grades')->where('student_id', 3)->value('independent_id'))->toBe($b)
        ->and(DB::table('student_grades')->whereIn('independent_id', [$old1, $old2])->count())->toBe(2)
        ->and(DB::table('independent_grade_history')->value('independent_id'))->toBe($b);
});

it('keeps an assignment where the student has a second, separate file', function () {
    $a = mergeInd('800', 'S', '13');
    $b = mergeInd('800', 'S', '13');
    DB::table('independent_submissions')->insert([
        ['independent_id' => $a, 'student_id' => 1], ['independent_id' => $a, 'student_id' => 2],
        ['independent_id' => $b, 'student_id' => 1],
    ]);

    $this->artisan('independents:merge-current', ['--force' => true])->assertSuccessful();

    expect(DB::table('independents')->pluck('id')->all())->toBe([$a, $b])
        ->and(DB::table('independent_submissions')->where('independent_id', $b)->count())->toBe(1);
});
