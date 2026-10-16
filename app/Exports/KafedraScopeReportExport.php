<?php

namespace App\Exports;

use App\Services\KafedraSubjectReport;
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
 * Fanlar bo'yicha hisobot — tanlangan "fakultet / yo'nalish · kurs": har fan alohida varaqda,
 * varaq ichida guruhlar ketma-ket bloklarda (guruh sarlavhasi, talaba × sana, JN, MT/ON/OSKI/Test).
 *
 * @param list<array{unit: object, groups: \Illuminate\Support\Collection}> $items
 */
class KafedraScopeReportExport implements WithMultipleSheets
{
    public function __construct(private array $items, private array $grades, private string $scopeLabel, private ?string $from, private ?string $to) {}

    public function sheets(): array
    {
        $sheets = [];
        $used = [];
        foreach ($this->items as $item) {
            $title = mb_substr(trim((string) preg_replace('/[\[\]:*?\/\\\\]/u', ' ', $item['unit']->subject_name)), 0, 28) ?: 'Fan';
            $base = $title;
            for ($i = 2; isset($used[mb_strtolower($title)]); $i++) {
                $title = mb_substr($base, 0, 25) . ' ' . $i;
            }
            $used[mb_strtolower($title)] = true;
            $sheets[] = new KafedraScopeSubjectSheet($title, $item['unit'], $item['groups'], $this->grades, $this->scopeLabel, $this->from, $this->to);
        }

        return $sheets ?: [new KafedraScopeSubjectSheet("Ma'lumot yo'q", (object) ['subject_name' => '', 'subject_id' => 0], collect(), [], $this->scopeLabel, $this->from, $this->to)];
    }
}

class KafedraScopeSubjectSheet implements FromArray, WithEvents, WithTitle, WithStrictNullComparison
{
    private const TYPES = ['MT', 'ON', 'OSKI', 'Test'];

    /** Bloklar: [title_row, head_row, first_student_row, last_student_row, width, date_count]. */
    private array $blocks = [];
    private int $maxWidth = 1;
    private int $maxDates = 0;

    public function __construct(private string $title, private object $unit, private $groups, private array $grades,
        private string $scopeLabel, private ?string $from, private ?string $to) {}

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        $sub = $this->unit->subject_id;
        $period = $this->from || $this->to
            ? ($this->from ? date('d.m.Y', strtotime($this->from)) : '…') . ' – ' . ($this->to ? date('d.m.Y', strtotime($this->to)) : '…')
            : 'joriy semestr';
        $rows = [[$this->unit->subject_name . ' · ' . $this->scopeLabel . ' · ' . $period], ['']];

        foreach ($this->groups as $g) {
            $ids = $g->students->pluck('hemis_id')->map(fn ($v) => (int) $v)->all();
            $dates = [];
            foreach ($ids as $id) {
                foreach (array_keys($this->grades[$id][$sub]['days'] ?? []) as $d) {
                    $dates[$d] = true;
                }
            }
            ksort($dates);
            $dates = array_keys($dates);
            $width = 3 + count($dates) + 1 + count(self::TYPES);
            $this->maxWidth = max($this->maxWidth, $width);
            $this->maxDates = max($this->maxDates, count($dates));

            $stats = KafedraSubjectReport::stats(array_map(fn ($id) => $this->grades[$id][$sub]['jn'] ?? null, $ids));
            $teacher = KafedraSubjectReport::teachers($this->grades, $sub, $ids);
            $titleRow = count($rows) + 1;
            $rows[] = ['Guruh: ' . $g->name . ($g->lang ? ' (' . $g->lang . ')' : '') . ($teacher ? ' · O\'qituvchi: ' . $teacher : '')
                . ' · Talabalar: ' . count($ids) . ' · O\'rtacha JN: ' . ($stats['avg'] ?? '—')];
            $rows[] = array_merge(['№', 'F.I.Sh.', 'ID raqam'], array_map(fn ($d) => date('d.m', strtotime($d)), $dates), ['JN'], self::TYPES);
            $first = count($rows) + 1;
            foreach ($g->students->values() as $i => $s) {
                $sg = $this->grades[(int) $s->hemis_id][$sub] ?? [];
                $row = [$i + 1, $s->full_name, $s->student_id_number];
                foreach ($dates as $d) {
                    $row[] = $sg['days'][$d] ?? '';
                }
                $row[] = $sg['jn'] ?? '';
                foreach (self::TYPES as $t) {
                    $vals = $sg['other'][$t] ?? [];
                    $row[] = count($vals) === 1 && is_numeric($vals[0]) ? (float) $vals[0] : implode(' / ', $vals);
                }
                $rows[] = $row;
            }
            $this->blocks[] = [$titleRow, $titleRow + 1, $first, count($rows), $width, count($dates)];
            $rows[] = [''];
        }
        if (!$this->blocks) {
            $rows[] = ["Bu fanni o'qiyotgan guruh topilmadi."];
        }

        return $rows;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $col = fn (int $i) => Coordinate::stringFromColumnIndex($i);
                $sheet->getColumnDimension('A')->setWidth(5);
                $sheet->getColumnDimension('B')->setWidth(34);
                $sheet->getColumnDimension('C')->setWidth(15);
                for ($i = 4; $i <= $this->maxWidth; $i++) {
                    $sheet->getColumnDimension($col($i))->setWidth(7);
                }
                $sheet->mergeCells('A1:' . $col(max(6, $this->maxWidth)) . '1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13)->getColor()->setRGB('1B3A63');
                $sheet->freezePane('D3');

                foreach ($this->blocks as [$t, $h, $first, $last, $width, $dateCount]) {
                    $lastCol = $col($width);
                    $jnCol = 4 + $dateCount;
                    // Guruh sarlavhasi
                    $sheet->mergeCells('A' . $t . ':' . $lastCol . $t);
                    $sheet->getStyle('A' . $t)->getFont()->setBold(true)->setSize(11)->getColor()->setRGB('1B3A63');
                    $sheet->getStyle('A' . $t . ':' . $lastCol . $t)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DCE8FB');
                    $sheet->getRowDimension($t)->setRowHeight(20);
                    // Ustun sarlavhalari
                    $head = 'A' . $h . ':' . $lastCol . $h;
                    $sheet->getStyle($head)->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                    $sheet->getStyle($head)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                    $sheet->getStyle($head)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle($col($jnCol) . $h)->getFill()->getStartColor()->setRGB('166534');
                    $sheet->getStyle($col($jnCol + 1) . $h . ':' . $lastCol . $h)->getFill()->getStartColor()->setRGB('2B5EA7');
                    if ($last < $first) {
                        continue;
                    }
                    $body = 'A' . $first . ':' . $lastCol . $last;
                    $sheet->getStyle('A' . $h . ':' . $lastCol . $last)->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('D9E1EC');
                    $sheet->getStyle('A' . $first . ':A' . $last)->getAlignment()->setHorizontal('center');
                    $sheet->getStyle('D' . $first . ':' . $lastCol . $last)->getAlignment()->setHorizontal('center');
                    $jnRange = $col($jnCol) . $first . ':' . $col($jnCol) . $last;
                    $sheet->getStyle($jnRange)->getFont()->setBold(true);
                    $sheet->getStyle($jnRange)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('F0F9F3');
                    $low = new Conditional();
                    $low->setConditionType(Conditional::CONDITION_CELLIS)->setOperatorType(Conditional::OPERATOR_BETWEEN)->addCondition('0.01')->addCondition('59.99');
                    $low->getStyle()->getFont()->setBold(true)->getColor()->setRGB('B3261E');
                    $nb = new Conditional();
                    $nb->setConditionType(Conditional::CONDITION_CONTAINSTEXT)->setOperatorType(Conditional::OPERATOR_CONTAINSTEXT)->setText('NB');
                    $nb->getStyle()->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF3C7');
                    $sheet->getStyle('D' . $first . ':' . $lastCol . $last)->setConditionalStyles([$nb, $low]);
                }
            },
        ];
    }
}
