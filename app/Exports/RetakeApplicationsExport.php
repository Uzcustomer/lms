<?php

namespace App\Exports;

use App\Models\RetakeApplication;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetakeApplicationsExport implements FromQuery, WithHeadings, WithMapping, WithStyles
{
    use Exportable;

    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        $q = RetakeApplication::query()
            ->with([
                'group.student',
                'group.window',
                'retakeGroup.teacher',
            ])
            ->orderBy('created_at', 'desc');

        $f = $this->filters;

        if (!empty($f['final_status'])) {
            $q->where('final_status', $f['final_status']);
        }
        if (!empty($f['date_from'])) {
            $q->whereDate('created_at', '>=', $f['date_from']);
        }
        if (!empty($f['date_to'])) {
            $q->whereDate('created_at', '<=', $f['date_to']);
        }
        if (!empty($f['subject_id'])) {
            $q->where('subject_id', $f['subject_id']);
        }
        if (!empty($f['semester_id'])) {
            $q->where('semester_id', $f['semester_id']);
        }
        if (!empty($f['student_filter'])) {
            $studentIds = Student::query();
            if (!empty($f['department_id'])) $studentIds->where('department_id', $f['department_id']);
            if (!empty($f['specialty_id']))  $studentIds->where('specialty_id', $f['specialty_id']);
            if (!empty($f['level_code']))    $studentIds->where('level_code', $f['level_code']);
            $q->whereIn('student_hemis_id', $studentIds->pluck('hemis_id'));
        }

        return $q;
    }

    public function headings(): array
    {
        return [
            '#', 'Sana', 'Talaba F.I.Sh', 'HEMIS ID', 'Fakultet', 'Yo\'nalish', 'Kurs', 'Guruh',
            'Fan', 'Semestr', 'Kredit', 'Summa (UZS)',
            'Dekan qarori', 'Dekan sababi',
            'Registrator qarori', 'Registrator sababi',
            'O\'quv bo\'limi qarori', 'O\'quv bo\'limi sababi',
            'Yakuniy holat', 'Rad etgan',
            'Guruh nomi', 'O\'qituvchi', 'Boshlanish', 'Tugash',
        ];
    }

    private int $rowNum = 0;

    public function map($app): array
    {
        $student = $app->group?->student;
        $rg = $app->retakeGroup;

        return [
            ++$this->rowNum,
            $app->created_at->format('Y-m-d H:i'),
            $student?->full_name ?? '—',
            $app->student_hemis_id,
            $student?->department_name ?? '',
            $student?->specialty_name ?? '',
            $student?->level_name ?? $student?->level_code ?? '',
            $student?->group_name ?? '',
            $app->subject_name,
            $app->semester_name,
            (float) $app->credit,
            number_format((float) ($app->group->receipt_amount ?? 0), 0, '.', ' '),
            $app->dean_status,
            $app->dean_reason,
            $app->registrar_status,
            $app->registrar_reason,
            $app->academic_dept_status,
            $app->academic_dept_reason,
            $app->final_status,
            $app->rejected_by,
            $rg?->name ?? '',
            $rg?->teacher_name ?? '',
            $rg?->start_date?->format('Y-m-d') ?? '',
            $rg?->end_date?->format('Y-m-d') ?? '',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->getRowDimension(1)->setRowHeight(30);
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1f2937']],
                'alignment' => ['vertical' => 'center', 'horizontal' => 'left'],
            ],
        ];
    }
}
