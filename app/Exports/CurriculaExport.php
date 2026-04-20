<?php

namespace App\Exports;

use App\Models\Curriculum;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class CurriculaExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return Curriculum::query()->orderBy('curricula_hemis_id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Curricula HEMIS ID',
            'Nomi',
            'Yo\'nalish HEMIS ID',
            'Kafedra HEMIS ID',
            'O\'quv yili kodi',
            'O\'quv yili nomi',
            'Joriy',
            'Ta\'lim turi kodi',
            'Ta\'lim turi nomi',
            'Ta\'lim shakli kodi',
            'Ta\'lim shakli nomi',
            'Baholash tizimi kodi',
            'Baholash tizimi nomi',
            'Minimal baho',
            'GPA chegarasi',
            'Semestrlar soni',
            'O\'qish muddati',
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
