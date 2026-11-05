<?php

use App\Console\Commands\ImportGrades;
use App\Models\MarkingSystemScore;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * HEMIS importi admin oshirgan baholarni (upgraded_by_admin) o'chirmaydi va o'rniga
 * HEMIS bahosini yozmaydi; qolgan baholar odatdagidek almashtiriladi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('curricula', fn ($t) => [$t->id(), $t->string('curricula_hemis_id'), $t->string('marking_system_code')->nullable()]);
    Schema::create('marking_system_scores', fn ($t) => [$t->id(), $t->string('marking_system_code'), $t->integer('minimum_limit')->default(60), $t->timestamps()]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('level_code')->nullable(), $t->string('curriculum_id')->nullable(), $t->timestamps()]);
    Schema::create('student_grades', function ($t) {
        $t->id();
        foreach (['hemis_id', 'student_id', 'student_hemis_id', 'semester_code', 'semester_name', 'education_year_code', 'education_year_name',
            'subject_schedule_id', 'subject_id', 'subject_name', 'subject_code', 'training_type_code', 'training_type_name', 'employee_id',
            'employee_name', 'lesson_pair_code', 'lesson_pair_name', 'lesson_pair_start_time', 'lesson_pair_end_time', 'reason', 'status',
            'retake_by', 'retake_file_path', 'graded_by_user_id'] as $c) {
            $t->string($c)->nullable();
        }
        $t->double('grade')->nullable();
        $t->double('retake_grade')->nullable();
        $t->dateTime('lesson_date')->nullable();
        $t->dateTime('created_at_api')->nullable();
        $t->dateTime('deadline')->nullable();
        $t->dateTime('retake_graded_at')->nullable();
        $t->boolean('is_final')->default(false);
        $t->boolean('upgraded_by_admin')->default(false);
        $t->double('admin_original_grade')->nullable();
        $t->dateTime('admin_upgraded_at')->nullable();
        $t->softDeletes();
        $t->timestamps();
    });
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('students')->insert([['hemis_id' => '1001', 'level_code' => '12'], ['hemis_id' => '1002', 'level_code' => '12']]);
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

function upgradeItem(int $id, int $student, Carbon $date, float $grade): array
{
    return [
        'id' => $id, '_student' => $student, 'grade' => $grade,
        'lesson_date' => $date->timestamp, 'created_at' => $date->timestamp,
        'semester' => ['code' => '13', 'name' => '13'], 'educationYear' => ['code' => '2026', 'name' => '2026-2027'],
        '_subject_schedule' => 5, 'subject' => ['id' => 77, 'name' => 'Pediatriya', 'code' => 'P'],
        'trainingType' => ['code' => '13', 'name' => 'Amaliy'], 'employee' => ['id' => 9, 'name' => 'T'],
        'lessonPair' => ['code' => '2', 'name' => '2', 'start_time' => '10:00', 'end_time' => '11:20'],
    ];
}

it('keeps admin-upgraded grades when a day is re-imported from HEMIS', function () {
    $date = Carbon::parse('2026-10-25 10:00:00');
    $base = ['subject_id' => '77', 'subject_name' => 'Pediatriya', 'training_type_code' => '13', 'lesson_pair_code' => '2',
        'lesson_date' => $date, 'is_final' => true, 'status' => 'recorded', 'created_at' => now(), 'updated_at' => now()];
    $upgradedId = DB::table('student_grades')->insertGetId($base + ['hemis_id' => '1', 'student_hemis_id' => '1001', 'grade' => 80,
        'upgraded_by_admin' => true, 'admin_original_grade' => 65]);
    $plainId = DB::table('student_grades')->insertGetId($base + ['hemis_id' => '2', 'student_hemis_id' => '1002', 'grade' => 70]);

    $import = app(ImportGrades::class);
    $import->setOutput(new Illuminate\Console\OutputStyle(new Symfony\Component\Console\Input\ArrayInput([]), new Symfony\Component\Console\Output\NullOutput()));
    $apply = (new ReflectionClass($import))->getMethod('applyGrades');
    $apply->setAccessible(true);
    $apply->invoke($import, [upgradeItem(1, 1001, $date, 65), upgradeItem(2, 1002, $date, 72)], $date, true);

    $active = DB::table('student_grades')->whereNull('deleted_at')->get()->keyBy('student_hemis_id');
    expect($active)->toHaveCount(2)
        // Admin oshirgan baho — o'sha yozuv, o'sha baho, HEMIS dublikati yo'q
        ->and((int) $active['1001']->id)->toBe($upgradedId)
        ->and((float) $active['1001']->grade)->toBe(80.0)
        // Oddiy baho — HEMIS dan yangilandi
        ->and((int) $active['1002']->id)->not->toBe($plainId)
        ->and((float) $active['1002']->grade)->toBe(72.0);
});
