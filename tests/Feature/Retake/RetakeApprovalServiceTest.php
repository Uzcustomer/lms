<?php

namespace Tests\Feature\Retake;

use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeApprovalService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->student = $this->makeStudent();
    $this->period = $this->makePeriod($this->student);
    $this->makeDebt($this->student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'semester_id' => '7']);

    $apps = app(RetakeApplicationService::class)->submit(
        $this->student,
        [['subject_id' => 100, 'semester_id' => 7]],
        $this->makeReceiptFile(),
    );
    $this->application = $apps->first();

    $this->dean = $this->makeTeacher(role: 'dekan', facultyIds: [$this->student->department_id]);
    $this->registrar = $this->makeTeacher(role: 'registrator_ofisi');
    $this->academic = $this->makeTeacher(role: 'oquv_bolimi');
});

it('dean can approve application of own faculty student', function () {
    $svc = app(RetakeApprovalService::class);
    $app = $svc->approveAsDean($this->dean, $this->application);

    expect($app->dean_status->value)->toBe('approved');
    expect($app->dean_reviewed_by)->toBe($this->dean->id);
    expect($app->academic_dept_status->value)->toBe('not_started'); // Registrator hali approved emas
});

it('dean cannot approve application of different faculty', function () {
    $other = $this->makeTeacher(role: 'dekan', facultyIds: [999]);

    expect(fn () => app(RetakeApprovalService::class)->approveAsDean($other, $this->application))
        ->toThrow(ValidationException::class);
});

it('dean rejection requires reason min 10 chars', function () {
    expect(fn () => app(RetakeApprovalService::class)->rejectAsDean($this->dean, $this->application, 'short'))
        ->toThrow(ValidationException::class);
});

it('dean rejection with proper reason finalizes application', function () {
    $app = app(RetakeApprovalService::class)->rejectAsDean(
        $this->dean, $this->application, 'Bu yetarli darajada asoslanmagan ariza',
    );

    expect($app->dean_status->value)->toBe('rejected');
    expect($app->final_status)->toBe('rejected');
});

it('parallel approval: both dean and registrar approve advances to academic_dept', function () {
    $svc = app(RetakeApprovalService::class);

    $svc->approveAsDean($this->dean, $this->application);
    $app = $svc->approveAsRegistrar($this->registrar, $this->application->fresh());

    expect($app->dean_status->value)->toBe('approved');
    expect($app->registrar_status->value)->toBe('approved');
    expect($app->academic_dept_status->value)->toBe('pending');
});

it('parallel approval: registrar approve does not advance if dean still pending', function () {
    $svc = app(RetakeApprovalService::class);

    $app = $svc->approveAsRegistrar($this->registrar, $this->application);

    expect($app->registrar_status->value)->toBe('approved');
    expect($app->dean_status->value)->toBe('pending');
    expect($app->academic_dept_status->value)->toBe('not_started');
});

it('rejected by dean blocks any further action', function () {
    $svc = app(RetakeApprovalService::class);
    $svc->rejectAsDean($this->dean, $this->application, 'Sabab uchun yetarli matn yozildi');

    expect(fn () => $svc->approveAsRegistrar($this->registrar, $this->application->fresh()))
        ->toThrow(ValidationException::class);
});

it('non-dean role cannot use approveAsDean', function () {
    expect(fn () => app(RetakeApprovalService::class)->approveAsDean($this->registrar, $this->application))
        ->toThrow(ValidationException::class);
});

it('rejectAsAcademicDept fails if not yet pending', function () {
    expect(fn () => app(RetakeApprovalService::class)->rejectAsAcademicDept(
        $this->academic, $this->application, 'Yetarli darajada sabab yozildi',
    ))->toThrow(ValidationException::class);
});
