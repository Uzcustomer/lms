<?php

namespace Tests\Feature\Retake;

use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->student = $this->makeStudent();
    $this->period = $this->makePeriod($this->student);
    $this->makeDebt($this->student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'semester_id' => '7']);
    $this->makeDebt($this->student, ['subject_id' => 101, 'subject_name' => 'Biokimyo', 'semester_id' => '7']);
    $this->makeDebt($this->student, ['subject_id' => 102, 'subject_name' => 'Fiziologiya', 'semester_id' => '7']);
    $this->makeDebt($this->student, ['subject_id' => 103, 'subject_name' => 'Mikrobiologiya', 'semester_id' => '7']);
});

it('submits application for a single subject', function () {
    $apps = app(RetakeApplicationService::class)->submit(
        $this->student,
        [['subject_id' => 100, 'semester_id' => 7]],
        $this->makeReceiptFile(),
        'Test izoh',
    );

    expect($apps)->toHaveCount(1);
    expect($apps->first()->subject_name)->toBe('Anatomiya');
    expect($apps->first()->student_note)->toBe('Test izoh');
    expect($apps->first()->dean_status->value)->toBe('pending');
    expect($apps->first()->registrar_status->value)->toBe('pending');
    expect($apps->first()->academic_dept_status->value)->toBe('not_started');
});

it('submits multi-subject application with one common group_id', function () {
    $apps = app(RetakeApplicationService::class)->submit(
        $this->student,
        [
            ['subject_id' => 100, 'semester_id' => 7],
            ['subject_id' => 101, 'semester_id' => 7],
            ['subject_id' => 102, 'semester_id' => 7],
        ],
        $this->makeReceiptFile(),
    );

    expect($apps)->toHaveCount(3);
    $groupIds = $apps->pluck('application_group_id')->unique();
    expect($groupIds)->toHaveCount(1);
});

it('rejects more than 3 subjects', function () {
    expect(fn () => app(RetakeApplicationService::class)->submit(
        $this->student,
        [
            ['subject_id' => 100, 'semester_id' => 7],
            ['subject_id' => 101, 'semester_id' => 7],
            ['subject_id' => 102, 'semester_id' => 7],
            ['subject_id' => 103, 'semester_id' => 7],
        ],
        $this->makeReceiptFile(),
    ))->toThrow(ValidationException::class);
});

it('rejects empty subjects array', function () {
    expect(fn () => app(RetakeApplicationService::class)->submit(
        $this->student, [], $this->makeReceiptFile(),
    ))->toThrow(ValidationException::class);
});

it('rejects subjects not in academic debt list', function () {
    expect(fn () => app(RetakeApplicationService::class)->submit(
        $this->student,
        [['subject_id' => 999, 'semester_id' => 7]],
        $this->makeReceiptFile(),
    ))->toThrow(ValidationException::class);
});

it('rejects when no active period for student', function () {
    $orphan = $this->makeStudent(['specialty_id' => 99, 'semester_id' => 99]);
    $this->makeDebt($orphan, ['subject_id' => 100, 'semester_id' => '99']);

    expect(fn () => app(RetakeApplicationService::class)->submit(
        $orphan,
        [['subject_id' => 100, 'semester_id' => 99]],
        $this->makeReceiptFile(),
    ))->toThrow(ValidationException::class);
});

it('blocks duplicate active application for same subject', function () {
    $svc = app(RetakeApplicationService::class);
    $svc->submit($this->student, [['subject_id' => 100, 'semester_id' => 7]], $this->makeReceiptFile());

    expect(fn () => $svc->submit(
        $this->student,
        [['subject_id' => 100, 'semester_id' => 7]],
        $this->makeReceiptFile(),
    ))->toThrow(ValidationException::class);
});

it('logs submitted action on first submission', function () {
    $apps = app(RetakeApplicationService::class)->submit(
        $this->student,
        [['subject_id' => 100, 'semester_id' => 7]],
        $this->makeReceiptFile(),
    );

    $log = $apps->first()->logs->first();
    expect($log->action->value)->toBe('submitted');
});
