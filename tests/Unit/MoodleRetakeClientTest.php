<?php

use App\Services\MoodleRetakeClient;
use App\Services\MoodleRetakeException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

/**
 * Moodle local_otrabotka mijozi: so'rov shakli, javoblar va xatolar.
 * Token javobga ham, xato matniga ham chiqmasligi kerak.
 */

uses(Tests\TestCase::class);

const RETAKE_WS = 'https://moodle.test/webservice/rest/server.php';
const RETAKE_TOKEN = 'retake-secret-token-123';

beforeEach(function () {
    config()->set('services.moodle.ws_url', RETAKE_WS);
    config()->set('services.moodle.retake_token', RETAKE_TOKEN);
});

function bookParams(): array
{
    return [
        'booking_id' => 7,
        'student_hemis_id' => 5531,
        'subject_id' => 181,
        'lesson_date' => '2026-10-24',
        'slot_start' => '2026-10-30 14:00',
        'slot_end' => '2026-10-30 15:00',
        'status' => 'booked',
    ];
}

it('books a retake and sends a form-encoded request with the token', function () {
    Http::fake([RETAKE_WS => Http::response(['ok' => true, 'code' => 'saved', 'message' => 'Bron saqlandi', 'deadline' => '2026-10-31'])]);

    $result = app(MoodleRetakeClient::class)->bookRetake(bookParams());

    expect($result)->toBe(['ok' => true, 'code' => 'saved', 'message' => 'Bron saqlandi', 'deadline' => '2026-10-31']);
    Http::assertSent(function (Request $r) {
        return $r->url() === RETAKE_WS
            && $r->isForm()
            && $r['wstoken'] === RETAKE_TOKEN
            && $r['wsfunction'] === 'local_otrabotka_book_retake'
            && $r['moodlewsrestformat'] === 'json'
            && $r['booking_id'] == 7
            && $r['slot_start'] === '2026-10-30 14:00';
    });
});

it('returns a rejected booking (after_deadline) as ok=false with the message', function () {
    Http::fake([RETAKE_WS => Http::response([
        'ok' => false, 'code' => 'after_deadline', 'message' => "Tanlangan vaqt muddatdan keyin", 'deadline' => '2026-10-28',
    ])]);

    $result = app(MoodleRetakeClient::class)->bookRetake(bookParams());

    expect($result['ok'])->toBeFalse()
        ->and($result['code'])->toBe('after_deadline')
        ->and($result['message'])->toBe('Tanlangan vaqt muddatdan keyin');
});

it('throws on a network error without leaking the token', function () {
    Http::fake(fn () => throw new ConnectionException('cURL error 28: timed out ' . RETAKE_TOKEN));

    try {
        app(MoodleRetakeClient::class)->bookRetake(bookParams());
        $this->fail('exception expected');
    } catch (MoodleRetakeException $e) {
        expect($e->getMessage())->toContain('timed out')->not->toContain(RETAKE_TOKEN);
    }
});

it('treats a Moodle exception body and HTTP errors like network errors', function () {
    Http::fake([RETAKE_WS => Http::sequence()
        ->push(['exception' => 'moodle_exception', 'errorcode' => 'invalidtoken', 'message' => 'Invalid token'])
        ->push('Server error', 500)]);
    $client = app(MoodleRetakeClient::class);

    expect(fn () => $client->getDebts(5531))->toThrow(MoodleRetakeException::class, 'invalidtoken');
    expect(fn () => $client->getDebts(5531))->toThrow(MoodleRetakeException::class, 'HTTP 500');
});

it('cancels by booking_id only', function () {
    Http::fake([RETAKE_WS => Http::response(['ok' => true, 'code' => 'cancelled', 'message' => 'Bekor qilindi'])]);

    $result = app(MoodleRetakeClient::class)->cancel(7);

    expect($result['ok'])->toBeTrue()->and($result['code'])->toBe('cancelled');
    Http::assertSent(fn (Request $r) => $r['booking_id'] == 7 && $r['status'] === 'cancelled'
        && !isset($r['student_hemis_id']) && !isset($r['slot_start']));
});

it('reads debts', function () {
    Http::fake([RETAKE_WS => Http::response(['found' => true, 'debts' => [[
        'subject_id' => 181, 'lesson_date' => '2026-09-16', 'state' => 'start', 'can_book' => true,
    ]]])]);

    $result = app(MoodleRetakeClient::class)->getDebts(5531);

    expect($result['found'])->toBeTrue()->and($result['debts'][0]['subject_id'])->toBe(181);
    Http::assertSent(fn (Request $r) => $r['wsfunction'] === 'local_otrabotka_get_debts' && $r['student_hemis_id'] == 5531);
});

it('refuses to call Moodle without a token', function () {
    config()->set('services.moodle.retake_token', '');
    Http::fake();

    expect(fn () => app(MoodleRetakeClient::class)->getDebts(1))->toThrow(MoodleRetakeException::class);
    Http::assertNothingSent();
});
