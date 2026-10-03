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
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * O'qituvchilar baholarni qachon qo'ygani — tanlangan sana oralig'idagi
 * bakalavr darslari bo'yicha, o'qituvchi + fan + guruh kesimida, har bir
 * talabaga qo'yilgan baho sanaladi.
 *
 * Baho qo'yilgan vaqt manbasi qatorning kelib chiqishiga qarab:
 *   - HEMIS'dan kelgan baho        — created_at_api (HEMIS vaqti);
 *   - LMS'da qo'yilgan baho        — created_at (dars ochish / muddat ichida /
 *                                    admin to'ldirgan bo'sh katak);
 *   - sababli ariza bo'yicha baho  — updated_at (NB qatori ustiga yozilgan).
 *
 * Dars kunining o'zida qo'yilganlar soat bo'yicha (dars vaqtida / ish
 * vaqtida / 18:00 dan keyin), keyingi kunlarda qo'yilganlar esa necha kun
 * kechikkani bo'yicha (1, 2–3, 4–7, 7+) ajratiladi.
 *
 * NB (grade = null) qatorlar hisobga olinmaydi; OSKI, test, oraliq nazorat,
 * mustaqil ta'lim va quiz ham — ular kundalik dars bahosi emas.
 */
class TeacherGradeTimingExport implements FromCollection, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    use Exportable;

    /** Kundalik dars bo'lmagan baho turlari */
    private const EXCLUDED_TRAINING_TYPES = [99, 100, 101, 102, 103];

    /** LMS ichida yaratilgan JN baholarining hemis_id belgilari */
    private const LMS_HEMIS_IDS = [0, 77777777, 88888888];

    private const AFTER_HOURS_FROM = '18:00:00';

    /**
     * Ustunlar: [kalit, sarlavha, sarlavha rangi, qator rangi].
     * Rang — har bir ustun uchun alohida, foiz ustuni o'z soni bilan bir xil.
     */
    private const COLUMNS = [
        ['num',       '#',                       '1A3268', 'FFFFFF'],
        ['teacher',   "O'qituvchi",              '1A3268', 'FFFFFF'],
        ['dept',      'Kafedra',                 '1A3268', 'FFFFFF'],
        ['subject',   'Fan',                     '1A3268', 'FFFFFF'],
        ['group',     'Guruh',                   '1A3268', 'FFFFFF'],
        ['during',    'Dars vaqtida (soni)',     '548235', 'E2EFDA'],
        ['work',      'Ish vaqtida (soni)',      '2F75B5', 'DDEBF7'],
        ['evening',   '18:00 dan keyin (soni)',  'BF8F00', 'FFF2CC'],
        ['d1',        '1 kun keyin (soni)',      'C65911', 'FCE4D6'],
        ['d23',       '2–3 kun keyin (soni)',    'C55A11', 'F8CBAD'],
        ['d47',       '4–7 kun keyin (soni)',    'B4511E', 'F4B183'],
        ['d8',        '7 kundan keyin (soni)',   'C00000', 'FFC7CE'],
        ['total',     'Jami',                    '3F3F3F', 'EDEDED'],
        ['lms',       "shundan LMS'da (soni)",   '7030A0', 'E4DFEC'],
        ['during_p',  'Dars vaqtida %',          '548235', 'E2EFDA'],
        ['work_p',    'Ish vaqtida %',           '2F75B5', 'DDEBF7'],
        ['evening_p', '18:00 dan keyin %',       'BF8F00', 'FFF2CC'],
        ['d1_p',      '1 kun keyin %',           'C65911', 'FCE4D6'],
        ['d23_p',     '2–3 kun keyin %',         'C55A11', 'F8CBAD'],
        ['d47_p',     '4–7 kun keyin %',         'B4511E', 'F4B183'],
        ['d8_p',      '7 kundan keyin %',        'C00000', 'FFC7CE'],
        ['avg_late',  "O'rtacha kechikish (kun)", '7F6000', 'FFEB9C'],
    ];

    private int $dataRows = 0;

    /**
     * @param array{faculty_hemis_id?: string|null, specialty_hemis_id?: string|null, level_code?: string|null, semester_code?: string|null, group_hemis_id?: string|null, employee_id?: string|null} $filters
     */
    public function __construct(private string $dateFrom, private string $dateTo, private array $filters = [])
    {
    }

    public function title(): string
    {
        return 'Baho vaqti';
    }

    public function collection(): Collection
    {
        $lmsIds = implode(',', self::LMS_HEMIS_IDS);

        // Qator kelib chiqishiga qarab baho qo'yilgan haqiqiy vaqt
        $enteredAt = "(CASE
            WHEN sg.reason = 'absent' AND sg.grade IS NOT NULL THEN sg.updated_at
            WHEN sg.hemis_id IN ({$lmsIds}) THEN sg.created_at
            ELSE sg.created_at_api END)";
        $isLms = "(sg.hemis_id IN ({$lmsIds}) OR (sg.reason = 'absent' AND sg.grade IS NOT NULL))";

        // Dars kunidan necha kun keyin qo'yilgan (0 — o'sha kuni)
        $daysLate = "DATEDIFF(DATE({$enteredAt}), DATE(sg.lesson_date))";
        $sameDay = "{$daysLate} <= 0";

        // Juftlik vaqti bo'sh bo'lsa ifoda NULL — COALESCE bilan "dars vaqtida emas"
        $during = "COALESCE(({$sameDay} AND TIME({$enteredAt}) BETWEEN CAST(sg.lesson_pair_start_time AS TIME) AND CAST(sg.lesson_pair_end_time AS TIME)), 0) = 1";
        $evening = "{$sameDay} AND NOT ({$during}) AND TIME({$enteredAt}) >= '" . self::AFTER_HOURS_FROM . "'";

        $rows = DB::table('student_grades as sg')
            ->join('students as st', 'st.hemis_id', '=', 'sg.student_hemis_id')
            ->join('groups as g', 'g.group_hemis_id', '=', 'st.group_id')
            ->join('curricula as c', 'c.curricula_hemis_id', '=', 'g.curriculum_hemis_id')
            ->leftJoin('teachers as t', 't.hemis_id', '=', 'sg.employee_id')
            ->whereNull('sg.deleted_at')
            ->whereNotNull('sg.grade')
            ->whereBetween('sg.lesson_date', [$this->dateFrom . ' 00:00:00', $this->dateTo . ' 23:59:59'])
            ->whereNotIn('sg.training_type_code', self::EXCLUDED_TRAINING_TYPES)
            ->whereRaw('LOWER(c.education_type_name) LIKE ?', ['%bakalavr%'])
            // Modal filtrlari: fakultet/yo'nalish/guruh — talabaning guruhi orqali,
            // semestr va o'qituvchi — baho qatoridan, kurs — semestr jadvalidan
            ->when(!empty($this->filters['faculty_hemis_id']), fn ($q) => $q->where('g.department_hemis_id', $this->filters['faculty_hemis_id']))
            ->when(!empty($this->filters['specialty_hemis_id']), fn ($q) => $q->where('g.specialty_hemis_id', $this->filters['specialty_hemis_id']))
            ->when(!empty($this->filters['level_code']), fn ($q) => $q->whereExists(function ($sub) {
                $sub->select(DB::raw(1))
                    ->from('semesters as sem')
                    ->whereColumn('sem.curriculum_hemis_id', 'g.curriculum_hemis_id')
                    ->whereColumn('sem.code', 'sg.semester_code')
                    ->where('sem.level_code', $this->filters['level_code']);
            }))
            ->when(!empty($this->filters['group_hemis_id']), fn ($q) => $q->where('st.group_id', $this->filters['group_hemis_id']))
            ->when(!empty($this->filters['semester_code']), fn ($q) => $q->where('sg.semester_code', $this->filters['semester_code']))
            ->when(!empty($this->filters['employee_id']), fn ($q) => $q->where('sg.employee_id', $this->filters['employee_id']))
            ->groupBy('sg.employee_id', 'sg.employee_name', 't.department', 'sg.subject_id', 'sg.subject_name', 'g.group_hemis_id', 'g.name')
            ->orderBy('sg.employee_name')
            ->orderBy('sg.subject_name')
            ->orderBy('g.name')
            ->select([
                'sg.employee_id',
                'sg.employee_name',
                't.department',
                'sg.subject_name',
                'g.name as group_name',
                DB::raw("SUM(CASE WHEN {$during} THEN 1 ELSE 0 END) AS during_lesson"),
                DB::raw("SUM(CASE WHEN {$evening} THEN 1 ELSE 0 END) AS evening"),
                DB::raw("SUM(CASE WHEN {$daysLate} = 1 THEN 1 ELSE 0 END) AS d1"),
                DB::raw("SUM(CASE WHEN {$daysLate} BETWEEN 2 AND 3 THEN 1 ELSE 0 END) AS d23"),
                DB::raw("SUM(CASE WHEN {$daysLate} BETWEEN 4 AND 7 THEN 1 ELSE 0 END) AS d47"),
                DB::raw("SUM(CASE WHEN {$daysLate} > 7 THEN 1 ELSE 0 END) AS d8"),
                DB::raw("SUM(CASE WHEN {$isLms} THEN 1 ELSE 0 END) AS lms"),
                DB::raw("AVG(GREATEST({$daysLate}, 0)) AS avg_late"),
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

        $keys = ['during', 'work', 'evening', 'd1', 'd23', 'd47', 'd8', 'total', 'lms'];
        $totals = array_fill_keys($keys, 0);
        $lateDaysSum = 0.0;
        $out = collect();

        foreach ($rows as $i => $r) {
            $c = [
                'during' => (int) $r->during_lesson,
                'evening' => (int) $r->evening,
                'd1' => (int) $r->d1,
                'd23' => (int) $r->d23,
                'd47' => (int) $r->d47,
                'd8' => (int) $r->d8,
                'total' => (int) $r->total,
                'lms' => (int) $r->lms,
            ];
            // Ish vaqtida — qolgan hammasi, shunda ustunlar yig'indisi "Jami"ga teng chiqadi
            $c['work'] = $c['total'] - $c['during'] - $c['evening'] - $c['d1'] - $c['d23'] - $c['d47'] - $c['d8'];

            foreach ($keys as $k) {
                $totals[$k] += $c[$k];
            }
            $lateDaysSum += (float) $r->avg_late * $c['total'];

            $out->push($this->row(
                $i + 1,
                $r->employee_name,
                blank($r->department) ? ($scheduleDepartments[$r->employee_id] ?? '-') : $r->department,
                $r->subject_name ?? '-',
                $r->group_name ?? '-',
                $c,
                round((float) $r->avg_late, 1)
            ));
        }

        $this->dataRows = $out->count();

        if ($this->dataRows > 0) {
            $out->push($this->row(
                '',
                'JAMI',
                '',
                '',
                '',
                $totals,
                $totals['total'] > 0 ? round($lateDaysSum / $totals['total'], 1) : 0.0
            ));
        }

        return $out;
    }

    public function headings(): array
    {
        return array_column(self::COLUMNS, 1);
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $this->dataRows + 1;
        $totalRow = $this->dataRows > 0 ? $lastRow + 1 : null;
        $endRow = $totalRow ?? $lastRow;
        $lastCol = count(self::COLUMNS);

        // Har bir ustun o'z rangida: sarlavha to'q, qatorlar och
        foreach (self::COLUMNS as $index => [$key, $heading, $headerColor, $bodyColor]) {
            $col = $index + 1;
            $letter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($col);

            $sheet->getStyle("{$letter}1")->applyFromArray([
                'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => $headerColor]],
                'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            ]);

            if ($this->dataRows > 0 && $bodyColor !== 'FFFFFF') {
                $sheet->getStyle("{$letter}2:{$letter}{$endRow}")->getFill()
                    ->setFillType('solid')->getStartColor()->setRGB($bodyColor);
            }

            if ($col >= 6) {
                $sheet->getStyle("{$letter}2:{$letter}{$endRow}")->getAlignment()
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if (str_ends_with($key, '_p') || $key === 'avg_late') {
                $sheet->getStyle("{$letter}2:{$letter}{$endRow}")->getNumberFormat()->setFormatCode('0.0');
            }
        }

        $endLetter = \PhpOffice\PhpSpreadsheet\Cell\Coordinate::stringFromColumnIndex($lastCol);
        $sheet->getStyle("A1:{$endLetter}{$endRow}")->getBorders()->getAllBorders()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('BFBFBF');
        $sheet->getRowDimension(1)->setRowHeight(44);
        $sheet->freezePane('F2');

        $styles = [];
        if ($totalRow) {
            $styles[$totalRow] = ['font' => ['bold' => true]];
        }

        return $styles;
    }

    private function row($num, string $teacher, string $dept, string $subject, string $group, array $c, float $avgLate): array
    {
        $t = $c['total'];

        return [
            $num,
            $teacher,
            $dept,
            $subject,
            $group,
            $c['during'],
            $c['work'],
            $c['evening'],
            $c['d1'],
            $c['d23'],
            $c['d47'],
            $c['d8'],
            $t,
            $c['lms'],
            $this->percent($c['during'], $t),
            $this->percent($c['work'], $t),
            $this->percent($c['evening'], $t),
            $this->percent($c['d1'], $t),
            $this->percent($c['d23'], $t),
            $this->percent($c['d47'], $t),
            $this->percent($c['d8'], $t),
            $avgLate,
        ];
    }

    private function percent(int $part, int $total): float
    {
        return $total > 0 ? round($part / $total * 100, 1) : 0.0;
    }
}
