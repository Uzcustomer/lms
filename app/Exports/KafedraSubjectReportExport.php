<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/** Fanlar bo'yicha hisobot: har guruh alohida varaqda — talaba × sana, oxirida JN va MT/ON/OSKI/Test. */
class KafedraSubjectReportExport implements WithMultipleSheets
{
    public function __construct(private object $unit, private Collection $groups, private array $grades, private ?string $from, private ?string $to) {}

    public function sheets(): array
    {
        $sheets = [];
        $used = [];
        foreach ($this->groups as $g) {
            $title = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', '-', $g->name)), 0, 28) ?: 'Guruh';
            $base = $title;
            for ($i = 2; isset($used[mb_strtolower($title)]); $i++) {
                $title = $base . ' ' . $i;
            }
            $used[mb_strtolower($title)] = true;
            $sheets[] = new KafedraSubjectGroupSheet($title, $this->unit, $g, $this->grades, $this->from, $this->to);
        }

        return $sheets ?: [new KafedraSubjectGroupSheet("Ma'lumot yo'q", $this->unit, (object) ['name' => '', 'students' => collect()], [], $this->from, $this->to)];
    }
}

class KafedraSubjectGroupSheet implements FromArray, WithEvents, WithTitle, WithStrictNullComparison
{
    private int $lastRow = 2;
    private int $dateCount = 0;
    private array $types = [];

    public function __construct(private string $title, private object $unit, private object $group, private array $grades, private ?string $from, private ?string $to) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $sub = $this->unit->subject_id;
        $dates = [];
        $seen = [];
        foreach ($this->group->students as $s) {
            $g = $this->grades[(int) $s->hemis_id][$sub] ?? [];
            foreach (array_keys($g['days'] ?? []) as $d) {
                $dates[$d] = true;
            }
            foreach (array_keys($g['other'] ?? []) as $t) {
                $seen[$t] = true;
            }
        }
        ksort($dates);
        $dates = array_keys($dates);
        $this->dateCount = count($dates);
        $this->types = array_values(array_filter(['MT', 'ON', 'OSKI', 'Test', 'Quiz'], fn ($t) => $t !== 'Quiz' || isset($seen[$t])));

        $period = $this->from || $this->to
            ? ' · ' . ($this->from ? date('d.m.Y', strtotime($this->from)) : '…') . ' – ' . ($this->to ? date('d.m.Y', strtotime($this->to)) : '…')
            : ' · joriy semestr';
        $width = 3 + count($dates) + 1 + count($this->types);
        $rows = [
            array_pad([$this->unit->subject_name . ' · ' . $this->group->name . ' · ' . $this->unit->specialty_name . ', ' . $this->unit->level_name . ', ' . $this->unit->semester_name . $period], $width, ''),
            array_merge(['№', 'F.I.Sh.', 'ID raqam'], array_map(fn ($d) => date('d.m', strtotime($d)), $dates), ['JN'], $this->types),
        ];
        foreach ($this->group->students->values() as $i => $s) {
            $g = $this->grades[(int) $s->hemis_id][$sub] ?? [];
            $row = [$i + 1, $s->full_name, $s->student_id_number];
            foreach ($dates as $d) {
                $row[] = $g['days'][$d] ?? '';
            }
            $row[] = $g['jn'] ?? '';
            foreach ($this->types as $t) {
                $vals = $g['other'][$t] ?? [];
                $row[] = count($vals) === 1 && is_numeric($vals[0]) ? (float) $vals[0] : implode(' / ', $vals);
            }
            $rows[] = $row;
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
                $firstDate = 4;
                $jnCol = $firstDate + $this->dateCount;
                $lastCol = $col($jnCol + count($this->types));

                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(34);
                $sheet->getColumnDimension('C')->setWidth(15);
                for ($i = $firstDate; $i < $jnCol; $i++) {
                    $sheet->getColumnDimension($col($i))->setWidth(6.5);
                }
                for ($i = $jnCol; $i <= $jnCol + count($this->types); $i++) {
                    $sheet->getColumnDimension($col($i))->setWidth(8);
                }
                $sheet->mergeCells('A1:' . $lastCol . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1B3A63');
                $head = 'A2:' . $lastCol . '2';
                $sheet->getStyle($head)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle($head)->getAlignment()->setHorizontal('center')->setVertical('center');
                $sheet->getStyle($col($jnCol) . '2')->getFill()->getStartColor()->setRGB('166534');
                if ($this->types) {
                    $sheet->getStyle($col($jnCol + 1) . '2:' . $lastCol . '2')->getFill()->getStartColor()->setRGB('2B5EA7');
                }
                $sheet->freezePane($col($firstDate) . '3');
                if ($last > 2) {
                    $sheet->getStyle('A3:' . $lastCol . $last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E1EC');
                    $sheet->getStyle('A3:A' . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle($col($firstDate) . '3:' . $lastCol . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle($col($jnCol) . '3:' . $col($jnCol) . $last)->getFont()->setBold(true);
                    $sheet->getStyle($col($jnCol) . '3:' . $col($jnCol) . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F9F3');
                    $low = new Conditional();
                    $low->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_BETWEEN)->addCondition('0.01')->addCondition('59.99');
                    $low->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B3261E');
                    $nb = new Conditional();
                    $nb->setConditionType(Conditional::CONDITION_CONTAINSTEXT)->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)->setText('NB');
                    $nb->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $sheet->getStyle($col($firstDate) . '3:' . $lastCol . $last)->setConditionalStyles([$nb, $low]);
                }
            },
        ];
    }
}
