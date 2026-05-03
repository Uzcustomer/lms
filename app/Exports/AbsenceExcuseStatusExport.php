<?php

namespace App\Exports;

use App\Models\AbsenceExcuse;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class AbsenceExcuseStatusExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle
{
    use Exportable;

    public function __construct(private string $status) {}

    public function query(): Builder
    {
        return AbsenceExcuse::query()
            ->where('status', $this->status)
            ->orderByDesc('reviewed_at')
            ->orderByDesc('created_at');
    }

    public function headings(): array
    {
        $base = [
            '#',
            'Talaba F.I.SH.',
            'Talaba HEMIS ID',
            'Guruh',
            'Kafedra / Fakultet',
            'Hujjat raqami',
            'Sabab',
            'Boshlanish sanasi',
            'Tugash sanasi',
            'Izoh',
            'Topshirilgan sana',
        ];

        if ($this->status === 'approved') {
            return array_merge($base, ['Tasdiqlovchi', 'Tasdiqlangan vaqt']);
        }
        if ($this->status === 'rejected') {
            return array_merge($base, ['Rad etgan', 'Rad etish sababi', 'Rad etilgan vaqt']);
        }

        return $base;
    }

    /**
     * @param  AbsenceExcuse  $excuse
     */
    public function map($excuse): array
    {
        static $rowNum = 0;
        $rowNum++;

        $base = [
            $rowNum,
            $excuse->student_full_name ?? '',
            $excuse->student_hemis_id ?? '',
            $excuse->group_name ?? '',
            $excuse->department_name ?? '',
            $excuse->doc_number ?? '',
            AbsenceExcuse::REASONS[$excuse->reason]['label'] ?? $excuse->reason,
            optional($excuse->start_date)->format('d.m.Y'),
            optional($excuse->end_date)->format('d.m.Y'),
            $excuse->description ?? '',
            optional($excuse->created_at)->format('d.m.Y H:i'),
        ];

        if ($this->status === 'approved') {
            return array_merge($base, [
                $excuse->reviewed_by_name ?? '',
                optional($excuse->reviewed_at)->format('d.m.Y H:i'),
            ]);
        }

        if ($this->status === 'rejected') {
            return array_merge($base, [
                $excuse->reviewed_by_name ?? '',
                $excuse->rejection_reason ?? '',
                optional($excuse->reviewed_at)->format('d.m.Y H:i'),
            ]);
        }

        return $base;
    }

    public function styles(Worksheet $sheet): array
    {
        $highest = $sheet->getHighestColumn();
        $sheet->getStyle("A1:{$highest}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => [
                'fillType' => 'solid',
                'startColor' => ['rgb' => match ($this->status) {
                    'approved' => '16A34A',
                    'rejected' => 'DC2626',
                    default => 'CA8A04',
                }],
            ],
            'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        ]);

        foreach (range('A', $highest) as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }

        return [];
    }

    public function title(): string
    {
        return match ($this->status) {
            'pending' => 'Kutilmoqda',
            'approved' => 'Tasdiqlangan',
            'rejected' => 'Rad etilgan',
            default => ucfirst($this->status),
        };
    }
}
