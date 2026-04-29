<?php

namespace Tests\Feature\Retake;

use App\Models\RetakeApplication;
use App\Services\Retake\RetakeApplicationService;
use App\Services\Retake\RetakeApprovalService;
use App\Services\Retake\RetakeGroupService;
use Illuminate\Support\Carbon;

beforeEach(function () {
    // To'liq approved oqim — verification_code generatsiya qilinishi uchun
    $this->student = $this->makeStudent();
    $this->period = $this->makePeriod($this->student);
    $this->makeDebt($this->student, ['subject_id' => 100, 'subject_name' => 'Anatomiya', 'semester_id' => '7']);

    $apps = app(RetakeApplicationService::class)->submit(
        $this->student, [['subject_id' => 100, 'semester_id' => 7]], $this->makeReceiptFile(),
    );
    $this->application = $apps->first();

    $dean = $this->makeTeacher(role: 'dekan', facultyIds: [$this->student->department_id]);
    $registrar = $this->makeTeacher(role: 'registrator_ofisi');
    $academic = $this->makeTeacher(role: 'oquv_bolimi');
    $teacher = $this->makeTeacher(role: 'oqituvchi');

    $apprSvc = app(RetakeApprovalService::class);
    $apprSvc->approveAsDean($dean, $this->application);
    $apprSvc->approveAsRegistrar($registrar, $this->application->fresh());

    // Tasdiqnoma generatsiyasi DomPDF + Storage'ni talab qiladi —
    // markApprovedByAcademicDept() avtomatik chaqiriladi, lekin xato bo'lsa
    // verification_code baribir saqlanadi.
    app(RetakeGroupService::class)->createAndAssign(
        $academic, 'Anatomiya — qayta o\'qish', 100, 'Anatomiya', 7, '1-semestr',
        Carbon::today()->addDays(10), Carbon::today()->addDays(40),
        $teacher->id, 30, [$this->application->id],
    );

    $this->application = $this->application->fresh();
});

it('returns success page for valid verification code', function () {
    expect($this->application->verification_code)->not->toBeNull();

    $response = $this->get('/verify/' . $this->application->verification_code);

    $response->assertOk();
    $response->assertSee('Tasdiqlangan ariza');
    $response->assertSee($this->student->full_name);
    $response->assertSee('Anatomiya');
});

it('does not show sensitive info on public page', function () {
    $response = $this->get('/verify/' . $this->application->verification_code);

    // Public sahifada bo'lmasligi kerak
    $response->assertDontSee('Kvitansiya');
    $response->assertDontSee('Audit log');
    $response->assertDontSee('Rejection reason');
});

it('returns not-found page for invalid code', function () {
    $response = $this->get('/verify/00000000-0000-0000-0000-000000000000');

    $response->assertOk();
    $response->assertSee('Tasdiqnoma topilmadi');
});

it('returns not-found for non-approved application', function () {
    // Boshqa ariza yarataman, verification_code yo'q (approved emas)
    $student2 = $this->makeStudent();
    $this->makeDebt($student2, ['subject_id' => 200, 'semester_id' => '7']);
    $period2 = $this->makePeriod($student2);

    $app = RetakeApplication::create([
        'application_group_id' => \Illuminate\Support\Str::uuid(),
        'student_id' => $student2->id,
        'subject_id' => 200,
        'subject_name' => 'Test',
        'semester_id' => 7,
        'semester_name' => '1-semestr',
        'credit' => 5.0,
        'period_id' => $period2->id,
        'receipt_path' => 'fake.pdf',
        'receipt_original_name' => 'fake.pdf',
        'receipt_size' => 100,
        'receipt_mime' => 'application/pdf',
        'verification_code' => (string) \Illuminate\Support\Str::uuid(),
        'submitted_at' => now(),
        'academic_dept_status' => 'pending', // approved emas!
    ]);

    $response = $this->get('/verify/' . $app->verification_code);

    $response->assertOk();
    $response->assertSee('Tasdiqnoma topilmadi');
});
