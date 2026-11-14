<?php

namespace App\Services\SubjectDifference;

/**
 * Transkriptdagi bahoni bizning 5 ballik bahoga o'girish qoidasi.
 *
 * Xodim transkriptni yuklayotganda belgilaydi:
 *  - mode = "ball"  — baho 100 ballik balldan chegaralar bo'yicha hisoblanadi
 *                     (standart: 5 = 90+, 4 = 70+, 3 = 60+, qolgani 2);
 *  - mode = "grade" — transkriptda yozilgan baho (5/4/3/2) o'zi olinadi.
 *
 * Asosiy manba bo'lmasa, ikkinchisi zaxira sifatida ishlatiladi; manbalar
 * bir-biriga zid bo'lsa (masalan "88 (a'lo)": shkala bo'yicha 4, so'z bilan 5)
 * qatorda ogohlantirish chiqadi.
 */
class GradeScale
{
    public const DEFAULTS = ['mode' => 'ball', 'five' => 90, 'four' => 70, 'three' => 60];

    private const WORDS = [
        5 => ["a'lo", 'alo', 'отлично', 'excellent'],
        4 => ['yaxshi', 'хорошо', 'good'],
        3 => ['qoniqarli', 'удовлетворительно', 'satisfactory'],
        2 => ['qoniqarsiz', 'неудовлетворительно', 'unsatisfactory', 'fail'],
    ];

    public function __construct(
        public readonly string $mode = 'ball',
        public readonly float $five = 90,
        public readonly float $four = 70,
        public readonly float $three = 60,
    ) {
    }

    public static function fromArray(?array $a): self
    {
        $a = array_merge(self::DEFAULTS, array_filter($a ?? [], fn ($v) => $v !== null && $v !== ''));

        return new self(
            in_array($a['mode'], ['ball', 'grade'], true) ? $a['mode'] : 'ball',
            (float) $a['five'],
            (float) $a['four'],
            (float) $a['three'],
        );
    }

    public function toArray(): array
    {
        return ['mode' => $this->mode, 'five' => $this->five, 'four' => $this->four, 'three' => $this->three];
    }

    public function label(): string
    {
        if ($this->mode === 'grade') {
            return 'Transkriptdagi baho olinadi';
        }

        return sprintf('Balldan: 5 = %s+, 4 = %s+, 3 = %s+', $this->num($this->five), $this->num($this->four), $this->num($this->three));
    }

    public function fromBall(float $ball): int
    {
        return match (true) {
            $ball >= $this->five => 5,
            $ball >= $this->four => 4,
            $ball >= $this->three => 3,
            default => 2,
        };
    }

    public static function fromWord(?string $text): ?int
    {
        $t = mb_strtolower(trim((string) $text));
        if ($t === '') {
            return null;
        }
        $t = str_replace(['‘', '’', 'ʻ', 'ʼ', '`'], "'", $t);
        // "qoniqarsiz" ichida "qoniqarli" yo'q, lekin "a'lo" boshqa so'z ichida uchrashi mumkin —
        // shuning uchun avval uzunroq so'zlar tekshiriladi.
        foreach ([2, 3, 4, 5] as $g) {
            foreach (self::WORDS[$g] as $w) {
                if (str_contains($t, $w)) {
                    return $g;
                }
            }
        }

        return null;
    }

    /**
     * Transkript qatoridan yakuniy bahoni aniqlaydi.
     *
     * @param  array{ball?: float|int|null, grade?: int|null, grade_text?: string|null}  $row
     * @return array{grade: int|null, source: string|null, conflicts: array<int, string>}
     */
    public function resolve(array $row): array
    {
        // "0 / 0" — hali baholanmagan (masalan, oxirgi semestr ko'chirmada alohida keladi): baho yo'q.
        $ball = isset($row['ball']) && is_numeric($row['ball']) && (float) $row['ball'] > 0 ? (float) $row['ball'] : null;
        $written = isset($row['grade']) && is_numeric($row['grade']) ? (int) $row['grade'] : null;
        if ($written !== null && ($written < 2 || $written > 5)) {
            $written = null; // 5 ballik tizimdan tashqari son — ishonchsiz
        }
        $word = self::fromWord($row['grade_text'] ?? null);
        $fromBall = $ball !== null && $ball <= 100 ? $this->fromBall($ball) : null;

        $candidates = $this->mode === 'grade'
            ? [['written', $written], ['word', $word], ['ball', $fromBall]]
            : [['ball', $fromBall], ['written', $written], ['word', $word]];

        $grade = null;
        $source = null;
        foreach ($candidates as [$src, $val]) {
            if ($val !== null) {
                $grade = $val;
                $source = $src;
                break;
            }
        }

        $conflicts = [];
        $labels = ['ball' => 'ball bo\'yicha', 'written' => 'yozilgan baho', 'word' => 'so\'z bilan'];
        foreach ($candidates as [$src, $val]) {
            if ($val !== null && $grade !== null && $val !== $grade) {
                $conflicts[] = sprintf('%s %d, lekin %s %d', ucfirst($labels[$source]), $grade, $labels[$src], $val);
            }
        }

        return ['grade' => $grade, 'source' => $source, 'conflicts' => $conflicts];
    }

    private function num(float $v): string
    {
        return rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    }
}
