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
 * "Faollik va mustaqil ta'lim" hisobotining bitta varag'i. Qaysi ustunlar
 * chiqishi $kind bilan tanlanadi; qatorlar StudentActivityStatsData dan keladi.
 * Oxirida "Jami" qatori: son ustunlari yig'iladi, foiz qayta hisoblanadi.
 */
class StudentActivityStatsSheet implements FromArray, WithColumnWidths, WithHeadings, WithStyles, WithTitle
{
    public const LOGINS = 'logins';

    public const NOT_LOGGED = 'not_logged';

    public const INDEPENDENT = 'independent';

    public const MATERIALS = 'materials';

    /**
     * Ustunlar: kalit => [sarlavha, kenglik, turi].
     * turi: text | int | pct | dt (sana-vaqt) — Jami qatori va tekislash uchun.
     */
    private const COLUMNS = [
        self::LOGINS => [
            'department' => ['Fakultet', 34, 'text'],
            'group' => ['Guruh', 16, 'text'],
            'course' => ['Kurs', 9, 'text'],
            'education_type' => ["Ta'lim turi", 14, 'text'],
            'students' => ['Faol talabalar', 12, 'int'],
            'logged_in' => ['Kirganlar', 11, 'int'],
            'not_logged_in' => ['Kirmaganlar', 12, 'int'],
            'percent' => ['Kirganlar %', 11, 'pct'],
            'logins' => ['Kirishlar soni', 12, 'int'],
            'last_login' => ['Oxirgi kirish', 18, 'dt'],
        ],
        self::NOT_LOGGED => [
            'department' => ['Fakultet', 34, 'text'],
            'group' => ['Guruh', 16, 'text'],
            'course' => ['Kurs', 9, 'text'],
            'student' => ['Talaba', 40, 'text'],
            'student_id_number' => ['Talaba ID', 16, 'text'],
            'last_login' => ['Oxirgi kirish (umuman)', 20, 'dt'],
            'telegram' => ['Telegram ulangan', 14, 'text'],
        ],
        self::INDEPENDENT => [
            'department' => ['Kafedra', 36, 'text'],
            'subject' => ['Fan', 44, 'text'],
            'groups' => ['Guruhlar', 10, 'int'],
            'teachers' => ["O'qituvchilar", 12, 'int'],
            'students' => ['Yuklagan talabalar', 14, 'int'],
            'uploaded' => ['Yuklangan fayllar', 14, 'int'],
            'graded' => ['Baholangan', 12, 'int'],
            'ungraded' => ['Baholanmagan', 13, 'int'],
            'percent' => ['Baholangan %', 12, 'pct'],
            'last_upload' => ['Oxirgi yuklash', 18, 'dt'],
        ],
        self::MATERIALS => [
            'department' => ['Kafedra', 36, 'text'],
            'subject' => ['Fan', 44, 'text'],
            'groups' => ['Guruhlar', 10, 'int'],
            'teachers' => ["O'qituvchilar", 12, 'int'],
            'tasks' => ['Topshiriqlar', 12, 'int'],
            'with_file' => ['Fayl biriktirilgan', 15, 'int'],
            'without_file' => ['Faylsiz', 10, 'int'],
            'has_material' => ['Material', 10, 'text'],
        ],
    ];

    /** Jami qatorida foiz qaysi ikki ustundan hisoblanadi: [surat, maxraj] */
    private const PCT_OF = [
        self::LOGINS => ['logged_in', 'students'],
        self::INDEPENDENT => ['graded', 'uploaded'],
    ];

    private int $lastRow = 1;

    public function __construct(private string $title, private array $rows, private string $kind)
    {
    }

    public function title(): string
    {
        return $this->title;
    }

    public function headings(): array
    {
        return ['#', ...array_column(self::COLUMNS[$this->kind], 0)];
    }

    public function array(): array
    {
        $cols = self::COLUMNS[$this->kind];
        $out = [];

        foreach ($this->rows as $i => $row) {
            $line = [$i + 1];
            foreach ($cols as $key => [$h, $w, $type]) {
                $line[] = $this->cell($row[$key] ?? null, $type);
            }
            $out[] = $line;
        }

        if ($out === []) {
            $out[] = array_pad(["Bu oraliqda ma'lumot topilmadi."], count($cols) + 1, '');
            $this->lastRow = 2;

            return $out;
        }

        // Jami
        $totals = ['Jami'];
        foreach ($cols as $key => [$h, $w, $type]) {
            $totals[] = match ($type) {
                'int' => array_sum(array_map(fn ($r) => (int) ($r[$key] ?? 0), $this->rows)),
                'pct' => $this->totalPercent($key),
                default => '',
            };
        }
        $out[] = $totals;
        $this->lastRow = count($out) + 1;

        return $out;
    }

    private function cell(mixed $value, string $type): mixed
    {
        return match ($type) {
            'dt' => $value ? \Carbon\Carbon::parse($value)->format('d.m.Y H:i') : '',
            'pct' => $value === null ? '' : (float) $value,
            'int' => (int) $value,
            default => (string) ($value ?? ''),
        };
    }

    private function totalPercent(string $key): string|float
    {
        [$num, $den] = self::PCT_OF[$this->kind] ?? [null, null];
        if ($num === null) {
            return '';
        }
        $n = array_sum(array_map(fn ($r) => (int) ($r[$num] ?? 0), $this->rows));
        $d = array_sum(array_map(fn ($r) => (int) ($r[$den] ?? 0), $this->rows));

        return $d > 0 ? round($n * 100 / $d, 1) : 0;
    }

    public function columnWidths(): array
    {
        $widths = ['A' => 6];
        $col = 2;
        foreach (self::COLUMNS[$this->kind] as [$h, $w, $type]) {
            $widths[Coordinate::stringFromColumnIndex($col++)] = $w;
        }

        return $widths;
    }

    public function styles(Worksheet $sheet): array
    {
        $cols = self::COLUMNS[$this->kind];
        $end = Coordinate::stringFromColumnIndex(count($cols) + 1);
        $last = $this->lastRow;

        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

        $sheet->getStyle("A1:{$end}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(34);
        $sheet->freezePane('A2');

        if ($last < 2) {
            return [];
        }

        if ($this->rows === []) {
            $sheet->mergeCells("A2:{$end}2");

            return [];
        }

        $sheet->setAutoFilter("A1:{$end}".($last - 1));
        $sheet->getStyle("A2:{$end}{$last}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);

        // Son, foiz va sana ustunlari markazda; foiz "0.0" ko'rinishida
        $col = 2;
        foreach ($cols as $key => [$h, $w, $type]) {
            $letter = Coordinate::stringFromColumnIndex($col++);
            if ($type !== 'text') {
                $sheet->getStyle("{$letter}2:{$letter}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
            }
            if ($type === 'pct') {
                $sheet->getStyle("{$letter}2:{$letter}{$last}")->getNumberFormat()->setFormatCode('0.0');
            }
            // "Yo'q" / "Kirmaganlar" / "Baholanmagan" ustunlari qizg'ish
            if (in_array($key, ['not_logged_in', 'ungraded', 'without_file'], true)) {
                $sheet->getStyle("{$letter}2:{$letter}".($last - 1))->getFont()->getColor()->setRGB('B3261E');
            }
        }

        // Jami qatori
        $sheet->getStyle("A{$last}:{$end}{$last}")->applyFromArray([
            'font' => ['bold' => true],
            'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'E8EEF7']],
        ]);
        $sheet->getStyle("A{$last}:{$end}{$last}")->getBorders()->getTop()
            ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1A3268');

        return [];
    }
}
