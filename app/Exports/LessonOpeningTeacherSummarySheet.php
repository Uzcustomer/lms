<?php

namespace App\Exports;

use App\Services\LessonOpeningTeacherReport;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * "O'qituvchilar" varag'i: har bir o'qituvchi bir qator. Uchinchi ustundan
 * keyingi sonlar — baho qo'yilmagan kunlar soni va ular arizaning holati
 * bo'yicha bo'linishi; "jami" shularning yig'indisi.
 */
class LessonOpeningTeacherSummarySheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Sarlavha qatori (undan yuqorida sarlavha, davr va izoh) */
    private const HEADER_ROW = 4;

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return "O'qituvchilar";
    }

    public function array(): array
    {
        $pad = fn (array $cells): array => array_pad($cells, 9, '');
        $labels = LessonOpeningTeacherReport::LABELS;

        $rows = [
            $pad(["Dars ochish arizalari — o'qituvchilar kesimida"]),
            $pad([
                'Davr: '.$this->report['from']->format('d.m.Y').' — '.$this->report['to']->format('d.m.Y')
                .' · Tayyorlangan: '.now('Asia/Tashkent')->format('d.m.Y H:i'),
            ]),
            $pad([
                "Baho qo'yilmagan holat — o'tgan amaliy dars kunida kamida bitta juftlikda na baho, na NB qo'yilmagan "
                ."(jurnaldagi qoida). Ariza yuborilgan kunlar ham shu hisobga kiradi. Faqat kamida bitta holati bor o'qituvchilar ko'rsatilgan.",
            ]),
            ['№', "O'qituvchi", 'Kafedra', "Baho qo'yilmagan holatlar (jami)", ...array_values($labels)],
        ];

        foreach ($this->report['teachers'] as $index => $teacher) {
            $rows[] = [
                $index + 1,
                $teacher['name'],
                $teacher['department'],
                $teacher['total'],
                ...array_map(fn (string $status) => $teacher[$status], array_keys($labels)),
            ];
        }

        if ($this->report['teachers'] === []) {
            $rows[] = $pad(["Bu davrda baho qo'yilmagan holat topilmadi."]);
        } else {
            $totals = $this->report['totals'];
            $rows[] = [
                '',
                'Jami',
                '',
                $totals['total'],
                ...array_map(fn (string $status) => $totals[$status], array_keys($labels)),
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 5, 'B' => 38, 'C' => 32, 'D' => 18, 'E' => 20, 'F' => 20, 'G' => 14, 'H' => 14, 'I' => 14];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $header = self::HEADER_ROW;
                $last = max($header + 1, $this->lastRow);
                $hasRows = $this->report['teachers'] !== [];
                $lastData = $hasRows ? $last - 1 : $last;   // oxirgi qator — "Jami"

                $sheet->mergeCells('A1:I1');
                $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14)->getColor()->setRGB('0F2748');
                $sheet->getRowDimension(1)->setRowHeight(24);

                $sheet->mergeCells('A2:I2');
                $sheet->getStyle('A2')->getFont()->setSize(10)->getColor()->setRGB('475569');

                $sheet->mergeCells('A3:I3');
                $sheet->getStyle('A3')->getFont()->setSize(9)->setItalic(true)->getColor()->setRGB('64748B');
                $sheet->getStyle('A3')->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
                $sheet->getRowDimension(3)->setRowHeight(40);

                $sheet->getStyle("A{$header}:I{$header}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$header}:I{$header}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A{$header}:I{$header}")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($header)->setRowHeight(48);
                // So'ralgan ikki son ajralib tursin: jami (ko'k) va ariza orqali baho qo'yilgani (yashil)
                $sheet->getStyle("E{$header}")->getFill()->getStartColor()->setRGB('0F7A52');
                $sheet->freezePane('A'.($header + 1));

                if (! $hasRows) {
                    $sheet->mergeCells("A{$last}:I{$last}");

                    return;
                }

                $sheet->setAutoFilter("A{$header}:I{$lastData}");
                $body = $header + 1;
                $sheet->getStyle("A{$body}:I{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$body}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("D{$body}:I{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getStyle("B{$body}:B{$lastData}")->getFont()->setBold(true);
                $sheet->getStyle("D{$body}:E{$last}")->getFont()->setBold(true);
                $sheet->getStyle("E{$body}:E{$lastData}")->getFont()->getColor()->setRGB('0F7A52');

                // "Jami" qatori
                $sheet->getStyle("A{$last}:I{$last}")->getFont()->setBold(true);
                $sheet->getStyle("A{$last}:I{$last}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
                $sheet->getStyle("A{$last}:I{$last}")->getBorders()->getTop()
                    ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('1B3A63');

                $sheet->getStyle("A{$body}:I{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
