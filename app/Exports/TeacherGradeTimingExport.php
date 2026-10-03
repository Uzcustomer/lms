<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * "Baho qo'yish vaqti" — ikki varaqli Excel:
 *   1. Umumiy          — o'qituvchi + fan kesimida;
 *   2. Guruhlar bo'yicha — o'qituvchi + fan + guruh kesimida.
 * Hisob mantig'i TeacherGradeTimingSheet da, varaqlar faqat guruh ustuni
 * bilan farq qiladi.
 */
class TeacherGradeTimingExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private string $dateFrom, private string $dateTo, private array $filters = [])
    {
    }

    public function sheets(): array
    {
        return [
            new TeacherGradeTimingSheet($this->dateFrom, $this->dateTo, $this->filters, false),
            new TeacherGradeTimingSheet($this->dateFrom, $this->dateTo, $this->filters, true),
        ];
    }
}
