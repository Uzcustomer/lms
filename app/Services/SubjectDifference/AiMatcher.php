<?php

namespace App\Services\SubjectDifference;

use App\Services\VedomostGeminiClient;

/**
 * AI bo'yicha moslashtirish: Gemini'ga bizning reja fanlari va transkript
 * qatorlari beriladi, u har reja fani uchun mazmunan mos transkript qatorini
 * (yoki null) qaytaradi — ishonch darajasi va qisqa izoh bilan.
 *
 * AI faqat BOG'LAYDI — baho va GPA'ni u hisoblamaydi (buni DifferenceCalculator
 * qiladi), shuning uchun AI xatosi faqat moslik ustunida ko'rinadi va xodim
 * uni qo'lda tuzata oladi.
 */
class AiMatcher
{
    public function __construct(private VedomostGeminiClient $gemini)
    {
    }

    /**
     * @return array{map: array<string, array{tr: int|null, confidence: float|null, reason: string}>, model: string}
     */
    public function match(array $plan, array $transcript): array
    {
        $planLines = [];
        $keys = [];
        foreach (array_values($plan) as $i => $p) {
            $id = 'P' . ($i + 1);
            $keys[$id] = $p['key'];
            $planLines[] = sprintf('%s | %s-semestr | %s | %s kredit | %s', $id, $p['semester'] ?? '?', $p['name'], $this->num($p['credit'] ?? null), $p['type'] ?? '');
        }
        $trLines = [];
        foreach ($transcript as $i => $t) {
            $trLines[] = sprintf('T%d | %s-semestr | %s | %s kredit', $i, $t['semester'] ?? '?', $t['name'], $this->num($t['credit'] ?? null));
        }

        $prompt = "OUR CURRICULUM (P rows):\n" . implode("\n", $planLines)
            . "\n\nSTUDENT TRANSCRIPT FROM PREVIOUS UNIVERSITY (T rows):\n" . implode("\n", $trLines)
            . "\n\n" . $this->instructions();

        $res = $this->gemini->generateJson([['text' => $prompt]], $this->schema(), null, null, 'farq_match');

        $map = [];
        foreach ($res['data']['matches'] ?? [] as $m) {
            $pid = strtoupper(trim((string) ($m['plan_id'] ?? '')));
            if (!isset($keys[$pid])) {
                continue;
            }
            $tid = trim((string) ($m['transcript_id'] ?? ''));
            $tr = null;
            if (preg_match('/^T?(\d+)$/i', $tid, $mm) && isset($transcript[(int) $mm[1]])) {
                $tr = (int) $mm[1];
            }
            $map[$keys[$pid]] = [
                'tr' => $tr,
                'confidence' => is_numeric($m['confidence'] ?? null) ? round((float) $m['confidence'], 2) : null,
                'reason' => trim((string) ($m['reason'] ?? '')),
            ];
        }
        // Javobda tushib qolgan reja qatorlari — moslik topilmagan deb belgilanadi.
        foreach ($keys as $key) {
            $map[$key] ??= ['tr' => null, 'confidence' => null, 'reason' => 'AI javobida yo\'q'];
        }

        return ['map' => $map, 'model' => $res['model']];
    }

    private function instructions(): string
    {
        return <<<'TXT'
TASK: A student transfers to our medical university. For EVERY P row decide which T row (if any) is the same discipline, so that the grade of that T row can be credited to the P row. Names are in Uzbek and are often worded differently.

Rules:
- Match by subject CONTENT, not by exact wording ("Klinik anatomiya" = "Klinik anatomiya (OXTA)" = "Operativ jarrohlik va topografik anatomiya"; "Gigiyena. Tibbiy ekologiya" = "Gigiyena, harbiy gigiyena. Tibbiy ekologiya").
- One T row MAY be used for several P rows: a subject taught over several semesters in our curriculum may appear once in the transcript (e.g. T "Tibbiy kimyo 210/7" in semester 2 covers P "Tibbiy kimyo" in semesters 1 and 2).
- When several T rows have the same subject, prefer the one with the same or nearest semester.
- Practices (amaliyot): an internship/practice in the transcript ("Statsionar vrach yordamchisi", "Hamshira yordamchisi", "Kichik tibbiy xodim yordamchisi", "Malakaviy amaliyot") may match our "Ishlab chiqarish amaliyoti" / "Malakaviy amaliyot" of the SAME study year (semesters 1-2 = year 1, 3-4 = year 2, ...).
- Do NOT match different disciplines just because they share a word: "Ichki kasalliklar" is NOT "Ichki kasalliklar propedevtikasi"; "Patologik anatomiya" is NOT "Patologik fiziologiya"; "Pediatriya" is NOT "Bolalar kasalliklari propedevtikasi" unless content clearly coincides.
- If no T row covers the P row, return transcript_id = "" (this is a subject difference).
- confidence: 0..1. reason: one short sentence in Uzbek explaining the match or why there is none.
Return one item for every P row.
TXT;
    }

    private function schema(): array
    {
        $s = ['type' => 'STRING'];
        $obj = fn (array $props) => [
            'type' => 'OBJECT',
            'properties' => $props,
            'required' => array_keys($props),
            'propertyOrdering' => array_keys($props),
        ];

        return $obj([
            'matches' => ['type' => 'ARRAY', 'items' => $obj([
                'plan_id' => $s,
                'transcript_id' => $s,
                'confidence' => ['type' => 'NUMBER'],
                'reason' => $s,
            ])],
        ]);
    }

    private function num($v): string
    {
        if ($v === null || $v === '') {
            return '?';
        }

        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }
}
