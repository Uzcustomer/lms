<?php

namespace App\Services;

use Carbon\Carbon;

/**
 * KTR haftalarini HEMIS o'quv haftalariga (curriculum_weeks) bog'lash.
 *
 * HEMISda ba'zi o'quv rejalarda kuzgi semestr haftalari 1-sentabrdan
 * boshlanadi, darslar esa 14-sentabrdan boshlanadi. KTR ning 1-haftasi
 * birinchi dars haftasiga to'g'ri kelishi kerak — shuning uchun kuzgi
 * semestrda 14-sentabrdan oldin tugaydigan haftalar tashlab yuboriladi
 * (baho qo'yilmaganlar hisobotidagi 14-sentabr qoidasi bilan bir xil).
 */
class KtrWeeks
{
    /** Kuzgi semestrda darslar boshlanadigan kun (oy-kun) */
    public const AUTUMN_TEACHING_START = '09-14';

    /**
     * O'quv haftalari (start_date, end_date maydonli obyektlar) — KTR uchun
     * tartiblangan va dars boshlanishidan oldingi haftalarsiz.
     */
    public static function teachingWeeks(iterable $weeks): array
    {
        $sorted = collect($weeks)->sortBy(fn ($w) => (string) $w->start_date)->values();
        if ($sorted->isEmpty()) {
            return [];
        }

        $start = self::teachingStart((string) $sorted->first()->start_date);
        if ($start === null) {
            return $sorted->all();
        }

        $kept = $sorted->filter(fn ($w) => substr((string) $w->end_date, 0, 10) >= $start)->values();

        return $kept->isEmpty() ? $sorted->all() : $kept->all();
    }

    /** Semestr kuzgi bo'lsa — darslar boshlanadigan sana (Y-m-d), aks holda null */
    public static function teachingStart(string $semesterFirstWeekStart): ?string
    {
        if ($semesterFirstWeekStart === '') {
            return null;
        }
        $date = Carbon::parse($semesterFirstWeekStart);
        if ($date->month >= 8) {
            return $date->year . '-' . self::AUTUMN_TEACHING_START;
        }

        return null;
    }
}
