<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "LMS ga kirishlar" — tanlangan sana oralig'i bo'yicha to'rt varaq: Kunlik,
 * Haftalik, Oylik (davrda nechta talaba kirgan va bu jami bakalavrning necha
 * foizi) va Talabalar (har bir bakalavr alohida: necha marta, nechta kunda,
 * oxirgi kirishi). Faqat bakalavr talabalari, butun universitet bo'yicha.
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
            new StudentActivityStudentsSheet($data->perStudent(), $total, $this->dateFrom, $this->dateTo),
        ];
    }
}
