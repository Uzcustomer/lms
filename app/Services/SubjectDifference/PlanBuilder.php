<?php

namespace App\Services\SubjectDifference;

use App\Services\CurriculumComparisonService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Bizning o'quv reja fanlari — AKKREDITATSIYA ma'lumotidan (academic_records,
 * HEMIS "Akkreditatsiya baxolari ro'yxati"): tanlangan rejada haqiqatda o'tilgan
 * va baholangan fanlar, 1-semestrdan tanlangan semestrgacha.
 *
 * Har (fan, semestr) bitta qator; nom, kredit va soat — shu rejadagi talabalar
 * yozuvlarida eng ko'p uchragan qiymat. Fan turi (Majburiy/Tanlov) va kredit
 * zaxirasi curriculum_subjects jadvalidan.
 */
class PlanBuilder
{
    public function curricula(?string $q = null): Collection
    {
        $ids = Cache::remember('subject-diff:curricula-with-records', 1800, fn () => DB::table('academic_records')
            ->whereNotNull('curriculum_id')
            ->distinct()
            ->pluck('curriculum_id')
            ->map(fn ($v) => (int) $v)
            ->all());

        return DB::table('curricula')
            ->leftJoin('specialties', 'specialties.specialty_hemis_id', '=', 'curricula.specialty_hemis_id')
            ->whereIn('curricula.curricula_hemis_id', $ids ?: [0])
            ->when($q, function ($query) use ($q) {
                $term = '%' . $q . '%';
                $query->where(fn ($s) => $s->where('curricula.name', 'like', $term)
                    ->orWhere('specialties.name', 'like', $term)
                    ->orWhere('specialties.code', 'like', $term)
                    ->orWhere('curricula.education_year_name', 'like', $term));
            })
            ->select(
                'curricula.curricula_hemis_id as id',
                'curricula.name',
                'curricula.education_year_name',
                'curricula.education_form_name',
                'curricula.semester_count',
                'specialties.name as specialty_name',
                'specialties.code as specialty_code'
            )
            ->orderByDesc('curricula.education_year_code')
            ->orderBy('curricula.name')
            ->limit(300)
            ->get();
    }

    public function curriculumLabel(int $curriculumId): string
    {
        $c = DB::table('curricula')
            ->leftJoin('specialties', 'specialties.specialty_hemis_id', '=', 'curricula.specialty_hemis_id')
            ->where('curricula.curricula_hemis_id', $curriculumId)
            ->select('curricula.name', 'curricula.education_year_name', 'curricula.education_form_name', 'specialties.name as specialty_name')
            ->first();

        if (!$c) {
            return "O'quv reja #{$curriculumId}";
        }

        return trim(implode(' · ', array_filter([$c->name, $c->specialty_name !== $c->name ? $c->specialty_name : null, $c->education_year_name, $c->education_form_name])));
    }

    /**
     * @return array<int, array{key: string, subject_id: int|null, semester: int, name: string, credit: float, hours: int|null, type: string|null, students: int}>
     */
    public function build(int $curriculumId, int $uptoSemester): array
    {
        $codes = array_map(fn ($n) => (string) (10 + $n), range(1, max(1, $uptoSemester)));

        $records = DB::table('academic_records')
            ->where('curriculum_id', $curriculumId)
            ->whereIn('semester_id', $codes)
            ->select('subject_id', 'subject_name', 'semester_id', 'credit', 'total_acload', 'student_id')
            ->get();

        $types = DB::table('curriculum_subjects')
            ->where('curricula_hemis_id', $curriculumId)
            ->whereIn('semester_code', $codes)
            ->select('subject_id', 'semester_code', 'subject_type_name', 'credit', 'subject_name', 'subject_block_code', 'curriculum_subject_hemis_id')
            ->get()
            ->keyBy(fn ($r) => $r->subject_id . '|' . $r->semester_code);

        return $this->aggregate($records, $types);
    }

    /**
     * Sof agregatsiya (DB'siz test qilinadi).
     *
     * @param  iterable  $records  academic_records qatorlari (object yoki array)
     * @param  Collection|array  $types  "subject_id|semester_code" => curriculum_subjects qatori
     */
    public function aggregate(iterable $records, $types = []): array
    {
        $groups = [];
        foreach ($records as $r) {
            $r = (object) $r;
            $sem = CurriculumComparisonService::semesterNumber((string) $r->semester_id);
            if (!$sem) {
                continue;
            }
            $k = ($r->subject_id ?: ('n:' . mb_strtolower(trim((string) $r->subject_name)))) . '|' . $r->semester_id;
            $g = &$groups[$k];
            $g['subject_id'] = $r->subject_id ? (int) $r->subject_id : null;
            $g['semester'] = $sem;
            $g['semester_code'] = (string) $r->semester_id;
            $g['names'][trim((string) $r->subject_name)] = ($g['names'][trim((string) $r->subject_name)] ?? 0) + 1;
            if (is_numeric($r->credit)) {
                $c = (string) (float) $r->credit;
                $g['credits'][$c] = ($g['credits'][$c] ?? 0) + 1;
            }
            if (is_numeric($r->total_acload)) {
                $h = (string) (int) $r->total_acload;
                $g['hours'][$h] = ($g['hours'][$h] ?? 0) + 1;
            }
            $g['students'][(string) $r->student_id] = true;
            unset($g);
        }

        $types = $types instanceof Collection ? $types : collect($types);
        $out = [];
        foreach ($groups as $g) {
            $cs = $g['subject_id'] ? $types->get($g['subject_id'] . '|' . $g['semester_code']) : null;
            $cs = $cs ? (object) $cs : null;
            $credit = $this->mode($g['credits'] ?? []);
            if ($credit === null && $cs && is_numeric($cs->credit)) {
                $credit = (string) (float) $cs->credit;
            }
            $out[] = [
                'key' => ($g['subject_id'] ?? substr(md5($this->mode($g['names'])), 0, 8)) . '-' . $g['semester'],
                'subject_id' => $g['subject_id'],
                'semester' => $g['semester'],
                'name' => (string) $this->mode($g['names']),
                'credit' => (float) ($credit ?? 0),
                'hours' => ($h = $this->mode($g['hours'] ?? [])) !== null ? (int) $h : null,
                'type' => $cs->subject_type_name ?? null,
                'students' => count($g['students'] ?? []),
                // HEMIS akkreditatsiya sahifasidagi tartib: semestr ichida o'quv reja bloki
                // (gumanitar → tabiiy → kasbiy → amaliyot), so'ng rejadagi o'rni.
                'block' => $cs->subject_block_code ?? null,
                'order' => isset($cs->curriculum_subject_hemis_id) ? (int) $cs->curriculum_subject_hemis_id : null,
            ];
        }

        usort($out, function ($a, $b) {
            return [$a['semester'], $a['block'] === null ? 1 : 0] <=> [$b['semester'], $b['block'] === null ? 1 : 0]
                ?: strnatcmp((string) $a['block'], (string) $b['block'])
                ?: [$a['order'] ?? PHP_INT_MAX, self::isPractice($a['name']), $a['name']] <=> [$b['order'] ?? PHP_INT_MAX, self::isPractice($b['name']), $b['name']];
        });

        return $out;
    }

    public static function isPractice(string $name): int
    {
        return preg_match('/amaliyot/iu', $name) ? 1 : 0;
    }

    private function mode(array $counts): ?string
    {
        if (!$counts) {
            return null;
        }
        arsort($counts);

        return (string) array_key_first($counts);
    }
}
