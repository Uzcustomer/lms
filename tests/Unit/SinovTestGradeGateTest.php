<?php

use App\Services\JournalGradeService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SinovTestGrade override FAQAT closing_form === 'sinov' fanlarga tegishli.
 *
 * Regressiya: yopilish shakli 'test' bo'lgan fanda fandan qolgan eski
 * SinovTestGrade yozuvi (default_grade = JN o'rtachasi) haqiqiy test bahosini
 * bosib, vedomost/qaydnomada JN qiymati test o'rniga tortilib qolardi
 * (jurnal esa to'g'ri ko'rsatardi). computeOnOskiTest endi jurnal
 * (JournalController::show) bilan bir xil closing_form shartini tekshiradi.
 *
 * Bu test to'liq migration to'plamiga tayanmaydi (repo migration'lari MySQL'ga
 * xos) — faqat computeOnOskiTest o'qiydigan jadvallarni qo'lda quradi.
 */

uses(Tests\TestCase::class);

const GATE_GROUP_HID   = 900001;
const GATE_CURRICULUM  = 700001;
const GATE_SUBJECT_ID  = '55501';
const GATE_SEMESTER    = '10';
const GATE_STUDENT_HID = 800001;

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');

    Schema::create('groups', function ($t) {
        $t->unsignedBigInteger('group_hemis_id');
        $t->unsignedBigInteger('curriculum_hemis_id');
    });
    Schema::create('curriculum_subjects', function ($t) {
        $t->unsignedBigInteger('curricula_hemis_id');
        $t->unsignedBigInteger('subject_id');
        $t->string('semester_code');
        $t->boolean('is_active')->default(true);
        $t->string('closing_form', 20)->nullable();
    });
    Schema::create('schedules', function ($t) {
        $t->unsignedBigInteger('group_id')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('semester_code')->nullable();
        $t->string('training_type_code')->nullable();
        $t->timestamp('lesson_date')->nullable();
        $t->string('education_year_code')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    Schema::create('student_grades', function ($t) {
        $t->unsignedBigInteger('student_hemis_id')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->string('semester_code')->nullable();
        $t->string('training_type_code')->nullable();
        $t->float('grade')->nullable();
        $t->float('retake_grade')->nullable();
        $t->string('status')->nullable();
        $t->string('reason')->nullable();
        $t->unsignedBigInteger('quiz_result_id')->nullable();
        $t->integer('attempt')->nullable();
        $t->boolean('is_qoshimcha')->default(false);
        $t->string('education_year_code')->nullable();
        $t->timestamp('lesson_date')->nullable();
        $t->timestamp('deleted_at')->nullable();
    });
    Schema::create('sinov_test_grades', function ($t) {
        $t->id();
        $t->string('subject_id', 64);
        $t->string('semester_code', 32);
        $t->string('group_hemis_id', 32);
        $t->string('student_hemis_id', 32);
        $t->decimal('default_grade', 5, 2)->nullable();
        $t->decimal('override_grade', 5, 2)->nullable();
        $t->boolean('is_locked')->default(false);
        $t->timestamps();
    });
});

function seedGateSubject(string $closingForm): void
{
    DB::table('groups')->insert([
        'group_hemis_id' => GATE_GROUP_HID,
        'curriculum_hemis_id' => GATE_CURRICULUM,
    ]);
    DB::table('curriculum_subjects')->insert([
        'curricula_hemis_id' => GATE_CURRICULUM,
        'subject_id' => GATE_SUBJECT_ID,
        'semester_code' => GATE_SEMESTER,
        'is_active' => true,
        'closing_form' => $closingForm,
    ]);
    // Fandan qolgan sinov yozuvi: default_grade = JN o'rtachasi (71).
    DB::table('sinov_test_grades')->insert([
        'subject_id' => GATE_SUBJECT_ID,
        'semester_code' => GATE_SEMESTER,
        'group_hemis_id' => (string) GATE_GROUP_HID,
        'student_hemis_id' => (string) GATE_STUDENT_HID,
        'default_grade' => 71,
        'override_grade' => null,
    ]);
}

it('does not pull SinovTestGrade (JN) into test for a test-form subject', function () {
    seedGateSubject('test');

    $out = JournalGradeService::computeOnOskiTest(
        (string) GATE_GROUP_HID,
        GATE_SUBJECT_ID,
        GATE_SEMESTER,
        [GATE_STUDENT_HID]
    );

    // Yopilish shakli 'test' — SinovTestGrade e'tiborga OLINMAYDI; 71 (JN) test
    // o'rniga tortilmaydi.
    expect($out['test'][(string) GATE_STUDENT_HID] ?? null)->toBeNull();
});

it('applies SinovTestGrade override for a sinov-form subject', function () {
    seedGateSubject('sinov');

    $out = JournalGradeService::computeOnOskiTest(
        (string) GATE_GROUP_HID,
        GATE_SUBJECT_ID,
        GATE_SEMESTER,
        [GATE_STUDENT_HID]
    );

    // Yopilish shakli 'sinov' — SinovTestGrade.default_grade test sifatida ishlatiladi.
    expect($out['test'][(string) GATE_STUDENT_HID] ?? null)->toBe(71);
});
