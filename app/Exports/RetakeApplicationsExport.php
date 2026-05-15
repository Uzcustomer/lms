<?php

namespace App\Exports;

use App\Models\RetakeApplication;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithChunkReading;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class RetakeApplicationsExport implements FromQuery, WithHeadings, WithMapping, WithStyles, WithChunkReading, ShouldAutoSize
{
    use Exportable;

    public function __construct(private array $filters = []) {}

    public function query(): Builder
    {
        $q = RetakeApplication::query()
            ->with([
                'group.student',
                'group.window',
                'deanUser',
                'registrarUser',
                'academicDeptUser',
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

        // Subject filter (both subject and legacy subject_id)
        $subject = $f['subject'] ?? $f['subject_id'] ?? null;
        if (!empty($subject)) {
            $q->where('subject_id', $subject);
        }

        $semester = $f['semester_code'] ?? $f['semester_id'] ?? null;
        if (!empty($semester)) {
            $q->where('semester_id', $semester);
        }

        // Stage (akademik dept) — pending / preapproved / rejected
        if (!empty($f['stage'])) {
            $q->whereHas('group', function ($g) {
                $g->whereNotNull('payment_uploaded_at')
                  ->where('payment_verification_status', 'approved');
            });
            match ($f['stage']) {
                'pending' => $q->where('dean_status', 'approved')
                    ->where('registrar_status', 'approved')
                    ->where('academic_dept_status', 'pending')
                    ->where('final_status', 'pending'),
                'preapproved' => $q->where('academic_dept_status', 'approved')
                    ->where('final_status', 'pending')
                    ->whereNull('retake_group_id'),
                'rejected' => $q->where('academic_dept_status', 'rejected'),
                default => null,
            };
        }

        // Approval-side filter (dekan/registrator)
        if (!empty($f['filter'])) {
            match ($f['filter']) {
                'approved' => $q->where('final_status', 'approved'),
                'rejected' => $q->where('final_status', 'rejected'),
                default => null,
            };
        }

        // Talabaning filtri
        $department  = $f['department']  ?? $f['department_id']  ?? null;
        $specialty   = $f['specialty']   ?? $f['specialty_id']   ?? null;
        $levelCode   = $f['level_code']  ?? null;
        $educationType = $f['education_type'] ?? null;
        $group       = $f['group']       ?? $f['group_id']       ?? null;
        $search      = trim((string) ($f['search'] ?? ''));

        $hasStudentFilter = $department || $specialty || $levelCode || $educationType || $group || $search !== '';

        if ($hasStudentFilter) {
            $studentQuery = Student::query();
            if ($educationType) $studentQuery->where('education_type_code', $educationType);
            if ($department)    $studentQuery->where('department_id', $department);
            if ($specialty)     $studentQuery->where('specialty_id', $specialty);
            if ($levelCode)     $studentQuery->where('level_code', $levelCode);
            if ($group)         $studentQuery->where('group_id', $group);
            if ($search !== '') {
                $studentQuery->where(function ($s) use ($search) {
                    $s->where('full_name', 'like', "%{$search}%");
                    if (ctype_digit($search)) {
                        $s->orWhere('hemis_id', $search);
                    }
                });
            }
            $q->whereIn('student_hemis_id', $studentQuery->select('hemis_id'));
        }

        // Dekan o'z fakultetiga cheklash
        if (!empty($f['dean_faculty_ids']) && is_array($f['dean_faculty_ids'])) {
            $facultyIds = array_map('intval', $f['dean_faculty_ids']);
            if (empty($facultyIds)) {
                $q->whereRaw('1=0');
            } else {
                $q->whereIn('student_hemis_id', function ($sub) use ($facultyIds) {
                    $sub->select('hemis_id')->from('students')->whereIn('department_id', $facultyIds);
                });
            }
        }

        return $q;
    }

    public function chunkSize(): int
    {
        return 500;
    }

    public function headings(): array
    {
        return [
            '#',
            'Yuborilgan',
            'Talaba F.I.Sh',
            'HEMIS ID',
            'Fakultet',
            'Yo\'nalish',
            'Kurs',
            'Semestr (talabaning)',
            'Guruh',
            'Fan',
            'Ariza semestri',
            'Kredit',
            'Summa (UZS)',
            'Dekan qarori',
            'Dekan',
            'Dekan sanasi',
            'Dekan sababi',
            'Registrator qarori',
            'Registrator',
            'Registrator sanasi',
            'Registrator sababi',
            'O\'quv bo\'limi qarori',
            'O\'quv bo\'limi',
            'O\'quv bo\'limi sanasi',
            'O\'quv bo\'limi sababi',
            'Yakuniy holat',
            'Rad etgan tomon',
            'Qayta o\'qish guruhi',
            'O\'qituvchi',
            'Guruh boshlanishi',
            'Guruh tugashi',
        ];
    }

    private int $rowNum = 0;

    public function map($app): array
    {
        $student = $app->group?->student;
        $rg = $app->retakeGroup;

        return [
            ++$this->rowNum,
            optional($app->created_at)->format('Y-m-d H:i'),
            $student?->full_name ?? '—',
            $app->student_hemis_id,
            $student?->department_name ?? '',
            $student?->specialty_name ?? '',
            $student?->level_name ?? $student?->level_code ?? '',
            $student?->semester_name ?? $student?->semester_code ?? '',
            $student?->group_name ?? '',
            $app->subject_name,
            $app->semester_name,
            (float) $app->credit,
            number_format((float) ($app->group->receipt_amount ?? 0), 0, '.', ' '),

            $this->statusLabel($app->dean_status),
            $app->dean_user_name ?? ($app->deanUser?->full_name ?? ''),
            optional($app->dean_decision_at)->format('Y-m-d H:i'),
            $app->dean_reason,

            $this->statusLabel($app->registrar_status),
            $app->registrar_user_name ?? ($app->registrarUser?->full_name ?? ''),
            optional($app->registrar_decision_at)->format('Y-m-d H:i'),
            $app->registrar_reason,

            $this->statusLabel($app->academic_dept_status),
            $app->academic_dept_user_name ?? ($app->academicDeptUser?->full_name ?? ''),
            optional($app->academic_dept_decision_at)->format('Y-m-d H:i'),
            $app->academic_dept_reason,

            $this->finalLabel($app->final_status),
            $this->rejectedByLabel($app->rejected_by),

            $rg?->name ?? '',
            $rg?->teacher?->full_name ?? ($rg?->teacher_name ?? ''),
            optional($rg?->start_date)->format('Y-m-d'),
            optional($rg?->end_date)->format('Y-m-d'),
        ];
    }

    private function statusLabel(?string $status): string
    {
        return match ($status) {
            'approved' => 'Tasdiqlandi',
            'rejected' => 'Rad etildi',
            'pending' => 'Kutilmoqda',
            default => '—',
        };
    }

    private function finalLabel(?string $status): string
    {
        return match ($status) {
            'approved' => 'Tasdiqlandi',
            'rejected' => 'Rad etildi',
            'pending' => 'Kutilmoqda',
            default => '—',
        };
    }

    private function rejectedByLabel(?string $by): string
    {
        return match ($by) {
            'dean' => 'Dekan',
            'registrar' => 'Registrator',
            'academic_dept' => 'O\'quv bo\'limi',
            'system_hemis' => 'Tizim (HEMIS)',
            'window_closed' => 'Oyna yopildi',
            default => '',
        };
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
