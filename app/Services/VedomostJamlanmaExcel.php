<?php

namespace App\Services;

use App\Models\VedomostSubmission;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
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
        $cached = $this->cachedRows($submissions->pluck('id')->filter()->all());
        foreach ($submissions as $sub) {
            // Oldindan hisoblangan qatorlar (tasdiqlanganda fonda yoziladi); yo'q bo'lsa — hozir hisoblab saqlaymiz.
            $rows = $cached[$sub->id] ?? $this->store($this->full($sub));
            foreach ($rows as $row) {
                $r++;
                $vt = $row['vt'];
                $vt['A'] = ++$n;
                foreach (array_keys($cols) as $col) {
                    if ($col === self::CLOSING_COL) {
                        $ws->setCellValue($col . $r, $row['closing'] ?? '');
                        continue;
                    }
                    $v = $vt[$col] ?? null;
                    if (in_array($col, ['C', 'Z', 'AF'], true)) {
                        $ws->setCellValueExplicit($col . $r, (string) $v, DataType::TYPE_STRING);
                    } elseif ($v !== null && $v !== '') {
                        $ws->setCellValue($col . $r, $v);
                    }
                }
                foreach (($row['edited'] ?? []) as $col => $e) {
                    $ws->getStyle($col . $r)->getFill()->setFillType(Fill::FILL_SOLID)->getStartColor()->setARGB('FFE9D5FF');
                    $orig = $e['orig'] ?? null;
                    $ws->getComment($col . $r)->getText()->createText(
                        'Hisobotda o‘zgartirilgan. Mark: ' . ($orig === null ? '—' : $orig) . (($e['why'] ?? '') !== '' ? "\nSabab: {$e['why']}" : '')
                    );
                }
                // «Vedomost tekshirish» eksportidagi son formatlari.
                $ws->getStyle("E{$r}")->getNumberFormat()->setFormatCode('0.0');
                $ws->getStyle("H{$r}")->getNumberFormat()->setFormatCode('0.0');
                if (!empty($row['on_fmt'])) {
                    $ws->getStyle("K{$r}")->getNumberFormat()->setFormatCode('0.0');
                }
                $ws->getStyle("N{$r}")->getNumberFormat()->setFormatCode('0%');
                if (!empty($row['exam_fmt'])) {
                    $ws->getStyle("Q{$r}")->getNumberFormat()->setFormatCode('0.00');
                    $ws->getStyle("T{$r}")->getNumberFormat()->setFormatCode('0.00');
                }
            }
        }

        $ws->freezePane('D2');
        $ws->setAutoFilter('A1:' . self::CLOSING_COL . max(1, $r));

        return $book;
    }

    /**
     * Bitta vedomostning jamlanma qatorlari (raqamlashsiz): Mark qiymatlari + hisobotdagi tuzatishlar.
     *
     * @return array<int, array{vt: array, edited: array, on_fmt: bool, exam_fmt: bool, closing: string}>
     */
    public function rowsFor(VedomostSubmission $sub): array
    {
        $w = $this->weightsFor($sub);
        $sysEdits = $sub->ai_result['edits']['system'] ?? [];
        $out = [];
        foreach ($this->studentsOf($sub) as $stu) {
            if (($stu['status_code'] ?? '') === '60') {
                continue;
            }
            $stu = $this->engine->applySystemEdits($stu, $sysEdits[VedomostScanReader::digits($stu['student_id'] ?? '')] ?? []);
            $vt = $this->engine->vtRow($stu, $w, ['level_code' => $stu['meta']['level_code'] ?? '', 'form_type' => (string) ($sub->form_type ?: '12')], 0);
            if (array_key_exists('_yn_orig', $vt)) {
                $stu['sys_orig']['V'] = $vt['_yn_orig'];
                unset($vt['_yn_orig']);
            }
            $edited = [];
            foreach (($stu['sys_edited'] ?? []) as $col => $why) {
                $edited[$col] = ['orig' => $stu['sys_orig'][$col] ?? null, 'why' => (string) $why];
            }
            $out[] = [
                'vt' => array_intersect_key($vt, array_flip(array_keys(VedomostCheckEngine::VT_HEADERS))),
                'edited' => $edited,
                'on_fmt' => $w['on'] > 0 && !in_array($stu['meta']['level_code'] ?? '', ['14', '15'], true),
                'exam_fmt' => $w['oski'] > 0 && $w['test'] > 0,
                'closing' => self::CLOSING_LABELS[$sub->closing_form] ?? (string) $sub->closing_form,
            ];
        }

        return $out;
    }

    /** Qatorlarni hisoblab jamlanma keshiga yozadi va qaytaradi. */
    public function store(VedomostSubmission $sub): array
    {
        $rows = $this->rowsFor($sub);
        if (!$sub->exists || !$sub->id) {
            return $rows; // saqlanmagan yozuv (masalan, sinov) — keshlanmaydi
        }
        // Kesh — faqat tezlashtirish: jadval yo'q (migratsiya qilinmagan) yoki yozishda xato bo'lsa ham
        // jamlanma yuklanaveradi.
        try {
            if (Schema::hasTable('vedomost_jamlanma_rows')) {
                DB::table('vedomost_jamlanma_rows')->updateOrInsert(
                    ['vedomost_submission_id' => $sub->id],
                    ['rows' => json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE), 'built_at' => now(), 'updated_at' => now(), 'created_at' => now()]
                );
            }
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::warning("[Jamlanma] kesh yozilmadi #{$sub->id}: " . $e->getMessage());
        }

        return $rows;
    }

    /** Ro'yxat yengil ustunlar bilan olingan bo'lsa (ai_result'siz) — hisoblash uchun to'liq yozuv. */
    private function full(VedomostSubmission $sub): VedomostSubmission
    {
        if (!$sub->exists || array_key_exists('ai_result', $sub->getAttributes())) {
            return $sub;
        }

        return VedomostSubmission::find($sub->id) ?? $sub;
    }

    /** Keshni o'chiradi (vedomost tasdiqlanmagan holatga qaytganda yoki natijasi o'zgarganda). */
    public static function forget(array $submissionIds): void
    {
        if ($submissionIds && Schema::hasTable('vedomost_jamlanma_rows')) {
            DB::table('vedomost_jamlanma_rows')->whereIn('vedomost_submission_id', $submissionIds)->delete();
        }
    }

    /**
     * Keshi yo'q vedomostlarni vaqt chegarasi ichida hisoblab keshga yozadi. Ulgurmaganlari
     * fon navbatiga qo'yiladi va ularning id'lari qaytariladi — so'rov vaqt tugashiga (timeout) yetmasin.
     *
     * @return int[] hali tayyor bo'lmagan vedomostlar
     */
    public function warm(Collection $submissions, float $budgetSeconds): array
    {
        $ids = $submissions->pluck('id')->filter()->all();
        if (empty($ids) || !Schema::hasTable('vedomost_jamlanma_rows')) {
            return [];
        }
        $have = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            $have += array_flip(DB::table('vedomost_jamlanma_rows')->whereIn('vedomost_submission_id', $chunk)->pluck('vedomost_submission_id')->map(fn ($v) => (int) $v)->all());
        }
        $start = microtime(true);
        $left = [];
        foreach ($submissions as $sub) {
            if (isset($have[$sub->id])) {
                continue;
            }
            if (microtime(true) - $start >= $budgetSeconds) {
                $left[] = (int) $sub->id;
                continue;
            }
            try {
                $this->store($this->full($sub));
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning("[Jamlanma] vedomost #{$sub->id} qatorlari hisoblanmadi: " . $e->getMessage());
                $left[] = (int) $sub->id;
            }
        }
        // Bitta ishda bir nechtadan — bittasi uzoq ishlasa (12a/12b umumiy) qolganlari kutib qolmasin.
        // Qayta bosilganda bir xil ish navbatga ikkinchi marta qo'yilmaydi (10 daqiqa).
        $queue = array_values(array_filter($left, fn ($id) => \Illuminate\Support\Facades\Cache::add("vedomost-jamlanma-build:{$id}", 1, 600)));
        foreach (array_chunk($queue, 5) as $chunk) {
            \App\Jobs\BuildVedomostJamlanmaRows::dispatch($chunk);
        }

        return $left;
    }

    /** @return array<int, array> vedomost id => qatorlar */
    private function cachedRows(array $ids): array
    {
        if (empty($ids) || !Schema::hasTable('vedomost_jamlanma_rows')) {
            return [];
        }
        $out = [];
        foreach (array_chunk($ids, 1000) as $chunk) {
            foreach (DB::table('vedomost_jamlanma_rows')->whereIn('vedomost_submission_id', $chunk)->get(['vedomost_submission_id', 'rows']) as $c) {
                $rows = json_decode((string) $c->rows, true);
                if (is_array($rows)) {
                    $out[(int) $c->vedomost_submission_id] = $rows;
                }
            }
        }

        return $out;
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
