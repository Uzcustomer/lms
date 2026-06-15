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
     * Telefon ustunlari — O'qituvchi tel. (I), Fan mas'uli tel. (K),
     * Kafedra mudiri tel. (M) — aniq MATN formatida bo'lsin (Excel raqamlarni
     * qisqartirmasligi uchun). ("Shakl" ustuni qo'shilgani uchun bir pog'ona surilgan.)
     */
    public function columnFormats(): array
    {
        return [
            'I' => NumberFormat::FORMAT_TEXT,
            'K' => NumberFormat::FORMAT_TEXT,
            'M' => NumberFormat::FORMAT_TEXT,
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
            'Shakl',
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
