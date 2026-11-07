<?php

use App\Models\Deadline;

/**
 * Qayta topshirish muddati: dars kunidan keyingi N kun, yakshanbalar sanalmaydi.
 */

uses(Tests\TestCase::class);

it('skips Sundays when counting retake days', function () {
    // Dushanba 02.11.2026 + 6 kun: 03–07 (5 kun), 08 yakshanba, 09 — 6-kun
    expect(Deadline::retakeDeadline('2026-11-02', 6)->toDateTimeString())->toBe('2026-11-09 23:59:59')
        // Shanba 07.11 + 1 kun: yakshanba o'tib, dushanba
        ->and(Deadline::retakeDeadline('2026-11-07', 1)->toDateString())->toBe('2026-11-09')
        // Yakshanba bo'lmagan oraliq — o'zgarmaydi
        ->and(Deadline::retakeDeadline('2026-11-03', 4)->toDateString())->toBe('2026-11-07')
        // 14 kun — ikki yakshanba
        ->and(Deadline::retakeDeadline('2026-11-02', 14)->toDateString())->toBe('2026-11-18');
});

it('uses the same rule for the stored-deadline fallback', function () {
    expect(\App\Services\RetakeBookingService::rowDeadline(null, '2026-11-02', 6)->toDateString())->toBe('2026-11-09')
        ->and(\App\Services\RetakeBookingService::rowDeadline('2026-11-08 23:59:59', '2026-11-02', 6)->toDateString())->toBe('2026-11-08');
});

describe('grades:recalc-retake-deadlines', function () {
    beforeEach(function () {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        \Illuminate\Support\Facades\DB::purge('sqlite');
        $schema = \Illuminate\Support\Facades\Schema::class;
        $schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
        $schema::create('students', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('level_code')]);
        $schema::create('student_grades', fn ($t) => [$t->id(), $t->unsignedBigInteger('student_hemis_id'), $t->dateTime('lesson_date'),
            $t->dateTime('deadline')->nullable(), $t->string('reason')->nullable(), $t->softDeletes()]);
        \Illuminate\Support\Facades\DB::table('deadlines')->insert(['level_code' => '12', 'deadline_days' => 6]);
        \Illuminate\Support\Facades\DB::table('students')->insert(['hemis_id' => 1, 'level_code' => '12']);
        \Carbon\Carbon::setTestNow('2026-11-09 10:00:00');
    });

    it('moves old-formula deadlines past the Sunday and leaves the rest', function () {
        $db = \Illuminate\Support\Facades\DB::table('student_grades');
        $old = $db->insertGetId(['student_hemis_id' => 1, 'lesson_date' => '2026-11-02 08:30:00', 'deadline' => '2026-11-08 23:59:59', 'reason' => 'absent']);
        $excuse = $db->insertGetId(['student_hemis_id' => 1, 'lesson_date' => '2026-11-02 08:30:00', 'deadline' => '2026-11-14 23:59:59', 'reason' => 'absent']);
        $past = $db->insertGetId(['student_hemis_id' => 1, 'lesson_date' => '2026-10-20 08:30:00', 'deadline' => '2026-10-26 23:59:59', 'reason' => 'low_grade']);

        $this->artisan('grades:recalc-retake-deadlines')->assertSuccessful();
        expect(\Illuminate\Support\Facades\DB::table('student_grades')->find($old)->deadline)->toBe('2026-11-08 23:59:59');   // hisobot — tegilmaydi

        $this->artisan('grades:recalc-retake-deadlines', ['--force' => true])->assertSuccessful();
        $get = fn ($id) => \Illuminate\Support\Facades\DB::table('student_grades')->find($id)->deadline;
        expect($get($old))->toBe('2026-11-09 23:59:59')        // o'tib ketgan edi — yana ochiladi
            ->and($get($excuse))->toBe('2026-11-14 23:59:59')  // sababli oraliq — tegilmaydi
            ->and($get($past))->toBe('2026-10-26 23:59:59');   // yangi muddat ham o'tgan
    });
});
