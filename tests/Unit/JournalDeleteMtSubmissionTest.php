<?php

use App\Http\Controllers\Admin\JournalController;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Jurnal: superadmin talabaning MT faylini o'chiradi ("Superadmin MT fayl yuklash" toggle bilan).
 * Avval independent_submissions da yo'q ustunlar (subject_id, semester_code) o'qilib 500 chiqardi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Storage::fake('public');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('independents', fn ($t) => [$t->id(), $t->string('subject_hemis_id'), $t->string('semester_code')]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curriculum_subject_hemis_id'), $t->string('subject_id')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'), $t->boolean('is_yn_locked')->default(false)]);
    Schema::create('independent_submissions', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->unsignedBigInteger('student_id'),
        $t->string('student_hemis_id'), $t->string('file_path'), $t->string('file_original_name')]);

    DB::table('independents')->insert(['id' => 5, 'subject_hemis_id' => 'cs-1', 'semester_code' => '11']);
    DB::table('curriculum_subjects')->insert(['curriculum_subject_hemis_id' => 'cs-1', 'subject_id' => '700']);
});

function mtDelAs(array $roles): void
{
    Auth::setUser(new class($roles) implements Authenticatable {
        public function __construct(private array $roles) {}
        public function hasRole($r) { return in_array($r, $this->roles, true); }
        public function getAuthIdentifierName() { return 'id'; }
        public function getAuthIdentifier() { return 1; }
        public function getAuthPasswordName() { return 'password'; }
        public function getAuthPassword() { return ''; }
        public function getRememberToken() { return null; }
        public function setRememberToken($value) {}
        public function getRememberTokenName() { return ''; }
    });
}

function mtDelSubmission(): int
{
    Storage::disk('public')->put('independent-submissions/5531/a.zip', 'x');

    return DB::table('independent_submissions')->insertGetId([
        'independent_id' => 5, 'student_id' => 1, 'student_hemis_id' => '5531',
        'file_path' => 'independent-submissions/5531/a.zip', 'file_original_name' => 'a.zip',
    ]);
}

function mtDelCall(int $id)
{
    return app(JournalController::class)->deleteMtSubmission(Request::create('/', 'POST', ['submission_id' => $id]));
}

it('lets a superadmin delete a student MT file only while the toggle is on', function () {
    $id = mtDelSubmission();

    mtDelAs(['superadmin']);
    expect(mtDelCall($id)->getStatusCode())->toBe(403);                // toggle o'chiq

    DB::table('settings')->insert(['key' => 'feature_superadmin_mt_upload_after_deadline', 'value' => '1']);
    mtDelAs(['admin']);
    expect(mtDelCall($id)->getStatusCode())->toBe(403);                // admin emas

    mtDelAs(['superadmin']);
    $res = mtDelCall($id);
    expect($res->getStatusCode())->toBe(200)
        ->and($res->getData(true)['success'])->toBeTrue()
        ->and(DB::table('independent_submissions')->count())->toBe(0)
        ->and(Storage::disk('public')->exists('independent-submissions/5531/a.zip'))->toBeFalse();
});

it('keeps the file once the subject was sent to YN', function () {
    DB::table('settings')->insert(['key' => 'feature_superadmin_mt_upload_after_deadline', 'value' => '1']);
    DB::table('student_grades')->insert(['student_hemis_id' => '5531', 'subject_id' => '700', 'semester_code' => '11', 'is_yn_locked' => true]);
    $id = mtDelSubmission();
    mtDelAs(['superadmin']);

    expect(mtDelCall($id)->getStatusCode())->toBe(403)
        ->and(DB::table('independent_submissions')->count())->toBe(1);
});
