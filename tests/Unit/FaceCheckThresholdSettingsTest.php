<?php

use App\Http\Controllers\Admin\RegistratorFaceCheckController as FaceCheck;
use App\Models\Setting;
use Illuminate\Foundation\Auth\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/**
 * Face ID tekshiruv sahifasidagi ⚙️ oynasi — HEMIS / Mark chegaralari settings jadvalida.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', [
        'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => false,
    ]);
    DB::purge('sqlite');
    Schema::create('settings', function ($t) {
        $t->id();
        $t->string('key')->unique();
        $t->text('value')->nullable();
        $t->timestamps();
    });
    Schema::create('activity_logs', function ($t) {
        $t->id();
        $t->string('guard')->nullable();
        $t->unsignedBigInteger('user_id')->nullable();
        $t->string('user_name')->nullable();
        $t->string('role')->nullable();
        $t->string('module')->nullable();
        $t->string('action')->nullable();
        $t->text('description')->nullable();
        $t->string('subject_type')->nullable();
        $t->unsignedBigInteger('subject_id')->nullable();
        $t->text('old_values')->nullable();
        $t->text('new_values')->nullable();
        $t->text('properties')->nullable();
        $t->string('ip_address')->nullable();
        $t->text('user_agent')->nullable();
        $t->timestamps();
    });
});

function faceCheckUser(bool $admin): void
{
    test()->actingAs(new class ($admin) extends User {
        public function __construct(private bool $admin = true)
        {
        }

        public function hasAnyRole($roles): bool
        {
            return $this->admin;
        }
    });
}

function saveThresholds(array $input)
{
    return (new FaceCheck)->saveSettings(Request::create('/admin/registrator/face-check/settings', 'POST', $input));
}

it('falls back to the built-in 71 / 82', function () {
    expect(FaceCheck::thresholds())->toBe(['hemis' => 71.0, 'mark' => 82.0]);
});

it('lets an admin change the thresholds', function () {
    faceCheckUser(true);

    saveThresholds(['threshold_hemis' => '64', 'threshold_mark' => '80.5']);

    expect(FaceCheck::thresholds())->toBe(['hemis' => 64.0, 'mark' => 80.5])
        ->and(Setting::get(FaceCheck::SETTING_THRESHOLD_HEMIS))->toBe('64');
});

it('rejects values outside 50-99', function () {
    faceCheckUser(true);

    expect(fn () => saveThresholds(['threshold_hemis' => '30', 'threshold_mark' => '82']))
        ->toThrow(ValidationException::class);
    expect(FaceCheck::thresholds()['hemis'])->toBe(71.0);
});

it('does not let registrators change them', function () {
    faceCheckUser(false);

    expect(fn () => saveThresholds(['threshold_hemis' => '60', 'threshold_mark' => '60']))
        ->toThrow(Symfony\Component\HttpKernel\Exception\HttpException::class);
    expect(FaceCheck::thresholds())->toBe(['hemis' => 71.0, 'mark' => 82.0]);
});
