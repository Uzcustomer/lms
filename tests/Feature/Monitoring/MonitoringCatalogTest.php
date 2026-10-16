<?php

require_once __DIR__ . '/helpers.php';

use App\Models\MonitoringDetector;
use App\Services\Monitoring\MonitoringCatalog;

/*
 * docs/detectors.yaml → monitoring_detectors: 54 detektor; paneldagi qiymatlar qayta sinxronda saqlanadi.
 */

test('katalog 54 detektorni yuklaydi, ⚠ lar ogohlantirish rejimida', function () {
    expect(MonitoringDetector::count())->toBe(54)
        ->and(MonitoringDetector::where('pilot', true)->pluck('code')->sort()->values()->all())->toBe(['A-5', 'D-2', 'E-1', 'E-7', 'Q-1'])
        ->and(MonitoringDetector::where('code', 'D-1')->value('warning_mode'))->toBeTruthy()
        ->and(MonitoringDetector::where('code', 'D-2')->first()->param('threshold_hours'))->toBe(74);
});

test('qayta sinxron paneldagi parametrni o\'zgartirmaydi, yangi kalitni qo\'shadi', function () {
    $d2 = MonitoringDetector::where('code', 'D-2')->first();
    $d2->update(['params' => ['threshold_hours' => 90], 'enabled' => false]);

    app(MonitoringCatalog::class)->sync();

    $d2->refresh();
    expect($d2->params['threshold_hours'])->toBe(90)
        ->and($d2->params)->toHaveKey('exclude_medical')
        ->and($d2->enabled)->toBeFalse();

    app(MonitoringCatalog::class)->sync(null, true);
    expect($d2->fresh()->params['threshold_hours'])->toBe(74)->and($d2->fresh()->enabled)->toBeTrue();
});
