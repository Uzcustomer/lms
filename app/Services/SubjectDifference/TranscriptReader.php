<?php

namespace App\Services\SubjectDifference;

use App\Services\VedomostGeminiClient;
use Illuminate\Support\Facades\Process;
use RuntimeException;

/**
 * Transkript PDF(lar)ini qatorlarga aylantiradi.
 *
 *  - PDF'da matn qatlami bo'lsa: `pdftotext` + qoida bilan parse (tez, bepul)
 *    VA Gemini — ikkala natija saqlanadi, sahifada solishtiriladi;
 *  - skaner (rasm) bo'lsa: faqat Gemini.
 *
 * Har bir fayl alohida o'qiladi (asosiy transkript + qo'shimcha ko'chirma), qatorlar
 * birlashtiriladi va har biriga `file` indeksi yoziladi.
 */
class TranscriptReader
{
    /** Shundan kam harf bo'lsa — matn qatlami yo'q (skaner) deb hisoblanadi. */
    private const MIN_TEXT_LETTERS = 200;

    public function __construct(
        private TextTranscriptParser $parser,
        private VedomostGeminiClient $gemini,
    ) {
    }

    public static function aiConfigured(): bool
    {
        return VedomostGeminiClient::isConfigured();
    }

    /**
     * @param  array<int, array{path: string, name: string}>  $files  absolyut yo'llar
     * @return array{text: array|null, ai: array|null, ai_error: string|null, files: array, header: array}
     */
    public function read(array $files): array
    {
        $text = null;
        $ai = null;
        $aiError = null;
        $fileMeta = [];
        $header = [];

        foreach (array_values($files) as $fi => $f) {
            $raw = $this->pdfText($f['path']);
            $hasText = $this->letters($raw) >= self::MIN_TEXT_LETTERS;
            $meta = ['name' => $f['name'], 'has_text' => $hasText, 'text_rows' => null, 'ai_rows' => null];

            if ($hasText) {
                $parsed = $this->tag($this->parser->parse($raw), $fi);
                $text = array_merge($text ?? [], $parsed);
                $meta['text_rows'] = count($parsed);
            }

            if (self::aiConfigured()) {
                try {
                    $res = $this->readWithAi($f['path']);
                    $ai = array_merge($ai ?? [], $this->tag($res['rows'], $fi));
                    $meta['ai_rows'] = count($res['rows']);
                    $meta['ai_model'] = $res['model'];
                    $header = $header ?: array_filter($res['header']);
                } catch (\Throwable $e) {
                    $aiError = mb_substr($e->getMessage(), 0, 500);
                    $meta['ai_error'] = $aiError;
                }
            } elseif (!$hasText) {
                $aiError = 'PDF skaner qilingan (matn qatlami yo\'q), Gemini API kaliti esa sozlanmagan.';
            }

            $fileMeta[] = $meta;
        }

        if ($text === null && $ai === null) {
            throw new RuntimeException($aiError ?: 'Transkriptdan birorta ham fan o\'qilmadi.');
        }

        return ['text' => $text, 'ai' => $ai, 'ai_error' => $aiError, 'files' => $fileMeta, 'header' => $header];
    }

    private function readWithAi(string $path): array
    {
        // Gemini inline so'rov chegarasi ~20 MB (base64 bilan) — katta skanlar uchun aniq xabar.
        if (filesize($path) > 14 * 1024 * 1024) {
            throw new RuntimeException('PDF juda katta (' . round(filesize($path) / 1048576, 1) . ' MB) — AI uchun 14 MB gacha. Faqat transkript sahifalarini yuklang yoki skan sifatini pasaytiring.');
        }
        $parts = [
            ['inlineData' => ['mimeType' => 'application/pdf', 'data' => base64_encode((string) file_get_contents($path))]],
            ['text' => $this->prompt()],
        ];
        $res = $this->gemini->generateJson($parts, $this->schema(), null, null, 'farq_transcript');
        $data = $res['data'];

        $rows = [];
        foreach ($data['rows'] ?? [] as $r) {
            $name = trim((string) ($r['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $rows[] = [
                'semester' => self::intOrNull($r['semester'] ?? null),
                'name' => $name,
                'hours' => self::intOrNull($r['hours'] ?? null),
                'credit' => self::numOrNull($r['credit'] ?? null),
                'ball' => self::numOrNull($r['ball'] ?? null),
                'grade' => self::intOrNull($r['grade'] ?? null),
                'grade_text' => ($t = trim((string) ($r['grade_text'] ?? ''))) !== '' ? $t : null,
            ];
        }

        return [
            'rows' => $rows,
            'model' => $res['model'],
            'header' => [
                'student_name' => trim((string) ($data['student_name'] ?? '')),
                'institution' => trim((string) ($data['institution'] ?? '')),
                'specialty' => trim((string) ($data['specialty'] ?? '')),
                'grading_scale' => trim((string) ($data['grading_scale'] ?? '')),
            ],
        ];
    }

    private function prompt(): string
    {
        return <<<'TXT'
You are transcribing a university academic transcript (Uzbek "Akademik ma'lumotnoma", "Transcript of Academic Records") or an extract from a student's record book ("Reyting daftarchasidan ko'chirma"). The document may be a scanned image.

Copy EVERY course row exactly as written. Do not calculate, do not skip, do not merge rows.
For each row return:
- semester: the semester number the row belongs to (from the nearest "N-semestr" / "VIII-semester" heading above it; Roman numerals → integer). null if not shown.
- name: the course name in UZBEK only, as printed. If the row is bilingual "Uzbek / English", return only the part before " / ". Keep punctuation.
- hours: total load in hours (the first number of "Yuklama / Kredit", e.g. 120 in "120 / 4"). null if absent.
- credit: credits (second number of "Yuklama / Kredit", e.g. 4). null if absent.
- ball: the score out of 100 (first number of "Ball / Baho", e.g. 72 in "72 / 4"). null if absent.
- grade: the grade on the 2–5 scale as written (second number of "Ball / Baho"). null if absent.
- grade_text: a grade written in words if present (e.g. "a'lo", "yaxshi", "qoniqarli"), else empty string.

Also return: student_name (full name of the student), institution (issuing university), specialty (yo'nalish), grading_scale (the grading scale legend text if printed, e.g. "5 = 90-100, 4 = 70-89, 3 = 60-69"), each empty string if absent.
Ignore totals rows (Jami/Total, GPA), signatures, stamps and QR codes.
TXT;
    }

    private function schema(): array
    {
        $s = ['type' => 'STRING'];
        $n = ['type' => 'NUMBER', 'nullable' => true];
        $i = ['type' => 'INTEGER', 'nullable' => true];
        $obj = fn (array $props) => [
            'type' => 'OBJECT',
            'properties' => $props,
            'required' => array_keys($props),
            'propertyOrdering' => array_keys($props),
        ];

        return $obj([
            'student_name' => $s,
            'institution' => $s,
            'specialty' => $s,
            'grading_scale' => $s,
            'rows' => ['type' => 'ARRAY', 'items' => $obj([
                'semester' => $i,
                'name' => $s,
                'hours' => $i,
                'credit' => $n,
                'ball' => $n,
                'grade' => $i,
                'grade_text' => $s,
            ])],
        ]);
    }

    private function pdfText(string $path): string
    {
        try {
            $proc = Process::timeout(60)->run(['pdftotext', '-layout', $path, '-']);

            return $proc->successful() ? (string) $proc->output() : '';
        } catch (\Throwable $e) {
            return '';
        }
    }

    private function letters(string $text): int
    {
        return (int) preg_match_all('/\p{L}/u', $text);
    }

    private function tag(array $rows, int $file): array
    {
        return array_map(fn ($r) => $r + ['file' => $file], $rows);
    }

    private static function intOrNull($v): ?int
    {
        return is_numeric($v) ? (int) $v : null;
    }

    private static function numOrNull($v): ?float
    {
        if (is_string($v)) {
            $v = str_replace(',', '.', $v);
        }

        return is_numeric($v) ? (float) $v : null;
    }
}
