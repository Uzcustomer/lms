<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI tekshiruv natijasi — Python "vedomost_avto" dasturining build_result_excel
 * fayli bilan bir xil tuzilma va ranglar:
 *   Tekshirish — "Vedomost tekshirish" (HEMIS) ustunlari + PDF (BQ) qiymatlari va Δ,
 *                Test markazi, kontingent, Holat, Izoh;
 *   Sarlavha, Xatolar, pdf (BQ).
 */
class VedomostAiReportExcel
{
    public const YELLOW = 'FFFFFF00';
    public const ORANGE = 'FFF6C98A';
    public const LIGHT = 'FFFFFFCC';
    public const HEAD = 'FFD9E1F2';
    public const BLUE = 'FFDDEBF7';

    /** PDF (BQ) ustunlari: [sarlavha, cells kaliti, VT ustuni]. */
    public const AB = [
        ['JN % (BQ)', 'jn', 'D'],
        ['MT ball (BQ)', 'mtb', 'H'],
        ['OSKE % (BQ)', 'oski', 'P'],
        ['TEST % (BQ)', 'test', 'S'],
        ['YN (BQ)', 'yn', 'V'],
    ];

    public function download(array $report, string $filename): StreamedResponse
    {
        $book = $this->build($report);

        return response()->streamDownload(function () use ($book) {
            $writer = new Xlsx($book);
            $writer->setPreCalculateFormulas(false);
            $writer->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function build(array $report): Spreadsheet
    {
        $book = new Spreadsheet();
        $this->mainSheet($book->getActiveSheet(), $report);
        $this->headerSheet($book->createSheet(), $report);
        $this->errorsSheet($book->createSheet(), $report);
        $this->pdfSheet($book->createSheet(), $report);
        foreach ($book->getWorksheetIterator() as $ws) {
            $ws->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex(max(1, Coordinate::columnIndexFromString($ws->getHighestColumn()))) . max(1, $ws->getHighestRow()));
            if ($ws->getFreezePane() === null) {
                $ws->freezePane('A2');
            }
        }
        $book->setActiveSheetIndex(0);

        return $book;
    }

    private function mainSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('Tekshirish');
        $vtCols = array_keys(VedomostCheckEngine::VT_HEADERS);
        $ncol = count($vtCols);
        $use = $report['use'] ?? [];
        $useO = !empty($use['oski']);
        $useT = !empty($use['test']);

        $ex = [];
        foreach (self::AB as [$t]) {
            array_push($ex, $t, 'Δ');
        }
        array_push($ex, '', 'OSKE test markazi', 'Δ', 'TEST test markazi', 'Δ', 'FIO (kontingent)', 'Holat', 'Izoh');
        $head = array_merge(array_values(VedomostCheckEngine::VT_HEADERS), $ex);
        foreach ($head as $i => $v) {
            $cell = $ws->getCell([$i + 1, 1]);
            $cell->setValue($v);
            $cell->getStyle()->getFont()->setBold(true);
            if ($v !== '') {
                $this->fill($ws, $i + 1, 1, self::HEAD);
            }
        }

        $rn = 1;
        foreach ($report['rows'] as $r) {
            $rn++;
            $vt = $r['vt'];
            foreach ($vtCols as $i => $col) {
                $v = $vt[$col] ?? null;
                if ($col === 'C' || $col === 'Z' || $col === 'AF') {
                    $ws->getCell([$i + 1, $rn])->setValueExplicit((string) $v, DataType::TYPE_STRING);
                } elseif ($v !== null && $v !== '') {
                    $ws->getCell([$i + 1, $rn])->setValue($v);
                }
            }
            $c = $ncol + 1;
            foreach (self::AB as [, $key, $hc]) {
                $skip = ($key === 'test' && !$useT) || ($key === 'oski' && !$useO);
                $cc = $r['cells'][$key] ?? null;
                $vc = $ws->getCell([$c, $rn]);
                if ($skip) {
                    $vc->setValue('kerak emas');
                    $vc->getStyle()->getFont()->setItalic(true)->getColor()->setARGB('FF8A8A8A');
                } elseif ($r['pdf'] === null) {
                    $vc->setValue("PDFda yo'q");
                } elseif ($cc) {
                    $val = ($cc['status'] === 'ocr' && $cc['raw'] !== '') ? $cc['raw'] : ($cc['pdf'] ?? ($cc['raw'] !== '' ? $cc['raw'] : null));
                    if ($val !== null) {
                        $vc->setValue($val);
                    }
                    if ($cc['pdf'] !== null) {
                        $ws->getCell([$c + 1, $rn])->setValue(sprintf('=IFERROR(%s%d-%s%d,"")',
                            Coordinate::stringFromColumnIndex($c), $rn, $hc, $rn));
                    }
                    if ($cc['status'] === 'bad') {
                        $this->fill($ws, $c, $rn, self::YELLOW);
                        $this->fill($ws, $c + 1, $rn, self::YELLOW);
                    } elseif ($cc['status'] === 'ocr') {
                        $this->fill($ws, $c, $rn, self::ORANGE);
                        $this->fill($ws, $c + 1, $rn, self::ORANGE);
                    }
                    if (in_array(VedomostCheckEngine::COMPARE[$key][1], $r['edited'] ?? [], true)) {
                        $ws->getComment([$c, $rn])->getText()->createText("AI o'qigan: " . ($r['orig'][VedomostCheckEngine::COMPARE[$key][1]] ?? '') . ' (xodim tuzatgan)');
                    }
                }
                $c += 2;
            }
            $c++; // bo'sh ustun

            foreach ([['oski', 'P', $useO, !empty($use['tm_oski'])], ['test', 'S', $useT, !empty($use['tm_test'])]] as [$k, $hc, $need, $on]) {
                $v = $ws->getCell([$c, $rn]);
                $d = $ws->getCell([$c + 1, $rn]);
                $tm = $r['tm'][$k] ?? null;
                if (!$need) {
                    $v->setValue('kerak emas');
                    $v->getStyle()->getFont()->setItalic(true)->getColor()->setARGB('FF8A8A8A');
                } elseif (!$on) {
                    $v->setValue("Test markazida natija yo'q");
                    $this->fill($ws, $c, $rn, self::ORANGE);
                } elseif (!empty($tm['sinov'])) {
                    $v->setValue('sinov: JN ' . ($tm['jn'] ?? ''));
                    $d->setValue(sprintf('=%s%d-D%d', $hc, $rn, $rn));
                    if ($tm['status'] === 'bad') {
                        $this->fill($ws, $c, $rn, self::YELLOW);
                        $this->fill($ws, $c + 1, $rn, self::YELLOW);
                    }
                } elseif ($tm !== null) {
                    $val = $tm['tm'];
                    $v->setValue($val !== null ? (!empty($tm['dal']) ? $val . ' (Dalolatnoma)' : $val) : (!empty($tm['nd']) ? 'nd' : "yo'q"));
                    if (!empty($tm['nd'])) {
                        $d->setValue('nd (test markazida yo‘q, HEMIS 0)');
                    } elseif ($val !== null && empty($tm['dal'])) {
                        $d->setValue(sprintf('=%s%d-%s%d', Coordinate::stringFromColumnIndex($c), $rn, $hc, $rn));
                    }
                    if ($tm['status'] === 'bad') {
                        $this->fill($ws, $c, $rn, self::YELLOW);
                        $this->fill($ws, $c + 1, $rn, self::YELLOW);
                    }
                }
                $c += 2;
            }

            $kc = $ws->getCell([$c++, $rn]);
            $kc->setValue($r['kont_fio'] ?? "KONTINGENTDA YO'Q");
            if (($r['kont_fio'] ?? null) === null) {
                $this->fill($ws, $c - 1, $rn, self::YELLOW);
            }
            $ws->getCell([$c++, $rn])->setValue(VedomostCheckEngine::STATUS_TXT[$r['status']] ?? '');
            $ws->getCell([$c, $rn])->setValue(implode('; ', array_column($r['flags'], 'm')));

            if (is_numeric($vt['V'] ?? null) && (float) $vt['V'] == 0) {
                for ($i = 1; $i <= $ncol; $i++) {
                    $this->fill($ws, $i, $rn, self::LIGHT);
                }
            }
            if (in_array($r['status'], ['bad', 'missing', 'nopdf'], true)) {
                foreach ([1, 2, 3] as $ci) {
                    $this->fill($ws, $ci, $rn, self::YELLOW);
                }
            } elseif ($r['status'] === 'ocr') {
                $this->fill($ws, 2, $rn, self::ORANGE);
            }
            if (!empty($r['flags'])) {
                $ws->getComment([2, $rn])->getText()->createText(implode("\n", array_column($r['flags'], 'm')));
            }
            foreach (['E' => '0.0', 'H' => '0.0', 'N' => '0%', 'AM' => '0.00%'] as $col => $fmt) {
                $ws->getStyle($col . $rn)->getNumberFormat()->setFormatCode($fmt);
            }
            if ($useO && $useT) {
                $ws->getStyle('Q' . $rn)->getNumberFormat()->setFormatCode('0.00');
                $ws->getStyle('T' . $rn)->getNumberFormat()->setFormatCode('0.00');
            }
        }

        $base = $ncol + 10 + 1;
        $ws->getColumnDimension('B')->setWidth(42);
        foreach ([12, 6, 12, 6] as $i => $wd) {
            $ws->getColumnDimension(Coordinate::stringFromColumnIndex($base + 1 + $i))->setWidth($wd);
        }
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex($base + 5))->setWidth(40);
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex($base + 6))->setWidth(16);
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex($base + 7))->setWidth(70);
        $ws->freezePane('D2');
    }

    private function headerSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('Sarlavha');
        $ws->fromArray(['Tekshiruv', 'Natija', 'Holat'], null, 'A1');
        $ws->getStyle('A1:C1')->getFont()->setBold(true);
        $r = 1;
        foreach ($report['header'] ?? [] as $h) {
            $r++;
            $ws->fromArray([$h['k'], $h['v'], $h['st'] === 'ok' ? 'OK' : ($h['st'] === 'bad' ? 'XATO' : 'Tekshiring')], null, 'A' . $r);
            if ($h['st'] !== 'ok') {
                $this->fill($ws, 3, $r, $h['st'] === 'bad' ? self::YELLOW : self::ORANGE);
            }
        }
        $w = $report['weights'] ?? [];
        $r++;
        $ws->fromArray(['Vaznlar (JN/MT/ON/OSKE/Test)', implode(' / ', [$w['jb'] ?? 0, $w['mt'] ?? 0, $w['on'] ?? 0, $w['oski'] ?? 0, $w['test'] ?? 0]), 'OK'], null, 'A' . $r);
        $ws->getColumnDimension('A')->setWidth(24);
        $ws->getColumnDimension('B')->setWidth(120);
        $ws->getColumnDimension('C')->setWidth(12);
    }

    private function errorsSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('Xatolar');
        $ws->fromArray(['#', 'FIO', 'ID', 'Guruh', 'Holat', 'Izoh'], null, 'A1');
        $ws->getStyle('A1:F1')->getFont()->setBold(true);
        $r = 1;
        foreach ($report['rows'] as $row) {
            if ($row['status'] === 'ok') {
                continue;
            }
            $r++;
            $ws->fromArray([$row['vt']['A'], $row['vt']['B'], '', $row['group'] ?? '', VedomostCheckEngine::STATUS_TXT[$row['status']],
                implode('; ', array_column($row['flags'], 'm'))], null, 'A' . $r);
            $ws->getCell('C' . $r)->setValueExplicit((string) $row['id'], DataType::TYPE_STRING);
        }
        foreach ($report['extra'] ?? [] as $e) {
            $r++;
            $ws->fromArray(['', $e['fio'], '', '', "PDFda bor, HEMISda yo'q", trim('vedomost.pdf / ' . ($e['page'] ?? 1) . ' · ' . ($e['note'] ?? ''))], null, 'A' . $r);
            $ws->getCell('C' . $r)->setValueExplicit((string) $e['id'], DataType::TYPE_STRING);
        }
        foreach ($report['issues'] ?? [] as $i) {
            $r++;
            $ws->fromArray(['', '', '', '', 'Ogohlantirish', $i['m']], null, 'A' . $r);
        }
        foreach ($report['header'] ?? [] as $h) {
            if ($h['st'] !== 'ok') {
                $r++;
                $ws->fromArray(['', '', '', '', 'Sarlavha: ' . $h['k'], $h['v']], null, 'A' . $r);
            }
        }
        $ws->getColumnDimension('B')->setWidth(40);
        $ws->getColumnDimension('F')->setWidth(90);
        $ws->getColumnDimension('D')->setWidth(16);
        $ws->getColumnDimension('E')->setWidth(22);
    }

    private function pdfSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('pdf (BQ)');
        $ws->fromArray(['Fayl / bet', '№', 'FIO (PDF asl)', 'FIO (moslashtirilgan)', 'ID (PDF asl)', 'ID (moslashtirilgan)', 'Ishonch',
            'Usul', 'JB %', 'JB ball', 'MT %', 'MT ball', 'JB+MT ball', 'JB+MT %', 'OSKE %', 'OSKE ball', 'TEST %',
            'TEST ball', 'YN', 'ECTS', "Aniq o'qilmagan"], null, 'A1');
        $ws->getStyle('A1:U1')->getFont()->setBold(true);

        $entries = [];
        foreach ($report['rows'] as $row) {
            if ($row['pdf'] !== null) {
                $entries[] = $row + ['matched' => true];
            }
        }
        foreach ($report['extra'] ?? [] as $e) {
            $entries[] = ['pdf' => $e['pdf'], 'page' => $e['page'], 'nr' => $e['nr'], 'scan_fio' => $e['fio'], 'scan_id' => $e['id'],
                'fio' => '', 'id' => '', 'unclear' => $e['unclear'], 'edited' => $e['edited'], 'flags' => [], 'matched' => false];
        }
        usort($entries, fn ($a, $b) => [(int) ($a['page'] ?? 0), (int) ($a['nr'] ?? 0)] <=> [(int) ($b['page'] ?? 0), (int) ($b['nr'] ?? 0)]);

        $r = 1;
        foreach ($entries as $e) {
            $r++;
            $p = $e['pdf'];
            $idDiff = VedomostScanReader::digits($e['scan_id'] ?? '') !== VedomostScanReader::digits($e['id'] ?? '');
            $conf = !$e['matched'] ? 'NONE' : (collect($e['flags'])->contains('t', 'ocr') ? 'MEDIUM' : 'HIGH');
            $method = !$e['matched'] ? '' : ($idDiff ? 'ism bo‘yicha' : 'ID + ism');
            $vals = ['vedomost.pdf / ' . ($e['page'] ?? 1), $e['nr'] ?? '', $e['scan_fio'] ?? '', $e['fio'] ?? '', '', '', $conf, $method];
            foreach (['jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'jbmton_ball', 'jbmton_pct', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn', 'ects'] as $f) {
                $n = VedomostScanReader::num($p[$f] ?? null);
                $vals[] = $n ?? (($p[$f] ?? '') !== '' ? $p[$f] : null);
            }
            $vals[] = implode(', ', $e['unclear'] ?? []);
            $ws->fromArray($vals, null, 'A' . $r, true);
            $ws->getCell('E' . $r)->setValueExplicit((string) ($e['scan_id'] ?? ''), DataType::TYPE_STRING);
            $ws->getCell('F' . $r)->setValueExplicit((string) ($e['id'] ?? ''), DataType::TYPE_STRING);
            if (!empty($e['unclear'])) {
                $this->fill($ws, 21, $r, self::ORANGE);
            }
            if (!empty($e['edited'])) {
                $ws->getComment('A' . $r)->getText()->createText('Xodim tuzatgan: ' . implode(', ', $e['edited']));
            }
        }
        $ws->getColumnDimension('A')->setWidth(26);
        $ws->getColumnDimension('C')->setWidth(40);
        $ws->getColumnDimension('D')->setWidth(40);
        $ws->getColumnDimension('E')->setWidth(15);
        $ws->getColumnDimension('F')->setWidth(15);
    }

    private function fill(Worksheet $ws, int $col, int $row, string $argb): void
    {
        $ws->getStyle(Coordinate::stringFromColumnIndex($col) . $row)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }
}
