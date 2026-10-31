<?php

namespace App\Services;

use App\Models\VedomostSubmission;
use Illuminate\Support\Collection;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Border;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Tasdiqlangan vedomostlar jamlanmasi — «Vedomost tekshirish» Excel eksporti formatida
 * (A–AJ ustunlar, xuddi o'sha sarlavha, kenglik va son formatlari) + oxirgi ustunda yopilish shakli.
 *
 * Qiymatlar — xodim tekshirib saqlagan AI hisobotidagi Mark qiymatlari: Mark hisobi (12-shakl —
 * «Vedomost tekshirish», 12a/12b va qo'shimcha shakllar — shu urinish talabalari va OSKE/Test
 * bahosi) + hisobotda «faqat hisobotda» o'zgartirilgan qiymatlar (binafsha katak, izohda asl qiymat
 * va sabab). Vaznlar AI o'qigan vedomost sarlavhasidan (bo'lmasa yopilish shakli bo'yicha standart).
 * Chetlashtirilgan talabalar kiritilmaydi.
 */
class VedomostJamlanmaExcel
{
    public const LAST_VT_COL = 'AJ';

    public const CLOSING_COL = 'AK';

    private const CLOSING_LABELS = [
        'oski' => 'Faqat OSKI', 'test' => 'Faqat Test', 'oski_test' => 'OSKI + Test',
        'normativ' => 'Normativ', 'sinov' => 'Sinov (test)',
    ];

    private const WIDTHS = [
        'A' => 4, 'B' => 30, 'C' => 14, 'D' => 6, 'E' => 7, 'F' => 2, 'G' => 6, 'H' => 7, 'I' => 2, 'J' => 6,
        'K' => 7, 'L' => 2, 'M' => 7, 'N' => 7, 'O' => 2, 'P' => 6, 'Q' => 7, 'R' => 2, 'S' => 6, 'T' => 7,
        'U' => 2, 'V' => 7, 'W' => 6, 'X' => 20, 'Y' => 10, 'Z' => 14, 'AA' => 10, 'AB' => 28, 'AC' => 14,
        'AD' => 14, 'AE' => 8, 'AF' => 12, 'AG' => 16, 'AH' => 6, 'AI' => 6, 'AJ' => 22, 'AK' => 14,
    ];

    public function __construct(private VedomostAiChecker $checker, private VedomostCheckEngine $engine)
    {
    }

    public function download(Collection $submissions, string $filename): StreamedResponse
    {
        $book = $this->build($submissions);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    /** @param  Collection<int, VedomostSubmission>  $submissions  tasdiqlangan guruh yozuvlari */
    public function build(Collection $submissions): Spreadsheet
    {
        $book = new Spreadsheet();
        $ws = $book->getActiveSheet();
        $ws->setTitle('Jamlanma');

        $cols = array_slice(VedomostCheckEngine::VT_HEADERS, 0, array_search(self::LAST_VT_COL, array_keys(VedomostCheckEngine::VT_HEADERS), true) + 1, true);
        $cols[self::CLOSING_COL] = 'Yopilish shakli';
        $ws->getRowDimension(1)->setRowHeight(30);
        foreach ($cols as $col => $label) {
            $ws->setCellValue($col . '1', $label);
        }
        $ws->getStyle('A1:' . self::CLOSING_COL . '1')->applyFromArray([
            'font' => ['bold' => true, 'size' => 9],
            'alignment' => ['horizontal' => Alignment::HORIZONTAL_CENTER, 'vertical' => Alignment::VERTICAL_CENTER, 'wrapText' => true],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['argb' => 'FFD9E1F2']],
            'borders' => ['allBorders' => ['borderStyle' => Border::BORDER_THIN, 'color' => ['argb' => 'FF000000']]],
        ]);
        foreach (self::WIDTHS as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }

        $r = 1;
        $n = 0;
        foreach ($submissions as $sub) {
            $w = $this->weightsFor($sub);
            $sysEdits = $sub->ai_result['edits']['system'] ?? [];
            foreach ($this->studentsOf($sub) as $stu) {
                if (($stu['status_code'] ?? '') === '60') {
                    continue;
                }
                $stu = $this->engine->applySystemEdits($stu, $sysEdits[VedomostScanReader::digits($stu['student_id'] ?? '')] ?? []);
                $vt = $this->engine->vtRow($stu, $w, ['level_code' => $stu['meta']['level_code'] ?? ''], ++$n);
                $r++;
                foreach (array_keys($cols) as $col) {
                    if ($col === self::CLOSING_COL) {
                        $ws->setCellValue($col . $r, self::CLOSING_LABELS[$sub->closing_form] ?? (string) $sub->closing_form);
                        continue;
                    }
                    $v = $vt[$col] ?? null;
                    if (in_array($col, ['C', 'Z', 'AF'], true)) {
                        $ws->setCellValueExplicit($col . $r, (string) $v, DataType::TYPE_STRING);
                    } elseif ($v !== null && $v !== '') {
                        $ws->setCellValue($col . $r, $v);
                    }
                }
                foreach (($stu['sys_edited'] ?? []) as $col => $why) {
                    $ws->getStyle($col . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9D5FF');
                    $orig = $stu['sys_orig'][$col] ?? null;
                    $ws->getComment($col . $r)->getText()->createText(
                        'Hisobotda o‘zgartirilgan. Mark: ' . ($orig === null ? '—' : $orig) . ($why !== '' ? "\nSabab: {$why}" : '')
                    );
                }
                // «Vedomost tekshirish» eksportidagi son formatlari.
                $ws->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0');
                $ws->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0');
                if ($w['on'] > 0 && !in_array($stu['meta']['level_code'] ?? '', ['14', '15'], true)) {
                    $ws->getStyle("K{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
                $ws->getStyle("N{$r}")->getNumberFormat()->setFormatCode('0%');
                if ($w['oski'] > 0 && $w['test'] > 0) {
                    $ws->getStyle("Q{$r}")->getNumberFormat()->setFormatCode('0.00');
                    $ws->getStyle("T{$r}")->getNumberFormat()->setFormatCode('0.00');
                }
            }
        }

        $ws->freezePane('D2');
        $ws->setAutoFilter('A1:' . self::CLOSING_COL . max(1, $r));

        return $book;
    }

    /** 12a/12b umumiy vedomosti — birlikning barcha guruhlari (shu urinish talabalari); boshqalari — o'z guruhi. */
    private function studentsOf(VedomostSubmission $sub): array
    {
        if (!$sub->isCombinedForm() || $sub->manual_opened_at !== null) {
            return $this->checker->studentsFor($sub);
        }
        $attempt = VedomostAiChecker::formAttempt($sub->form_type);
        $shakl = VedomostSubmission::formLabel($sub->form_type);
        $out = [];
        foreach (app(VedomostMergeService::class)->unitGroupsOf($sub) as $g) {
            array_push($out, ...$this->checker->studentsFor($g, $attempt, $shakl));
        }

        return $out;
    }

    /** Vaznlar: AI o'qigan vedomost sarlavhasidan; tekshirilmagan bo'lsa — yopilish shakli bo'yicha. */
    private function weightsFor(VedomostSubmission $sub): array
    {
        $w = $sub->ai_result['report']['weights'] ?? null;
        if (is_array($w) && array_sum(array_map('intval', $w)) === 100) {
            return array_merge(['jb' => 0, 'mt' => 0, 'on' => 0, 'oski' => 0, 'test' => 0], array_map('intval', $w));
        }

        return VedomostCheckEngine::defaultWeights($sub->closing_form);
    }
}
