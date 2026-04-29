<?php

namespace Tests\Feature\Retake;

use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeApprovalService;
use App\Services\Retake\RetakeGroupService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->student = $this->makeStudent();
    $this->period = $this->makePeriod($this->student);
    $this->makeDebt($this->student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'semester_id' => '7']);

    $apps = app(RetakeApplicationService::class)->submit(
        $this->student, [['subject_id' => 100, 'semester_id' => 7]], $this->makeReceiptFile(),
    );
    $this->application = $apps->first();

    $this->dean = $this->makeTeacher(role: 'dekan', facultyIds: [$this->student->department_id]);
    $this->registrar = $this->makeTeacher(role: 'registrator_ofisi');
    $this->academic = $this->makeTeacher(role: 'oquv_bolimi');
    $this->teacher = $this->makeTeacher(role: 'oqituvchi');

    // Dekan + Registrator approved (academic_dept_status -> pending)
    $apprSvc = app(RetakeApprovalService::class);
    $apprSvc->approveAsDean($this->dean, $this->application);
    $apprSvc->approveAsRegistrar($this->registrar, $this->application->fresh());
    $this->application = $this->application->fresh();
});

it('creates group and approves applications with verification_code', function () {
    $group = app(RetakeGroupService::class)->createAndAssign(
        $this->academic,
        'Anatomiya — qayta o\'qish 2025',
        100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        $this->teacher->id, 30, [$this->application->id],
    );

    expect($group->name)->toContain('Anatomiya');
    $app = $this->application->fresh();
    expect($app->academic_dept_status->value)->toBe('approved');
    expect($app->retake_group_id)->toBe($group->id);
    expect($app->verification_code)->not->toBeNull();
});

it('rejects application_ids which are not pending academic_dept', function () {
    // Bu ariza hali "pending" emas (faqat dean approved)
    $student2 = $this->makeStudent();
    $this->makeDebt($student2, ['subject_id' => 100, 'semester_id' => '7']);
    $apps = app(RetakeApplicationService::class)->submit(
        $student2, [['subject_id' => 100, 'semester_id' => 7]], $this->makeReceiptFile(),
    );

    expect(fn () => app(RetakeGroupService::class)->createAndAssign(
        $this->academic, 'Test', 100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        $this->teacher->id, 30, [$apps->first()->id],
    ))->toThrow(ValidationException::class);
});

it('rejects mismatched subject_id between group and applications', function () {
    expect(fn () => app(RetakeGroupService::class)->createAndAssign(
        $this->academic, 'Test', 999, 'Mismatch', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        $this->teacher->id, 30, [$this->application->id],
    ))->toThrow(ValidationException::class);
});

it('rejects nonexistent teacher_id', function () {
    expect(fn () => app(RetakeGroupService::class)->createAndAssign(
        $this->academic, 'Test', 100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        99999, 30, [$this->application->id],
    ))->toThrow(ValidationException::class);
});

it('rejects empty application_ids', function () {
    expect(fn () => app(RetakeGroupService::class)->createAndAssign(
        $this->academic, 'Test', 100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        $this->teacher->id, 30, [],
    ))->toThrow(ValidationException::class);
});

it('rejects end_date before start_date', function () {
    expect(fn () => app(RetakeGroupService::class)->createAndAssign(
        $this->academic, 'Test', 100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(40), Carbon::today()->addDays(10),
        $this->teacher->id, 30, [$this->application->id],
    ))->toThrow(ValidationException::class);
});
