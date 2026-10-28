<?php

use App\Services\Timetable\ProgramCalendar;

it('keeps same-faculty PIMU and daytime durations independent regardless of row order', function () {
    $common = ['faculty_name' => 'Xalqaro', 'specialty_name' => 'davolash ishi', 'course' => 1];
    $legacy = (object) ($common + ['weeks' => 15]);
    $daytime = (object) ($common + ['curriculum_hemis_id' => 101, 'weeks' => 15]);
    $pimu = (object) ($common + ['curriculum_hemis_id' => 202, 'weeks' => 17]);
    foreach ([[$pimu, $legacy, $daytime], [$daytime, $pimu, $legacy]] as $rows) {
        expect(ProgramCalendar::select($rows, 'Xalqaro', 'davolash ishi', 1, 202)->weeks)->toBe(17)
            ->and(ProgramCalendar::select($rows, 'Xalqaro', 'davolash ishi', 1, 101)->weeks)->toBe(15)
            ->and(ProgramCalendar::select($rows, 'Xalqaro', 'davolash ishi', 1, 303)->weeks)->toBe(15)
            ->and(ProgramCalendar::select($rows, 'Xalqaro', 'davolash ishi', 1)->weeks)->toBe(15);
    }
});

it('never borrows a calendar from another curriculum, faculty or course', function () {
    $rows = [(object) ['faculty_name' => 'F', 'specialty_name' => 'stomatologiya', 'course' => 1,
        'curriculum_hemis_id' => 202, 'weeks' => 17]];
    expect(ProgramCalendar::select($rows, 'F', 'stomatologiya', 1, 101))->toBeNull()
        ->and(ProgramCalendar::select($rows, 'Other', 'stomatologiya', 1, 202))->toBeNull()
        ->and(ProgramCalendar::select($rows, 'F', 'stomatologiya', 2, 202))->toBeNull();
});

it('inherits legacy calendars while keeping explicit empty holiday overrides', function () {
    $rows = [(object) ['faculty_name' => null, 'specialty_name' => 'Davolash ishi', 'course' => 1, 'weeks' => 15],
        (object) ['faculty_name' => 'F', 'specialty_name' => 'Davolash ishi', 'course' => 1, 'curriculum_hemis_id' => 202,
            'weeks' => 17, 'start_date' => '2026-09-21', 'weekly_breaks' => []]];
    $program = ProgramCalendar::select($rows, 'F', 'davolash ishi', 1, 202);
    expect($program->weeks)->toBe(17)->and($program->weekly_breaks)->toBe([])
        ->and(ProgramCalendar::select($rows, 'F', 'davolash ishi', 1, 101)->weeks)->toBe(15);
});
