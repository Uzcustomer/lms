<?php

use App\Services\CurriculumComparisonService;

/**
 * Fan guruhlarini solishtirish: ikki rejada fanlar har xil bo'linib kelishi
 * mumkin — namunaviyda tanlov bloki (ishchida bittasi tanlangan) yoki
 * namunaviyda birikkan fan (ishchida ikkita alohida fan).
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
    $choices = callService($service, 'detectGroups', [referenceGroups($service)]);

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
    $choices = callService($service, 'detectGroups', [$refGroups]);

    // 2.05 blokida 360 va 60 soatlik muqobillar bor; ishchi rejada 60 soatligi tanlangan
    $workGroups = [
        $service->normalize('Klinik laboratoriya tashhisoti')
            => subjectGroup('Klinik laboratoriya tashhisoti', 'Tanlov fanlar', 60, 2),
    ];

    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [$choices['2.05 Harbiy tibbiy tayyorgarlik'], $refGroups, &$workGroups, []]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
    expect($row['ref_hours'])->toBe(60.0);   // 360 emas — tanlanmagan muqobil hisobga olinmaydi
    expect($row['work_hours'])->toBe(60.0);
    expect($row['group'])->toBeTrue();
    expect($workGroups)->toBe([]);           // moslangan fan ishchi ro'yxatdan chiqarildi
});

test('birorta muqobil tanlanmasa blok bitta qator bo\'lib ogohlantiradi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'detectGroups', [$refGroups]);
    $workGroups = [];

    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices['2.03 Hayot faoliyati xavfsizligi YOKI Bioetika'], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_MISSING_IN_WORKING);
    expect($row['ref_hours'])->toBe(60.0);
    expect($row['note'])->toContain('Fan guruhi');
});

test('ro\'yxatdan tashqari fan blok kodi bo\'yicha moslanadi va "Tanlov farqi" bo\'ladi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = referenceGroups($service);
    $choices = callService($service, 'detectGroups', [$refGroups]);

    // Ishchi rejada 2.02 blokida namunaviyda yo'q fan turibdi, soati esa mos
    $workGroups = [
        $service->normalize('Ingliz tili') => subjectGroup('Ingliz tili', '2.02 Chet tili', 90, 3),
    ];

    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_GROUP_DIFF);
    expect($row['work_name'])->toBe('Ingliz tili');
    expect($row['note'])->toContain("namunaviy guruh tarkibida yo'q");
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

    $groups = callService($service, 'manualGroups', [$manual, $refGroups]);
    expect(array_keys($groups))->toBe(['manual-0']);
    expect($groups['manual-0']['manual'])->toBeTrue();

    $workGroups = [
        $service->normalize("O'zbek/rus tili / Tibbiyotda xorijiy til") => subjectGroup(
            "O'zbek/rus tili / Tibbiyotda xorijiy til", 'tanlov', 90, 3
        ),
        $service->normalize('Anatomiya') => subjectGroup('Anatomiya', 'majburiy', 210, 7),
    ];

    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [$groups['manual-0'], $refGroups, &$workGroups, []]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
    expect($row['ref_hours'])->toBe(90.0);
    expect($row['work_hours'])->toBe(90.0);
    expect($row['group_manual'])->toBeTrue();
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
    $groups = callService($service, 'manualGroups', [[[
        'label' => '2.05',
        'ref_names' => ['Harbiy tibbiy tayyorgarlik', 'Klinik laboratoriya tashhisoti'],
        'work_names' => ['Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti'],
        'norm_name' => 'Klinik laboratoriya tashhisoti',
    ]], $refGroups]);

    $workGroups = [
        $service->normalize('Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti')
            => subjectGroup('Harbiy tibbiy tayyorgarlik / Klinik laboratoriya tashhisoti', 'tanlov', 60, 2),
    ];

    $method = new ReflectionMethod($service, 'buildGroupRow');
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

    $groups = callService($service, 'manualGroups', [[[
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
    $choices = callService($service, 'detectGroups', [$refGroups]);

    $workGroups = [
        $service->normalize('Ingliz tili') => subjectGroup('Ingliz tili', '2.02 Chet tili', 120, 4),
    ];

    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);
    $row = $method->invokeArgs($service, [
        $choices["2.02 O'zbek/rus tili YOKI Tibbiyotda xorijiy til"], $refGroups, &$workGroups, [],
    ]);

    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_HOURS_CREDIT);
    expect($row['hours_diff'])->toBe(30.0);
});

/**
 * Namunaviyda birikkan fan ("Ichki kasalliklar. Endokrinologiya"), ishchida
 * ikkita alohida fan — tanlov emas, shunchaki boshqacha bo'lingan.
 */
function mergedSubjectGroup(CurriculumComparisonService $service): array
{
    return callService($service, 'manualGroups', [
        [[
            'label' => 'Ichki kasalliklar + Endokrinologiya',
            'ref_names' => ['Ichki kasalliklar. Endokrinologiya'],
            'work_names' => ['Ichki kasalliklar', 'Endokrinologiya'],
            'norm_name' => null,
        ]],
        [
            $service->normalize('Ichki kasalliklar. Endokrinologiya')
                => subjectGroup('Ichki kasalliklar. Endokrinologiya', 'MAJBURIY FANLAR', 240, 8, [5]),
        ],
    ])['manual-0'];
}

/** Yuqoridagi guruhga mos ishchi fanlar (har chaqiruvda yangi nusxa). */
function splitWorkGroups(CurriculumComparisonService $service): array
{
    return [
        $service->normalize('Ichki kasalliklar') => subjectGroup('Ichki kasalliklar', 'majburiy', 180, 6, [5]),
        $service->normalize('Endokrinologiya') => subjectGroup('Endokrinologiya', 'majburiy', 60, 2, [6]),
    ];
}

/** Guruh qatorini qurish (buildGroupRow yopiq va $workGroups ni o'zgartiradi). */
function groupRow(CurriculumComparisonService $service, array $group, array $refGroups, array &$work, array $hemis): array
{
    $method = new ReflectionMethod($service, 'buildGroupRow');
    $method->setAccessible(true);

    return $method->invokeArgs($service, [$group, $refGroups, &$work, $hemis]);
}

test('guruh qatorida blok namunaviy rejadagicha saqlanadi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = [
        $service->normalize('Ichki kasalliklar. Endokrinologiya')
            => subjectGroup('Ichki kasalliklar. Endokrinologiya', 'MAJBURIY FANLAR', 240, 8, [5]),
    ];
    $work = splitWorkGroups($service);

    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work, []);

    // Guruh nomi blokning o'rnini bosmaydi — u alohida maydonda
    expect($row['block'])->toBe('MAJBURIY FANLAR');
    expect($row['group_label'])->toBe('Ichki kasalliklar + Endokrinologiya');
});

test('birikkan qatorda har bir fanning soati alohida ko\'rsatiladi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = [
        $service->normalize('Ichki kasalliklar. Endokrinologiya')
            => subjectGroup('Ichki kasalliklar. Endokrinologiya', 'MAJBURIY FANLAR', 240, 8, [5]),
    ];
    $work = splitWorkGroups($service);

    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work, []);

    expect($row['work_parts'])->toHaveCount(2);
    expect($row['work_parts'][0]['hours'])->toBe(180.0);
    expect($row['work_parts'][1]['hours'])->toBe(60.0);
    expect($row['work_hours'])->toBe(240.0);       // jami
    expect($row['ref_parts'])->toHaveCount(1);     // namunaviyda birikkan
});

test('HEMIS nomi birikkan guruhda qism-qism qidiriladi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = [
        $service->normalize('Ichki kasalliklar. Endokrinologiya')
            => subjectGroup('Ichki kasalliklar. Endokrinologiya', 'MAJBURIY FANLAR', 240, 8, [5]),
    ];
    $hemis = [
        $service->normalize('Ichki kasalliklar') => 'Ichki kasalliklar',
        $service->normalize('Endokrinologiya') => 'Endokrinologiya',
    ];
    $work = splitWorkGroups($service);

    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work, $hemis);

    // Butun "A / B" nomi HEMIS'da yo'q, ammo qismlari bor — "topilmadi" emas
    expect($row['hemis_name'])->toBe('Ichki kasalliklar / Endokrinologiya');
    expect($row['ref_matches_hemis'])->toBeNull();  // birikkan nom ayblanmaydi
    expect($row['work_matches_hemis'])->toBeTrue();
    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
});

test('HEMIS kamchiligi qo\'lda guruhda ham "To\'g\'ri" ostida yashirilmaydi', function () {
    $service = new CurriculumComparisonService();
    $refGroups = [
        $service->normalize('Ichki kasalliklar. Endokrinologiya')
            => subjectGroup('Ichki kasalliklar. Endokrinologiya', 'MAJBURIY FANLAR', 240, 8, [5]),
    ];

    // Tarkibning bir qismi HEMIS'da yo'q
    $work = splitWorkGroups($service);
    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work,
        [$service->normalize('Ichki kasalliklar') => 'Ichki kasalliklar']);
    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_NAME);
    expect($row['note'])->toContain('Endokrinologiya');

    // Umuman topilmadi
    $work = splitWorkGroups($service);
    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work,
        [$service->normalize('Anatomiya') => 'Anatomiya']);
    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_NAME);
    expect($row['note'])->toContain('HEMIS');

    // HEMIS nomlari umuman yuklanmagan — nom jufti farqi ayblanmaydi
    $work = splitWorkGroups($service);
    $row = groupRow($service, mergedSubjectGroup($service), $refGroups, $work, []);
    expect($row['status'])->toBe(CurriculumComparisonService::STATUS_OK);
});
