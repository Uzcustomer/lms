<?php

namespace App\Exports;

use App\Models\Semester;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SemestersExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return Semester::query()->orderBy('curriculum_hemis_id')->orderBy('code');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Semester HEMIS ID',
            'Kod',
            'Nomi',
            'Curriculum HEMIS ID',
            'O\'quv yili',
            'Kurs kodi',
            'Kurs nomi',
            'Joriy',
            'Yaratilgan',
            'Yangilangan',
        ];
    }

    public function chunkSize(): int
    {
        return 500;
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
