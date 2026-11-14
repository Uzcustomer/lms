<?php

use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * O'tish chegarasi 26.10.2026 dan 70 ga oshirilgan: YN imtihonlari shu sanadan oldin tugagan fanlarda
 * eski 60 qoladi (jurnal, YN ga ruxsat, vedomost), qolganlarida — sozlamadagi 70.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable(),
        $t->boolean('oski_na')->default(false), $t->boolean('test_na')->default(false)]);
    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    MarkingSystemScore::forgetSubject();

    DB::table('exam_schedules')->insert([
        ['group_hemis_id' => '1', 'subject_id' => '10', 'semester_code' => '19', 'oski_date' => '2026-10-20', 'test_date' => '2026-10-24', 'oski_na' => false, 'test_na' => false],  // oldin tugagan
        ['group_hemis_id' => '1', 'subject_id' => '11', 'semester_code' => '19', 'oski_date' => '2026-10-20', 'test_date' => '2026-10-27', 'oski_na' => false, 'test_na' => false],  // test — keyin
        ['group_hemis_id' => '1', 'subject_id' => '12', 'semester_code' => '19', 'oski_date' => '2026-10-21', 'test_date' => '2026-11-02', 'oski_na' => false, 'test_na' => true],   // test yo'q — OSKI oldin
    ]);
});

afterEach(fn () => MarkingSystemScore::forgetSubject());

it('keeps the old 60 pass mark for subjects whose exams all finished before 26.10.2026', function () {
    expect(MarkingSystemScore::lowGradeLimit())->toBe(70)
        ->and(MarkingSystemScore::withSubject('1', '10', '19', fn () => [MarkingSystemScore::lowGradeLimit(), MarkingSystemScore::ynJnMtLimit()]))->toBe([60, 60])
        ->and(MarkingSystemScore::withSubject('1', '11', '19', fn () => [MarkingSystemScore::lowGradeLimit(), MarkingSystemScore::ynJnMtLimit()]))->toBe([70, 70])
        ->and(MarkingSystemScore::withSubject('1', '12', '19', fn () => MarkingSystemScore::lowGradeLimit()))->toBe(60)
        ->and(MarkingSystemScore::withSubject('1', '99', '19', fn () => MarkingSystemScore::lowGradeLimit()))->toBe(70)   // jadvali yo'q
        ->and(MarkingSystemScore::lowGradeLimit())->toBe(70);                                                          // kontekst tiklangan
});

it('sets the subject limit for journal and YN requests from the route or the request data', function () {
    $run = function (array $input) {
        $request = \Illuminate\Http\Request::create('/admin/journal/submit-to-yn', 'POST', $input);

        return (new \App\Http\Middleware\ApplySubjectGradeLimit())->handle($request, fn () => response((string) MarkingSystemScore::lowGradeLimit()))->getContent();
    };

    expect($run(['subject_id' => '10', 'semester_code' => '19', 'group_hemis_id' => '1']))->toBe('60')
        ->and($run(['subject_id' => '11', 'semester_code' => '19', 'group_hemis_id' => '1']))->toBe('70')
        ->and($run([]))->toBe('70');
});
