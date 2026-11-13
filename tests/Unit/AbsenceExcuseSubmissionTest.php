<?php

use App\Services\AbsenceExcuseSubmission;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Veb forma va mobil API bir xil jadval qoidasidan o'tadi.
 * 2026-11: 1, 8, 15 — yakshanba.
 */

uses(Tests\TestCase::class);

afterEach(fn () => Carbon::setTestNow());

function submissionRequest(array $items, bool $manual = false): Request
{
    return Request::create('/', 'POST', [
        'start_date' => '2026-11-02',
        'end_date' => '2026-11-04', // 3 kun dars qoldirilgan
        'makeup_dates' => $items,
        'retake_manual' => $manual ? '1' : '0',
    ]);
}

function submissionItem(string $subject, string $type, string $original, ?string $makeup = null, array $extra = []): array
{
    return array_merge([
        'subject_name' => $subject, 'subject_id' => '1', 'assessment_type' => $type,
        'assessment_type_code' => '100', 'original_date' => $original, 'makeup_date' => $makeup ?? '',
    ], $extra);
}

it('ignores the dates an old app sends and builds the automatic plan from the submission day', function () {
    Carbon::setTestNow('2026-11-09 10:00:00');

    $plan = AbsenceExcuseSubmission::makeupPlan(submissionRequest([
        submissionItem('Anatomiya', 'jn', '2026-11-03', '2026-11-20'),
        submissionItem('Anatomiya', 'jn', '2026-11-02', '2026-11-25'),
        submissionItem('Anatomiya', 'mt', '2026-11-04', null, ['already_submitted' => '1']),
    ]));

    expect(array_map(fn ($p) => $p['makeup_date'], $plan))->toBe([
        1 => '2026-11-09',
        0 => '2026-11-10',
        2 => null, // MT yuklangan — kun ajratilmaydi
    ]);
});

it('keeps a moved day that follows the rules', function () {
    Carbon::setTestNow('2026-11-09 10:00:00');

    $plan = AbsenceExcuseSubmission::makeupPlan(submissionRequest([
        submissionItem('Anatomiya', 'jn', '2026-11-02', '2026-11-09'),
        submissionItem('Anatomiya', 'jn', '2026-11-03', '2026-11-11'),
    ], manual: true));

    expect(array_column($plan, 'makeup_date'))->toBe(['2026-11-09', '2026-11-11']);
});

it('rejects a moved day past the absent-days limit or two retakes of one subject on a day', function () {
    Carbon::setTestNow('2026-11-09 10:00:00');

    expect(AbsenceExcuseSubmission::makeupPlan(submissionRequest([
        submissionItem('Anatomiya', 'jn', '2026-11-02', '2026-11-12'), // 3 kun: 9, 10, 11
    ], manual: true)))->toContain('Qayta topshirish kunlari:')->toContain('11.11.2026 gacha');

    expect(AbsenceExcuseSubmission::makeupPlan(submissionRequest([
        submissionItem('Anatomiya', 'jn', '2026-11-02', '2026-11-10'),
        submissionItem('Anatomiya', 'jn', '2026-11-03', '2026-11-10'),
    ], manual: true)))->toContain('bittadan ortiq retake');
});
