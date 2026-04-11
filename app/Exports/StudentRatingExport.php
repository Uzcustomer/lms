<?php

namespace App\Exports;

use App\Models\StudentRating;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StudentRatingExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithStyles
{
    use Exportable;

    protected ?string $department;
    protected ?string $specialty;
    protected ?string $level;
    protected ?string $search;
    private int $row = 0;

    public function __construct(?string $department = null, ?string $specialty = null, ?string $level = null, ?string $search = null)
    {
        $this->department = $department;
        $this->specialty = $specialty;
        $this->level = $level;
        $this->search = $search;
    }

    public function query()
    {
        $query = StudentRating::query()->orderByDesc('jn_average');

        if ($this->department) {
            $query->where('department_code', $this->department);
        }
        if ($this->specialty) {
            $query->where('specialty_code', $this->specialty);
        }
        if ($this->level) {
            $query->where('level_code', $this->level);
        }
        if ($this->search) {
            $query->where(function ($q) {
                $q->where('full_name', 'like', '%' . $this->search . '%')
                  ->orWhere('group_name', 'like', '%' . $this->search . '%');
            });
        }

        return $query;
    }

    public function headings(): array
    {
        return ['#', 'F.I.O', 'Guruh', 'Fakultet', "Yo'nalish", 'Fanlar soni', "JN o'rtacha"];
    }

    public function map($rating): array
    {
        $this->row++;
        return [
            $this->row,
            $rating->full_name,
            $rating->group_name,
            $rating->department_name,
            $rating->specialty_name,
            $rating->subjects_count,
            $rating->jn_average,
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
