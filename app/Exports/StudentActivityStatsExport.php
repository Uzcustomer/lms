<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "LMS ga kirishlar" — tanlangan sana oralig'i bo'yicha uch varaq: Kunlik,
 * Haftalik, Oylik. Faqat bakalavr talabalari, butun universitet bo'yicha:
 * davrda nechta talaba kirgan va bu jami bakalavrning necha foizi.
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
        $total = $data->totalStudents();

        return [
            new StudentActivityStatsSheet('Kunlik', $data->daily(), 'Kun', $total, $this->dateFrom, $this->dateTo),
            new StudentActivityStatsSheet('Haftalik', $data->weekly(), 'Hafta', $total, $this->dateFrom, $this->dateTo),
            new StudentActivityStatsSheet('Oylik', $data->monthly(), 'Oy', $total, $this->dateFrom, $this->dateTo),
        ];
    }
}
