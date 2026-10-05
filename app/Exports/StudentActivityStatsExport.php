<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "LMS ga kirishlar" — tanlangan sana oralig'i bo'yicha uch varaq, hammasi
 * fakultet va kurs kesimida: Kunlik, Haftalik, Oylik.
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
            new StudentActivityStatsSheet('Kunlik', $data->daily(), 'Kun'),
            new StudentActivityStatsSheet('Haftalik', $data->weekly(), 'Hafta'),
            new StudentActivityStatsSheet('Oylik', $data->monthly(), 'Oy'),
        ];
    }
}
