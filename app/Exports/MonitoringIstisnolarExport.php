<?php

namespace App\Exports;

use App\Models\MonitoringIstisno;
use App\Services\Monitoring\MonitoringAccess;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Monitoring istisnolari ro'yxati (filtrlangan) — Excel eksport (TZ 6.2).
 */
class MonitoringIstisnolarExport implements FromCollection, WithHeadings, WithMapping, WithStyles
{
    public function __construct(private $rows)
    {
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return ['№', 'Detektor', 'Guruh', 'Obyekt', 'Holat', 'Ogohlantirish', 'Topilgan', 'Muddat', "Mas'ul", 'Yopilgan', 'Tafsilot'];
    }

    /** @param MonitoringIstisno $i */
    public function map($i): array
    {
        return [
            $i->id,
            $i->detector?->code,
            $i->detector?->group_name,
            $i->subject_label,
            $i->statusLabel(),
            $i->warning_only ? 'ha' : '',
            $i->found_at?->format('d.m.Y'),
            $i->due_at?->format('d.m.Y'),
            MonitoringAccess::displayName($i->owner),
            $i->closed_at?->format('d.m.Y'),
            collect($i->details ?? [])->map(fn ($v, $k) => $k . ': ' . (is_array($v) ? implode('; ', $v) : (is_bool($v) ? ($v ? 'ha' : "yo'q") : $v)))->implode(' | '),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        foreach (['A' => 7, 'B' => 9, 'C' => 30, 'D' => 50, 'E' => 16, 'F' => 13, 'G' => 12, 'H' => 12, 'I' => 30, 'J' => 12, 'K' => 80] as $col => $width) {
            $sheet->getColumnDimension($col)->setWidth($width);
        }

        return [1 => ['font' => ['bold' => true]]];
    }
}
