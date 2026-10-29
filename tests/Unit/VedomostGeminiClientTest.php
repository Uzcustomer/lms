<?php

use App\Models\VedomostAiKey;
use App\Models\VedomostAiRequest;
use App\Services\VedomostAiLimitException;
use App\Services\VedomostAiSettings;
use App\Services\VedomostGeminiClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Vedomost AI tekshiruvining Gemini mijozi: kalit tanlash tartibi (pullik → bepul),
 * xatoda keyingi kalit/zaxira model, limitlar va so'rovlar jurnali.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');

    Schema::create('settings', function ($t) {
        $t->id();
        $t->string('key')->unique();
        $t->text('value')->nullable();
        $t->timestamps();
    });
    (include database_path('migrations/2026_10_29_100000_create_vedomost_ai_tables.php'))->up();

    app(VedomostAiSettings::class)->save(['max_retries' => 0, 'model' => 'gemini-main', 'fallback_model' => 'gemini-lite']);
});

function aiKey(string $key, bool $paid, array $extra = []): VedomostAiKey
{
    return VedomostAiKey::create(array_merge([
        'api_key' => $key,
        'key_hint' => VedomostAiKey::hintFor($key),
        'is_paid' => $paid,
        'enabled' => true,
    ], $extra));
}

function geminiOk(array $data): array
{
    return [
        'candidates' => [[
            'content' => ['parts' => [['text' => json_encode($data)]]],
            'finishReason' => 'STOP',
        ]],
        'usageMetadata' => ['promptTokenCount' => 1000, 'candidatesTokenCount' => 200, 'thoughtsTokenCount' => 50],
    ];
}

function client(): VedomostGeminiClient
{
    return app(VedomostGeminiClient::class);
}

test('pullik kalit bepuldan oldin ishlatiladi va so\'rov jurnalga yoziladi', function () {
    aiKey('free-key-aaaaaaaaaaaaaaaaaaaa', false);
    $paid = aiKey('paid-key-bbbbbbbbbbbbbbbbbbbb', true);

    Http::fake(['*' => Http::response(geminiOk(['a' => 1]))]);

    $res = client()->generateJson([['text' => 'x']], ['type' => 'OBJECT'], 'sys', 5);

    expect($res['data'])->toBe(['a' => 1])
        ->and($res['key_id'])->toBe($paid->id)
        ->and($res['model'])->toBe('gemini-main');

    Http::assertSent(fn (Request $r) => $r->header('x-goog-api-key')[0] === 'paid-key-bbbbbbbbbbbbbbbbbbbb'
        && str_contains($r->url(), 'models/gemini-main:generateContent')
        && $r['generationConfig']['responseMimeType'] === 'application/json'
        && $r['systemInstruction']['parts'][0]['text'] === 'sys');

    $log = VedomostAiRequest::sole();
    expect($log->status)->toBe('ok')
        ->and($log->vedomost_submission_id)->toBe(5)
        ->and($log->input_tokens)->toBe(1000)
        ->and($log->output_tokens)->toBe(250);
});

test('kalit 503 bersa keyingi kalitga o\'tadi', function () {
    aiKey('paid-key-1111111111111111111', true);
    $second = aiKey('paid-key-2222222222222222222', true);

    Http::fake(function (Request $r) {
        return $r->header('x-goog-api-key')[0] === 'paid-key-1111111111111111111'
            ? Http::response(['error' => ['message' => 'overloaded']], 503)
            : Http::response(geminiOk(['ok' => true]));
    });

    $res = client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']);

    expect($res['key_id'])->toBe($second->id)
        ->and(VedomostAiRequest::where('status', 'error')->value('error_type'))->toBe('overloaded')
        ->and(VedomostAiRequest::count())->toBe(2);
});

test('bepul kalitlar o\'chirilgan bo\'lsa ishlatilmaydi', function () {
    aiKey('free-key-aaaaaaaaaaaaaaaaaaaa', false);
    app(VedomostAiSettings::class)->save(['allow_free_keys' => false]);

    expect(VedomostGeminiClient::isConfigured())->toBeFalse();
    Http::fake();

    expect(fn () => client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']))
        ->toThrow(RuntimeException::class);
    Http::assertNothingSent();
});

test('model topilmasa zaxira modelga o\'tadi', function () {
    aiKey('paid-key-bbbbbbbbbbbbbbbbbbbb', true);

    Http::fake(function (Request $r) {
        return str_contains($r->url(), 'gemini-main')
            ? Http::response(['error' => ['message' => 'not found']], 404)
            : Http::response(geminiOk(['m' => 'lite']));
    });

    $res = client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']);

    expect($res['model'])->toBe('gemini-lite')->and($res['data'])->toBe(['m' => 'lite']);
});

test('kunlik umumiy limit tugasa limit istisnosi', function () {
    aiKey('paid-key-bbbbbbbbbbbbbbbbbbbb', true);
    app(VedomostAiSettings::class)->save(['daily_limit' => 1]);
    VedomostAiRequest::create(['status' => 'ok', 'model' => 'gemini-main', 'created_at' => now()]);

    Http::fake();

    expect(fn () => client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']))
        ->toThrow(VedomostAiLimitException::class);
    Http::assertNothingSent();
});

test('kvotasi tugagan kalit boshqa ishlatilmaydi, oxirida limit istisnosi', function () {
    aiKey('paid-key-bbbbbbbbbbbbbbbbbbbb', true);

    Http::fake(['*' => Http::response(['error' => ['message' => 'Quota exceeded for metric: requests per day']], 429)]);

    expect(fn () => client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']))
        ->toThrow(VedomostAiLimitException::class);

    // Asosiy model: 1 so'rov; zaxira modelda kalit allaqachon "o'lik" — so'rov ketmaydi.
    expect(VedomostAiRequest::count())->toBe(1)
        ->and(VedomostAiRequest::value('error_type'))->toBe('quota');
});

test('kalitning kunlik limiti tugasa keyingisiga o\'tadi', function () {
    $first = aiKey('paid-key-1111111111111111111', true, ['daily_limit' => 1]);
    $second = aiKey('paid-key-2222222222222222222', true);
    VedomostAiRequest::create(['status' => 'ok', 'vedomost_ai_key_id' => $first->id, 'created_at' => now()]);

    Http::fake(['*' => Http::response(geminiOk(['ok' => 1]))]);

    expect(client()->generateJson([['text' => 'x']], ['type' => 'OBJECT'])['key_id'])->toBe($second->id);
});

test('kesilgan javob xato sifatida qayd etiladi', function () {
    aiKey('paid-key-bbbbbbbbbbbbbbbbbbbb', true);
    app(VedomostAiSettings::class)->save(['fallback_model' => '']);

    Http::fake(['*' => Http::response([
        'candidates' => [['content' => ['parts' => [['text' => '{"a":']]], 'finishReason' => 'MAX_TOKENS']],
    ])]);

    expect(fn () => client()->generateJson([['text' => 'x']], ['type' => 'OBJECT']))
        ->toThrow(RuntimeException::class, 'Gemini javob bermadi');
    expect(VedomostAiRequest::value('error_type'))->toBe('truncated')
        // Zaxira model bo'sh qoldirilgan — faqat bitta so'rov.
        ->and(VedomostAiRequest::count())->toBe(1)
        ->and(app(VedomostAiSettings::class)->get('fallback_model'))->toBeNull();
});

test('kalit bazada shifrlangan saqlanadi', function () {
    aiKey('secret-key-ccccccccccccccccccc', true);

    $raw = DB::table('vedomost_ai_keys')->value('api_key');
    expect($raw)->not->toContain('secret-key')
        ->and(VedomostAiKey::first()->api_key)->toBe('secret-key-ccccccccccccccccccc')
        ->and(VedomostAiKey::first()->toArray())->not->toHaveKey('api_key');
});

test('sozlamalar standart qiymatlar bilan to\'ldiriladi va turlarga keltiriladi', function () {
    $s = app(VedomostAiSettings::class);
    $s->save(['bands' => '3', 'auto_check' => '1', 'daily_limit' => '', 'tolerance' => '0.1', 'unknown' => 'x']);

    $fresh = new VedomostAiSettings();
    expect($fresh->get('bands'))->toBe(3)
        ->and($fresh->get('auto_check'))->toBeTrue()
        ->and($fresh->get('daily_limit'))->toBeNull()
        ->and($fresh->get('tolerance'))->toBe(0.1)
        ->and($fresh->all())->not->toHaveKey('unknown')
        ->and($fresh->get('jn_mt_pass'))->toBe(\App\Models\MarkingSystemScore::YN_JN_MT_LIMIT);
});
