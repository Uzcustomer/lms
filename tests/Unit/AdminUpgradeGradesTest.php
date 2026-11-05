<?php

use App\Console\Commands\AdminUpgradeGrades;
use App\Models\MarkingSystemScore;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * grades:admin-upgrade: ro'yxatdagi guruh/fan/sana oralig'idagi kunlik baholar formula bo'yicha
 * oshiriladi, eski baho saqlanadi, qayta ishga tushirish ikki marta oshirmaydi, --revert qaytaradi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('deadlines', fn ($t) => [$t->id(), $t->string('level_code'), $t->integer('deadline_days'), $t->timestamps()]);
    Schema::create('groups', fn ($t) => [$t->id(), $t->unsignedBigInteger('group_hemis_id'), $t->string('name')]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->unsignedBigInteger('group_id'), $t->string('level_code')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_name'), $t->dateTime('lesson_date'),
        $t->integer('training_type_code'), $t->float('grade')->nullable(), $t->float('retake_grade')->nullable(), $t->string('reason')->nullable(),
        $t->string('status')->nullable(), $t->dateTime('deadline')->nullable(), $t->boolean('upgraded_by_admin')->default(false),
        $t->decimal('admin_original_grade', 8, 2)->nullable(), $t->timestamp('admin_upgraded_at')->nullable(), $t->softDeletes(), $t->timestamps()]);

    DB::table('settings')->insert(['key' => MarkingSystemScore::SETTING_MINIMUM_LIMIT, 'value' => '70']);
    MarkingSystemScore::forgetLmsMinimumLimit();
    DB::table('deadlines')->insert(['level_code' => '15', 'deadline_days' => 7]);
    // Ro'yxatda d1/d23-09 — LMS da kichik guruhlar (a), (b); d1/d23-091 — boshqa guruh
    DB::table('groups')->insert([['group_hemis_id' => 1, 'name' => 'd1/d23-09(a)'], ['group_hemis_id' => 2, 'name' => 'd1/d23-01'],
        ['group_hemis_id' => 3, 'name' => 'd1/d23-09 (b)'], ['group_hemis_id' => 4, 'name' => 'd1/d23-091']]);
    DB::table('students')->insert([['hemis_id' => '10', 'group_id' => 1, 'level_code' => '15'], ['hemis_id' => '20', 'group_id' => 2, 'level_code' => '15'],
        ['hemis_id' => '30', 'group_id' => 3, 'level_code' => '15'], ['hemis_id' => '40', 'group_id' => 4, 'level_code' => '15']]);

    // Ro'yxat yo'li base_path() ga nisbiy
    $this->list = 'storage/framework/testing-upgrade-list.csv';
    file_put_contents(base_path($this->list), "guruh;fan;boshlanish;tugash\nd1/d23-09;Pediatriya;24.10.2026;02.11.2026\n");
});

afterEach(function () {
    @unlink(base_path($this->list));
    MarkingSystemScore::forgetLmsMinimumLimit();
});

function upgradeGrade(array $row): int
{
    return DB::table('student_grades')->insertGetId($row + ['student_hemis_id' => '10', 'subject_name' => 'Pediatriya',
        'lesson_date' => '2026-10-27 10:00:00', 'training_type_code' => 13, 'status' => 'recorded']);
}

it('applies the formula by range', function () {
    expect(AdminUpgradeGrades::upgradedGrade(59.9))->toBeNull()
        ->and(AdminUpgradeGrades::upgradedGrade(60))->toBe(70.0)
        ->and(AdminUpgradeGrades::upgradedGrade(69))->toBe(79.0)
        ->and(AdminUpgradeGrades::upgradedGrade(70))->toBe(80.0)
        ->and(AdminUpgradeGrades::upgradedGrade(71))->toBe(80.5)
        ->and(AdminUpgradeGrades::upgradedGrade(84.9))->toBe(87.45)
        ->and(AdminUpgradeGrades::upgradedGrade(89.8))->toBe(89.9)
        ->and(AdminUpgradeGrades::upgradedGrade(90))->toBe(95.0)
        ->and(AdminUpgradeGrades::upgradedGrade(99))->toBe(99.5)
        ->and(AdminUpgradeGrades::upgradedGrade(100))->toBe(100.0);
});

it('upgrades only listed group/subject/dates, keeps the old grade, is idempotent and reverts', function () {
    $low = upgradeGrade(['grade' => 65, 'reason' => 'low_grade', 'status' => 'pending', 'deadline' => '2026-11-03 23:59:59']);
    $mid = upgradeGrade(['grade' => 80]);
    $below = upgradeGrade(['grade' => 50, 'reason' => 'low_grade', 'status' => 'pending']);
    $retaken = upgradeGrade(['grade' => 65, 'retake_grade' => 85, 'reason' => 'low_grade', 'status' => 'retake']);
    $lecture = upgradeGrade(['grade' => 80, 'training_type_code' => 11]);
    $outside = upgradeGrade(['grade' => 80, 'lesson_date' => '2026-10-23 10:00:00']);
    $otherSubject = upgradeGrade(['grade' => 80, 'subject_name' => 'Onkologiya']);
    $otherGroup = upgradeGrade(['grade' => 80, 'student_hemis_id' => '20']);
    $similarGroup = upgradeGrade(['grade' => 80, 'student_hemis_id' => '40']);
    $subgroupB = upgradeGrade(['grade' => 90, 'student_hemis_id' => '30']);

    $local = $this->list;
    $this->artisan('grades:admin-upgrade', ['file' => $local])->assertSuccessful();
    expect((float) DB::table('student_grades')->find($mid)->grade)->toBe(80.0);   // hisobot — yozmaydi

    $this->artisan('grades:admin-upgrade', ['file' => $local, '--force' => true])->assertSuccessful();
    $this->artisan('grades:admin-upgrade', ['file' => $local, '--force' => true])->assertSuccessful();

    $g = fn ($id) => DB::table('student_grades')->find($id);
    expect((float) $g($low)->grade)->toBe(75.0)
        ->and((float) $g($low)->admin_original_grade)->toBe(65.0)
        ->and((bool) $g($low)->upgraded_by_admin)->toBeTrue()
        ->and($g($low)->reason)->toBeNull()              // 75 ≥ 70 — otrabotka qarzi yopildi
        ->and($g($low)->status)->toBe('recorded')
        ->and((float) $g($mid)->grade)->toBe(85.0)        // ikki marta ishlatildi — baribir 85
        ->and((float) $g($mid)->admin_original_grade)->toBe(80.0)
        ->and((float) $g($subgroupB)->grade)->toBe(95.0);
    foreach ([$below, $retaken, $lecture, $outside, $otherSubject, $otherGroup, $similarGroup] as $id) {
        expect((bool) $g($id)->upgraded_by_admin)->toBeFalse();
    }

    $this->artisan('grades:admin-upgrade', ['file' => $local, '--revert' => true, '--force' => true])->assertSuccessful();

    expect((float) $g($low)->grade)->toBe(65.0)
        ->and((bool) $g($low)->upgraded_by_admin)->toBeFalse()
        ->and($g($low)->admin_original_grade)->toBeNull()
        ->and($g($low)->reason)->toBe('low_grade')        // 65 < 70 — qarz qaytdi
        ->and($g($low)->status)->toBe('pending')
        ->and((float) $g($mid)->grade)->toBe(80.0);
});
