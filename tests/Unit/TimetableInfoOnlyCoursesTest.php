<?php

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Services\Timetable\InfoOnlyCourses;

it('defaults to course six and honors an explicitly empty list', function () {
    $board = new TimetableBoard();
    expect(InfoOnlyCourses::isHemisExcluded(new TimetableCard(['course' => 6]), $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded(['course' => 5], $board))->toBeFalse();
    $board->settings = ['info_only_courses' => []];
    expect(InfoOnlyCourses::isHemisExcluded(['course' => 6], $board))->toBeFalse();
});

it('uses the current board setting for cards, cycle units and semester exclusions', function () {
    $board = new TimetableBoard(['settings' => ['info_only_courses' => [3, '5']]]);
    expect(InfoOnlyCourses::isHemisExcluded(['course' => 3], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded((object) ['course' => 5], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded(['course' => 6], $board))->toBeFalse()
        ->and(InfoOnlyCourses::semesterCodes($board))->toBe(['15', '16', '19', '20']);
    $board->settings = ['info_only_courses' => [6]];
    expect(InfoOnlyCourses::courses($board))->toBe([6])
        ->and(InfoOnlyCourses::hemisNotice($board))->toBe('6-kurs — HEMISga yozilmaydi');
});

it('matches subject fragments with case and apostrophe normalization without excluding their export', function (string $name) {
    $board = new TimetableBoard();
    $card = ['course' => 3, 'subject_name' => $name];
    expect(InfoOnlyCourses::isNonBlocking($card, $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded($card, $board))->toBeFalse()
        ->and(InfoOnlyCourses::subjectMatch($card, $board))->toBe("O'quv amaliyoti");
})->with(["O'quv amaliyoti", 'Kasbiy O‘QUV AMALIYOTI (nazorat)', 'Oʻquv amaliyoti', 'O`quv amaliyoti', 'Oʼquv  amaliyoti']);

it('invalidates subject matches when settings change and honors empty subject lists independently of courses', function () {
    $board = new TimetableBoard();
    $card = (object) ['course' => 3, 'subject' => "O'quv amaliyoti (a)"];
    expect(InfoOnlyCourses::isNonBlocking($card, $board))->toBeTrue()
        ->and(InfoOnlyCourses::isNonBlocking(['course' => 3, 'subject_name' => 'Farmakologiya'], $board))->toBeFalse();
    $board->settings = ['info_only_subjects' => []];
    expect(InfoOnlyCourses::isNonBlocking($card, $board))->toBeFalse()
        ->and(InfoOnlyCourses::isNonBlocking(['course' => 6], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded(['course' => 6, 'subject' => "O'quv amaliyoti"], $board))->toBeTrue();
    $board->settings = ['info_only_courses' => [], 'info_only_subjects' => ['Farmakologiya', 'farmakologiya', ' ', 'Nazorat']];
    expect(InfoOnlyCourses::subjects($board))->toBe(['Farmakologiya', 'Nazorat'])
        ->and(InfoOnlyCourses::isNonBlocking(['course' => 3, 'subject_name' => 'Farmakologiya (a)'], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isHemisExcluded(['course' => 6], $board))->toBeFalse();
});
