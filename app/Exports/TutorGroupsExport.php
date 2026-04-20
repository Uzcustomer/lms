<?php

namespace App\Exports;

use App\Models\Teacher;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class TutorGroupsExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    protected Teacher $teacher;

    public function __construct(Teacher $teacher)
    {
        $this->teacher = $teacher;
    }

    public function array(): array
    {
        $groups = $this->teacher->groups()
            ->orderBy('name')
            ->get();

        $rows = [];

        // Tyutor haqida ma'lumot (1-3 qatorlar)
        $rows[] = ['Tyutor F.I.O:', $this->teacher->full_name];
        $rows[] = ['Kafedra:', $this->teacher->department ?? ''];
        $rows[] = ['Guruhlar soni:', $groups->count()];
        $rows[] = ['', '']; // bo'sh qator

        // Guruhlar jadvali sarlavhasi (5-qator)
        $rows[] = [
            '№',
            'Guruh nomi',
            'HEMIS ID',
            'Yo\'nalish',
            'Yo\'nalish kodi',
            'Kafedra',
            'Ta\'lim tili',
            'Faol',
        ];

        $index = 1;
        foreach ($groups as $group) {
            $rows[] = [
                $index++,
                $group->name,
                $group->group_hemis_id,
                $group->specialty_name ?? '',
                $group->specialty_code ?? '',
                $group->department_name ?? '',
                $group->education_lang_name ?? '',
                $group->active ? 'Ha' : 'Yo\'q',
            ];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Tyutor guruhlari';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '1a3268'], 'size' => 12],
            ],
            2 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '1a3268']],
            ],
            3 => [
                'font' => ['bold' => true, 'color' => ['rgb' => '1a3268']],
            ],
            5 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
