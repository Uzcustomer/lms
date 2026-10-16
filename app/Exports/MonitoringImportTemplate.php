<?php

namespace App\Exports;

use App\Models\MonitoringDataImport;
use App\Services\Monitoring\Imports\OrderImporter;
use App\Services\Monitoring\Imports\RecommendationImporter;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Cell\StringValueBinder;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Monitoring importi uchun shablon. Namuna qatorlari — soxta ma'lumot.
 * Hamma katak matn sifatida yoziladi (ID raqamlardagi boshlang'ich nollar yo'qolmasin).
 */
class MonitoringImportTemplate extends StringValueBinder implements FromArray, WithHeadings, WithStyles, WithCustomValueBinder
{
    public function __construct(private string $kind)
    {
    }

    private function fields(): array
    {
        return $this->kind === MonitoringDataImport::KIND_ORDERS ? OrderImporter::FIELDS : RecommendationImporter::FIELDS;
    }

    public function headings(): array
    {
        return array_map(fn ($f) => $f['label'], array_values($this->fields()));
    }

    public function array(): array
    {
        if ($this->kind === MonitoringDataImport::KIND_ORDERS) {
            return [
                ['01-123', '15.10.2026', 'Akademik ta\'til berish', '', '000000000001', 'Namunaviy Talaba Birinchi', 'Ariza va tibbiy xulosa'],
                ['01-124', '16.10.2026', 'Talabalar safidan chetlashtirish', '', '000000000002', 'Namunaviy Talaba Ikkinchi', ''],
            ];
        }

        return [
            ['Namunaviy Abituriyent Birinchi', '1000001', 'AA1234567', '', 'Davolash ishi', 'Kunduzgi', "To'lov-kontrakt", '20.08.2026'],
            ['Namunaviy Abituriyent Ikkinchi', '1000002', '', '', 'Pediatriya ishi', 'Kunduzgi', "Qo'shimcha to'lov-kontrakt", '20.08.2026'],
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach (range('A', 'H') as $col) {
            $sheet->getColumnDimension($col)->setWidth(24);
        }

        return [1 => ['font' => ['bold' => true]]];
    }
}
