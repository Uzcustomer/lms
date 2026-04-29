<?php

namespace Tests\Feature\Retake;

use App\Services\Retake\StudentDebtService;

it('returns only retraining_status=true subjects as debts', function () {
    $student = $this->makeStudent();

    // Qarzdor fan
    $this->makeDebt($student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'retraining_status' => true]);
    // Topshirilgan fan (qarzdorlik emas)
    $this->makeDebt($student, ['subject_id' => 101, 'subject_name' => 'Biokimyo', 'retraining_status' => false, 'finish_credit_status' => true]);
    // Yana qarzdorlik
    $this->makeDebt($student, ['subject_id' => 102, 'subject_name' => 'Fiziologiya', 'retraining_status' => true]);

    $debts = app(StudentDebtService::class)->getDebtSubjects($student);

    expect($debts)->toHaveCount(2);
    expect($debts->pluck('subject_id')->all())->toEqualCanonicalizing([100, 102]);
});

it('returns empty collection when student has no debts', function () {
    $student = $this->makeStudent();
    $this->makeDebt($student, ['retraining_status' => false, 'finish_credit_status' => true]);

    $debts = app(StudentDebtService::class)->getDebtSubjects($student);

    expect($debts)->toHaveCount(0);
});

it('marks subject as ineligible if active application exists', function () {
    $student = $this->makeStudent();
    $this->makeDebt($student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'semester_id' => '7']);
    $period = $this->makePeriod($student);

    // Mavjud aktiv ariza (pending)
    \App\Models\RetakeApplication::create([
        'application_group_id' => \Illuminate\Support\Str::uuid(),
        'student_id' => $student->id,
        'subject_id' => 100,
        'subject_name' => 'Anatomiya',
        'semester_id' => 7,
        'semester_name' => '1-semestr',
        'credit' => 6.00,
        'period_id' => $period->id,
        'receipt_path' => 'fake.pdf',
        'receipt_original_name' => 'fake.pdf',
        'receipt_size' => 100,
        'receipt_mime' => 'application/pdf',
        'submitted_at' => now(),
    ]);

    $debts = app(StudentDebtService::class)->getDebtSubjects($student);

    expect($debts->first()['is_eligible_for_new'])->toBeFalse();
    expect($debts->first()['application_status'])->toBe('pending_dean_registrar');
});
