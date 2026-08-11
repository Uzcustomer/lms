<?php

use App\Http\Controllers\Admin\TimetableController;
use App\Models\TimetableCard;

function timetableMatchingCard(array $attributes): TimetableCard
{
    $card = new TimetableCard;
    $card->forceFill(array_merge([
        'faculty_name' => '1-son davolash',
        'specialty_name' => 'davolash ishi',
        'course' => 1,
        'subject_name' => 'Odam anatomiyasi 1.2.',
    ], $attributes));

    return $card;
}

function matchingLecture(TimetableCard $practice, array $lectures): ?TimetableCard
{
    $controller = new TimetableController;
    $baseMethod = new ReflectionMethod($controller, 'placementBaseKey');
    $baseMethod->setAccessible(true);
    $matchMethod = new ReflectionMethod($controller, 'matchingLectureForPractice');
    $matchMethod->setAccessible(true);

    $byBase = [];
    foreach ($lectures as $lecture) {
        $byBase[$baseMethod->invoke($controller, $lecture)][] = $lecture;
    }

    return $matchMethod->invoke($controller, $practice, $byBase);
}

test('amaliy eski oqim labeliga qaramay guruh tarkibidagi ma\'ruzaga bog\'lanadi', function () {
    $firstFlow = timetableMatchingCard([
        'id' => 10,
        'training_type' => 'lecture',
        'oqim_label' => '1-oqim',
        'group_names' => ['1K-01a', '1K-01b'],
    ]);
    $secondFlow = timetableMatchingCard([
        'id' => 20,
        'training_type' => 'lecture',
        'oqim_label' => '2-oqim',
        'group_names' => ['1K-05a', '1K-05b'],
    ]);
    $practice = timetableMatchingCard([
        'training_type' => 'practice',
        'oqim_label' => '1-oqim', // importdan qolgan noto'g'ri qiymat
        'group_name' => '1K-05a',
    ]);

    expect(matchingLecture($practice, [$firstFlow, $secondFlow]))->toBe($secondFlow);
});

test('boshqa fan yoki yo\'nalish ma\'ruzasi amaliyga bog\'lanmaydi', function () {
    $lecture = timetableMatchingCard([
        'id' => 10,
        'training_type' => 'lecture',
        'oqim_label' => '1-oqim',
        'group_names' => ['1K-01a'],
        'subject_name' => 'Tibbiy kimyo',
    ]);
    $practice = timetableMatchingCard([
        'training_type' => 'practice',
        'oqim_label' => '1-oqim',
        'group_name' => '1K-01a',
    ]);

    expect(matchingLecture($practice, [$lecture]))->toBeNull();
});
