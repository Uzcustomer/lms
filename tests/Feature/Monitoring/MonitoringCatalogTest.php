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

test('pilot detektorlar xavfsiz ishga tushirish uchun ogohlantirish rejimida boshlanadi', function () {
    expect(MonitoringDetector::where('pilot', true)->where('warning_mode', false)->count())->toBe(0)
        ->and(MonitoringDetector::where('code', 'D-3')->value('warning_mode'))->toBeFalsy();
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

test('eski katalog bilan seed qilingan serverda buyurtmachi qarorlari migratsiya bilan qo\'llanadi', function () {
    // Eski holat: sinov rejimi yo'q, Q-1 qayta topshirishni tekshirmaydi, E-1 da turar joy kalitlari yo'q.
    MonitoringDetector::where('pilot', true)->update(['warning_mode' => false]);
    $q1 = MonitoringDetector::where('code', 'Q-1')->first();
    $q1->update(['params' => array_merge($q1->params, ['include_resits' => false])]);
    $e1 = MonitoringDetector::where('code', 'E-1')->first();
    $e1->update(['params' => \Illuminate\Support\Arr::except($e1->params, ['accommodation_keywords', 'accommodation_codes'])]);
    MonitoringDetector::where('code', 'D-2')->update(['last_run_at' => now()]); // allaqachon ishlagan

    (require base_path('database/migrations/2026_10_16_110000_apply_monitoring_pilot_decisions.php'))->up();

    expect(MonitoringDetector::where('code', 'Q-1')->first()->params['include_resits'])->toBeTrue()
        ->and(MonitoringDetector::where('code', 'E-1')->first()->params['accommodation_keywords'])->toBe(['ijara'])
        ->and(MonitoringDetector::where('code', 'Q-1')->value('warning_mode'))->toBeTruthy()
        ->and(MonitoringDetector::where('code', 'D-2')->value('warning_mode'))->toBeFalsy();
});

test('katalog o\'qilmasa ham migratsiya qarorlarni mavjud detektorlarga qo\'llaydi', function () {
    config(['monitoring.catalog_path' => base_path('docs/yoq-fayl.yaml')]);
    MonitoringDetector::where('pilot', true)->update(['warning_mode' => false]);
    $q1 = MonitoringDetector::where('code', 'Q-1')->first();
    $q1->update(['params' => array_merge($q1->params, ['include_resits' => false])]);

    (require base_path('database/migrations/2026_10_16_110000_apply_monitoring_pilot_decisions.php'))->up();

    expect(MonitoringDetector::where('code', 'Q-1')->first()->params['include_resits'])->toBeTrue()
        ->and(MonitoringDetector::where('pilot', true)->where('warning_mode', false)->count())->toBe(0);
});
