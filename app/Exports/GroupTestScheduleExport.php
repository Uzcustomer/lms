<?php

namespace App\Exports;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class GroupTestScheduleExport implements FromArray, ShouldAutoSize, WithStyles, WithTitle
{
    use Exportable;

    private Collection $rows;
    private Carbon $dateFrom;
    private Carbon $dateTo;
    private string $scopeLabel;

    public function __construct(Collection $rows, Carbon $dateFrom, Carbon $dateTo, string $scopeLabel)
    {
        $this->rows = $rows;
        $this->dateFrom = $dateFrom;
        $this->dateTo = $dateTo;
        $this->scopeLabel = $scopeLabel;
    }

    public function array(): array
    {
        $rows = [];

        $rows[] = ['Guruh test jadvali'];
        $rows[] = ['Sana oralig\'i:', $this->dateFrom->format('d.m.Y') . ' — ' . $this->dateTo->format('d.m.Y')];
        $rows[] = ['Ko\'rinish doirasi:', $this->scopeLabel];
        $rows[] = ['Yaratilgan:', now()->format('d.m.Y H:i')];
        $rows[] = [''];

        $rows[] = [
            '№',
            'Sana',
            'Vaqt',
            'Urinish',
            'Guruh',
            'Yo\'nalish',
            'Fakultet',
            'Kafedra',
            'Fan kodi',
            'Fan nomi',
            'Semestr',
            'Talabalar soni',
        ];

        $i = 1;
        foreach ($this->rows as $r) {
            $rows[] = [
                $i++,
                $r->test_date ? Carbon::parse($r->test_date)->format('d.m.Y') : '',
                $r->test_time ?: '',
                $r->attempt,
                $r->group_name ?: '',
                $r->specialty_name ?: '',
                $r->faculty_name ?: '',
                $r->kafedra_name ?: '',
                $r->subject_id ?: '',
                $r->subject_name ?: '',
                $r->semester_code ?: '',
                $r->student_count,
            ];
        }

        if ($i === 1) {
            $rows[] = ['—', 'Tanlangan oraliqda jadval bo\'sh', '', '', '', '', '', '', '', '', '', ''];
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Guruh test jadvali';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 14, 'color' => ['rgb' => '1a3268']]],
            2 => ['font' => ['bold' => true, 'color' => ['rgb' => '1a3268']]],
            3 => ['font' => ['bold' => true, 'color' => ['rgb' => '1a3268']]],
            4 => ['font' => ['italic' => true, 'color' => ['rgb' => '666666']]],
            6 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
            ],
        ];
    }
}
