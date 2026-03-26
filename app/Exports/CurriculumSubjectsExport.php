<?php

namespace App\Exports;

use App\Models\CurriculumSubject;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CurriculumSubjectsExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return CurriculumSubject::query()->orderBy('curricula_hemis_id')->orderBy('semester_code');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Curriculum Subject HEMIS ID',
            'Curricula HEMIS ID',
            'Fan ID',
            'Fan nomi',
            'Fan kodi',
            'Fan turi kodi',
            'Fan turi nomi',
            'Fan bloki kodi',
            'Fan bloki nomi',
            'Semestr kodi',
            'Semestr nomi',
            'Jami soat',
            'Kredit',
            'Guruhda',
            'Semestrda',
            'Faol',
            'Reyting baho kodi',
            'Reyting baho nomi',
            'Yakuniy nazorat kodi',
            'Yakuniy nazorat nomi',
            'Kafedra ID',
            'Kafedra nomi',
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
