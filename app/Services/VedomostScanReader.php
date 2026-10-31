<?php

namespace App\Services;

use GdImage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Skaner qilingan vedomostni (12-shakl) Gemini orqali o'qiydi — Python
 * "vedomost_avto" dasturidagi usul:
 *
 *  - har sahifa rasmga aylantiriladi (pdftoppm) va N ta gorizontal bo'lakka
 *    bo'linadi; 2-bo'lakdan boshlab har biriga jadval sarlavhasi yopishtiriladi
 *    (model ustunlarni adashtirmasin);
 *  - model faqat KO'CHIRADI (hisoblamaydi), aniq o'qiy olmagan katakni "unclear"
 *    ga yozadi;
 *  - har qatorning ichki hisobi (% × vazn = ball, JB+MT+ON, YN) tekshiriladi;
 *    mos kelmasa sahifa boshqacha bo'linib QAYTA o'qiladi:
 *      qayta o'qish mos → tuzatildi; ikkalasi bir xil → xato vedomostning o'zida;
 *      ikkalasi farqli → katak "noaniq" (ko'z bilan tekshirish kerak);
 *  - natija fayl hash'i bo'yicha keshlanadi (qayta tekshirish limit sarflamaydi).
 *
 * pdftoppm bo'lmasa (server image hali qayta qurilmagan) — butun PDF bitta
 * so'rovda yuboriladi (bo'laklash va qayta o'qishsiz).
 */
class VedomostScanReader
{
    /** Prompt yoki sxema o'zgarsa oshiriladi — eski kesh ishlatilmaydi. */
    public const PROMPT_VER = 4;

    public const ROW_FIELDS = [
        'jb_pct', 'jb_ball', 'mt_pct', 'mt_ball', 'on_pct', 'on_ball',
        'jbmton_ball', 'jbmton_pct', 'oski_pct', 'oski_ball', 'test_pct', 'test_ball', 'yn',
    ];

    private const HEADER_FIELDS = [
        'fakultet', 'oquv_yili', 'yonalish', 'fan', 'kurs', 'semestr', 'guruh', 'soat', 'kredit',
        'maruzachi', 'yn_sana', 'dekan', 'kafedra_mudiri', 'shakl',
    ];

    private const PAIRS = [
        'jb' => ['jb_pct', 'jb_ball'],
        'mt' => ['mt_pct', 'mt_ball'],
        'on' => ['on_pct', 'on_ball'],
        'oski' => ['oski_pct', 'oski_ball'],
        'test' => ['test_pct', 'test_ball'],
    ];

    private ?int $submissionId = null;

    private ?string $lastModel = null;

    public function __construct(
        private VedomostGeminiClient $gemini,
        private VedomostAiSettings $settings
    ) {
    }

    public static function rendererAvailable(): bool
    {
        return (new \Symfony\Component\Process\ExecutableFinder())->find('pdftoppm') !== null;
    }

    /**
     * @return array{header: array, rows: array, signatures: array, weights: array, meta: array}
     */
    /**
     * @param  bool  $fresh  true — keshga qaramay AI'ga qaytadan o'qitish (xodim «Qayta tekshirish»ni bosganda);
     *                       natija baribir keshga yoziladi.
     */
    public function read(string $pdfPath, ?int $submissionId = null, ?int $expectedRows = null, bool $fresh = false): array
    {
        if (!is_file($pdfPath)) {
            throw new RuntimeException('Skaner PDF topilmadi.');
        }
        $this->submissionId = $submissionId;

        $bands = max(1, min(4, (int) $this->settings->get('bands')));
        $model = (string) $this->settings->get('model');
        $hash = sha1_file($pdfPath);
        // Rejim ham kalitda: pdftoppm keyin o'rnatilsa, butun-PDF rejimidagi eski natija qaytmasin.
        $images = self::rendererAvailable();
        $cacheFile = $this->cachePath(sha1(implode('|', ['v' . self::PROMPT_VER, $hash, $bands, $model, $images ? 'images' : 'pdf'])));

        if (!$fresh && is_file($cacheFile)) {
            $cached = json_decode((string) file_get_contents($cacheFile), true);
            if (is_array($cached)) {
                $cached['meta']['cached'] = true;
                VedomostAiProgress::log('PDF o‘qish natijasi keshdan olindi (AI chaqirilmadi)');

                return $cached;
            }
        }

        VedomostAiProgress::log($images ? "O‘qish rejimi: rasm, {$bands} bo‘lak · model {$model}" : "O‘qish rejimi: butun PDF (pdftoppm yo‘q) · model {$model}");
        $result = $images
            ? $this->readAsImages($pdfPath, $bands, $expectedRows)
            : $this->readAsPdf($pdfPath, $expectedRows);

        $result['meta'] += ['file_hash' => $hash, 'bands' => $bands, 'cached' => false, 'read_at' => now()->toDateTimeString()];

        File::ensureDirectoryExists(dirname($cacheFile));
        file_put_contents($cacheFile, json_encode($result, JSON_UNESCAPED_UNICODE));

        return $result;
    }

    // ───────────────────────── rasm rejimi ─────────────────────────

    private function readAsImages(string $pdfPath, int $bands, ?int $expectedRows): array
    {
        $dir = storage_path('app/vedomost-ai-tmp/' . uniqid('scan_', true));
        File::ensureDirectoryExists($dir);

        try {
            $proc = Process::timeout(180)->run(['pdftoppm', '-r', '200', '-png', $pdfPath, $dir . '/p']);
            if (!$proc->successful()) {
                throw new RuntimeException('PDF rasmga aylantirilmadi: ' . mb_substr($proc->errorOutput(), 0, 300));
            }
            $files = glob($dir . '/p-*.png') ?: [];
            natsort($files);
            $files = array_values($files);
            if (empty($files)) {
                throw new RuntimeException('PDF ichida sahifa topilmadi.');
            }

            $total = count($files);
            VedomostAiProgress::log("PDF → {$total} sahifa rasmga aylantirildi");
            $pages = [];
            $repaired = 0;
            foreach ($files as $i => $file) {
                $pn = $i + 1;
                $img = imagecreatefrompng($file);
                if (!$img) {
                    throw new RuntimeException("{$pn}-sahifa rasmini o'qib bo'lmadi.");
                }
                $head = $this->headerStrip($img);

                $res = $this->readSplit($img, $head, $pn, $total, $bands, 0.10);
                $again = null;
                $readAgain = function () use (&$again, $img, $head, $pn, $total, $bands) {
                    return $again ??= $this->readSplit($img, $head, $pn, $total, $bands + 1, 0.16);
                };
                [$res, $fixed] = $this->verifyPage($res, $readAgain);
                $repaired += $fixed;

                // Tushib qolgan qatorlar: raqamlashda bo'shliq bo'lsa (yoki oxirgi sahifada
                // talabalar soni kam bo'lsa) — boshqacha bo'linib qayta o'qilgan natijadan
                // yetishmagan qatorlarni qo'shamiz.
                $shortfall = $expectedRows !== null && $pn === $total
                    && (count($pages ? array_merge(...array_column($pages, 'rows')) : []) + count($res['rows'])) < $expectedRows;
                if ($this->gaps($res['rows'], $pn === 1) || $shortfall) {
                    VedomostAiProgress::log("{$pn}-sahifada qatorlar tushib qolgan" . ($shortfall ? ' (talabalar soni kam)' : '') . ' → qayta o‘qilmoqda', 'warn');
                    try {
                        $res['rows'] = $this->unionRows($res['rows'], $readAgain()['rows']);
                        $res['reread_gaps'] = true;
                    } catch (VedomostAiLimitException $e) {
                        throw $e;
                    } catch (\Throwable $e) {
                        Log::info('[VedomostScanReader] tushib qolgan qatorlar uchun qayta o\'qish muvaffaqiyatsiz: ' . $e->getMessage());
                    }
                }

                foreach ($res['rows'] as &$row) {
                    $row['page'] = $pn;
                }
                unset($row);
                $pages[] = $res;

                imagedestroy($img);
                imagedestroy($head);
                unset($readAgain, $again);
                gc_collect_cycles();
            }

            return $this->combine($pages, ['mode' => 'images', 'pages' => $total, 'repaired' => $repaired], $expectedRows);
        } finally {
            File::deleteDirectory($dir);
        }
    }

    private function readSplit(GdImage $img, GdImage $head, int $pn, int $total, int $n, float $overlap): array
    {
        $got = [];
        foreach ($this->splitBands($img, $n, $overlap) as $i => $band) {
            $desc = $n > 1 ? sprintf('strip %d of %d (top to bottom)', $i + 1, $n) : 'the whole page';
            $send = $i === 0 ? $band : $this->stackHeader($head, $band);
            $got[] = $this->callImage($send, $pn, $total, $desc, $i > 0, "{$pn}-sahifa" . ($n > 1 ? ', bo‘lak ' . ($i + 1) . "/{$n}" : ''));
            if ($send !== $band) {
                imagedestroy($send);
            }
            if ($band !== $img) {
                imagedestroy($band);
            }
        }

        return [
            'header' => $this->mergeHeaders(array_column($got, 'header')),
            'weights' => $this->mergeWeights(array_column($got, 'weights')),
            'signatures' => $this->mergeSignatures(array_column($got, 'signatures')),
            'rows' => $this->mergeRows(array_column($got, 'rows')),
        ];
    }

    private function callImage(GdImage $img, int $pn, int $total, string $part, bool $stacked, string $label = ''): array
    {
        ob_start();
        imagejpeg($img, null, 88);
        $jpeg = (string) ob_get_clean();

        $parts = [
            ['inlineData' => ['mimeType' => 'image/jpeg', 'data' => base64_encode($jpeg)]],
            ['text' => $this->prompt($pn, $total, $part, $stacked)],
        ];

        return $this->ask($parts, $label);
    }

    private function ask(array $parts, string $label = 'butun PDF'): array
    {
        $t0 = microtime(true);
        VedomostAiProgress::log("Gemini so‘rovi yuborildi: {$label}");
        try {
            $res = $this->gemini->generateJson($parts, $this->schema(), null, $this->submissionId);
        } catch (\Throwable $e) {
            VedomostAiProgress::log("Gemini xatosi ({$label}): " . mb_substr($e->getMessage(), 0, 300), 'error');
            throw $e;
        }
        $this->lastModel = $res['model'];
        $out = $this->normalize($res['data']);
        VedomostAiProgress::log(sprintf('Gemini javobi: %s · %d qator · %.1f s · %s', $label, count($out['rows'] ?? []), microtime(true) - $t0, $res['model']), 'ok');

        return $out;
    }

    // ───────────────────────── PDF rejimi (zaxira) ─────────────────────────

    private function readAsPdf(string $pdfPath, ?int $expectedRows): array
    {
        $parts = [
            ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($pdfPath))]],
            ['text' => $this->prompt(1, 1, 'the whole document (all pages)', false)],
        ];
        $res = $this->ask($parts);
        foreach ($res['rows'] as &$row) {
            $row['page'] = 1;
        }
        unset($row);

        // Ichki hisob mos kelmasa yoki qatorlar tushib qolsa — butun PDF qayta o'qiladi.
        $again = null;
        $readAgain = function () use (&$again, $parts) {
            if ($again === null) {
                $again = $this->ask($parts);
                foreach ($again['rows'] as &$row) {
                    $row['page'] = 1;
                }
            }

            return $again;
        };
        [$res, $fixed] = $this->verifyPage($res, $readAgain);
        if ($this->gaps($res['rows'], true) || ($expectedRows !== null && count($res['rows']) < $expectedRows)) {
            try {
                $res['rows'] = $this->unionRows($res['rows'], $readAgain()['rows']);
            } catch (VedomostAiLimitException $e) {
                throw $e;
            } catch (\Throwable $e) {
                Log::info('[VedomostScanReader] qayta o\'qish muvaffaqiyatsiz: ' . $e->getMessage());
            }
        }

        return $this->combine([$res], ['mode' => 'pdf', 'pages' => null, 'repaired' => $fixed], $expectedRows);
    }

    /** Raqamlashda (№) bo'shliq bormi. */
    private function gaps(array $rows, bool $firstPage): bool
    {
        return !empty($this->missingNrs($rows, $firstPage));
    }

    private function missingNrs(array $rows, bool $fromOne): array
    {
        $nrs = array_values(array_unique(array_filter(array_map(fn ($r) => $r['nr'] ?? null, $rows), fn ($x) => is_int($x) && $x > 0)));
        if (empty($nrs)) {
            return [];
        }
        sort($nrs);
        $start = $fromOne ? 1 : $nrs[0];

        return array_values(array_diff(range($start, end($nrs)), $nrs));
    }

    /** Birinchi o'qishda yo'q qatorlarni ikkinchi o'qishdan qo'shadi (mavjudlariga tegmaydi). */
    private function unionRows(array $rows, array $more): array
    {
        $have = array_flip(array_filter(array_map(fn ($r) => $r['nr'] ?? null, $rows)));
        foreach ($more as $m) {
            $dup = isset($have[$m['nr'] ?? -1]);
            foreach ($rows as $r) {
                if ($dup || $this->sameRow($r, $m)) {
                    $dup = true;
                    break;
                }
            }
            if (!$dup) {
                $m['trace'][] = "birinchi o'qishda tushib qolgan — qayta o'qishdan olindi";
                $rows[] = $m;
            }
        }
        usort($rows, fn ($a, $b) => (int) ($a['nr'] ?? 0) <=> (int) ($b['nr'] ?? 0));

        return $rows;
    }

    // ───────────────────────── tekshirish va qayta o'qish ─────────────────────────

    /**
     * @return array{0: array, 1: int}
     */
    private function verifyPage(array $res, callable $readAgain): array
    {
        $ratios = $this->pageRatios($res['rows']);
        $bad = array_filter($res['rows'], fn ($r) => !empty($this->rowProblems($r, $ratios)));
        if (empty($bad)) {
            return [$res, 0];
        }
        VedomostAiProgress::log(count($bad) . ' qatorda ichki hisob mos emas (' . implode(', ', array_map(fn ($r) => $r['nr'] ?? '?', $bad)) . '-qator) → boshqacha bo‘lib qayta o‘qilmoqda', 'warn');

        try {
            $res2 = $readAgain();
        } catch (VedomostAiLimitException $e) {
            throw $e;
        } catch (\Throwable $e) {
            Log::info('[VedomostScanReader] qayta o\'qish muvaffaqiyatsiz: ' . $e->getMessage());
            $res2 = ['rows' => []];
        }

        $fixed = 0;
        $out = [];
        foreach ($res['rows'] as $r) {
            $pr = $this->rowProblems($r, $ratios);
            if (empty($pr)) {
                $out[] = $r;
                continue;
            }
            $r['trace'][] = 'ichki hisob mos emas: ' . implode('; ', array_column($pr, 0));
            $r['problems'] = array_column($pr, 0);
            $prFields = array_merge(...array_column($pr, 1));

            $r2 = null;
            foreach ($res2['rows'] as $x) {
                if ($this->sameRow($r, $x)) {
                    $r2 = $x;
                    break;
                }
            }

            if ($r2 === null) {
                $r['trace'][] = "qayta o'qishda bu qator topilmadi → noaniq";
                $r['unclear'] = array_values(array_unique(array_merge($r['unclear'], $prFields)));
                $out[] = $r;
            } elseif (empty($this->rowProblems($r2, $ratios))) {
                $r2['repaired'] = true;
                $r2['problems'] = [];
                $r2['first'] = array_intersect_key($r, array_flip(array_merge(self::ROW_FIELDS, ['id', 'fio', 'ects'])));
                $r2['trace'] = array_merge($r['trace'], ["qayta o'qish mos keldi → tuzatildi"]);
                $out[] = $r2;
                $fixed++;
            } else {
                $diff = array_values(array_filter(self::ROW_FIELDS, fn ($f) => $this->differs($r[$f] ?? null, $r2[$f] ?? null)));
                if (empty($diff)) {
                    $r['verified'] = true;
                    $r['trace'][] = "qayta o'qish aynan bir xil → xato vedomostning o'zida";
                } else {
                    $r['unclear'] = array_values(array_unique(array_merge($r['unclear'], $prFields, $diff)));
                    $r['trace'][] = "qayta o'qish farq qildi (" . implode(', ', $diff) . ') → noaniq';
                }
                $out[] = $r;
            }
        }

        $res['rows'] = $out;

        return [$res, $fixed];
    }

    /**
     * Har nazorat turi uchun ball/foiz nisbati — vedomostning o'z vaznlari. Vazn butun foiz
     * bo'lgani uchun nisbat 0.01 qadamli: mediana atrofidagi nomzodlardan eng ko'p qator
     * (aniq yoki butun songacha yaxlitlangan — 12a/12b, 4–5 kurs) mos keladigani olinadi.
     * Oddiy mediana yaxlitlangan ballarda 0.5 o'rniga 0.505 berib, soxta «mos emas» chiqarardi.
     */
    public function pageRatios(array $rows): array
    {
        $out = [];
        foreach (self::PAIRS as $key => [$p, $b]) {
            $xs = [];
            $pairs = [];
            foreach ($rows as $r) {
                $pv = self::num($r[$p] ?? null);
                $bv = self::num($r[$b] ?? null);
                if ($pv && $bv !== null && $bv > 0) {
                    $xs[] = $bv / $pv;
                    $pairs[] = [$pv, $bv];
                }
            }
            $med = self::median($xs);
            if ($med === null) {
                $out[$key] = null;
                continue;
            }
            $best = $med;
            $bestHits = -1;
            foreach (array_unique([floor($med * 100), round($med * 100), ceil($med * 100)]) as $c) {
                $rt = $c / 100;
                $hits = count(array_filter($pairs, fn ($q) => abs($q[1] - $q[0] * $rt) <= 0.11 || abs($q[1] - floor($q[0] * $rt + 0.5)) < 0.001));
                if ($c > 0 && $hits > $bestHits) {
                    [$best, $bestHits] = [$rt, $hits];
                }
            }
            $out[$key] = $best;
        }
        $xs = [];
        foreach ($rows as $r) {
            $bv = self::num($r['jbmton_ball'] ?? null);
            $pv = self::num($r['jbmton_pct'] ?? null);
            if ($pv && $bv) {
                $xs[] = $bv / $pv;
            }
        }
        $out['jbmton'] = self::median($xs);

        return $out;
    }

    /**
     * Qatorning o'z ichidagi arifmetik mos kelmasliklar.
     *
     * @return array<int, array{0: string, 1: array}>
     */
    /**
     * YN manfiy kod bo'lishi kerak (baho «kelmadi» → -1; «qo'yilmadi» yoki ECTS FX → -2/-3),
     * lekin musbat yoki 0 o'qilgan. Odatda kulrang bo'yalgan qatorda minus belgisi yo'qolganda.
     */
    public static function ynConflict(array $row): bool
    {
        $yn = self::num($row['yn'] ?? null);
        if ($yn === null || $yn < 0) {
            return false;
        }
        $baho = preg_replace('/[^a-z]/', '', mb_strtolower(VedomostNames::latin((string) ($row['baho'] ?? ''))));
        $ects = strtoupper(trim((string) ($row['ects'] ?? '')));

        return str_contains($baho, 'kelmadi') || str_contains($baho, 'qoyilmadi') || $ects === 'FX';
    }

    public function rowProblems(array $row, array $ratios): array
    {
        $p = [];
        foreach (self::PAIRS as $key => [$pf, $bf]) {
            $w = $ratios[$key] ?? null;
            $pv = self::num($row[$pf] ?? null);
            $bv = self::num($row[$bf] ?? null);
            if (in_array($key, ['oski', 'test', 'jb', 'mt'], true) && $pv !== null && $pv < 60 && $bv !== null && abs($bv) < 0.001) {
                continue; // o'tmagan: foiz bor, ball 0 — vedomostda shunday yoziladi
            }
            // Ball foiz × vazn ga teng yoki (4–5 kurs qayta topshirish shakllarida) butun songacha yaxlitlangan.
            if ($w && $pv !== null && $bv !== null && abs($bv - $pv * $w) > 0.11 && abs($bv - floor($pv * $w + 0.5)) > 0.001) {
                $p[] = ["{$pf} va {$bf} o'zaro mos emas", [$pf, $bf]];
            }
        }

        $jb = self::num($row['jb_ball'] ?? null);
        $mt = self::num($row['mt_ball'] ?? null);
        $on = self::num($row['on_ball'] ?? null) ?? 0.0;
        $jm = self::num($row['jbmton_ball'] ?? null);
        if ($jb !== null && $mt !== null && $jm !== null && $jm > 0 && abs($jb + $mt + $on - $jm) > 0.51) {
            $p[] = ["JB+MT+ON ball yig'indisi mos emas", ['jb_ball', 'mt_ball', 'on_ball', 'jbmton_ball']];
        }

        $yn = self::num($row['yn'] ?? null);
        if ($yn !== null && $yn > 0 && $jm !== null
            && (self::num($row['oski_pct'] ?? null) !== null || self::num($row['test_pct'] ?? null) !== null)) {
            $exp = $jm;
            foreach (['oski', 'test'] as $key) {
                $pv = self::num($row[self::PAIRS[$key][0]] ?? null);
                $bv = self::num($row[self::PAIRS[$key][1]] ?? null);
                if ($pv !== null && $pv < 60 && $bv !== null && abs($bv) < 0.001) {
                    continue;
                }
                if ($bv !== null) {
                    $exp += $bv;
                } elseif ($pv !== null && !empty($ratios[$key])) {
                    $exp += $pv * $ratios[$key];
                }
            }
            if (abs($yn - floor($exp + 0.5 + 1e-9)) >= 1.5) {
                $p[] = ["YN ball yig'indisiga mos emas", ['yn', 'jbmton_ball', 'oski_ball', 'test_ball']];
            }
        }

        if (self::ynConflict($row)) {
            $p[] = ["YN baho/ECTS bilan mos emas (kelmadi → -1, qo'yilmadi/FX → -2 yoki -3)", ['yn', 'ects', 'baho']];
        }

        $e = strtoupper(trim((string) ($row['ects'] ?? '')));
        if ($e !== '' && !preg_match('/^(A|B\+?|C\+?|D|E|FX|F)$/', $e)) {
            $p[] = ["ECTS harfi emas — ustunlar siljigan bo'lishi mumkin", ['ects', 'yn']];
        }

        return $p;
    }

    // ───────────────────────── birlashtirish ─────────────────────────

    private function combine(array $pages, array $meta, ?int $expectedRows = null): array
    {
        $rows = [];
        foreach ($pages as $p) {
            foreach ($p['rows'] as $r) {
                $rows[] = $r;
            }
        }
        $rows = $this->mergeRows([$rows]);

        $weights = $this->mergeWeights(array_column($pages, 'weights'));
        $ratios = $this->pageRatios($rows);
        foreach (['jb', 'mt', 'on', 'oski', 'test'] as $k) {
            if (empty($weights[$k]) && !empty($ratios[$k])) {
                $weights[$k] = (int) round($ratios[$k] * 100);
                $meta['weights_from_ratio'][] = $k;
            }
        }

        $meta['model'] = $this->lastModel;
        $meta['missing_nrs'] = $this->missingNrs($rows, true);
        $meta['rows_read'] = count($rows);
        $meta['rows_expected'] = $expectedRows;
        $meta['unclear_cells'] = array_sum(array_map(fn ($r) => count($r['unclear']), $rows));

        return [
            'header' => $this->mergeHeaders(array_column($pages, 'header')),
            'weights' => $weights,
            'signatures' => $this->mergeSignatures(array_column($pages, 'signatures')),
            'rows' => $rows,
            'meta' => $meta,
        ];
    }

    private function mergeRows(array $bandRows): array
    {
        $out = [];
        foreach ($bandRows as $rows) {
            foreach ($rows as $r) {
                $dup = null;
                foreach ($out as $i => $o) {
                    if ($this->sameRow($o, $r)) {
                        $dup = $i;
                        break;
                    }
                }
                if ($dup === null) {
                    $out[] = $r;
                } elseif ($this->rowQuality($r) > $this->rowQuality($out[$dup])) {
                    $out[$dup] = $r;
                }
            }
        }

        usort($out, fn ($a, $b) => [($a['page'] ?? 0), (int) ($a['nr'] ?? 0)] <=> [($b['page'] ?? 0), (int) ($b['nr'] ?? 0)]);

        return $out;
    }

    private function rowQuality(array $r): int
    {
        $filled = count(array_filter(self::ROW_FIELDS, fn ($f) => ($r[$f] ?? '') !== ''));

        return $filled * 10 - count($r['unclear'] ?? []) * 5;
    }

    private function sameRow(array $a, array $b): bool
    {
        $ia = self::digits($a['id'] ?? '');
        $ib = self::digits($b['id'] ?? '');
        if ($ia !== '' && $ia === $ib) {
            return true;
        }
        if (($a['nr'] ?? null) !== null && ($a['nr'] ?? null) === ($b['nr'] ?? null) && ($a['page'] ?? 0) === ($b['page'] ?? 0)) {
            return VedomostNames::similarity((string) ($a['fio'] ?? ''), (string) ($b['fio'] ?? '')) >= 0.7;
        }

        return false;
    }

    private function differs($x, $y): bool
    {
        $a = self::num($x);
        $b = self::num($y);
        if ($a === null || $b === null) {
            return trim((string) $x) !== trim((string) $y);
        }

        return abs($a - $b) > 0.01;
    }

    private function mergeHeaders(array $headers): array
    {
        $out = array_fill_keys(self::HEADER_FIELDS, null);
        $out['amaliyot'] = [];
        foreach ($headers as $h) {
            foreach (self::HEADER_FIELDS as $f) {
                if (($out[$f] === null || $out[$f] === '') && ($h[$f] ?? null) !== null && trim((string) $h[$f]) !== '') {
                    $out[$f] = trim((string) $h[$f]);
                }
            }
            foreach ((array) ($h['amaliyot'] ?? []) as $a) {
                $a = trim((string) $a);
                if ($a !== '' && !in_array($a, $out['amaliyot'], true)) {
                    $out['amaliyot'][] = $a;
                }
            }
        }

        return $out;
    }

    private function mergeWeights(array $list): array
    {
        $out = ['jb' => null, 'mt' => null, 'on' => null, 'oski' => null, 'test' => null];
        foreach ($list as $w) {
            foreach ($out as $k => $v) {
                $n = self::num($w[$k] ?? null);
                if ($v === null && $n !== null) {
                    $out[$k] = (int) round($n);
                }
            }
        }

        return $out;
    }

    private function mergeSignatures(array $list): array
    {
        $out = ['oqituvchi' => false, 'dekan' => false, 'kafedra_mudiri' => false, 'muhr' => false];
        foreach ($list as $s) {
            foreach ($out as $k => $v) {
                $out[$k] = $v || !empty($s[$k]);
            }
        }

        return $out;
    }

    private function normalize(array $data): array
    {
        $rows = [];
        foreach (($data['rows'] ?? []) as $r) {
            if (!is_array($r)) {
                continue;
            }
            $row = [
                'nr' => isset($r['nr']) && is_numeric($r['nr']) ? (int) $r['nr'] : null,
                'fio' => trim((string) ($r['fio'] ?? '')),
                'id' => trim((string) ($r['id'] ?? '')),
                'ects' => trim((string) ($r['ects'] ?? '')),
                'baho' => trim((string) ($r['baho'] ?? '')),
                'unclear' => array_values(array_filter(array_map(fn ($u) => $this->fieldAlias((string) $u), (array) ($r['unclear'] ?? [])))),
                'trace' => [],
            ];
            foreach (self::ROW_FIELDS as $f) {
                $row[$f] = trim((string) ($r[$f] ?? ''));
            }
            if ($row['fio'] === '' && $row['id'] === '') {
                continue;
            }
            // YN bo'sh qolsa — ECTS dan oldingi oxirgi son (Python prepare_pdf_entry).
            $nums = array_values(array_filter(array_map(fn ($x) => self::num($x), (array) ($r['nums'] ?? [])), fn ($x) => $x !== null));
            if ($row['yn'] === '' && !empty($nums)) {
                $row['yn'] = (string) end($nums);
                $row['unclear'][] = 'yn';
            }
            $rows[] = $row;
        }

        return [
            'header' => is_array($data['header'] ?? null) ? $data['header'] : [],
            'weights' => is_array($data['weights'] ?? null) ? $data['weights'] : [],
            'signatures' => is_array($data['signatures'] ?? null) ? $data['signatures'] : [],
            'rows' => $rows,
        ];
    }

    private function fieldAlias(string $f): string
    {
        $f = strtolower(trim($f));

        return [
            'jn_pct' => 'jb_pct', 'jn_ball' => 'jb_ball', 'oske_pct' => 'oski_pct', 'oske_ball' => 'oski_ball',
            'jnmt_ball' => 'jbmton_ball', 'jnmt_pct' => 'jbmton_pct',
        ][$f] ?? $f;
    }

    // ───────────────────────── rasm amallari (GD) ─────────────────────────

    /** Jadvalning yuqori chizig'i (balandlikka nisbatan 0..1) yoki null. */
    private function findTableTop(GdImage $img): ?float
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $step = 3;
        for ($y = (int) ($h * 0.08); $y < (int) ($h * 0.55); $y++) {
            $dark = 0;
            $total = 0;
            for ($x = 0; $x < $w; $x += $step) {
                $rgb = imagecolorat($img, $x, $y);
                $lum = ((($rgb >> 16) & 0xFF) * 299 + (($rgb >> 8) & 0xFF) * 587 + ($rgb & 0xFF) * 114) / 1000;
                $total++;
                if ($lum < 170) {
                    $dark++;
                }
            }
            if ($total > 0 && $dark / $total >= 0.55) {
                return $y / $h;
            }
        }

        return null;
    }

    private function headerStrip(GdImage $img): GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $top = $this->findTableTop($img) ?? 0.236;
        $a = max(0.0, $top - 0.006);
        $b = min(1.0, $top + 0.092);

        return $this->crop($img, (int) ($w * 0.04), (int) ($h * $a), (int) ($w * 0.92), (int) ($h * ($b - $a)));
    }

    /** @return GdImage[] */
    private function splitBands(GdImage $img, int $n, float $overlap): array
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $x0 = (int) ($w * 0.04);
        $cw = (int) ($w * 0.92);
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = max(0.0, $i / $n - ($i > 0 ? $overlap / 2 : 0));
            $b = min(1.0, ($i + 1) / $n + ($i < $n - 1 ? $overlap / 2 : 0));
            // Bitta bo'lak — sahifaning o'zi (nusxa olinmaydi: 200 dpi sahifa GD'da ~15 MB).
            $out[] = $n === 1 ? $img : $this->crop($img, $x0, (int) ($h * $a), $cw, (int) ($h * ($b - $a)));
        }

        return $out;
    }

    private function stackHeader(GdImage $head, GdImage $strip): GdImage
    {
        $bar = 22;
        $w = max(imagesx($head), imagesx($strip));
        $out = imagecreatetruecolor($w, imagesy($head) + $bar + imagesy($strip));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $head, 0, 0, 0, 0, imagesx($head), imagesy($head));
        imagefilledrectangle($out, 0, imagesy($head), $w, imagesy($head) + $bar - 1, imagecolorallocate($out, 220, 0, 0));
        imagecopy($out, $strip, 0, imagesy($head) + $bar, 0, 0, imagesx($strip), imagesy($strip));

        return $out;
    }

    private function crop(GdImage $img, int $x, int $y, int $w, int $h): GdImage
    {
        $out = imagecreatetruecolor(max(1, $w), max(1, $h));
        imagecopy($out, $img, 0, 0, $x, $y, $w, $h);

        return $out;
    }

    // ───────────────────────── prompt va sxema ─────────────────────────

    private function prompt(int $pn, int $total, string $part, bool $stacked): string
    {
        $stack = $stacked
            ? "\nTHIS IMAGE HAS TWO PARTS separated by a thick RED horizontal bar. PART 1 (above the red bar) only repeats the table's column headings so you can see which column is which. PART 2 (below the red bar) is a strip of student rows. Read student rows ONLY from PART 2 and ignore any row fragment in PART 1 and the red bar itself. Header fields are not in this image: leave them null.\n"
            : '';

        return <<<TXT
You are reading a scanned Uzbek medical-university grading sheet ("BAHOLASH QAYDNOMASI"; form 12, 12a, 12b or their "qo'shimcha" variants). The image is {$part} of page {$pn} of {$total}. Neighbouring images overlap, so a table row can appear in two images.{$stack}
Transcribe exactly what is PRINTED or WRITTEN. Do not calculate, correct or guess values from other columns. Copy every student's name and ID exactly as printed, even if it looks wrong. If a value is hard to read, give your best reading and put that field's name in "unclear".
A table row that is cut off by the top or bottom edge of this image must be skipped (the neighbouring image shows it whole). Skip empty numbered rows and the "O'rtacha foiz" row.
FIRST read the table's own column headings — the layout differs between subjects: some sheets have OSKE columns and no TEST columns, some have TEST and no OSKE, some have both, some have neither. Map every number by the heading above it, never by position. An EMPTY column (for example ON) still occupies its place: never shift later numbers into it or out of it. A field whose column does not exist on this sheet, or whose cell is empty, is "".
Usual columns: № | Talabaning familiyasi, ismi-sharifi | Talabaning ID raqami | JB % | JB ball | MT % | MT ball | ON % | ON ball | JB+MT+ON ball | JB+MT+ON % | OSKE % | OSKE ball | TEST % | TEST ball | O'zlashtirish ko'rsatkichi | ECTS | signature | grade word.
VERY IMPORTANT: "yn" is the final grade in the column "O'zlashtirish ko'rsatkichi", which stands immediately LEFT of the ECTS letter (A, B+, B, C+, C, D, E, F, FX). It can be -1 (kelmagan), -2 or -3. These negative codes are common on rows shaded grey: look carefully for the minus sign — when the grade word is "kelmadi" the value is -1, when it is "qo'yilmadi" (ECTS FX) it is -2 or -3, never 0. Always fill "yn" from that column; never put that number into test_pct or oski_pct. test_pct/test_ball come only from the column headed TEST, oski_pct/oski_ball only from the column headed OSKE. "baho" is the grade word in the last column (a'lo, yaxshi, o'rta, qoniqarsiz, kelmadi...).
Also give "nums": every number printed in the row after the ID, left to right, exactly as printed (empty cells skipped).
Numbers: write them as printed ("86,0" → "86,0"; "76%" → "76").
"weights": the maximum points printed in the heading row under each control type (the small numbers under JB, MT, ON, OSKE, TEST, e.g. 50, 20, 0, 0, 30). "" if not visible.
Header (top of the page) and footer (bottom): fakultet, oquv_yili, yonalish = "Ta'lim yo'nalishi", fan = "Fan(modul) nomi", kurs, semestr, guruh, soat = "umumiy soat" number, kredit number, maruzachi = "Ma'ruzachi", amaliyot = every "Amaliyot o'qituvchisi" name, yn_sana = "YaN o'tkazilgan sana", dekan = the name printed in the "Fakultet dekani" block, kafedra_mudiri = the name printed in the "Kafedra mudiri" block, shakl = the form name in the TOP-RIGHT corner exactly as printed (e.g. "12- shakl", "12A- shakl", "12b- shakl", "12-q'oshimcha shakl", "12A.QO'SHIMCHA shakl"). In some forms the student name is followed by the student's group (e.g. "AMINOVA SHAXZODA ASLIDDINOVNA 21-02b"): copy it as printed. A header field not visible in this image is "".
"signatures": true only if a REAL hand-drawn signature (pen strokes) is visible: oqituvchi = in the student rows' signature column or next to the teacher; dekan = next to "Fakultet dekani"; kafedra_mudiri = next to "Kafedra mudiri"; muhr = a round official stamp/seal anywhere in the footer. Printed words like "imzo", "M.O'", "F.I.Sh" are NOT signatures. If that part of the page is not in this image, false.
Reply with JSON only.
TXT;
    }

    private function schema(): array
    {
        $s = ['type' => 'STRING'];
        $b = ['type' => 'BOOLEAN'];
        $obj = fn (array $props, ?array $required = null) => [
            'type' => 'OBJECT',
            'properties' => $props,
            'required' => $required ?? array_keys($props),
            'propertyOrdering' => array_keys($props),
        ];

        $rowProps = ['nr' => ['type' => 'INTEGER', 'nullable' => true], 'fio' => $s, 'id' => $s];
        foreach (self::ROW_FIELDS as $f) {
            $rowProps[$f] = $s;
        }
        $rowProps += [
            'ects' => $s,
            'baho' => $s,
            'nums' => ['type' => 'ARRAY', 'items' => $s],
            'unclear' => ['type' => 'ARRAY', 'items' => $s],
        ];

        $headerProps = array_fill_keys(self::HEADER_FIELDS, $s);
        $headerProps['amaliyot'] = ['type' => 'ARRAY', 'items' => $s];

        return $obj([
            'header' => $obj($headerProps),
            'weights' => $obj(['jb' => $s, 'mt' => $s, 'on' => $s, 'oski' => $s, 'test' => $s]),
            'signatures' => $obj(['oqituvchi' => $b, 'dekan' => $b, 'kafedra_mudiri' => $b, 'muhr' => $b]),
            'rows' => ['type' => 'ARRAY', 'items' => $obj($rowProps)],
        ]);
    }

    // ───────────────────────── yordamchilar ─────────────────────────

    private function cachePath(string $key): string
    {
        return storage_path('app/vedomost-ai-cache/' . substr($key, 0, 2) . '/' . $key . '.json');
    }

    /** "86,0" → 86.0; "76%" → 76; bo'sh/yaroqsiz → null. */
    public static function num($v): ?float
    {
        if ($v === null) {
            return null;
        }
        if (is_int($v) || is_float($v)) {
            return (float) $v;
        }
        $s = str_replace([',', '%', ' '], ['.', '', ''], trim((string) $v));
        if ($s === '' || $s === '-' || $s === '—' || !is_numeric($s)) {
            return null;
        }

        return (float) $s;
    }

    public static function digits($s): string
    {
        return preg_replace('/\D+/', '', (string) $s);
    }

    private static function median(array $xs): ?float
    {
        if (empty($xs)) {
            return null;
        }
        sort($xs);

        return $xs[intdiv(count($xs), 2)];
    }
}
