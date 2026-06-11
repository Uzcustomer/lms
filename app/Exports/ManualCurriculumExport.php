<?php

namespace App\Exports;

use App\Models\ManualCurriculum;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ManualCurriculumExport implements FromArray, ShouldAutoSize, WithStrictNullComparison, WithStyles, WithTitle
{
    // Row metadata for styling (set during buildSetka / buildJadval)
    /** @var array<int,string> rowIndex(1-based) => 'block'|'summary'|'data' */
    private array $rowMeta = [];
    private int $maxSem = 12;
    /** @var array<string,int> block name => first data row (1-based) used for summary calc */
    private array $blockStartRow = [];

    public function __construct(
        private ManualCurriculum $curriculum,
        private string $format = 'jadval',
    ) {
    }

    public function title(): string
    {
        return mb_substr($this->curriculum->name, 0, 31);
    }

    // -------------------------------------------------------------------------
    // FromArray
    // -------------------------------------------------------------------------
    public function array(): array
    {
        return $this->format === 'setka' ? $this->buildSetka() : $this->buildJadval();
    }

    // -------------------------------------------------------------------------
    // Jadval (list) format
    // -------------------------------------------------------------------------
    private function buildJadval(): array
    {
        $rows = [];
        $fmt = fn($v) => $v === null ? null : (float) $v;

        $rows[] = [$this->curriculum->name];
        $rows[] = [
            'T/r', 'Blok', 'Fan kodi', 'Fan nomi', "Namunaviy rejadagi nomi",
            'Kurs', 'Semestr', 'Umumiy soat', "Ma'ruza", 'Amaliy',
            'Laboratoriya', 'Seminar', "Mustaqil ta'lim", 'Kredit', 'Izoh',
        ];
        $this->rowMeta[1] = 'title';
        $this->rowMeta[2] = 'header';

        $prevBlock = null;
        $num = 0;
        foreach ($this->curriculum->subjects as $subject) {
            if ($subject->block !== $prevBlock) {
                $rowIdx = count($rows) + 1;
                $rows[] = [$subject->block];
                $this->rowMeta[$rowIdx] = 'block';
                $prevBlock = $subject->block;
            }
            $num++;
            $rowIdx = count($rows) + 1;
            $rows[] = [
                $num,
                $subject->block,
                $subject->subject_code,
                $subject->subject_name,
                $subject->reference_name,
                $subject->kurs,
                $subject->semester,
                $fmt($subject->total_hours),
                $fmt($subject->lecture),
                $fmt($subject->practice),
                $fmt($subject->laboratory),
                $fmt($subject->seminar),
                $fmt($subject->independent),
                $fmt($subject->credit),
                $subject->note,
            ];
            $this->rowMeta[$rowIdx] = 'data';
        }

        return $rows;
    }

    // -------------------------------------------------------------------------
    // Setka (grid) format
    // -------------------------------------------------------------------------
    private function buildSetka(): array
    {
        // Determine max semester from subjects
        $this->maxSem = max(12, $this->curriculum->subjects->max('semester') ?? 12);

        $groups = $this->groupSubjects();
        $kursCount = (int) ceil($this->maxSem / 2);

        $totalCols = 10 + $this->maxSem + 1; // A-J + semesters + Jami kredit
        $lastCol = Coordinate::stringFromColumnIndex($totalCols);
        $semStartCol = Coordinate::stringFromColumnIndex(11); // K

        // Row 1: title
        $titleRow = array_fill(0, $totalCols, null);
        $titleRow[0] = "O'QUV REJASI — " . $this->curriculum->name;
        $this->rowMeta[1] = 'title';

        // Row 2: main headers
        $h2 = array_fill(0, $totalCols, null);
        $h2[0] = 'T/r';
        $h2[1] = 'Fan kodi';
        $h2[2] = "O'quv bloklari, fanlar va faoliyat turlarining nomlari";
        $h2[3] = "Umumiy\nyuklama\n(soat)";
        $h2[4] = "Auditoriya mashg'ulotlari (soatlarida)";
        $h2[9] = "Mustaqil\nta'lim";
        $h2[10] = "Semestrlar bo'yicha kredit taqsimoti";
        $h2[$totalCols - 1] = "Jami\nkredit";
        $this->rowMeta[2] = 'header';

        // Row 3: kurs sub-headers
        $h3 = array_fill(0, $totalCols, null);
        $h3[4] = 'Jami';
        $h3[5] = "Ma'ruza";
        $h3[6] = 'Amaliy';
        $h3[7] = 'Laboratoriya';
        $h3[8] = 'Seminar';
        for ($k = 1; $k <= $kursCount; $k++) {
            $col = 10 + ($k - 1) * 2; // 0-based: K=10, M=12, O=14...
            $semStart = ($k - 1) * 2 + 1;
            $semEnd = $k * 2;
            $h3[$col] = "{$k}-kurs\n(sem {$semStart}-{$semEnd})";
        }
        $this->rowMeta[3] = 'header';

        // Row 4: semester numbers
        $h4 = array_fill(0, $totalCols, null);
        for ($s = 1; $s <= $this->maxSem; $s++) {
            $h4[10 + $s - 1] = $s;
        }
        $this->rowMeta[4] = 'header';

        $data = [$titleRow, $h2, $h3, $h4];

        // Data rows
        $prevBlock = null;
        $num = 0;
        $blockGroupCredits = array_fill(1, $this->maxSem, 0);
        $blockTotalHours = 0;
        $blockTotalCredit = 0;

        foreach ($groups as $group) {
            // Block header
            if ($group['block'] !== $prevBlock) {
                if ($prevBlock !== null) {
                    // Summary row for previous block
                    $sumRow = array_fill(0, $totalCols, null);
                    $sumRow[0] = $prevBlock . ' jami';
                    $sumRow[3] = $blockTotalHours ?: null;
                    for ($s = 1; $s <= $this->maxSem; $s++) {
                        $sumRow[10 + $s - 1] = $blockGroupCredits[$s] ?: null;
                    }
                    $sumRow[$totalCols - 1] = $blockTotalCredit ?: null;
                    $rowIdx = count($data) + 1;
                    $data[] = $sumRow;
                    $this->rowMeta[$rowIdx] = 'summary';
                }
                $blockGroupCredits = array_fill(1, $this->maxSem, 0);
                $blockTotalHours = 0;
                $blockTotalCredit = 0;

                $blockRow = array_fill(0, $totalCols, null);
                $blockRow[0] = $group['block'];
                $rowIdx = count($data) + 1;
                $data[] = $blockRow;
                $this->rowMeta[$rowIdx] = 'block';
                $prevBlock = $group['block'];
                $num = 0;
            }

            $num++;
            $row = array_fill(0, $totalCols, null);
            $row[0] = $num;
            $row[1] = $group['subject_code'];
            $row[2] = $group['subject_name'];
            $row[3] = $group['total_hours'] ? (float) $group['total_hours'] : null;
            $row[4] = $group['audit_total'] ? (float) $group['audit_total'] : null;
            $row[5] = $group['lecture'] ? (float) $group['lecture'] : null;
            $row[6] = $group['practice'] ? (float) $group['practice'] : null;
            $row[7] = $group['laboratory'] ? (float) $group['laboratory'] : null;
            $row[8] = $group['seminar'] ? (float) $group['seminar'] : null;
            $row[9] = $group['independent'] ? (float) $group['independent'] : null;

            $totalCredit = 0;
            foreach ($group['credits'] as $semester => $credit) {
                if ($semester >= 1 && $semester <= $this->maxSem) {
                    $row[10 + $semester - 1] = $credit ?: null;
                    $blockGroupCredits[$semester] += $credit;
                    $totalCredit += $credit;
                }
            }
            $row[$totalCols - 1] = $totalCredit ?: null;
            $blockTotalCredit += $totalCredit;
            $blockTotalHours += (float) ($group['total_hours'] ?? 0);

            $rowIdx = count($data) + 1;
            $data[] = $row;
            $this->rowMeta[$rowIdx] = 'data';
        }

        // Last block summary
        if ($prevBlock !== null) {
            $sumRow = array_fill(0, $totalCols, null);
            $sumRow[0] = $prevBlock . ' jami';
            $sumRow[3] = $blockTotalHours ?: null;
            for ($s = 1; $s <= $this->maxSem; $s++) {
                $sumRow[10 + $s - 1] = $blockGroupCredits[$s] ?: null;
            }
            $sumRow[$totalCols - 1] = $blockTotalCredit ?: null;
            $rowIdx = count($data) + 1;
            $data[] = $sumRow;
            $this->rowMeta[$rowIdx] = 'summary';
        }

        // Grand total
        $grandHours = $this->curriculum->subjects
            ->whereNotNull('total_hours')->unique(fn($s) => ($s->subject_code ?? '') . '|' . $s->subject_name . '|' . ($s->block ?? ''))
            ->sum(fn($s) => (float) $s->total_hours);
        $grandCredits = array_fill(1, $this->maxSem, 0);
        foreach ($this->curriculum->subjects as $subject) {
            if ($subject->semester && $subject->credit) {
                $s = (int) $subject->semester;
                if ($s >= 1 && $s <= $this->maxSem) {
                    $grandCredits[$s] += (float) $subject->credit;
                }
            }
        }
        $grandCredit = array_sum($grandCredits);

        $totalRow = array_fill(0, $totalCols, null);
        $totalRow[0] = 'HAMMASI';
        $totalRow[3] = $grandHours ?: null;
        for ($s = 1; $s <= $this->maxSem; $s++) {
            $totalRow[10 + $s - 1] = $grandCredits[$s] ?: null;
        }
        $totalRow[$totalCols - 1] = $grandCredit ?: null;
        $rowIdx = count($data) + 1;
        $data[] = $totalRow;
        $this->rowMeta[$rowIdx] = 'total';

        return $data;
    }

    // -------------------------------------------------------------------------
    // Group consecutive rows with same (block|code|name) back into subjects
    // -------------------------------------------------------------------------
    private function groupSubjects(): array
    {
        $groups = [];
        $prevKey = null;

        foreach ($this->curriculum->subjects->sortBy('id') as $subject) {
            $key = ($subject->block ?? '') . '||' . ($subject->subject_code ?? '') . '||' . $subject->subject_name;
            $isNewGroup = ($key !== $prevKey)
                || ($subject->note && str_starts_with((string) $subject->note, 'Jami'));
            if ($isNewGroup) {
                $groups[] = [
                    'block' => $subject->block,
                    'subject_code' => $subject->subject_code,
                    'subject_name' => $subject->subject_name,
                    'total_hours' => $subject->total_hours,
                    'audit_total' => $subject->audit_total,
                    'lecture' => $subject->lecture,
                    'practice' => $subject->practice,
                    'laboratory' => $subject->laboratory,
                    'seminar' => $subject->seminar,
                    'independent' => $subject->independent,
                    'credits' => [],
                ];
                $prevKey = $key;
            }
            if ($subject->semester !== null && $subject->credit !== null) {
                $groups[count($groups) - 1]['credits'][(int) $subject->semester] = (float) $subject->credit;
            } elseif ($subject->credit !== null) {
                $groups[count($groups) - 1]['credits'][0] = (float) $subject->credit;
            }
        }

        return $groups;
    }

    // -------------------------------------------------------------------------
    // WithStyles
    // -------------------------------------------------------------------------
    public function styles(Worksheet $sheet)
    {
        $maxRow = count($this->rowMeta);

        // Borders on all data area
        if ($maxRow > 1) {
            $lastCol = $sheet->getHighestColumn();
            $sheet->getStyle("A1:{$lastCol}{$maxRow}")->applyFromArray([
                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['rgb' => 'D1D5DB']]],
            ]);
        }

        $styles = [];

        foreach ($this->rowMeta as $rowNum => $type) {
            match ($type) {
                'title' => $styles[$rowNum] = [
                    'font' => ['bold' => true, 'size' => 12],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EFF6FF']],
                ],
                'header' => $styles[$rowNum] = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1e3a5f']],
                    'alignment' => ['wrapText' => true, 'vertical' => Alignment::VERTICAL_CENTER, 'horizontal' => Alignment::HORIZONTAL_CENTER],
                ],
                'block' => $styles[$rowNum] = [
                    'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '2563EB']],
                ],
                'summary' => $styles[$rowNum] = [
                    'font' => ['bold' => true, 'italic' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'DBEAFE']],
                ],
                'total' => $styles[$rowNum] = [
                    'font' => ['bold' => true, 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'BBF7D0']],
                ],
                default => null,
            };
        }

        // Setka: merge multi-column header cells
        if ($this->format === 'setka') {
            $totalCols = 10 + $this->maxSem + 1;
            $lastCol = Coordinate::stringFromColumnIndex($totalCols);
            $semEndCol = Coordinate::stringFromColumnIndex(10 + $this->maxSem);
            $kursCount = (int) ceil($this->maxSem / 2);

            // Row 1: title spans all columns
            $sheet->mergeCells("A1:{$lastCol}1");
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

            // Row 2: Auditoriya E2:I2, Semestrlar K2:lastSemCol
            $sheet->mergeCells('E2:I2');
            $sheet->mergeCells('K2:' . $semEndCol . '2');
            // T/r, Fan kodi, Fan nomi, Umumiy, Mustaqil, Jami kredit — span rows 2-4
            foreach (['A', 'B', 'C', 'D', 'J', $lastCol] as $col) {
                $sheet->mergeCells("{$col}2:{$col}4");
                $sheet->getStyle("{$col}2")->getAlignment()
                    ->setVertical(Alignment::VERTICAL_CENTER)
                    ->setHorizontal(Alignment::HORIZONTAL_CENTER)
                    ->setWrapText(true);
            }

            // Rotate narrow header columns upward (D and J span rows 2-4; E-I row 3)
            foreach (['D', 'J'] as $col) {
                $a = $sheet->getStyle("{$col}2")->getAlignment();
                $a->setTextRotation(90);
                $a->setWrapText(false);
            }
            $sheet->getStyle('E3:I3')->getAlignment()->setTextRotation(90)->setWrapText(false);

            // Row 3: kurs pairs (K3:L3, M3:N3, ...)
            for ($k = 0; $k < $kursCount; $k++) {
                $c1 = Coordinate::stringFromColumnIndex(11 + $k * 2);
                $c2 = Coordinate::stringFromColumnIndex(12 + $k * 2);
                if ((11 + $k * 2) <= (10 + $this->maxSem)) {
                    $sheet->mergeCells("{$c1}3:{$c2}3");
                    $sheet->getStyle("{$c1}3")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER)->setWrapText(true);
                }
            }

            // Row heights for header rows
            $sheet->getRowDimension(1)->setRowHeight(22);
            $sheet->getRowDimension(2)->setRowHeight(40);
            $sheet->getRowDimension(3)->setRowHeight(34);
            $sheet->getRowDimension(4)->setRowHeight(18);

            // Block rows: span A to last col
            foreach ($this->rowMeta as $rowNum => $type) {
                if ($type === 'block' || $type === 'summary' || $type === 'total') {
                    $sheet->mergeCells("A{$rowNum}:{$lastCol}{$rowNum}");
                }
            }

            // Freeze header rows
            $sheet->freezePane('A5');
        } else {
            // Jadval: title spans A-O
            $sheet->mergeCells('A1:O1');
            $sheet->getStyle('A1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getRowDimension(1)->setRowHeight(22);

            // Block rows span all columns
            foreach ($this->rowMeta as $rowNum => $type) {
                if ($type === 'block') {
                    $sheet->mergeCells("A{$rowNum}:O{$rowNum}");
                }
            }

            $sheet->freezePane('A3');
        }

        // Column widths
        $sheet->getColumnDimension('A')->setWidth(6);
        if ($this->format === 'setka') {
            $sheet->getColumnDimension('B')->setWidth(16);
            $sheet->getColumnDimension('C')->setWidth(48);
            $sheet->getColumnDimension('D')->setWidth(10);
            foreach (range('E', 'J') as $col) {
                $sheet->getColumnDimension($col)->setWidth(9);
            }
            for ($s = 1; $s <= $this->maxSem; $s++) {
                $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(10 + $s))->setWidth(5);
            }
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex(11 + $this->maxSem))->setWidth(8);
        } else {
            $sheet->getColumnDimension('B')->setWidth(28);
            $sheet->getColumnDimension('C')->setWidth(14);
            $sheet->getColumnDimension('D')->setWidth(48);
        }

        return $styles;
    }
}
