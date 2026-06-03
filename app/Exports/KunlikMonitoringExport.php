<?php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Kunlik monitoring Excel eksporti — uchta sahifa:
 *   1) Kunlik xulosa (asosiy jadval)
 *   2) Sync gap — Moodle'da bor, LMS'da yo'q attempt_id lar
 *   3) Mark gap — LMS'da bor, mark'da yo'q yozuvlar (talaba/fan bilan)
 */
class KunlikMonitoringExport implements WithMultipleSheets
{
    public function __construct(
        private array $days,
        private array $missingSync,
        private array $missingMark,
        private string $dateFrom,
        private string $dateTo,
    ) {}

    public function sheets(): array
    {
        return [
            new KunlikMonitoringSummarySheet($this->days, $this->dateFrom, $this->dateTo),
            new KunlikMonitoringSyncGapSheet($this->missingSync),
            new KunlikMonitoringMarkGapSheet($this->missingMark),
        ];
    }
}

class KunlikMonitoringSummarySheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(
        private array $days,
        private string $dateFrom,
        private string $dateTo,
    ) {}

    public function title(): string
    {
        return 'Kunlik xulosa';
    }

    public function headings(): array
    {
        return ['Sana', 'Moodle', 'LMS sync', 'Markda', 'Sync farq', 'Mark farq', 'Status'];
    }

    public function array(): array
    {
        $rows = [];
        $totMoodle = 0;
        $totSynced = 0;
        $totGraded = 0;
        $totSyncGap = 0;
        $totMarkGap = 0;

        foreach ($this->days as $d) {
            $status = match ($d['status'] ?? 'ok') {
                'sync_gap' => 'Sync gap',
                'mark_gap' => 'Mark gap',
                default    => 'OK',
            };
            $rows[] = [
                $d['date'],
                (int) $d['moodle_count'],
                (int) $d['synced_count'],
                (int) $d['graded_count'],
                (int) $d['sync_gap'],
                (int) $d['mark_gap'],
                $status,
            ];
            $totMoodle += (int) $d['moodle_count'];
            $totSynced += (int) $d['synced_count'];
            $totGraded += (int) $d['graded_count'];
            $totSyncGap += (int) $d['sync_gap'];
            $totMarkGap += (int) $d['mark_gap'];
        }

        $rows[] = ['JAMI (' . $this->dateFrom . ' — ' . $this->dateTo . ')', $totMoodle, $totSynced, $totGraded, $totSyncGap, $totMarkGap, ''];

        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        $lastRow = $sheet->getHighestRow();
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF16A34A']],
            ],
            $lastRow => [
                'font' => ['bold' => true],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFEF3C7']],
            ],
        ];
    }
}

class KunlikMonitoringSyncGapSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private array $missingSync) {}

    public function title(): string
    {
        return 'Sync gap';
    }

    public function headings(): array
    {
        return ['Sana', 'attempt_id', 'Izoh'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->missingSync as $date => $ids) {
            foreach ($ids as $id) {
                $rows[] = [$date, (int) $id, "Moodle'da bor, LMS sync yo'q"];
            }
        }
        if (empty($rows)) {
            $rows[] = ['', '', "Sync bosqichida yo'qotish yo'q ✓"];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFDC2626']],
            ],
        ];
    }
}

class KunlikMonitoringMarkGapSheet implements FromArray, WithHeadings, WithStyles, WithTitle, ShouldAutoSize
{
    public function __construct(private array $missingMark) {}

    public function title(): string
    {
        return 'Mark gap';
    }

    public function headings(): array
    {
        return ['Sana', 'attempt_id', 'HEMIS ID', "F.I.Sh.", 'Fan', 'Quiz turi', 'Quiz to\'liq nomi', 'Tugatildi', 'Baho'];
    }

    public function array(): array
    {
        $rows = [];
        foreach ($this->missingMark as $date => $items) {
            foreach ($items as $r) {
                $rows[] = [
                    $date,
                    (int) ($r['attempt_id'] ?? 0),
                    $r['student_id'] ?? '',
                    $r['student_name'] ?? '',
                    $r['fan_name'] ?? '',
                    $r['quiz_type'] ?? '',
                    $r['attempt_name'] ?? '',
                    $r['date_finish'] ?? '',
                    $r['grade'] ?? '',
                ];
            }
        }
        if (empty($rows)) {
            $rows[] = ['', '', '', '', '', '', '', '', "Mark bosqichida yo'qotish yo'q ✓"];
        }
        return $rows;
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => [
                'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF']],
                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD97706']],
            ],
        ];
    }
}
