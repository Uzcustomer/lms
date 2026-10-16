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

test('qoida faqat ijarada yashovchilarga: ijarada — istisno, TTJ da — yo\'q', function () {
    $tenant = monStudent(['accommodation_code' => '12', 'accommodation_name' => 'Ijarada']);
    $dorm = monStudent(['accommodation_code' => '13', 'accommodation_name' => 'Talabalar turar joyida']);
    monOrder(['order_type' => 'chetlashtirish', 'order_date' => '2026-10-12', 'student_hemis_id' => $tenant->hemis_id]);
    monOrder(['order_type' => 'chetlashtirish', 'order_date' => '2026-10-12', 'student_hemis_id' => $dorm->hemis_id]);

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->subject_label)->toContain('B-')
        ->and($istisno->details['turar_joy'])->toBe('Ijarada');
});

test('turar joy kodi berilsa kod bo\'yicha aniqlanadi', function () {
    $student = monStudent(['accommodation_code' => '15', 'accommodation_name' => 'Boshqa']);
    monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-12', 'student_hemis_id' => $student->hemis_id]);

    app(DetectorRunner::class)->run(monDetector('E-1', ['accommodation_codes' => ['15']]));
    expect(MonitoringIstisno::count())->toBe(1);

    app(DetectorRunner::class)->run(monDetector('E-1', ['accommodation_codes' => [], 'accommodation_keywords' => ['ijara']]));
    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');
});

test('kodlar berilsa faqat kod hal qiladi: nomida «ijara» bo\'lsa ham boshqa kod — istisno yo\'q', function () {
    $student = monStudent(['accommodation_code' => '13', 'accommodation_name' => 'Ijarada (vaqtincha)']);
    monOrder(['order_type' => 'chetlashtirish', 'order_date' => '2026-10-12', 'student_hemis_id' => $student->hemis_id]);

    app(DetectorRunner::class)->run(monDetector('E-1', ['accommodation_codes' => ['12'], 'accommodation_keywords' => ['ijara']]));

    expect(MonitoringIstisno::count())->toBe(0);
});

test('istisno talaba kursining registratoriga, biriktirilmagan bo\'lsa standart mas\'ulga boradi', function () {
    $registrar = monTeacher(['registrator_ofisi']);
    $other = monTeacher(['registrator_ofisi']);
    $fallback = monTeacher(['registrator_ofisi']);
    \App\Models\StaffRegistrationDivision::create([
        'teacher_id' => $registrar->id, 'division_type' => 'back_office', 'department_hemis_id' => 7101, 'level_code' => '12',
    ]);
    \App\Models\StaffRegistrationDivision::create([
        'teacher_id' => $other->id, 'division_type' => 'back_office', 'department_hemis_id' => 7101, 'level_code' => '13',
    ]);
    $second = monStudent(['department_id' => 7101, 'level_code' => '12', 'accommodation_name' => 'Ijarada']);
    $fourth = monStudent(['department_id' => 7102, 'level_code' => '14', 'accommodation_name' => 'Ijarada']);
    $byCourse = monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-12', 'student_hemis_id' => $second->hemis_id]);
    $unassigned = monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-12', 'student_hemis_id' => $fourth->hemis_id]);
    $detector = monDetector('E-1', [], ['default_owner_type' => $fallback->getMorphClass(), 'default_owner_id' => $fallback->id]);

    app(DetectorRunner::class)->run($detector);

    expect(MonitoringIstisno::where('fingerprint', "E-1:{$byCourse->id}")->sole()->isOwnedBy($registrar))->toBeTrue()
        ->and(MonitoringIstisno::where('fingerprint', "E-1:{$unassigned->id}")->sole()->isOwnedBy($fallback))->toBeTrue();
});

test('registrar_office o\'chirilsa — standart mas\'ulga', function () {
    $registrar = monTeacher(['registrator_ofisi']);
    $fallback = monTeacher(['registrator_ofisi']);
    \App\Models\StaffRegistrationDivision::create([
        'teacher_id' => $registrar->id, 'division_type' => 'back_office', 'department_hemis_id' => 7101,
    ]);
    $student = monStudent(['department_id' => 7101, 'accommodation_name' => 'Ijarada']);
    monOrder(['order_type' => 'akademik_tatil', 'order_date' => '2026-10-12', 'student_hemis_id' => $student->hemis_id]);
    $detector = monDetector('E-1', ['registrar_office' => ''], ['default_owner_type' => $fallback->getMorphClass(), 'default_owner_id' => $fallback->id]);

    app(DetectorRunner::class)->run($detector);

    expect(MonitoringIstisno::sole()->isOwnedBy($fallback))->toBeTrue();
});
