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
    Http::assertSent(fn (Request $r) => $r['status'] === 'cancelled' && $r['booking_id'] == $b->id);
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
