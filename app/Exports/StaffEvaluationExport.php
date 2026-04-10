<?php

namespace App\Exports;

use App\Models\StaffEvaluation;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class StaffEvaluationExport implements FromQuery, WithHeadings, ShouldAutoSize, WithMapping, WithStyles
{
    use Exportable;

    protected int $teacherId;
    protected ?int $rating;
    private int $row = 0;

    public function __construct(int $teacherId, ?int $rating = null)
    {
        $this->teacherId = $teacherId;
        $this->rating = $rating;
    }

    public function query()
    {
        $query = StaffEvaluation::where('teacher_id', $this->teacherId)
            ->with('student:id,full_name,short_name')
            ->latest();

        if ($this->rating) {
            $query->where('rating', $this->rating);
        }

        return $query;
    }

    public function headings(): array
    {
        return ['#', 'Baho', 'Izoh', 'Talaba', 'Sana'];
    }

    public function map($eval): array
    {
        $this->row++;
        return [
            $this->row,
            $eval->rating . ' yulduz',
            $eval->comment ?? '',
            $eval->student ? ($eval->student->short_name ?? $eval->student->full_name) : 'Anonim',
            $eval->created_at->format('d.m.Y H:i'),
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
