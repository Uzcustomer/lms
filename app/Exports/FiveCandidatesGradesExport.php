<?php

namespace App\Exports;

use App\Services\FiveCandidateGrades;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Conditional;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "5 ga da'vogar" talabalarning davrdagi barcha baholari: har fakultet alohida varaqda,
 * har bir baho alohida qatorda (fan, sana, baho turi — joriy, MT, ON, OSKI, Test).
 */
class FiveCandidatesGradesExport implements WithMultipleSheets
{
    public function __construct(private Collection $students, private Collection $grades, private string $from, private string $to) {}

    public function sheets(): array
    {
        $sheets = [];
        $used = [];
        foreach ($this->students->groupBy(fn ($s) => $s->department_name ?: 'Fakultetsiz') as $faculty => $students) {
            $title = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', ' ', (string) $faculty)), 0, 28) ?: 'Fakultet';
            $base = $title;
            for ($i = 2; isset($used[mb_strtolower($title)]); $i++) {
                $title = $base . ' ' . $i;
            }
            $used[mb_strtolower($title)] = true;
            $sheets[] = new FiveCandidatesFacultySheet($title, (string) $faculty, $students, $this->grades, $this->from, $this->to);
        }
        if (!$sheets) {
            $sheets[] = new FiveCandidatesFacultySheet("Ma'lumot yo'q", '', collect(), collect(), $this->from, $this->to);
        }

        return $sheets;
    }
}

class FiveCandidatesFacultySheet implements FromArray, WithColumnWidths, WithEvents, WithTitle, WithStrictNullComparison
{
    private int $lastRow = 2;

    public function __construct(
        private string $title,
        private string $faculty,
        private Collection $students,
        private Collection $grades,
        private string $from,
        private string $to,
    ) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $period = date('d.m.Y', strtotime($this->from)) . ' – ' . date('d.m.Y', strtotime($this->to));
        $rows = [
            [($this->faculty ?: "5 ga da'vogarlar") . " · 5 ga da'vogar talabalar baholari · " . $period, '', '', '', '', '', '', '', '', '', ''],
            ['№', 'F.I.Sh.', 'Guruh', "Yo'nalish", 'Semestr', 'Fan', 'Sana', 'Juftlik', 'Baho turi', 'Baho', 'Izoh'],
        ];
        $n = 0;
        foreach ($this->students as $s) {
            $n++;
            $list = $this->grades->get($s->hemis_id, collect());
            if ($list->isEmpty()) {
                $rows[] = [$n, $s->full_name, $s->group_name, $s->specialty_name, $s->semester_name, '— davrda baho yo\'q —', '', '', '', '', ''];
                continue;
            }
            foreach ($list as $g) {
                $date = $g->lesson_date ?: $g->created_at;
                $rows[] = [
                    $n, $s->full_name, $s->group_name, $s->specialty_name, $g->semester_name ?: $s->semester_name,
                    $g->subject_name, $date ? date('d.m.Y', strtotime($date)) : '', (string) ($g->lesson_pair_name ?? ''),
                    FiveCandidateGrades::typeName($g), FiveCandidateGrades::value($g), FiveCandidateGrades::note($g),
                ];
            }
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 34, 'C' => 18, 'D' => 26, 'E' => 12, 'F' => 40, 'G' => 11, 'H' => 9, 'I' => 11, 'J' => 8, 'K' => 26];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                $sheet->mergeCells('A1:K1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1B3A63');
                $sheet->getStyle('A2:K2')->getFont()->setBold(true)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle('A2:K2')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->freezePane('A3');
                if ($last > 2) {
                    $sheet->setAutoFilter('A2:K' . $last);
                    $sheet->getStyle('A3:A' . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle('G3:J' . $last)->getAlignment()->setHorizontal('center');
                    // 90 dan past baho — qizil, NB — sariq
                    $low = new Conditional();
                    $low->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_LESSTHAN)->addCondition('90');
                    $low->getStyle()->getFont()->getColor()->setRGB('B3261E');
                    $low->getStyle()->getFont()->setBold(true);
                    $nb = new Conditional();
                    $nb->setConditionType(Conditional::CONDITION_CONTAINSTEXT)->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)->setText('NB');
                    $nb->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $sheet->getStyle('J3:J' . $last)->setConditionalStyles([$nb, $low]);
                }
            },
        ];
    }
}
