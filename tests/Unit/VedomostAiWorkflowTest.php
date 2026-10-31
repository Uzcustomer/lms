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

test('Mark qiymatini o\'zgartirishda sabab majburiy; tarixga yoziladi', function () {
    $result = [
        'scan' => ['rows' => [], 'header' => [], 'weights' => [], 'signatures' => []],
        'edits' => [],
        'report' => ['rows' => [['key' => 'r0', 'id' => '368221100001', 'fio' => 'ALIYEV ALI', 'vt' => ['P' => 0], 'sys_orig' => []]]],
    ];
    $ctrl = app(VedomostAiReportController::class);
    $merge = (new ReflectionClass($ctrl))->getMethod('mergeEdits');

    expect(fn () => $merge->invoke($ctrl, $result, ['system' => ['368221100001' => ['P' => ['v' => '90', 'why' => '']]]]))
        ->toThrow(\Illuminate\Validation\ValidationException::class);

    [$edits, $changes] = $merge->invoke($ctrl, $result, ['system' => ['368221100001' => ['P' => ['v' => '90', 'why' => 'Dalolatnoma'], 'X' => ['v' => '1', 'why' => 'yo\'q ustun']]]]);
    expect($edits)->toBe(['system' => ['368221100001' => ['P' => ['v' => 90.0, 'why' => 'Dalolatnoma']]]])
        ->and($changes[0])->toContain('Mark qiymati (faqat hisobotda) ALIYEV ALI — OSKI: 0 → 90 · sabab: Dalolatnoma');
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

test('test markazi: faqat aynan shu fan va semestrning YN quizlari', function () {
    Schema::create('hemis_quiz_results', function ($t) {
        $t->id();
        foreach (['student_id', 'fan_name', 'quiz_type', 'shakl', 'attempt_name', 'semester'] as $c) {
            $t->string($c)->nullable();
        }
        $t->unsignedBigInteger('fan_id')->nullable();
        $t->decimal('grade', 10, 2)->nullable();
        $t->dateTime('date_finish')->nullable();
        $t->tinyInteger('is_active')->default(1);
    });
    $row = fn (array $a) => array_merge(['student_id' => '368221100747', 'fan_id' => 433, 'fan_name' => 'Xirurgik kasalliklar',
        'shakl' => '1-urinish', 'attempt_name' => 'YN', 'semester' => '9', 'date_finish' => '2026-10-21 10:00:00'], $a);
    DB::table('hemis_quiz_results')->insert([
        $row(['quiz_type' => 'OSKI (rus)', 'grade' => 100]),
        $row(['quiz_type' => 'YN test (rus)', 'grade' => 52, 'semester' => '8', 'date_finish' => '2026-05-20 10:00:00']), // oldingi semestr
        $row(['quiz_type' => 'Oraliq test (rus)', 'grade' => 80, 'date_finish' => '2026-10-25 10:00:00']),                    // YN emas
        $row(['quiz_type' => 'YN test (rus)', 'grade' => 70, 'semester' => null, 'date_finish' => '2026-10-26 10:00:00']),    // semestri yo'q
        $row(['quiz_type' => 'YN test (rus)', 'grade' => 60, 'fan_id' => 999, 'fan_name' => 'Bolalar xirurgik kasalliklari']), // boshqa fan
    ]);

    $tc = (new ReflectionMethod(VedomostAiChecker::class, 'testCenter'))
        ->invoke(app(VedomostAiChecker::class), ['368221100747'], ['subject_id' => '433', 'fan' => 'Xirurgik kasalliklar'], '12', '9-semestr');

    expect($tc['368221100747']['oski']['grade'])->toBe(100.0)
        ->and($tc['368221100747'])->not->toHaveKey('test');
});

test('test markazi: qo\'shimcha shaklda OSKE 1-urinishdan, Test qo\'shimchadan (zanjir); attemptRaw ham komponent bo\'yicha', function () {
    Schema::create('hemis_quiz_results', function ($t) {
        $t->id();
        foreach (['student_id', 'fan_name', 'quiz_type', 'shakl', 'attempt_name', 'semester'] as $c) {
            $t->string($c)->nullable();
        }
        $t->unsignedBigInteger('fan_id')->nullable();
        $t->decimal('grade', 10, 2)->nullable();
        $t->dateTime('date_finish')->nullable();
        $t->tinyInteger('is_active')->default(1);
    });
    $row = fn (array $a) => array_merge(['student_id' => '1001', 'fan_id' => 433, 'fan_name' => 'Pediatriya', 'attempt_name' => 'YN', 'semester' => '9', 'date_finish' => '2026-10-21 10:00:00'], $a);
    DB::table('hemis_quiz_results')->insert([
        $row(['quiz_type' => 'OSKI (rus)', 'shakl' => '1-urinish', 'grade' => 80]),
        $row(['quiz_type' => 'YN test (rus)', 'shakl' => "1-urinish (qo'shimcha)", 'grade' => 72, 'date_finish' => '2026-10-28 10:00:00']),
        $row(['quiz_type' => 'YN test (rus)', 'shakl' => '2-urinish', 'grade' => 99]), // 12a — qo'shimcha zanjirida yo'q
    ]);
    $tc = (new ReflectionMethod(VedomostAiChecker::class, 'testCenter'))
        ->invoke(app(VedomostAiChecker::class), ['1001'], ['subject_id' => '433', 'fan' => 'Pediatriya'], '12q', '9-semestr');
    expect($tc['1001']['oski']['grade'])->toBe(80.0)->and($tc['1001']['test']['grade'])->toBe(72.0);

    // Qo'shimcha ssenariyda OSKE yo'q (null), asosiyda bor → OSKE asosiydan.
    $raw = VedomostAiChecker::attemptRaw(['oski' => 0.0, 'test' => 0.0], ['qoshimcha' => ['oski' => null, 'test' => 72], 'main' => ['oski' => 80, 'test' => null]], ['qoshimcha', 'main']);
    expect($raw['oski'])->toBe(80.0)->and($raw['test'])->toBe(72.0);
});

test('test markazi: fan nomi xato yozilgan («Yukumli») va fan ID boshqa — o\'xshashlik bo\'yicha topiladi (Python check_tm)', function () {
    Schema::create('hemis_quiz_results', function ($t) {
        $t->id();
        foreach (['student_id', 'fan_name', 'quiz_type', 'shakl', 'attempt_name', 'semester'] as $c) {
            $t->string($c)->nullable();
        }
        $t->unsignedBigInteger('fan_id')->nullable();
        $t->decimal('grade', 10, 2)->nullable();
        $t->dateTime('date_finish')->nullable();
        $t->tinyInteger('is_active')->default(1);
    });
    $fan = 'Yukumli kasalliklar. Bolalar yuqumli kasalliklari';
    $row = fn (array $a) => array_merge(['student_id' => '368211100549', 'fan_id' => 777, 'fan_name' => $fan, 'semester' => '10-SEM'], $a);
    DB::table('hemis_quiz_results')->insert([
        $row(['quiz_type' => 'OSKI (uzb)', 'shakl' => '1-urinish', 'attempt_name' => "OSKI (uzb)_{$fan}_10-sem_DAV-2_D_1-urinish", 'grade' => 100, 'date_finish' => '2026-04-15 15:47:37']),
        $row(['quiz_type' => 'YN test (uzb)', 'shakl' => '1-urinish', 'attempt_name' => "YN test (uzb)_{$fan}_10-sem_DAV-2_D_1-urinish", 'grade' => 100, 'date_finish' => '2026-04-17 11:23:47']),
        $row(['quiz_type' => 'YN test (uzb)', 'shakl' => '2-urinish', 'attempt_name' => "YN test (uzb)_{$fan}_10-sem_DAV-2_D_2-urinish", 'grade' => 89, 'date_finish' => '2026-05-19 14:15:37']),
    ]);
    $tc = (new ReflectionMethod(VedomostAiChecker::class, 'testCenter'))
        ->invoke(app(VedomostAiChecker::class), ['368211100549'], ['subject_id' => '123', 'fan' => 'Yuqumli kasalliklar. Bolalar yuqumli kasalliklari'], '12a', '10-semestr');
    expect($tc['368211100549']['oski']['grade'])->toBe(100.0)->and($tc['368211100549']['test']['grade'])->toBe(89.0);
});
