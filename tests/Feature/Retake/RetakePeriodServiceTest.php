<?php

namespace Tests\Feature\Retake;

use App\Models\RetakeApplicationPeriod;
use App\Services\Retake\RetakePeriodService;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

it('creates a period for specialty + course + semester', function () {
    $teacher = $this->makeTeacher(role: 'oquv_bolimi');

    $period = app(RetakePeriodService::class)->create(
        $teacher, 12, 1, 7,
        Carbon::today()->addDay(), Carbon::today()->addDays(10),
    );

    expect($period)->toBeInstanceOf(RetakeApplicationPeriod::class);
    expect($period->specialty_id)->toBe(12);
});

it('rejects duplicate period for same specialty/course/semester', function () {
    $teacher = $this->makeTeacher(role: 'oquv_bolimi');
    $svc = app(RetakePeriodService::class);

    $svc->create($teacher, 12, 1, 7, Carbon::today()->addDay(), Carbon::today()->addDays(10));

    expect(fn () => $svc->create($teacher, 12, 1, 7, Carbon::today()->addDays(20), Carbon::today()->addDays(30)))
        ->toThrow(ValidationException::class);
});

it('rejects end_date before start_date', function () {
    $teacher = $this->makeTeacher(role: 'oquv_bolimi');

    expect(fn () => app(RetakePeriodService::class)->create(
        $teacher, 12, 1, 7,
        Carbon::today()->addDays(10), Carbon::today()->addDay(),
    ))->toThrow(ValidationException::class);
});

it('refuses overrideDates for non-superadmin', function () {
    $teacher = $this->makeTeacher(role: 'oquv_bolimi');
    $svc = app(RetakePeriodService::class);
    $period = $svc->create($teacher, 12, 1, 7, Carbon::today()->addDay(), Carbon::today()->addDays(10));

    expect(fn () => $svc->overrideDates($teacher, $period, Carbon::today()->addDays(2), Carbon::today()->addDays(5)))
        ->toThrow(ValidationException::class);
});

it('allows overrideDates for superadmin', function () {
    $teacher = $this->makeTeacher(role: 'oquv_bolimi');
    $admin = $this->makeTeacher(role: 'superadmin');
    $svc = app(RetakePeriodService::class);
    $period = $svc->create($teacher, 12, 1, 7, Carbon::today()->addDay(), Carbon::today()->addDays(10));

    $updated = $svc->overrideDates($admin, $period, Carbon::today()->addDays(5), Carbon::today()->addDays(15));

    expect($updated->start_date->toDateString())->toBe(Carbon::today()->addDays(5)->toDateString());
});

it('finds active period for student', function () {
    $student = $this->makeStudent();
    $this->makePeriod($student); // bugun + 7 kun ichida

    $period = app(RetakePeriodService::class)->findActiveForStudent($student);

    expect($period)->not->toBeNull();
    expect($period->is_active)->toBeTrue();
});
