<?php

namespace App\Exports;

use App\Services\FiveCandidateGrades;
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

/**
 * "5 ga da'vogar" talabalarning davrdagi baholari: har fakultet alohida varaqda.
 * Bitta qator — talaba × fan; har bir sana alohida ustunda (shu kundagi baholar
 * o'rtachasi, hammasi kelmagan bo'lsa "NB"), oxirida JN (fan o'rtachasi) va
 * MT / ON / OSKI / Test / Quiz baholari.
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

class FiveCandidatesFacultySheet implements FromArray, WithEvents, WithTitle, WithStrictNullComparison
{
    private const FIXED = ['№', 'F.I.Sh.', 'Guruh', "Yo'nalish", 'Kurs', 'Semestr', 'Fan'];
    private const OTHER_TYPES = ['MT', 'ON', 'OSKI', 'Test', 'Quiz'];

    private int $lastRow = 2;
    private int $dateCount = 0;
    private array $otherTypes = [];

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

    private static function day($g): string
    {
        $d = $g->lesson_date ?: $g->created_at;

        return $d ? substr((string) $d, 0, 10) : '';
    }

    public function array(): array
    {
        // Varaqdagi barcha kunlik baho sanalari va uchragan boshqa baho turlari
        $dates = [];
        $types = [];
        foreach ($this->students as $s) {
            foreach ($this->grades->get($s->hemis_id, collect()) as $g) {
                if (FiveCandidateGrades::isDaily($g)) {
                    if ($day = self::day($g)) {
                        $dates[$day] = true;
                    }
                } else {
                    $types[FiveCandidateGrades::typeName($g)] = true;
                }
            }
        }
        ksort($dates);
        $dates = array_keys($dates);
        // MT/ON/OSKI/Test — doim (bo'sh bo'lsa ham), Quiz — faqat uchrasa
        $this->otherTypes = array_values(array_filter(self::OTHER_TYPES, fn ($t) => $t !== 'Quiz' || isset($types[$t])));
        $this->dateCount = count($dates);

        $width = count(self::FIXED) + count($dates) + 1 + count($this->otherTypes);
        $period = date('d.m.Y', strtotime($this->from)) . ' – ' . date('d.m.Y', strtotime($this->to));
        $rows = [
            array_pad([($this->faculty ?: "5 ga da'vogarlar") . " · 5 ga da'vogar talabalar baholari · " . $period], $width, ''),
            array_merge(self::FIXED, array_map(fn ($d) => date('d.m', strtotime($d)), $dates), ['JN'], $this->otherTypes),
        ];

        $n = 0;
        foreach ($this->students as $s) {
            $n++;
            $list = $this->grades->get($s->hemis_id, collect());
            $fixed = fn ($subject) => [$n, $s->full_name, $s->group_name, $s->specialty_name, $s->level_name, $s->semester_name, $subject];
            if ($list->isEmpty()) {
                $rows[] = array_pad($fixed("— davrda baho yo'q —"), $width, '');
                continue;
            }
            $avgBySubject = collect(FiveCandidateGrades::subjectAverages($list))->keyBy('name');

            foreach ($list->groupBy('subject_name')->sortKeys() as $subject => $sg) {
                // Kunlik: shu kundagi baholar o'rtachasi (JN hisobi bilan bir xil), hammasi NB bo'lsa "NB"
                $byDay = [];
                foreach ($sg->filter(fn ($g) => FiveCandidateGrades::isDaily($g))->groupBy(fn ($g) => self::day($g)) as $day => $dg) {
                    $vals = $dg->map(fn ($g) => FiveCandidateGrades::value($g));
                    $nums = $vals->filter(fn ($v) => is_numeric($v));
                    $byDay[$day] = $nums->isEmpty()
                        ? ($vals->contains('NB') ? 'NB' : '')
                        : (int) round($vals->sum(fn ($v) => is_numeric($v) ? (float) $v : 0) / $vals->count());
                }
                // Boshqa turlar: bir nechta bo'lsa — sana tartibida "/" bilan
                $other = [];
                foreach ($sg->reject(fn ($g) => FiveCandidateGrades::isDaily($g))->sortBy(fn ($g) => self::day($g)) as $g) {
                    $v = FiveCandidateGrades::value($g);
                    if ($v !== '') {
                        $other[FiveCandidateGrades::typeName($g)][] = is_numeric($v) ? rtrim(rtrim(number_format((float) $v, 1, '.', ''), '0'), '.') : $v;
                    }
                }
                $row = $fixed($subject);
                foreach ($dates as $d) {
                    $row[] = $byDay[$d] ?? '';
                }
                $jn = $avgBySubject->get($subject)['average'] ?? null;
                $row[] = $jn !== null && ($avgBySubject->get($subject)['days'] ?? 0) > 0 ? (float) $jn : '';
                foreach ($this->otherTypes as $t) {
                    $vals = $other[$t] ?? [];
                    $row[] = count($vals) === 1 && is_numeric($vals[0]) ? (float) $vals[0] : implode(' / ', $vals);
                }
                $rows[] = $row;
            }
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
                $fixedN = count(self::FIXED);
                $firstDate = $fixedN + 1;
                $jnCol = $firstDate + $this->dateCount;
                $lastCol = Coordinate::stringFromColumnIndex($jnCol + count($this->otherTypes));
                $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);

                // O'lchamlar
                foreach (['A' => 5, 'B' => 32, 'C' => 14, 'D' => 20, 'E' => 8, 'F' => 11, 'G' => 34] as $c => $w) {
                    $sheet->getColumnDimension($c)->setWidth($w);
                }
                for ($i = $firstDate; $i < $jnCol; $i++) {
                    $sheet->getColumnDimension($col($i))->setWidth(6.5);
                }
                $sheet->getColumnDimension($col($jnCol))->setWidth(7.5);
                for ($i = $jnCol + 1; $i <= $jnCol + count($this->otherTypes); $i++) {
                    $sheet->getColumnDimension($col($i))->setWidth(8);
                }

                // Sarlavhalar
                $sheet->mergeCells('A1:' . $lastCol . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(12)->getColor()->setRGB('1B3A63');
                $head = 'A2:' . $lastCol . '2';
                $sheet->getStyle($head)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle($head)->getAlignment()->setHorizontal('center')->setVertical('center')->setWrapText(true);
                $sheet->getRowDimension(2)->setRowHeight(30);
                // JN va boshqa turlar ustunlari — boshqa rang
                $sheet->getStyle($col($jnCol) . '2')->getFill()->getStartColor()->setRGB('166534');
                if ($this->otherTypes) {
                    $sheet->getStyle($col($jnCol + 1) . '2:' . $lastCol . '2')->getFill()->getStartColor()->setRGB('2B5EA7');
                }
                $sheet->freezePane($col($firstDate) . '3');

                if ($last > 2) {
                    $sheet->setAutoFilter('A2:' . $lastCol . $last);
                    $body = 'A3:' . $lastCol . $last;
                    $sheet->getStyle($body)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E1EC');
                    $sheet->getStyle('A3:A' . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle($col($firstDate) . '3:' . $lastCol . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle($col($jnCol) . '3:' . $col($jnCol) . $last)->getFont()->setBold(true);
                    $sheet->getStyle($col($jnCol) . '3:' . $col($jnCol) . $last)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F9F3');

                    // 90 dan past — qizil, NB — sariq (baholar ustunlari)
                    $grades = $col($firstDate) . '3:' . $lastCol . $last;
                    $low = new Conditional();
                    $low->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_BETWEEN)->addCondition('0.01')->addCondition('89.99');
                    $low->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B3261E');
                    $nb = new Conditional();
                    $nb->setConditionType(Conditional::CONDITION_CONTAINSTEXT)->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)->setText('NB');
                    $nb->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $sheet->getStyle($grades)->setConditionalStyles([$nb, $low]);
                }
            },
        ];
    }
}
