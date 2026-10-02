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
 * "Kunlar" varag'i: yig'madagi har bir son ortidagi dars kunlari — o'qituvchi,
 * kafedrasi, guruh, kurs, semestr, fan, dars sanasi va (baho qo'yilmagan bo'lsa)
 * qaysi juftlikda va qaysi soatda ekani, ariza holati, so'rovni kim yuborgani va
 * kim(lar) tasdiqlagani.
 */
class LessonOpeningTeacherDaysSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Ustunlar: A..P (Holat — K ustun) */
    private const LAST_COL = 'P';

    private const STATUS_COL = 'L';

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Kunlar';
    }

    public function array(): array
    {
        $rows = [[
            '№', "O'qituvchi", 'Kafedra', 'Guruh', 'Kurs', 'Semestr',
            "Baho qo'yilmagan talaba",
            'Fan', 'Dars sanasi', 'Juftlik', 'Soat', 'Holat',
            "So'rov raqami", 'Ariza sanasi', "So'rov yuborgan", 'Tasdiqlaganlar',
        ]];

        foreach ($this->report['days'] as $index => $day) {
            $rows[] = [
                $index + 1,
                $day['teacher'],
                $day['department'] ?? '',
                $day['group'],
                $day['course'] ?? '',
                $day['semester'] ?? '',
                ($day['ungraded_students'] ?? 0) > 0 ? $day['ungraded_students'] : '',
                $day['subject'],
                $day['date'] ? \Carbon\Carbon::parse($day['date'])->format('d.m.Y') : '',
                $day['pair'] ?? '',
                $day['time'] ?? '',
                LessonOpeningTeacherReport::LABELS[$day['status']] ?? $day['status'],
                $day['request_number'] ?? '',
                $day['requested_at'] ? $day['requested_at']->format('d.m.Y H:i') : '',
                $day['applicant'] ?? '',
                $day['approvers'] ?? '',
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 34, 'C' => 32, 'D' => 16, 'E' => 9, 'F' => 12, 'G' => 14, 'H' => 40, 'I' => 13, 'J' => 16, 'K' => 14, 'L' => 36, 'M' => 11, 'N' => 17, 'O' => 34, 'P' => 40];
    }

    public function registerEvents(): array
    {
        return [
            AfterSheet::class => function (AfterSheet $event) {
                $sheet = $event->sheet->getDelegate();
                $last = $this->lastRow;
                // Butun hujjat Times New Roman (rasmiy hisobot ko'rinishi).
                // Varaq uslubi — keyingi aniq uslublar buni bekor qilmaydi.
                $sheet->getParent()->getDefaultStyle()->getFont()->setName('Times New Roman')->setSize(11);
                $lc = self::LAST_COL;
                $sc = self::STATUS_COL;

                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(28);
                $sheet->freezePane('A2');

                if ($last < 2) {
                    return;
                }

                $sheet->setAutoFilter("A1:{$lc}{$last}");
                $sheet->getStyle("A2:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A2:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Kurs, semestr, baho qo'yilmagan talaba — markazda
                $sheet->getStyle("E2:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Sana, juftlik, soat — markazda
                $sheet->getStyle("I2:K{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // So'rov raqami, ariza sanasi — markazda
                $sheet->getStyle("M2:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Baho qo'yilmagan talaba soni ajralib tursin
                $sheet->getStyle("G2:G{$last}")->getFont()->setBold(true);

                // Holat rangi: yashil — baho qo'yilgan, to'q sariq — tasdiqlangan lekin baho yo'q,
                // ko'k — kutilmoqda, qizil — rad etilgan, kulrang — ariza yo'q
                $colors = [
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::GRADED] => '0F7A52',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::APPROVED] => 'B45309',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::PENDING] => '1D4ED8',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::REJECTED] => 'B3261E',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::NO_REQUEST] => '64748B',
                ];
                for ($row = 2; $row <= $last; $row++) {
                    $color = $colors[(string) $sheet->getCell("{$sc}{$row}")->getValue()] ?? null;
                    if ($color) {
                        $sheet->getStyle("{$sc}{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }

                $sheet->getStyle("A1:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
