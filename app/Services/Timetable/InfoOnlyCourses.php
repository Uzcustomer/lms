<?php

namespace App\Services\Timetable;

use App\Models\TimetableBoard;

/** One policy for cards, cycle units and normalized HEMIS records. */
final class InfoOnlyCourses
{
    private static ?\WeakMap $policies = null;

    private static function policy(TimetableBoard $board): array
    {
        self::$policies ??= new \WeakMap();
        $raw = $board->getAttributes()['settings'] ?? null;
        $cached = self::$policies[$board] ?? null;
        if ($cached !== null && $cached['raw'] === $raw) {
            return $cached;
        }
        $settings = $board->settings ?? [];
        $courses = $settings['info_only_courses'] ?? [6];
        $courses = is_array($courses) ? $courses : [6];
        $courses = array_values(array_unique(array_map('intval', array_filter($courses,
            fn ($course) => (is_int($course) || (is_string($course) && ctype_digit($course)))
                && (int) $course >= 1 && (int) $course <= 6))));

        return self::$policies[$board] = ['raw' => $raw, 'courses' => $courses, 'set' => array_fill_keys($courses, true)];
    }

    public static function courses(TimetableBoard $board): array
    {
        return self::policy($board)['courses'];
    }

    public static function isInfoOnly($card, TimetableBoard $board): bool
    {
        $course = (int) (is_array($card) ? ($card['course'] ?? 0) : ($card->course ?? 0));

        return isset(self::policy($board)['set'][$course]);
    }

    public static function semesterCodes(TimetableBoard $board): array
    {
        $codes = [];
        foreach (self::courses($board) as $course) {
            array_push($codes, (string) (9 + 2 * $course), (string) (10 + 2 * $course));
        }
        return $codes;
    }

    public static function hemisNotice(TimetableBoard $board): string
    {
        $courses = self::courses($board);

        return $courses ? implode(', ', $courses) . '-kurs — HEMISga yozilmaydi' : '';
    }
}
