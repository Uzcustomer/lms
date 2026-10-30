<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Jobs\BuildUnplacedReportJob;
use App\Models\Teacher;
use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Models\TimetableCardOverride;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Spatie\Permission\Models\Role;

function urTeacher(string $name, string $role): Teacher
{
    Role::findOrCreate($role, 'web');
    $t = new Teacher();
    $t->forceFill([
        'hemis_id' => random_int(100000, 999999), 'full_name' => $name, 'short_name' => $name, 'first_name' => $name, 'second_name' => $name,
        'employee_id_number' => (string) random_int(100000, 999999), 'birth_date' => '1980-01-01', 'year_of_enter' => 2010,
        'gender' => 'm', 'department' => 'x', 'employment_form' => 'x', 'employment_staff' => 'x', 'staff_position' => 'x',
        'employee_status' => 'x', 'employee_type' => 'x', 'contract_number' => '1', 'decree_number' => '1',
        'contract_date' => '2020-01-01', 'decree_date' => '2020-01-01', 'status' => true, 'login' => 'u' . random_int(100000, 999999), 'password' => bcrypt('x'),
    ])->save();
    $t->assignRole($role);

    return $t;
}

// Yuklab olingan XLSX: varaq nomi => qatorlar (A1 dan, qiymatlar).
function urSheets($response): array
{
    $file = tempnam(sys_get_temp_dir(), 'ur') . '.xlsx';
    file_put_contents($file, $response->baseResponse->getFile()->getContent());
    $book = \PhpOffice\PhpSpreadsheet\IOFactory::load($file);
    @unlink($file);
    $out = [];
    foreach ($book->getAllSheets() as $sh) {
        $out[$sh->getTitle()] = $sh->toArray(null, false, false, false);
    }
    $sum = $book->getSheetByName('Yig\'ma');
    $det = $book->getSheetByName('Batafsil (haftalik)');
    $out['_style'] = ['fill_D2' => $sum->getStyle('D2')->getFill()->getStartColor()->getRGB(),
        'bold' => [$sum->getStyle('A2')->getFont()->getBold(), $sum->getStyle('A3')->getFont()->getBold(), $sum->getStyle('H4')->getFont()->getBold()],
        'filter' => $det->getAutoFilter()->getRange(), 'freeze' => $det->getFreezePane()];

    return $out;
}

// Prod'da faylni navbat worker'i (root) yozadi, php-fpm (www-data) o'qiydi: testlar root nomidan ishlasa, yuklab olish
// boshqa foydalanuvchi (nobody) nomidan bajariladi — 0600 fayl o'qilmay qolishi aynan shu yerda takrorlanadi.
function urAsWebUser(callable $fn)
{
    $switch = function_exists('posix_geteuid') && posix_geteuid() === 0;
    if ($switch) {
        posix_seteuid(65534);
    }
    try {
        return $fn();
    } finally {
        if ($switch) {
            posix_seteuid(0);
        }
    }
}

function urPerms(string $path): string
{
    clearstatcache(true, $path);

    return substr(sprintf('%o', fileperms($path)), -4);
}

beforeEach(function () {
    $this->board = TimetableBoard::create(['name' => 'T', 'academic_year' => '2026-2027', 'semester_parity' => 'kuzgi', 'days' => 6,
        'pairs_per_day' => 12, 'weeks' => 4, 'settings' => ['semester_start' => '2026-09-14']]);
    $base = ['board_id' => $this->board->id, 'oqim_label' => '1-oqim', 'lang' => 'uz', 'students' => 10, 'training_type' => 'practice',
        'len_half' => 2, 'weeks' => 4, 'kafedra_name' => 'K'];
    $this->base = $base;
    // F1: Anatomiya — shablonda joyi yo'q; 3-hafta bekor, 4-hafta ko'chirilgan → 1- va 2-hafta joylanmagan
    $this->a = TimetableCard::create($base + ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'subject_name' => 'Anatomiya', 'group_name' => 'd-01']);
    TimetableCardOverride::create(['card_id' => $this->a->id, 'week' => 3, 'cancelled' => true]);
    TimetableCardOverride::create(['card_id' => $this->a->id, 'week' => 4, 'day' => 2, 'pair' => 3, 'start_half' => 0, 'cancelled' => false]);
    // F2: Fiziologiya — shablonda joyi yo'q, lekin har haftaga ko'chirib qo'yilgan → joylangan
    $b = TimetableCard::create($base + ['faculty_name' => 'F2', 'specialty_name' => 'pediatriya ishi', 'course' => 2,
        'subject_name' => 'Fiziologiya', 'group_name' => 'p-01']);
    foreach ([1, 2, 3, 4] as $w) {
        TimetableCardOverride::create(['card_id' => $b->id, 'week' => $w, 'day' => 3, 'pair' => 1, 'start_half' => 0, 'cancelled' => false]);
    }
    // F2: Pediatriya — sikl fani (2 kun), ichki sikl jadvalida joylanmagan
    \App\Models\TimetableSubjectSetting::create(['board_id' => $this->board->id, 'specialty_name' => 'pediatriya ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'mode' => 'cycle', 'cycle_days' => 2]);
    TimetableCard::create($base + ['faculty_name' => 'F2', 'specialty_name' => 'pediatriya ishi', 'course' => 5,
        'subject_name' => 'Pediatriya', 'group_name' => 'p5-01a', 'weeks' => 15]);

    // Foydalanuvchi nomidan so'rovlar (hisobot holati va fayli — shu foydalanuvchiniki).
    $this->as = fn (Teacher $u, string $role) => test()->withoutVite()->actingAs($u, 'teacher')->withSession(['active_role' => $role]);
    $this->url = fn (string $name, array $q = []) => route('admin.timetable.boards.unplaced-report' . $name, ['board' => $this->board->id] + $q);
    // Boshlash (POST) → holat "done" (test navbati — sync) → yuklab olish.
    $this->report = function (string $role, array $body = []) {
        $u = urTeacher('U' . $role, $role);
        $start = ($this->as)($u, $role)->postJson(($this->url)(''), $body);
        if ($start->status() !== 200) {
            return $start;
        }
        $token = $start->json('token');
        $this->tokens[] = $token;
        expect($start->json('already_running'))->toBeFalse()
            ->and(($this->as)($u, $role)->getJson(($this->url)('.status', ['token' => $token]))->assertOk()->json())
            ->toBe(['status' => 'done', 'percent' => 100, 'step_label' => 'Tayyor', 'message' => 'Hisobot tayyor'])
            ->and(is_file(BuildUnplacedReportJob::path($token)))->toBeTrue()
            // Veb-server foydalanuvchisi o'qiy olsin: fayl 0664, papka 0775
            ->and(urPerms(BuildUnplacedReportJob::path($token)))->toBe('0664')
            ->and(urPerms(dirname(BuildUnplacedReportJob::path($token))))->toBe('0775');

        return urAsWebUser(fn () => ($this->as)($u, $role)->get(($this->url)('.download', ['token' => $token])));
    };
    $this->tokens = [];
});

afterEach(function () {
    foreach ($this->tokens as $token) {
        @unlink(BuildUnplacedReportJob::path($token));
    }
});

it('fonda tayyorlanadi: yig\'ma, batafsil (sikl fanisiz), sikl va fanlar varaqlari; tanlangan fakultet hisobga olinmaydi', function () {
    // Avvalgi versiya yaratgan papka (umask bilan 0755) — job uni 0775 qiladi
    @mkdir(dirname(BuildUnplacedReportJob::path('x')), 0755, true);
    chmod(dirname(BuildUnplacedReportJob::path('x')), 0755);
    // Ekranda F2 tanlangan bo'lsa ham — butun doska
    $resp = ($this->report)('registrator_ofisi', ['faculty_names' => ['F2'], 'layer' => 'internal'])->assertOk();
    expect($resp->headers->get('content-disposition'))->toContain('joylanmagan_kartalar_butun_doska_' . now()->format('Y-m-d') . '.xlsx');
    $x = urSheets($resp);

    expect(array_keys($x))->toBe(['Yig\'ma', 'Batafsil (haftalik)', 'Sikl (joylanmagan)', 'Fanlar bo\'yicha', '_style'])
        // Qiymatli katak rangli; fakultet jamisi va umumiy jami — qalin; batafsilda avtofiltr va muzlatilgan sarlavha
        ->and($x['_style'])->toMatchArray(['bold' => [false, true, true], 'filter' => 'A1:M2', 'freeze' => 'A2'])
        ->and($x['_style']['fill_D2'])->not->toBe('FFFFFF')
        // Yig'ma (har hafta alohida sanaladi): F1 / davolash ishi / 1-kurs — 1- va 2-haftada bittadan; 0 — bo'sh
        ->and($x['Yig\'ma'])->toBe([
            ['Fakultet', 'Yo\'nalish', 'Kurs', '1-hafta', '2-hafta', '3-hafta', '4-hafta', 'Jami'],
            ['F1', 'davolash ishi', 1, 1, 1, null, null, 2],
            ['F1 — jami', null, null, 1, 1, null, null, 2],
            ['Jami', null, null, 1, 1, null, null, 2],
        ])
        // Batafsil: faqat haftalik kartalar (sikl fani — Pediatriya yo'q); sababi bir xil haftalar — bitta qatorda
        ->and(array_map(fn ($r) => [$r[0], $r[5], $r[6], $r[7], $r[8]], array_slice($x['Batafsil (haftalik)'], 1)))
        ->toBe([['F1', 'Anatomiya', 'Amaliy', '1–2', 2]])
        ->and($x['Batafsil (haftalik)'][0][11])->toBe('Tekshirilgan vaqt')
        ->and($x['Batafsil (haftalik)'][1][11])->toBe('Seshanba, 2-para (odatiy joyi)')
        // Sikl varag'ida — joylanmagan sikl kartasi
        ->and(array_map(fn ($r) => [$r[0], $r[1], $r[2], $r[4], $r[5]], array_slice($x['Sikl (joylanmagan)'], 1)))
        ->toBe([['F2', 'pediatriya ishi', 5, 'Pediatriya', 2]])
        ->and(array_slice($x['Fanlar bo\'yicha'], 1))->toBe([['F1', 'davolash ishi', 1, 'Anatomiya', 2, '1–2']]);
});

it('batafsil: bir karta haftalari sabab bo\'yicha birlashadi ("1, 3–4" va "2"), yig\'ma esa har hafta sanaydi', function () {
    // Anatomiya hamma haftada joylanmagan, tashqi jadvaldagi vaqti — Seshanba 2-para
    TimetableCardOverride::where('card_id', $this->a->id)->delete();
    $this->a->update(['ext_day' => 2, 'ext_pair' => 3]);
    // Shu guruhning Biologiyasi aynan shu vaqtda, faqat 2-haftada (qolganlari bekor) — 2-haftada to'qnashuv
    $bio = TimetableCard::create($this->base + ['faculty_name' => 'F1', 'specialty_name' => 'davolash ishi', 'course' => 1,
        'subject_name' => 'Biologiya', 'group_name' => 'd-01', 'day' => 2, 'pair' => 3]);
    foreach ([1, 3, 4] as $w) {
        TimetableCardOverride::create(['card_id' => $bio->id, 'week' => $w, 'cancelled' => true]);
    }
    $x = urSheets(($this->report)('admin')->assertOk());
    $rows = array_slice($x['Batafsil (haftalik)'], 1);

    expect(array_map(fn ($r) => [$r[5], $r[7], $r[11]], $rows))->toBe([
        ['Anatomiya', '1, 3–4', 'Seshanba, 2-para (tashqi jadval)'],
        ['Anatomiya', 2, 'Seshanba, 2-para (tashqi jadval)'],
    ])
        ->and($rows[0][12])->not->toBe($rows[1][12])
        ->and($rows[1][12])->toContain('Biologiya')
        ->and($x['Yig\'ma'][1])->toBe(['F1', 'davolash ishi', 1, 1, 1, 1, 1, 4])
        ->and(array_slice($x['Fanlar bo\'yicha'], 1))->toBe([['F1', 'davolash ishi', 1, 'Anatomiya', 4, '1–4']]);

    // with_reasons=0 — "Sabab"siz tez variant: karta haftalari bitta qatorda, ustunlar 11 ta
    $x = urSheets(($this->report)('admin', ['with_reasons' => 0])->assertOk());
    expect($x['Batafsil (haftalik)'][0])->toHaveCount(11)
        ->and(array_map(fn ($r) => [$r[5], $r[7]], array_slice($x['Batafsil (haftalik)'], 1)))->toBe([['Anatomiya', '1–4']])
        ->and($x['_style']['filter'])->toBe('A1:K2')
        ->and($x['Yig\'ma'][1])->toBe(['F1', 'davolash ishi', 1, 1, 1, 1, 1, 4]);
});

it('hafta oralig\'i, tashqi qatlam va ruxsat', function () {
    // 2-haftadan: faqat 2-hafta
    $x = urSheets(($this->report)('oquv_bolimi', ['from_week' => 2])->assertOk());
    expect(array_column(array_slice($x['Batafsil (haftalik)'], 1), 7))->toBe([2]);
    // Tashqi qatlam: haftaga ko'chirishlar hisobga olinmaydi (faqat bekor qilingan hafta) — Fiziologiya ham joylanmagan
    $this->a->update(['ext_day' => 1, 'ext_pair' => 1]);
    $x = urSheets(($this->report)('admin', ['layer' => 'external'])->assertOk());
    expect(array_map(fn ($r) => $r[5] . ' ' . $r[7], array_slice($x['Batafsil (haftalik)'], 1)))->toBe(['Fiziologiya 1–4'])
        ->and($x['Yig\'ma'][1])->toBe(['F2', 'pediatriya ishi', 2, 1, 1, 1, 1, 4]);
    // Hisobotga ruxsati yo'q rollar
    ($this->report)('oqituvchi')->assertForbidden();
    ($this->report)('kafedra_mudiri')->assertForbidden();
    ($this->report)('kichik_admin')->assertForbidden();
    // O'quv bo'limi: holat va yuklab olish ham ochiq (middleware)
    expect(($this->report)('oquv_bolimi_boshligi')->assertOk()->headers->get('content-disposition'))->toContain('.xlsx');
});

it('bir foydalanuvchiga bitta hisobot; holat va fayl faqat egasiga', function () {
    Queue::fake();
    $u = urTeacher('Ua', 'admin');
    $other = urTeacher('Ub', 'admin');
    $first = ($this->as)($u, 'admin')->postJson(($this->url)(''), ['layer' => 'internal'])->assertOk();
    $token = $first->json('token');
    Queue::assertPushed(BuildUnplacedReportJob::class, fn ($job) => $job->token === $token && $job->boardId === $this->board->id
        && $job->opts === ['layer' => 'internal', 'from_week' => null, 'to_week' => null, 'with_reasons' => true]);
    expect($first->json('state'))->toBe(['status' => 'queued', 'percent' => 0, 'step_label' => 'Navbatda', 'message' => 'Hisobot navbatga qo\'yildi…']);

    // Hali navbatda — qayta bosilsa shu hisobot qaytadi, yangi job qo'yilmaydi
    $again = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk();
    expect($again->json('token'))->toBe($token)->and($again->json('already_running'))->toBeTrue();
    Queue::assertPushed(BuildUnplacedReportJob::class, 1);
    // Boshqa foydalanuvchi — o'zinikini boshlaydi; birovning holati/fayli — 404
    expect(($this->as)($other, 'admin')->postJson(($this->url)(''))->assertOk()->json('token'))->not->toBe($token);
    ($this->as)($other, 'admin')->getJson(($this->url)('.status', ['token' => $token]))->assertNotFound();
    ($this->as)($other, 'admin')->get(($this->url)('.download', ['token' => $token]))->assertNotFound();
    ($this->as)($u, 'admin')->getJson(($this->url)('.status', ['token' => 'yoq']))->assertNotFound();
    // Tayyor bo'lmagan hisobot — yuklab bo'lmaydi
    ($this->as)($u, 'admin')->get(($this->url)('.download', ['token' => $token]))->assertStatus(409);

    // 10 daqiqadan beri yangilanmagan (worker to'xtagan) — yangisi boshlanadi
    $this->travel(11)->minutes();
    $fresh = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk();
    expect($fresh->json('token'))->not->toBe($token)->and($fresh->json('already_running'))->toBeFalse();
});

it('job xatosi holatga yoziladi va oynada ko\'rinadi', function () {
    $this->partialMock(TimetableController::class, fn ($m) => $m->shouldReceive('buildUnplacedReport')->andThrow(new RuntimeException('test xato')));
    $u = urTeacher('Ue', 'admin');
    $start = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk();
    expect($start->json('state'))->toMatchArray(['status' => 'error', 'step_label' => 'Xatolik', 'message' => 'Xatolik: test xato'])
        ->and(($this->as)($u, 'admin')->getJson(($this->url)('.status', ['token' => $start->json('token')]))->json('status'))->toBe('error')
        ->and(is_file(BuildUnplacedReportJob::path($start->json('token'))))->toBeFalse();
    // Xato bilan tugagani — qayta bosilsa yangisi boshlanadi
    expect(($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk()->json('token'))->not->toBe($start->json('token'));
});

it('xato oxirgi bosqichda bo\'lsa ham — 100% va "Tayyor" emas (100% faqat tayyor hisobotda)', function () {
    $this->partialMock(TimetableController::class, fn ($m) => $m->shouldReceive('buildUnplacedReport')
        ->andReturnUsing(function ($board, $opts, $progress) {
            $progress(100, 'Tayyor');
            throw new RuntimeException('disk to\'la');
        }));
    $u = urTeacher('Ut', 'admin');
    $start = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk();
    expect(($this->as)($u, 'admin')->getJson(($this->url)('.status', ['token' => $start->json('token')]))->json())
        ->toBe(['status' => 'error', 'percent' => 99, 'step_label' => 'Xatolik', 'message' => 'Xatolik: disk to\'la'])
        // Eski (xato) holat keshda 100 bo'lsa ham — tashqariga 99 chiqadi
        ->and(BuildUnplacedReportJob::publicState(['status' => 'running', 'percent' => 100])['percent'])->toBe(99)
        ->and(BuildUnplacedReportJob::publicState(['status' => 'done', 'percent' => 40])['percent'])->toBe(100);
});

it('yuklab olish: fayl o\'qilmasa yoki yuborib bo\'lmasa — aniq JSON xato (500) va log', function () {
    $u = urTeacher('Ur', 'admin');
    $token = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk()->json('token');
    $this->tokens[] = $token;
    $path = BuildUnplacedReportJob::path($token);
    Log::spy();
    Log::shouldReceive('channel')->andReturnSelf();   // middleware Log::channel('daily') ga yozadi

    // Fayl faqat egasiga (eski 0600 holati): veb-server foydalanuvchisi o'qiy olmaydi
    $root = function_exists('posix_geteuid') && posix_geteuid() === 0;
    chmod($path, $root ? 0600 : 0000);
    $resp = urAsWebUser(fn () => ($this->as)($u, 'admin')->get(($this->url)('.download', ['token' => $token])));
    chmod($path, 0664);
    expect($resp->getStatusCode())->toBe(500)
        ->and($resp->json())->toBe(['error' => 'Fayl o\'qib bo\'lmadi (ruxsat)']);
    Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx) => str_contains($msg, 'faylni o\'qib bo\'lmadi') && $ctx['path'] === $path)->once();

    // response()->download o'zi xato bersa — ushlanadi, logga yoziladi
    $factory = \Mockery::mock(app(\Illuminate\Contracts\Routing\ResponseFactory::class));
    $factory->shouldReceive('download')->andThrow(new \Symfony\Component\HttpFoundation\File\Exception\FileException('File must be readable.'));
    app()->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $factory);
    $resp = ($this->as)($u, 'admin')->get(($this->url)('.download', ['token' => $token]));
    expect($resp->getStatusCode())->toBe(500)
        ->and($resp->json())->toBe(['error' => 'Faylni yuborib bo\'lmadi: File must be readable.']);
    Log::shouldHaveReceived('error')->withArgs(fn ($msg, $ctx) => str_contains($msg, 'yuborib bo\'lmadi') && $ctx['error'] === 'File must be readable.')->once();
});

it('navbatga qo\'yib bo\'lmasa — holat "error", "queued" da qotib qolmaydi', function () {
    config(['queue.default' => 'yoq']);   // sozlanmagan navbat ulanishi — dispatch xato beradi
    $u = urTeacher('Uq', 'admin');
    $start = ($this->as)($u, 'admin')->postJson(($this->url)(''))->assertOk();
    expect($start->json('state.status'))->toBe('error')
        ->and($start->json('state.message'))->toContain('Xatolik:')
        ->and(($this->as)($u, 'admin')->getJson(($this->url)('.status', ['token' => $start->json('token')]))->json('status'))->toBe('error');
});

it('1 kundan eski hisobot fayllari o\'chiriladi', function () {
    $old = BuildUnplacedReportJob::path('eski');
    $new = BuildUnplacedReportJob::path('yangi');
    @mkdir(dirname($old), 0775, true);
    file_put_contents($old, 'x');
    file_put_contents($new, 'x');
    touch($old, time() - 86400 - 60);
    BuildUnplacedReportJob::sweep();
    expect(is_file($old))->toBeFalse()->and(is_file($new))->toBeTrue();
    @unlink($new);
});

it('sikl: cycleUnplacedCards — cyclePlan (ichki) cycle_cards[].placed bilan bir xil', function () {
    $bid = $this->board->id;
    $card = fn (string $fac, string $spec, int $course, string $subject, string $group) => TimetableCard::create($this->base + [
        'faculty_name' => $fac, 'specialty_name' => $spec, 'course' => $course, 'subject_name' => $subject, 'group_name' => $group, 'weeks' => 15]);
    $cycle = fn (string $spec, int $course, string $subject, int $days) => \App\Models\TimetableSubjectSetting::create(['board_id' => $bid,
        'specialty_name' => $spec, 'course' => $course, 'subject_name' => $subject, 'mode' => 'cycle', 'cycle_days' => $days]);
    $place = fn (string $row, string $subject, int $from) => \App\Models\TimetableCyclePlacement::create(['board_id' => $bid,
        'specialty_name' => 'davolash ishi', 'course' => 5, 'group_name' => $row, 'subject_name' => $subject, 'start_index' => $from, 'training_type' => 'practice']);
    foreach (['Gigiyena' => 3, 'Terapiya' => 2, 'Nevrologiya' => 2, 'Xirurgiya (a)' => 2, 'Xirurgiya (b)' => 2, 'Kardiologiya' => 2] as $subject => $days) {
        $cycle('davolash ishi', 5, $subject, $days);
    }
    foreach (['Gigiyena', 'Terapiya', 'Nevrologiya', 'Kardiologiya'] as $subject) {
        foreach (['g5-01a', 'g5-01b'] as $g) {
            $card('F1', 'davolash ishi', 5, $subject, $g);
        }
    }
    $card('F1', 'davolash ishi', 5, 'Xirurgiya (a)', 'g5-01a');
    $card('F1', 'davolash ishi', 5, 'Xirurgiya (b)', 'g5-01b');
    $place('F1 / 1-oqim', 'Gigiyena', 0);         // oqim qatorida — ikkala guruh joylangan
    $place('g5-01a', 'Terapiya', 5);              // faqat bitta guruh qatorida
    $place('F1 / 1-oqim', 'Nevrologiya', 1000);   // kalendardan tashqarida — joylanmagan
    $place('F1 / 1-oqim', 'Xirurgiya (a)', 3);    // bo'lakli fan: (a) joylangan, (b) yo'q
    // Kardiologiya — joylashuvsiz; F2 dagi Pediatriya (beforeEach) ham joylanmagan

    $ctl = app(TimetableController::class);
    test()->actingAs(urTeacher('Uc', 'admin'), 'teacher');
    session(['active_role' => 'admin']);
    $plan = $ctl->cyclePlan(\Illuminate\Http\Request::create('/', 'POST', ['layer' => 'internal']), $this->board)->getData(true);
    $facOf = collect($plan['rows'])->keyBy('row_key')->map(fn ($r) => $r['faculty']);
    $expected = collect($plan['cycle_cards'])->reject(fn ($c) => $c['placed'])
        ->map(fn ($c) => [$facOf[$c['row_key']], $c['specialty'], $c['course'], $c['group'], $c['subject'], $c['days'], $c['key']])
        ->sort()->values()->all();
    $own = (new ReflectionMethod($ctl, 'cycleUnplacedCards'))->invoke($ctl, $this->board, [], true);
    $actual = collect($own)->map(fn ($c) => [$c['faculty'], $c['specialty'], $c['course'], $c['group'], $c['subject'], $c['days'], $c['key']])
        ->sort()->values()->all();

    expect($actual)->toBe($expected)
        ->and(collect($own)->map(fn ($c) => $c['group'] . ' ' . $c['subject'])->sort()->values()->all())->toBe([
            'g5-01a Kardiologiya', 'g5-01a Nevrologiya', 'g5-01b Kardiologiya', 'g5-01b Nevrologiya', 'g5-01b Terapiya',
            'g5-01b Xirurgiya (b)', 'p5-01a Pediatriya',
        ])
        // Sahifa ham joylanganini shunday ko'radi; hisobot varag'ida — xuddi shu qatorlar
        ->and(collect($plan['cycle_cards'])->where('placed', true)->count())->toBe(4)
        ->and(collect($own)->pluck('hours')->unique()->all())->toBe(collect($plan['cycle_cards'])->reject(fn ($c) => $c['placed'])->pluck('hours')->unique()->values()->all());
});

it('progress: bosqichlar ketma-ket, foiz o\'smaydi-kamaymaydi', function () {
    $steps = [];
    $file = app(TimetableController::class)->buildUnplacedReport($this->board, [], function (int $pct, string $label) use (&$steps) {
        $steps[] = [$pct, $label];
    });
    @unlink($file);
    $pcts = array_column($steps, 0);
    $sorted = $pcts;
    sort($sorted);
    // "Tayyor" va 100% — faqat job faylni joyiga qo'yib bo'lganda (holat "done")
    expect(array_values(array_unique(array_column($steps, 1))))->toBe(['Haftalik kartalar…', 'Sabablar…', 'Sikl…', 'Excel yozilmoqda…'])
        ->and($pcts)->toBe($sorted)
        // Keshga faqat o'zgarganda yoziladi
        ->and(collect($steps)->map(fn ($x) => implode('|', $x))->unique()->count())->toBe(count($steps))
        ->and($pcts[0])->toBe(0)
        ->and(end($pcts))->toBe(95);
});
