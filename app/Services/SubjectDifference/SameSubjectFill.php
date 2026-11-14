<?php

namespace App\Services\SubjectDifference;

/**
 * Bizning rejada bitta fan bir nechta semestrda o'tilsa (masalan Gistologiya 2-, 3-, 4-semestr),
 * transkriptda esa u bitta qator bo'lsa — barcha semestrlariga shu bitta baho qo'yiladi.
 *
 * Moslashtirish (qoida yoki AI) fanning ba'zi semestrlarini topib, boshqalarini topmagan bo'lsa,
 * topilmaganlariga eng yaqin semestrdagi moslik ko'chiriladi. Allaqachon moslangan qatorlarga
 * tegilmaydi; fanning birorta semestri ham moslanmagan bo'lsa — hammasi farq bo'lib qoladi.
 */
class SameSubjectFill
{
    /**
     * @param  array<int, array{key: string, subject_id?: int|null, name: string, semester: int|null}>  $plan
     * @param  array<string, array>  $map  plan key => ['tr' => int|null, ...]
     * @return array<string, array>
     */
    public static function apply(array $plan, array $map): array
    {
        $rule = new RuleMatcher();
        $groups = [];
        foreach ($plan as $p) {
            $g = !empty($p['subject_id']) ? 'id:' . $p['subject_id'] : 'n:' . $rule->normalize((string) $p['name']);
            $groups[$g][] = $p;
        }

        foreach ($groups as $rows) {
            if (count($rows) < 2) {
                continue;
            }
            $matched = array_values(array_filter($rows, fn ($p) => ($map[$p['key']]['tr'] ?? null) !== null));
            if (!$matched) {
                continue;
            }
            foreach ($rows as $p) {
                if (($map[$p['key']]['tr'] ?? null) !== null) {
                    continue;
                }
                usort($matched, fn ($a, $b) => abs((int) $a['semester'] - (int) $p['semester']) <=> abs((int) $b['semester'] - (int) $p['semester']));
                $src = $matched[0];
                $entry = $map[$src['key']];
                $entry['filled_from'] = $src['key'];
                if (array_key_exists('reason', $entry)) {
                    $entry['reason'] = "Transkriptda bitta fan — {$src['semester']}-semestrdagi moslik bilan bir xil";
                }
                $map[$p['key']] = $entry;
            }
        }

        return $map;
    }
}
