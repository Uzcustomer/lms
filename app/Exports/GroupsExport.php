<?php

namespace App\Exports;

use App\Models\Group;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupsExport implements FromQuery, WithHeadings, ShouldAutoSize, WithStyles, WithChunkReading
{
    use Exportable;

    public function query()
    {
        return Group::query()->orderBy('group_hemis_id');
    }

    public function headings(): array
    {
        return [
            'ID',
            'Group HEMIS ID',
            'Nomi',
            'Kafedra HEMIS ID',
            'Kafedra nomi',
            'Kafedra kodi',
            'Kafedra structure type code',
            'Kafedra structure type name',
            'Kafedra locality type code',
            'Kafedra locality type name',
            'Kafedra active',
            'Group active',
            'Specialty HEMIS ID',
            'Specialty code',
            'Specialty name',
            'Ta\'lim tili kodi',
            'Ta\'lim tili nomi',
            'Curriculum HEMIS ID',
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
