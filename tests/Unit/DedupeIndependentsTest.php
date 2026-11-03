<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Mustaqil ta'lim dublikatlarini tozalash: fan bo'yicha bitta avtomatik topshiriq qoladi,
 * talaba fayli/bahosi bor va qo'lda yaratilganlarga tegilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('independents', function ($t) {
        $t->id();
        $t->unsignedBigInteger('user_id')->default(0);
        $t->string('group_hemis_id');
        $t->string('group_name')->nullable();
        $t->string('subject_hemis_id');
        $t->string('subject_name')->nullable();
        $t->string('semester_code');
        $t->string('teacher_name')->nullable();
        $t->string('file_path')->nullable();
    });
    foreach (['independent_submissions', 'independent_grade_history', 'student_grades'] as $table) {
        Schema::create($table, function ($t) {
            $t->id();
            $t->unsignedBigInteger('independent_id')->nullable();
        });
    }
});

function dedupeInd(string $subject, array $extra = []): int
{
    return DB::table('independents')->insertGetId(array_merge([
        'group_hemis_id' => '700', 'group_name' => 'p/p 101', 'subject_hemis_id' => $subject,
        'subject_name' => 'Fan ' . $subject, 'semester_code' => '13', 'teacher_name' => 'Ustoz', 'user_id' => 0,
    ], $extra));
}

it('keeps one automatic assignment per subject and never deletes used ones', function () {
    $blank = dedupeInd('A', ['teacher_name' => '']);   // Fanlar sahifasi yaratgan
    $first = dedupeInd('A');
    dedupeInd('A');
    dedupeInd('A');

    $submitted = dedupeInd('B');
    dedupeInd('B');
    $graded = dedupeInd('B');
    DB::table('independent_submissions')->insert(['independent_id' => $submitted]);
    DB::table('student_grades')->insert(['independent_id' => $graded]);

    $single = dedupeInd('C');
    $manual1 = dedupeInd('A', ['user_id' => 15]);        // o'qituvchi qo'lda — tegilmaydi
    $manual2 = dedupeInd('A', ['user_id' => 15]);
    $otherSem = dedupeInd('A', ['semester_code' => '14']);

    $this->artisan('independents:dedupe')->assertSuccessful();
    expect(DB::table('independents')->count())->toBe(11);   // hisobot — hech narsa o'chmaydi

    $this->artisan('independents:dedupe', ['--force' => true])->assertSuccessful();

    expect(DB::table('independents')->orderBy('id')->pluck('id')->all())
        ->toBe([$first, $submitted, $graded, $single, $manual1, $manual2, $otherSem])
        ->and($blank)->toBeLessThan($first);
});
