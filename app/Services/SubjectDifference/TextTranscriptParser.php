<?php

namespace App\Services\SubjectDifference;

/**
 * Matn qatlami bor (skaner emas) transkriptni AI'siz o'qiydi — `pdftotext -layout`
 * natijasidan jadval qatorlarini ajratadi.
 *
 * HEMIS akademik ma'lumotnomasidagi qator ko'rinishi:
 *     12  Odam anatomiyasi / Human anatomy            360 / 12        72 / 4
 * Semestr sarlavhasi:  "3-semestr" (yoki "VIII- semester").
 * Uzun nom bir necha qatorga bo'linsa, raqamsiz qatorlar oldingi/keyingi qatorga
 * qo'shiladi.
 *
 * Bu "tezkor yo'l": format boshqacha bo'lsa kam qator topadi — sahifada AI natijasi
 * bilan solishtirilib ko'rsatiladi.
 */
class TextTranscriptParser
{
    private const ROMAN = ['i' => 1, 'ii' => 2, 'iii' => 3, 'iv' => 4, 'v' => 5, 'vi' => 6, 'vii' => 7, 'viii' => 8, 'ix' => 9, 'x' => 10, 'xi' => 11, 'xii' => 12];

    /**
     * @return array<int, array{semester: int|null, name: string, hours: int|null, credit: float|null, ball: float|null, grade: int|null, grade_text: string|null}>
     */
    public function parse(string $text): array
    {
        $rows = [];
        $semester = null;
        $pending = []; // raqamsiz nom bo'laklari (keyingi qatorga tegishli bo'lishi mumkin)
        $lastIdx = null;
        $inTable = false;

        foreach (preg_split('/\R/u', $text) as $line) {
            $trim = trim($line);
            if ($trim === '') {
                $pending = [];
                continue;
            }

            if (($s = $this->semesterHeader($trim)) !== null) {
                $semester = $s;
                $pending = [];
                $lastIdx = null;
                $inTable = true;
                continue;
            }

            if ($row = $this->dataRow($trim)) {
                if ($row['name'] === '' && $pending) {
                    $row['name'] = implode(' ', $pending);
                } elseif ($pending) {
                    $row['name'] = trim(implode(' ', $pending) . ' ' . $row['name']);
                }
                $row['semester'] = $semester;
                $rows[] = $row;
                $lastIdx = array_key_last($rows);
                $pending = [];
                $inTable = true;
                continue;
            }

            if (!$inTable || $this->isNoise($trim)) {
                continue;
            }

            // Raqamsiz qator — nomning davomi. Kichik harf yoki "/" bilan boshlansa, yoki
            // oldingi nom tugallanmagan ko'rinsa ("HDJ.", "…,", "… va") — oldingi qatorning
            // davomi; aks holda keyingi qatorning boshi (raqamlar nomning o'rta qatorida
            // turgan holat: "QVP, QOP, … / Assistant" ↵ "45 nurse of … 180 / 6 78 / 4").
            if ($lastIdx !== null && $pending === [] && (preg_match('/^[\p{Ll}\/(,.]/u', $trim)
                || preg_match('/([.,\/\-]|\sva)$/u', $rows[$lastIdx]['name']))) {
                $rows[$lastIdx]['name'] = trim($rows[$lastIdx]['name'] . ' ' . $trim);
            } else {
                $pending[] = $trim;
            }
        }

        foreach ($rows as &$r) {
            $r['name'] = $this->cleanName($r['name']);
        }
        unset($r);

        return array_values(array_filter($rows, fn ($r) => $r['name'] !== ''));
    }

    private function semesterHeader(string $line): ?int
    {
        if (preg_match('/^(\d{1,2})\s*-?\s*semest(?:r|er)\b/iu', $line, $m)) {
            return (int) $m[1];
        }
        if (preg_match('/\b([ivx]{1,4})\s*-?\s*semest(?:r|er)\b/iu', $line, $m)) {
            return self::ROMAN[mb_strtolower($m[1])] ?? null;
        }

        return null;
    }

    /**
     * "N  Nom ...   yuklama / kredit   ball / baho" ko'rinishidagi qator.
     */
    private function dataRow(string $line): ?array
    {
        $re = '/^(?:(\d{1,3})[.)]?\s+)?(.*?)\s*(\d{2,4})\s*\/\s*(\d{1,2}(?:[.,]\d+)?)\s+(\d{1,3}(?:[.,]\d+)?)\s*\/\s*([0-5])\b\s*(.*)$/u';
        if (!preg_match($re, $line, $m)) {
            return null;
        }

        return [
            'semester' => null,
            'name' => trim($m[2]),
            'hours' => (int) $m[3],
            'credit' => (float) str_replace(',', '.', $m[4]),
            'ball' => (float) str_replace(',', '.', $m[5]),
            'grade' => (int) $m[6],
            'grade_text' => trim($m[7]) !== '' ? trim($m[7]) : null,
        ];
    }

    private function isNoise(string $line): bool
    {
        return (bool) preg_match('/(fan nomi|name of course|yuklama|kredit|ball\s*\/\s*baho|score\/grade|jami|total|gpa|baholash tizimi|grading system|dekan)/iu', $line);
    }

    private function cleanName(string $name): string
    {
        $name = preg_replace('/\s+/u', ' ', $name);

        return trim($name, " \t-–|");
    }
}
