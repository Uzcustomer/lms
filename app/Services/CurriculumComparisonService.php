<?php

namespace App\Services;

use App\Models\ManualCurriculum;
use Illuminate\Support\Collection;

/**
 * Namunaviy o'quv reja bilan ishchi o'quv rejani fanlar kesimida solishtiradi.
 *
 * Fanlar normalizatsiya qilingan nom bo'yicha moslanadi (katta-kichik harf,
 * apostrof turlari va tinish belgilaridagi farqlar e'tiborga olinmaydi).
 * Ishchi rejada nom boshqacha bo'lsa, Excel'dagi "Namunaviy rejadagi nomi"
 * ustuni (reference_name) orqali aniq bog'lanadi. Bir fan bir nechta
 * semestrda o'tilsa, soat va kreditlari jamlab solishtiriladi.
 */
class CurriculumComparisonService
{
    public const STATUS_OK = "To'g'ri";
    public const STATUS_NAME = 'Nom farqi';
    public const STATUS_HOURS = 'Soat farqi';
    public const STATUS_CREDIT = 'Kredit farqi';
    public const STATUS_HOURS_CREDIT = 'Soat va kredit farqi';
    public const STATUS_MISSING_IN_WORKING = "Ishchi rejada yo'q";
    public const STATUS_MISSING_IN_REFERENCE = "Namunaviy rejada yo'q";

    public function compare(ManualCurriculum $reference, ManualCurriculum $working, array $hemisNames = []): array
    {
        return $this->run(
            $reference->subjects()->orderBy('id')->get(),
            $working->subjects()->orderBy('id')->get(),
            $hemisNames
        );
    }

    /**
     * Jamlangan solishtirish: bitta namunaviy reja bilan unga tegishli BARCHA
     * ishchi rejalar (turli yillarda, turli semestrlar uchun yuklangan) birgalikda
     * solishtiriladi. Batch yuklashda bitta fayl bir nechta semestr yozuviga
     * nusxalanadi — ikki marta hisoblanmasligi uchun har bir yozuvdan faqat o'z
     * semestriga tegishli qatorlar olinadi va takrorlar chiqarib tashlanadi.
     * Namunaviy reja esa faqat yuklangan (qamrab olingan) semestrlar bo'yicha
     * filtrlanadi — shunda hali yuklanmagan semestrlar farq sifatida ko'rinmaydi.
     */
    public function compareGroup(ManualCurriculum $reference, Collection $workings, array $hemisNames = []): array
    {
        [$workSubjects, $covered, $plans] = $this->mergeWorkingSubjects($workings);

        $refSubjects = $reference->subjects()->orderBy('id')->get();
        if (!empty($covered)) {
            $refSubjects = $refSubjects->filter(function ($s) use ($covered) {
                $sem = ($s->semester !== null && $s->semester !== '') ? (int) $s->semester : null;
                return $sem === null || in_array($sem, $covered, true);
            })->values();
        }

        $result = $this->run($refSubjects, $workSubjects, $hemisNames);
        $result['covered_semesters'] = $covered;
        $result['plans'] = $plans;

        return $result;
    }

    private function run(Collection $refSubjects, Collection $workSubjects, array $hemisNames = []): array
    {
        // HEMIS fan nomlari xaritasi: normalizatsiya qilingan kalit => asl nom.
        // Bu namunaviy/ishchi nomlarini HEMIS nomi bilan solishtirish uchun.
        $hemisMap = [];
        foreach ($hemisNames as $hn) {
            $key = $this->normalize((string) $hn);
            if ($key !== '' && !isset($hemisMap[$key])) {
                $hemisMap[$key] = $this->collapse((string) $hn);
            }
        }

        $refGroups = $this->groupSubjects($refSubjects, false);
        $workGroups = $this->groupSubjects($workSubjects, true);

        $rows = [];
        foreach ($refGroups as $key => $ref) {
            $work = $workGroups[$key] ?? null;
            unset($workGroups[$key]);
            $rows[] = $this->buildRow($ref, $work, $hemisMap);
        }
        foreach ($workGroups as $work) {
            $rows[] = $this->buildRow(null, $work, $hemisMap);
        }

        $stats = collect($rows)->countBy('status')->all();
        $totals = [
            'ref_hours' => collect($rows)->sum(fn($r) => $r['ref_hours'] ?? 0),
            'work_hours' => collect($rows)->sum(fn($r) => $r['work_hours'] ?? 0),
            'ref_credit' => collect($rows)->sum(fn($r) => $r['ref_credit'] ?? 0),
            'work_credit' => collect($rows)->sum(fn($r) => $r['work_credit'] ?? 0),
        ];
        $totals['hours_diff'] = round($totals['work_hours'] - $totals['ref_hours'], 2);
        $totals['credit_diff'] = round($totals['work_credit'] - $totals['ref_credit'], 2);

        return ['rows' => $rows, 'totals' => $totals, 'stats' => $stats];
    }

    /**
     * Ishchi rejalar fanlarini bitta ro'yxatga yig'ish.
     *
     * Qoidalar:
     *  - yozuvda semester_code bo'lsa, fayldan faqat shu semestr qatorlari olinadi
     *    (batch yuklashda bir fayl bir nechta semestr yozuviga to'liq nusxalanadi);
     *  - takror (semestr + fan nomi) juftliklari bir marta olinadi, eng oxirgi
     *    yuklangan reja ustunlik qiladi;
     *  - semestri ko'rsatilmagan qatorlar fayl bo'yicha bir marta olinadi.
     *
     * @return array{0: Collection, 1: array<int>, 2: array<int, array>}
     *         [jamlangan fanlar, qamrab olingan semestrlar, har bir reja bo'yicha xulosa]
     */
    private function mergeWorkingSubjects(Collection $workings): array
    {
        $seen = [];
        $combined = collect();
        $covered = [];
        $plans = [];

        // Eng oxirgi yuklangan reja takror qatorlarda ustun bo'lishi uchun ID kamayish tartibida
        foreach ($workings->sortByDesc('id')->values() as $working) {
            $recSem = self::semesterNumber($working->semester_code);
            $taken = 0;
            $hours = 0.0;
            $credit = 0.0;

            foreach ($working->subjects()->orderBy('id')->get() as $s) {
                $rowSem = ($s->semester !== null && $s->semester !== '') ? (int) $s->semester : null;
                if ($recSem !== null && $rowSem !== null && $rowSem !== $recSem) {
                    continue;
                }
                $name = $this->normalize($s->reference_name ?: $s->subject_name);
                $key = $rowSem !== null
                    ? 's' . $rowSem . '|' . $name
                    : 'n|' . ($working->file_path ?: $working->id) . '|' . $name;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $combined->push($s);
                $taken++;
                $hours += (float) ($s->total_hours ?? 0);
                $credit += (float) ($s->credit ?? 0);

                $sem = $rowSem ?? $recSem;
                if ($sem !== null && !in_array($sem, $covered, true)) {
                    $covered[] = $sem;
                }
            }

            $plans[] = [
                'id'            => $working->id,
                'name'          => $working->name,
                'plan_year'     => $working->plan_year,
                'semester'      => $recSem,
                'semester_code' => $working->semester_code,
                'subjects'      => $taken,
                'hours'         => round($hours, 2),
                'credit'        => round($credit, 2),
            ];
        }

        sort($covered);
        usort($plans, fn($a, $b) => [$a['semester'] ?? 99, $a['id']] <=> [$b['semester'] ?? 99, $b['id']]);

        return [$combined->values(), $covered, $plans];
    }

    /** HEMIS semestr kodini tartib raqamiga o'tkazish (11 => 1, 12 => 2, ...). */
    public static function semesterNumber(?string $code): ?int
    {
        if ($code === null || $code === '') {
            return null;
        }
        $n = (int) $code;

        return $n >= 11 ? $n - 10 : $n;
    }

    private function buildRow(?array $ref, ?array $work, array $hemisMap = []): array
    {
        $notes = array_filter(array_merge($ref['notes'] ?? [], $work['notes'] ?? []));

        $refName = $ref['name'] ?? null;
        $workName = $work['name'] ?? null;

        // HEMIS nomini topish (normalizatsiya bo'yicha), keyin nomlarni AYNAN
        // (collapse — faqat bo'shliq farqi e'tiborsiz, apostrof/registr muhim)
        // HEMIS nomi bilan solishtirish.
        $lookupKey = $this->normalize((string) ($refName ?? $workName ?? ''));
        $hemisName = $hemisMap[$lookupKey] ?? null;

        $refMatchesHemis = ($hemisName !== null && $refName !== null)
            ? ($this->collapse($refName) === $hemisName) : null;
        $workMatchesHemis = ($hemisName !== null && $workName !== null)
            ? ($this->collapse($workName) === $hemisName) : null;

        // HEMIS nomlari umuman yuklangan bo'lsa — tekshiruv faol.
        $hemisAvailable = !empty($hemisMap);

        if ($ref === null) {
            $status = self::STATUS_MISSING_IN_REFERENCE;
        } elseif ($work === null) {
            $status = self::STATUS_MISSING_IN_WORKING;
        } else {
            $hoursDiff = $this->diff($ref['hours'], $work['hours']);
            $creditDiff = $this->diff($ref['credit'], $work['credit']);
            // Nom farqi:
            //  - HEMIS tekshiruvi faol bo'lsa: HEMIS'da topilmasa YOKI namunaviy/
            //    ishchi nomi HEMIS nomidan farq qilsa — nom farqi (yashil emas).
            //  - HEMIS nomlari umuman yuklanmagan bo'lsa: eski mantiq — namunaviy
            //    va ishchi bir-biridan farq qilsa.
            $nameDiffers = $hemisAvailable
                ? ($hemisName === null || $refMatchesHemis === false || $workMatchesHemis === false)
                : ($this->collapse($ref['name']) !== $this->collapse($work['name']));
            $status = match (true) {
                $hoursDiff && $creditDiff => self::STATUS_HOURS_CREDIT,
                $hoursDiff => self::STATUS_HOURS,
                $creditDiff => self::STATUS_CREDIT,
                $nameDiffers => self::STATUS_NAME,
                default => self::STATUS_OK,
            };
            if ($nameDiffers && $status !== self::STATUS_NAME) {
                $notes[] = 'Nomda ham farq bor';
            }
        }

        // HEMIS nomi bo'yicha aniq izohlar
        if ($refMatchesHemis === false) {
            $notes[] = "Namunaviy nomi HEMIS nomidan farq qiladi";
        }
        if ($workMatchesHemis === false) {
            $notes[] = "Ishchi nomi HEMIS nomidan farq qiladi";
        }
        if ($hemisName === null && ($refName !== null || $workName !== null)) {
            $notes[] = "HEMIS bazasida fan topilmadi";
        }

        return [
            'block' => $ref['block'] ?? $work['block'] ?? null,
            'hemis_name' => $hemisName,
            'ref_name' => $refName,
            'work_name' => $workName,
            'ref_matches_hemis' => $refMatchesHemis,
            'work_matches_hemis' => $workMatchesHemis,
            'name_differs' => $ref && $work && $this->collapse($ref['name']) !== $this->collapse($work['name']),
            'ref_hours' => $ref['hours'] ?? null,
            'work_hours' => $work['hours'] ?? null,
            'hours_diff' => ($ref && $work) ? round(($work['hours'] ?? 0) - ($ref['hours'] ?? 0), 2) : null,
            'ref_credit' => $ref['credit'] ?? null,
            'work_credit' => $work['credit'] ?? null,
            'credit_diff' => ($ref && $work) ? round(($work['credit'] ?? 0) - ($ref['credit'] ?? 0), 2) : null,
            'kurslar' => implode(',', $work['kurslar'] ?? []),
            'semestrlar' => implode(',', collect(array_merge($ref['semestrlar'] ?? [], $work['semestrlar'] ?? []))->unique()->sort()->values()->all()),
            'status' => $status,
            'note' => implode('; ', array_unique($notes)),
        ];
    }

    private function groupSubjects(Collection $subjects, bool $useReferenceName): array
    {
        $groups = [];
        foreach ($subjects as $subject) {
            $key = $this->normalize($useReferenceName && $subject->reference_name
                ? $subject->reference_name
                : $subject->subject_name);
            if (!isset($groups[$key])) {
                $groups[$key] = [
                    'name' => trim($subject->subject_name),
                    'block' => $subject->block,
                    'hours' => null,
                    'credit' => null,
                    'kurslar' => [],
                    'semestrlar' => [],
                    'notes' => [],
                ];
            }
            $group = &$groups[$key];
            if ($subject->total_hours !== null) {
                $group['hours'] = round(($group['hours'] ?? 0) + (float) $subject->total_hours, 2);
            }
            if ($subject->credit !== null) {
                $group['credit'] = round(($group['credit'] ?? 0) + (float) $subject->credit, 2);
            }
            if ($subject->kurs && !in_array($subject->kurs, $group['kurslar'])) {
                $group['kurslar'][] = $subject->kurs;
            }
            if ($subject->semester && !in_array($subject->semester, $group['semestrlar'])) {
                $group['semestrlar'][] = $subject->semester;
            }
            if ($subject->note) {
                $group['notes'][] = $subject->note;
            }
            unset($group);
        }
        foreach ($groups as &$group) {
            sort($group['kurslar']);
            sort($group['semestrlar']);
        }
        return $groups;
    }

    private function diff(?float $a, ?float $b): bool
    {
        return abs(($b ?? 0) - ($a ?? 0)) > 0.011;
    }

    private function collapse(string $value): string
    {
        return trim(preg_replace('/\s+/u', ' ', $value));
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }
}
