<?php

use App\Models\RetakeBooking;
use App\Services\RetakeBookingService;
use Carbon\Carbon;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

/**
 * Qayta topshirish broni: LMS'dagi yozuv holati Moodle javobiga qarab
 * (saqlandi / rad etildi / yetib bormadi / bekor qilindi) va cron qayta yuborishi.
 */

uses(Tests\TestCase::class);

const SVC_WS = 'https://moodle.test/webservice/rest/server.php';

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');
    config()->set('services.moodle.ws_url', SVC_WS);
    config()->set('services.moodle.retake_token', 'svc-token');

    Schema::create('student_grades', function ($t) {
        $t->id();
        $t->unsignedBigInteger('student_hemis_id');
        $t->unsignedBigInteger('subject_id');
        $t->dateTime('lesson_date');
        $t->double('grade')->nullable();
        $t->double('retake_grade')->nullable();
        $t->string('reason')->nullable();
        $t->dateTime('deadline')->nullable();
        $t->timestamps();
        $t->softDeletes();
    });
    Schema::create('deadlines', function ($t) {
        $t->id();
        $t->string('level_code');
        $t->integer('deadline_days');
        $t->timestamps();
    });
    (include database_path('migrations/2026_10_30_100000_create_retake_bookings_table.php'))->up();
    svcExcuseTables();

    Carbon::setTestNow('2026-10-29 10:00:00');
});

afterEach(fn () => Carbon::setTestNow());

function svcRefake($stub): void
{
    // Http::fake() qayta chaqirilsa birinchi stub ustun qoladi — yangi factory bilan almashtiramiz
    Http::swap(new Illuminate\Http\Client\Factory());
    Http::fake($stub);
}

function svcDebt(array $extra = []): array
{
    return array_merge([
        'subject_id' => 181, 'subject_name' => 'Tibbiy fizika', 'mavzu' => 3,
        'lesson_date' => '2026-10-24', 'reason' => 'nb', 'deadline' => '2026-10-31',
        'quiz_uploaded' => true, 'tries_left' => 2, 'state' => 'start', 'can_book' => true,
        'booking_id' => 0, 'slot_start' => '', 'slot_end' => '', 'missed_booking_id' => 0,
    ], $extra);
}

function svcBook(string $start = '2026-10-30 14:00', string $end = '2026-10-30 15:00'): array
{
    return app(RetakeBookingService::class)->book(5531, svcDebt(), Carbon::parse($start), Carbon::parse($end));
}

it('saves a booking and marks it synced when Moodle accepts it', function () {
    DB::table('student_grades')->insert(['student_hemis_id' => 5531, 'subject_id' => 181, 'lesson_date' => '2026-10-24 00:00:00', 'reason' => 'absent']);
    Http::fake([SVC_WS => Http::response(['ok' => true, 'code' => 'saved', 'message' => '30.10 14:00–15:00', 'deadline' => '2026-11-01'])]);

    $result = svcBook();
    $b = $result['booking']->fresh();

    expect($result['type'])->toBe('success')
        ->and($result['message'])->toBe('Saqlandi: 30.10 14:00–15:00')
        ->and($b->status)->toBe('booked')
        ->and($b->moodle_sync)->toBe('ok')
        ->and($b->moodle_code)->toBe('saved')
        // Muddat LMS'niki — Moodle qaytargan 2026-11-01 yozilmaydi
        ->and($b->deadline->format('Y-m-d'))->toBe('2026-10-31')
        ->and($b->grade_id)->toBe(1)
        ->and($b->synced_at)->not->toBeNull();
    Http::assertSent(fn (Request $r) => $r['booking_id'] == $b->id && $r['lesson_date'] === '2026-10-24'
        && $r['slot_end'] === '2026-10-30 15:00' && $r['status'] === 'booked');
});

it('marks a new booking rejected when Moodle refuses it (after_deadline)', function () {
    Http::fake([SVC_WS => Http::response(['ok' => false, 'code' => 'after_deadline', 'message' => 'Muddatdan keyin'])]);

    $result = svcBook();
    $b = $result['booking']->fresh();

    expect($result['type'])->toBe('error')
        ->and($result['message'])->toBe('Muddatdan keyin')
        ->and($b->status)->toBe('rejected')
        ->and($b->moodle_code)->toBe('after_deadline');
});

it('keeps the old slot when changing an existing booking is refused', function () {
    Http::fake([SVC_WS => Http::sequence()
        ->push(['ok' => true, 'code' => 'saved', 'message' => 'ok'])
        ->push(['ok' => false, 'code' => 'in_past', 'message' => "O'tgan vaqt"])]);

    $first = svcBook()['booking'];
    $second = svcBook('2026-10-30 16:00', '2026-10-30 17:00')['booking']->fresh();

    expect($second->id)->toBe($first->id)
        ->and($second->status)->toBe('booked')
        ->and($second->slot_start->format('H:i'))->toBe('14:00')
        ->and($second->moodle_code)->toBe('in_past')
        ->and(RetakeBooking::count())->toBe(1);
});

it('reuses the same id when the time is changed', function () {
    Http::fake([SVC_WS => Http::response(['ok' => true, 'code' => 'saved', 'message' => 'ok'])]);

    $first = svcBook()['booking'];
    $second = svcBook('2026-10-30 16:00', '2026-10-30 17:00')['booking']->fresh();

    expect($second->id)->toBe($first->id)
        ->and($second->slot_start->format('H:i'))->toBe('16:00')
        ->and(RetakeBooking::where('status', 'booked')->count())->toBe(1);
    Http::assertSentCount(2);
});

it('keeps the booking with moodle_sync=failed on a network error', function () {
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $result = svcBook();
    $b = $result['booking']->fresh();

    expect($result['type'])->toBe('warning')
        ->and($result['message'])->toContain('sinxronlash kutilmoqda')
        ->and($b->status)->toBe('booked')
        ->and($b->moodle_sync)->toBe('failed')
        ->and($b->sync_attempts)->toBe(1);
});

it('cancels a booking in LMS and Moodle', function () {
    Http::fake([SVC_WS => Http::sequence()
        ->push(['ok' => true, 'code' => 'saved', 'message' => 'ok'])
        ->push(['ok' => true, 'code' => 'cancelled', 'message' => 'Bekor qilindi'])]);

    $booking = svcBook()['booking'];
    $result = app(RetakeBookingService::class)->cancel($booking->fresh());
    $b = $booking->fresh();

    expect($result['type'])->toBe('success')
        ->and($b->status)->toBe('cancelled')
        ->and($b->cancelled_at)->not->toBeNull()
        ->and($b->moodle_sync)->toBe('ok')
        ->and($b->moodle_code)->toBe('cancelled');
    Http::assertSent(fn (Request $r) => $r['status'] === 'cancelled' && $r['booking_id'] == $b->id
        && $r['student_hemis_id'] == 5531 && $r['subject_id'] == 181 && $r['lesson_date'] === '2026-10-24');
});

it('marks a booking missed when Moodle reports it', function () {
    Http::fake([SVC_WS => Http::response(['ok' => true, 'code' => 'saved', 'message' => 'ok'])]);
    $booking = svcBook()['booking'];

    svcRefake([SVC_WS => Http::response(['found' => true, 'debts' => [svcDebt(['state' => 'missed', 'missed_booking_id' => $booking->id])]])]);
    app(RetakeBookingService::class)->debts(5531);
    // Boshqa talabaning broni o'zgarmasin
    app(RetakeBookingService::class)->debts(9999);

    expect($booking->fresh()->status)->toBe('missed');
});

it('validates the slot', function () {
    $v = fn ($s, $e, $d = '2026-10-31') => RetakeBookingService::validateSlot(Carbon::parse($s), Carbon::parse($e), $d);

    expect($v('2026-10-29 09:00', '2026-10-29 11:00'))->toContain('hozirgi vaqtdan keyin')
        ->and($v('2026-10-30 15:00', '2026-10-30 14:00'))->toContain('boshlanish vaqtidan keyin')
        ->and($v('2026-11-01 10:00', '2026-11-01 11:00'))->toContain('31.10.2026')
        ->and($v('2026-10-31 10:00', '2026-10-31 11:00'))->toBeNull();
});

it('cron resends unsynced bookings and logs after five failures', function () {
    Http::fake(fn () => throw new ConnectionException('down'));
    $booking = svcBook()['booking'];
    Carbon::setTestNow('2026-10-29 10:05:00');

    Log::spy();
    foreach (range(1, 4) as $_) {
        $this->artisan('retake-bookings:sync', ['--pause' => 0])->assertSuccessful();
        Carbon::setTestNow(now()->addMinutes(5));
    }
    expect($booking->fresh()->sync_attempts)->toBe(5);
    Log::shouldHaveReceived('error')->once();

    svcRefake([SVC_WS => Http::response(['ok' => true, 'code' => 'saved', 'message' => 'ok'])]);
    $this->artisan('retake-bookings:sync', ['--pause' => 0])->assertSuccessful();

    expect($booking->fresh()->moodle_sync)->toBe('ok');
    // Endi sinxronlangan — qayta yuborilmaydi
    expect(RetakeBookingService::unsyncedQuery()->count())->toBe(0);
});

it('cron does not resend a booking whose slot already passed', function () {
    Http::fake(fn () => throw new ConnectionException('down'));
    svcBook();
    Carbon::setTestNow('2026-10-30 16:00:00');

    expect(RetakeBookingService::unsyncedQuery()->count())->toBe(0);
});

it('builds time slots from the configured working hours', function () {
    config()->set('services.moodle.retake_slot_from', '09:00');
    config()->set('services.moodle.retake_slot_to', '12:30');
    config()->set('services.moodle.retake_slot_minutes', 60);

    expect(RetakeBookingService::slotOptions())->toBe([
        '09:00-10:00' => ['09:00', '10:00'],
        '10:00-11:00' => ['10:00', '11:00'],
        '11:00-12:00' => ['11:00', '12:00'],
    ]);
});

it('takes the deadline from LMS: stored grade deadline, else lesson date + level days', function () {
    DB::table('deadlines')->insert(['level_code' => '13', 'deadline_days' => 6]);

    // Saqlangan muddat yo'q — 24.10 + 6 kun
    DB::table('student_grades')->insert(['student_hemis_id' => 5531, 'subject_id' => 181, 'lesson_date' => '2026-10-24 00:00:00', 'reason' => 'absent']);
    expect(RetakeBookingService::lmsDeadline(5531, '13', 181, '2026-10-24')->toDateString())->toBe('2026-10-30');

    // Sozlama yo'q kurs — 7 kun
    expect(RetakeBookingService::lmsDeadline(5531, '99', 181, '2026-10-24')->toDateString())->toBe('2026-10-31');

    // Bir kunda ikki juftlik — saqlangan eng yaqin muddat
    DB::table('student_grades')->insert(['student_hemis_id' => 5531, 'subject_id' => 181, 'lesson_date' => '2026-10-24 00:00:00', 'reason' => 'absent', 'deadline' => '2026-10-28 23:59:59']);
    expect(RetakeBookingService::lmsDeadline(5531, '13', 181, '2026-10-24')->toDateString())->toBe('2026-10-28');
});

it('sends the student a Telegram message with a booking link per debt', function () {
    Schema::create('students', function ($t) {
        $t->id();
        $t->unsignedBigInteger('hemis_id');
        $t->string('student_id_number');
        $t->string('full_name');
        $t->string('telegram_chat_id')->nullable();
        $t->string('level_code')->nullable();
        $t->timestamps();
    });
    DB::table('students')->insert(['hemis_id' => 5531, 'student_id_number' => '368200000001', 'full_name' => 'Sinov Talaba', 'telegram_chat_id' => '111', 'level_code' => '13']);
    DB::table('deadlines')->insert(['level_code' => '13', 'deadline_days' => 6]);
    config()->set('services.telegram.bot_token', 'bot-token');

    Http::fake([
        SVC_WS => Http::response(['found' => true, 'debts' => [svcDebt(), svcDebt(['subject_id' => 200, 'state' => 'done', 'can_book' => false])]]),
        'api.telegram.org/*' => Http::response(['ok' => true]),
    ]);

    $this->artisan('retake-bookings:notify', ['student' => '368200000001'])->assertSuccessful();

    Http::assertSent(function (Request $r) {
        if (!str_contains($r->url(), 'api.telegram.org')) {
            return false;
        }
        $text = $r['text'];

        return $r['chat_id'] == '111'
            && str_contains($text, 'Tibbiy fizika')
            && str_contains($text, 'Muddat: 30.10.2026 gacha')
            && str_contains($text, 'retake-booking?subject_id=181&amp;lesson_date=2026-10-24')
            && substr_count($text, '📌') === 1;
    });
});

function svcExcuseTables(): void
{
    Schema::create('absence_excuses', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
        $t->date('start_date');
        $t->date('end_date');
        $t->string('status');
        $t->timestamps();
    });
    Schema::create('absence_excuse_makeups', function ($t) {
        $t->id();
        $t->unsignedBigInteger('absence_excuse_id');
        $t->unsignedBigInteger('student_id')->nullable();
        $t->string('subject_id')->nullable();
        $t->string('assessment_type');
        $t->date('original_date')->nullable();
        $t->date('makeup_date')->nullable();
        $t->date('makeup_end_date')->nullable();
        $t->boolean('jn_submitted')->default(false);
        $t->string('status')->default('scheduled');
        $t->timestamps();
    });
}

function svcExcuse(string $status, string $from, string $to, array $makeup = []): int
{
    $id = DB::table('absence_excuses')->insertGetId(['student_hemis_id' => '5531', 'start_date' => $from, 'end_date' => $to, 'status' => $status]);
    DB::table('absence_excuse_makeups')->insert(array_merge([
        'absence_excuse_id' => $id, 'student_id' => 1, 'subject_id' => '181', 'assessment_type' => 'jn',
        'original_date' => $from, 'makeup_date' => '2026-11-02', 'makeup_end_date' => '2026-11-07',
    ], $makeup));

    return $id;
}

it('takes the retake range from an approved excuse covering the lesson date', function () {

    // Ariza yo'q — oddiy muddat
    expect(RetakeBookingService::excuseWindow(5531, 181, '2026-10-24'))->toBeNull();

    // Tasdiqlanmagan ariza va boshqa davr hisobga olinmaydi
    svcExcuse('pending', '2026-10-22', '2026-10-25');
    svcExcuse('approved', '2026-10-01', '2026-10-05');
    expect(RetakeBookingService::excuseWindow(5531, 181, '2026-10-24'))->toBeNull();

    // Boshqa fan
    svcExcuse('approved', '2026-10-22', '2026-10-25', ['subject_id' => '999']);
    expect(RetakeBookingService::excuseWindow(5531, 181, '2026-10-24'))->toBeNull();

    svcExcuse('approved', '2026-10-22', '2026-10-25');
    expect(RetakeBookingService::excuseWindow(5531, 181, '2026-10-24'))->toBe(['2026-11-02', '2026-11-07']);
});

it('uses the rescheduled range when the excuse range was missed', function () {
    svcExcuse('approved', '2026-10-22', '2026-10-25', ['status' => 'missed', 'makeup_date' => '2026-10-26', 'makeup_end_date' => '2026-10-27']);
    // Keyingi arizada o'sha original_date bilan qayta belgilangan
    svcExcuse('approved', '2026-10-26', '2026-10-28', ['original_date' => '2026-10-22', 'makeup_date' => '2026-11-03', 'makeup_end_date' => '2026-11-09']);

    expect(RetakeBookingService::excuseWindow(5531, 181, '2026-10-24'))->toBe(['2026-11-03', '2026-11-09']);
});

it('opens booking until the excuse deadline even when the settings deadline passed', function () {
    DB::table('deadlines')->insert(['level_code' => '13', 'deadline_days' => 3]);
    svcExcuse('approved', '2026-10-22', '2026-10-25');
    $student = (object) ['hemis_id' => 5531, 'level_code' => '13'];
    $service = app(RetakeBookingService::class);

    // Sozlama bo'yicha 24.10 + 3 kun = 27.10 — o'tgan; Moodle ham "expired" degan
    [$excused, $plain] = $service->withLmsDeadline([
        svcDebt(['state' => 'expired', 'can_book' => false]),
        svcDebt(['subject_id' => 200, 'state' => 'expired', 'can_book' => false]),
    ], $student);

    expect($excused)->toMatchArray(['deadline' => '2026-11-07', 'book_from' => '2026-11-02', 'excused' => true, 'state' => 'start', 'can_book' => true])
        ->and($plain)->toMatchArray(['deadline' => '2026-10-27', 'state' => 'expired', 'can_book' => false]);

    expect(RetakeBookingService::validateSlot(Carbon::parse('2026-10-31 10:00'), Carbon::parse('2026-10-31 11:00'), '2026-11-07', '2026-11-02'))
        ->toContain('02.11.2026 dan boshlanadi')
        ->and(RetakeBookingService::validateSlot(Carbon::parse('2026-11-07 10:00'), Carbon::parse('2026-11-07 11:00'), '2026-11-07', '2026-11-02'))->toBeNull()
        ->and(RetakeBookingService::validateSlot(Carbon::parse('2026-11-08 10:00'), Carbon::parse('2026-11-08 11:00'), '2026-11-07', '2026-11-02'))->not->toBeNull();
});

it('gives Moodle the LMS deadlines over the X-SYNC-SECRET API', function () {
    config()->set('services.moodle.pull_secret', 'pull-secret');
    Schema::create('students', function ($t) {
        $t->id();
        $t->unsignedBigInteger('hemis_id');
        $t->string('level_code')->nullable();
        $t->timestamps();
    });
    DB::table('students')->insert(['hemis_id' => 5531, 'level_code' => '13']);
    DB::table('deadlines')->insert(['level_code' => '13', 'deadline_days' => 6]);
    svcExcuse('approved', '2026-10-22', '2026-10-25');

    $items = ['items' => [
        ['student_hemis_id' => 5531, 'subject_id' => 181, 'lesson_date' => '2026-10-24'],
        ['student_hemis_id' => 5531, 'subject_id' => 200, 'lesson_date' => '2026-10-24'],
        ['student_hemis_id' => 999, 'subject_id' => 181, 'lesson_date' => '2026-10-24'],
    ]];

    $this->postJson('/api/otrabotka/deadlines', $items)->assertStatus(403);
    $this->postJson('/api/otrabotka/deadlines', $items, ['X-SYNC-SECRET' => 'wrong'])->assertStatus(403);
    $this->postJson('/api/otrabotka/deadlines', ['items' => [['student_hemis_id' => 5531]]], ['X-SYNC-SECRET' => 'pull-secret'])->assertStatus(422);

    $this->postJson('/api/otrabotka/deadlines', $items, ['X-SYNC-SECRET' => 'pull-secret'])
        ->assertOk()
        ->assertExactJson(['ok' => true, 'items' => [
            ['student_hemis_id' => 5531, 'subject_id' => 181, 'lesson_date' => '2026-10-24', 'found' => true, 'deadline' => '2026-11-07', 'book_from' => '2026-11-02', 'excused' => true],
            ['student_hemis_id' => 5531, 'subject_id' => 200, 'lesson_date' => '2026-10-24', 'found' => true, 'deadline' => '2026-10-30', 'book_from' => null, 'excused' => false],
            ['student_hemis_id' => 999, 'subject_id' => 181, 'lesson_date' => '2026-10-24', 'found' => false, 'deadline' => null, 'book_from' => null, 'excused' => false],
        ]]);
});
