<?php

namespace App\Services;

use App\Http\Middleware\BlockExpelledStudentGrade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Bir fan bo'yicha kichik guruhlarga (d1/d22-01a, 01b, 01c) bo'lingan guruh — bitta umumiy YN
 * qaydnoma: nomi faqat oxirgi harf bilan farq qiladigan, bir o'quv rejadagi guruhlar; faqat
 * shu fandan talabasi va bahosi bor kichik guruhlar kiradi. Qaydnomaning asosiy guruhi —
 * birinchisi (nomi bo'yicha), umumiy nomi — harfsiz ("d1/d22-01").
 */
class YnqFamily
{
    /** @var array<string, array> */
    private static array $cache = [];

    /** "d1/d22-01a (rus)" → "d1/d22-01 (rus)"; raqamdan keyin bitta harf bilan tugamasa — null */
    public static function stem(?string $name): ?string
    {
        return preg_match('/^(.*\d)\s*([a-z])(\s*\(.*\))?$/iu', trim((string) $name), $m) ? $m[1] . ($m[3] ?? '') : null;
    }

    /** Qaydnoma guruhlari (group_hemis_id), nomi bo'yicha tartibda; $groupHemisId doim kiradi */
    public static function members(string $subjectId, string $semesterCode, string $groupHemisId): array
    {
        $group = DB::table('groups')->where('group_hemis_id', $groupHemisId)->first(['name', 'curriculum_hemis_id']);

        return $group ? self::membersFor($subjectId, $semesterCode, $groupHemisId, $group->name, $group->curriculum_hemis_id) : [$groupHemisId];
    }

    /** members() — guruh nomi va o'quv rejasi ma'lum bo'lsa (ro'yxatlar uchun, ortiqcha so'rovsiz) */
    public static function membersFor(string $subjectId, string $semesterCode, string $groupHemisId, ?string $name, $curriculumHemisId): array
    {
        $stem = self::stem($name);
        if ($stem === null) {
            return [$groupHemisId];
        }
        $key = $curriculumHemisId . '|' . $stem . '|' . $subjectId . '|' . $semesterCode;
        $eligible = self::$cache[$key] ??= self::eligible($subjectId, $semesterCode, $stem, $curriculumHemisId);
        if (!isset($eligible[$groupHemisId])) {
            $eligible[$groupHemisId] = (string) $name;
            asort($eligible, SORT_NATURAL);
        }

        return array_map('strval', array_keys($eligible));
    }

    /** Asosiy guruh (qaydnoma va dekan / kafedra tasdiqlari shu guruh nomidan) */
    public static function canonical(string $subjectId, string $semesterCode, string $groupHemisId): string
    {
        return self::members($subjectId, $semesterCode, $groupHemisId)[0];
    }

    /** Qaydnoma nomi: bir nechta kichik guruh bo'lsa — harfsiz umumiy nom */
    public static function label(?string $name, int $memberCount): string
    {
        return $memberCount > 1 ? (self::stem($name) ?? (string) $name) : (string) $name;
    }

    /** Testlar uchun */
    public static function flush(): void
    {
        self::$cache = [];
    }

    /** group_hemis_id → nom: shu o'quv rejadagi bir oilaviy guruhlardan shu fandan talabasi va bahosi borlari */
    private static function eligible(string $subjectId, string $semesterCode, string $stem, $curriculumHemisId): array
    {
        $prefix = preg_replace('/\s*\(.*\)$/', '', $stem);
        $candidates = DB::table('groups')
            ->where('curriculum_hemis_id', $curriculumHemisId)
            ->where('name', 'like', str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $prefix) . '%')
            ->get(['group_hemis_id', 'name'])
            ->filter(fn ($g) => self::stem($g->name) === $stem)
            ->mapWithKeys(fn ($g) => [(string) $g->group_hemis_id => (string) $g->name]);
        if ($candidates->count() < 2 || !Schema::hasTable('students') || !Schema::hasTable('student_grades')) {
            return [];
        }
        $withGrades = DB::table('students as s')
            ->join('student_grades as sg', 'sg.student_hemis_id', '=', 's.hemis_id')
            ->whereIn('s.group_id', $candidates->keys()->all())
            ->where(fn ($q) => $q->whereNull('s.student_status_code')
                ->orWhere('s.student_status_code', '!=', BlockExpelledStudentGrade::EXPELLED_STATUS))
            ->where('sg.subject_id', $subjectId)
            ->where('sg.semester_code', $semesterCode)
            ->whereNull('sg.deleted_at')
            ->distinct()
            ->pluck('s.group_id')
            ->map(fn ($v) => (string) $v)
            ->all();
        $eligible = $candidates->only($withGrades)->all();
        asort($eligible, SORT_NATURAL);

        return $eligible;
    }
}
