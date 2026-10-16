<?php

require_once __DIR__ . '/helpers.php';

use App\Models\MonitoringAdmissionRecommendation;
use App\Models\MonitoringDataImport;
use App\Models\MonitoringOrder;
use App\Models\MonitoringTransferChecklist;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/*
 * Excel importi: yuklash → ustunlarni moslash → import; qayta yuklashda dublikat yo'q.
 */

beforeEach(function () {
    $this->withoutVite(); // testlarda frontend build (public/build) yo'q
    Storage::fake('local');
    $this->registrar = monTeacher(['registrator_ofisi']);
});

function monXlsx(array $rows): UploadedFile
{
    $spreadsheet = new Spreadsheet();
    $spreadsheet->getActiveSheet()->fromArray($rows, null, 'A1', true);
    $path = tempnam(sys_get_temp_dir(), 'mon') . '.xlsx';
    (new Xlsx($spreadsheet))->save($path);

    return new UploadedFile($path, 'sinov.xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', null, true);
}

function monImportOrders($test, UploadedFile $file): MonitoringDataImport
{
    $test->actingAs($test->registrar, 'teacher')
        ->post(route('admin.monitoring.importlar.upload'), ['kind' => 'buyruqlar', 'file' => $file])
        ->assertRedirect();
    $import = MonitoringDataImport::latest('id')->first();

    $test->actingAs($test->registrar, 'teacher')->get(route('admin.monitoring.importlar.mapping', $import))->assertOk();
    $test->actingAs($test->registrar, 'teacher')->post(route('admin.monitoring.importlar.process', $import), [
        'map' => ['order_number' => 0, 'order_date' => 1, 'order_type_raw' => 2, 'student_id_number' => 3, 'full_name' => 4],
    ])->assertRedirect();

    return $import->fresh();
}

test('buyruqlar importi turlarni aniqlaydi, talabani bog\'laydi va ko\'chirib kelganga cheklist ochadi', function () {
    $student = monStudent(['student_id_number' => 'ST-IMP-1']);
    $rows = [
        ['Buyruq raqami', 'Sana', 'Buyruq turi', 'Talaba ID', 'F.I.Sh.'],
        ['10-01', '12.10.2026', "Akademik ta'til berish", 'ST-IMP-1', $student->full_name],
        ['10-02', '13.10.2026', "Boshqa OTMdan ko'chirib kelish", '', 'Sinov Ko\'chgan'],
        ['', '13.10.2026', 'Chetlashtirish', '', 'Raqamsiz'],
    ];

    $import = monImportOrders($this, monXlsx($rows));

    expect($import->status)->toBe('bajarildi')
        ->and($import->rows_ok)->toBe(2)
        ->and($import->rows_error)->toBe(1);
    $leave = MonitoringOrder::where('order_number', '10-01')->sole();
    expect($leave->order_type)->toBe('akademik_tatil')
        ->and((int) $leave->student_hemis_id)->toBe((int) $student->hemis_id)
        ->and($leave->order_date->toDateString())->toBe('2026-10-12')
        ->and(MonitoringOrder::where('order_number', '10-02')->sole()->order_type)->toBe('kochirib_kelish')
        ->and(MonitoringTransferChecklist::count())->toBe(1);

    // Qayta yuklash — dublikat yo'q
    monImportOrders($this, monXlsx($rows));
    expect(MonitoringOrder::count())->toBe(2)->and(MonitoringTransferChecklist::count())->toBe(1);
});

test('mandat importi pasport bo\'yicha talabaga bog\'laydi va kontrakt turini aniqlaydi', function () {
    $student = monStudent(['passport_serial' => 'AB', 'passport_number' => '1234567', 'level_code' => '11']);
    $file = monXlsx([
        ['F.I.Sh.', 'Abituriyent ID', 'Pasport', 'Yo\'nalish', 'Kontrakt turi'],
        [$student->full_name, '3000001', 'AB 1234567', 'Davolash ishi', "Qo'shimcha to'lov-kontrakt"],
        ['Topilmaydigan Abituriyent', '3000002', '', 'Pediatriya', "To'lov-kontrakt"],
    ]);

    $this->actingAs($this->registrar, 'teacher')
        ->post(route('admin.monitoring.importlar.upload'), ['kind' => 'tavsiya_etilganlar', 'file' => $file, 'education_year' => '2026-2027'])
        ->assertRedirect();
    $import = MonitoringDataImport::latest('id')->first();
    $this->actingAs($this->registrar, 'teacher')->post(route('admin.monitoring.importlar.process', $import), [
        'map' => ['full_name' => 0, 'applicant_id' => 1, 'passport' => 2, 'specialty_name' => 3, 'contract_type_raw' => 4],
    ])->assertRedirect();

    $matched = MonitoringAdmissionRecommendation::where('applicant_id', '3000001')->sole();
    expect((int) $matched->student_hemis_id)->toBe((int) $student->hemis_id)
        ->and($matched->match_method)->toBe('passport')
        ->and($matched->match_confirmed)->toBeTrue()
        ->and($matched->contract_type)->toBe('qoshimcha')
        ->and(MonitoringAdmissionRecommendation::where('applicant_id', '3000002')->sole()->student_hemis_id)->toBeNull();
});

test('oddiy xodim import qila olmaydi', function () {
    $teacher = monTeacher(['oqituvchi']);

    $this->actingAs($teacher, 'teacher')
        ->post(route('admin.monitoring.importlar.upload'), ['kind' => 'buyruqlar', 'file' => monXlsx([['a'], ['b']])])
        ->assertForbidden();
});
