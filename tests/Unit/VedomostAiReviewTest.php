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

test('yig\'ma oyna: muammolilar yuqorida, faqat muammoli qatorlar; moslar va tekshirilmaganlar alohida', function () {
    $ok = reviewSub(1, 'Xirurgiya', 'ok', [['status' => 'ok', 'n' => 1, 'fio' => 'MOS TALABA', 'id' => '1']]);
    $bad = reviewSub(2, 'Terapiya', 'issues', [
        ['status' => 'ok', 'n' => 1, 'fio' => 'MOS TALABA', 'id' => '1'],
        ['status' => 'bad', 'n' => 2, 'fio' => 'XATO TALABA', 'id' => '2', 'key' => 'r1', 'vt' => ['D' => 95], 'pdf' => ['jb_pct' => '25'],
            'cells' => ['jn' => ['status' => 'bad']], 'flags' => [['t' => 'bad', 'm' => 'JN%: PDF 25 ≠ Mark 95']]],
    ]);
    $none = reviewSub(3, 'Pediatriya', null);

    $view = app(VedomostAiReportController::class)->review(Request::create('/', 'GET', ['ids' => [$ok->id, "{$bad->id}", $none->id]]));
    $items = $view->getData()['items'];

    expect(array_map(fn ($i) => $i['v']->id, $items))->toBe([$bad->id, $none->id, $ok->id])
        ->and(array_column($items[0]['rows'], 'fio'))->toBe(['XATO TALABA'])
        ->and($items[1]['checked'])->toBeFalse()
        ->and($items[2]['rows'])->toBe([]);

    $html = $view->render();
    expect($html)->toContain('XATO TALABA')->toContain('JN%: PDF 25 ≠ Mark 95')->not->toContain('MOS TALABA')
        ->toContain('name="reasons[' . $bad->id . ']"');
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
