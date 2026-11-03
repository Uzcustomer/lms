<?php

use App\Http\Controllers\Admin\RegistratorFaceCheckController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Face ID yo'q talabalar: Moodle tasdiqlagan yuzi borlar chiqmaydi, qolganlariga sabab.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('students', function ($t) {
        $t->id();
        $t->string('student_id_number');
        $t->string('full_name');
        $t->string('department_name')->nullable();
        $t->string('group_name')->nullable();
        $t->string('level_code')->nullable();
        $t->string('level_name')->nullable();
        $t->string('student_status_code')->nullable();
        $t->timestamps();
    });
    Schema::create('student_photos', function ($t) {
        $t->id();
        $t->string('student_id_number');
        $t->string('status');
        $t->string('moodle_sync_status')->nullable();
        $t->text('moodle_sync_error')->nullable();
        $t->text('rejection_reason')->nullable();
        $t->dateTime('descriptor_confirmed_at')->nullable();
        $t->timestamps();
    });
});

function fmStudent(string $id, string $status = '11', array $extra = []): void
{
    DB::table('students')->insert(array_merge([
        'student_id_number' => $id, 'full_name' => 'Talaba ' . $id, 'department_name' => 'Davolash',
        'group_name' => '101', 'level_code' => '11', 'level_name' => '1-kurs', 'student_status_code' => $status,
    ], $extra));
}

function fmPhoto(string $id, string $status, array $extra = []): void
{
    DB::table('student_photos')->insert(array_merge(['student_id_number' => $id, 'status' => $status], $extra));
}

it('lists active students without a Moodle-confirmed face, with the reason', function () {
    fmStudent('1');                                                                // rasm yo'q
    fmStudent('2'); fmPhoto('2', 'rejected', ['rejection_reason' => 'Xira']);       // rad etilgan
    fmStudent('3'); fmPhoto('3', 'rejected'); fmPhoto('3', 'pending');               // tekshiruvda
    fmStudent('4'); fmPhoto('4', 'approved', ['moodle_sync_status' => 'moodle_face_api_failed', 'moodle_sync_error' => 'Yuz topilmadi']);
    fmStudent('5'); fmPhoto('5', 'approved');                                        // Moodle'da ishlanmoqda
    fmStudent('6'); fmPhoto('6', 'approved', ['descriptor_confirmed_at' => now()]);  // Face ID bor — chiqmaydi
    fmStudent('7', '60');                                                            // faol emas — chiqmaydi

    $view = app(RegistratorFaceCheckController::class)->missing(Request::create('/'));
    $data = $view->getData();

    expect($data['total'])->toBe(5)
        ->and($data['counts']->all())->toBe(['no_photo' => 1, 'rejected' => 1, 'pending' => 1, 'moodle_failed' => 1, 'processing' => 1])
        ->and($data['states']->map(fn ($s) => $s['key'] . ($s['note'] ? ':' . $s['note'] : ''))->all())->toBe([
            '1' => 'no_photo', '2' => 'rejected:Xira', '3' => 'pending', '4' => 'moodle_failed:Yuz topilmadi', '5' => 'processing',
        ]);

    $pending = app(RegistratorFaceCheckController::class)->missing(Request::create('/', 'GET', ['state' => 'pending']))->getData();
    expect($pending['students']->pluck('student_id_number')->all())->toBe(['3']);
});
