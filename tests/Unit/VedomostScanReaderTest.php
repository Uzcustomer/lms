<?php

use App\Services\VedomostAiSettings;
use App\Services\VedomostScanReader;
use App\Models\VedomostAiKey;
use Dompdf\Dompdf;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Skaner o'qish quvuri: PDF → rasm (pdftoppm) → bo'laklar (GD) → Gemini (soxta)
 * → birlashtirish → ichki hisob bo'yicha qayta o'qish → kesh.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false]);
    DB::purge('sqlite');
    Schema::create('settings', function ($t) {
        $t->id();
        $t->string('key')->unique();
        $t->text('value')->nullable();
        $t->timestamps();
    });
    (include database_path('migrations/2026_10_29_100000_create_vedomost_ai_tables.php'))->up();
    VedomostAiKey::create(['api_key' => 'paid-key-xxxxxxxxxxxxxxxxxxxxxx', 'key_hint' => '…xxxx', 'is_paid' => true, 'enabled' => true]);
    app(VedomostAiSettings::class)->save(['max_retries' => 0, 'fallback_model' => '', 'bands' => 2]);
    File::deleteDirectory(storage_path('app/vedomost-ai-cache'));

    $this->pdf = storage_path('app/vedomost-ai-test.pdf');
    $dompdf = new Dompdf();
    $dompdf->loadHtml('<h3>BAHOLASH QAYDNOMASI</h3><table border="1" style="width:100%;margin-top:120px">'
        . str_repeat('<tr><td>1</td><td>ALIYEV ALI</td><td>368221100001</td><td>78,0</td><td>39,0</td></tr>', 20) . '</table>');
    $dompdf->setPaper('A4', 'landscape');
    $dompdf->render();
    file_put_contents($this->pdf, $dompdf->output());
});

afterEach(function () {
    @unlink($this->pdf);
    File::deleteDirectory(storage_path('app/vedomost-ai-cache'));
});

function bandReply(array $rows, array $header = []): array
{
    return [
        'candidates' => [['content' => ['parts' => [['text' => json_encode([
            'header' => $header,
            'weights' => ['jb' => '50', 'mt' => '20', 'on' => '', 'oski' => '', 'test' => '30'],
            'signatures' => ['oqituvchi' => true, 'dekan' => false, 'kafedra_mudiri' => false, 'muhr' => false],
            'rows' => $rows,
        ])]]], 'finishReason' => 'STOP']],
    ];
}

function readerRow(int $nr, string $id, string $jbBall): array
{
    return ['nr' => $nr, 'fio' => "TALABA {$nr}", 'id' => $id, 'jb_pct' => '78,0', 'jb_ball' => $jbBall, 'mt_pct' => '90', 'mt_ball' => '18',
        'on_pct' => '', 'on_ball' => '', 'jbmton_ball' => '57', 'jbmton_pct' => '81', 'oski_pct' => '', 'oski_ball' => '',
        'test_pct' => '77', 'test_ball' => '23', 'yn' => '80', 'ects' => 'B', 'baho' => 'yaxshi', 'nums' => [], 'unclear' => []];
}

test('rasm rejimi: bo\'laklar birlashadi, xato qator qayta o\'qib tuzatiladi, natija keshlanadi', function () {
    if (!VedomostScanReader::rendererAvailable()) {
        $this->markTestSkipped('pdftoppm yo\'q');
    }

    $calls = 0;
    Http::fake(function (Request $r) use (&$calls) {
        $calls++;
        $prompt = collect($r['contents'][0]['parts'])->pluck('text')->filter()->implode(' ');
        // 1-o'qish (2 bo'lak): 2-qatorda JB ball xato (44); qayta o'qish (3 bo'lak): to'g'ri.
        if (str_contains($prompt, 'of 2')) {
            return Http::response(str_contains($prompt, 'strip 1 of 2')
                ? bandReply([readerRow(1, '368221100001', '39'), readerRow(2, '368221100002', '44')], ['fan' => 'Endokrinologiya', 'guruh' => 'd1/22-03'])
                : bandReply([readerRow(2, '368221100002', '44'), readerRow(3, '368221100003', '39')]));
        }

        return Http::response(bandReply([readerRow(1, '368221100001', '39'), readerRow(2, '368221100002', '39'), readerRow(3, '368221100003', '39')]));
    });

    $res = app(VedomostScanReader::class)->read($this->pdf, 7);

    expect($res['meta']['mode'])->toBe('images')
        ->and($res['rows'])->toHaveCount(3)
        ->and(collect($res['rows'])->firstWhere('id', '368221100002')['jb_ball'])->toBe('39')
        ->and(collect($res['rows'])->firstWhere('id', '368221100002')['repaired'])->toBeTrue()
        ->and($res['meta']['repaired'])->toBe(1)
        ->and($res['header']['fan'])->toBe('Endokrinologiya')
        ->and($res['weights'])->toBe(['jb' => 50, 'mt' => 20, 'on' => null, 'oski' => null, 'test' => 30])
        ->and($res['signatures']['oqituvchi'])->toBeTrue();

    // 2-bo'lak rasmga jadval sarlavhasi yopishtirilgan (prompt'da qizil chiziq tavsifi).
    Http::assertSent(fn (Request $r) => str_contains(json_encode($r['contents']), 'RED horizontal bar')
        && $r['contents'][0]['parts'][0]['inlineData']['mimeType'] === 'image/jpeg');

    $before = $calls;
    $again = app(VedomostScanReader::class)->read($this->pdf, 7);
    expect($calls)->toBe($before)->and($again['meta']['cached'])->toBeTrue();

    // Xodim «Qayta tekshirish»ni bosganda — kesh ishlatilmaydi, AI qaytadan o'qiydi.
    $fresh = app(VedomostScanReader::class)->read($this->pdf, 7, null, true);
    expect($calls)->toBeGreaterThan($before)->and($fresh['meta']['cached'])->toBeFalse();
});

test('tushib qolgan qator (raqamlashda bo\'shliq) qayta o\'qib qo\'shiladi', function () {
    if (!VedomostScanReader::rendererAvailable()) {
        $this->markTestSkipped('pdftoppm yo\'q');
    }
    app(VedomostAiSettings::class)->save(['bands' => 1]);

    Http::fake(function (Request $r) {
        $prompt = collect($r['contents'][0]['parts'])->pluck('text')->filter()->implode(' ');
        // 1-o'qish (butun sahifa): 2-qator tushib qolgan; qayta o'qish (2 bo'lak): bor.
        return Http::response(str_contains($prompt, 'the whole page')
            ? bandReply([readerRow(1, '368221100001', '39'), readerRow(3, '368221100003', '39')])
            : bandReply([readerRow(1, '368221100001', '39'), readerRow(2, '368221100002', '39'), readerRow(3, '368221100003', '39')]));
    });

    \App\Services\VedomostAiProgress::start($this->pdf, 'Tekshiruv boshlandi');
    $res = app(VedomostScanReader::class)->read($this->pdf, 7, 3);
    $log = collect(\App\Services\VedomostAiProgress::lines($this->pdf))->pluck('m')->implode(' | ');
    \App\Services\VedomostAiProgress::stop();

    expect($log)->toContain('PDF → 1 sahifa rasmga aylantirildi')
        ->and($log)->toContain('Gemini javobi: 1-sahifa')
        ->and($log)->toContain('qatorlar tushib qolgan');

    expect(collect($res['rows'])->pluck('nr')->all())->toBe([1, 2, 3])
        ->and($res['meta']['missing_nrs'])->toBe([])
        ->and($res['meta']['rows_read'])->toBe(3);
});

test('ichki hisob: ball foiz×vazn yoki uning butun songacha yaxlitlangani (12a, 4–5 kurs) — xato emas', function () {
    $reader = app(\App\Services\VedomostScanReader::class);
    $ratios = ['jb' => 0.5, 'mt' => 0.2];
    $p = fn (array $row) => array_column($reader->rowProblems($row, $ratios), 0);
    expect($p(['jb_pct' => '77,0', 'jb_ball' => '39,0', 'mt_pct' => '88,0', 'mt_ball' => '18,0']))->toBe([])
        ->and($p(['jb_pct' => '77,0', 'jb_ball' => '38,5', 'mt_pct' => '88,0', 'mt_ball' => '17,6']))->toBe([])
        ->and($p(['jb_pct' => '77,0', 'jb_ball' => '40,0']))->toContain("jb_pct va jb_ball o'zaro mos emas");
});

test('vazn nisbati yaxlitlangan ballardan ham aniq (0.505 emas, 0.50) — soxta «o\'zaro mos emas» yo\'q', function () {
    $reader = app(\App\Services\VedomostScanReader::class);
    // 12a, 5-kurs: toq foizlarda ball yuqoriga yaxlitlangan (77 → 39, 91 → 46, 75 → 38).
    $rows = array_map(fn ($x) => ['jb_pct' => (string) $x[0], 'jb_ball' => (string) $x[1], 'mt_pct' => (string) $x[2], 'mt_ball' => (string) $x[3]],
        [[77, 39, 75, 15], [91, 46, 88, 18], [75, 38, 95, 19], [83, 42, 85, 17], [87, 44, 91, 18], [94, 47, 95, 19], [90, 45, 82, 16]]);
    $ratios = $reader->pageRatios($rows);
    expect($ratios['jb'])->toBe(0.5)->and($ratios['mt'])->toBe(0.2)
        ->and($reader->rowProblems(['jb_pct' => '94,0', 'jb_ball' => '47,0', 'mt_pct' => '95,0', 'mt_ball' => '19,0'], $ratios))->toBe([]);
});

test('qattiq tekshiruv (sozlamada): to\'g\'ri qatorlar o\'tadi, qo\'shni qatordan olingan 1–2 ustun ushlanadi', function () {
    $ratios = ['jb' => 0.5, 'mt' => 0.2, 'on' => null, 'oski' => 0.15, 'test' => 0.15];
    $f = ['jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'jbmton_ball', 'jbmton_pct', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects', 'baho'];
    $row = fn (string $s) => array_combine($f, preg_split('/\s+/', $s));
    // Sozlama har o'qishda bazadan — har bosqichda yangi reader (sozlamalar keshi eskirmasin).
    $p = fn (string $s) => array_column(app(\App\Services\VedomostScanReader::class)->rowProblems($row($s), $ratios), 0);

    // Haqiqiy vedomostlardan (Akusherlik d1/d23-06, Pediatriya xd/22-01, Onkologiya 12B).
    $good = [
        '81,0 40,5 90,0 18,0 59,0 84 100,0 15,0 70,0 10,5 85 B+ yaxshi',
        '81,0 40,5 90,0 18,0 59,0 84 90,0 13,5 74,0 11,1 84 B yaxshi',
        '85,0 43,0 95,0 19,0 62,0 89 60,0 9,0 100,0 15,0 86 B+ yaxshi',
        '89,0 45,0 100,0 20,0 65,0 93 100,0 15,0 96,0 14,4 94 A a\'lo',
        '80,0 40,0 88,0 18,0 58,0 83 90,0 13,5 60,0 9,0 81 B yaxshi',
        '0,0 0,0 90,0 18,0 0,0 0 0,0 0,0 0,0 0,0 -3 FX qo\'yilmadi',
    ];

    app(VedomostAiSettings::class)->save(['strict_row_checks' => true]);
    foreach ($good as $s) {
        expect($p($s))->toBe([]);
    }
    // Test juftligi yuqoridagi qatordan (100 / 15,0) — YN 83, formula 88.
    expect(implode(' ', $p('82,0 41,0 90,0 18,0 59,0 84 90,0 13,5 100,0 15,0 83 B yaxshi')))->toContain('= 88')
        // 1 ball farq: Test 14,4 o'rniga 15,0 — yumshoq tekshiruv (±1,5) o'tkazib yuborardi.
        ->and($p('89,0 45,0 100,0 20,0 65,0 93 100,0 15,0 100,0 15,0 94 A a\'lo'))->not->toBe([])
        // ECTS qo'shni qatordan.
        ->and(implode(' ', $p('82,0 41,0 90,0 18,0 59,0 84 90,0 13,5 99,0 14,9 87 B yaxshi')))->toContain('ECTS')
        // JB+MT % qo'shni qatordan.
        ->and(implode(' ', $p('78,0 39,0 90,0 18,0 57,0 84 100,0 15,0 85,0 12,8 85 B+ yaxshi')))->toContain('JB+MT+ON %');

    // O'chiq bo'lsa — avvalgidek: 1 ball farq va ECTS tekshirilmaydi.
    app(VedomostAiSettings::class)->save(['strict_row_checks' => false]);
    expect($p('89,0 45,0 100,0 20,0 65,0 93 100,0 15,0 100,0 15,0 94 A a\'lo'))->toBe([])
        ->and($p('82,0 41,0 90,0 18,0 59,0 84 90,0 13,5 99,0 14,9 87 B yaxshi'))->toBe([]);
});
