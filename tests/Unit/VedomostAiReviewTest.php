<?php

use App\Http\Controllers\Admin\VedomostAiReportController;
use App\Http\Controllers\Admin\VedomostSubmissionController;
use App\Models\VedomostAiKey;
use App\Models\VedomostSubmission;
use App\Services\VedomostCheckEngine;
use Illuminate\Auth\GenericUser;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Yig'ma oyna: bir nechta AI tekshirgan vedomost bitta sahifada va ommaviy tasdiqlash
 * (har biri odatdagi tasdiqlash sharti bilan).
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
    Schema::create('groups', function ($t) {
        $t->id();
        $t->string('group_hemis_id');
        $t->boolean('active')->default(true);
    });
    foreach (glob(database_path('migrations/*vedomost_submission*')) as $f) {
        (include $f)->up();
    }
    (include database_path('migrations/2026_10_29_100000_create_vedomost_ai_tables.php'))->up();
    VedomostAiKey::create(['api_key' => 'paid-key-xxxxxxxxxxxxxxxxxxxxxx', 'key_hint' => '…xxxx', 'is_paid' => true, 'enabled' => true]);

    auth()->setUser(new GenericUser(['id' => 1, 'name' => 'Registrator']));
    session(['active_role' => 'registrator_ofisi']);
});

function reviewSub(int $group, string $subject, ?string $verdict, array $rows = []): VedomostSubmission
{
    DB::table('groups')->insert(['group_hemis_id' => (string) $group, 'active' => true]);
    $report = ['version' => VedomostCheckEngine::VERSION, 'verdict' => $verdict, 'summary' => "AI: {$verdict}",
        'counts' => ['sys_edited' => 0], 'rows' => $rows, 'extra' => [], 'header' => []];

    return VedomostSubmission::create([
        'education_year' => '2026-2027', 'semester_code' => '17', 'group_hemis_id' => $group, 'group_name' => "d1/22-0{$group}",
        'subject_id' => 433, 'subject_name' => $subject, 'closing_form' => 'oski_test', 'form_type' => '12', 'status' => 'reviewing',
        'pdf_path' => "v{$group}.pdf", 'ai_check_status' => $verdict ? 'done' : 'none', 'ai_verdict' => $verdict,
        'ai_result' => $verdict ? ['version' => VedomostCheckEngine::VERSION, 'report' => $report] : null,
    ]);
}

test('yig\'ma oyna: bitta fan hisoboti oynasi, barcha fanlar bitta jadvalda (kalitlar vedomost bo\'yicha)', function () {
    $ok = reviewSub(1, 'Xirurgiya', 'ok', [['status' => 'ok', 'n' => 1, 'fio' => 'MOS TALABA', 'id' => '1', 'key' => 'r0', 'vt' => ['A' => 1], 'cells' => []]]);
    $bad = reviewSub(2, 'Terapiya', 'issues', [
        ['status' => 'bad', 'n' => 2, 'fio' => 'XATO TALABA', 'id' => '2', 'key' => 'r1', 'vt' => ['D' => 95], 'pdf' => ['jb_pct' => '25'],
            'cells' => ['jn' => ['status' => 'bad']], 'flags' => [['t' => 'bad', 'm' => 'JN%: PDF 25 ≠ Mark 95']]],
    ]);
    $none = reviewSub(3, 'Pediatriya', null);

    $view = app(VedomostAiReportController::class)->review(Request::create('/', 'GET', ['ids' => [$ok->id, "{$bad->id}", $none->id]]));
    $data = $view->getData();

    expect($view->name())->toBe('admin.vedomost-submission.ai-report')
        // Muammolilar birinchi, AI tekshirmagan jadvalga tushmaydi, lekin tasdiqlash ro'yxatida bor.
        ->and(array_column($data['report']['rows'], 'fio'))->toBe(['XATO TALABA', 'MOS TALABA'])
        ->and(array_column($data['report']['rows'], 'key'))->toBe([$bad->id . '|r1', $ok->id . '|r0'])
        ->and(array_column($data['report']['rows'], 'fan'))->toBe(['Terapiya', 'Xirurgiya'])
        ->and(array_column($data['report']['rows'], 'grp'))->toBe(['d1/22-02', 'd1/22-01'])
        ->and($data['report']['verdict'])->toBe('issues')
        ->and(array_column($data['multi'], 'id'))->toBe([$bad->id, $none->id, $ok->id])
        ->and($data['multi'][1]['checked'])->toBeFalse();

    $html = $view->render();
    expect($html)->toContain("Yig'ma tekshiruv — 3 ta vedomost")->toContain('name="reasons[' . $bad->id . ']"')
        ->toContain('id="sheet-approve"');
});

test('ommaviy tasdiqlash: mos — darhol, muammoli — faqat sabab bilan', function () {
    $ok = reviewSub(1, 'Xirurgiya', 'ok');
    $bad = reviewSub(2, 'Terapiya', 'issues');
    $ctrl = app(VedomostSubmissionController::class);

    app()->instance('request', Request::create('/', 'POST', ['approve' => [$ok->id, $bad->id]]));
    $ctrl->bulkApprove(request());
    expect($ok->fresh()->status)->toBe('approved')
        ->and($bad->fresh()->status)->toBe('reviewing')
        ->and(session('error'))->toContain('1 ta tasdiqlanmadi');

    app()->instance('request', Request::create('/', 'POST', ['approve' => [$bad->id], 'reasons' => [$bad->id => 'qog\'ozdagi baho to\'g\'ri, Mark tuzatildi']]));
    $ctrl->bulkApprove(request());
    expect($bad->fresh()->status)->toBe('approved')
        ->and(DB::table('vedomost_submission_logs')->where('vedomost_submission_id', $bad->id)->value('note'))->toContain('Sabab: qog');
});

test('12a umumiy vedomost: birlikning barcha guruhlari (yo\'nalish, fan, fakultet bo\'yicha) va har biriga jurnal havolasi', function () {
    Schema::create('curricula', function ($t) {
        $t->id();
        $t->string('curricula_hemis_id');
        $t->string('department_hemis_id')->nullable();
    });
    DB::table('curricula')->insert([['curricula_hemis_id' => '10', 'department_hemis_id' => 'F1'], ['curricula_hemis_id' => '11', 'department_hemis_id' => 'F1'], ['curricula_hemis_id' => '20', 'department_hemis_id' => 'F2']]);
    $mk = function (int $g, string $form, array $over = []) {
        DB::table('groups')->insertOrIgnore(['group_hemis_id' => (string) $g, 'active' => true]);
        return VedomostSubmission::create(array_merge([
            'education_year' => '2025-2026', 'semester_code' => '20', 'group_hemis_id' => $g, 'group_name' => "d2/21-0{$g}",
            'curriculum_hemis_id' => 10, 'subject_id' => 500 + $g, 'subject_name' => 'Yuqumli kasalliklar', 'specialty_name' => 'Davolash ishi',
            'closing_form' => 'oski_test', 'form_type' => $form, 'status' => 'reviewing', 'ai_check_status' => 'none',
        ], $over));
    };
    $mk(1, '12');
    $mk(2, '12', ['curriculum_hemis_id' => 11]);                      // boshqa reja, lekin shu fakultet
    $mk(3, '12', ['specialty_name' => 'Pediatriya']);                 // boshqa yo'nalish
    $mk(4, '12', ['curriculum_hemis_id' => 20]);                      // boshqa fakultet
    $mk(5, '12', ['subject_name' => 'Ichki kasalliklar']);            // boshqa fan
    $combined = $mk(1, '12a', ['group_name' => 'Barcha guruhlar (2 ta)', 'subject_id' => 501]);

    $merge = app(\App\Services\VedomostMergeService::class);
    expect($merge->unitGroupsOf($combined)->pluck('group_hemis_id')->map(fn ($g) => (int) $g)->all())->toBe([1, 2]);

    $links = $merge->journalLinks($combined);
    expect(array_column($links, 'label'))->toBe(['d2/21-01', 'd2/21-02'])
        ->and($links[1]['url'])->toContain('/journal/show/')->toContain('/502/20');
});

test('jonli kuzatuv: ishlayotgan vedomost (oxirgi qadam, kalit), navbat va oxirgi so\'rovlar', function () {
    config()->set('cache.default', 'array');
    Schema::create('jobs', function ($t) {
        $t->id();
        $t->string('queue');
        $t->longText('payload');
        $t->unsignedTinyInteger('attempts')->default(0);
        $t->unsignedInteger('reserved_at')->nullable();
        $t->unsignedInteger('available_at');
        $t->unsignedInteger('created_at');
    });
    $run = reviewSub(1, 'Xirurgiya', null);
    $run->update(['ai_check_status' => 'running']);
    \App\Services\VedomostAiProgress::start($run->pdf_path, 'Tekshiruv boshlandi');
    \App\Services\VedomostAiProgress::log('Gemini so‘rovi: 1-bo‘lak');
    \App\Services\VedomostAiProgress::stop();
    $client = app(\App\Services\VedomostGeminiClient::class);
    $key = $client->leaseKey($run->id);
    \App\Models\VedomostAiRequest::create(['vedomost_submission_id' => $run->id, 'vedomost_ai_key_id' => $key->id, 'model' => 'gemini-x', 'status' => 'error', 'http_status' => 429, 'error_type' => 'rate_limit', 'error_message' => 'Too many', 'latency_ms' => 1200, 'created_at' => now()]);
    DB::table('jobs')->insert(['queue' => 'vedomost-ai', 'payload' => '{}', 'available_at' => now()->subMinutes(5)->getTimestamp(), 'created_at' => now()->getTimestamp()]);

    $d = app(\App\Http\Controllers\Admin\VedomostAiSettingsController::class)->live()->getData(true);
    $client->releaseLease();

    expect($d['active'][0]['status'])->toBe('running')
        ->and($d['active'][0]['step']['m'])->toContain('1-bo‘lak')
        ->and($d['active'][0]['key'])->toContain('…xxxx')
        ->and($d['leases'])->toHaveCount(1)
        ->and($d['jobs']['pending'])->toBe(1)
        ->and($d['worker_warning'])->toBeTrue()
        ->and($d['requests'][0]['error_type'])->toBe('rate_limit');
});
