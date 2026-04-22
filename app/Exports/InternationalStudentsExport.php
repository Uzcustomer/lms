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
        $query = Student::where(function ($q) {
                $q->where('group_name', 'like', 'xd%')
                  ->orWhere('citizenship_name', 'like', '%orijiy%');
            })->with('visaInfo');

        if (!empty($this->filters['search'])) {
            $query->where('full_name', 'like', '%' . $this->filters['search'] . '%');
        }

        if (!empty($this->filters['level_code'])) {
            $query->where('level_code', (string) $this->filters['level_code']);
        }

        if (!empty($this->filters['group_name'])) {
            $query->where('group_name', 'like', '%' . $this->filters['group_name'] . '%');
        }

        if (!empty($this->filters['country'])) {
            $query->where('country_name', $this->filters['country']);
        }

        if (!empty($this->filters['department'])) {
            $query->where('department_id', $this->filters['department']);
        }

        if (!empty($this->filters['firm'])) {
            if ($this->filters['firm'] === 'none') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('visaInfo')
                      ->orWhereHas('visaInfo', fn($q2) => $q2->whereNull('firm')->orWhere('firm', ''));
                });
            } else {
                $query->whereHas('visaInfo', fn($q) => $q->where('firm', $this->filters['firm']));
            }
        }

        if (!empty($this->filters['data_status'])) {
            $status = $this->filters['data_status'];
            if ($status === 'filled') {
                $query->whereHas('visaInfo', fn($q) => $q->where(fn($q2) => $q2->whereNotNull('passport_number')->orWhereNotNull('visa_number')->orWhereNotNull('registration_end_date')));
            } elseif ($status === 'not_filled') {
                $query->where(function ($q) {
                    $q->whereDoesntHave('visaInfo')
                      ->orWhereHas('visaInfo', fn($q2) => $q2->whereNull('passport_number')->whereNull('visa_number')->whereNull('registration_end_date'));
                });
            } elseif (in_array($status, ['approved', 'pending', 'rejected'])) {
                $query->whereHas('visaInfo', fn($q) => $q->where('status', $status));
            }
        }

        if (isset($this->filters['visa_expiry']) && $this->filters['visa_expiry'] !== '' && $this->filters['visa_expiry'] !== null) {
            $days = (int) $this->filters['visa_expiry'];
            $query->whereHas('visaInfo', fn($q) => $q->whereNotNull('visa_end_date')->whereDate('visa_end_date', '<=', now()->addDays($days)));
        }

        if (isset($this->filters['registration_expiry']) && $this->filters['registration_expiry'] !== '' && $this->filters['registration_expiry'] !== null) {
            $days = (int) $this->filters['registration_expiry'];
            $query->whereHas('visaInfo', fn($q) => $q->whereNotNull('registration_end_date')->whereDate('registration_end_date', '<=', now()->addDays($days)));
        }

        if (!empty($this->filters['hemis_status'])) {
            if ($this->filters['hemis_status'] === 'inactive') {
                $query->where('student_status_code', '60');
            } elseif ($this->filters['hemis_status'] === 'active') {
                $query->where('student_status_code', '!=', '60');
            }
        }

        return $query->orderBy('full_name');
    }

    public function map($student): array
    {
        $visa = $student->visaInfo;
        $hasRealData = $visa && ($visa->passport_number || $visa->visa_number || $visa->registration_end_date);

        $firmDisplay = '';
        if ($visa?->firm) {
            $firmDisplay = $visa->firm === 'other'
                ? ($visa->firm_custom ?? '')
                : (StudentVisaInfo::FIRM_OPTIONS[$visa->firm] ?? $visa->firm ?? '');
        }

        return [
            $student->student_id_number,
            $student->full_name,
            $student->country_name,
            $student->group_name,
            $student->level_name,
            $student->department_name,
            $student->specialty_name,
            $student->citizenship_name,
            $student->student_status_name ?? ($student->student_status_code == '60' ? "Chetlashgan" : "O'qimoqda"),
            $student->phone,
            $student->telegram_username,
            $hasRealData ? 'Ha' : 'Yo\'q',
            $hasRealData ? match($visa->status) {
                'approved' => 'Tasdiqlangan',
                'rejected' => 'Rad etilgan',
                default => 'Kutilmoqda',
            } : '-',
            $firmDisplay ?: '-',
            $visa?->passport_number ?? '-',
            $visa?->passport_issued_place ?? '-',
            $visa?->passport_expiry_date?->format('d.m.Y') ?? '-',
            $visa?->registration_start_date?->format('d.m.Y') ?? '-',
            $visa?->registration_end_date?->format('d.m.Y') ?? '-',
            $visa?->visa_number ?? '-',
            $visa?->visa_type ?? '-',
            $visa?->visa_start_date?->format('d.m.Y') ?? '-',
            $visa?->visa_end_date?->format('d.m.Y') ?? '-',
            $visa?->visa_entries_count ?? '-',
            $visa?->visa_stay_days ?? '-',
            $visa?->visa_issued_place ?? '-',
            $visa?->entry_date?->format('d.m.Y') ?? '-',
            $visa?->birth_country ?? '-',
            $visa?->birth_region ?? '-',
            $visa?->birth_city ?? '-',
        ];
    }

    public function headings(): array
    {
        return [
            'Talaba ID',
            'To\'liq ismi',
            'Davlati',
            'Guruh',
            'Kurs',
            'Fakultet',
            'Yo\'nalish',
            'Fuqaroligi',
            'Holati (HEMIS)',
            'Telefon',
            'Telegram',
            'Ma\'lumot kiritilgan',
            'Tekshiruv holati',
            'Firma',
            'Pasport raqami',
            'Pasport berilgan joy',
            'Pasport muddati',
            'Reg. boshlanish',
            'Reg. tugash',
            'Viza raqami',
            'Viza turi',
            'Viza boshlanish',
            'Viza tugash',
            'Kirishlar soni',
            'Istiqomat muddati',
            'Viza berilgan joy',
            'Kirish sanasi',
            'Tug\'ilgan davlat',
            'Tug\'ilgan viloyat',
            'Tug\'ilgan shahar',
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
