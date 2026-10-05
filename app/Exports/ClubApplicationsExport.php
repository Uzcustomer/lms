<?php

namespace App\Exports;

use App\Models\Department;
use App\Models\Teacher;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\Exportable;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "To'garak arizalari" — ikki varaq:
 *   1. To'garaklar  — har to'garak bir qator: kafedra, mas'ul (kafedra mudiri),
 *                     kun/vaqt, joy, jami ariza, biriktirilgan, kutilmoqda, rad.
 *   2. Arizalar     — har ariza bir qator: talaba, guruh, to'garak, kafedra,
 *                     mas'ul, holat, ariza sanasi, rad sababi.
 *
 * Mas'ul shaxs sahifadagi bilan bir xil usulda topiladi: arizadagi
 * department_hemis_id (bo'lmasa kafedra nomi) bo'yicha kafedra mudiri roli
 * bor xodim. Qaysi arizalar kirishi chaqiruvchi tomonidan beriladi — rol
 * cheklovi (kafedra mudiri faqat o'z kafedrasi) kontrollerda.
 */
class ClubApplicationsExport implements WithMultipleSheets
{
    use Exportable;

    public const STATUS_LABELS = [
        'pending' => 'Kutilmoqda',
        'approved' => 'Biriktirilgan',
        'rejected' => 'Rad etilgan',
    ];

    public function __construct(private Collection $applications)
    {
    }

    public function sheets(): array
    {
        $responsible = $this->responsibleByClub();

        return [
            new ClubApplicationsClubsSheet($this->applications, $responsible),
            new ClubApplicationsRowsSheet($this->applications, $responsible),
        ];
    }

    /**
     * To'garak nomi => mas'ul (kafedra mudiri) ismi. Sahifadagi mantiq bilan
     * bir xil, lekin bitta so'rovda: avval kafedra id'lar yig'iladi, so'ng
     * mudirlar bir marta olinadi.
     *
     * @return array<string, string>
     */
    private function responsibleByClub(): array
    {
        $deptByClub = [];
        foreach ($this->applications->groupBy('club_name') as $club => $apps) {
            $first = $apps->first();
            $deptId = $first->department_hemis_id;
            if (! $deptId) {
                $kafedra = (string) ($first->kafedra_name ?? '');
                if (preg_match('/^(.+?kafedrasi)\b/iu', $kafedra, $m)) {
                    $kafedra = $m[1];
                }
                if ($kafedra !== '') {
                    $deptId = Department::whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($kafedra).'%'])->value('department_hemis_id');
                }
            }
            $deptByClub[$club] = $deptId ? (string) $deptId : null;
        }

        $deptIds = array_values(array_unique(array_filter($deptByClub)));
        $headByDept = [];
        if ($deptIds !== []) {
            Teacher::query()
                ->whereIn('department_hemis_id', $deptIds)
                ->whereHas('roles', fn ($q) => $q->where('name', 'kafedra_mudiri'))
                ->get(['department_hemis_id', 'full_name'])
                ->each(function ($t) use (&$headByDept) {
                    $headByDept[(string) $t->department_hemis_id] ??= (string) $t->full_name;
                });
        }

        $out = [];
        foreach ($deptByClub as $club => $deptId) {
            $out[$club] = $deptId !== null ? ($headByDept[$deptId] ?? '') : '';
        }

        return $out;
    }
}

/** 1-varaq: to'garaklar kesimida yig'ma */
class ClubApplicationsClubsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $lastRow = 1;

    public function __construct(private Collection $applications, private array $responsible)
    {
    }

    public function title(): string
    {
        return "To'garaklar";
    }

    public function array(): array
    {
        $rows = [['#', "To'garak", 'Kafedra', "Mas'ul shaxs", 'Kun / vaqt', 'Joy', 'Jami ariza', 'Biriktirilgan', 'Kutilmoqda', 'Rad etilgan']];

        $i = 0;
        foreach ($this->applications->groupBy('club_name')->sortKeys() as $club => $apps) {
            $first = $apps->first();
            $rows[] = [
                ++$i,
                (string) $club,
                (string) ($first->kafedra_name ?? ''),
                $this->responsible[$club] ?? '',
                trim((string) ($first->club_day ?? '').' '.(string) ($first->club_time ?? '')),
                (string) ($first->club_place ?? ''),
                $apps->count(),
                $apps->where('status', 'approved')->count(),
                $apps->where('status', 'pending')->count(),
                $apps->where('status', 'rejected')->count(),
            ];
        }

        if ($i === 0) {
            $rows[] = array_pad(['Ariza topilmadi.'], 10, '');
        } else {
            $rows[] = ['', 'Jami', '', '', '', '',
                $this->applications->count(),
                $this->applications->where('status', 'approved')->count(),
                $this->applications->where('status', 'pending')->count(),
                $this->applications->where('status', 'rejected')->count(),
            ];
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 36, 'C' => 40, 'D' => 36, 'E' => 18, 'F' => 24, 'G' => 11, 'H' => 13, 'I' => 12, 'J' => 12];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            ClubApplicationsStyle::apply($e->sheet->getDelegate(), 'J', $this->lastRow, $this->applications->isNotEmpty(), 'G', [
                'H' => '0F7A52', 'I' => 'B45309', 'J' => 'B3261E',
            ]);
        }];
    }
}

/** 2-varaq: barcha arizalar */
class ClubApplicationsRowsSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    private int $lastRow = 1;

    public function __construct(private Collection $applications, private array $responsible)
    {
    }

    public function title(): string
    {
        return 'Arizalar';
    }

    public function array(): array
    {
        $rows = [['#', 'Talaba', 'Talaba HEMIS ID', 'Guruh', "To'garak", 'Kafedra', "Mas'ul shaxs", 'Holati', 'Ariza sanasi', 'Rad sababi']];

        $sorted = $this->applications->sortBy([
            ['club_name', 'asc'],
            ['status', 'asc'],
            ['student_name', 'asc'],
        ])->values();

        foreach ($sorted as $i => $a) {
            $rows[] = [
                $i + 1,
                (string) $a->student_name,
                (string) $a->student_hemis_id,
                (string) ($a->group_name ?? ''),
                (string) $a->club_name,
                (string) ($a->kafedra_name ?? ''),
                $this->responsible[$a->club_name] ?? '',
                ClubApplicationsExport::STATUS_LABELS[$a->status] ?? (string) $a->status,
                $a->created_at ? $a->created_at->format('d.m.Y H:i') : '',
                (string) ($a->reject_reason ?? ''),
            ];
        }

        if ($sorted->isEmpty()) {
            $rows[] = array_pad(['Ariza topilmadi.'], 10, '');
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 38, 'C' => 15, 'D' => 14, 'E' => 34, 'F' => 40, 'G' => 36, 'H' => 14, 'I' => 17, 'J' => 40];
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $e) {
            $sheet = $e->sheet->getDelegate();
            ClubApplicationsStyle::apply($sheet, 'J', $this->lastRow, $this->applications->isNotEmpty(), null, [], false);

            // Holat rangi
            $colors = ['Biriktirilgan' => '0F7A52', 'Kutilmoqda' => 'B45309', 'Rad etilgan' => 'B3261E'];
            for ($r = 2; $r <= $this->lastRow; $r++) {
                $c = $colors[(string) $sheet->getCell("H{$r}")->getValue()] ?? null;
                if ($c) {
                    $sheet->getStyle("H{$r}")->getFont()->setBold(true)->getColor()->setRGB($c);
                }
            }
        }];
    }
}

/** Ikkala varaq uchun bir xil bezak */
final class ClubApplicationsStyle
{
    /**
     * @param  string|null  $boldCol  qalin son ustuni (yig'ma varaqda "Jami ariza")
     * @param  array<string,string>  $colorCols  ustun => rang (son ustunlari)
     * @param  bool  $hasTotals  oxirgi qator "Jami" bo'lsa — bezaladi va filtrdan chiqariladi
     */
    public static function apply($sheet, string $end, int $last, bool $hasRows, ?string $boldCol, array $colorCols, bool $hasTotals = true): void
    {
        $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);

        $sheet->getStyle("A1:{$end}1")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 10],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1A3268']],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
        ]);
        $sheet->getRowDimension(1)->setRowHeight(30);
        $sheet->freezePane('A2');

        if (! $hasRows) {
            $sheet->mergeCells("A2:{$end}2");

            return;
        }

        $dataLast = $hasTotals ? $last - 1 : $last;
        $sheet->setAutoFilter("A1:{$end}{$dataLast}");
        $sheet->getStyle("A2:{$end}{$last}")->getBorders()->getBottom()
            ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
        $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle("A2:{$end}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
        $sheet->getStyle("B2:B{$dataLast}")->getFont()->setBold(true);

        if ($boldCol) {
            $sheet->getStyle("{$boldCol}2:{$boldCol}{$last}")->getFont()->setBold(true);
        }
        foreach ($colorCols as $col => $rgb) {
            $sheet->getStyle("{$col}2:{$col}{$last}")->getFont()->getColor()->setRGB($rgb);
        }
        if ($boldCol || $colorCols) {
            $first = $boldCol ?? array_key_first($colorCols);
            $sheet->getStyle("{$first}2:{$end}{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        }

        if ($hasTotals) {
            $sheet->getStyle("A{$last}:{$end}{$last}")->applyFromArray([
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'E8EEF7']],
            ]);
            $sheet->getStyle("A{$last}:{$end}{$last}")->getBorders()->getTop()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1A3268');
        }
    }
}
