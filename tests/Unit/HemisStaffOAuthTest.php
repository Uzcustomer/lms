<?php

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

/**
 * Xodimlar HEMIS (hemis.ttatf.uz) orqali kirishi: state tekshiriladi, code → token → foydalanuvchi,
 * xodim LMS bazasidan Xodim ID (employee_id_number) yoki login bo'yicha topilib, teacher guard bilan kiradi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('teachers', fn ($t) => [$t->id(), $t->string('full_name')->nullable(), $t->string('login')->nullable(),
        $t->string('employee_id_number')->nullable(), $t->string('password')->nullable(), $t->string('phone')->nullable(),
        $t->timestamp('telegram_verified_at')->nullable(), $t->boolean('must_change_password')->default(false),
        $t->rememberToken(), $t->timestamps()]);
    Schema::create('roles', fn ($t) => [$t->id(), $t->string('name'), $t->string('guard_name'), $t->timestamps()]);
    Schema::create('model_has_roles', fn ($t) => [$t->unsignedBigInteger('role_id'), $t->string('model_type'), $t->unsignedBigInteger('model_id')]);
    Schema::create('activity_logs', fn ($t) => [$t->id(), $t->timestamps()]);

    config()->set('services.hemis_staff_oauth', [
        'base_url' => 'https://hemis.test', 'client_id' => '55', 'client_secret' => 'secret',
        'redirect_uri' => 'https://lms.test/auth/hemis-staff/callback',
    ]);
    DB::table('teachers')->insert([
        ['id' => 1, 'full_name' => 'Kaxxarov N.', 'login' => 'kaxxarov', 'employee_id_number' => '3682111026', 'phone' => '+998901234567', 'telegram_verified_at' => now()],
        ['id' => 2, 'full_name' => 'Boshqa xodim', 'login' => '3682111026x', 'employee_id_number' => '3682000001', 'phone' => null, 'telegram_verified_at' => null],
    ]);
});

function fakeHemis(array $user): void
{
    Http::fake([
        'hemis.test/oauth/access-token' => Http::response(['access_token' => 'tok']),
        'hemis.test/oauth/api/user*' => Http::response($user),
    ]);
}

it('redirects to the HEMIS employee portal with state', function () {
    $response = $this->get('/auth/hemis-staff/redirect');

    $response->assertRedirect();
    expect($response->headers->get('Location'))
        ->toStartWith('https://hemis.test/oauth/authorize?')
        ->toContain('client_id=55')
        ->toContain(urlencode('https://lms.test/auth/hemis-staff/callback'));
});

it('logs the employee in by Xodim ID', function () {
    fakeHemis(['id' => 999, 'login' => '3682111026', 'name' => 'Kaxxarov']);

    $this->withSession(['hemis_staff_oauth_state' => 'st'])
        ->get('/auth/hemis-staff/callback?code=c&state=st')
        ->assertRedirect(route('teacher.dashboard'));

    expect(Auth::guard('teacher')->id())->toBe(1);
});

it('rejects a wrong state without calling HEMIS', function () {
    fakeHemis(['id' => 1, 'login' => '3682111026']);
    $this->withSession(['hemis_staff_oauth_state' => 'st'])
        ->get('/auth/hemis-staff/callback?code=c&state=other')
        ->assertRedirect('/');
    expect(Auth::guard('teacher')->check())->toBeFalse();
    Http::assertNothingSent();
});

it('rejects employees missing from LMS', function () {
    fakeHemis(['id' => 2, 'login' => '9999999999']);
    $this->withSession(['hemis_staff_oauth_state' => 'st'])
        ->get('/auth/hemis-staff/callback?code=c&state=st')
        ->assertRedirect('/');
    expect(Auth::guard('teacher')->check())->toBeFalse();
});

function logoutTeacher(?string $loginVia): \Symfony\Component\HttpFoundation\Response
{
    $request = \Illuminate\Http\Request::create('/teacher/logout', 'POST');
    $request->setLaravelSession($session = app('session')->driver());
    $session->start();
    if ($loginVia) {
        $session->put('login_via', $loginVia);
    }
    app()->instance('request', $request);
    Auth::guard('teacher')->login(\App\Models\Teacher::find(1));

    return app(\App\Http\Controllers\Teacher\TeacherAuthController::class)->logout($request);
}

it('marks HEMIS logins so logout can close the HEMIS session too', function () {
    fakeHemis(['id' => 999, 'login' => '3682111026']);
    $this->withSession(['hemis_staff_oauth_state' => 'st'])
        ->get('/auth/hemis-staff/callback?code=c&state=st')
        ->assertSessionHas('login_via', 'hemis_staff');
});

it('sends HEMIS users to the HEMIS logout page, others to the home page', function () {
    config()->set('services.hemis_staff_oauth.logout_url', 'https://hemis.test/dashboard/logout');

    expect(logoutTeacher('hemis_staff')->headers->get('Location'))->toBe('https://hemis.test/dashboard/logout')
        ->and(Auth::guard('teacher')->check())->toBeFalse()
        ->and(logoutTeacher(null)->headers->get('Location'))->toBe(url('/'));

    // Chiqish manzili sozlanmagan — avvalgidek bosh sahifa
    config()->set('services.hemis_staff_oauth.logout_url', null);
    expect(logoutTeacher('hemis_staff')->headers->get('Location'))->toBe(url('/'));
});
