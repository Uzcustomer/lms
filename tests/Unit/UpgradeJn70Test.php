<?php

use App\Http\Controllers\Admin\JournalController;
use App\Services\YnAttemptStatusService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "JN 70%": YN imtihonlari 26.10.2026 dan oldin tugagan fanda yopuvchi imtihonga qatnashmagan talabalar —
 * keyingi urinishlar uchun oshirilgan JN alohida jadvalda (asl JN o'zgarmaydi), --revert bilan bekor.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    \App\Services\YnqFamily::flush();
    Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id')->nullable()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('subject_name')->nullable(), $t->string('closing_form')->nullable()]);
    Schema::create('exam_schedules', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->string('student_hemis_id')->nullable(), $t->date('oski_date')->nullable(), $t->date('test_date')->nullable(),
        $t->boolean('oski_na')->default(false), $t->boolean('test_na')->default(false)]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('group_id'), $t->string('full_name'), $t->string('student_status_code')->nullable()]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->integer('training_type_code'), $t->integer('attempt')->nullable(), $t->decimal('grade')->nullable(), $t->string('reason')->nullable(), $t->softDeletes()]);
    (require base_path('database/migrations/2026_11_14_180000_create_jn_upgrades_table.php'))->up();

    DB::table('groups')->insert(['group_hemis_id' => '1', 'name' => 'd1/d22-01a', 'curriculum_hemis_id' => '5']);
    DB::table('curriculum_subjects')->insert([
        ['curricula_hemis_id' => '5', 'subject_id' => '10', 'semester_code' => '19', 'subject_name' => 'Akusherlik', 'closing_form' => 'oski_test'],
        ['curricula_hemis_id' => '5', 'subject_id' => '11', 'semester_code' => '19', 'subject_name' => 'Pediatriya', 'closing_form' => 'oski_test'],
    ]);
    DB::table('exam_schedules')->insert([
        ['group_hemis_id' => '1', 'subject_id' => '10', 'semester_code' => '19', 'oski_date' => '2026-10-15', 'test_date' => '2026-10-20'],   // 26.10 dan oldin
        ['group_hemis_id' => '1', 'subject_id' => '11', 'semester_code' => '19', 'oski_date' => '2026-10-22', 'test_date' => '2026-11-04'],   // keyin — tegilmaydi
        ['group_hemis_id' => '1', 'subject_id' => '12', 'semester_code' => '19', 'oski_date' => null, 'test_date' => null],                 // sanasiz — o'tkaziladi
    ]);
    DB::table('students')->insert([
        ['hemis_id' => 's1', 'group_id' => '1', 'full_name' => 'A TESTGA KELGAN'],
        ['hemis_id' => 's2', 'group_id' => '1', 'full_name' => 'B BAHOSI YOQ'],
        ['hemis_id' => 's3', 'group_id' => '1', 'full_name' => 'C KELMAGAN'],
        ['hemis_id' => 's4', 'group_id' => '1', 'full_name' => 'D JN PAST'],
        ['hemis_id' => 's5', 'group_id' => '1', 'full_name' => 'E TESTDAN YIQILGAN'],
    ]);
    Schema::create('hemis_quiz_results', fn ($t) => [$t->id(), $t->string('quiz_type')->nullable()]);
    Schema::table('student_grades', fn ($t) => $t->unsignedBigInteger('quiz_result_id')->nullable());
    DB::table('student_grades')->insert([
        // s1 — OSKI va testdan o'tgan (OSKI — test markazi natijasi, quiz)
        ['student_hemis_id' => 's1', 'subject_id' => '10', 'semester_code' => '19', 'training_type_code' => 102, 'attempt' => 1, 'grade' => 80, 'reason' => null, 'quiz_result_id' => null],
        ['student_hemis_id' => 's1', 'subject_id' => '10', 'semester_code' => '19', 'training_type_code' => 103, 'attempt' => 1, 'grade' => 75, 'reason' => null, 'quiz_result_id' => 1],
        // s3 — testga kelmagan
        ['student_hemis_id' => 's3', 'subject_id' => '10', 'semester_code' => '19', 'training_type_code' => 102, 'attempt' => 1, 'grade' => null, 'reason' => 'absent', 'quiz_result_id' => null],
        // s5 — OSKI 70, test 55 (60 dan past)
        ['student_hemis_id' => 's5', 'subject_id' => '10', 'semester_code' => '19', 'training_type_code' => 101, 'attempt' => 1, 'grade' => 70, 'reason' => null, 'quiz_result_id' => null],
        ['student_hemis_id' => 's5', 'subject_id' => '10', 'semester_code' => '19', 'training_type_code' => 102, 'attempt' => 1, 'grade' => 55, 'reason' => null, 'quiz_result_id' => null],
    ]);
    DB::table('hemis_quiz_results')->insert(['id' => 1, 'quiz_type' => 'OSKI (uzb)']);
    // Baho hisobi (jurnal) o'rniga: asl va formula bo'yicha oshirilgan JN (V1, V2)
    app()->instance(JournalController::class, new class extends JournalController {
        public function __construct() {}

        public function ynqJnMaps(string $subjectId, string $semesterCode, string $groupHemisId, bool $upgraded): array|string
        {
            return $upgraded ? [[], ['s1' => 90, 's2' => 75, 's3' => 86, 's4' => 64, 's5' => 79]] : [[], ['s1' => 85, 's2' => 65, 's3' => 80, 's4' => 58, 's5' => 68]];
        }
    });
});

it('stores JN 70% for students who missed or failed (<60) OSKI or test of a subject finished before 26.10, and reverts it', function () {
    $this->artisan('grades:jn70')->assertSuccessful();                       // hisobot — yozilmaydi
    expect(DB::table('jn_upgrades')->count())->toBe(0);

    $this->artisan('grades:jn70 --force')->assertSuccessful();
    expect(DB::table('jn_upgrades')->orderBy('student_hemis_id')->get(['student_hemis_id', 'subject_id', 'jn_original', 'jn_upgraded'])
        ->map(fn ($r) => "{$r->student_hemis_id} {$r->subject_id} {$r->jn_original}→{$r->jn_upgraded}")->all())
        ->toBe(['s2 10 65→75', 's3 10 80→86', 's5 10 68→79']);               // o'tgan s1, asl JN 60 dan past s4 va 26.10 dan keyingi fan — yo'q
    expect(\App\Services\JnUpgrade::map('10', '19', ['s1', 's2', 's3', 's5']))->toBe(['s2' => 75, 's3' => 86, 's5' => 79]);

    $this->artisan('grades:jn70 --revert')->assertSuccessful();              // hisobot — o'chirilmaydi
    expect(DB::table('jn_upgrades')->count())->toBe(3);
    $this->artisan('grades:jn70 --revert --force')->assertSuccessful();
    expect(DB::table('jn_upgrades')->count())->toBe(0);
});

it('judges a later attempt computed with JN 70% by its own pass mark (70)', function () {
    $main = ['v' => -1, 'jn' => 65, 'mt' => 80];
    // Sababli bilan V = 66: eski qoida (60) bo'yicha o'tardi, "JN 70%" urinishida chegara 70 — o'tmaydi
    expect(YnAttemptStatusService::determineStage($main, ['v' => 66, 'jn' => 75, 'mt' => 80, 'limit' => 70])['stage'])
        ->toBe(YnAttemptStatusService::STAGE_IN_12A)
        ->and(YnAttemptStatusService::determineStage($main, ['v' => 72, 'jn' => 75, 'mt' => 80, 'limit' => 70])['stage'])
        ->toBe(YnAttemptStatusService::STAGE_QOSHIMCHA_PASSED);
});
