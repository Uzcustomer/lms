<?php

use App\Models\MarkingSystemScore;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal chegara: admin sozlamasi barcha baholash tizimlari uchun HEMIS qiymatidan ustun,
 * HEMIS sinxronizatsiyasi sozlamani o'zgartirmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('marking_system_scores', fn ($t) => [$t->id(), $t->string('marking_system_code'), $t->string('marking_system_name')->nullable(),
        $t->integer('minimum_limit')->default(60), $t->float('gpa_limit')->nullable(),
        ...collect(['jn', 'mt', 'on', 'oski', 'test', 'total'])->flatMap(fn ($k) => [$t->integer($k . '_limit')->default(60), $t->boolean($k . '_active')->default(true)])->all(),
        $t->timestamps()]);
    MarkingSystemScore::forgetLmsMinimumLimit();
});

afterEach(fn () => MarkingSystemScore::forgetLmsMinimumLimit());

it('uses the HEMIS limit until the LMS setting is filled', function () {
    $score = MarkingSystemScore::create(['marking_system_code' => '11', 'minimum_limit' => 60]);

    expect($score->fresh()->minimum_limit)->toBe(60)
        ->and(MarkingSystemScore::lowGradeLimit())->toBe(60)
        ->and(MarkingSystemScore::getDefault()->minimum_limit)->toBe(60);

    Setting::set(MarkingSystemScore::SETTING_MINIMUM_LIMIT, '70');
    MarkingSystemScore::forgetLmsMinimumLimit();

    expect($score->fresh()->minimum_limit)->toBe(70)
        ->and($score->fresh()->hemisMinimumLimit())->toBe(60)
        ->and(MarkingSystemScore::lowGradeLimit())->toBe(70)
        ->and(MarkingSystemScore::getDefault()->minimum_limit)->toBe(70);

    // HEMIS sinxronizatsiyasi asl ustunni yangilaydi — sozlama baribir ustun
    MarkingSystemScore::updateOrCreate(['marking_system_code' => '11'], ['minimum_limit' => 55]);
    expect($score->fresh()->minimum_limit)->toBe(70)
        ->and($score->fresh()->hemisMinimumLimit())->toBe(55);

    Setting::set(MarkingSystemScore::SETTING_MINIMUM_LIMIT, '');
    MarkingSystemScore::forgetLmsMinimumLimit();
    expect($score->fresh()->minimum_limit)->toBe(55)
        ->and(MarkingSystemScore::lowGradeLimit())->toBe(60);
});

it('raises 100-point component limits to the system minimum on save, leaving 5-point ones', function () {
    $hundred = MarkingSystemScore::create(['marking_system_code' => '11']);
    $five = MarkingSystemScore::create(['marking_system_code' => '12']);
    $row = fn ($v, $oski) => ['jn_limit' => $v, 'jn_active' => '1', 'mt_limit' => 60, 'on_limit' => 60,
        'oski_limit' => $oski, 'oski_active' => '1', 'test_limit' => $v, 'total_limit' => $v];

    $request = Illuminate\Http\Request::create('/', 'POST', ['grade_minimum_limit' => 70,
        'scores' => [$hundred->id => $row(60, 80), $five->id => $row(3, 3)]]);
    app(App\Http\Controllers\Admin\SettingsController::class)->updateMarkingSystemScores($request);

    $hundred->refresh();
    $five->refresh();
    expect([$hundred->jn_limit, $hundred->mt_limit, $hundred->on_limit, $hundred->oski_limit, $hundred->test_limit, $hundred->total_limit])
        ->toBe([70, 70, 70, 80, 70, 70])
        ->and([$five->jn_limit, $five->oski_limit, $five->test_limit, $five->mt_limit])->toBe([3, 3, 3, 70])
        ->and(MarkingSystemScore::getDefault()->oski_limit)->toBe(70);
});
