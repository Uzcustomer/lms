<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VedomostSubmissionExport extends DefaultValueBinder implements FromArray, WithHeadings, ShouldAutoSize, WithStyles, WithCustomValueBinder, WithColumnFormatting
{
    use Exportable;

    public function __construct(private array $rows)
    {
    }

    /**
     * Telefon ustunlari — O'qituvchi tel. (H), Fan mas'uli tel. (J),
     * Kafedra mudiri tel. (L) — aniq MATN formatida bo'lsin (Excel raqamlarni
     * qisqartirmasligi uchun).
     */
    public function columnFormats(): array
    {
        return [
            'H' => NumberFormat::FORMAT_TEXT,
            'J' => NumberFormat::FORMAT_TEXT,
            'L' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * Barcha matnli kataklarni MATN sifatida yozamiz — aks holda Excel uzun tel
     * raqamlarini ("998901234567" yoki "+998...") songa aylantirib qisqartiradi
     * yoki formula deb o'qiydi.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if (is_string($value) && $value !== '') {
            $cell->setValueExplicit($value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return [
            '#',
            'Fakultet',
            'Guruh',
            "Yo'nalish",
            'Fan',
            'Kafedra',
            "O'qituvchi",
            "O'qituvchi tel.",
            "Fan mas'uli",
            "Fan mas'uli tel.",
            'Kafedra mudiri',
            'Kafedra mudiri tel.',
            'Yopilish shakli',
            'Asos sana',
            'Muddat (deadline)',
            'Kechikkan',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
            ],
        ];
    }
}
