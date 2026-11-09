<?php

use App\Http\Controllers\Admin\JournalController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * YN qaydnoma sahifada: workbook chop etish maydoniga qirqilib HTML ga aylanadi (birlashtirilgan
 * sarlavhalar xato bermaydi); amaliy o'qituvchi tasdiqlasa unikal QR uning ismi yoniga bosiladi.
 */

uses(Tests\TestCase::class);

function qaydnomaSheet(): \PhpOffice\PhpSpreadsheet\Spreadsheet
{
    $s = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $w = $s->getActiveSheet()->setTitle('d1_d22-01b (rus)');
    $w->setCellValue('A1', 'BAHOLASH QAYDNOMASI')->mergeCells('A1:Y1');      // butun kenglik — kutubxonada ogohlantirish
    $w->setCellValue('U10', 'IZZATULLAYEV O.Sh.');
    $w->fromArray([[1, 'ABDULLAYEV', 96.0]], null, 'A20');
    foreach (range(2, 10) as $n) {                                             // 10 talaba: 20–29 qatorlar
        $w->fromArray([[$n, 'TALABA ' . $n, 80.0]], null, 'A' . (19 + $n));
    }
    $w->setCellValue('X15', "YAN o'tkazuvchi o'qituvchi imzosi");
    $w->getColumnDimension('X')->setWidth(28.81640625);
    // Kelmagan talaba qatori pushti — imzo katagiga o'tmasligi kerak
    $w->getStyle('A20:Y20')->getFill()->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)->getStartColor()->setRGB('FFC7CE');
    $w->setCellValue('B60', 'Fakultet dekani')->setCellValue('C61', 'imzo');
    $w->getStyle('C60')->getBorders()->getBottom()->setBorderStyle('thin');
    // Shablondagi shartli formatlash: kelmagan (V = -1) qator pushti
    $kelmadi = (new \PhpOffice\PhpSpreadsheet\Style\Conditional())->setConditionType('expression')->addCondition('$V20=-1');
    $kelmadi->getStyle()->getFill()->setFillType('solid')->getEndColor()->setRGB('FEC2F1');
    $w->setConditionalStyles('A20:Y49', [$kelmadi]);
    $w->setCellValue('M20', '=C20/2');
    $w->setCellValue('AB1', 'Topshiriq bilan');                               // chop etish maydonidan tashqari
    $w->getPageSetup()->setPrintArea('A1:Y61');
    $s->createSheet()->setTitle('12a-shakl')->setCellValue('A1', '12a');

    return $s;
}

it('renders the print area of every sheet as a page without errors', function () {
    $html = JournalController::qaydnomaHtml(qaydnomaSheet(), 'YN qaydnoma <d1/d22-01b>', '<button>X</button>');

    expect($html)
        ->toContain('colspan="25"')
        ->toContain('ABDULLAYEV')
        ->toContain('>48<')                       // formula hisoblangan
        ->toContain('12a')
        ->toContain('Chop etish')
        ->toContain('<button>X</button>')
        ->toContain('<title>YN qaydnoma &lt;d1/d22-01b&gt;</title>')
        ->not->toContain('Topshiriq bilan');      // Y dan keyingi yordamchi ustunlar chiqmaydi
});

describe('teacher confirmation', function () {
    beforeEach(function () {
        config()->set('database.default', 'sqlite');
        config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        DB::purge('sqlite');
        Storage::fake('public');
        Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(), $t->rememberToken(), $t->timestamps()]);
        Schema::create('roles', fn ($t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
        Schema::create('model_has_roles', fn ($t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
        Schema::create('activity_logs', fn ($t) => [$t->id(), $t->timestamps()]);
        Schema::create('schedules', fn ($t) => [$t->id(), $t->unsignedBigInteger('employee_id'), $t->unsignedBigInteger('subject_id'),
            $t->unsignedBigInteger('group_id'), $t->string('training_type_code'), $t->softDeletes()]);
        Schema::create('curriculum_subject_teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('employee_id'), $t->unsignedBigInteger('subject_id'),
            $t->unsignedBigInteger('group_id'), $t->string('training_type_code')->nullable(), $t->timestamps()]);
        Schema::create('yn_submissions', fn ($t) => [$t->id(), $t->string('subject_id'), $t->string('semester_code'), $t->string('group_hemis_id'), $t->timestamps()]);
        Schema::create('groups', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('name'), $t->string('curriculum_hemis_id')->nullable(),
            $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->string('department_name')->nullable(), $t->string('specialty_name')->nullable()]);
        Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curricula_hemis_id')->nullable(), $t->string('subject_id'),
            $t->string('semester_code'), $t->string('subject_name'), $t->string('semester_name')->nullable()]);
        Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->string('document_type'), $t->string('subject_name')->nullable(),
            $t->string('group_names')->nullable(), $t->string('semester_name')->nullable(), $t->string('department_name')->nullable(),
            $t->string('generated_by')->nullable(), $t->timestamp('generated_at')->nullable(), $t->string('document_path')->nullable(), $t->json('meta')->nullable(), $t->timestamps()]);
        (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();
        (require base_path('database/migrations/2026_11_09_120000_add_role_to_yn_qaydnoma_confirmations.php'))->up();
        (require base_path('database/migrations/2026_11_09_160000_create_yn_qaydnoma_rejections_table.php'))->up();
        (require base_path('database/migrations/2026_11_09_180000_add_teacher_ids_to_yn_qaydnoma_rejections.php'))->up();
        Schema::create('departments', fn ($t) => [$t->id(), $t->unsignedBigInteger('department_hemis_id'), $t->string('name')]);
        Schema::create('dean_faculties', fn ($t) => [$t->id(), $t->unsignedBigInteger('teacher_id'), $t->unsignedBigInteger('department_hemis_id'), $t->timestamps()]);

        DB::table('teachers')->insert([['id' => 1, 'hemis_id' => 501, 'full_name' => 'IZZATULLAYEV OYBEK'], ['id' => 2, 'hemis_id' => 502, 'full_name' => 'IKROMOVA SH'],
            ['id' => 3, 'hemis_id' => 503, 'full_name' => 'SAGIRAYEV NURALI']]);
        DB::table('roles')->insert(['id' => 1, 'name' => 'dekan', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 1, 'model_type' => \App\Models\Teacher::class, 'model_id' => 3]);
        DB::table('departments')->insert(['department_hemis_id' => 11, 'name' => '1-son davolash']);
        DB::table('dean_faculties')->insert(['teacher_id' => 3, 'department_hemis_id' => 11]);
        DB::table('schedules')->insert([
            ['employee_id' => 501, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '13'],  // amaliy
            ['employee_id' => 502, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '11'],  // ma'ruza
        ]);
        DB::table('yn_submissions')->insert(['subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '77']);
        DB::table('groups')->insert(['group_hemis_id' => '77', 'name' => 'd1/d22-01b (rus)', 'curriculum_hemis_id' => '9',
            'department_hemis_id' => 11, 'department_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi']);
        DB::table('curriculum_subjects')->insert(['curricula_hemis_id' => '9', 'subject_id' => '435', 'semester_code' => '19', 'subject_name' => 'Pediatriya']);

        // Excel quruvchi o'rniga soxta varaq — QR bosish asl mantiq bilan (stampConfirmationQrs)
        $this->controller = new class extends JournalController {
            public function __construct() {}

            public function buildYnQaydnomaSpreadsheet(string $subjectId, string $semesterCode, string $groupHemisId,
                int $weightJn, int $weightMt, int $weightOn, int $weightOski, int $weightTest, int $shaklId = 1): array|string
            {
                $s = qaydnomaSheet();
                (new ReflectionMethod(JournalController::class, 'stampConfirmationQrs'))->invoke(null, $s, $subjectId, $semesterCode, $groupHemisId);

                return [$s, 'YN_qaydnoma_test.xlsx'];
            }
        };
        $this->confirm = function (int $teacherId, string $action = 'confirm') {
            $request = \Illuminate\Http\Request::create('/admin/journal/' . $action . '-yn-qaydnoma', 'POST', [
                'subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '77',
                'weight_jn' => 30, 'weight_mt' => 10, 'weight_on' => 0, 'weight_oski' => 0, 'weight_test' => 60,
            ]);
            $request->setLaravelSession($session = app('session')->driver());
            $session->start();
            app()->instance('request', $request);
            Auth::shouldUse('teacher');
            Auth::guard('teacher')->login(\App\Models\Teacher::find($teacherId));
            session(['active_role' => $teacherId === 3 ? 'dekan' : 'oqituvchi']);

            return match ($action) {
                'confirm' => $this->controller->confirmYnQaydnoma($request),
                'reject' => $this->controller->rejectYnQaydnoma($request->merge(['reason' => 'MT baholari xato'])),
                default => $this->controller->revokeYnQaydnoma($request),
            };
        };
    });

    it('lets the practical teacher confirm once and stamps a unique QR next to the name', function () {
        ($this->confirm)(1);
        ($this->confirm)(1);   // takroriy — yangi tasdiq yaratilmaydi

        $c = \App\Models\YnQaydnomaConfirmation::sole();
        expect($c->teacher_name)->toBe('IZZATULLAYEV OYBEK')
            ->and($c->role)->toBe('oqituvchi')
            ->and($c->verification->meta['specialty_name'])->toBe('Davolash ishi')
            ->and($c->verification->document_path)->toEndWith('.xlsx')
            ->and($c->weights['test'])->toBe(60)
            ->and($c->verification->document_type)->toBe('yn_qaydnoma')
            ->and(strlen($c->verification->token))->toBe(48);
        Storage::disk('public')->assertExists($c->verification->document_path);

        [$sheet] = $this->controller->buildYnQaydnomaSpreadsheet('435', '19', '77', 30, 10, 0, 0, 60);
        $drawings = $sheet->getSheet(0)->getDrawingCollection();
        // Imzo ustuni talabalar qatorlarida bitta katak; QR uning o'rtasida (10 × 20px, QR 90px:
        // yuqori cheti 55px — 22-qatorda 15px), ustun 207px — chapdan 58px
        expect($sheet->getSheet(0)->getMergeCells())->toHaveKey('X20:X29')
            ->and($sheet->getSheet(0)->getStyle('X20')->getFill()->getFillType())->toBe(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_NONE)
            ->and($sheet->getSheet(0)->getStyle('W20')->getFill()->getStartColor()->getRGB())->toBe('FFC7CE')
            // Shartli formatlash imzo ustunisiz: A–W va Y
            ->and(array_keys($sheet->getSheet(0)->getConditionalStylesCollection()))->toEqualCanonicalizing(['A20:W49', 'Y20:Y49'])
            ->and($drawings)->toHaveCount(1)
            ->and($drawings[0]->getCoordinates())->toBe('X22')
            ->and([$drawings[0]->getOffsetY(), $drawings[0]->getHeight()])->toBe([15, 90])
            ->and($drawings[0]->getOffsetX())->toBeGreaterThan(50)->toBeLessThan(65)
            ->and($sheet->getSheet(1)->getDrawingCollection())->toHaveCount(0);   // chop etish maydoni yo'q varaq

        // Sahifada QR birlashtirilgan katakning (X20) o'zida, katak o'rtasida (flex markaz)
        $html = JournalController::qaydnomaHtml($sheet, 'YN', '');
        expect($html)->toContain('rowspan="10"')
            ->toMatch('/<td[^>]*rowspan="10"[^>]*><div class="ynq-qr"><img alt="Tasdiq QR: IZZATULLAYEV[^"]*" src="data:image\/png;base64,[^"]+" style="width:90px;height:90px" \/><\/div>/');
    });

    it('lets the teacher revoke the confirmation, removing the QR and its verification', function () {
        ($this->confirm)(1);
        $c = \App\Models\YnQaydnomaConfirmation::sole();
        $path = $c->verification->document_path;

        $request = \Illuminate\Http\Request::create('/admin/journal/revoke-yn-qaydnoma', 'POST',
            ['subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '77']);
        $request->setLaravelSession(app('session')->driver());
        $this->controller->revokeYnQaydnoma($request);

        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)
            ->and(\App\Models\DocumentVerification::count())->toBe(0);
        Storage::disk('public')->assertMissing($path);
        [$sheet] = $this->controller->buildYnQaydnomaSpreadsheet('435', '19', '77', 30, 10, 0, 0, 60);
        expect($sheet->getSheet(0)->getDrawingCollection())->toHaveCount(0);

        ($this->confirm)(1);   // qayta tasdiqlash mumkin
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(1);
    });

    it('lets the dean confirm after the teacher and stamps the dean QR on the signature line', function () {
        expect(fn () => ($this->confirm)(3))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // o'qituvchidan oldin — yo'q

        ($this->confirm)(1);
        ($this->confirm)(3);
        $dean = \App\Models\YnQaydnomaConfirmation::where('role', 'dekan')->sole();
        expect($dean->teacher_name)->toBe('SAGIRAYEV NURALI')
            ->and($dean->weights['test'])->toBe(60)                                   // o'qituvchi vaznlari
            ->and($dean->verification->meta['role'])->toBe('dekan');
        // Ikkala QR bitta (eng so'nggi) Excel fayliga havola qiladi
        $paths = \App\Models\DocumentVerification::pluck('document_path')->unique();
        expect($paths)->toHaveCount(1);
        Storage::disk('public')->assertExists($paths->first());

        [$sheet] = $this->controller->buildYnQaydnomaSpreadsheet('435', '19', '77', 30, 10, 0, 0, 60);
        $deanQr = collect($sheet->getSheet(0)->getDrawingCollection())->first(fn ($d) => str_starts_with($d->getDescription(), 'Dekan QR: SAGIRAYEV'));
        // 56px QR pastki cheti C60 chizig'idan 3px yuqorida: 58-qator (1px) + 59 + 60
        // "Fakultet dekani" ro'parasida: imzo chizig'i va "imzo" yozuvi o'rniga ikki qatorli katak, QR o'rtada
        expect($deanQr->getCoordinates())->toBe('C60')
            ->and($deanQr->getHeight())->toBe(56)
            ->and($sheet->getSheet(0)->getMergeCells())->toHaveKey('C60:C61')
            ->and($sheet->getSheet(0)->getCell('C61')->getValue())->toBeNull()
            ->and($sheet->getSheet(0)->getStyle('C60')->getBorders()->getBottom()->getBorderStyle())->toBe('none');

        $html = JournalController::qaydnomaHtml($sheet, 'YN', '');
        expect($html)->toMatch('/<img alt="Dekan QR: SAGIRAYEV[^"]*" src="[^"]+" style="position:absolute;left:50%;top:50%;[^"]*" \/>/');

        // O'qituvchi tasdig'ini qaytarsa — dekan tasdig'i ham bekor
        ($this->confirm)(1, 'revoke');
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)
            ->and(\App\Models\DocumentVerification::count())->toBe(0);
        Storage::disk('public')->assertMissing($paths->first());
    });

    it('lets the dean reject with a reason and the teacher re-confirm', function () {
        ($this->confirm)(1);
        $path = \App\Models\YnQaydnomaConfirmation::sole()->verification->document_path;

        ($this->confirm)(3, 'reject');
        $rejection = \App\Models\YnQaydnomaRejection::sole();
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)                  // o'qituvchi tasdig'i va QR bekor
            ->and(\App\Models\DocumentVerification::count())->toBe(0)
            ->and($rejection->reason)->toBe('MT baholari xato')
            ->and($rejection->dean_name)->toBe('SAGIRAYEV NURALI')
            ->and($rejection->weights['test'])->toBe(60)
            ->and($rejection->teacher_ids)->toBe([1])
            ->and($rejection->resolved_at)->toBeNull();
        Storage::disk('public')->assertMissing($path);
        expect(fn () => ($this->confirm)(3))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // tasdiqlaydigan narsa yo'q

        ($this->confirm)(1);   // o'qituvchi qayta ko'rib chiqib tasdiqlaydi
        expect($rejection->fresh()->resolved_at)->not->toBeNull()
            ->and(\App\Models\YnQaydnomaConfirmation::count())->toBe(1);
    });

    it('does not let a teacher reject', function () {
        ($this->confirm)(1);
        expect(fn () => ($this->confirm)(1, 'reject'))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(1);
    });

    it('does not let the lecturer confirm', function () {
        expect(fn () => ($this->confirm)(2))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0);
    });
});
