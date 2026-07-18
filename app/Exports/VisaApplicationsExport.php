<?php

namespace App\Exports;

use App\Models\VisaApplication;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VisaApplicationsExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithStyles, WithChunkReading
{
    use Exportable;

    public function __construct(
        protected ?string $status = null,
        protected array $ids = []
    ) {}

    public function query()
    {
        $query = VisaApplication::query()
            ->with(['student:id,full_name', 'student.visaInfo'])
            ->latest();

        if (!empty($this->ids)) {
            $query->whereIn('id', $this->ids);
        } elseif ($this->status && in_array($this->status, ['pending', 'reviewing', 'submitted', 'approved', 'rejected'])) {
            $query->where('status', $this->status);
        }

        return $query;
    }

    public function map($app): array
    {
        $statusLabel = match ($app->status) {
            'pending'   => 'Kutilmoqda',
            'reviewing' => "Ko'rilmoqda",
            'submitted' => 'Submitted',
            'approved'  => 'Qabul qilindi',
            'rejected'  => 'Rad etilgan',
            default     => $app->status,
        };

        return [
            $app->application_number,
            $app->student?->full_name ?? trim(implode(' ', array_filter([
                $app->last_name,
                $app->first_name,
                $app->middle_name,
            ]))),
            $app->student?->visaInfo?->firm_display ?: '-',
            $app->last_name,
            $app->first_name,
            $app->middle_name,
            optional($app->birth_date)->format('d.m.Y'),
            $app->passport_number,
            $app->student_number,
            $app->phone_number,
            ucfirst($app->messenger_type ?? ''),
            $app->messenger_username ? '@' . ltrim($app->messenger_username, '@') : '',
            $statusLabel,
            $app->admin_note,
            $app->created_at?->format('d.m.Y H:i'),
            $app->reviewed_at?->format('d.m.Y H:i'),
        ];
    }

    public function headings(): array
    {
        return [
            'Ariza raqami',
            'LMS full name',
            'Firma',
            'Familiya',
            'Ism',
            'Otasining ismi',
            "Tug'ilgan sana",
            'Pasport raqami',
            'Student ID',
            'Telefon',
            'Messenger',
            'Username',
            'Holat',
            'Admin izoh',
            'Yuborilgan',
            "Ko'rib chiqilgan",
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
