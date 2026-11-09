<?php

use App\Services\JnMtCalculator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * JN ni ko'tarishdan oldingi baholar (admin_original_grade) bilan hisoblash — Sinov bahosini
 * baholar ko'tarilishidan oldingi holatiga qaytarish uchun.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    \App\Models\MarkingSystemScore::forgetLmsMinimumLimit();
    \Carbon\Carbon::setTestNow('2026-11-09 10:00:00');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('marking_system_scores', fn ($t) => [$t->id(), $t->string('marking_system_code')->nullable(), $t->integer('minimum_limit')->nullable(), $t->timestamps()]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('training_type_code'), $t->string('training_type_name')->nullable(), $t->date('lesson_date')->nullable(), $t->string('lesson_pair_code'), $t->softDeletes()]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('group_id'), $t->unsignedBigInteger('hemis_id')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->unsignedBigInteger('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('training_type_code'), $t->date('lesson_date')->nullable(), $t->string('lesson_pair_code')->nullable(),
        $t->decimal('grade', 5, 2)->nullable(), $t->decimal('retake_grade', 5, 2)->nullable(), $t->string('status')->default('recorded'), $t->string('reason')->nullable(),
        $t->boolean('upgraded_by_admin')->default(false), $t->decimal('admin_original_grade', 5, 2)->nullable(), $t->softDeletes()]);

    DB::table('students')->insert(['group_id' => '77', 'hemis_id' => 1]);
    foreach (['2026-10-20', '2026-10-21'] as $day) {
        DB::table('schedules')->insert(['group_id' => '77', 'subject_id' => '27', 'semester_code' => '19', 'training_type_code' => '13',
            'training_type_name' => 'Amaliy', 'lesson_date' => $day, 'lesson_pair_code' => '1']);
    }
    // 1-kun: 80 → 90 ga ko'tarilgan; 2-kun: NB qayta topshirilgan 70 → 84
    DB::table('student_grades')->insert([
        ['student_hemis_id' => 1, 'subject_id' => '27', 'semester_code' => '19', 'training_type_code' => '13', 'lesson_date' => '2026-10-20', 'lesson_pair_code' => '1',
            'grade' => 90, 'retake_grade' => null, 'status' => 'recorded', 'reason' => null, 'upgraded_by_admin' => true, 'admin_original_grade' => 80],
        ['student_hemis_id' => 1, 'subject_id' => '27', 'semester_code' => '19', 'training_type_code' => '13', 'lesson_date' => '2026-10-21', 'lesson_pair_code' => '1',
            'grade' => null, 'retake_grade' => 84, 'status' => 'recorded', 'reason' => 'absent', 'upgraded_by_admin' => true, 'admin_original_grade' => 70],
    ]);
});

it('computes JN with the original grades before the admin upgrade', function () {
    $calc = new JnMtCalculator();

    expect($calc->computeForGroup('77', 27, '19')['1']['jn'])->toBe(87)          // (90 + 84) / 2
        ->and($calc->computeForGroup('77', 27, '19', true)['1']['jn'])->toBe(75);  // (80 + 70) / 2
});
