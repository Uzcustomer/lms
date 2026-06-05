<?php

namespace App\Support;

use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * Ish kunlari bo'yicha sana hisoblash.
 *
 * Hozircha yagona dam kuni — yakshanba (shanba ish kuni hisoblanadi).
 * Bayramlar kelajakda holidays jadvali orqali qo'shilishi mumkin.
 */
class WorkdayCalculator
{
    public static function isWorkday(CarbonInterface $date): bool
    {
        return $date->dayOfWeek !== Carbon::SUNDAY;
    }

    /**
     * Berilgan sanaga $days ta ish kuni qo'shadi (yakshanbalarni o'tkazib).
     */
    public static function addWorkdays(CarbonInterface $date, int $days): Carbon
    {
        $result = $date->copy()->startOfDay();
        $added = 0;

        while ($added < $days) {
            $result->addDay();
            if (self::isWorkday($result)) {
                $added++;
            }
        }

        return $result;
    }
}
