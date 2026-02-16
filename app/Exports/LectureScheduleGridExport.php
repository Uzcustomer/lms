<?php

namespace App\Exports;

use App\Models\LectureSchedule;
use App\Models\LectureScheduleBatch;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

class LectureScheduleGridExport implements WithEvents
{
    private LectureScheduleBatch $batch;
    private ?int $week;

    public function __construct(LectureScheduleBatch $batch, ?int $week = null)
    {
        $this->batch = $batch;
        $this->week = $week;
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $items = $this->getFilteredItems();

                $days = LectureSchedule::WEEK_DAYS;
                $pairTimes = LectureSchedule::PAIR_TIMES;

                // Juftliklar ro'yxati
                $pairCodes = $items->pluck('lesson_pair_code')->unique()->sort()->values()->toArray();
                if (empty($pairCodes)) {
                    $pairCodes = array_keys($pairTimes);
                }

                // Grid ma'lumotlarini tayyorlash
                $grid = [];
                $groupSourceSeen = [];
                foreach ($items as $item) {
                    $key = $item->week_day . '_' . $item->lesson_pair_code;
                    if ($item->group_source) {
                        $gsKey = $key . '|' . $item->group_source . '|' . ($item->auditorium_name ?? '');
                        if (isset($groupSourceSeen[$gsKey])) continue;
                        $groupSourceSeen[$gsKey] = true;
                    }
                    $grid[$key][] = $item;
                }

                // Sarlavha: Hafta info
                $weekLabel = $this->week ? $this->week . '-hafta' : 'Barcha haftalar';
                $sheet->setCellValue('A1', 'Dars jadvali — ' . $this->batch->file_name . ' (' . $weekLabel . ')');
                $sheet->mergeCells('A1:' . $this->colLetter(count($days)) . '1');
                $sheet->getStyle('A1')->applyFromArray([
                    'font' => ['bold' => true, 'size' => 14, 'color' => ['argb' => 'FF1A3268']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER],
                ]);
                $sheet->getRowDimension(1)->setRowHeight(30);

                // Ustun sarlavhalari: A = Juftlik, B..G = Kunlar
                $row = 3;
                $sheet->setCellValue('A' . $row, 'Juftlik');
                $col = 1;
                foreach ($days as $dayNum => $dayName) {
                    $sheet->setCellValue($this->colLetter($col) . $row, $dayName);
                    $col++;
                }

                // Header stilini berish
                $headerRange = 'A' . $row . ':' . $this->colLetter(count($days)) . $row;
                $sheet->getStyle($headerRange)->applyFromArray([
                    'font' => ['bold' => true, 'color' => ['argb' => 'FFFFFFFF'], 'size' => 11],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FF2B5EA7']],
                    'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER],
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF8DB4E2']]],
                ]);
                $sheet->getRowDimension($row)->setRowHeight(28);

                // Ustunlar kengligini belgilash
                $sheet->getColumnDimension('A')->setWidth(16);
                for ($i = 1; $i <= count($days); $i++) {
                    $sheet->getColumnDimension($this->colLetter($i))->setWidth(28);
                }

                // Grid qatorlari
                $row = 4;
                foreach ($pairCodes as $pairCode) {
                    $pairTime = $pairTimes[(int) $pairCode] ?? null;
                    $timeStr = $pairCode . '-juftlik';
                    if ($pairTime) {
                        $timeStr .= "\n" . $pairTime['start'] . ' - ' . $pairTime['end'];
                    }
                    $sheet->setCellValue('A' . $row, $timeStr);
                    $sheet->getStyle('A' . $row)->applyFromArray([
                        'font' => ['bold' => true, 'size' => 9],
                        'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
                        'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF0F4FF']],
                    ]);

                    // Har bir kun uchun
                    $col = 1;
                    foreach ($days as $dayNum => $dayName) {
                        $cellKey = $dayNum . '_' . $pairCode;
                        $cards = $grid[$cellKey] ?? [];
                        $cellText = '';
                        foreach ($cards as $card) {
                            if ($cellText) $cellText .= "\n---\n";
                            $cellText .= $card->subject_name;
                            if ($card->employee_name) $cellText .= "\n" . $card->employee_name;
                            $meta = array_filter([$card->building_name, $card->auditorium_name]);
                            if ($meta) $cellText .= "\n" . implode(', ', $meta);
                            $cellText .= "\n" . ($card->group_source ?: $card->group_name);
                            if ($card->training_type_name) $cellText .= ' | ' . $card->training_type_name;
                            // Hafta paritet label
                            if ($card->week_parity) {
                                $pLabel = $card->week_parity === 'juft' ? 'J' : ($card->week_parity === 'toq' ? 'T' : $card->week_parity);
                                $cellText .= ' (' . $pLabel . ')';
                            }
                        }

                        $cellRef = $this->colLetter($col) . $row;
                        $sheet->setCellValue($cellRef, $cellText);
                        $sheet->getStyle($cellRef)->applyFromArray([
                            'alignment' => ['vertical' => Alignment::VERTICAL_TOP, 'wrapText' => true],
                            'font' => ['size' => 8],
                        ]);

                        // Konfliktli yacheykani ranglash
                        $hasConflict = collect($cards)->contains(fn($c) => $c->has_conflict);
                        if ($hasConflict) {
                            $sheet->getStyle($cellRef)->applyFromArray([
                                'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFFFF0F0']],
                                'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFEF4444']]],
                            ]);
                        }

                        $col++;
                    }

                    $sheet->getRowDimension($row)->setRowHeight(count($pairCodes) > 5 ? 65 : 80);
                    $row++;
                }

                // Border
                $dataRange = 'A3:' . $this->colLetter(count($days)) . ($row - 1);
                $sheet->getStyle($dataRange)->applyFromArray([
                    'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FFD1D5DB']]],
                ]);

                // Alternating row colors
                for ($r = 4; $r < $row; $r++) {
                    if ($r % 2 === 0) {
                        $sheet->getStyle('A' . $r . ':' . $this->colLetter(count($days)) . $r)->applyFromArray([
                            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFF8FAFC']],
                        ]);
                    }
                }

                // Sheet title
                $sheet->setTitle('Jadval');
            },
        ];
    }

    private function getFilteredItems()
    {
        $items = $this->batch->items()
            ->orderBy('week_day')
            ->orderBy('lesson_pair_code')
            ->get();

        if ($this->week) {
            $week = $this->week;
            $items = $items->filter(function ($item) use ($week) {
                $parity = mb_strtolower(trim($item->week_parity ?? ''));
                if ($parity === 'juft' && $week % 2 !== 0) return false;
                if ($parity === 'toq' && $week % 2 !== 1) return false;
                if (empty($item->weeks)) return true;
                return $this->weekInRange($week, $item->weeks, $parity);
            });
        }

        return $items;
    }

    private function weekInRange(int $week, string $weeksStr, string $parity = ''): bool
    {
        $weeksStr = trim($weeksStr);
        if (preg_match('/^(\d+)\s*-\s*(\d+)$/', $weeksStr, $m)) {
            return $week >= (int) $m[1] && $week <= (int) $m[2];
        }
        if (str_contains($weeksStr, ',')) {
            $weekList = array_map('intval', array_map('trim', explode(',', $weeksStr)));
            return in_array($week, $weekList);
        }
        if (is_numeric($weeksStr)) {
            $lessonCount = (int) $weeksStr;
            if ($parity === 'juft') {
                $maxWeek = $lessonCount * 2;
            } elseif ($parity === 'toq') {
                $maxWeek = $lessonCount * 2 - 1;
            } else {
                $maxWeek = $lessonCount;
            }
            return $week >= 1 && $week <= $maxWeek;
        }
        return true;
    }

    private function colLetter(int $index): string
    {
        // 0 = A, 1 = B, etc.
        return chr(65 + $index);
    }
}
