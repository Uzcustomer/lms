<?php

use App\Services\CurriculumComparisonService;

/**
 * Tanlov bloklarini solishtirish: namunaviy rejada blok ostida bir nechta
 * muqobil fan turadi, ishchi rejada esa faqat tanlangani bo'ladi.
 */

/** Yopiq (private) metodni chaqirish. */
function callService(CurriculumComparisonService $service, string $method, array $args)
{
    $ref = new ReflectionMethod($service, $method);
    $ref->setAccessible(true);

    return $ref->invokeArgs($service, $args);
}

/** groupSubjects() qaytaradigan guruh shakli. */
function subjectGroup(string $name, string $block, ?float $hours, ?float $credit, array $semestrlar = [1]): array
{
    return [
        'name' => $name,
        'block' => $block,
        'hours' => $hours,
        'credit' => $credit,
        'kurslar' => [1],
        'semestrlar' => $semestrlar,
        'notes' => [],
    ];
}

/** Testlar uchun namunaviy reja guruhlari (majburiy + uch xil tanlov bloki). */
function referenceGroups(CurriculumComparisonService $service): array
{
    $key = fn($name) => $service->normalize($name);

    return [
        $key('Anatomiya') => subjectGroup('Anatomiya', '1.00 MAJBURIY FANLAR', 210, 7),
        $key('Gistologiya') => subjectGroup('Gistologiya', '1.00 MAJBURIY FANLAR', 120, 4),
        $key('Tibbiyotda xorijiy til') => subjectGroup(
            'Tibbiyotda xorijiy til', "2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til", 90, 3
        ),
        $key('Bioetika') => subjectGroup(
            'Bioetika', '2.03 Hayot faoliyati xavfsizligi YOKI Bioetika', 60, 2
        ),
        $key('Harbiy tibbiy tayyorgarlik') => subjectGroup(
            'Harbiy tibbiy tayyorgarlik', '2.05 Harbiy tibbiy tayyorgarlik', 360, 12
        ),
        $key('Klinik laboratoriya tashhisoti') => subjectGroup(
            'Klinik laboratoriya tashhisoti', '2.05 Harbiy tibbiy tayyorgarlik', 60, 2
        ),
    ];
}

test('blok sarlavhasidagi muqobillar YOKI bo\'yicha ajratiladi', function () {
    $service = new CurriculumComparisonService();

    expect(callService($service, 'splitChoiceLabel', ["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"]))
        ->toBe(["O'zbek/rus tili", 'Tibbiyotda xorijiy til']);
    expect(callService($service, 'splitChoiceLabel', ['2.03 Hayot faoliyati xavfsizligi yoki Bioetika']))
        ->toBe(['Hayot faoliyati xavfsizligi', 'Bioetika']);
    expect(callService($service, 'splitChoiceLabel', ['1.00 MAJBURIY FANLAR']))->toBe([]);
});

test('majburiy fanlar bloki tanlov deb qabul qilinmaydi', function () {
    $service = new CurriculumComparisonService();

    expect(callService($service, 'looksLikeChoiceBlock', ['1.00 MAJBURIY FANLAR']))->toBeFalse();
    expect(callService($service, 'looksLikeChoiceBlock', ['2.00 TANLOV FANLAR']))->toBeFalse();
    expect(callService($service, 'looksLikeChoiceBlock', ['2.05 Harbiy tibbiy tayyorgarlik']))->toBeTrue();
});

test('tanlov bloklari sarlavha va ichki kod bo\'yicha aniqlanadi', function () {
    $service = new CurriculumComparisonService();
    $choices = callService($service, 'choiceGroups', [referenceGroups($service)]);

    expect(array_keys($choices))->toBe([
        "2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til",
        '2.03 Hayot faoliyati xavfsizligi YOKI Bioetika',
        '2.05 Harbiy tibbiy tayyorgarlik',
    ]);

    // Sarlavhadagi nomlar ham, blok ostidagi fan qatorlari ham muqobil sanaladi
    expect(array_values($choices["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"]['alts']))
        ->toBe(["O'zbek/rus tili", 'Tibbiyotda xorijiy til']);
    expect(array_values($choices['2.05 Harbiy tibbiy tayyorgarlik']['alts']))
        ->toBe(['Harbiy tibbiy tayyorgarlik', 'Klinik laboratoriya tashhisoti']);
});

test('tanlangan muqobil topilsa norma aynan shu muqobilnikidan olinadi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'choiceGroups', [$refGroups]);

    // 2.05 blokida 360 va 60 soatlik muqobillar bor; ishchi rejada 60 soatligi tanlangan
    $workGroups = [
        $service->normalize('Klinik laboratoriya tashhisoti')
            => subjectGroup('Klinik laboratoriya tashhisoti', 'Tanlov fanlar', 60, 2),
    ];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [$choices['2.05 Harbiy tibbiy tayyorgarlik'], $refGroups, &$workGroups, []]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
    expect($row['ref_hours'])->toBe(60.0);   // 360 emas — tanlanmagan muqobil hisobga olinmaydi
    expect($row['work_hours'])->toBe(60.0);
    expect($row['choice'])->toBeTrue();
    expect($workGroups)->toBe([]);           // moslangan fan ishchi ro'yxatdan chiqarildi
});

test('birorta muqobil tanlanmasa blok bitta qator bo\'lib ogohlantiradi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'choiceGroups', [$refGroups]);
    $workGroups = [];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices['2.03 Hayot faoliyati xavfsizligi YOKI Bioetika'], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_MISSING_IN_WORKING);
    expect($row['ref_hours'])->toBe(60.0);
    expect($row['note'])->toContain('Tanlov bloki');
});

test('ro\'yxatdan tashqari fan blok kodi bo\'yicha moslanadi va "Tanlov farqi" bo\'ladi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'choiceGroups', [$refGroups]);

    // Ishchi rejada 2.02 blokida namunaviyda yo'q fan turibdi, soati esa mos
    $workGroups = [
        $service->normalize('Ingliz tili') => subjectGroup('Ingliz tili', '2.02 Chet tili', 90, 3),
    ];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_CHOICE_DIFF);
    expect($row['work_name'])->toBe('Ingliz tili');
    expect($row['note'])->toContain("namunaviy muqobillar ro'yxatida yo'q");
});

test('qo\'lda guruh ishchidagi birlashgan "A / B" qatorini bog\'laydi', function () {
    $service = new CurriculumComparisonService();

    // Namunaviyda blok ostida bitta muqobil qatori bor, ikkinchisi faqat
    // sarlavhada; ishchida esa ikkalasi bitta qatorda birlashtirilgan.
    $refGroups = [
        $service->normalize("O'zbek/rus tili") => subjectGroup(
            "O'zbek/rus tili", "2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til", 90, 3
        ),
    ];
    $manual = [[
        'label' => '2.02 Chet tili',
        'ref_names' => ["O'zbek/rus tili", 'Tibbiyotda xorijiy til'],
        'work_names' => ["O'zbek/rus tili / Tibbiyotda xorijiy til"],
        'norm_name' => null,
    ]];

    $groups = callService($service, 'manualChoiceGroups', [$manual, $refGroups]);
    expect(array_keys($groups))->toBe(['manual-0']);
    expect($groups['manual-0']['manual'])->toBeTrue();

    $workGroups = [
        $service->normalize("O'zbek/rus tili / Tibbiyotda xorijiy til") => subjectGroup(
            "O'zbek/rus tili / Tibbiyotda xorijiy til", 'tanlov', 90, 3
        ),
        $service->normalize('Anatomiya') => subjectGroup('Anatomiya', 'majburiy', 210, 7),
    ];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [$groups['manual-0'], $refGroups, &$workGroups, []]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
    expect($row['ref_hours'])->toBe(90.0);
    expect($row['work_hours'])->toBe(90.0);
    expect($row['choice_manual'])->toBeTrue();
    // Faqat guruhga tegishli fan iste'mol qilinadi, qolgani jadvalda qoladi
    expect(array_keys($workGroups))->toBe([$service->normalize('Anatomiya')]);
});

test('qo\'lda guruhda norm_name normani belgilaydi', function () {
    $service = new CurriculumComparisonService();

    $refGroups = [
        $service->normalize('Harbiy tibbiy tayyorgarlik')
            => subjectGroup('Harbiy tibbiy tayyorgarlik', '2.05', 360, 12),
        $service->normalize('Klinik laboratoriya tashhisoti')
            => subjectGroup('Klinik laboratoriya tashhisoti', '2.05', 60, 2),
    ];
    $groups = callService($service, 'manualChoiceGroups', [[[
        'label' => '2.05',
        'ref_names' => ['Harbiy tibbiy tayyorgarlik', 'Klinik laboratoriya tashhisoti'],
        'work_names' => ['Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti'],
        'norm_name' => 'Klinik laboratoriya tashhisoti',
    ]], $refGroups]);

    $workGroups = [
        $service->normalize('Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti')
            => subjectGroup('Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti', 'tanlov', 60, 2),
    ];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [$groups['manual-0'], $refGroups, &$workGroups, []]);

    expect($row['ref_hours'])->toBe(60.0);   // 360 emas — norma qo'lda ko'rsatilgan
    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
});

test('normalize apostrof variantlarini bir xil deb qabul qiladi', function () {
    $service = new CurriculumComparisonService();

    // saveChoiceGroups() norma muqobillar ro'yxatida borligini shu normalizatsiya
    // orqali tekshiradi — apostrof turi farq qilsa ham norma yo'qolmasligi kerak
    $expected = $service->normalize("O'zbek/rus tili");
    expect($service->normalize("O‘zbek/rus tili"))->toBe($expected);
    expect($service->normalize("Oʻzbek/rus tili"))->toBe($expected);
    expect($service->normalize("  O'ZBEK / RUS  TILI "))->toBe($expected);

    expect($service->normalize('Bioetika'))->not->toBe($expected);
});

test('namunaviyda muqobili topilmagan qo\'lda guruh o\'tkazib yuboriladi', function () {
    $service = new CurriculumComparisonService();

    $groups = callService($service, 'manualChoiceGroups', [[[
        'label' => 'x',
        'ref_names' => ["Rejada yo'q fan"],
        'work_names' => [],
        'norm_name' => null,
    ]], referenceGroups($service)]);

    expect($groups)->toBe([]);
});

test('soat va kredit farqi tanlov farqidan ustun turadi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'choiceGroups', [$refGroups]);

    $workGroups = [
        $service->normalize('Ingliz tili') => subjectGroup('Ingliz tili', '2.02 Chet tili', 120, 4),
    ];

    $method = new ReflectionMethod($service, 'buildChoiceRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_HOURS_CREDIT);
    expect($row['hours_diff'])->toBe(30.0);
});
