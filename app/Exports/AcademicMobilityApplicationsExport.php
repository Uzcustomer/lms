<?php

namespace App\Exports;

use App\Models\AkademikMobillikAriza;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Akademik mobillik arizalari ro'yxati.
 *
 * Ariza bergan talabaning to'liq ma'lumoti (fakultet, yo'nalish, kurs,
 * guruh), qayerga o'tmoqchi ekani, telefoni va tasdiqlash holati.
 * Ro'yxatdagi qidiruv qo'llanilgan bo'lsa, eksport ham shunga mos keladi.
 */
class AcademicMobilityApplicationsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithTitle, WithColumnWidths
{
    use Exportable;

    private int $row = 0;

    public function __construct(private ?string $search = null)
    {
    }

    public function query(): Builder
    {
        return AkademikMobillikAriza::query()
            ->with(['student', 'approvals'])
            ->when($this->search, fn (Builder $query) => $query->whereHas(
                'student',
                fn (Builder $student) => $student->where('full_name', 'like', '%' . $this->search . '%')
            ))
            ->latest();
    }

    public function title(): string
    {
        return 'Mobillik arizalari';
    }

    public function headings(): array
    {
        return [
            '#',
            'Talaba F.I.SH.',
            'HEMIS ID',
            'Talaba ID',
            'Fakultet',
            "Yo'nalish",
            'Kurs',
            'Guruh',
            'Telefon',
            "Mobillik bo'layotgan joy",
            'Sabab',
            'Holat',
            "O'quv bo'limi",
            'Prorektor',
            'Ariza sanasi',
        ];
    }

    public function map($application): array
    {
        $student = $application->student;

        $decision = function (string $role) use ($application) {
            $row = $application->approvals->firstWhere('role', $role);
            if (!$row) {
                return 'kutilmoqda';
            }

            return match ($row->status) {
                'approved' => 'tasdiqlangan',
                'rejected' => 'rad etilgan' . ($row->rejection_comment ? ' — ' . $row->rejection_comment : ''),
                default => 'kutilmoqda',
            };
        };

        return [
            ++$this->row,
            $student->full_name ?? '—',
            $student->hemis_id ?? '—',
            $student->student_id_number ?? '—',
            $student->department_name ?? '—',
            $student->specialty_name ?? '—',
            $student->level_name ?? '—',
            $student->group_name ?? '—',
            // Telefon matn bo'lib qolsin: + belgisi formula deb o'qilmasin
            (string) ($application->phone ?? '—'),
            $application->transfer_destination ?: '—',
            $application->reason ?: '—',
            match ($application->status) {
                'approved' => 'tasdiqlangan',
                'rejected' => 'rad etilgan',
                default => 'yangi',
            },
            $decision('oquv_bolimi'),
            $decision('oquv_prorektori'),
            optional($application->created_at)->format('d.m.Y H:i') ?? '—',
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 5,   'B' => 34,  'C' => 11,  'D' => 15,  'E' => 30,
            'F' => 26,  'G' => 10,  'H' => 16,  'I' => 17,  'J' => 38,
            'K' => 40,  'L' => 14,  'M' => 26,  'N' => 26,  'O' => 17,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->freezePane('A2');

        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F2748']],
                'alignment' => ['vertical' => 'center'],
            ],
        ];
    }
}
