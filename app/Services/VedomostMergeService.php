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
     * O'zak guruh nomini birlashtirish uchun NORMALIZATSIYA qiladi: nuqta, bo'shliq
     * kabi nomuvofiq belgilarni olib tashlab, kichik harfga keltiradi. HEMIS'da
     * bitta kohortaning guruhchalari turlicha yozilishi mumkin:
     *   "tibprof23-02a"  -> o'zak "tibprof23-02"  -> normal "tibprof23-02"
     *   "tib.prof23-02b" -> o'zak "tib.prof23-02" -> normal "tibprof23-02"
     * Shunda nuqtali/nuqtasiz variantlar BITTA vedomostga birlashadi.
     */
    public function normalizedRootGroup(?string $name): string
    {
        return mb_strtolower(str_replace([' ', '.'], '', $this->rootGroupName($name)));
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
     *
     * 12-shakl: o'zak guruh kalitga kiradi (har guruh alohida varaq).
     * 12a/12b : o'zak guruh kalitdan CHIQARILADI, lekin FAKULTET (reja) saqlanadi —
     *           yo'nalish × fan bo'yicha bitta FAKULTETning barcha guruhlari bitta
     *           umumiy varaqqa jamlanadi (har fakultet alohida varaq).
     */
    public function mergeKey(object $row): string
    {
        $formType = $row->form_type ?? VedomostSubmission::FORM_12;
        $isCombined = in_array($formType, VedomostSubmission::COMBINED_FORMS, true);

        return implode('|', [
            $formType,
            $row->education_year ?? '',
            $row->semester_code ?? '',
            $row->specialty_name ?? '',
            $row->closing_form ?? '',
            // 12a/12b — guruh o'rniga fakultet (reja) belgisi; 12 — o'zak guruh.
            $isCombined ? ('*' . ($row->curriculum_hemis_id ?? '')) : $this->normalizedRootGroup($row->group_name ?? ''),
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

        $formType = $rep->form_type ?? VedomostSubmission::FORM_12;
        $isCombined = in_array($formType, VedomostSubmission::COMBINED_FORMS, true);

        $subgroups = $group->pluck('group_name')->filter()->unique()->sort()->values();

        // 12a/12b — umumiy varaq: "Guruh" ustuni umumlashtiriladi.
        // 12-shakl — o'zak guruh nomi (guruchalar kesilgan).
        $groupNameOut = $isCombined
            ? ($rep->group_name ?: 'Barcha guruhlar')
            : $this->rootGroupName($rep->group_name);

        // Ism va telefonni MOSLAB chiqaramiz: "A, B, C" -> "-, +998.., +998.."
        // (telefoni yo'q o'qituvchi o'rnida "-").
        $teacher = $this->joinPersons($group, 'teacher_name', 'teacher_phone');
        $fanMasuli = $this->joinPersons($group, 'fan_masuli_name', 'fan_masuli_phone');
        $kafedraMudiri = $this->joinPersons($group, 'kafedra_mudiri_name', 'kafedra_mudiri_phone');

        $out = (object) [
            'id' => $rep->id,
            'ids' => $group->pluck('id')->all(),
            'merge_count' => $group->count(),
            'subgroup_names' => $subgroups->all(),
            'subgroup_label' => $subgroups->implode(', '),

            'education_year' => $rep->education_year,
            'semester_code' => $rep->semester_code,
            'group_name' => $groupNameOut,
            'subject_name' => $this->rootSubjectName($rep->subject_name),
            'specialty_name' => $rep->specialty_name,
            'department_name' => $this->joinDistinct($group, 'department_name'),
            'faculty_name' => $rep->faculty_name ?? null,
            'closing_form' => $rep->closing_form,
            'form_type' => $formType,
            'level_name' => $rep->level_name ?? null,
            'level_code' => $rep->level_code ?? null,

            'teacher_name' => $teacher['names'],
            'teacher_phone' => $teacher['phones'],
            'fan_masuli_name' => $fanMasuli['names'],
            'fan_masuli_phone' => $fanMasuli['phones'],
            'kafedra_mudiri_name' => $kafedraMudiri['names'],
            'kafedra_mudiri_phone' => $kafedraMudiri['phones'],

            'base_type' => $deadlineRep->base_type,
            'base_date' => $deadlineRep->base_date,
            'deadline' => $deadlineRep->deadline,

            // Rad etilish bildirgilari (inbox) uchun — vakil yozuv qiymatlari.
            // Rad etishda barcha guruhchalarga bir xil reviewed_at/sabab yoziladi.
            'reviewed_at' => $rep->reviewed_at ?? null,
            'reviewed_by_name' => $rep->reviewed_by_name ?? null,
            'rejection_reason' => $rep->rejection_reason ?? null,
            'reupload_allowed_at' => $rep->reupload_allowed_at ?? null,
            'reupload_allowed_by_name' => $rep->reupload_allowed_by_name ?? null,

            'status' => $status,
            'status_counts' => $statusCounts->all(),
            'is_mixed_status' => $statusCounts->count() > 1,
        ];

        return $out;
    }

    /**
     * Ism + telefonni MOSLAB chiqaradi. Har bir takrorlanmas ism uchun (guruhcha
     * o'qituvchilari) uning telefoni topiladi; telefoni yo'q bo'lsa "-" qo'yiladi.
     * Ikkala ro'yxat bir xil tartibda (ksort) — ustunlar bir-biriga to'g'ri keladi.
     *
     *   names  = "Aliyev, Valiyev, Hasanov"
     *   phones = "-, +998901112233, +998931234567"
     *
     * @return array{names: ?string, phones: ?string}
     */
    private function joinPersons(Collection $group, string $nameField, string $phoneField): array
    {
        $map = []; // ism => telefon
        foreach ($group as $r) {
            $name = trim((string) ($r->{$nameField} ?? ''));
            if ($name === '') {
                continue;
            }
            $phone = trim((string) ($r->{$phoneField} ?? ''));
            // Ism birinchi marta uchrasa yoki avval telefoni bo'sh bo'lib endi topilsa.
            if (!array_key_exists($name, $map) || ($map[$name] === '' && $phone !== '')) {
                $map[$name] = $phone;
            }
        }

        if (empty($map)) {
            return ['names' => null, 'phones' => null];
        }

        ksort($map, SORT_NATURAL | SORT_FLAG_CASE);
        $names = implode(', ', array_keys($map));
        $phones = implode(', ', array_map(fn($p) => $p !== '' ? $p : '-', array_values($map)));

        return ['names' => $names, 'phones' => $phones];
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
        $formType = $v->form_type ?? VedomostSubmission::FORM_12;
        $isCombined = in_array($formType, VedomostSubmission::COMBINED_FORMS, true);

        $query = VedomostSubmission::query()
            // Faqat FAOL guruhlar — index ro'yxati bilan mos bo'lishi uchun.
            ->whereIn('group_hemis_id', function ($q) {
                $q->select('group_hemis_id')->from('groups')->where('active', true);
            })
            ->where('education_year', $v->education_year)
            ->where('semester_code', $v->semester_code)
            ->where('closing_form', $v->closing_form)
            ->where('form_type', $formType);

        if ($v->specialty_name === null) {
            $query->whereNull('specialty_name');
        } else {
            $query->where('specialty_name', $v->specialty_name);
        }

        // 12a/12b — har fakultet alohida varaq: bir xil reja (fakultet) doirasida.
        if ($isCombined) {
            $query->where('curriculum_hemis_id', $v->curriculum_hemis_id);
        }

        $rootGroup = $this->normalizedRootGroup($v->group_name);
        $rootSubject = $this->rootSubjectName($v->subject_name);

        return $query->get()->filter(function (VedomostSubmission $row) use ($isCombined, $rootGroup, $rootSubject) {
            // 12a/12b — guruh sharti yo'q (hamma guruh bitta vedomost).
            $groupMatch = $isCombined || $this->normalizedRootGroup($row->group_name) === $rootGroup;

            return $groupMatch
                && $this->rootSubjectName($row->subject_name) === $rootSubject;
        })->values();
    }
}
