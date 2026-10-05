<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "Faollik va mustaqil ta'lim" — tanlangan sana oralig'i bo'yicha to'rt varaq:
 *   1. Kirish faolligi      — guruh kesimida: nechta talaba kirgan, necha marta;
 *   2. Kirmagan talabalar   — oraliqda birorta ham kirish yozuvi yo'q faol talabalar;
 *   3. Mustaqil ta'lim      — fan kesimida: yuklangan fayl, baholangan, baholanmagan;
 *   4. Materiallar          — fan kesimida: topshiriqlar soni, qanchasida fayl bor.
 *
 * Manbalar: activity_logs (kirishlar), independent_submissions (talaba fayli),
 * student_grades (MT bahosi, training_type_code = 99), independents (topshiriq
 * va o'qituvchi materiali). Hisob StudentActivityStatsSheet da.
 */
class StudentActivityStatsExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private string $dateFrom, private string $dateTo)
    {
    }

    public function sheets(): array
    {
        $data = new StudentActivityStatsData($this->dateFrom, $this->dateTo);

        return [
            new StudentActivityStatsSheet('Kirish faolligi', $data->loginsByGroup(), StudentActivityStatsSheet::LOGINS),
            new StudentActivityStatsSheet('Kirmagan talabalar', $data->neverLoggedIn(), StudentActivityStatsSheet::NOT_LOGGED),
            new StudentActivityStatsSheet("Mustaqil ta'lim", $data->independentBySubject(), StudentActivityStatsSheet::INDEPENDENT),
            new StudentActivityStatsSheet('Materiallar', $data->materialsBySubject(), StudentActivityStatsSheet::MATERIALS),
        ];
    }
}
