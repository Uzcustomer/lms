<?php

namespace App\Services;

use App\Models\VedomostSubmission;
use Illuminate\Support\Collection;

/**
 * Vedomost topshirish ro'yxatini "o'zak guruh × o'zak fan" bo'yicha birlashtiradi.
 *
 * HEMIS guruhlarni guruhchalarga (d1/22-01a, d1/22-01b) va fanlarni variantlarga
 * ("Anatomiya (a)", "Anatomiya (b)") ajratib beradi. Vedomost esa real hayotda
 * bitta o'zak guruh × bitta o'zak fan uchun BITTA varaqda topshiriladi.
 *
 * Shuning uchun ro'yxatda guruhcha harflari (a/b/c) va fan variant harflari
 * kesilib, bitta o'zak qatorga jamlanadi. Guruhchalarning o'qituvchilari (2-3 ta
 * bo'lsa) birlashtirib ko'rsatiladi.
 */
class VedomostMergeService
{
    /** Status og'irligi — eng e'tibor talab qiladigani (kichik raqam) yuqori. */
    private const STATUS_PRIORITY = [
        VedomostSubmission::STATUS_REJECTED => 0,
        VedomostSubmission::STATUS_PENDING => 1,
        VedomostSubmission::STATUS_RECEIVED => 2,
        VedomostSubmission::STATUS_REVIEWING => 3,
        VedomostSubmission::STATUS_APPROVED => 4,
    ];

    /**
     * Guruh nomidan barcha guruhcha/til/o'lcham qo'shimchalarini kesib, o'zak
     * guruhni qaytaradi. O'zak — nom boshidan birinchi "...NN-NN" gacha; undan
     * keyingi BUTUN quyruq (variant harfi, til va o'lcham teglari) tashlanadi:
     *   "d1/22-01a"               -> "d1/22-01"   (bevosita harf)
     *   "d1/22-01 (b)"            -> "d1/22-01"   (qavsli variant)
     *   "d1/22-01с"               -> "d1/22-01"   (kirill harf)
     *   "d1/21-09a (ang)"         -> "d1/21-09"   (harf + til tegi)
     *   "d1/21-09a (rus)"         -> "d1/21-09"   (boshqa til ham bitta o'zakka)
     *   "d1/21-09a (2 talik guruh)" -> "d1/21-09" (o'lcham tegi)
     */
    public function rootGroupName(?string $name): string
    {
        $name = trim((string) $name);

        // O'zak: nom boshidan birinchi "...NN-NN" bloki gacha.
        if (preg_match('~^(.*?\d+-\d+)~u', $name, $m)) {
            return trim($m[1]);
        }

        // "NN-NN" formatiga tushmasa — xavfsiz zaxira: faqat oxirgi variant belgisi.
        $name = preg_replace('/\s*\([A-Za-zА-Яа-яёЁ]\)\s*$/u', '', $name);
        $name = preg_replace('/(?<=\d)[A-Za-zА-Яа-яёЁ]$/u', '', $name);

        return trim($name);
    }

    /**
     * Fan nomidan variant qo'shimchasini ("(a)", "(б)", "(1)") kesib, o'zak fanni qaytaradi.
     */
    public function rootSubjectName(?string $name): string
    {
        return trim(preg_replace('/\s*\([A-Za-zА-Яа-яёЁ0-9]\)\s*$/u', '', trim((string) $name)));
    }

    /**
     * Bitta yozuv uchun birlashtirish kaliti — bir xil kalitli yozuvlar bitta
     * vedomost hisoblanadi.
     */
    public function mergeKey(object $row): string
    {
        return implode('|', [
            $row->education_year ?? '',
            $row->semester_code ?? '',
            $row->specialty_name ?? '',
            $row->closing_form ?? '',
            $this->rootGroupName($row->group_name ?? ''),
            $this->rootSubjectName($row->subject_name ?? ''),
        ]);
    }

    /**
     * Filtrlangan yozuvlar to'plamini o'zak guruh × o'zak fan bo'yicha jamlaydi.
     *
     * @param  iterable  $rows  vedomost_submissions qatorlari (stdClass, faculty_name/level_* bilan)
     * @return Collection<int, object>  jamlangan qatorlar
     */
    public function aggregate(iterable $rows): Collection
    {
        return collect($rows)
            ->groupBy(fn($r) => $this->mergeKey($r))
            ->map(fn(Collection $group) => $this->mergeGroup($group))
            ->values();
    }

    /**
     * Bitta o'zak guruh×fan uchun bir nechta guruhcha/variant yozuvini bitta
     * ko'rsatish obyektiga jamlaydi.
     */
    private function mergeGroup(Collection $group): object
    {
        // "Batafsil" va amallar shu vakil (eng kichik id) ustida ishlaydi.
        $rep = $group->sortBy('id')->first();

        // Eng yaqin (eng erta) muddatli yozuvni asos sana/muddat uchun olamiz.
        $byDeadline = $group->filter(fn($r) => !empty($r->deadline))->sortBy('deadline');
        $deadlineRep = $byDeadline->first() ?? $rep;

        $statusCounts = $group->groupBy('status')->map->count();
        $status = $group->pluck('status')
            ->sortBy(fn($s) => self::STATUS_PRIORITY[$s] ?? 99)
            ->first();

        $subgroups = $group->pluck('group_name')->filter()->unique()->sort()->values();

        $out = (object) [
            'id' => $rep->id,
            'ids' => $group->pluck('id')->all(),
            'merge_count' => $group->count(),
            'subgroup_names' => $subgroups->all(),
            'subgroup_label' => $subgroups->implode(', '),

            'education_year' => $rep->education_year,
            'semester_code' => $rep->semester_code,
            'group_name' => $this->rootGroupName($rep->group_name),
            'subject_name' => $this->rootSubjectName($rep->subject_name),
            'specialty_name' => $rep->specialty_name,
            'department_name' => $this->joinDistinct($group, 'department_name'),
            'faculty_name' => $rep->faculty_name ?? null,
            'closing_form' => $rep->closing_form,
            'level_name' => $rep->level_name ?? null,
            'level_code' => $rep->level_code ?? null,

            'teacher_name' => $this->joinDistinct($group, 'teacher_name'),
            'teacher_phone' => $this->joinDistinct($group, 'teacher_phone'),
            'fan_masuli_name' => $this->joinDistinct($group, 'fan_masuli_name'),
            'fan_masuli_phone' => $this->joinDistinct($group, 'fan_masuli_phone'),
            'kafedra_mudiri_name' => $this->joinDistinct($group, 'kafedra_mudiri_name'),
            'kafedra_mudiri_phone' => $this->joinDistinct($group, 'kafedra_mudiri_phone'),

            'base_type' => $deadlineRep->base_type,
            'base_date' => $deadlineRep->base_date,
            'deadline' => $deadlineRep->deadline,

            'status' => $status,
            'status_counts' => $statusCounts->all(),
            'is_mixed_status' => $statusCounts->count() > 1,
        ];

        return $out;
    }

    /**
     * Ustun bo'yicha takrorlanmas, bo'sh bo'lmagan qiymatlarni vergul bilan birlashtiradi.
     */
    private function joinDistinct(Collection $group, string $field): ?string
    {
        $vals = $group->pluck($field)
            ->map(fn($v) => $v !== null ? trim((string) $v) : '')
            ->filter(fn($v) => $v !== '')
            ->unique()
            ->values();

        return $vals->isEmpty() ? null : $vals->implode(', ');
    }

    /**
     * Berilgan vedomost bilan bitta o'zak guruh×fan guruhiga tegishli barcha
     * yozuvlarni (o'zini ham) qaytaradi. Amallar (yuklash/tasdiqlash/rad etish)
     * shu guruhdagi barcha yozuvlarga birdek qo'llanadi — chunki bu BITTA vedomost.
     *
     * @return Collection<int, VedomostSubmission>
     */
    public function siblingsOf(VedomostSubmission $v): Collection
    {
        $query = VedomostSubmission::query()
            // Faqat FAOL guruhlar — index ro'yxati bilan mos bo'lishi uchun.
            ->whereIn('group_hemis_id', function ($q) {
                $q->select('group_hemis_id')->from('groups')->where('active', true);
            })
            ->where('education_year', $v->education_year)
            ->where('semester_code', $v->semester_code)
            ->where('closing_form', $v->closing_form);

        if ($v->specialty_name === null) {
            $query->whereNull('specialty_name');
        } else {
            $query->where('specialty_name', $v->specialty_name);
        }

        $rootGroup = $this->rootGroupName($v->group_name);
        $rootSubject = $this->rootSubjectName($v->subject_name);

        return $query->get()->filter(function (VedomostSubmission $row) use ($rootGroup, $rootSubject) {
            return $this->rootGroupName($row->group_name) === $rootGroup
                && $this->rootSubjectName($row->subject_name) === $rootSubject;
        })->values();
    }
}
