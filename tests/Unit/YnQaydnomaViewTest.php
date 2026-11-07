<?php

use App\Http\Controllers\Admin\JournalController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * YN qaydnoma sahifada: workbook (barcha varaqlar) HTML ga aylanadi — butun kenglik bo'yicha
 * birlashtirilgan sarlavha qatorlari Laravel'da xato bermaydi; amaliy o'qituvchi ma'ruzachidan ajratiladi.
 */

uses(Tests\TestCase::class);

it('renders every sheet of the qaydnoma workbook as a page without errors', function () {
    $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $w = $s->getActiveSheet()->setTitle('d1_d22-01b (rus)');
    $w->setCellValue('A1', 'BAHOLASH QAYDNOMASI')->mergeCells('A1:D1');   // butun kenglik — kutubxonada ogohlantirish
    $w->fromArray([[1, 'ABDULLAYEV', 96.0, 'a\'lo']], null, 'A2');
    $s->createSheet()->setTitle('12a-shakl')->setCellValue('A1', '12a');

    $html = JournalController::qaydnomaHtml($s, 'YN qaydnoma <d1/d22-01b>');

    expect($html)
        ->toContain('colspan="4"')
        ->toContain('ABDULLAYEV')
        ->toContain('12a')
        ->toContain('Chop etish')
        ->toContain('<title>YN qaydnoma &lt;d1/d22-01b&gt;</title>');
});

it('treats only non-lecture teachers of the group and subject as practical teachers', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(), $t->rememberToken(), $t->timestamps()]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('employee_id'), $t->unsignedBigInteger('subject_id'),
        $t->unsignedBigInteger('group_id'), $t->string('training_type_code'), $t->softDeletes()]);
    Schema::create('curriculum_subject_teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('employee_id'), $t->unsignedBigInteger('subject_id'),
        $t->unsignedBigInteger('group_id'), $t->string('training_type_code')->nullable(), $t->timestamps()]);
    DB::table('teachers')->insert([['id' => 1, 'hemis_id' => 501], ['id' => 2, 'hemis_id' => 502]]);
    DB::table('schedules')->insert([
        ['employee_id' => 501, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '13'],  // amaliy
        ['employee_id' => 502, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '11'],  // ma'ruza
    ]);

    $check = fn (int $teacherId) => (function () use ($teacherId) {
        Auth::guard('teacher')->login(\App\Models\Teacher::find($teacherId));

        return (new ReflectionMethod(JournalController::class, 'isAmaliyTeacherFor'))
            ->invoke(app(JournalController::class), '435', '77');
    })();

    expect($check(1))->toBeTrue()
        ->and($check(2))->toBeFalse();
});
