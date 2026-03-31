<?php

namespace App\Exports;

use App\Models\Student;
use App\Models\StudentVisaInfo;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class InternationalStudentsExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithStyles, WithChunkReading
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Student::where('group_name', 'like', 'xd%')
            ->with('visaInfo');

        if (!empty($this->filters['search'])) {
            $query->where('full_name', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['level_code'])) {
            $query->where('level_code', $this->filters['level_code']);
        }

        if (!empty($this->filters['firm'])) {
            $query->whereHas('visaInfo', fn($q) => $q->where('firm', $this->filters['firm']));
        }

        if (!empty($this->filters['data_status'])) {
            $status = $this->filters['data_status'];
            if ($status === 'filled') {
                $query->whereHas('visaInfo');
            } elseif ($status === 'not_filled') {
                $query->whereDoesntHave('visaInfo');
            } elseif (in_array($status, ['approved', 'pending', 'rejected'])) {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', $status));
            }
        }

        return $query->orderBy('full_name');
    }

    public function map($student): array
    {
        $visa = $student->visaInfo;

        $firmDisplay = '';
        if ($visa) {
            $firmDisplay = $visa->firm === 'other'
                ? ($visa->firm_custom ?? '')
                : (StudentVisaInfo::FIRM_OPTIONS[$visa->firm] ?? $visa->firm ?? '');
        }

        return [
            $student->full_name,
            $student->group_name,
            $student->level_name,
            $student->department_name,
            $student->citizenship_name,
            $visa ? 'Ha' : 'Yo\'q',
            $visa ? match($visa->status) {
                'approved' => 'Tasdiqlangan',
                'rejected' => 'Rad etilgan',
                default => 'Kutilmoqda',
            } : '-',
            $visa?->visa_number ?? '-',
            $visa?->visa_type ?? '-',
            $visa?->visa_start_date?->format('d.m.Y') ?? '-',
            $visa?->visa_end_date?->format('d.m.Y') ?? '-',
            $visa?->registration_start_date?->format('d.m.Y') ?? '-',
            $visa?->registration_end_date?->format('d.m.Y') ?? '-',
            $firmDisplay ?: '-',
            $visa?->passport_number ?? '-',
            $visa?->passport_expiry_date?->format('d.m.Y') ?? '-',
            $visa?->passport_handed_over ? 'Ha' : 'Yo\'q',
        ];
    }

    public function headings(): array
    {
        return [
            'To\'liq ismi',
            'Guruh',
            'Kurs',
            'Fakultet',
            'Fuqaroligi',
            'Ma\'lumot kiritilgan',
            'Holati',
            'Viza raqami',
            'Viza turi',
            'Viza boshlanish',
            'Viza tugash',
            'Propiska boshlanish',
            'Propiska tugash',
            'Firma',
            'Pasport raqami',
            'Pasport muddati',
            'Pasport topshirilgan',
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
