<?php

namespace App\Services\SubjectDifference;

/**
 * Qoida bo'yicha moslashtirish (AI'siz): bizning reja fanlarini transkript
 * qatorlariga nom o'xshashligi bo'yicha bog'laydi.
 *
 *  - nomlar normallashtiriladi: inglizcha qism (" / Human anatomy"), qavs ichidagi
 *    izohlar ("(OXTA)", "(tanlov)"), apostroflar, x/h farqi olib tashlanadi;
 *  - o'xshashlik = so'z o'zaklari (Dice) + harflar ketma-ketligi aralashmasi;
 *  - bitta transkript qatori bizning bir nechta qatorimizga baho berishi mumkin
 *    (transkriptda "Tibbiy kimyo 210/7" 2-semestrda, bizda 1- va 2-semestrda);
 *  - o'xshashligi deyarli teng nomzodlar orasidan semestri yaqinrog'i olinadi.
 *
 * Mazmunan bir xil, lekin nomi butunlay boshqa fanlarni (masalan "Statsionar vrach
 * yordamchisi" ↔ "Ishlab chiqarish amaliyoti") bu usul topmaydi — buni AI qiladi,
 * sahifada esa ikkala natija yonma-yon ko'rsatiladi.
 */
class RuleMatcher
{
    public const THRESHOLD = 0.72;

    private const TIE_WINDOW = 0.08;

    /**
     * @param  array<int, array{key: string, name: string, semester: int|null}>  $plan
     * @param  array<int, array{name: string, semester?: int|null}>  $transcript
     * @return array<string, array{tr: int|null, score: float}>  plan key => transkript indeksi
     */
    public function match(array $plan, array $transcript): array
    {
        $prepared = [];
        foreach ($transcript as $i => $t) {
            $prepared[$i] = [$this->normalize((string) ($t['name'] ?? '')), $this->tokens((string) ($t['name'] ?? '')), $t['semester'] ?? null, self::graded($t)];
        }

        $out = [];
        foreach ($plan as $p) {
            $pn = $this->normalize((string) $p['name']);
            $pt = $this->tokens((string) $p['name']);
            $scored = [];
            foreach ($prepared as $i => [$tn, $tt, $sem, $graded]) {
                $s = $this->similarity($pn, $pt, $tn, $tt);
                if ($s >= self::THRESHOLD) {
                    $scored[] = ['tr' => $i, 'score' => $s, 'dist' => $this->semDistance($p['semester'] ?? null, $sem), 'graded' => $graded];
                }
            }
            if (!$scored) {
                $out[$p['key']] = ['tr' => null, 'score' => 0.0];
                continue;
            }
            $best = max(array_column($scored, 'score'));
            $near = array_values(array_filter($scored, fn ($c) => $c['score'] >= $best - self::TIE_WINDOW));
            // Bahosi bor qator birinchi: transkriptda "0 / 0" (hali baholanmagan) bo'lib,
            // bahosi qo'shimcha ko'chirmada kelgan fanlar uchun.
            usort($near, fn ($a, $b) => [!$a['graded'], $a['dist'], -$a['score']] <=> [!$b['graded'], $b['dist'], -$b['score']]);
            $out[$p['key']] = ['tr' => $near[0]['tr'], 'score' => round($near[0]['score'], 3)];
        }

        return $out;
    }

    /** Qatorda haqiqiy baho bormi ("0 / 0" yoki bo'sh emas). */
    public static function graded(array $t): bool
    {
        return (is_numeric($t['ball'] ?? null) && (float) $t['ball'] > 0)
            || (is_numeric($t['grade'] ?? null) && (int) $t['grade'] >= 2)
            || trim((string) ($t['grade_text'] ?? '')) !== '';
    }

    public function score(string $a, string $b): float
    {
        return $this->similarity($this->normalize($a), $this->tokens($a), $this->normalize($b), $this->tokens($b));
    }

    /** Inglizcha tarjima va qavsdagi izohlarsiz, faqat harf-raqamlar. */
    public function normalize(string $name): string
    {
        return implode('', $this->words($name));
    }

    private function similarity(string $an, array $at, string $bn, array $bt): float
    {
        if ($an === '' || $bn === '') {
            return 0.0;
        }
        if ($an === $bn) {
            return 1.0;
        }

        $dice = ($at || $bt) ? 2 * $this->fuzzyIntersect($at, $bt) / (count($at) + count($bt)) : 0.0;
        similar_text($an, $bn, $pct);
        $score = 0.55 * $dice + 0.45 * ($pct / 100);

        // Biri ikkinchisining boshi bo'lsa ("Pediatriya" ↔ "Pediatriya, neonatologiya"),
        // ulush qanchalik katta bo'lsa shuncha yaqin.
        [$short, $long] = mb_strlen($an) <= mb_strlen($bn) ? [$an, $bn] : [$bn, $an];
        if (mb_strlen($short) >= 6 && str_starts_with($long, $short)) {
            $score = max($score, 0.65 + 0.35 * mb_strlen($short) / mb_strlen($long));
        }

        return min(1.0, $score);
    }

    /**
     * Umumiy so'z o'zaklari soni; bitta harf farqi (imlo xatosi: "ftizatriya" ↔
     * "ftiziatriya") ham mos deb sanaladi. Har o'zak faqat bir marta ishlatiladi.
     */
    private function fuzzyIntersect(array $a, array $b): int
    {
        $count = 0;
        foreach ($a as $x) {
            foreach ($b as $j => $y) {
                if (mb_substr($x, 0, 6) === mb_substr($y, 0, 6)
                    || (min(mb_strlen($x), mb_strlen($y)) >= 6 && levenshtein($x, $y) <= (mb_strlen($x) >= 10 ? 2 : 1))) {
                    $count++;
                    unset($b[$j]);
                    break;
                }
            }
        }

        return $count;
    }

    /**
     * Mazmunli so'zlar (3+ harf). Solishtirishda birinchi 6 harfi (o'zak) bir xil
     * bo'lsa mos sanaladi — qo'shimchalar farqini yumshatadi. Bir xil o'zakli
     * so'zlar bir marta olinadi.
     */
    private function tokens(string $name): array
    {
        $out = [];
        foreach ($this->words($name) as $w) {
            if (mb_strlen($w) >= 3) {
                $out[mb_substr($w, 0, 6)] ??= $w;
            }
        }

        return array_values($out);
    }

    private function words(string $name): array
    {
        $s = mb_strtolower(trim($name));
        // "Odam anatomiyasi / Human anatomy" → faqat o'zbekcha qism
        $s = preg_split('#\s/\s#u', $s)[0];
        // "(OXTA)", "(a)", "(tanlov)" kabi izohlar
        $s = preg_replace('/\([^)]*\)/u', ' ', $s);
        $s = str_replace(["'", '‘', '’', 'ʻ', 'ʼ', '`', '´'], '', $s);
        // Kirill-lotin aralash yozilgan harflar (masalan "prоpedevtikasi"dagi kirill "о")
        $s = strtr($s, ['а' => 'a', 'е' => 'e', 'о' => 'o', 'р' => 'p', 'с' => 'c', 'х' => 'x', 'у' => 'y', 'і' => 'i']);
        // O'zbek lotinida x/h ko'p adashtiriladi: "jarroxlik"/"jarrohlik", "XDJ"/"HDJ"
        $s = str_replace('x', 'h', $s);
        $parts = preg_split('/[^\p{L}\p{N}]+/u', $s, -1, PREG_SPLIT_NO_EMPTY);

        return $parts ?: [];
    }

    private function semDistance(?int $a, $b): int
    {
        if ($a === null || $b === null || !is_numeric($b)) {
            return 50;
        }

        return abs($a - (int) $b);
    }
}
