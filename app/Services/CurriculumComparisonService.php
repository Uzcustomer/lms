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
 *
 * TANLOV FANLARI alohida qoida bo'yicha solishtiriladi: namunaviy rejada
 * blok ostida bir nechta muqobil fan turadi ("2.03 Hayot faoliyati xavfsizligi
 * YOKI Bioetika"), ishchi rejada esa ulardan faqat bittasi — aniq tanlangani —
 * bo'ladi. Bunday bloklar fan emas, BLOK kesimida solishtiriladi
 * (@see choiceGroups), shunda tanlanmagan muqobillar "Ishchi rejada yo'q"
 * bo'lib xato ogohlantirish bermaydi va jami soat/kredit oshib ketmaydi.
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
    public const STATUS_CHOICE_DIFF = 'Tanlov farqi';

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
            // Setka formatida ko'p semestrli fanning umumiy soati faqat BIRINCHI
            // semestr qatorida saqlanadi (masalan, 3-semestrda 120 soat, keyingi
            // semestr qatorlarida soat bo'sh). Agar qatorlarni semestr bo'yicha
            // alohida filtrlasak — qamrab olingan semestrga tushmagan, ammo aynan
            // soatni saqlab turgan qator yo'qolib, "Nam. soat" ustuni bo'sh ("—")
            // chiqadi. Shuning uchun avval fan NOMI bo'yicha qaysi fanlar qamrab
            // olingan semestrda uchrashini aniqlaymiz, so'ng shu fanlarning BARCHA
            // qatorlarini (boshqa semestrdagi soat qatori bilan birga) saqlaymiz.
            // Bundan tashqari ishchi rejada mavjud bo'lgan fanlar ham saqlanadi —
            // hatto namunaviyda boshqa semestrda turgan bo'lsa ham (semestr farqi
            // holati): shunda fan tushib qolmaydi, balki farq bilan ko'rsatiladi.
            $workNames = [];
            foreach ($workSubjects as $s) {
                $workNames[$this->normalize((string) ($s->reference_name ?: $s->subject_name))] = true;
                $workNames[$this->normalize((string) $s->subject_name)] = true;
            }
            $coveredNames = [];
            foreach ($refSubjects as $s) {
                $sem = ($s->semester !== null && $s->semester !== '') ? (int) $s->semester : null;
                if ($sem === null || in_array($sem, $covered, true)) {
                    $coveredNames[$this->normalize((string) $s->subject_name)] = true;
                }
            }
            $keep = fn($s) => isset($coveredNames[$this->normalize((string) $s->subject_name)])
                || isset($workNames[$this->normalize((string) $s->subject_name)]);

            // Tanlov blokining bir muqobili qamrovga tushsa — blokdagi qolgan
            // muqobillar ham saqlanadi, aks holda blok yakka fanga aylanib
            // qolib, tanlanmagani "Ishchi rejada yo'q" bo'lib ko'rinardi.
            // Faqat tanlov bloklariga tegishli: majburiy fanlar bloki
            // qamrovdan tashqari semestrlarni qaytarib olib kelmasligi kerak.
            $keptBlocks = [];
            foreach ($refSubjects as $s) {
                $block = trim((string) $s->block);
                if ($block !== '' && $this->looksLikeChoiceBlock($block) && $keep($s)) {
                    $keptBlocks[$block] = true;
                }
            }

            $refSubjects = $refSubjects->filter(
                fn($s) => $keep($s) || isset($keptBlocks[trim((string) $s->block)])
            )->values();
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

        // Tanlov bloklari: har bir blok bitta qator bo'lib chiqadi, muqobillari
        // esa shu qator ichida sanaladi. Blokka tegishli fan kalitlari alohida
        // qator sifatida chiqmasligi uchun oldindan xaritaga olinadi.
        $choices = $this->choiceGroups($refGroups);
        $choiceOf = [];
        foreach ($choices as $block => $choice) {
            foreach ($choice['keys'] as $key) {
                $choiceOf[$key] = $block;
            }
        }
        $emitted = [];

        $rows = [];
        foreach ($refGroups as $key => $ref) {
            $block = $choiceOf[$key] ?? null;
            if ($block !== null) {
                // Blok qatori faqat bir marta — birinchi muqobil uchraganda
                if (isset($emitted[$block])) {
                    continue;
                }
                $emitted[$block] = true;
                $rows[] = $this->buildChoiceRow($choices[$block], $refGroups, $workGroups, $hemisMap);
                continue;
            }
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
     *  - takror (semestr + fan nomi) juftliklari bir marta olinadi, eng oxirgi
     *    yuklangan reja ustunlik qiladi (shu bilan batch nusxalar ikki marta
     *    hisoblanmaydi);
     *  - fan qaysi semestr qatorida bo'lsa ham JAMLANGAN ro'yxatga kiritiladi —
     *    hatto yozuv yorlig'i (semester_code) boshqa semestrni ko'rsatsa ham.
     *    Chunki bir fayl ko'pincha bir necha semestrni o'z ichiga oladi (masalan
     *    7- va 8-semestr birga), fan esa 8-semestr yozuvida bo'lsa ham aslida
     *    7-semestrda turishi mumkin. Filtrlab tashlansa, u fan "Ishchi rejada
     *    yo'q" bo'lib noto'g'ri ko'rinardi;
     *  - "qamrab olingan semestrlar" esa yozuvlarning semester_code'i bo'yicha
     *    aniqlanadi (tasodifiy boshqa-semestr qatori butun semestrni "yuklangan"
     *    deb belgilab qo'ymasligi uchun);
     *  - har bir reja xulosasi (fanlar/soat/kredit) yozuvning O'Z semestriga
     *    tegishli qatorlar bo'yicha hisoblanadi.
     *
     * @return array{0: Collection, 1: array<int>, 2: array<int, array>}
     *         [jamlangan fanlar, qamrab olingan semestrlar, har bir reja bo'yicha xulosa]
     */
    private function mergeWorkingSubjects(Collection $workings): array
    {
        $seen = [];
        $combined = collect();
        $coveredSet = [];
        $plans = [];

        // Eng oxirgi yuklangan reja takror qatorlarda ustun bo'lishi uchun ID kamayish tartibida
        foreach ($workings->sortByDesc('id')->values() as $working) {
            $recSem = self::semesterNumber($working->semester_code);
            $ownCount = 0;
            $ownHours = 0.0;
            $ownCredit = 0.0;

            foreach ($working->subjects()->orderBy('id')->get() as $s) {
                $rowSem = ($s->semester !== null && $s->semester !== '') ? (int) $s->semester : null;

                // Reja xulosasi: yozuvning o'z semestriga tegishli qatorlar
                // (semester_code yo'q bo'lsa — barcha qatorlar).
                if ($recSem === null || $rowSem === null || $rowSem === $recSem) {
                    $ownCount++;
                    $ownHours += (float) ($s->total_hours ?? 0);
                    $ownCredit += (float) ($s->credit ?? 0);
                }

                // Jamlangan ro'yxat: BARCHA qatorlar (semestr mos kelmasa ham).
                // Takror (semestr + nom / nomsiz) juftliklari bir marta olinadi.
                $name = $this->normalize($s->reference_name ?: $s->subject_name);
                $key = $rowSem !== null
                    ? 's' . $rowSem . '|' . $name
                    : 'n|' . $name;
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $combined->push($s);

                // Rejalashtirilgan (semester_code'siz) yozuvda qamrovni qator
                // semestridan olamiz.
                if ($recSem === null && $rowSem !== null) {
                    $coveredSet[$rowSem] = true;
                }
            }

            // Asosiy qamrov manbai — yozuvning semester_code'i (ishchi reja
            // yuklangan semestr). Tasodifiy boshqa-semestr qatorlari qamrovni
            // kengaytirmaydi.
            if ($recSem !== null) {
                $coveredSet[$recSem] = true;
            }

            $plans[] = [
                'id'            => $working->id,
                'name'          => $working->name,
                'plan_year'     => $working->plan_year,
                'semester'      => $recSem,
                'semester_code' => $working->semester_code,
                'subjects'      => $ownCount,
                'hours'         => round($ownHours, 2),
                'credit'        => round($ownCredit, 2),
            ];
        }

        $covered = array_keys($coveredSet);
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

        // Semestr farqi: fan namunaviy va ishchi rejada turli semestrlarda turgan
        // bo'lsa — ogohlantirish. (Ilgari mos kelmaydigan semestr fanni umuman
        // tushirib qoldirardi; endi fan ko'rsatiladi va farq belgilanadi.)
        $refSems = $ref['semestrlar'] ?? [];
        $workSems = $work['semestrlar'] ?? [];
        $semesterDiffers = $ref !== null && $work !== null
            && !empty($refSems) && !empty($workSems)
            && array_map('intval', $refSems) !== array_map('intval', $workSems);
        if ($semesterDiffers) {
            $notes[] = 'Semestr farqi: namunaviyda ' . implode(',', $refSems)
                . '-semestr, ishchida ' . implode(',', $workSems) . '-semestr';
        }

        return [
            'block' => $ref['block'] ?? $work['block'] ?? null,
            'choice' => false,
            'choice_alts' => [],
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
            'ref_semestrlar' => implode(',', $refSems),
            'work_semestrlar' => implode(',', $workSems),
            'semester_differs' => $semesterDiffers,
            'status' => $status,
            'note' => implode('; ', array_unique($notes)),
        ];
    }

    /**
     * Namunaviy rejadagi TANLOV bloklarini aniqlaydi.
     *
     * Tanlov bloki ikki ko'rinishda keladi:
     *  1) muqobillar blok sarlavhasining o'zida sanaladi —
     *     "2.03 Hayot faoliyati xavfsizligi YOKI Bioetika";
     *  2) sarlavha umumiy, muqobillar esa uning ostidagi alohida fan qatorlari —
     *     "2.05 Harbiy tibbiy tayyorgarlik" ostida ikkita fan.
     *
     * Ikkinchi holat faqat ichki kodli bloklar uchun ("2.05" kabi, "2.00" emas)
     * qo'llanadi: "1.00 MAJBURIY FANLAR" ostidagi o'nlab fan tanlov emas, ular
     * hammasi o'tiladi.
     *
     * @param  array<string, array>  $refGroups  groupSubjects() natijasi
     * @return array<string, array{label:string, code:?string, alts:array<string,string>, keys:string[]}>
     */
    private function choiceGroups(array $refGroups): array
    {
        $byBlock = [];
        foreach ($refGroups as $key => $group) {
            $block = trim((string) ($group['block'] ?? ''));
            if ($block !== '') {
                $byBlock[$block][] = $key;
            }
        }

        $choices = [];
        foreach ($byBlock as $block => $keys) {
            $labelAlts = $this->splitChoiceLabel($block);
            $isSubBlock = (bool) preg_match('/^\s*\d+\.(?!0+\b)\d+/u', $block);
            if ($labelAlts === [] && !($isSubBlock && count($keys) > 1)) {
                continue; // tanlov bloki emas
            }

            // Muqobillar: sarlavhadagi nomlar + blok ostidagi fan qatorlari.
            // Kalit — normalizatsiya qilingan nom (groupSubjects bilan bir xil).
            $alts = [];
            foreach ($labelAlts as $name) {
                $alts[$this->normalize($name)] = $name;
            }
            foreach ($keys as $key) {
                $alts[$key] = $refGroups[$key]['name'];
            }
            if (count($alts) < 2) {
                continue; // muqobili yo'q — oddiy fan
            }

            $choices[$block] = [
                'label' => $block,
                'code'  => $this->blockCode($block),
                'alts'  => $alts,
                'keys'  => $keys,
            ];
        }

        return $choices;
    }

    /**
     * Tanlov bloki uchun bitta solishtirma qatori.
     *
     * Ishchi rejadagi fan avval muqobil NOMLARI bo'yicha, topilmasa blok kodi
     * bo'yicha moslanadi. Norma soat/kredit aynan tanlangan muqobilnikidan
     * olinadi — shu bois tanlanmagan muqobillar na farq, na jamiga qo'shiladi.
     *
     * @param  array<string, array>  $refGroups
     * @param  array<string, array>  $workGroups  moslangan fanlar chiqarib tashlanadi
     */
    private function buildChoiceRow(array $choice, array $refGroups, array &$workGroups, array $hemisMap): array
    {
        // 1) Muqobil nomlari bo'yicha moslash
        $matched = [];
        foreach (array_keys($choice['alts']) as $altKey) {
            if (isset($workGroups[$altKey])) {
                $matched[$altKey] = $workGroups[$altKey];
                unset($workGroups[$altKey]);
            }
        }

        // 2) Nom bo'yicha topilmasa — ishchi rejadagi blok kodi/sarlavhasi
        //    bo'yicha. Bunda tanlangan fan namunaviy muqobillar ro'yxatida yo'q.
        $outside = false;
        if ($matched === []) {
            foreach ($workGroups as $wKey => $wGroup) {
                if (!$this->sameBlock($choice, (string) ($wGroup['block'] ?? ''))) {
                    continue;
                }
                $matched[$wKey] = $wGroup;
                unset($workGroups[$wKey]);
                $outside = true;
            }
        }

        // Norma: tanlangan muqobil(lar)ning namunaviy qatori. Tanlangani
        // namunaviyda alohida qator bilan kelmasa (nomi faqat blok sarlavhasida
        // bo'lsa) — blokdagi soat/kreditli birinchi muqobil norma bo'ladi.
        $normKeys = array_values(array_filter(array_keys($matched), fn($k) => isset($refGroups[$k])));
        if ($normKeys === []) {
            $normKeys = [$this->representativeAlt($choice, $refGroups)];
        }

        $ref = $this->mergeGroups(array_map(fn($k) => $refGroups[$k], $normKeys), $choice['label']);
        $work = $this->mergeGroups(array_values($matched), $choice['label']);

        $row = $this->buildRow($ref, $work, $hemisMap);
        $row['choice'] = true;
        $row['choice_alts'] = array_values($choice['alts']);

        // Blok kodi bo'yicha moslangan, ammo namunaviy ro'yxatda yo'q fan —
        // soat/kredit farqi bo'lmasa ham alohida holat sifatida belgilanadi.
        if ($outside && in_array($row['status'], [self::STATUS_OK, self::STATUS_NAME], true)) {
            $row['status'] = self::STATUS_CHOICE_DIFF;
        }

        $notes = ['Tanlov bloki: ' . implode(' / ', $row['choice_alts'])];
        if ($work === null) {
            $notes[] = "ishchi rejada birorta muqobil tanlanmagan";
        } else {
            $notes[] = "ishchida tanlangan: {$work['name']}";
        }
        if ($outside) {
            $notes[] = "tanlangan fan namunaviy muqobillar ro'yxatida yo'q";
        }
        if ($row['note'] !== '') {
            $notes[] = $row['note'];
        }
        $row['note'] = implode('; ', $notes);

        return $row;
    }

    /**
     * Blok sarlavhasidan muqobil fan nomlarini ajratish. Sarlavhada "YOKI"
     * bo'lmasa — bo'sh massiv (bu oddiy blok sarlavhasi).
     *
     * @return string[]
     */
    private function splitChoiceLabel(string $block): array
    {
        // Boshidagi tartib kodini ("2.03", "2.03." va h.k.) olib tashlaymiz
        $text = preg_replace('/^\s*\d+(?:\.\d+)*[\s.)\-–]*/u', '', trim($block));
        $parts = preg_split('/\s+(?:yoki|yohud|yoxud)\s+/iu', $text);
        if (count($parts) < 2) {
            return [];
        }

        return array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
    }

    /**
     * Blok sarlavhasi tanlov blokiga o'xshaydimi: "YOKI" ajratuvchisi bor yoki
     * ichki kodli ("2.03" kabi, "2.00" emas). choiceGroups() dan farqli — bu
     * yerda muqobillar soni tekshirilmaydi, chunki filtrlashdan OLDIN chaqiriladi.
     */
    private function looksLikeChoiceBlock(string $block): bool
    {
        return $this->splitChoiceLabel($block) !== []
            || (bool) preg_match('/^\s*\d+\.(?!0+\b)\d+/u', $block);
    }

    /** Blok sarlavhasi boshidagi tartib kodi ("2.03"), bo'lmasa null. */
    private function blockCode(string $block): ?string
    {
        return preg_match('/^\s*(\d+(?:\.\d+)+)/u', $block, $m) ? $m[1] : null;
    }

    /** Ishchi rejadagi blok shu tanlov blokiga tegishlimi (kod yoki sarlavha bo'yicha). */
    private function sameBlock(array $choice, string $workBlock): bool
    {
        if (trim($workBlock) === '') {
            return false;
        }
        if ($choice['code'] !== null && $this->blockCode($workBlock) === $choice['code']) {
            return true;
        }

        return $this->normalize($workBlock) === $this->normalize($choice['label']);
    }

    /**
     * Tanlangani aniqlanmagan blok uchun norma manbai: soat/krediti bor birinchi
     * muqobil, bunday muqobil bo'lmasa — blokdagi birinchi fan.
     */
    private function representativeAlt(array $choice, array $refGroups): string
    {
        foreach ($choice['keys'] as $key) {
            $group = $refGroups[$key] ?? null;
            if ($group && (($group['hours'] ?? null) !== null || ($group['credit'] ?? null) !== null)) {
                return $key;
            }
        }

        return $choice['keys'][0];
    }

    /**
     * Bir nechta fan guruhini bitta qatorga jamlash (tanlov blokida bir necha
     * muqobil bir vaqtda topilgan holat uchun). Bo'sh ro'yxatda null.
     *
     * @param  array<int, array>  $groups
     */
    private function mergeGroups(array $groups, string $block): ?array
    {
        if ($groups === []) {
            return null;
        }

        $merged = [
            'name' => '',
            'block' => $block,
            'hours' => null,
            'credit' => null,
            'kurslar' => [],
            'semestrlar' => [],
            'notes' => [],
        ];
        $names = [];
        foreach ($groups as $group) {
            $names[] = $group['name'];
            if (($group['hours'] ?? null) !== null) {
                $merged['hours'] = round(($merged['hours'] ?? 0) + (float) $group['hours'], 2);
            }
            if (($group['credit'] ?? null) !== null) {
                $merged['credit'] = round(($merged['credit'] ?? 0) + (float) $group['credit'], 2);
            }
            $merged['kurslar'] = array_merge($merged['kurslar'], $group['kurslar'] ?? []);
            $merged['semestrlar'] = array_merge($merged['semestrlar'], $group['semestrlar'] ?? []);
            $merged['notes'] = array_merge($merged['notes'], $group['notes'] ?? []);
        }

        $merged['name'] = implode(' / ', array_unique($names));
        $merged['kurslar'] = array_values(array_unique($merged['kurslar']));
        $merged['semestrlar'] = array_values(array_unique($merged['semestrlar']));
        $merged['notes'] = array_values(array_unique($merged['notes']));
        sort($merged['kurslar']);
        sort($merged['semestrlar']);

        return $merged;
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
