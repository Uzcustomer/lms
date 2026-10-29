<?php

namespace App\Services;

use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * AI tekshiruv hisobotini Excel'ga chiqaradi — Python "vedomost_avto" natija
 * faylidagi varaqlar va ranglar bilan: Tekshirish, Sarlavha, Xatolar, PDF (BQ).
 * Sariq — farq, to'q sariq — PDFdan ko'z bilan tekshirish kerak.
 */
class VedomostAiReportExcel
{
    private const YELLOW = 'FFFFFF00';
    private const ORANGE = 'FFF6C98A';
    private const RED = 'FFFFC7CE';
    private const BLUE = 'FFDDEBF7';
    private const HEAD = 'FFD9E1F2';

    private const STATUS_TXT = [
        'ok' => 'OK', 'bad' => 'XATO', 'ocr' => 'PDFdan tekshiring', 'missing' => "PDFda yo'q", 'extra' => "Tizimda yo'q",
    ];

    public function download(array $report, array $labels, string $filename): StreamedResponse
    {
        $book = $this->build($report, $labels);

        return response()->streamDownload(function () use ($book) {
            (new Xlsx($book))->save('php://output');
        }, $filename, ['Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet']);
    }

    public function build(array $report, array $labels): Spreadsheet
    {
        $book = new Spreadsheet();
        $this->mainSheet($book->getActiveSheet(), $report, $labels);
        $this->headerSheet($book->createSheet(), $report);
        $this->errorsSheet($book->createSheet(), $report);
        $this->pdfSheet($book->createSheet(), $report, $labels);
        $book->setActiveSheetIndex(0);

        return $book;
    }

    public static function visibleFields(array $report): array
    {
        $c = $report['controls'] ?? ['jb', 'mt'];
        $f = ['jb_pct', 'jb_ball', 'mt_pct', 'mt_ball'];
        if (in_array('on', $c, true)) {
            array_push($f, 'on_pct', 'on_ball');
        }
        $f[] = 'jbmton_ball';
        if (in_array('oski', $c, true)) {
            array_push($f, 'oski_pct', 'oski_ball');
        }
        if (in_array('test', $c, true)) {
            array_push($f, 'test_pct', 'test_ball');
        }

        return array_merge($f, ['yn', 'ects', 'baho']);
    }

    private function mainSheet(Worksheet $ws, array $report, array $labels): void
    {
        $ws->setTitle('Tekshirish');
        $fields = self::visibleFields($report);
        $controls = $report['controls'] ?? [];

        $head = ['№', 'F.I.Sh', 'ID raqami'];
        foreach ($fields as $f) {
            $head[] = ($labels[$f] ?? $f) . ' (tizim)';
            $head[] = ($labels[$f] ?? $f) . ' (PDF)';
            if (!in_array($f, ['ects', 'baho'], true)) {
                $head[] = 'Δ';
            }
        }
        $tmCols = array_values(array_intersect(['oski', 'test'], $controls));
        foreach ($tmCols as $k) {
            $head[] = ($k === 'oski' ? 'OSKE' : 'Test') . ' test markazi';
        }
        array_push($head, 'Holat', 'Izoh');
        $ws->fromArray($head, null, 'A1');
        $this->styleHead($ws, count($head));

        $r = 1;
        foreach ($report['rows'] as $row) {
            $r++;
            $c = 1;
            $this->set($ws, $c++, $r, $row['n']);
            $this->set($ws, $c++, $r, $row['fio']);
            $this->set($ws, $c++, $r, (string) $row['id'], true);
            foreach ($fields as $f) {
                $sys = $row['system'][$f] ?? null;
                $pdf = $row['scan'][$f] ?? null;
                $this->set($ws, $c, $r, $sys);
                $pdfCell = $c + 1;
                $this->set($ws, $pdfCell, $r, $this->numOrText($pdf));
                $st = $row['cells'][$f] ?? null;
                $fill = $st === 'bad' ? self::YELLOW : ($st === 'ocr' ? self::ORANGE : null);
                if (in_array($f, $row['edited'] ?? [], true)) {
                    $ws->getComment(Coordinate::stringFromColumnIndex($pdfCell) . $r)
                        ->getText()->createText('AI o\'qigan: ' . ($row['orig'][$f] ?? ''));
                    $fill ??= self::BLUE;
                }
                if ($fill) {
                    $this->fill($ws, $pdfCell, $r, $fill);
                }
                $c += 2;
                if (!in_array($f, ['ects', 'baho'], true)) {
                    $a = VedomostScanReader::num($pdf);
                    $b = VedomostScanReader::num($sys);
                    if ($a !== null && $b !== null) {
                        $this->set($ws, $c, $r, round($a - $b, 2));
                        if ($fill === self::YELLOW || $fill === self::ORANGE) {
                            $this->fill($ws, $c, $r, $fill);
                        }
                    }
                    $c++;
                }
            }
            foreach ($tmCols as $k) {
                $tm = $row['tm'][$k] ?? null;
                $this->set($ws, $c, $r, $tm ? ($tm['value'] ?? "yo'q") : '');
                if ($tm && ($tm['status'] ?? '') === 'bad') {
                    $this->fill($ws, $c, $r, self::YELLOW);
                }
                $c++;
            }
            $this->set($ws, $c, $r, self::STATUS_TXT[$row['status']] ?? $row['status']);
            $statusFill = ['bad' => self::YELLOW, 'ocr' => self::ORANGE, 'missing' => self::RED, 'extra' => self::RED][$row['status']] ?? null;
            if ($statusFill) {
                $this->fill($ws, $c, $r, $statusFill);
            }
            $this->set($ws, $c + 1, $r, implode('; ', array_column($row['flags'] ?? [], 'm')));
        }

        $ws->freezePane('D2');
        $ws->getColumnDimension('B')->setWidth(36);
        $ws->getColumnDimension('C')->setWidth(15);
        $ws->getColumnDimension(Coordinate::stringFromColumnIndex(count($head)))->setWidth(80);
        $ws->setAutoFilter('A1:' . Coordinate::stringFromColumnIndex(count($head)) . max(2, $r));
    }

    private function headerSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('Sarlavha');
        $ws->fromArray(['Tekshiruv', 'Tizimda', 'Skanerda', 'Holat', 'Izoh'], null, 'A1');
        $this->styleHead($ws, 5);
        $r = 1;
        $txt = ['ok' => 'OK', 'bad' => 'XATO', 'warn' => 'Tekshiring', 'info' => "Ma'lumot"];
        foreach ($report['header'] ?? [] as $h) {
            $r++;
            $ws->fromArray([$h['label'], (string) $h['system'], (string) $h['scan'], $txt[$h['status']] ?? $h['status'], $h['note']], null, 'A' . $r);
            if (in_array($h['status'], ['bad', 'warn'], true)) {
                $this->fill($ws, 4, $r, $h['status'] === 'bad' ? self::YELLOW : self::ORANGE);
            }
        }
        $r++;
        $w = $report['weights'] ?? [];
        $ws->fromArray(['Vaznlar (JB/MT/ON/OSKE/Test)', '', implode(' / ', [$w['jn'] ?? 0, $w['mt'] ?? 0, $w['on'] ?? 0, $w['oski'] ?? 0, $w['test'] ?? 0]), '', ''], null, 'A' . $r);
        $sigNames = ['oqituvchi' => "O'qituvchi imzosi", 'dekan' => 'Dekan imzosi', 'kafedra_mudiri' => 'Kafedra mudiri imzosi', 'muhr' => 'Muhr (M.O\')'];
        foreach ($sigNames as $k => $label) {
            $r++;
            $ok = !empty($report['signatures'][$k]);
            $ws->fromArray([$label, "qo'yilgan", $ok ? 'bor' : "yo'q", $ok ? 'OK' : 'XATO', ''], null, 'A' . $r);
            if (!$ok) {
                $this->fill($ws, 4, $r, self::YELLOW);
            }
        }
        foreach ($report['doc_issues'] ?? [] as $d) {
            $r++;
            $ws->fromArray(['Ogohlantirish', '', '', $txt[$d['t']] ?? ($d['t'] === 'ocr' ? 'Tekshiring' : $d['t']), $d['m']], null, 'A' . $r);
        }
        foreach (['A' => 34, 'B' => 40, 'C' => 40, 'D' => 14, 'E' => 70] as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
    }

    private function errorsSheet(Worksheet $ws, array $report): void
    {
        $ws->setTitle('Xatolar');
        $ws->fromArray(['№', 'F.I.Sh', 'ID', 'Holat', 'Izoh'], null, 'A1');
        $this->styleHead($ws, 5);
        $r = 1;
        foreach ($report['rows'] as $row) {
            if ($row['status'] === 'ok') {
                continue;
            }
            foreach ($row['flags'] as $f) {
                if ($f['t'] === 'info') {
                    continue;
                }
                $r++;
                $ws->fromArray([$row['n'], $row['fio'], '', self::STATUS_TXT[$f['t']] ?? $f['t'], $f['m']], null, 'A' . $r);
                $ws->setCellValueExplicit('C' . $r, (string) $row['id'], \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
            }
        }
        foreach ($report['header'] ?? [] as $h) {
            if (in_array($h['status'], ['bad', 'warn'], true)) {
                $r++;
                $ws->fromArray(['', '', '', 'Sarlavha: ' . $h['label'], trim("Tizimda: {$h['system']} · Skanerda: {$h['scan']}. {$h['note']}")], null, 'A' . $r);
            }
        }
        foreach ($report['doc_issues'] ?? [] as $d) {
            if ($d['t'] !== 'info') {
                $r++;
                $ws->fromArray(['', '', '', 'Ogohlantirish', $d['m']], null, 'A' . $r);
            }
        }
        foreach (['B' => 36, 'C' => 15, 'D' => 22, 'E' => 90] as $col => $width) {
            $ws->getColumnDimension($col)->setWidth($width);
        }
    }

    private function pdfSheet(Worksheet $ws, array $report, array $labels): void
    {
        $ws->setTitle('PDF (BQ)');
        $fields = VedomostCheckEngine::FIELDS;
        $head = array_merge(['Bet', '№', 'F.I.Sh (PDF)', 'ID (PDF)'], array_map(fn ($f) => $labels[$f] ?? $f, $fields), ['Noaniq kataklar']);
        $ws->fromArray($head, null, 'A1');
        $this->styleHead($ws, count($head));
        $r = 1;
        foreach ($report['rows'] as $row) {
            if ($row['key'] === null) {
                continue;
            }
            $r++;
            $this->set($ws, 1, $r, $row['page']);
            $this->set($ws, 2, $r, $row['n']);
            $this->set($ws, 3, $r, $row['scan_fio'] ?? $row['fio']);
            $this->set($ws, 4, $r, (string) ($row['scan_id'] ?? $row['id']), true);
            foreach ($fields as $i => $f) {
                $col = 5 + $i;
                $this->set($ws, $col, $r, $this->numOrText($row['scan'][$f] ?? ''));
                if (in_array($f, $row['unclear'] ?? [], true)) {
                    $this->fill($ws, $col, $r, self::ORANGE);
                } elseif (in_array($f, $row['edited'] ?? [], true)) {
                    $this->fill($ws, $col, $r, self::BLUE);
                }
            }
            $this->set($ws, 5 + count($fields), $r, implode(', ', $row['unclear'] ?? []));
        }
        $ws->freezePane('E2');
        $ws->getColumnDimension('C')->setWidth(36);
        $ws->getColumnDimension('D')->setWidth(15);
    }

    private function styleHead(Worksheet $ws, int $n): void
    {
        $range = 'A1:' . Coordinate::stringFromColumnIndex($n) . '1';
        $ws->getStyle($range)->getFont()->setBold(true);
        $ws->getStyle($range)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB(self::HEAD);
        $ws->getStyle($range)->getAlignment()->setWrapText(true)->setVertical('center');
        $ws->getRowDimension(1)->setRowHeight(32);
    }

    private function set(Worksheet $ws, int $col, int $row, $value, bool $asText = false): void
    {
        $coord = Coordinate::stringFromColumnIndex($col) . $row;
        if ($asText) {
            $ws->setCellValueExplicit($coord, (string) $value, \PhpOffice\PhpSpreadsheet\Cell\DataType::TYPE_STRING);
        } else {
            $ws->setCellValue($coord, $value);
        }
    }

    private function fill(Worksheet $ws, int $col, int $row, string $argb): void
    {
        $ws->getStyle(Coordinate::stringFromColumnIndex($col) . $row)
            ->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB($argb);
    }

    private function numOrText($v)
    {
        $n = VedomostScanReader::num($v);

        return $n !== null ? $n : (string) ($v ?? '');
    }
}
