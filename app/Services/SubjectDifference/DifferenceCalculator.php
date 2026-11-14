<?php

namespace App\Services\SubjectDifference;

/**
 * Bitta bosqich (tanlangan o'quv reja + "qaysi semestrgacha") bo'yicha fanlar
 * farqi va GPA.
 *
 *  - Fan va KREDIT bizning rejadan, BAHO transkriptdan olinadi.
 *  - Transkriptda mos fan bo'lmasa — baho 0, bu fanlar farqi.
 *  - GPA = Σ(kredit × baho) / Σ(kredit).
 *  - "Semestrgacha" S ≥ 2: GPA ≥ 2.4 bo'lsa (S/2 + 1)-kursga, (S+1)-semestrdan qabul.
 *  - S = 1: GPA ahamiyatsiz — 1-semestrning barcha fanlari o'zlashtirilgan bo'lsa
 *    1-kurs 2-semestrdan davom ettiradi.
 */
class DifferenceCalculator
{
    public const GPA_LIMIT = 2.4;

    /**
     * @param  array<int, array>  $plan  reja qatorlari (key, semester, name, credit, type, ...)
     * @param  array<string, int|null>  $mapping  plan key => transkript indeksi
     * @param  array<int, array>  $transcript  transkript qatorlari
     * @param  array<int, string>  $excluded  hisobdan chiqarilgan plan key'lar
     */
    public function calculate(array $plan, array $mapping, array $transcript, GradeScale $scale, int $uptoSemester, array $excluded = []): array
    {
        $rows = [];
        $sumCredit = 0.0;
        $sumProduct = 0.0;
        $farqCount = 0;
        $farqCredit = 0.0;
        $semOneFarq = 0;

        foreach ($plan as $p) {
            $key = $p['key'];
            $trIdx = $mapping[$key] ?? null;
            $tr = $trIdx !== null ? ($transcript[$trIdx] ?? null) : null;
            $credit = (float) ($p['credit'] ?? 0);
            $isExcluded = in_array($key, $excluded, true);

            $notes = [];
            $grade = 0;
            if ($tr) {
                $res = $scale->resolve($tr);
                $grade = (int) ($res['grade'] ?? 0);
                $notes = array_merge($notes, $res['conflicts']);
                if ($res['grade'] === null) {
                    $notes[] = 'Transkriptda baho o\'qilmadi';
                }
            }

            $farq = !$tr || $grade < 3;
            if ($farq) {
                if (!$tr) {
                    $notes[] = 'Transkriptda yo\'q — fanlar farqi';
                } elseif ($grade > 0) {
                    $notes[] = "O'zlashtirilmagan ({$grade}) — fanlar farqi";
                }
                if (self::isElective($p['type'] ?? null)) {
                    $notes[] = 'Tanlov fan (guruhdagi muqobil) — farq sifatida hisoblandi';
                }
            }
            if ($isExcluded) {
                $notes[] = 'Xodim hisobdan chiqargan';
            }

            if (!$isExcluded) {
                $sumCredit += $credit;
                $sumProduct += $credit * $grade;
                if ($farq) {
                    $farqCount++;
                    $farqCredit += $credit;
                    if ((int) ($p['semester'] ?? 0) === 1) {
                        $semOneFarq++;
                    }
                }
            }

            $rows[] = $p + [
                'tr' => $trIdx,
                'tr_name' => $tr['name'] ?? null,
                'tr_semester' => $tr['semester'] ?? null,
                'tr_credit' => $tr['credit'] ?? null,
                'tr_ball' => $tr['ball'] ?? null,
                'grade' => $grade,
                'product' => $isExcluded ? 0 : $credit * $grade,
                'farq' => $farq,
                'excluded' => $isExcluded,
                'notes' => array_values(array_unique($notes)),
            ];
        }

        $gpa = $sumCredit > 0 ? $sumProduct / $sumCredit : 0.0;

        return [
            'rows' => $rows,
            'total_credit' => round($sumCredit, 2),
            'total_product' => round($sumProduct, 2),
            'gpa' => round($gpa, 4),
            'farq_count' => $farqCount,
            'farq_credit' => round($farqCredit, 2),
            'decision' => $this->decision($uptoSemester, $gpa, $semOneFarq),
        ];
    }

    public function decision(int $upto, float $gpa, int $semOneFarq): array
    {
        if ($upto <= 1) {
            $passed = $semOneFarq === 0;

            return [
                'passed' => $passed,
                'kurs' => 1,
                'start_semester' => $passed ? 2 : 1,
                'rule' => '1-semestr fanlari to\'liq o\'zlashtirilganmi (GPA ahamiyatsiz)',
                'text' => $passed
                    ? '1-kurs 2-semestrdan davom ettiradi'
                    : '1-semestr fanlarida farq bor — 1-kurs 1-semestr (Nizomning 10-bandi: 1-kurs 1-semestrga ko\'chirish taqiqlangan, istisno holatlar bundan mustasno)',
            ];
        }

        $kurs = intdiv($upto, 2) + 1;
        $passed = $gpa >= self::GPA_LIMIT;
        $next = $upto - 2 >= 1 ? $upto - 2 : 1;

        return [
            'passed' => $passed,
            'kurs' => $kurs,
            'start_semester' => $upto + 1,
            'rule' => 'GPA ≥ ' . self::GPA_LIMIT,
            'text' => $passed
                ? sprintf('%d-kursga (%d-semestrdan) qabul qilinadi', $kurs, $upto + 1)
                : sprintf('GPA %.2f < %.1f — %d-kursga qabul qilinmaydi. Pastki kurs o\'quv rejasini tanlab, %d-semestrgacha tekshiring', $gpa, self::GPA_LIMIT, $kurs, $next),
            'next_upto' => $passed ? null : $next,
        ];
    }

    public static function isElective(?string $type): bool
    {
        $t = mb_strtolower((string) $type);

        return str_contains($t, 'tanlov') || str_contains($t, 'elective');
    }
}
