<?php

namespace App\Services\Timetable;

use App\Models\TimetableBoard;

/** Separate occupancy exemptions from the course-only HEMIS export policy. */
final class InfoOnlyCourses
{
    private static ?\WeakMap $policies = null;

    private static function policy(TimetableBoard $board): object
    {
        self::$policies ??= new \WeakMap();
        $raw = $board->getAttributes()['settings'] ?? null;
        $cached = self::$policies[$board] ?? null;
        if ($cached !== null && $cached->raw === $raw) return $cached;
        $settings = $board->settings ?? [];
        $courses = $settings['info_only_courses'] ?? [6];
        $courses = is_array($courses) ? $courses : [6];
        $courses = array_values(array_unique(array_map('intval', array_filter($courses,
            fn ($course) => (is_int($course) || (is_string($course) && ctype_digit($course)))
                && (int) $course >= 1 && (int) $course <= 6))));
        $subjects = self::normalizeSubjects(is_array($settings['info_only_subjects'] ?? null)
            ? $settings['info_only_subjects'] : ["O'quv amaliyoti"]);

        return self::$policies[$board] = (object) ['raw' => $raw, 'courses' => $courses,
            'set' => array_fill_keys($courses, true), 'subjects' => $subjects,
            'needles' => array_map(self::normalizeSubject(...), $subjects), 'matches' => []];
    }

    public static function normalizeSubject(string $name): string
    {
        $name = strtr($name, ['’' => "'", '‘' => "'", 'ʻ' => "'", 'ʼ' => "'", '`' => "'", '´' => "'", '‛' => "'", '＇' => "'"]);
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $name)));
    }

    public static function normalizeSubjects(array $subjects): array
    {
        $unique = [];
        foreach ($subjects as $subject) {
            if (is_string($subject) && ($key = self::normalizeSubject($subject)) !== '') $unique[$key] ??= trim($subject);
        }
        return array_values($unique);
    }

    public static function courses(TimetableBoard $board): array
    {
        return self::policy($board)->courses;
    }

    public static function subjects(TimetableBoard $board): array
    {
        return self::policy($board)->subjects;
    }

    public static function isHemisExcluded($card, TimetableBoard $board): bool
    {
        $course = (int) (is_array($card) ? ($card['course'] ?? 0) : ($card->course ?? 0));
        return isset(self::policy($board)->set[$course]);
    }

    /** Return the configured matching fragment; memoize each distinct name per board/settings. */
    public static function subjectMatch($card, TimetableBoard $board): ?string
    {
        $name = (string) (is_array($card) ? ($card['subject_name'] ?? $card['subject'] ?? '') : ($card->subject_name ?? $card->subject ?? ''));
        $policy = self::policy($board);
        if (array_key_exists($name, $policy->matches)) return $policy->matches[$name];
        $normalized = self::normalizeSubject($name);
        foreach ($policy->needles as $i => $needle) {
            if (str_contains($normalized, $needle)) return $policy->matches[$name] = $policy->subjects[$i];
        }
        return $policy->matches[$name] = null;
    }

    public static function isNonBlocking($card, TimetableBoard $board): bool
    {
        return self::isHemisExcluded($card, $board) || self::subjectMatch($card, $board) !== null;
    }

    public static function semesterCodes(TimetableBoard $board): array
    {
        $codes = [];
        foreach (self::courses($board) as $course) array_push($codes, (string) (9 + 2 * $course), (string) (10 + 2 * $course));
        return $codes;
    }

    public static function hemisNotice(TimetableBoard $board): string
    {
        $courses = self::courses($board);
        return $courses ? implode(', ', $courses) . '-kurs — HEMISga yozilmaydi' : '';
    }
}
