<?php

namespace App\Services\Timetable;

/** Shared, deterministic lookup for legacy defaults and HEMIS curriculum overrides. */
class ProgramCalendar
{
    public static function key(?string $faculty, string $specialty, int $course, int $curriculum = 0): string
    {
        return mb_strtolower(trim((string) $faculty)).'|'.mb_strtolower(trim($specialty)).'|'.$course.'|'.$curriculum;
    }

    public static function select(iterable $settings, ?string $faculty, string $specialty, int $course, int $curriculum = 0): ?object
    {
        $best = null;
        $rank = -1;
        foreach ($settings as $setting) {
            $s = (object) $setting;
            if (mb_strtolower(trim($s->specialty_name)) !== mb_strtolower(trim($specialty)) || (int) $s->course !== $course) {
                continue;
            }
            $fac = mb_strtolower(trim((string) ($s->faculty_name ?? '')));
            $wanted = mb_strtolower(trim((string) $faculty));
            $program = (int) ($s->curriculum_hemis_id ?? 0);
            if (($fac !== '' && $fac !== $wanted) || ($program !== 0 && $program !== $curriculum)) {
                continue;
            }
            // An exact curriculum always wins; never use another curriculum's setting.
            $score = ($program > 0 ? 2 : 0) + ($fac === $wanted && $fac !== '' ? 1 : 0);
            if ($score > $rank) {
                $best = $s;
                $rank = $score;
            }
        }

        return $best;
    }

    public static function groupKey(string $name): string
    {
        $name = str_replace(['‘', '’', 'ʻ', 'ʼ', '`', '´'], "'", mb_strtolower(trim($name)));

        return preg_replace('/\s+/u', '', $name);
    }
}
