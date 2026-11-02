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

it('lays subjects out one after another and skips submitted items', function () {
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
        '2026-11-13', // Fiziologiya 21-okt — Anatomiya testidan keyin
        '2026-11-10', // Anatomiya 20-okt
        '2026-11-12', // Anatomiya testi: 10 + bo'sh 11
        '2026-11-14', // Fiziologiya 22-okt
        '2026-11-09', // Anatomiya 19-okt
        '2026-11-14', // MT — fanning oxirgi retake kuni, kun band qilmaydi
        null,         // topshirilgan
    ]);
});

it('places a lone OSKI on the first free day and the test after a gap', function () {
    expect(planDates([
        planItem('Anatomiya', 'test', '2026-10-24'),
        planItem('Anatomiya', 'oski', '2026-10-23'),
    ], '2026-11-09'))->toBe(['2026-11-11', '2026-11-09']);
});

it('collects graded lesson days from the timetable, one per subject and day', function () {
    config()->set('database.default', 'sqlite');
    config()->set('database.connections.sqlite', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    DB::purge('sqlite');
    Schema::create('schedules', function ($t) {
        $t->id();
        $t->unsignedBigInteger('group_id');
        $t->string('subject_id');
        $t->string('subject_name');
        $t->dateTime('lesson_date');
        $t->integer('training_type_code');
        $t->softDeletes();
    });
    $row = fn ($date, $type, $subject = 'Anatomiya') => ['group_id' => 7, 'subject_id' => '1', 'subject_name' => $subject, 'lesson_date' => $date, 'training_type_code' => $type];
    DB::table('schedules')->insert([
        $row('2026-10-19 08:30:00', 13),
        $row('2026-10-19 10:10:00', 13), // o'sha kunning ikkinchi juftligi
        $row('2026-10-20 08:30:00', 11), // ma'ruza — baho yo'q
        $row('2026-10-21 08:30:00', 101), // OSKI — alohida nazorat
        $row('2026-10-21 10:10:00', 13, 'Fiziologiya'),
        $row('2026-10-30 08:30:00', 13), // davrdan tashqarida
    ]);

    $days = SababliRetakePlan::lessonDays(7, Carbon::parse('2026-10-19'), Carbon::parse('2026-10-24'));

    expect($days->map(fn ($d) => $d['subject_name'] . ' ' . $d['original_date'])->all())
        ->toBe(['Anatomiya 2026-10-19', 'Fiziologiya 2026-10-21']);
});
