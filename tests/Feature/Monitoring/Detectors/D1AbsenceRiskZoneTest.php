<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Services\Monitoring\DetectorRunner;

/*
 * D-1: xavf zonasi — semestrda sababsiz 30–74 soat (D-2 bilan bir xil hisob).
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(6, 0));
    $this->faculty = monFaculty();
    $this->dean = monDean($this->faculty);
    $this->detector = monDetector('D-1');
});

test('30–74 soat oralig\'idagi talaba — xavf zonasi, chegaragacha qolgan soat ko\'rsatiladi', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 30, '2026-09-20');
    monAbsence($student, 20, '2026-10-01');

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toBe("D-1:{$student->hemis_id}:2026:13")
        ->and($istisno->details['hisoblangan_soat'])->toEqual(50)
        ->and($istisno->details['chetlashtirishgacha'])->toEqual(24)
        ->and($istisno->isOwnedBy($this->dean))->toBeTrue();
});

test('30 soatdan kam yoki 74 dan ortiq (D-2) — xavf zonasida emas', function () {
    $low = monStudent(['department_id' => $this->faculty]);
    monAbsence($low, 28);
    $high = monStudent(['department_id' => $this->faculty]);
    monAbsence($high, 80);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('soat 74 dan oshsa xavf zonasi istisnosi yopiladi', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 60, '2026-10-01');
    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    monAbsence($student, 20, '2026-10-10');
    app(DetectorRunner::class)->run($this->detector->fresh());

    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');
});
