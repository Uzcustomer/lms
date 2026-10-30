<?php

use App\Http\Controllers\Admin\JournalController;
use App\Http\Controllers\Admin\VedomostTekshirishController;
use App\Services\VedomostTekshirishService;
use App\Services\YnAttemptStatusService;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * Vedomost tekshirish Excel: eski jadval tuzilmasi, har shakl alohida varaqda —
 * 12-shakl tekshiruv hisobidan, qo'shimcha / 12a / 12b jurnal qaydnomasidan.
 */

uses(Tests\TestCase::class);

function vtStudent(int $hemisId, string $name): object
{
    return (object) ['hemis_id' => $hemisId, 'full_name' => $name, 'student_id_number' => '3682' . $hemisId];
}

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
    Schema::create('hemis_exam_grades', function ($t) {
        $t->id();
        $t->unsignedBigInteger('student_hemis_id');
        $t->string('subject_id');
        $t->string('semester_code');
        $t->string('exam_type_code');
        $t->double('grade')->nullable();
        $t->timestamps();
    });
    DB::table('groups')->insert([
        ['id' => 1, 'group_hemis_id' => '101', 'name' => 'd1/d22-02c (rus)'],
        ['id' => 2, 'group_hemis_id' => '102', 'name' => 'd1/d22-03c (rus)'],
    ]);
    DB::table('hemis_exam_grades')->insert(['student_hemis_id' => 1, 'subject_id' => '700', 'semester_code' => '11', 'exam_type_code' => '11', 'grade' => 56]);

    // Tekshiruv hisobi: guruh 101 — talaba 1, 2; guruh 102 — talaba 3
    app()->bind(VedomostTekshirishService::class, fn () => new class extends VedomostTekshirishService {
        public function collect($group, string $subjectId, string $semesterCode): ?array
        {
            $students = $group->group_hemis_id === '101'
                ? collect([vtStudent(1, 'Aliyev A'), vtStudent(2, 'Boboyev B')])
                : collect([vtStudent(3, 'Valiyev V')]);
            $raw = [];
            foreach ($students as $s) {
                $raw[$s->hemis_id] = ['jn' => 80, 'mt' => 80, 'on' => 0, 'oski' => 0, 'test' => 50, 'dav' => 4.0, 'jnCount' => 12];
            }

            return [
                'groupHemisId' => $group->group_hemis_id, 'curriculum' => null, 'educationYearCode' => '2026',
                'educationYearName' => '2026-2027', 'subject' => (object) ['subject_name' => 'Pediatriya', 'credit' => 4],
                'semester' => (object) ['name' => '11-semestr', 'level_code' => '16'], 'students' => $students,
                'studentHemisIds' => $students->pluck('hemis_id')->all(), 'raw' => $raw,
                'lectureTeacher' => 'Ma\'ruzachi', 'practiceTeacher' => 'Amaliyotchi', 'totalHours' => 120, 'divisor' => 12,
            ];
        }
    });

    // Jurnal shakllari: 101 da talaba 2 — 12a (test 75), 102 da talaba 3 — 12-qo'shimcha (test 90)
    app()->bind(JournalController::class, fn () => new class extends JournalController {
        public function __construct()
        {
        }

        public function ynQaydnomaForms(
            string $subjectId, string $semesterCode, string $groupHemisId,
            int $weightJn, int $weightMt, int $weightOn, int $weightOski, int $weightTest
        ): array|string {
            $forms = [YnAttemptStatusService::FORM_12 => ['students' => collect(), 'jn' => [], 'mt' => [], 'other' => []]];
            if ($groupHemisId === '101') {
                $forms[YnAttemptStatusService::FORM_12A] = [
                    'students' => collect([vtStudent(2, 'Boboyev B')]),
                    'jn' => [2 => 80], 'mt' => [2 => 80], 'other' => [100 => [], 101 => [], 102 => [2 => 75]],
                ];
            } else {
                $forms[YnAttemptStatusService::FORM_12_QOSHIMCHA] = [
                    'students' => collect([vtStudent(3, 'Valiyev V')]),
                    'jn' => [3 => 85], 'mt' => [3 => 80], 'other' => [100 => [], 101 => [], 102 => [3 => 90]],
                ];
            }

            return ['forms' => $forms];
        }
    });

    $this->actingAs(new class extends User {
        public function hasAnyRole($roles): bool
        {
            return true;
        }
    });
});

it('writes each form on its own sheet in the check-table layout', function () {
    $request = Request::create('/admin/vedomost-tekshirish/export', 'POST', [
        'rows' => [
            ['group_id' => 1, 'subject_id' => '700', 'semester_code' => '11'],
            ['group_id' => 2, 'subject_id' => '700', 'semester_code' => '11'],
        ],
        'weight_jn' => 50, 'weight_mt' => 20, 'weight_on' => 0, 'weight_oski' => 0, 'weight_test' => 30,
    ]);
    $response = app(VedomostTekshirishController::class)->export($request);

    ob_start();
    $response->sendContent();
    $path = tempnam(sys_get_temp_dir(), 'vt') . '.xlsx';
    file_put_contents($path, ob_get_clean());
    $book = IOFactory::load($path);
    @unlink($path);

    $sheets = [];
    foreach ($book->getAllSheets() as $ws) {
        $rows = [];
        for ($r = 2; $ws->getCell("B{$r}")->getValue() !== null; $r++) {
            $rows[] = [
                'fio' => $ws->getCell("B{$r}")->getValue(), 'test' => $ws->getCell("S{$r}")->getValue(),
                'yn' => $ws->getCell("V{$r}")->getValue(), 'shakl' => $ws->getCell("AC{$r}")->getValue(),
                'group' => $ws->getCell("AG{$r}")->getValue(), 'check' => $ws->getCell("AU{$r}")->getValue(),
            ];
        }
        $sheets[$ws->getTitle()] = ['header' => $ws->getCell('AC1')->getValue(), 'rows' => $rows];
    }

    $q = "12-shakl(qo\u{2018}shimcha)";
    expect(array_keys($sheets))->toBe(['12-shakl', $q, '12a-shakl'])
        ->and($sheets['12-shakl']['header'])->toBe('Qaydnoma turi')
        ->and(array_column($sheets['12-shakl']['rows'], 'fio'))->toBe(['Aliyev A', 'Boboyev B', 'Valiyev V'])
        ->and($sheets['12-shakl']['rows'][1])->toMatchArray(['test' => 50, 'shakl' => '12-shakl', 'check' => "Hemisda yo'q"])
        ->and($sheets['12a-shakl']['rows'])->toEqual([
            ['fio' => 'Boboyev B', 'test' => 75, 'yn' => 79, 'shakl' => '12a-shakl', 'group' => 'd1/d22-02c (rus)', 'check' => null],
        ])
        ->and($sheets[$q]['rows'][0])->toMatchArray(['fio' => 'Valiyev V', 'test' => 90, 'shakl' => $q, 'group' => 'd1/d22-03c (rus)']);
});
