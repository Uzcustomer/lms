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

it('drops the signature column from the template and adds rows for more than 30 students', function () {
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load(public_path('templates/yn_qaydnoma (1).xlsx'));
    $w = $book->getActiveSheet();
    $total = 0;
    foreach (range('A', 'Y') as $col) {
        $total += $w->getColumnDimension($col)->getWidth();
    }
    (new ReflectionMethod(JournalController::class, 'prepareYnqSheet'))->invoke(null, $w, 33);
    $after = 0;
    foreach (range('A', 'X') as $col) {
        $after += $w->getColumnDimension($col)->getWidth();
    }
    $extra = (new ReflectionMethod(JournalController::class, 'ynqExtraRows'))->invoke(null, $w);

    expect((string) $w->getCell('X15')->getValue())->not->toContain('imzo')
        ->and(round($after, 4))->toBe(round($total + 6, 4))                   // imzo ustuni kengligi teng bo'lingan, so'z bilan baho ustuni +6
        ->and($w->getCell('X1')->getValue())->toContain('shakl')
        // Shakl raqami ko'rinadi: 1-qatordagi birlashtirish oxirgi ustungacha emas
        ->and($w->getMergeCells())->toHaveKey('C1:W1')->not->toHaveKey('C1:X1')
        ->and($w->getStyle('X1')->getAlignment()->getHorizontal())->toBe('right')
        ->and($extra)->toBe(3)
        ->and($w->getCell('A52')->getValue())->toBe(33)
        ->and($w->getCell('B53')->getValue())->toBe('O‘rtacha foiz')
        ->and($w->getCell('F60')->getValue())->toBe('=COUNTIF(X20:X52,"aʼlo")')
        ->and($w->getCell('C60')->getValue())->toBe('=COUNTA(B20:B52)');
});

it('keeps the Excel column widths in the PDF: narrow equal spacer columns', function () {
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load(public_path('templates/yn_qaydnoma (1).xlsx'));
    $w = $book->getActiveSheet();
    (new ReflectionMethod(JournalController::class, 'prepareYnqSheet'))->invoke(null, $w, 29);
    foreach (range(20, 48) as $r) {
        $w->setCellValue("B{$r}", 'ABDULLAYEV NEMATJON NODIRJON OGLI')->setCellValue("D{$r}", 87)->setCellValue("V{$r}", 91);
    }
    $widths = [];
    app()->bind('dompdf.wrapper', function ($app) use (&$widths) {
        $pdf = new \Barryvdh\DomPDF\PDF($app->make('dompdf'), $app['config'], $app['files'], $app['view']);
        $pdf->getDomPDF()->setCallbacks([['event' => 'end_frame', 'f' => function ($frame) use (&$widths) {
            $n = $frame->get_node();
            if ($n->nodeName === 'td' && $n->parentNode && $n->parentNode->getAttribute('class') === 'row20') {
                $box = $frame->get_border_box();
                $widths[(int) preg_replace('/^column(\d+).*$/', '$1', $n->getAttribute('class'))] = round($box['w'] ?? $box[2], 1);
            }
        }]]);

        return $pdf;
    });
    JournalController::qaydnomaPdf($book);

    // F, I, L, O, R, U — bir xil va eng tor (matn uzunligi ularni kengaytirmaydi)
    $spacers = array_map(fn ($c) => $widths[$c], [5, 8, 11, 14, 17, 20]);
    expect(array_unique($spacers))->toHaveCount(1)
        ->and($spacers[0])->toBeGreaterThan(3.5)->toBeLessThan(4.5)              // ~4pt
        ->and($spacers[0])->toBeLessThan(min(array_diff_key($widths, array_flip([5, 8, 11, 14, 17, 20]))) / 3)
        ->and($widths[21])->toBeLessThan($widths[23]);                       // "O'zlashtirish" ustuni so'z bilan bahodan torroq
});

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
        \App\Services\YnqFamily::flush();
        Storage::fake('public');
        Schema::create('teachers', fn ($t) => [$t->id(), $t->unsignedBigInteger('hemis_id'), $t->string('full_name')->nullable(),
            $t->unsignedBigInteger('department_hemis_id')->nullable(), $t->unsignedBigInteger('head_department_hemis_id')->nullable(), $t->rememberToken(), $t->timestamps()]);
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
            $t->string('semester_code'), $t->string('subject_name'), $t->string('semester_name')->nullable(), $t->string('department_id')->nullable()]);
        Schema::create('document_verifications', fn ($t) => [$t->id(), $t->string('token'), $t->string('document_type'), $t->string('subject_name')->nullable(),
            $t->string('group_names')->nullable(), $t->string('semester_name')->nullable(), $t->string('department_name')->nullable(),
            $t->string('generated_by')->nullable(), $t->timestamp('generated_at')->nullable(), $t->string('document_path')->nullable(), $t->json('meta')->nullable(), $t->timestamps()]);
        (require base_path('database/migrations/2026_11_07_160000_create_yn_qaydnoma_confirmations_table.php'))->up();
        (require base_path('database/migrations/2026_11_09_120000_add_role_to_yn_qaydnoma_confirmations.php'))->up();
        (require base_path('database/migrations/2026_11_09_200000_add_pdf_path_to_yn_qaydnoma_confirmations.php'))->up();
        (require base_path('database/migrations/2026_11_09_160000_create_yn_qaydnoma_rejections_table.php'))->up();
        (require base_path('database/migrations/2026_11_09_180000_add_teacher_ids_to_yn_qaydnoma_rejections.php'))->up();
        Schema::create('departments', fn ($t) => [$t->id(), $t->unsignedBigInteger('department_hemis_id'), $t->string('name')]);
        Schema::create('dean_faculties', fn ($t) => [$t->id(), $t->unsignedBigInteger('teacher_id'), $t->unsignedBigInteger('department_hemis_id'), $t->timestamps()]);

        DB::table('teachers')->insert([['id' => 1, 'hemis_id' => 501, 'full_name' => 'IZZATULLAYEV OYBEK'], ['id' => 2, 'hemis_id' => 502, 'full_name' => 'IKROMOVA SH'],
            ['id' => 3, 'hemis_id' => 503, 'full_name' => 'SAGIRAYEV NURALI'], ['id' => 4, 'hemis_id' => 504, 'full_name' => 'XURMATOVA DILNOZA AKMALOVNA']]);
        DB::table('teachers')->where('id', 4)->update(['department_hemis_id' => 55]);   // kafedra mudiri — 55-kafedra
        DB::table('roles')->insert([['id' => 1, 'name' => 'dekan', 'guard_name' => 'web'], ['id' => 5, 'name' => 'kafedra_mudiri', 'guard_name' => 'web']]);
        DB::table('model_has_roles')->insert([['role_id' => 1, 'model_type' => \App\Models\Teacher::class, 'model_id' => 3],
            ['role_id' => 5, 'model_type' => \App\Models\Teacher::class, 'model_id' => 4]]);
        DB::table('departments')->insert(['department_hemis_id' => 11, 'name' => '1-son davolash']);
        DB::table('dean_faculties')->insert(['teacher_id' => 3, 'department_hemis_id' => 11]);
        DB::table('schedules')->insert([
            ['employee_id' => 501, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '13'],  // amaliy
            ['employee_id' => 502, 'subject_id' => 435, 'group_id' => 77, 'training_type_code' => '11'],  // ma'ruza
        ]);
        DB::table('yn_submissions')->insert(['subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '77']);
        DB::table('groups')->insert(['group_hemis_id' => '77', 'name' => 'd1/d22-01b (rus)', 'curriculum_hemis_id' => '9',
            'department_hemis_id' => 11, 'department_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi']);
        DB::table('curriculum_subjects')->insert(['curricula_hemis_id' => '9', 'subject_id' => '435', 'semester_code' => '19', 'subject_name' => 'Pediatriya', 'department_id' => '55']);

        // Excel quruvchi o'rniga soxta varaq — QR bosish asl mantiq bilan (stampConfirmationQrs)
        $this->controller = new class extends JournalController {
            public ?bool $withFamily = null;

            public function __construct() {}

            public function buildYnQaydnomaSpreadsheet(string $subjectId, string $semesterCode, string $groupHemisId,
                int $weightJn, int $weightMt, int $weightOn, int $weightOski, int $weightTest, int $shaklId = 1, bool $withFamily = true): array|string
            {
                $this->withFamily = $withFamily;
                $s = qaydnomaSheet();
                (new ReflectionMethod(JournalController::class, 'prepareYnqSheet'))->invoke(null, $s->getSheet(0), 10);
                (new ReflectionMethod(JournalController::class, 'stampConfirmationQrs'))->invoke(null, $s, $subjectId, $semesterCode, $groupHemisId);

                return [$s, 'YN_qaydnoma_test.xlsx'];
            }
        };
        $this->confirm = function (int $teacherId, string $action = 'confirm', string $group = '77') {
            $request = \Illuminate\Http\Request::create('/admin/journal/' . $action . '-yn-qaydnoma', 'POST', [
                'subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => $group,
                'weight_jn' => 30, 'weight_mt' => 10, 'weight_on' => 0, 'weight_oski' => 0, 'weight_test' => 60,
            ]);
            $request->setLaravelSession($session = app('session')->driver());
            $session->start();
            app()->instance('request', $request);
            Auth::shouldUse('teacher');
            Auth::guard('teacher')->login(\App\Models\Teacher::find($teacherId));
            session(['active_role' => [3 => 'dekan', 4 => 'kafedra_mudiri'][$teacherId] ?? 'oqituvchi']);
            \App\Services\YnqFamily::flush();

            return match ($action) {
                'confirm' => $this->controller->confirmYnQaydnoma($request),
                'reject' => $this->controller->rejectYnQaydnoma($request->merge(['reason' => 'MT baholari xato'])),
                'view' => $this->controller->viewYnQaydnoma($request),
                default => $this->controller->revokeYnQaydnoma($request),
            };
        };
    });

    it('lets the practical teacher confirm once and stamps a unique QR in the bottom teachers block', function () {
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
        $w = $sheet->getSheet(0);
        // Imzo ustuni olib tashlangan (kengligi qolganlariga bo'lingan); o'qituvchi QR i pastda — yozuvdan
        // darhol keyin (C dan), ostida ismi
        expect($w->getCell('X15')->getValue())->toBeNull()
            ->and($w->getPageSetup()->getPrintArea())->toBe('A1:X61')
            ->and(array_keys($w->getConditionalStylesCollection()))->toBe(['A20:X49'])
            ->and($w->getStyle('X20')->getFill()->getStartColor()->getRGB())->toBe('FFC7CE')
            // Ajratuvchi ustunlar bir xil va tor; bo'shagan kenglik qolganlariga teng
            ->and(collect(['F', 'I', 'L', 'O', 'R', 'U'])->map(fn ($c) => $w->getColumnDimension($c)->getWidth())->unique()->all())->toBe([1.55])
            ->and(round($w->getColumnDimension('A')->getWidth(), 2))->toBe(round(8.43 + (28.81640625 + 6 * (8.43 - 1.55)) / 18, 2))
            ->and($drawings)->toHaveCount(1)
            ->and($drawings[0]->getCoordinates())->toBe('C60')
            ->and($drawings[0]->getHeight())->toBe(56)
            ->and($w->getMergeCells())->toHaveKeys(['C60:E60', 'C61:E61'])   // QR va ism sig'adigan, oralari kengroq
            ->and($w->getCell('C61')->getValue())->toBe('IZZATULLAYEV O.')
            ->and($w->getCell('B60')->getValue())->toBe('Amaliyot o‘qituvchilari:')
            ->and($w->getStyle('B60')->getAlignment()->getHorizontal())->toBe($w->getStyle('B57')->getAlignment()->getHorizontal())   // "Jami talabalar soni" bilan bir chiziqda
            ->and($sheet->getSheet(1)->getDrawingCollection())->toHaveCount(0);   // chop etish maydoni yo'q varaq

        // Sahifada QR o'z katagining o'rtasida (flex markaz)
        $html = JournalController::qaydnomaHtml($sheet, 'YN', '');
        expect($html)->toMatch('/<div class="ynq-qr"><img alt="Tasdiq QR: IZZATULLAYEV O\.[^"]*" src="data:image\/png;base64,[^"]+" style="width:56px;height:56px" \/><\/div>/');
    });

    it('does not let the teacher revoke their own confirmation', function () {
        ($this->confirm)(1);
        expect(fn () => ($this->confirm)(1, 'revoke'))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(1);
    });

    it('lets a superadmin acting as the teacher revoke the confirmation, removing the QR and its verification', function () {
        session(['impersonating' => true]);
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

    it('goes teacher → kafedra mudiri → dean and stamps their QRs in the top corners', function () {
        expect(fn () => ($this->confirm)(4))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // o'qituvchidan oldin — yo'q

        ($this->confirm)(1);
        expect(fn () => ($this->confirm)(3))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // kafedradan oldin dekan — yo'q
        ($this->confirm)(4);
        ($this->confirm)(3);
        $head = \App\Models\YnQaydnomaConfirmation::where('role', 'kafedra_mudiri')->sole();
        $dean = \App\Models\YnQaydnomaConfirmation::where('role', 'dekan')->sole();
        expect($head->teacher_name)->toBe('XURMATOVA DILNOZA AKMALOVNA')
            ->and($dean->teacher_name)->toBe('SAGIRAYEV NURALI')
            ->and($dean->weights['test'])->toBe(60)                                   // o'qituvchi vaznlari
            ->and($dean->verification->meta['role'])->toBe('dekan');
        // Hamma QR bitta (eng so'nggi) Excel fayliga havola qiladi
        $paths = \App\Models\DocumentVerification::pluck('document_path')->unique();
        expect($paths)->toHaveCount(1);
        Storage::disk('public')->assertExists($paths->first());

        [$sheet] = $this->controller->buildYnQaydnomaSpreadsheet('435', '19', '77', 30, 10, 0, 0, 60);
        $find = fn ($prefix) => collect($sheet->getSheet(0)->getDrawingCollection())->first(fn ($d) => str_starts_with($d->getDescription(), $prefix));
        // Dekan — chap yuqori, kafedra mudiri — o'ng yuqori burchak
        expect($find('Dekan QR: SAGIRAYEV N.')->getCoordinates())->toBe('A2')
            ->and($find('Kafedra QR: XURMATOVA D.A.')->getCoordinates())->toBe('X2');

        $html = JournalController::qaydnomaHtml($sheet, 'YN', '');
        expect($html)->toMatch("/<div class='ynq-corner' style='position:absolute;top:24px;left:0;[^']*'><img src='data:image\\/png;base64,[^']+'[^>]*><div[^>]*>SAGIRAYEV N\\.<br>\\(Fakultet dekani\\)/")
            ->toMatch("/<div class='ynq-corner' style='position:absolute;top:24px;right:0;[^']*'><img[^>]*><div[^>]*>XURMATOVA D\\.A\\.<br>\\(Kafedra mudiri\\)/")
            // QR lar uchun varaq chetlarida joy — sarlavha yozuvlari ustiga chiqmaydi
            ->toContain("position:relative;padding:0 100px'>")
            ->toContain('right:0;width:100px;');

        // O'qituvchi tasdig'i qaytarilsa (superadmin sinov rejimi) — kafedra va dekan tasdig'i ham bekor
        session(['impersonating' => true]);
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

    it('makes the PDF once the dean confirms and removes it when the dean revokes', function () {
        ($this->confirm)(1);
        ($this->confirm)(4);
        expect($this->controller->generateYnQaydnomaPdf('435', '19', '77'))->toBeNull();   // dekan tasdiqlamagan

        ($this->confirm)(3);
        $path = $this->controller->generateYnQaydnomaPdf('435', '19', '77');
        expect($path)->toStartWith('yn_qaydnoma_pdf/')
            ->and(\App\Models\YnQaydnomaConfirmation::where('role', 'dekan')->value('pdf_path'))->toBe($path)
            ->and(Storage::disk('public')->get($path))->toStartWith('%PDF');

        ($this->confirm)(3, 'revoke');
        Storage::disk('public')->assertMissing($path);
    });

    it('lets only the superadmin delete a vedomost completely', function () {
        ($this->confirm)(1);
        ($this->confirm)(4);
        ($this->confirm)(3);
        $pdf = $this->controller->generateYnQaydnomaPdf('435', '19', '77');
        $xlsx = \App\Models\DocumentVerification::value('document_path');
        $delete = function () {
            $request = \Illuminate\Http\Request::create('/admin/journal/delete-yn-qaydnoma', 'POST',
                ['subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '77']);
            $request->headers->set('Accept', 'application/json');
            app()->instance('request', $request);

            return $this->controller->deleteYnQaydnoma($request);
        };

        expect($delete)->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // dekan — yo'q

        DB::table('roles')->insert(['id' => 2, 'name' => 'superadmin', 'guard_name' => 'web']);
        DB::table('model_has_roles')->insert(['role_id' => 2, 'model_type' => \App\Models\Teacher::class, 'model_id' => 2]);
        app(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        Auth::guard('teacher')->login(\App\Models\Teacher::find(2));
        expect($delete()->getData(true)['ok'])->toBeTrue()
            ->and(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)
            ->and(\App\Models\DocumentVerification::count())->toBe(0);
        Storage::disk('public')->assertMissing($pdf);
        Storage::disk('public')->assertMissing($xlsx);
    });

    it('lets the kafedra mudiri reject, and a dean rejection drops the kafedra confirmation too', function () {
        ($this->confirm)(1);
        ($this->confirm)(4, 'reject');
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)
            ->and(\App\Models\YnQaydnomaRejection::sole()->dean_name)->toBe('XURMATOVA DILNOZA AKMALOVNA');

        ($this->confirm)(1);
        ($this->confirm)(4);
        ($this->confirm)(3, 'reject');
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0);
    });

    it('does not let a teacher reject', function () {
        ($this->confirm)(1);
        expect(fn () => ($this->confirm)(1, 'reject'))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(1);
    });

    it('makes one vedomost for subgroups a/b: the kafedra mudiri confirms only after every subgroup teacher', function () {
        Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('group_id'), $t->string('student_status_code')->nullable()]);
        Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->softDeletes()]);
        DB::table('teachers')->insert(['id' => 5, 'hemis_id' => 505, 'full_name' => 'KARIMOVA LOLA']);
        DB::table('groups')->insert([
            ['group_hemis_id' => '78', 'name' => 'd1/d22-01a (rus)', 'curriculum_hemis_id' => '9', 'department_hemis_id' => 11, 'department_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi'],
            ['group_hemis_id' => '79', 'name' => 'd1/d22-01c (rus)', 'curriculum_hemis_id' => '9', 'department_hemis_id' => 11, 'department_name' => '1-son davolash', 'specialty_name' => 'Davolash ishi'],
        ]);
        // 01c — talabasi bor, lekin shu fandan bahosi yo'q: qaydnomaga kirmaydi
        DB::table('students')->insert([['hemis_id' => 's1', 'group_id' => '77'], ['hemis_id' => 's2', 'group_id' => '78'], ['hemis_id' => 's3', 'group_id' => '79']]);
        DB::table('student_grades')->insert([['student_hemis_id' => 's1', 'subject_id' => '435', 'semester_code' => '19'],
            ['student_hemis_id' => 's2', 'subject_id' => '435', 'semester_code' => '19'], ['student_hemis_id' => 's3', 'subject_id' => '1', 'semester_code' => '19']]);
        DB::table('schedules')->insert(['employee_id' => 505, 'subject_id' => 435, 'group_id' => 78, 'training_type_code' => '13']);
        // 01b o'qituvchisining 01a da ham bitta darsi bor (almashtirish) — uning tasdig'i 01a ni yopmaydi
        DB::table('schedules')->insert(['employee_id' => 501, 'subject_id' => 435, 'group_id' => 78, 'training_type_code' => '13']);
        DB::table('yn_submissions')->insert(['subject_id' => '435', 'semester_code' => '19', 'group_hemis_id' => '78']);
        \App\Services\YnqFamily::flush();
        expect(\App\Services\YnqFamily::members('435', '19', '77'))->toBe(['78', '77'])
            ->and(\App\Services\YnqFamily::label('d1/d22-01a (rus)', 2))->toBe('d1/d22-01 (rus)');

        Schema::table('schedules', fn ($t) => [$t->string('semester_code')->nullable(), $t->string('subject_name')->nullable(), $t->boolean('education_year_current')->default(true)]);
        DB::table('schedules')->update(['semester_code' => '19', 'subject_name' => 'Pediatriya']);
        expect(\App\Services\YnqPending::forTeacher(\App\Models\Teacher::find(5)))->toBe([]);          // hali hech kim tasdiqlamagan

        ($this->confirm)(1);                                                   // 01b o'qituvchisi
        // 01a o'qituvchisiga modal: 01b tasdiqladi, sizniki kutilmoqda
        \Illuminate\Support\Facades\Cache::flush();
        $pending = \App\Services\YnqPending::forTeacher(\App\Models\Teacher::find(5));
        expect($pending)->toHaveCount(1)
            ->and($pending[0]['group'])->toBe('d1/d22-01a (rus)')
            ->and($pending[0]['by'])->toBe([['group' => 'd1/d22-01b (rus)', 'teacher' => 'IZZATULLAYEV OYBEK']])
            ->and([$pending[0]['done'], $pending[0]['total']])->toBe([1, 2])
            ->and($pending[0]['ready'])->toBeTrue()
            ->and($pending[0]['url'])->toContain('group_hemis_id=78')
            ->and(\App\Models\YnQaydnomaConfirmation::pluck('group_hemis_id')->all())->toBe(['77'])            // faqat o'z guruhi
            ->and(array_column(\App\Services\YnqPending::forTeacher(\App\Models\Teacher::find(1)), 'group'))->toBe(['d1/d22-01a (rus)']);
        expect(fn () => ($this->confirm)(4))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);   // 01a hali topshirmagan
        ($this->confirm)(5, 'confirm', '78');                                  // 01a o'qituvchisi
        expect(\App\Services\YnqPending::forTeacher(\App\Models\Teacher::find(5)))->toBe([]);   // tasdiqlagach modal yo'q
        ($this->confirm)(4);
        ($this->confirm)(3, 'confirm', '78');
        expect(\App\Models\YnQaydnomaConfirmation::where('role', 'kafedra_mudiri')->value('group_hemis_id'))->toBe('78')   // asosiy guruh
            ->and(\App\Models\YnQaydnomaConfirmation::where('role', 'dekan')->count())->toBe(1);

        // Pastda ikkala o'qituvchi QR i yonma-yon (C:E, F:…), tepada kafedra va dekan
        [$sheet] = $this->controller->buildYnQaydnomaSpreadsheet('435', '19', '78', 30, 10, 0, 0, 60);
        $teacherQrs = collect($sheet->getSheet(0)->getDrawingCollection())->filter(fn ($d) => str_starts_with($d->getDescription(), 'Tasdiq QR: '));
        expect($teacherQrs->map->getCoordinates()->values()->all())->toBe(['C60', 'F60'])
            ->and($sheet->getSheet(0)->getCell('F61')->getValue())->toBe('KARIMOVA L.')
            ->and(collect($sheet->getSheet(0)->getDrawingCollection())->map->getCoordinates()->values()->all())->toContain('A2', 'X2');

        // Rad etilsa ikkala o'qituvchi tasdig'i bekor; ikkalasi qayta topshirgach rad etish yopiladi
        // Dekan tasdig'ini qaytarib rad etadi — kafedra va ikkala o'qituvchi tasdig'i bekor
        ($this->confirm)(3, 'revoke', '78');
        ($this->confirm)(3, 'reject', '78');
        $rejection = \App\Models\YnQaydnomaRejection::sole();
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0)
            ->and($rejection->group_hemis_id)->toBe('78')
            ->and($rejection->teacher_ids)->toEqualCanonicalizing([1, 5]);
        ($this->confirm)(1);
        expect($rejection->fresh()->resolved_at)->toBeNull();
        ($this->confirm)(5, 'confirm', '78');
        expect($rejection->fresh()->resolved_at)->not->toBeNull();
    });

    it('shows the subgroup teacher only their own group, the kafedra mudiri the whole vedomost, with a side panel', function () {
        $html = ($this->confirm)(1, 'view')->getContent();
        expect($this->controller->withFamily)->toBeFalse()
            ->and($html)->toContain('<aside class="ynq-dock" aria-label="Tasdiqlash">')
            ->toContain('Tasdiqlash</button>');

        ($this->confirm)(1);
        ($this->confirm)(4, 'view');
        expect($this->controller->withFamily)->toBeTrue();
    });

    it('keeps the top right corner free for the kafedra mudiri: teacher name lines stop before the last column', function () {
        $book = \PhpOffice\PhpSpreadsheet\IOFactory::load(public_path('templates/yn_qaydnoma (1).xlsx'));
        $w = $book->getActiveSheet();
        (new ReflectionMethod(JournalController::class, 'prepareYnqSheet'))->invoke(null, $w, 5);
        $w->setCellValue('U10', 'ALLAMURATOVA U.D.')->setCellValue('W8', 'd1/d22-01 (rus)');
        (new ReflectionMethod(JournalController::class, 'stampConfirmationQrs'))->invoke(null, $book, '435', '19', '77');
        expect($w->getStyle('B60')->getAlignment()->getHorizontal())->toBe('center');   // shablonda "Jami talabalar soni:" (B57) kabi
        expect($w->getMergeCells())->toHaveKeys(['N10:R10', 'S10:U10', 'N12:R12', 'S12:U12', 'V8:W8', 'K13:U13'])
            ->not->toHaveKey('U10:X10')
            ->and($w->getCell('S10')->getValue())->toBe('ALLAMURATOVA U.D.')
            ->and($w->getCell('N11')->getValue())->toContain('Amaliyot')
            ->and($w->getCell('K13')->getValue())->toContain('YaN o‘tkazilgan sana')
            ->and($w->getCell('V8')->getValue())->toBe('d1/d22-01 (rus)')
            ->and($w->getCell('W8')->getValue())->toBeNull()
            ->and($w->getStyle('U10')->getBorders()->getBottom()->getBorderStyle())->toBe('thin')
            ->and($w->getStyle('V10')->getBorders()->getBottom()->getBorderStyle())->toBe('none')
            ->and($w->getStyle('U13')->getBorders()->getBottom()->getBorderStyle())->toBe('thin')
            ->and($w->getStyle('W13')->getBorders()->getBottom()->getBorderStyle())->toBe('none')
            ->and($w->getStyle('X11')->getBorders()->getTop()->getBorderStyle())->toBe('none');
    });

    it('does not let the lecturer confirm', function () {
        expect(fn () => ($this->confirm)(2))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        expect(\App\Models\YnQaydnomaConfirmation::count())->toBe(0);
    });
});
