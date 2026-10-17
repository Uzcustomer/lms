<?php

namespace App\Exports;

use App\Models\FanTesti;
use App\Models\FanTestiAttempt;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithEvents;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Events\AfterSheet;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;

/**
 * Test jurnali: "Natijalar" varag'i (bitta qator — bitta talaba urinishi) va
 * "Javoblar" varag'i (bitta qator — talabaning bitta savolga javobi).
 */
class FanTestiJournalExport implements WithMultipleSheets
{
    public function __construct(private FanTesti $test, private Collection $attempts) {}

    public function sheets(): array
    {
        return [
            new FanTestiJournalResultsSheet($this->test, $this->attempts),
            new FanTestiJournalAnswersSheet($this->test, $this->attempts),
        ];
    }

    public static function title(FanTesti $test): string
    {
        return trim($test->name . ($test->subject?->subject_name ? ' — ' . $test->subject->subject_name : ''));
    }

    public static function status(FanTestiAttempt $attempt): string
    {
        if ($attempt->status === 'in_progress') {
            return 'Ishlamoqda';
        }

        return ($attempt->is_passed ? "O'tdi" : "O'tmadi") . ($attempt->status === 'expired' ? ' (vaqt tugagan)' : '');
    }

    /** Sarlavha qatori, ramka va ustun kengliklari. */
    public static function style(AfterSheet $event, int $cols, int $lastRow, array $widths): void
    {
        $sheet = $event->sheet->getDelegate();
        $last = Coordinate::stringFromColumnIndex($cols);

        $sheet->mergeCells("A1:{$last}1");
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(13);
        $sheet->getStyle("A2:{$last}2")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '1B3A63']],
            'alignment' => ['vertical' => 'center', 'wrapText' => true],
        ]);
        if ($lastRow >= 2) {
            $sheet->getStyle("A2:{$last}{$lastRow}")->getBorders()->getAllBorders()
                ->setBorderStyle(Border::BORDER_THIN)->getColor()->setRGB('C9D5E4');
        }
        $sheet->freezePane('A3');
        $sheet->setAutoFilter("A2:{$last}" . max(2, $lastRow));
        foreach ($widths as $i => $w) {
            $sheet->getColumnDimension(Coordinate::stringFromColumnIndex($i + 1))->setWidth($w);
        }
    }
}

class FanTestiJournalResultsSheet implements FromArray, WithEvents, WithTitle, WithStrictNullComparison
{
    private const HEAD = ['№', 'Guruh', 'F.I.Sh.', 'Talaba ID', 'Urinish', 'Fakultet', "Yo'nalish", 'Holat', 'Ball',
        'Maks. ball', 'Foiz', "To'g'ri javoblar", 'Savollar soni', 'Boshlagan vaqti', 'Topshirgan vaqti',
        'Davomiyligi (daq.)', 'Izoh'];

    private int $lastRow = 2;

    public function __construct(private FanTesti $test, private Collection $attempts) {}

    public function title(): string
    {
        return 'Natijalar';
    }

    public function array(): array
    {
        $rows = [[FanTestiJournalExport::title($this->test)], self::HEAD];

        foreach ($this->attempts->values() as $i => $a) {
            $rows[] = [
                $i + 1,
                $a->group_name ?: 'Guruhsiz',
                $a->student_name,
                (string) $a->student_id_number,
                $a->attemptLabel(),
                $a->faculty_name,
                $a->specialty_name,
                FanTestiJournalExport::status($a),
                $a->score !== null ? (float) $a->score : null,
                $a->total_points,
                $a->percent !== null ? round((float) $a->percent, 1) : null,
                $a->correct_count,
                $a->questions_count,
                $a->started_at?->format('d.m.Y H:i'),
                $a->submitted_at?->format('d.m.Y H:i'),
                $a->duration_seconds ? round($a->duration_seconds / 60, 1) : null,
                $this->notes($a),
            ];
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    private function notes(FanTestiAttempt $a): string
    {
        $notes = [];
        if ((int) $a->face_mismatches > 0) {
            $notes[] = "Yuz mos emas: {$a->face_mismatches}/{$a->face_checks}";
        }
        if ((int) $a->away_count > 0) {
            $notes[] = "Chalg'idi: {$a->away_count} marta";
        }
        if (!empty($a->face_bypass_by)) {
            $notes[] = "Yuz tekshiruvisiz: {$a->face_bypass_by}";
        }
        if (($a->ended_reason ?? null) === 'away') {
            $notes[] = "Chalg'igani uchun yakunlandi";
        } elseif ((int) ($a->lock_count ?? 0) >= 1) {
            $notes[] = "{$a->lock_count} marta qulflangan";
        }

        return implode('; ', $notes);
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            FanTestiJournalExport::style($event, count(self::HEAD), $this->lastRow,
                [5, 16, 34, 16, 10, 24, 24, 18, 8, 10, 8, 10, 10, 17, 17, 12, 40]);
        }];
    }
}

class FanTestiJournalAnswersSheet implements FromArray, WithEvents, WithTitle, WithStrictNullComparison
{
    private const HEAD = ['Guruh', 'F.I.Sh.', 'Talaba ID', 'Urinish', 'Savol №', 'Savol', 'Talaba javobi', "To'g'ri javob", 'Natija', 'Ball'];

    private int $lastRow = 2;

    public function __construct(private FanTesti $test, private Collection $attempts) {}

    public function title(): string
    {
        return 'Javoblar';
    }

    public function array(): array
    {
        $rows = [[FanTestiJournalExport::title($this->test) . ' — javoblar'], self::HEAD];

        foreach ($this->attempts as $a) {
            foreach ($a->answers as $ans) {
                $given = $ans->question_type === 'fill_in_blank' ? $ans->answer_text : $ans->selected_option_text;
                $rows[] = [
                    $a->group_name ?: 'Guruhsiz',
                    $a->student_name,
                    (string) $a->student_id_number,
                    $a->attemptLabel(),
                    $ans->question_index + 1,
                    $ans->question_prompt,
                    $given ?: '—',
                    $ans->correct_answer_text,
                    $ans->is_correct ? "To'g'ri" : "Noto'g'ri",
                    $ans->points_earned !== null ? (float) $ans->points_earned : null,
                ];
            }
        }
        $this->lastRow = count($rows);

        return $rows;
    }

    public function registerEvents(): array
    {
        return [AfterSheet::class => function (AfterSheet $event) {
            FanTestiJournalExport::style($event, count(self::HEAD), $this->lastRow,
                [16, 34, 16, 10, 8, 60, 34, 34, 11, 7]);
        }];
    }
}
