<?php

use App\Services\MtDeadline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * MT muddati — dars jadvalidagi haqiqiy oxirgi dars (MtDeadline). Muddatdan keyin yuklangan ishlar:
 * fayl o'chadi, baho 0; sababli ariza oynasida yuklangan va YN yuborilgan baholarga tegilmaydi.
 */

uses(Tests\TestCase::class);

beforeEach(function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Storage::fake('public');
    Schema::create('settings', fn ($t) => [$t->id(), $t->string('key'), $t->text('value')->nullable(), $t->timestamps()]);
    Schema::create('schedules', fn ($t) => [$t->id(), $t->string('group_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->dateTime('lesson_date')->nullable(), $t->softDeletes()]);
    Schema::create('curriculum_subjects', fn ($t) => [$t->id(), $t->string('curriculum_subject_hemis_id'), $t->string('subject_id')]);
    Schema::create('independents', fn ($t) => [$t->id(), $t->string('group_hemis_id'), $t->string('group_name'), $t->string('subject_hemis_id'),
        $t->string('subject_name'), $t->string('semester_code'), $t->date('deadline')->nullable(), $t->timestamps()]);
    Schema::create('students', fn ($t) => [$t->id(), $t->string('hemis_id'), $t->string('full_name'), $t->string('telegram_chat_id')->nullable(),
        $t->timestamp('telegram_verified_at')->nullable()]);
    Schema::create('independent_submissions', fn ($t) => [$t->id(), $t->unsignedBigInteger('independent_id'), $t->unsignedBigInteger('student_id'),
        $t->string('student_hemis_id'), $t->string('file_path'), $t->string('file_original_name'), $t->dateTime('submitted_at')]);
    Schema::create('student_grades', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('subject_id'), $t->string('semester_code'),
        $t->integer('training_type_code'), $t->date('lesson_date')->nullable(), $t->decimal('grade')->nullable(), $t->text('grade_comment')->nullable(),
        $t->boolean('is_yn_locked')->default(false), $t->softDeletes(), $t->timestamps()]);
    Schema::create('absence_excuses', fn ($t) => [$t->id(), $t->string('student_hemis_id'), $t->string('status'), $t->date('start_date'),
        $t->date('end_date'), $t->timestamp('reviewed_at')->nullable(), $t->timestamps()]);
    Schema::create('absence_excuse_makeups', fn ($t) => [$t->id(), $t->unsignedBigInteger('absence_excuse_id'), $t->string('subject_id')->nullable(),
        $t->string('assessment_type'), $t->timestamps()]);
    Schema::create('activity_logs', fn ($t) => [$t->id(), $t->timestamps()]);

    DB::table('settings')->insert([['key' => 'mt_deadline_type', 'value' => 'last'], ['key' => 'mt_deadline_time', 'value' => '23:59']]);
    // Darslar 31.10 va 07.11 — muddat 07.11 23:59
    DB::table('schedules')->insert([
        ['group_id' => '929', 'subject_id' => '171', 'semester_code' => '15', 'lesson_date' => '2026-10-31 00:00:00', 'deleted_at' => null],
        ['group_id' => '929', 'subject_id' => '171', 'semester_code' => '15', 'lesson_date' => '2026-11-07 00:00:00', 'deleted_at' => null],
        ['group_id' => '929', 'subject_id' => '171', 'semester_code' => '15', 'lesson_date' => '2026-11-20 00:00:00', 'deleted_at' => '2026-11-01'],
    ]);
    DB::table('curriculum_subjects')->insert(['curriculum_subject_hemis_id' => 'cs-171', 'subject_id' => '171']);
    DB::table('independents')->insert(['id' => 1, 'group_hemis_id' => '929', 'group_name' => 'p/p24-04a', 'subject_hemis_id' => 'cs-171',
        'subject_name' => 'Ichki kasalliklar propedevtikasi (a)', 'semester_code' => '15', 'deadline' => '2026-12-20']);

    $this->student = function (int $id, string $submittedAt, ?float $grade = 90, bool $locked = false) {
        DB::table('students')->insert(['id' => $id, 'hemis_id' => "h{$id}", 'full_name' => "TALABA {$id}"]);
        Storage::disk('public')->put("independent-submissions/h{$id}/a.pptx", 'x');
        DB::table('independent_submissions')->insert(['independent_id' => 1, 'student_id' => $id, 'student_hemis_id' => "h{$id}",
            'file_path' => "independent-submissions/h{$id}/a.pptx", 'file_original_name' => 'a.pptx', 'submitted_at' => $submittedAt]);
        if ($grade !== null) {
            DB::table('student_grades')->insert(['student_hemis_id' => "h{$id}", 'subject_id' => '171', 'semester_code' => '15',
                'training_type_code' => 99, 'grade' => $grade, 'is_yn_locked' => $locked]);
        }
    };
});

it('takes the MT deadline from the real last lesson, never from a pushed database value', function () {
    $independent = \App\Models\Independent::find(1);
    expect(MtDeadline::forIndependent($independent))->toBe('2026-11-07')                 // o'chirilgan 20.11 darsi hisobga olinmaydi
        ->and($independent->fresh()->deadline)->toStartWith('2026-11-07')               // bazadagi surilgan muddat tuzatildi
        ->and(MtDeadline::closesAt('2026-11-07')->format('Y-m-d H:i'))->toBe('2026-11-07 23:59');

    DB::table('settings')->where('key', 'mt_deadline_type')->update(['value' => 'before_last']);
    expect(MtDeadline::date('929', '171', '15'))->toBe('2026-10-31');
});

it('zeroes late MT submissions and deletes their files, leaving on-time, sababli and YN-locked ones', function () {
    ($this->student)(1, '2026-11-07 20:20:00');                 // vaqtida (23:59 gacha)
    ($this->student)(2, '2026-11-11 08:53:30');                 // kech
    ($this->student)(3, '2026-11-11 08:55:00', null);            // kech, baho qo'yilmagan
    ($this->student)(4, '2026-11-11 09:00:00');                 // kech, lekin sababli ariza oynasida
    ($this->student)(5, '2026-11-11 09:01:00', 90, true);        // kech, YN yuborilgan
    $excuse = DB::table('absence_excuses')->insertGetId(['student_hemis_id' => 'h4', 'status' => 'approved',
        'start_date' => '2026-11-03', 'end_date' => '2026-11-09', 'reviewed_at' => '2026-11-10 10:00:00']);
    DB::table('absence_excuse_makeups')->insert(['absence_excuse_id' => $excuse, 'subject_id' => '171', 'assessment_type' => 'mt']);

    $this->artisan('mt:zero-late')->assertSuccessful();             // hisobot — hech narsa o'zgarmaydi
    expect(DB::table('independent_submissions')->count())->toBe(5);

    $this->artisan('mt:zero-late --force --no-notify')->assertSuccessful();
    $grade = fn (int $id) => DB::table('student_grades')->where('student_hemis_id', "h{$id}")->value('grade');
    expect(DB::table('independent_submissions')->orderBy('student_id')->pluck('student_id')->all())->toBe([1, 4, 5])
        ->and((float) $grade(1))->toBe(90.0)
        ->and((float) $grade(2))->toBe(0.0)
        ->and(DB::table('student_grades')->where('student_hemis_id', 'h2')->value('grade_comment'))->toContain('Muddatdan keyin yuklangan')
        ->and((float) $grade(4))->toBe(90.0)
        ->and((float) $grade(5))->toBe(90.0);
    Storage::disk('public')->assertMissing('independent-submissions/h2/a.pptx');
    Storage::disk('public')->assertMissing('independent-submissions/h3/a.pptx');
    Storage::disk('public')->assertExists('independent-submissions/h1/a.pptx');
});
