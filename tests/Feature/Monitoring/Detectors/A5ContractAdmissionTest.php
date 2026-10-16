<?php

require_once __DIR__ . '/../helpers.php';

use App\Models\MonitoringAdmissionRecommendation;
use App\Models\MonitoringIstisno;
use App\Services\Monitoring\DetectorRunner;
use Illuminate\Support\Facades\DB;

/*
 * A-5: kontrakt bo'yicha tavsiya etilganlar — to'lov (25 % / 50 %) ↔ qabul buyrug'i ↔ HEMIS kontingenti, muddat 01.11.2026.
 */

beforeEach(function () {
    $this->travelTo(now()->setDate(2026, 11, 3)->setTime(6, 0)); // muddatdan keyin
    $this->detector = monDetector('A-5', ['check_notice_evidence' => false]);
});

function a5Recommendation(?object $student, string $type = 'oddiy'): MonitoringAdmissionRecommendation
{
    $name = $student->full_name ?? 'Sinov Abituriyent ' . monSeq();

    return MonitoringAdmissionRecommendation::create([
        'dedupe_key' => sha1($name . monSeq()),
        'education_year' => '2026-2027',
        'applicant_id' => (string) (2000000 + monSeq()),
        'full_name' => $name,
        'contract_type' => $type,
        'student_hemis_id' => $student?->hemis_id,
        'match_method' => $student ? 'passport' : null,
        'match_confirmed' => (bool) $student,
    ]);
}

function a5Paid(object $student, float $sum, float $paid): void
{
    DB::table('contract_list')->insert([
        'hemis_id' => random_int(1, 1_000_000_000),
        'student_hemis_id' => $student->hemis_id,
        'edu_year' => '2026-2027',
        'year' => '2026',
        'edu_contract_sum' => $sum,
        'paid_credit_amount' => $paid,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function a5Signals(): array
{
    return MonitoringIstisno::where('fingerprint', 'like', 'A-5:%')->where('fingerprint', 'not like', 'A-5:xabarnoma%')
        ->get()->flatMap(fn ($i) => $i->details['signallar'])->all();
}

test('to\'lamagan (25 % dan kam), lekin kontingentda — istisno', function () {
    $student = monStudent(['level_code' => '11']);
    a5Recommendation($student);
    a5Paid($student, 20_000_000, 2_000_000); // 10 %

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::where('fingerprint', 'like', 'A-5:%')->sole();
    expect($istisno->warning_only)->toBeFalse()
        ->and($istisno->details['tolov_foizi'])->toEqual(10)
        ->and(a5Signals()[0])->toContain('kontingentda');
});

test('25 % to\'lagan va qabul buyrug\'i bor — istisno yo\'q', function () {
    $student = monStudent(['level_code' => '11']);
    a5Recommendation($student);
    a5Paid($student, 20_000_000, 5_000_000); // 25 %
    monOrder(['order_type' => 'qabul', 'student_hemis_id' => $student->hemis_id, 'order_date' => '2026-10-20']);

    app(DetectorRunner::class)->run($this->detector);

    expect(MonitoringIstisno::count())->toBe(0);
});

test('to\'lagan, lekin qabul buyrug\'i chiqmagan — istisno', function () {
    $student = monStudent(['level_code' => '11']);
    a5Recommendation($student);
    a5Paid($student, 20_000_000, 6_000_000);

    app(DetectorRunner::class)->run($this->detector);

    expect(a5Signals()[0])->toContain("qabul buyrug'i reyestrda yo'q");
});

test('qo\'shimcha kontraktda 50 % talab qilinadi', function () {
    $student = monStudent(['level_code' => '11']);
    a5Recommendation($student, 'qoshimcha');
    a5Paid($student, 40_000_000, 12_000_000); // 30 %
    monOrder(['order_type' => 'qabul', 'student_hemis_id' => $student->hemis_id]);

    app(DetectorRunner::class)->run($this->detector);

    $istisno = MonitoringIstisno::where('fingerprint', 'like', 'A-5:%')->sole();
    expect($istisno->details['talab_foizi'])->toEqual(50);
});

test('muddatgacha — faqat prognoz (ogohlantirish), mas\'ulga yuborilmaydi', function () {
    $this->travelTo(now()->setDate(2026, 10, 20));
    $student = monStudent(['level_code' => '11']);
    a5Recommendation($student);

    app(DetectorRunner::class)->run($this->detector->fresh());

    $istisno = MonitoringIstisno::where('fingerprint', 'like', 'A-5:%')->sole();
    expect($istisno->warning_only)->toBeTrue()->and($istisno->owner_id)->toBeNull();
});

test('xabardor qilish dalillari uchun bitta tekshiruv istisnosi ochiladi', function () {
    app(DetectorRunner::class)->run(monDetector('A-5', ['check_notice_evidence' => true]));
    app(DetectorRunner::class)->run(monDetector('A-5'));

    expect(MonitoringIstisno::where('fingerprint', 'A-5:xabarnoma:2026-2027')->count())->toBe(1);
});
