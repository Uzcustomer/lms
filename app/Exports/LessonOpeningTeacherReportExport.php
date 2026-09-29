<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;

/**
 * Dars ochish arizalari hisoboti — ikki varaq:
 *   "O'qituvchilar" — har bir o'qituvchi necha marta baho qo'ymagan va shulardan
 *                     nechtasi uchun ariza orqali tasdiq olib baho qo'ygan;
 *   "Kunlar"        — o'sha holatlarning har biri alohida qator: guruh, fan, sana,
 *                     ariza holati (yig'madagi sonlarni tekshirish uchun).
 *
 * Ma'lumotni LessonOpeningTeacherReport::build() beradi.
 */
class LessonOpeningTeacherReportExport implements WithMultipleSheets
{
    use Exportable;

    public function __construct(private array $report) {}

    public function sheets(): array
    {
        return [
            new LessonOpeningTeacherSummarySheet($this->report),
            new LessonOpeningTeacherDaysSheet($this->report),
        ];
    }
}
