<?php

use App\Models\ManualCurriculum;
use App\Models\VedomostSubmission;
use App\Services\VedomostAiChecker;
use Illuminate\Support\Facades\DB;

/**
 * Vedomost uchun «O'quv reja to'g'riligi»dagi ishchi rejadan fan qatorini topish.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    DB::purge('sqlite');
    foreach (glob(database_path('migrations/*manual_curricul*')) as $f) {
        (include $f)->up();
    }
});

function planFor(string $sem, array $subjects, string $type = 'ishchi'): ManualCurriculum
{
    $c = ManualCurriculum::create(['type' => $type, 'status' => 'active', 'name' => "Davolash ishi — {$type} {$sem}", 'curricula_hemis_id' => 777, 'semester_code' => $sem]);
    foreach ($subjects as $s) {
        $c->subjects()->create($s);
    }

    return $c;
}

test('ishchi reja: shu o\'quv reja va semestr, nom normallashtirib moslanadi, shu semestr qatori olinadi', function () {
    planFor('19', [
        ['subject_name' => 'Xirurgik kasalliklar', 'semester' => 8, 'total_hours' => 60, 'credit' => 2],
        ['subject_name' => 'Xirurgik  kasalliklar.', 'semester' => 9, 'total_hours' => 90, 'credit' => 3],
    ]);
    planFor('19', [['subject_name' => 'Xirurgik kasalliklar', 'semester' => 9, 'total_hours' => 999, 'credit' => 9]], 'namunaviy');
    planFor('18', [['subject_name' => 'Xirurgik kasalliklar', 'semester' => 8, 'total_hours' => 60, 'credit' => 2]]);
    $v = new VedomostSubmission(['curriculum_hemis_id' => 777, 'semester_code' => '19', 'subject_name' => 'Xirurgik kasalliklar']);

    $p = app(VedomostAiChecker::class)->workingPlan($v, ['fan' => 'Xirurgik kasalliklar', 'talabalar' => []]);
    expect($p['found'])->toBeTrue()->and($p['hours'])->toBe(90.0)->and($p['credit'])->toBe(3.0)->and($p['semester'])->toBe(9);

    $none = app(VedomostAiChecker::class)->workingPlan(new VedomostSubmission(['curriculum_hemis_id' => 777, 'semester_code' => '20', 'subject_name' => 'X']), ['talabalar' => []]);
    expect($none['found'])->toBeFalse()->and($none['reason'])->toContain('10-semestr ishchi rejasi yuklanmagan');

    $missing = app(VedomostAiChecker::class)->workingPlan(new VedomostSubmission(['curriculum_hemis_id' => 777, 'semester_code' => '19', 'subject_name' => 'Pediatriya']), ['talabalar' => []]);
    expect($missing['found'])->toBeFalse()->and($missing['reason'])->toContain('«Pediatriya» fani topilmadi');
});
