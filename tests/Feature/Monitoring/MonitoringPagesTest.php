<?php

require_once __DIR__ . '/helpers.php';

use App\Jobs\RunMonitoringDetectorJob;
use App\Models\ActivityLog;
use App\Models\MonitoringDetector;
use App\Models\MonitoringNoticeLog;
use App\Models\TeacherNotification;
use Illuminate\Support\Facades\Queue;

/*
 * Sahifalar ochiladi; detektor sozlamasi saqlanadi va audit'ga yoziladi; E-1 xabari yuboriladi.
 */

beforeEach(function () {
    $this->withoutVite(); // testlarda frontend build (public/build) yo'q
    $this->admin = monUser(['monitoring_admin']);
});

test('monitoring sahifalari administratorga ochiladi', function (string $route, array $params) {
    $params = array_map(fn ($p) => $p === 'D-2' ? MonitoringDetector::where('code', 'D-2')->value('id') : $p, $params);

    $this->actingAs($this->admin, 'web')->get(route($route, $params))->assertOk();
})->with([
    ['admin.monitoring.index', []],
    ['admin.monitoring.istisnolar.index', []],
    ['admin.monitoring.mine', []],
    ['admin.monitoring.detektorlar.index', []],
    ['admin.monitoring.detektorlar.edit', ['D-2']],
    ['admin.monitoring.importlar.index', []],
    ['admin.monitoring.buyruqlar.index', []],
    ['admin.monitoring.tavsiyalar.index', []],
    ['admin.monitoring.yigmajild.index', []],
]);

test('detektor parametri paneldan saqlanadi va audit jurnaliga yoziladi', function () {
    $detector = MonitoringDetector::where('code', 'D-2')->firstOrFail();

    $this->actingAs($this->admin, 'web')->put(route('admin.monitoring.detektorlar.update', $detector), [
        'enabled' => 1,
        'due_workdays' => 5,
        'severity' => 'yuqori',
        'params' => [
            'threshold_hours' => 80,
            'exclude_medical' => '0',
            'medical_reasons' => 'kasallik, homiladorlik',
            'excluded_training_types' => '',
        ],
    ])->assertRedirect();

    $detector->refresh();
    expect($detector->params['threshold_hours'])->toBe(80)
        ->and($detector->params['exclude_medical'])->toBeFalse()
        ->and($detector->params['medical_reasons'])->toBe(['kasallik', 'homiladorlik'])
        ->and($detector->due_workdays)->toBe(5)
        ->and(ActivityLog::where('module', 'monitoring_detector')->where('subject_id', $detector->id)->exists())->toBeTrue();
});

test('detektor sozlamasini faqat monitoring administratori o\'zgartiradi', function () {
    $detector = MonitoringDetector::where('code', 'D-2')->firstOrFail();
    $registrar = monTeacher(['registrator_ofisi']);

    $this->actingAs($registrar, 'teacher')->get(route('admin.monitoring.detektorlar.edit', $detector))->assertForbidden();
    $this->actingAs($registrar, 'teacher')->put(route('admin.monitoring.detektorlar.update', $detector), [
        'due_workdays' => 1, 'severity' => 'past', 'params' => ['threshold_hours' => 1],
    ])->assertForbidden();

    expect($detector->fresh()->params['threshold_hours'])->toBe(74);
});

test('«Hozir ishga tushir» detektorni navbatga qo\'yadi', function () {
    Queue::fake();
    $detector = MonitoringDetector::where('code', 'D-2')->firstOrFail();

    $this->actingAs($this->admin, 'web')->post(route('admin.monitoring.detektorlar.run', $detector))->assertRedirect();

    Queue::assertPushed(RunMonitoringDetectorJob::class, fn ($job) => $job->detectorId === $detector->id);
});

test('E-1: «Xabar yuborish» ijara kotibi va buxgalteriyaga xabar beradi va qayd etadi', function () {
    $secretary = monTeacher(['oqituvchi']);
    $accountant = monTeacher(['buxgalteriya']);
    monDetector('E-1', ['recipient_ijara_kotibi' => 'teacher:' . $secretary->id]);
    $order = monOrder(['order_type' => 'akademik_tatil']);
    $registrar = monTeacher(['registrator_ofisi']);

    $this->actingAs($registrar, 'teacher')
        ->post(route('admin.monitoring.buyruqlar.send-notice', $order), ['kinds' => ['ijara_kotibi', 'buxgalteriya']])
        ->assertRedirect()
        ->assertSessionHas('success');

    expect(MonitoringNoticeLog::where('order_id', $order->id)->pluck('recipient_kind')->sort()->values()->all())
        ->toBe(['buxgalteriya', 'ijara_kotibi'])
        ->and(TeacherNotification::whereIn('teacher_id', [$secretary->id, $accountant->id])->count())->toBe(2);
});

test('ma\'lumot bor holatda panel, ro\'yxat va kartochka ochiladi; dalil fayli yuklanadi va beriladi', function () {
    \Illuminate\Support\Facades\Storage::fake('local');
    $faculty = monFaculty();
    $dean = monDean($faculty);
    $student = monStudent(['department_id' => $faculty]);
    monAbsence($student, 90);
    app(\App\Services\Monitoring\DetectorRunner::class)->run(MonitoringDetector::where('code', 'D-2')->first());
    $istisno = \App\Models\MonitoringIstisno::sole();

    $this->actingAs($dean, 'teacher')->post(route('admin.monitoring.istisnolar.respond', $istisno), [
        'body' => 'Chetlashtirish buyrug\'i loyihasi',
        'files' => [\Illuminate\Http\UploadedFile::fake()->create('buyruq.pdf', 50, 'application/pdf')],
    ])->assertRedirect();
    $evidence = $istisno->evidences()->sole();

    // Avval xodim (teacher guard) so'rovlari: admin (web guard) kirgach AdminMultiGuardAuth web'ni afzal ko'radi.
    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.mine'))->assertOk()->assertSee($student->full_name);
    $this->actingAs($dean, 'teacher')->get(route('admin.monitoring.istisnolar.evidence', [$istisno, $evidence]))->assertOk();

    $stranger = monTeacher(['dekan']);
    $this->actingAs($stranger, 'teacher')->get(route('admin.monitoring.istisnolar.evidence', [$istisno, $evidence]))->assertForbidden();

    foreach (['admin.monitoring.index', 'admin.monitoring.istisnolar.index'] as $route) {
        $this->actingAs($this->admin, 'web')->get(route($route))->assertOk()->assertSee('D-2');
    }
    $this->actingAs($this->admin, 'web')->get(route('admin.monitoring.istisnolar.show', $istisno))
        ->assertOk()->assertSee($student->full_name)->assertSee('buyruq.pdf');
});
