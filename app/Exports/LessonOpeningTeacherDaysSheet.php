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
 * qaysi juftlikda va qaysi soatda ekani, ariza holati, so'rovni kim yuborgani,
 * kim(lar) tasdiqlagani va guruhning mas'ul back ofis xodimi.
 *
 * 1-qator — jami (filtrlanganda ko'rinayotganlar bo'yicha yangilanadi),
 * 2-qator — sarlavha, ma'lumot 3-qatordan.
 */
class LessonOpeningTeacherDaysSheet implements FromArray, WithColumnWidths, WithEvents, WithTitle
{
    /** Ustunlar: A..Q (Holat — L, Mas'ul xodim — Q) */
    private const LAST_COL = 'Q';

    private const STATUS_COL = 'L';

    private const HEADER_ROW = 2;

    private int $lastRow = 0;

    public function __construct(private array $report) {}

    public function title(): string
    {
        return 'Kunlar';
    }

    public function array(): array
    {
        $days = $this->report['days'] ?? [];
        $students = array_sum(array_map(fn ($day) => (int) ($day['ungraded_students'] ?? 0), $days));

        $rows = [
            array_pad(['', 'Jami: '.count($days).' ta dars kuni', '', '', '', 'Jami:', $students], 17, ''),
            [
                '№', "O'qituvchi", 'Kafedra', 'Guruh', 'Kurs', 'Semestr',
                "Baho qo'yilmagan talaba",
                'Fan', 'Dars sanasi', 'Juftlik', 'Soat', 'Holat',
                "So'rov raqami", 'Ariza sanasi', "So'rov yuborgan", 'Tasdiqlaganlar', "Mas'ul xodim",
            ],
        ];

        foreach ($days as $index => $day) {
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
                ($day['manager'] ?? '') !== '' ? $day['manager'] : LessonOpeningTeacherStudentsSheet::UNASSIGNED,
            ];
        }

        $this->lastRow = count($rows);

        return $rows;
    }

    public function columnWidths(): array
    {
        return ['A' => 6, 'B' => 34, 'C' => 32, 'D' => 16, 'E' => 9, 'F' => 12, 'G' => 14, 'H' => 40, 'I' => 13, 'J' => 16, 'K' => 14, 'L' => 36, 'M' => 11, 'N' => 17, 'O' => 34, 'P' => 40, 'Q' => 34];
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
                $h = self::HEADER_ROW;
                $first = $h + 1;

                // Jami qatori
                $sheet->getStyle("A1:{$lc}1")->getFont()->setBold(true)->getColor()->setRGB('1B3A63');
                $sheet->getStyle("A1:{$lc}1")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('E8EEF7');
                $sheet->getStyle('F1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_RIGHT);
                $sheet->getStyle('G1')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                $sheet->getRowDimension(1)->setRowHeight(22);

                $sheet->getStyle("A{$h}:{$lc}{$h}")->getFont()->setBold(true)->setSize(10)->getColor()->setRGB('FFFFFF');
                $sheet->getStyle("A{$h}:{$lc}{$h}")->getFill()
                    ->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('1B3A63');
                $sheet->getStyle("A{$h}:{$lc}{$h}")->getAlignment()
                    ->setWrapText(true)->setHorizontal(Alignment::HORIZONTAL_CENTER)->setVertical(Alignment::VERTICAL_CENTER);
                $sheet->getRowDimension($h)->setRowHeight(28);
                $sheet->freezePane("A{$first}");

                if ($last < $first) {
                    return;
                }

                // Filtr qo'yilganda faqat ko'rinayotgan qatorlar hisoblanadi
                $sheet->setCellValue('B1', "=\"Jami: \"&SUBTOTAL(103,B{$first}:B{$last})&\" ta dars kuni\"");
                $sheet->setCellValue('G1', "=SUBTOTAL(109,G{$first}:G{$last})");

                $sheet->setAutoFilter("A{$h}:{$lc}{$last}");
                $sheet->getStyle("A{$first}:{$lc}{$last}")->getBorders()->getBottom()
                    ->setBorderStyle(Border::BORDER_HAIR)->getColor()->setRGB('CBD5E1');
                $sheet->getStyle("A{$first}:A{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Kurs, semestr, baho qo'yilmagan talaba — markazda
                $sheet->getStyle("E{$first}:G{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Sana, juftlik, soat — markazda
                $sheet->getStyle("I{$first}:K{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // So'rov raqami, ariza sanasi — markazda
                $sheet->getStyle("M{$first}:N{$last}")->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
                // Baho qo'yilmagan talaba soni ajralib tursin
                $sheet->getStyle("G{$first}:G{$last}")->getFont()->setBold(true);

                // Holat rangi: yashil — baho qo'yilgan, to'q sariq — tasdiqlangan lekin baho yo'q,
                // ko'k — kutilmoqda, qizil — rad etilgan, kulrang — ariza yo'q
                $colors = [
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::GRADED] => '0F7A52',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::APPROVED] => 'B45309',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::PENDING] => '1D4ED8',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::REJECTED] => 'B3261E',
                    LessonOpeningTeacherReport::LABELS[LessonOpeningTeacherReport::NO_REQUEST] => '64748B',
                ];
                for ($row = $first; $row <= $last; $row++) {
                    $color = $colors[(string) $sheet->getCell("{$sc}{$row}")->getValue()] ?? null;
                    if ($color) {
                        $sheet->getStyle("{$sc}{$row}")->getFont()->setBold(true)->getColor()->setRGB($color);
                    }
                }

                $sheet->getStyle("A{$h}:{$lc}{$last}")->getAlignment()->setVertical(Alignment::VERTICAL_CENTER);
            },
        ];
    }
}
