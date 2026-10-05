<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "LMS ga kirishlar" — tanlangan sana oralig'i bo'yicha uch varaq, hammasi
 * fakultet va kurs kesimida: Kunlik, Haftalik, Oylik. Har varaqning o'ng
 * tomonida shu davr bo'yicha umumiy (fakultetsiz) jadval turadi.
 *
 * Manba: activity_logs (web va mobil kirishlar). Hisob StudentActivityStatsData da.
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
            new StudentActivityStatsSheet('Kunlik', $data->daily(), 'Kun', $data->summarize($data->dailyPeriod())),
            new StudentActivityStatsSheet('Haftalik', $data->weekly(), 'Hafta', $data->summarize($data->weeklyPeriod())),
            new StudentActivityStatsSheet('Oylik', $data->monthly(), 'Oy', $data->summarize($data->monthlyPeriod())),
        ];
    }
}
