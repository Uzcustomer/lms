<?php

namespace App\Services\SubjectDifference;

use App\Models\SubjectDifferenceCheck;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Bosqich natijasi Excel'da — registrator qo'lda tuzadigan "Fanlar bazasi" jadvali
 * ko'rinishida (Semestr, Fanlar, Umumiy yuklama, Kredit, Ball, Baho, Kredit*Baho),
 * GPA formulasi bilan; yonida transkriptdagi mos fan va izoh.
 */
class SubjectDifferenceExcel
{
    public function download(SubjectDifferenceCheck $check, array $level, array $result, string $filename): StreamedResponse
    {
        $book = $this->build($check, $level, $result);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function build(SubjectDifferenceCheck $check, array $level, array $result): Spreadsheet
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setTitle('Fanlar bazasi');

        $ws->setCellValue('A1', sprintf('%s — fanlar farqi (%s)', $check->student_name ?: 'Talaba', $check->source_institution ?: 'avvalgi OTM'));
        $ws->setCellValue('A2', sprintf("O'quv reja: %s · 1–%d-semestr · %s", $level['curriculum_name'], $level['upto'], GradeScale::fromArray($check->scale)->label()));
        $ws->mergeCells('A1:K1');
        $ws->mergeCells('A2:K2');
        $ws->getStyle('A1')->getFont()->setBold(true)->setSize(13);

        $head = ['Semestr', 'Fanlar', 'Umumiy yuklama', 'Kredit', 'BALL', 'BAHO', 'KREDIT*BAHO', 'Fan turi', 'Transkriptdagi fan', 'Transkript semestri', 'Izoh'];
        $ws->fromArray($head, null, 'A4');
        $ws->getStyle('A4:K4')->getFont()->setBold(true);
        $ws->getStyle('A4:K4')->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('DBEAFE');

        $r = 5;
        $first = $r;
        foreach ($result['rows'] as $row) {
            $ws->setCellValue("A{$r}", $row['semester'] . '-semestr');
            $ws->setCellValue("B{$r}", $row['name']);
            $ws->setCellValue("C{$r}", $row['hours']);
            $ws->setCellValue("D{$r}", $row['excluded'] ? 0 : $row['credit']);
            $ws->setCellValue("E{$r}", $row['tr_ball']);
            $ws->setCellValue("F{$r}", $row['grade']);
            $ws->setCellValue("G{$r}", "=D{$r}*F{$r}");
            $ws->setCellValue("H{$r}", $row['type']);
            $ws->setCellValue("I{$r}", $row['tr_name']);
            $ws->setCellValue("J{$r}", $row['tr_semester'] ? $row['tr_semester'] . '-semestr' : null);
            $ws->setCellValue("K{$r}", implode('; ', $row['notes']));
            if ($row['farq'] && !$row['excluded']) {
                $ws->getStyle("A{$r}:K{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEE2E2');
            } elseif (!empty($row['notes'])) {
                $ws->getStyle("A{$r}:K{$r}")->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setRGB('FEF9C3');
            }
            $r++;
        }
        $last = $r - 1;

        $ws->setCellValue("D{$r}", "=SUM(D{$first}:D{$last})");
        $ws->setCellValue("G{$r}", "=SUM(G{$first}:G{$last})");
        $ws->getStyle("A{$r}:K{$r}")->getFont()->setBold(true);
        $r++;
        $ws->setCellValue("F{$r}", 'GPA:');
        // Yaxlitlanmaydi: 2.396 "2.40" bo'lib ko'rinib, chegaradan o'tgandek tuyulmasin.
        $ws->setCellValue("G{$r}", '=G' . ($r - 1) . '/D' . ($r - 1));
        $ws->getStyle("G{$r}")->getNumberFormat()->setFormatCode('0.000');
        $ws->getStyle("F{$r}:G{$r}")->getFont()->setBold(true);
        $r += 2;
        $ws->setCellValue("A{$r}", 'Xulosa: ' . $result['decision']['text']);
        $ws->mergeCells("A{$r}:K{$r}");
        $ws->getStyle("A{$r}")->getFont()->setBold(true);
        $r++;
        $ws->setCellValue("A{$r}", sprintf('Fanlar farqi: %d ta fan, %s kredit', $result['farq_count'], $result['farq_credit']));
        $ws->mergeCells("A{$r}:K{$r}");

        $ws->getStyle("A4:K{$last}")->getBorders()->getAllBorders()->setBorderStyle(Border::BORDER_THIN);
        $ws->getStyle("B5:B{$last}")->getAlignment()->setWrapText(true);
        $ws->getStyle("I5:K{$last}")->getAlignment()->setWrapText(true)->setVertical(Alignment::VERTICAL_TOP);
        foreach (['A' => 12, 'B' => 46, 'C' => 10, 'D' => 8, 'E' => 8, 'F' => 8, 'G' => 13, 'H' => 11, 'I' => 40, 'J' => 12, 'K' => 50] as $col => $w) {
            $ws->getColumnDimension($col)->setWidth($w);
        }
        $ws->freezePane('A5');

        return $book;
    }
}
