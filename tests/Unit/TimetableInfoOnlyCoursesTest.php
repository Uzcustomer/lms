<?php

use App\Models\TimetableBoard;
use App\Models\TimetableCard;
use App\Services\Timetable\InfoOnlyCourses;

it('defaults to course six and honors an explicitly empty list', function () {
    $board = new TimetableBoard();
    expect(InfoOnlyCourses::isInfoOnly(new TimetableCard(['course' => 6]), $board))->toBeTrue()
        ->and(InfoOnlyCourses::isInfoOnly(['course' => 5], $board))->toBeFalse();
    $board->settings = ['info_only_courses' => []];
    expect(InfoOnlyCourses::isInfoOnly(['course' => 6], $board))->toBeFalse();
});

it('uses the current board setting for cards, cycle units and semester exclusions', function () {
    $board = new TimetableBoard(['settings' => ['info_only_courses' => [3, '5']]]);
    expect(InfoOnlyCourses::isInfoOnly(['course' => 3], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isInfoOnly((object) ['course' => 5], $board))->toBeTrue()
        ->and(InfoOnlyCourses::isInfoOnly(['course' => 6], $board))->toBeFalse()
        ->and(InfoOnlyCourses::semesterCodes($board))->toBe(['15', '16', '19', '20']);
    $board->settings = ['info_only_courses' => [6]];
    expect(InfoOnlyCourses::courses($board))->toBe([6])
        ->and(InfoOnlyCourses::hemisNotice($board))->toBe('6-kurs — HEMISga yozilmaydi');
});
