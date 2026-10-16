<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Models\MonitoringTransferChecklist;
use App\Services\Monitoring\DetectorRunner;

/*
 * E-7: ko'chirib kelgan talaba yig'majildida 6 majburiy hujjat + aslini tekshirish.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
});

function e7Items(bool $original = true, array $skip = []): array
{
    $items = [];
    foreach (array_keys(config('monitoring.transfer_documents')) as $key) {
        $items[$key] = ['bor' => !in_array($key, $skip, true), 'asli_tekshirildi' => $original, 'izoh' => null];
    }

    return $items;
}

function e7Checklist(array $items): MonitoringTransferChecklist
{
    return MonitoringTransferChecklist::create([
        'full_name' => 'Sinov Ko\'chgan Talaba', 'student_id_number' => 'ST-E7', 'arrived_at' => '2026-09-15', 'items' => $items,
    ]);
}

test('hujjatlar to\'liq emas — istisno, yetishmaganlari ro\'yxatda', function () {
    e7Checklist(e7Items(true, ['transkript', 'sorovnoma_javobi']));

    app(DetectorRunner::class)->run(monDetector('E-7'));

    $istisno = MonitoringIstisno::sole();
    expect($istisno->details['yetishmaydi'])->toHaveCount(2)
        ->and($istisno->subject_label)->toContain('4/6');
});

test('6 hujjat bor va asli tekshirilgan — istisno yo\'q', function () {
    e7Checklist(e7Items());

    app(DetectorRunner::class)->run(monDetector('E-7'));

    expect(MonitoringIstisno::count())->toBe(0);
});

test('asli tekshirilmagan: talab yoqilgan bo\'lsa istisno, o\'chirilgan bo\'lsa yo\'q', function () {
    e7Checklist(e7Items(false));

    app(DetectorRunner::class)->run(monDetector('E-7', ['require_original_check' => true]));
    expect(MonitoringIstisno::count())->toBe(1);

    app(DetectorRunner::class)->run(monDetector('E-7', ['require_original_check' => false]));
    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');
});
