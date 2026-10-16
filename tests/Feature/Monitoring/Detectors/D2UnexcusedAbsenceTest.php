<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Support\Facades\DB;

/*
 * D-2: semestrda sababsiz > 74 soat ↔ chetlashtirish buyrug'i.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 20)->setTime(6, 0));
    $this->faculty = monFaculty();
    $this->dean = monDean($this->faculty);
    $this->detector = monDetector('D-2');
});

function d2Run($test): void
{
    app(DetectorRunner::class)->run($test->detector->fresh());
}

test('74 soatdan ortiq qoldirgan, buyrug\'i yo\'q talaba — istisno, mas\'ul dekan', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 40, '2026-09-20');
    monAbsence($student, 40, '2026-10-01');

    d2Run($this);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toBe("D-2:{$student->hemis_id}:2026:13")
        ->and($istisno->details['hisoblangan_soat'])->toEqual(80)
        ->and($istisno->isOwnedBy($this->dean))->toBeTrue()
        ->and($istisno->faculty_hemis_id)->toBe($this->faculty);
});

test('74 soat va undan kam — istisno yo\'q', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 74);

    d2Run($this);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('chetlashtirish buyrug\'i reyestrda bo\'lsa — istisno yo\'q, mavjudi avtomatik yopiladi', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 90);
    d2Run($this);
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    monOrder(['order_type' => 'chetlashtirish', 'student_hemis_id' => $student->hemis_id, 'order_date' => '2026-10-15']);
    d2Run($this);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->status)->toBe('yopildi')->and($istisno->closed_by_system)->toBeTrue();
});

test('talaba endi o\'qimayotgan bo\'lsa (holat kodi 11 emas) — istisno yo\'q', function () {
    $student = monStudent(['department_id' => $this->faculty, 'student_status_code' => '13']);
    monAbsence($student, 90);

    d2Run($this);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('tasdiqlangan tibbiy ariza qamragan soatlar ayiriladi', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 60, '2026-09-20');
    monAbsence($student, 20, '2026-10-02');
    DB::table('absence_excuses')->insert([
        'student_id' => $student->id,
        'student_hemis_id' => (string) $student->hemis_id,
        'student_full_name' => $student->full_name,
        'reason' => 'kasallik',
        'start_date' => '2026-10-01',
        'end_date' => '2026-10-05',
        'status' => 'approved',
        'file_path' => 'sinov.pdf',
        'file_original_name' => 'sinov.pdf',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    d2Run($this);

    expect(MonitoringIstisno::count())->toBe(0); // 80 - 20 = 60 ≤ 74
});

test('chegara paneldan o\'zgarsa keyingi ishga tushishda kuchga kiradi', function () {
    $student = monStudent(['department_id' => $this->faculty]);
    monAbsence($student, 80);

    $this->detector = monDetector('D-2', ['threshold_hours' => 90]);
    d2Run($this);
    expect(MonitoringIstisno::count())->toBe(0);

    $this->detector = monDetector('D-2', ['threshold_hours' => 74]);
    d2Run($this);
    expect(MonitoringIstisno::count())->toBe(1);
});
