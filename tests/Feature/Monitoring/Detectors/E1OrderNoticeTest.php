<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Models\MonitoringNoticeLog;
use App\Services\Monitoring\DetectorRunner;

/*
 * E-1: akademik ta'til / chetlashtirish / kursda qoldirish buyrug'i → 1 ish kunida ijara kotibi va buxgalteriyaga.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 16)->setTime(9, 0));
    $this->detector = monDetector('E-1');
});

function e1Notice($order, string $kind, string $at): void
{
    MonitoringNoticeLog::create([
        'order_id' => $order->id, 'recipient_kind' => $kind, 'recipient_name' => 'Sinov qabul qiluvchi',
        'sent_at' => $at, 'method' => 'mark',
    ]);
}

test('muddat o\'tgan, xabar berilmagan — istisno', function () {
    monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-12']);

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->details['xabar_berilmagan'])->toBe(['Ijara kotibi', 'Buxgalteriya']);
});

test('ikkala qabul qiluvchiga 1 ish kunida xabar berilgan — istisno yo\'q', function () {
    $order = monOrder(['order_type' => 'chetlashtirish', 'order_date' => '2026-10-12']);
    e1Notice($order, 'ijara_kotibi', '2026-10-12 15:00:00');
    e1Notice($order, 'buxgalteriya', '2026-10-13 11:00:00');

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('faqat bittasiga xabar berilgan — yetishmayotgani ko\'rsatiladi', function () {
    $order = monOrder(['order_type' => 'kursda_qoldirish', 'order_date' => '2026-10-12']);
    e1Notice($order, 'ijara_kotibi', '2026-10-12 15:00:00');

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::sole()->details['xabar_berilmagan'])->toBe(['Buxgalteriya']);
});

test('muddat hali tugamagan yoki buyruq turi talab qilmaydi — istisno yo\'q', function () {
    monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-16']);
    monOrder(['order_type' => 'qabul', 'order_date' => '2026-10-01']);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});
