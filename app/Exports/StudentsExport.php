<?php

namespace App\Exports;

use App\Models\StaffRegistrationDivision;
use App\Models\Student;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentsExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithStyles, WithChunkReading
{
    use Exportable;

    protected array $filters;

    public function __construct(array $filters = [])
    {
        $this->filters = $filters;
    }

    public function query()
    {
        $query = Student::query();

        if (!empty($this->filters['student_id_number'])) {
            $query->where('student_id_number', $this->filters['student_id_number']);
        }

        if (!empty($this->filters['full_name'])) {
            $query->where('full_name', 'like', '%' . $this->filters['full_name'] . '%');
        }

        if (!empty($this->filters['level_code'])) {
            $query->where('level_code', $this->filters['level_code']);
        }

        if (!empty($this->filters['semester_code'])) {
            $query->where('semester_code', $this->filters['semester_code']);
        }

        if (!empty($this->filters['department'])) {
            $query->where('department_id', $this->filters['department']);
        }

        if (!empty($this->filters['specialty'])) {
            $query->where('specialty_id', $this->filters['specialty']);
        }

        if (!empty($this->filters['group'])) {
            $query->where('group_id', $this->filters['group']);
        }

        if (!empty($this->filters['education_type'])) {
            $query->where('education_type_code', $this->filters['education_type']);
        }

        return $query->orderBy('department_name')->orderBy('group_name')->orderBy('full_name');
    }

    public function map($student): array
    {
        $frontOffice = StaffRegistrationDivision::findForStudent(
            $student->department_id, $student->specialty_id, $student->level_code, 'front_office'
        );
        $backOffice = StaffRegistrationDivision::findForStudent(
            $student->department_id, $student->specialty_id, $student->level_code, 'back_office'
        );

        return [
            $student->hemis_id,
            $student->student_id_number,
            $student->full_name,
            $student->short_name,
            $student->first_name,
            $student->second_name,
            $student->third_name,
            $student->birth_date ? $student->birth_date->format('d.m.Y') : '',
            $student->gender_name,
            $student->phone,
            $student->university_name,
            $student->department_name,
            $student->specialty_name,
            $student->specialty_code,
            $student->group_name,
            $student->language_name,
            $student->level_name,
            $student->semester_name,
            $student->education_year_name,
            $student->education_form_name,
            $student->education_type_name,
            $student->payment_form_name,
            $student->student_type_name,
            $student->student_status_name,
            $student->social_category_name,
            $student->accommodation_name,
            $student->country_name,
            $student->province_name,
            $student->district_name,
            $student->terrain_name,
            $student->citizenship_name,
            $student->avg_gpa,
            $student->avg_grade,
            $student->total_credit,
            $student->total_acload,
            $student->year_of_enter,
            $student->is_graduate ? 'Ha' : 'Yo\'q',
            $student->is_five_candidate ? 'Ha' : 'Yo\'q',
            $student->roommate_count,
            $student->telegram_username,
            $student->telegram_verified_at ? $student->telegram_verified_at->format('d.m.Y H:i') : '',
            $student->face_id_enabled ? 'Ha' : 'Yo\'q',
            $student->hemis_created_at ? $student->hemis_created_at->format('d.m.Y') : '',
            $student->hemis_updated_at ? $student->hemis_updated_at->format('d.m.Y') : '',
            $frontOffice?->teacher?->full_name ?? '',
            $frontOffice?->started_at ? $frontOffice->started_at->format('d.m.Y') : '',
            $backOffice?->teacher?->full_name ?? '',
            $backOffice?->started_at ? $backOffice->started_at->format('d.m.Y') : '',
        ];
    }

    public function headings(): array
    {
        return [
            'HEMIS ID',
            'Talaba ID',
            'To\'liq ismi',
            'Qisqa ismi',
            'Ismi',
            'Familiyasi',
            'Otasining ismi',
            'Tug\'ilgan sana',
            'Jinsi',
            'Telefon',
            'Universitet',
            'Fakultet',
            'Yo\'nalish',
            'Yo\'nalish kodi',
            'Guruh',
            'Ta\'lim tili',
            'Kurs',
            'Semestr',
            'O\'quv yili',
            'Ta\'lim shakli',
            'Ta\'lim turi',
            'To\'lov shakli',
            'Talaba turi',
            'Talaba holati',
            'Ijtimoiy toifa',
            'Turar joy',
            'Davlat',
            'Viloyat',
            'Tuman',
            'Hududiy joy',
            'Fuqarolik',
            'O\'rtacha GPA',
            'O\'rtacha baho',
            'Jami kredit',
            'Jami o\'quv yuklamasi',
            'Kirish yili',
            'Bitiruvchi',
            '5 ga da\'vogar',
            'Xonadoshlar soni',
            'Telegram username',
            'Telegram tasdiqlangan',
            'Face ID faol',
            'HEMIS yaratilgan',
            'HEMIS yangilangan',
            'Front ofis xodimi',
            'Front ofis boshlanish sanasi',
            'Back ofis xodimi',
            'Back ofis boshlanish sanasi',
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
