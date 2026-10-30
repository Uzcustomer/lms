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

test('«tahlil qilmoqda»da qotib qolgan tekshiruv 20 daqiqadan keyin xato holatiga tiklanadi', function () {
    foreach (glob(database_path('migrations/*vedomost_submissions*.php')) as $m) {
        if (!str_contains($m, 'logs')) {
            (include $m)->up();
        }
    }
    Schema::create('groups', function ($t) {
        $t->id();
        $t->string('group_hemis_id');
        $t->boolean('active')->default(true);
    });
    DB::table('groups')->insert([['group_hemis_id' => 'g1', 'active' => true], ['group_hemis_id' => 'g2', 'active' => true]]);
    $mk = fn (string $name, $ago, string $g) => tap(VedomostSubmission::forceCreate([
        'education_year' => '2026', 'semester_code' => '19', 'group_hemis_id' => $g, 'group_name' => $name,
        'subject_id' => 's1', 'subject_name' => 'Xirurgik kasalliklar', 'closing_form' => 'oski_test', 'form_type' => '12',
        'status' => 'reviewing', 'pdf_path' => 'a.pdf', 'ai_check_status' => 'running',
    ]), function ($v) use ($ago) {
        DB::table('vedomost_submissions')->where('id', $v->id)->update(['updated_at' => now()->subMinutes($ago)]);
        $v->refresh();
    });

    $fresh = $mk('d1/d22-02a (rus)', 5, 'g1');
    expect(app(VedomostAiDispatcher::class)->recoverStuck($fresh))->toBeFalse()
        ->and($fresh->ai_check_status)->toBe('running');

    $stuck = $mk('d1/d22-03a (rus)', 25, 'g2');
    expect(app(VedomostAiDispatcher::class)->recoverStuck($stuck))->toBeTrue()
        ->and($stuck->ai_check_status)->toBe('error')
        ->and($stuck->ai_error)->toContain("to'xtab qoldi")
        ->and(app(VedomostAiDispatcher::class)->approvalGate($stuck)['blocked'])->toBeFalse();
});
