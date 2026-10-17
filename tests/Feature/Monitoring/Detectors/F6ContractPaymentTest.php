<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringIstisno;
use App\Models\Setting;
use App\Services\ContractCutoffs;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Support\Facades\DB;

/*
 * F-6: kontrakt to'lovi bosqichlari — muddatlar Sozlamalarda kurs kesimida (ContractCutoffs).
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 10, 17)->setTime(6, 0));
    Setting::set('contract_cutoffs', json_encode([
        ['deadline' => '2026-10-01', 'percent' => 25], ['deadline' => '2027-01-01', 'percent' => 50],
        ['deadline' => '2027-03-01', 'percent' => 75], ['deadline' => '2027-05-01', 'percent' => 100],
    ]));
    ContractCutoffs::saveByCourse(
        [1 => [['deadline' => '2026-11-01', 'percent' => 25], ['deadline' => '2027-01-01', 'percent' => 50]]],
        [['deadline' => '2026-11-01', 'percent' => 50]],
        ['15'],
    );
    $this->detector = monDetector('F-6');
});

function f6Contract(object $student, int $course, float $paidPercent, string $type = '11'): void
{
    DB::table('contract_list')->insert([
        'hemis_id' => random_int(1, 1_000_000_000), 'student_hemis_id' => $student->hemis_id, 'edu_year' => '2026-2027',
        'edu_cours_id' => $course, 'edu_contract_type_code' => $type, 'edu_contract_type_name' => 'Sinov turi ' . $type,
        'edu_contract_sum' => 20_000_000, 'paid_credit_amount' => 20_000_000 * $paidPercent / 100,
        'created_at' => now(), 'updated_at' => now(),
    ]);
}

test('2-kurs umumiy jadval: 1-oktabr o\'tgan, 25 % to\'lanmagan — istisno; 1-kurs va superkontrakt hali muddatida', function () {
    $second = monStudent();
    f6Contract($second, 2, 10);
    f6Contract(monStudent(), 1, 10);
    f6Contract(monStudent(), 2, 10, '15');
    f6Contract(monStudent(), 2, 30);

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::sole();
    expect($istisno->fingerprint)->toBe("F-6:{$second->hemis_id}:2026-2027")
        ->and($istisno->details['jadval'])->toBe('Umumiy jadval')
        ->and($istisno->details['muddat'])->toBe('2026-10-01')
        ->and($istisno->details['talab_foizi'])->toBe(25)
        ->and($istisno->details['yetishmaydi_summa'])->toEqual(3_000_000);
});

test('1-noyabrdan keyin 1-kurs (25 %) va superkontrakt (50 %) jadvallari qo\'llanadi', function () {
    $first = monStudent();
    f6Contract($first, 1, 20);
    $super = monStudent();
    f6Contract($super, 3, 40, '15');
    f6Contract(monStudent(), 1, 30);

    $this->travelTo(now()->setDate(2026, 11, 5));
    app(DetectorRunner::class)->run($this->detector->fresh());

    expect(MonitoringIstisno::where('fingerprint', "F-6:{$first->hemis_id}:2026-2027")->value('details')['jadval'])->toBe('1-kurs')
        ->and(MonitoringIstisno::where('fingerprint', "F-6:{$super->hemis_id}:2026-2027")->value('details')['talab_foizi'])->toBe(50)
        ->and(MonitoringIstisno::count())->toBe(2);
});

test('o\'tgan o\'quv yilidan qolgan jadval va o\'qimayotgan talaba hisobga olinmaydi', function () {
    Setting::set('contract_cutoffs', json_encode(ContractCutoffs::DEFAULT_GENERAL)); // 2025/2026 sanalari
    f6Contract(monStudent(), 2, 0);
    f6Contract(monStudent(['student_status_code' => '16']), 1, 0);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('to\'lov qilinsa istisno yopiladi', function () {
    $student = monStudent();
    f6Contract($student, 2, 10);
    app(DetectorRunner::class)->run($this->detector);
    expect(MonitoringIstisno::sole()->status)->toBe('ochiq');

    DB::table('contract_list')->where('student_hemis_id', $student->hemis_id)->update(['paid_credit_amount' => 6_000_000]);
    app(DetectorRunner::class)->run($this->detector->fresh());

    expect(MonitoringIstisno::sole()->status)->toBe('yopildi');
});
