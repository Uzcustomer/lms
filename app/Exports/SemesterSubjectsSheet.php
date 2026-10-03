<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/**
 * Bitta yo'nalishning semestr fanlari — varaq. Kurslar "1-KURS", "2-KURS"
 * kabi bo'lim qatorlari bilan ajratiladi, tartib raqami butun varaq bo'ylab
 * davom etadi. Qator rangi nazorat turiga qarab.
 */
class SemesterSubjectsSheet implements FromArray, WithHeadings, WithStyles, WithTitle, WithColumnWidths
{
    /** Nazorat turi: [yorliq, qator rangi] */
    private const CLOSING_FORMS = [
        'test' => ['TEST', 'FFF2CC'],
        'sinov' => ['Sinov(Test)', 'FCE4D6'],
        'oski' => ['OSKE', 'DDEBF7'],
        'oski_test' => ['OSKE+TEST', 'E2EFDA'],
        'normativ' => ['Normativ', 'EDEDED'],
        'none' => ["Yo'q", 'FFFFFF'],
    ];

    /** Mashg'ulot turlari ustunlarining tartibi (nomidagi kalit so'z bo'yicha) */
    private const TYPE_ORDER = ['maruza', 'amaliy', 'laboratoriya', 'klinik', 'seminar', 'mustaqil'];

    private const FIXED_HEADINGS = ['#', 'Fan nomi', 'Kurs', 'Nazorat turi', 'Soat'];

    /** @var array<string, string> type code => name (tartiblangan) */
    private array $types = [];

    /** @var array<int, array{kind: string, form?: string|null}> Excel qatori (2 dan) => meta */
    private array $rowMeta = [];

    private ?array $built = null;

    public function __construct(private string $title, private array $rows)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function array(): array
    {
        return $this->build();
    }

    public function headings(): array
    {
        $this->build();

        return array_merge(self::FIXED_HEADINGS, array_values($this->types));
    }

    private function build(): array
    {
        if ($this->built !== null) {
            return $this->built;
        }

        // Shu varaqda uchraydigan mashg'ulot turlari
        $loads = [];
        $types = [];
        foreach ($this->rows as $i => $row) {
            $details = is_string($row->subject_details) ? json_decode($row->subject_details, true) : $row->subject_details;
            $loads[$i] = [];
            if (is_array($details)) {
                foreach ($details as $d) {
                    $code = (string) ($d['trainingType']['code'] ?? '');
                    $name = (string) ($d['trainingType']['name'] ?? '');
                    if ($code === '' || $name === '') {
                        continue;
                    }
                    $types[$code] = $name;
                    $loads[$i][$code] = (int) ($d['academic_load'] ?? 0);
                }
            }
        }
        $this->types = $this->sortTypes($types);
        $width = count(self::FIXED_HEADINGS) + count($this->types);

        $out = [];
        $excelRow = 2;          // 1 — sarlavha
        $num = 0;
        $currentLevel = null;
        foreach ($this->rows as $i => $row) {
            $level = (string) $row->level_code;
            if ($level !== $currentLevel) {
                // "1-KURS" bo'lim qatori
                $label = mb_strtoupper($row->level_name ?: (((int) $level - 10) . '-kurs'));
                $out[] = array_pad([$label], $width, '');
                $this->rowMeta[$excelRow++] = ['kind' => 'course'];
                $currentLevel = $level;
            }

            [$formLabel] = self::CLOSING_FORMS[$row->closing_form] ?? [$row->closing_form ? (string) $row->closing_form : '', null];
            $courseNumber = is_numeric($level) ? (int) $level - 10 : $row->level_name;

            $cells = [
                ++$num,
                $row->subject_name ?? '-',
                $courseNumber,
                $formLabel,
                $row->total_acload ?: '',
            ];
            foreach (array_keys($this->types) as $code) {
                $hours = $loads[$i][$code] ?? 0;
                $cells[] = $hours > 0 ? $hours : '';
            }
            $out[] = $cells;
            $this->rowMeta[$excelRow++] = ['kind' => 'subject', 'form' => $row->closing_form];
        }

        return $this->built = $out;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 52, 'C' => 7, 'D' => 14, 'E' => 8];
    }

    public function styles(Worksheet $sheet): array
    {
        $this->build();
        $lastCol = count(self::FIXED_HEADINGS) + count($this->types);
        $end = Coordinate::stringFromColumnIndex($lastCol);

        // Sarlavha
        $sheet->getStyle("A1:{$end}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->freezePane('A2');

        // Mashg'ulot turi ustunlari tor va o'ralgan sarlavha bilan
        for ($col = count(self::FIXED_HEADINGS) + 1; $col <= $lastCol; $col++) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($col))->setWidth(11);
        }

        $lastRow = 1;
        foreach ($this->rowMeta as $excelRow => $meta) {
            $lastRow = $excelRow;
            if ($meta['kind'] === 'course') {
                $sheet->mergeCells("A{$excelRow}:{$end}{$excelRow}");
                $sheet->getStyle("A{$excelRow}:{$end}{$excelRow}")->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['rgb' => '1A3268']],
                    'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'D9E1F2']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                continue;
            }

            $color = self::CLOSING_FORMS[$meta['form']][1] ?? null;
            if ($color && $color !== 'FFFFFF') {
                $sheet->getStyle("A{$excelRow}:{$end}{$excelRow}")->getFill()
                    ->setFillType('solid')->getStartColor()->setRGB($color);
            }
            $sheet->getStyle("A{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("C{$excelRow}:{$end}{$excelRow}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            $sheet->getStyle("B{$excelRow}")->getAlignment()->setWrapText(true);
        }

        if ($lastRow > 1) {
            $sheet->getStyle("A1:{$end}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('9CA3AF');
        }

        return [];
    }

    /** Ma'ruza, amaliy, laboratoriya… tartibida; qolganlari oxirida */
    private function sortTypes(array $types): array
    {
        $normalize = fn ($str) => preg_replace('/[^a-z\x{0400}-\x{04FF}]/u', '', mb_strtolower($str));
        $position = function (string $name) use ($normalize): int {
            $n = $normalize($name);
            foreach (self::TYPE_ORDER as $i => $keyword) {
                if (str_contains($n, $keyword)) {
                    return $i;
                }
            }

            return count(self::TYPE_ORDER);
        };

        uasort($types, fn ($a, $b) => $position($a) <=> $position($b) ?: strcmp($a, $b));

        return $types;
    }
}
