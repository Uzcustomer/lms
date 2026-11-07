<?php

use App\Services\SababliRetakePlan;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Sababli ariza: kuniga bitta retake, OSKI oxirgi retake kunida, test bir kun oraliq bilan.
 * 2026-11: 1, 8, 15 — yakshanba.
 */

uses(Tests\TestCase::class);

function planItem(string $subject, string $type, string $original, array $extra = []): array
{
    return array_merge([
        'subject_name' => $subject, 'subject_id' => '1', 'assessment_type' => $type,
        'assessment_type_code' => '100', 'original_date' => $original,
    ], $extra);
}

function planDates(array $items, string $start): array
{
    return array_map(fn ($i) => $i['makeup_date'], SababliRetakePlan::assign($items, Carbon::parse($start)));
}

it('gives every missed lesson day its own retake day, OSKI on the last one and the test a day later', function () {
    $items = [];
    foreach (['2026-10-19', '2026-10-20', '2026-10-21', '2026-10-22', '2026-10-23', '2026-10-24'] as $day) {
        $items[] = planItem('Anatomiya', 'jn', $day);
    }
    $items[] = planItem('Anatomiya', 'oski', '2026-10-24');
    $items[] = planItem('Anatomiya', 'test', '2026-10-24');

    expect(planDates($items, '2026-11-09'))->toBe([
        '2026-11-09', '2026-11-10', '2026-11-11', '2026-11-12', '2026-11-13', '2026-11-14',
        '2026-11-14', // OSKI — oxirgi retake kuni
        '2026-11-17', // 15 — yakshanba, 16 — bo'sh kun, 17 — test
    ]);
});

it('starts on Monday when the chosen day is Sunday', function () {
    expect(planDates([planItem('Anatomiya', 'jn', '2026-10-19')], '2026-11-08'))->toBe(['2026-11-09']);
});

it('lets different subjects share a day but never two retakes of one subject', function () {
    // Bir kunda 3 fandan NB — uchalasi bir kunda; Anatomiyaning 2-NB si keyingi kunga
    expect(planDates([
        planItem('Anatomiya', 'jn', '2026-10-19'),
        planItem('Fiziologiya', 'jn', '2026-10-19'),
        planItem('Biokimyo', 'jn', '2026-10-19'),
        planItem('Anatomiya', 'jn', '2026-10-20'),
    ], '2026-11-09'))->toBe(['2026-11-09', '2026-11-09', '2026-11-09', '2026-11-10']);
});

it('puts at most three subjects on one day, earlier lessons first', function () {
    // 7 fan, 8 ta NB (farmoyishdagi holat) — avval hammasi 05.11 ga tushardi
    expect(planDates([
        planItem('Lotin tili va tibbiy terminologiya', 'jn', '2026-10-23'),
        planItem("O'zbekistonning eng yangi tarixi", 'jn', '2026-10-21'),
        planItem('Odam anatomiyasi', 'jn', '2026-10-20'),
        planItem('Tibbiy biologiya. Umumiy genetika', 'jn', '2026-10-22'),
        planItem('Tibbiy kimyo', 'jn', '2026-10-24'),
        planItem('Tibbiyotda axborot texnologiyalari', 'jn', '2026-10-26'),
        planItem('Tibbiyotda xorijiy til', 'jn', '2026-10-23'),
        planItem('Odam anatomiyasi', 'jn', '2026-10-27'),
    ], '2026-11-05'))->toBe([
        '2026-11-06', // 23.10 Lotin — 05.11 to'lgan (20, 21, 22.10)
        '2026-11-05', // 21.10
        '2026-11-05', // 20.10
        '2026-11-05', // 22.10
        '2026-11-06', // 24.10
        '2026-11-07', // 26.10 — 06.11 to'lgan
        '2026-11-06', // 23.10
        '2026-11-07', // 27.10 Anatomiya — 06.11 to'lgan
    ]);
});

it('runs each subject on its own days and skips submitted items', function () {
    $items = [
        planItem('Fiziologiya', 'jn', '2026-10-21'),
        planItem('Anatomiya', 'jn', '2026-10-20'),
        planItem('Anatomiya', 'test', '2026-10-24'),
        planItem('Fiziologiya', 'jn', '2026-10-22'),
        planItem('Anatomiya', 'jn', '2026-10-19'),
        planItem('Fiziologiya', 'mt', '2026-10-22'),
        planItem('Fiziologiya', 'jn', '2026-10-23', ['jn_submitted' => '1']),
    ];

    expect(planDates($items, '2026-11-09'))->toBe([
        '2026-11-09', // Fiziologiya 21-okt — o'z kunlari boshlanish kunidan
        '2026-11-10', // Anatomiya 20-okt
        '2026-11-12', // Anatomiya testi: 10 + bo'sh 11
        '2026-11-10', // Fiziologiya 22-okt
        '2026-11-09', // Anatomiya 19-okt
        '2026-11-10', // MT — fanning oxirgi retake kuni, kun band qilmaydi
        null,         // topshirilgan
    ]);
});

it('places a lone OSKI on the first free day and the test after a gap', function () {
    expect(planDates([
        planItem('Anatomiya', 'test', '2026-10-24'),
        planItem('Anatomiya', 'oski', '2026-10-23'),
    ], '2026-11-09'))->toBe(['2026-11-11', '2026-11-09']);
});

function planGradesTable(): void
{
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('student_grades', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
        $t->string('subject_id');
        $t->string('subject_name');
        $t->dateTime('lesson_date');
        $t->string('lesson_pair_name')->nullable();
        $t->integer('training_type_code');
        $t->double('grade')->nullable();
        $t->double('retake_grade')->nullable();
        $t->string('reason')->nullable();
        $t->softDeletes();
    });
}

function planGrade(string $date, array $extra = []): array
{
    return array_merge([
        'student_hemis_id' => '5531', 'subject_id' => '1', 'subject_name' => 'Anatomiya', 'lesson_date' => $date,
        'training_type_code' => 13, 'grade' => null, 'retake_grade' => null, 'reason' => 'absent', 'deleted_at' => null,
    ], $extra);
}

it('takes retake days only from the NB lessons in the journal', function () {
    planGradesTable();
    DB::table('student_grades')->insert([
        planGrade('2026-10-19 08:30:00'),
        planGrade('2026-10-19 10:10:00'),                                    // o'sha kunning ikkinchi juftligi
        planGrade('2026-10-20 08:30:00', ['reason' => null, 'grade' => 4]), // darsda bo'lgan — baho bor
        planGrade('2026-10-21 08:30:00', ['retake_grade' => 4]),            // otrabotkasi qo'yilgan
        planGrade('2026-10-21 10:10:00', ['training_type_code' => 11]),     // ma'ruza
        planGrade('2026-10-22 08:30:00', ['subject_id' => '2', 'subject_name' => 'Fiziologiya']),
        planGrade('2026-10-23 08:30:00', ['deleted_at' => now()]),
        planGrade('2026-10-30 08:30:00'),                                    // davrdan tashqarida
        planGrade('2026-10-22 08:30:00', ['student_hemis_id' => '9999']),
    ]);

    $days = SababliRetakePlan::nbDays('5531', Carbon::parse('2026-10-19'), Carbon::parse('2026-10-24'));

    expect($days->map(fn ($d) => $d['subject_name'] . ' ' . $d['original_date'])->all())
        ->toBe(['Anatomiya 2026-10-19', 'Fiziologiya 2026-10-22']);
});

it('drops JN items on days the student attended and was graded', function () {
    planGradesTable();
    DB::table('student_grades')->insert([
        planGrade('2026-10-20 08:30:00', ['training_type_code' => 100, 'reason' => null, 'grade' => 4]),
    ]);
    $items = collect([
        planItem('Anatomiya', 'jn', '2026-10-20'),                       // baho olgan — chiqariladi
        planItem('Anatomiya', 'jn', '2026-10-21'),
        planItem('Anatomiya', 'oski', '2026-10-20'),                     // JN emas — qoladi
        planItem('Fiziologiya', 'jn', '2026-10-20', ['subject_id' => '2']),
    ]);

    $kept = SababliRetakePlan::withoutAttended($items, '5531', Carbon::parse('2026-10-19'), Carbon::parse('2026-10-24'));

    expect($kept->map(fn ($i) => $i['subject_name'] . ' ' . $i['assessment_type'] . ' ' . $i['original_date'])->all())
        ->toBe(['Anatomiya jn 2026-10-21', 'Anatomiya oski 2026-10-20', 'Fiziologiya jn 2026-10-20']);
});


function planExcuseTables(): void
{
    planGradesTable();
    Schema::create('absence_excuses', function ($t) {
        $t->id();
        $t->string('student_hemis_id');
        $t->string('status');
        $t->timestamps();
    });
    Schema::create('absence_excuse_makeups', function ($t) {
        $t->id();
        $t->unsignedBigInteger('absence_excuse_id');
        $t->string('subject_name');
        $t->string('assessment_type');
        $t->date('original_date')->nullable();
        $t->date('makeup_date')->nullable();
        $t->date('makeup_end_date')->nullable();
        $t->boolean('jn_submitted')->default(false);
        $t->string('status')->default('scheduled');
        $t->timestamps();
    });
}

function planMakeup(int $excuseId, string $subject, string $type, string $original, ?string $retake, array $extra = []): void
{
    DB::table('absence_excuse_makeups')->insert(array_merge([
        'absence_excuse_id' => $excuseId, 'subject_name' => $subject, 'assessment_type' => $type,
        'original_date' => $original, 'makeup_date' => $retake, 'makeup_end_date' => $type === 'jn' ? $retake : null,
        'jn_submitted' => false, 'status' => 'scheduled',
    ], $extra));
}

function planMakeupDates(): array
{
    return DB::table('absence_excuse_makeups')->orderBy('id')->get()
        ->map(fn ($m) => $m->subject_name . ' ' . $m->assessment_type . ' ' . substr((string) $m->makeup_date, 0, 10)
            . ($m->makeup_end_date ? '/' . substr((string) $m->makeup_end_date, 0, 10) : ''))
        ->all();
}

it('moves the retake days after a late approval and keeps them when approved in time', function () {
    planExcuseTables();
    $id = DB::table('absence_excuses')->insertGetId(['student_hemis_id' => '5531', 'status' => 'pending']);
    // Ariza 02.11 da yuborilgan: retake 03.11 dan
    planMakeup($id, 'Anatomiya', 'jn', '2026-10-20', '2026-11-03');
    planMakeup($id, 'Anatomiya', 'jn', '2026-10-21', '2026-11-04');
    planMakeup($id, 'Anatomiya', 'oski', '2026-10-24', '2026-11-04');
    planMakeup($id, 'Fiziologiya', 'jn', '2026-10-20', '2026-11-03');
    planMakeup($id, 'Fiziologiya', 'jn', '2026-10-22', '2026-10-22', ['jn_submitted' => true, 'status' => 'completed']);
    $excuse = App\Models\AbsenceExcuse::find($id);

    // 02.11 da tasdiqlandi — birinchi retake (03.11) hali kelmagan, o'zgarmaydi
    expect(SababliRetakePlan::rescheduleIfLate($excuse, Carbon::parse('2026-11-03')))->toBeFalse();

    // 06.11 (juma) da tasdiqlandi — 07.11 (shanba) dan qayta; yakshanba o'tkaziladi
    expect(SababliRetakePlan::rescheduleIfLate($excuse, Carbon::parse('2026-11-07')))->toBeTrue()
        ->and(planMakeupDates())->toBe([
            'Anatomiya jn 2026-11-07/2026-11-07',
            'Anatomiya jn 2026-11-09/2026-11-09',
            'Anatomiya oski 2026-11-09',
            'Fiziologiya jn 2026-11-07/2026-11-07',
            'Fiziologiya jn 2026-10-22/2026-10-22', // topshirilgan — tegilmaydi
        ]);
});
