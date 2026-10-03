<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * O'qituvchilar baholarni qachon qo'ygani — tanlangan sana oralig'idagi
 * bakalavr darslari bo'yicha.
 *
 * Har bir JN bahosi uchun HEMIS'da qo'yilgan vaqt (created_at_api) o'sha
 * darsning juftlik soati bilan solishtiriladi:
 *   - Dars vaqtida   — dars kuni, juftlik boshlanishidan tugashigacha;
 *   - Ish vaqtida    — dars vaqtidan tashqari, lekin 18:00 gacha;
 *   - 18:00 dan keyin — soat 18:00 dan keyin qo'yilgan.
 *
 * NB (grade = null) qatorlar hisobga olinmaydi — ularning created_at_api
 * qiymati o'qituvchi harakati emas, importning vaqti. OSKI, test, oraliq
 * nazorat va mustaqil ta'lim ham kirmaydi — ular kundalik dars emas.
 */
class TeacherGradeTimingExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    use Exportable;

    /** Kundalik dars bo'lmagan baho turlari */
    private const EXCLUDED_TRAINING_TYPES = [99, 100, 101, 102, 103];

    /** LMS ichida yaratilgan qatorlarning soxta hemis_id lari */
    private const LOCAL_HEMIS_IDS = [77777777, 88888888, 888888888, 999999999];

    private const AFTER_HOURS_FROM = '18:00:00';

    private int $dataRows = 0;

    public function __construct(private string $dateFrom, private string $dateTo)
    {
    }

    public function title(): string
    {
        return 'Baho vaqti';
    }

    public function collection(): Collection
    {
        // Dars kuni, juftlik boshlanishi va tugashi orasida qo'yilgan.
        // Juftlik vaqti bo'sh qatorda ifoda NULL bo'ladi — COALESCE bilan
        // "dars vaqtida emas" deb qaraladi, aks holda ikkala CASE ham 0 berib
        // qator noto'g'ri "ish vaqtida"ga tushardi.
        $duringLesson = "COALESCE((DATE(sg.created_at_api) = DATE(sg.lesson_date)
            AND TIME(sg.created_at_api) BETWEEN CAST(sg.lesson_pair_start_time AS TIME) AND CAST(sg.lesson_pair_end_time AS TIME)), 0) = 1";

        $rows = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 'sg.employee_id')
            ->whereNull('sg.deleted_at')
            ->whereNotNull('sg.grade')
            ->whereBetween('sg.lesson_date', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->whereNotIn('sg.training_type_code', self::EXCLUDED_TRAINING_TYPES)
            ->whereNotIn('sg.hemis_id', self::LOCAL_HEMIS_IDS)
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            ->groupBy('sg.employee_id', 'sg.employee_name', 't.department')
            ->orderBy('sg.employee_name')
            ->select([
                'sg.employee_id',
                'sg.employee_name',
                't.department',
                DB::raw("SUM(CASE WHEN {$duringLesson} THEN 1 ELSE 0 END) AS during_lesson"),
                DB::raw("SUM(CASE WHEN NOT ({$duringLesson}) AND TIME(sg.created_at_api) >= '" . self::AFTER_HOURS_FROM . "' THEN 1 ELSE 0 END) AS after_hours"),
                DB::raw('COUNT(*) AS total'),
            ])
            ->get();

        // Xodimlar jadvalida kafedrasi yo'q o'qituvchi — dars jadvalidagi kafedra
        $missingDepartment = $rows->filter(fn ($r) => blank($r->department))->pluck('employee_id')->unique()->values();
        $scheduleDepartments = $missingDepartment->isEmpty() ? collect() : DB::table('schedules')
            ->whereIn('employee_id', $missingDepartment)
            ->whereNull('deleted_at')
            ->whereNotNull('department_name')
            ->select('employee_id', 'department_name', DB::raw('COUNT(*) AS cnt'))
            ->groupBy('employee_id', 'department_name')
            ->orderByDesc('cnt')
            ->get()
            ->groupBy('employee_id')
            ->map(fn ($g) => $g->first()->department_name);

        $totals = ['during' => 0, 'work' => 0, 'late' => 0, 'all' => 0];
        $out = collect();

        foreach ($rows as $i => $r) {
            $during = (int) $r->during_lesson;
            $late = (int) $r->after_hours;
            $total = (int) $r->total;
            $work = $total - $during - $late;

            $totals['during'] += $during;
            $totals['work'] += $work;
            $totals['late'] += $late;
            $totals['all'] += $total;

            $out->push([
                $i + 1,
                $r->employee_name,
                blank($r->department) ? ($scheduleDepartments[$r->employee_id] ?? '-') : $r->department,
                $during,
                $work,
                $late,
                $total,
                $this->percent($during, $total),
                $this->percent($work, $total),
                $this->percent($late, $total),
            ]);
        }

        $this->dataRows = $out->count();

        if ($this->dataRows > 0) {
            $out->push([
                '',
                'JAMI',
                '',
                $totals['during'],
                $totals['work'],
                $totals['late'],
                $totals['all'],
                $this->percent($totals['during'], $totals['all']),
                $this->percent($totals['work'], $totals['all']),
                $this->percent($totals['late'], $totals['all']),
            ]);
        }

        return $out;
    }

    public function headings(): array
    {
        return [
            '#',
            "O'qituvchi",
            'Kafedra',
            'Dars vaqtida (soni)',
            'Ish vaqtida (soni)',
            '18:00 dan keyin (soni)',
            'Jami',
            'Dars vaqtida %',
            'Ish vaqtida %',
            '18:00 dan keyin %',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->dataRows + 1;        // sarlavha + ma'lumot qatorlari
        $totalRow = $this->dataRows > 0 ? $lastRow + 1 : null;

        $styles = [
            1 => [
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1a3268']],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ],
        ];

        $sheet->getRowDimension(1)->setRowHeight(42);
        $sheet->freezePane('A2');

        if ($this->dataRows > 0) {
            $sheet->getStyle("D2:J{$lastRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H2:J{$lastRow}")->getNumberFormat()->setFormatCode('0.0');

            // Rang: dars vaqtida foizi — sariq; 18:00 dan keyin — kam bo'lsa yashil, ko'p bo'lsa qizg'ish
            for ($row = 2; $row <= $lastRow; $row++) {
                $sheet->getStyle("H{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB('FFF2CC');

                $late = (float) $sheet->getCell("J{$row}")->getValue();
                $color = $late < 10 ? 'E2EFDA' : ($late < 30 ? 'FFF2CC' : 'F8CBAD');
                $sheet->getStyle("J{$row}")->getFill()->setFillType('solid')->getStartColor()->setRGB($color);
            }
        }

        if ($totalRow) {
            $styles[$totalRow] = [
                'font' => ['bold' => true],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E7ECF4']],
            ];
            $sheet->getStyle("D{$totalRow}:J{$totalRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("H{$totalRow}:J{$totalRow}")->getNumberFormat()->setFormatCode('0.0');
        }

        return $styles;
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}
