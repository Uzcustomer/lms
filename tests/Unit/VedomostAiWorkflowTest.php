<?php

use App\Http\Controllers\Admin\VedomostAiReportController;
use App\Models\VedomostAiKey;
use App\Models\VedomostSubmission;
use App\Services\VedomostAiChecker;
use App\Services\VedomostAiDispatcher;
use App\Services\VedomostAiSettings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Ish jarayoni: tasdiqlashdan oldingi AI sharti va xodim tuzatishlarini birlashtirish.
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
});

function sub(array $attrs): VedomostSubmission
{
    return new VedomostSubmission(array_merge(['pdf_path' => 'a.pdf', 'status' => 'reviewing'], $attrs));
}

test('tasdiqlash sharti: navbatda — blok, mos — erkin, xato yoki tekshirilmagan — sabab', function () {
    $gate = app(VedomostAiDispatcher::class);

    expect($gate->approvalGate(sub(['ai_check_status' => 'running']))['blocked'])->toBeTrue()
        ->and($gate->approvalGate(sub(['ai_check_status' => 'done', 'ai_verdict' => 'ok'])))->toMatchArray(['blocked' => false, 'needs_reason' => false])
        ->and($gate->approvalGate(sub(['ai_check_status' => 'done', 'ai_verdict' => 'issues']))['needs_reason'])->toBeTrue()
        ->and($gate->approvalGate(sub(['ai_check_status' => 'none']))['needs_reason'])->toBeTrue()
        ->and($gate->approvalGate(sub(['ai_check_status' => 'none', 'pdf_path' => null]))['needs_reason'])->toBeFalse();

    app(VedomostAiSettings::class)->save(['require_ai_before_approve' => false]);
    expect(app(VedomostAiDispatcher::class)->approvalGate(sub(['ai_check_status' => 'running']))['blocked'])->toBeFalse();
});

test('tuzatishlar birlashtiriladi: asl qiymatga qaytsa olib tashlanadi, tarix matni yoziladi', function () {
    $result = [
        'scan' => [
            'rows' => [['fio' => 'ALIYEV ALI', 'id' => '1', 'jb_pct' => '87', 'unclear' => []]],
            'header' => ['fan' => 'Endokrinologiya'],
            'weights' => ['jb' => 50, 'test' => 30],
            'signatures' => ['muhr' => false],
        ],
        'edits' => [],
        'report' => ['rows' => [['key' => 'r0', 'fio' => 'ALIYEV ALI']]],
    ];
    $ctrl = app(VedomostAiReportController::class);
    $merge = (new ReflectionClass($ctrl))->getMethod('mergeEdits');

    [$edits, $changes] = $merge->invoke($ctrl, $result, [
        'rows' => ['r0' => ['jb_pct' => '78', 'unknown' => 'x']],
        'weights' => ['test' => '30'],
        'signatures' => ['muhr' => true],
    ]);
    expect($edits)->toBe(['rows' => ['r0' => ['jb_pct' => '78']], 'signatures' => ['muhr' => true]])
        ->and($changes[0])->toContain('ALIYEV ALI — JB %: 87 → 78')
        ->and($changes)->toHaveCount(2);

    $result['edits'] = $edits;
    [$edits2, $changes2] = $merge->invoke($ctrl, $result, ['rows' => ['r0' => ['jb_pct' => null]]]);
    expect($edits2)->toBe(['signatures' => ['muhr' => true]])
        ->and($changes2[0])->toContain('78 → 87 (asl qiymat)');
});

test('eski formatdagi natijani qayta hisoblab bo\'lmaydi', function () {
    expect(fn () => app(VedomostAiChecker::class)->recompute(['version' => 1], []))
        ->toThrow(RuntimeException::class, 'eski formatda');
});
