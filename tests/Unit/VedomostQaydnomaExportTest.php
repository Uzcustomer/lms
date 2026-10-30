<?php

use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\VedomostTekshirishController;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;

/**
 * Vedomost tekshirish → YN qaydnoma: tanlangan har (guruh, fan) uchun jurnal
 * qaydnomasining barcha shakl varaqlari bitta faylga yig'iladi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');
    Schema::create('groups', function ($t) {
        $t->id();
        $t->string('group_hemis_id');
        $t->string('name');
        $t->timestamps();
    });
    DB::table('groups')->insert([
        ['id' => 1, 'group_hemis_id' => '101', 'name' => 'd1/d22-02c (rus)'],
        ['id' => 2, 'group_hemis_id' => '102', 'name' => 'd1/d22-03c (rus)'],
    ]);

    // Jurnal qaydnomasi o'rniga: guruh nomi bilan 12 / 12a / 12b varaqlari
    app()->bind(JournalController::class, fn () => new class extends JournalController {
        public function __construct()
        {
        }

        public function buildYnQaydnomaSpreadsheet(
            string $subjectId, string $semesterCode, string $groupHemisId,
            int $weightJn, int $weightMt, int $weightOn, int $weightOski, int $weightTest, int $shaklId = 1
        ): array|string {
            if ($subjectId === 'yoq') {
                return 'Fan topilmadi';
            }
            $name = $groupHemisId === '101' ? 'd1_d22-02c (rus)' : 'd1_d22-03c (rus)';
            $book = new Spreadsheet();
            $book->getActiveSheet()->setTitle($name)->setCellValue('A1', "12:{$groupHemisId}:{$subjectId}");
            foreach (['12a', '12b'] as $form) {
                $book->createSheet()->setTitle("{$name} {$form}")->setCellValue('A1', "{$form}:{$groupHemisId}:{$subjectId}");
            }

            return [$book, "YN_qaydnoma_{$groupHemisId}.xlsx"];
        }
    });

    $this->actingAs(new class extends User {
        public function hasAnyRole($roles): bool
        {
            return true;
        }
    });
});

function qaydnomaExport(array $rows): array
{
    $request = Request::create('/admin/vedomost-tekshirish/export-qaydnoma', 'POST', [
        'rows' => $rows, 'weight_jn' => 50, 'weight_mt' => 20, 'weight_on' => 0, 'weight_oski' => 0, 'weight_test' => 30,
    ]);
    $response = app(VedomostTekshirishController::class)->exportQaydnoma($request);

    ob_start();
    $response->sendContent();
    $path = tempnam(sys_get_temp_dir(), 'qd') . '.xlsx';
    file_put_contents($path, ob_get_clean());
    $book = IOFactory::load($path);
    $sheets = [];
    foreach ($book->getAllSheets() as $ws) {
        $sheets[$ws->getTitle()] = (string) $ws->getCell('A1')->getValue();
    }
    @unlink($path);

    return [$response, $sheets];
}

it('puts every form of every selected row into one workbook', function () {
    [$response, $sheets] = qaydnomaExport([
        ['group_id' => 1, 'subject_id' => '700', 'semester_code' => '11'],
        ['group_id' => 2, 'subject_id' => '700', 'semester_code' => '11'],
        ['group_id' => 1, 'subject_id' => '701', 'semester_code' => '11'],
    ]);

    expect($sheets)->toBe([
        '1. d1_d22-02c (rus)' => '12:101:700',
        '1. d1_d22-02c (rus) 12a' => '12a:101:700',
        '1. d1_d22-02c (rus) 12b' => '12b:101:700',
        '2. d1_d22-03c (rus)' => '12:102:700',
        '2. d1_d22-03c (rus) 12a' => '12a:102:700',
        '2. d1_d22-03c (rus) 12b' => '12b:102:700',
        '3. d1_d22-02c (rus)' => '12:101:701',
        '3. d1_d22-02c (rus) 12a' => '12a:101:701',
        '3. d1_d22-02c (rus) 12b' => '12b:101:701',
    ])->and($response->headers->get('content-disposition'))->toContain('YN_qaydnoma_');
});

it('keeps the journal sheet names and file name for a single row', function () {
    [$response, $sheets] = qaydnomaExport([['group_id' => 1, 'subject_id' => '700', 'semester_code' => '11']]);

    expect(array_keys($sheets))->toBe(['d1_d22-02c (rus)', 'd1_d22-02c (rus) 12a', 'd1_d22-02c (rus) 12b'])
        ->and($response->headers->get('content-disposition'))->toContain('YN_qaydnoma_101.xlsx');
});

it('lists rows that could not be built on a separate sheet', function () {
    [, $sheets] = qaydnomaExport([
        ['group_id' => 1, 'subject_id' => '700', 'semester_code' => '11'],
        ['group_id' => 2, 'subject_id' => 'yoq', 'semester_code' => '11'],
        ['group_id' => 99, 'subject_id' => '700', 'semester_code' => '11'],
    ]);

    expect($sheets)->toHaveKey("O'tkazib yuborilgan")
        ->and(count($sheets))->toBe(4);
});
