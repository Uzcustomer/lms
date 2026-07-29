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
 * FAN GURUHLARI alohida qoida bo'yicha solishtiriladi. Ikki rejada fanlar
 * har xil bo'linib kelishi mumkin:
 *
 *  - namunaviyda tanlov bloki ("2.03 Hayot faoliyati xavfsizligi YOKI
 *    Bioetika"), ishchida esa faqat tanlangani;
 *  - namunaviyda birikkan fan ("Ichki kasalliklar. Endokrinologiya"),
 *    ishchida esa ikkita alohida fan ("Ichki kasalliklar", "Endokrinologiya").
 *
 * Ikkala holda ham nom bo'yicha yakka moslash ishlamaydi. Shuning uchun
 * bunday fanlar GURUH kesimida solishtiriladi (@see manualGroups qo'lda,
 * @see detectGroups avtomatik): guruh bitta qator bo'lib chiqadi, tarkibiy
 * fanlar shu qator ichida sanaladi, jami soat/kredit esa ikki marta
 * hisoblanmaydi.
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
    public const STATUS_GROUP_DIFF = 'Guruh farqi';

    public function compare(
        ManualCurriculum $reference,
        ManualCurriculum $working,
        array $hemisNames = [],
        array $manualChoices = []
    ): array {
        return $this->run(
            $reference->subjects()->orderBy('id')->get(),
            $working->subjects()->orderBy('id')->get(),
            $hemisNames,
            $manualChoices
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
    public function compareGroup(
        ManualCurriculum $reference,
        Collection $workings,
        array $hemisNames = [],
        array $manualChoices = []
    ): array {
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

            // Qo'lda belgilangan guruhlar uchun ham xuddi shunday: guruhning bir
            // muqobili qamrovga tushsa, qolgan muqobillari ham saqlanadi.
            $keptNames = [];
            foreach ($manualChoices as $manual) {
                $names = array_map(fn($n) => $this->normalize((string) $n), $manual['ref_names'] ?? []);
                $hit = array_filter($names, fn($n) => isset($coveredNames[$n]) || isset($workNames[$n]));
                if ($hit !== []) {
                    foreach ($names as $n) {
                        $keptNames[$n] = true;
                    }
                }
            }

            $refSubjects = $refSubjects->filter(
                fn($s) => $keep($s)
                    || isset($keptBlocks[trim((string) $s->block)])
                    || isset($keptNames[$this->normalize((string) $s->subject_name)])
            )->values();
        }

        $result = $this->run($refSubjects, $workSubjects, $hemisNames, $manualChoices);
        $result['covered_semesters'] = $covered;
        $result['plans'] = $plans;

        return $result;
    }

    private function run(
        Collection $refSubjects,
        Collection $workSubjects,
        array $hemisNames = [],
        array $manualChoices = []
    ): array {
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

        // Tanlov guruhlari: har bir guruh bitta qator bo'lib chiqadi, muqobillari
        // esa shu qator ichida sanaladi. Guruhga tegishli fan kalitlari alohida
        // qator sifatida chiqmasligi uchun oldindan xaritaga olinadi.
        //
        // Qo'lda belgilangan guruhlar avtomatik aniqlashdan USTUN: reja bo'yicha
        // birorta guruh qo'lda kiritilgan bo'lsa, avtomatik topish umuman
        // ishlamaydi — foydalanuvchi ro'yxati to'liq hisoblanadi. Namunaviy va
        // ishchi rejalarda tanlov fanlari butunlay boshqacha yozilishi mumkin
        // ("A YOKI B" bloki ↔ bitta "A / B" qatori), avtomatik moslash esa
        // bunday hollarda ishlamaydi.
        $choices = $manualChoices !== []
            ? $this->manualGroups($manualChoices, $refGroups)
            : $this->detectGroups($refGroups);
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
                $rows[] = $this->buildGroupRow($choices[$block], $refGroups, $workGroups, $hemisMap);
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

        // Namunaviy nomi HEMIS'da bo'lmasa, ishchi nomi bo'yicha ham qaraymiz:
        // HEMIS ishchi rejani aks ettiradi, ikkovi har xil yozilishi mumkin.
        if ($hemisName === null && $refName !== null && $workName !== null) {
            $hemisName = $hemisMap[$this->normalize($workName)] ?? null;
        }

        // Birikkan guruhda "A / B" nomi — bizning yasama satrimiz, HEMIS'da
        // bunday yozuv yo'q. Uni HEMIS nomi bilan solishtirish ma'nosiz, shu
        // bois tomon "farq qiladi" deb ayblanmaydi (null).
        $refSplit = count($ref['parts'] ?? []) > 1;
        $workSplit = count($work['parts'] ?? []) > 1;

        $refMatchesHemis = ($hemisName !== null && $refName !== null && !$refSplit)
            ? ($this->collapse($refName) === $hemisName) : null;
        $workMatchesHemis = ($hemisName !== null && $workName !== null && !$workSplit)
            ? ($this->collapse($workName) === $hemisName) : null;

        // HEMIS'da fan yaxlit turgan, rejada esa bo'lingan holat — kamchilik
        // emas, ammo bilib turish uchun izohda qayd etiladi.
        if ($hemisName !== null && ($refSplit || $workSplit)) {
            $notes[] = "HEMIS'da yaxlit yozilgan: {$hemisName}";
        }

        // Butun nom HEMIS'da topilmasa — tarkibiy fanlar bittalab qidiriladi
        // (HEMIS'da fanlar alohida turgan holat).
        //
        // MUHIM: topilgan nomlar BIRLASHTIRILMAYDI. Ilgari ular " / " bilan
        // qo'shilib bitta qator qilib ko'rsatilardi va HEMIS'da umuman
        // mavjud bo'lmagan nom ("Xirurgik kasalliklar / Urologiya") HEMIS
        // nomi sifatida chiqardi. Endi har bir HEMIS yozuvi o'z holicha,
        // alohida qator bo'lib ko'rsatiladi.
        $missingInHemis = [];
        $hemisParts = [];
        if ($hemisName === null && $hemisMap !== [] && ($workSplit || $refSplit)) {
            $parts = array_column($workSplit ? $work['parts'] : $ref['parts'], 'name');
            foreach ($parts as $part) {
                $found = $hemisMap[$this->normalize((string) $part)] ?? null;
                $hemisParts[] = ['name' => trim((string) $part), 'hemis' => $found];
                if ($found === null) {
                    $missingInHemis[] = trim((string) $part);
                }
            }
            // Birortasi ham topilmasa — qism-qism yechim ishlamadi
            if (count($missingInHemis) === count($parts)) {
                $hemisParts = [];
                $missingInHemis = [];
            }
            $workMatchesHemis = $hemisParts !== [] && $workSplit ? ($missingInHemis === []) : null;
        }

        // HEMIS'da fan aniqlandimi — butun nom bo'yicha yoki tarkibiy fanlar bo'yicha
        $hemisFound = $hemisName !== null || $hemisParts !== [];

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
                ? (!$hemisFound || $refMatchesHemis === false || $workMatchesHemis === false)
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
        if (!$hemisFound && ($refName !== null || $workName !== null)) {
            $notes[] = "HEMIS bazasida fan topilmadi";
        }
        if ($missingInHemis !== []) {
            $notes[] = 'HEMIS bazasida topilmagan fan(lar): ' . implode(', ', $missingInHemis);
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
            'group' => false,
            'group_manual' => false,
            'group_parts' => [],
            'ref_parts' => $ref['parts'] ?? [],
            'work_parts' => $work['parts'] ?? [],
            'hemis_name' => $hemisName,
            'hemis_parts' => $hemisParts,
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
     * Muharrir uchun boshlang'ich takliflar: namunaviy rejadagi blok
     * sarlavhalaridan avtomatik topilgan tanlov guruhlari.
     *
     * Bu faqat TAKLIF — foydalanuvchi tahrirlab, ishchi rejadagi mos fanni
     * ko'rsatib saqlaguncha solishtirishga ta'sir qilmaydi.
     *
     * @return array<int, array{label:string, ref_names:string[], work_names:string[], norm_name:?string}>
     */
    public function suggestGroups(Collection $refSubjects): array
    {
        $refGroups = $this->groupSubjects($refSubjects, false);

        $out = [];
        foreach ($this->detectGroups($refGroups) as $choice) {
            $out[] = [
                'label'      => $choice['label'],
                'ref_names'  => array_values($choice['alts']),
                'work_names' => [],
                'norm_name'  => $refGroups[$this->representativeAlt($choice, $refGroups)]['name'] ?? null,
            ];
        }

        return $out;
    }

    /**
     * Qo'lda kiritilgan fan guruhlarini solishtirish shakliga o'tkazadi.
     *
     * Avtomatik aniqlashdan farqi: namunaviy fanlar ham, ularga mos ishchi
     * fan(lar) ham aniq ko'rsatilgan — nom yoki blok kodi bo'yicha taxmin
     * qilinmaydi. Shu bois har qanday bo'linish bog'lanadi: tanlov bloki ham
     * ("A YOKI B" ↔ bitta "A / B" qatori), birikkan fan ham ("A. B" ↔ ikkita
     * alohida "A" va "B" qatori).
     *
     * Namunaviy rejada birorta tarkibiy fani topilmagan guruh o'tkazib
     * yuboriladi — solishtirishga qo'shadigan hissasi yo'q.
     *
     * @param  array<int, array{label:string, ref_names:string[], work_names:string[], norm_name?:?string}>  $manual
     * @param  array<string, array>  $refGroups
     * @return array<string, array>
     */
    private function manualGroups(array $manual, array $refGroups): array
    {
        $groups = [];
        foreach ($manual as $i => $item) {
            $alts = [];
            $keys = [];
            foreach ($item['ref_names'] ?? [] as $name) {
                $key = $this->normalize((string) $name);
                if ($key === '') {
                    continue;
                }
                // Ko'rsatiladigan nom: namunaviydagi asl yozilishi (bo'lsa)
                $alts[$key] = $refGroups[$key]['name'] ?? trim((string) $name);
                if (isset($refGroups[$key])) {
                    $keys[] = $key;
                }
            }
            if ($keys === []) {
                continue;
            }

            $work = [];
            foreach ($item['work_names'] ?? [] as $name) {
                $key = $this->normalize((string) $name);
                if ($key !== '') {
                    $work[] = $key;
                }
            }

            $norm = $this->normalize((string) ($item['norm_name'] ?? ''));

            $groups['manual-' . $i] = [
                'label'  => trim((string) ($item['label'] ?? '')) ?: implode(' / ', $alts),
                'code'   => null,
                'alts'   => $alts,
                'keys'   => $keys,
                'work'   => $work,
                'norm'   => isset($refGroups[$norm]) ? $norm : null,
                'manual' => true,
            ];
        }

        return $groups;
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
    private function detectGroups(array $refGroups): array
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
     * Fan guruhi uchun bitta solishtirma qatori.
     *
     * Ishchi rejadagi fan(lar) avval guruh tarkibidagi NOMLAR bo'yicha,
     * topilmasa blok kodi bo'yicha moslanadi. Norma soat/kredit aynan
     * moslangan fan(lar)dan olinadi — shu bois guruhning ishchi rejada
     * uchramagan tarkibi na farqqa, na jamiga qo'shiladi.
     *
     * @param  array<string, array>  $refGroups
     * @param  array<string, array>  $workGroups  moslangan fanlar chiqarib tashlanadi
     */
    private function buildGroupRow(array $choice, array $refGroups, array &$workGroups, array $hemisMap): array
    {
        $manual = !empty($choice['manual']);
        $outside = false;
        $matched = [];

        if ($manual) {
            // Qo'lda guruh: ishchi fan(lar) aniq ko'rsatilgan. Ehtiyot uchun
            // muqobil nomlari ham tekshiriladi — ishchida namunaviydagi nom
            // aynan takrorlangan bo'lsa ham topilsin.
            foreach (array_merge($choice['work'], array_keys($choice['alts'])) as $key) {
                if (isset($workGroups[$key])) {
                    $matched[$key] = $workGroups[$key];
                    unset($workGroups[$key]);
                }
            }
        } else {
            // 1) Muqobil nomlari bo'yicha moslash
            foreach (array_keys($choice['alts']) as $altKey) {
                if (isset($workGroups[$altKey])) {
                    $matched[$altKey] = $workGroups[$altKey];
                    unset($workGroups[$altKey]);
                }
            }

            // 2) Nom bo'yicha topilmasa — ishchi rejadagi blok kodi/sarlavhasi
            //    bo'yicha. Bunda tanlangan fan namunaviy muqobillar ro'yxatida yo'q.
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
        }

        // Norma: tanlangan muqobil(lar)ning namunaviy qatori. Qo'lda guruhda
        // norma muqobil ko'rsatilgan bo'lsa — aynan o'sha. Tanlangani namunaviyda
        // alohida qator bilan kelmasa (masalan ishchida "A / B" bitta qatorda
        // yozilgan) — guruhdagi soat/kreditli birinchi muqobil norma bo'ladi.
        $normKeys = [];
        if ($manual && ($choice['norm'] ?? null) !== null) {
            $normKeys = [$choice['norm']];
        } else {
            $normKeys = array_values(array_filter(array_keys($matched), fn($k) => isset($refGroups[$k])));
        }
        if ($normKeys === []) {
            $normKeys = [$this->representativeAlt($choice, $refGroups)];
        }

        // Blok — namunaviy rejada qanday bo'lsa shundayligicha ("MAJBURIY
        // FANLAR", "2.02 ... YOKI ..."). Guruh nomi blokning o'rnini bosmaydi,
        // u izohda ko'rsatiladi: aks holda fanning qaysi blokda turgani
        // jadvaldan yo'qolib ketardi.
        $refBlock = $refGroups[$normKeys[0]]['block'] ?? null;
        foreach ($choice['keys'] as $key) {
            $refBlock = $refBlock ?: ($refGroups[$key]['block'] ?? null);
        }

        $ref = $this->mergeGroups(array_map(fn($k) => $refGroups[$k], $normKeys), (string) $refBlock);
        $work = $this->mergeGroups(array_values($matched), (string) $refBlock);

        $row = $this->buildRow($ref, $work, $hemisMap);
        $row['group'] = true;
        $row['group_manual'] = $manual;
        $row['group_parts'] = array_values($choice['alts']);
        $row['group_label'] = $choice['label'];

        // Blok kodi bo'yicha moslangan, ammo namunaviy ro'yxatda yo'q fan —
        // soat/kredit farqi bo'lmasa ham alohida holat sifatida belgilanadi.
        if ($outside && in_array($row['status'], [self::STATUS_OK, self::STATUS_NAME], true)) {
            $row['status'] = self::STATUS_GROUP_DIFF;
        }

        // Qo'lda tuzilgan guruhda namunaviy va ishchi nomlarining farq qilishi
        // tabiiy: fanlar bir tomonda birikkan, ikkinchisida alohida. Buni nom
        // farqi deb hisoblamaymiz — guruhni foydalanuvchi tasdiqlagan. Ammo bu
        // faqat NOM JUFTI bo'yicha farqqa taalluqli: HEMIS bilan mos kelmaslik
        // (yoki HEMIS'da umuman topilmaslik) saqlanib qoladi, aks holda
        // haqiqiy kamchilik "To'g'ri" ostida yashirinib qolardi.
        if ($manual && $row['status'] === self::STATUS_NAME && $hemisMap === []) {
            $row['status'] = self::STATUS_OK;
        }

        $notes = ['Fan guruhi' . ($manual ? " (qo'lda)" : '') . ': ' . implode(' / ', $row['group_parts'])];
        if ($work === null) {
            $notes[] = 'ishchi rejada mos fan topilmadi';
        } else {
            $notes[] = "ishchida: {$work['name']}";
        }
        if ($outside) {
            $notes[] = "ishchidagi fan namunaviy guruh tarkibida yo'q";
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
     * ichki kodli ("2.03" kabi, "2.00" emas). detectGroups() dan farqli — bu
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
            // Tarkibiy fanlar: birikkan qatorda har bir fanning o'z soati
            // ko'rsatilishi va HEMIS'da bittalab qidirilishi uchun.
            'parts' => [],
        ];
        $names = [];
        foreach ($groups as $group) {
            $names[] = $group['name'];
            $merged['parts'][] = [
                'name' => $group['name'],
                'hours' => $group['hours'] ?? null,
                'credit' => $group['credit'] ?? null,
                'semestrlar' => $group['semestrlar'] ?? [],
            ];
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

        // Apostroflarni alohida olib tashlaymiz: o'zbek lotin yozuvidagi "ʻ"
        // (U+02BB) va "ʼ" (U+02BC) Unicode'da HARF toifasiga (Lm) kiradi, ya'ni
        // quyidagi \p{L} filtri ularni saqlab qolardi. Natijada "Oʻzbek" bilan
        // "O'zbek" har xil fan deb hisoblanardi.
        $value = str_replace(["'", '‘', '’', 'ʻ', 'ʼ', '`', '´', '′'], '', $value);

        return preg_replace('/[^\p{L}\p{N}]+/u', '', $value);
    }
}
