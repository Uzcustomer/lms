<?php

/*
|--------------------------------------------------------------------------
| Monitoring moduli (Registrator ofisi nazorat detektorlari)
|--------------------------------------------------------------------------
|
| Muddat va chegaralar bu yerda EMAS — ular docs/detectors.yaml dan
| monitoring_detectors.params ga yoziladi va paneldan o'zgartiriladi.
| Bu faylda faqat texnik bog'lanishlar turadi.
|
*/

return [

    // Boshlang'ich katalog (TZ 3-bo'lim): monitoring:sync-catalog shu fayldan o'qiydi.
    'catalog_path' => base_path('docs/detectors.yaml'),

    // Detektor kodi → klass. Klassi yo'q detektorlar katalogda «amalga oshirilmagan» bo'lib turadi.
    'detectors' => [
        'D-2' => \App\Services\Monitoring\Detectors\D2UnexcusedAbsence::class,
        'Q-1' => \App\Services\Monitoring\Detectors\Q1FinalGradesEntry::class,
        'A-5' => \App\Services\Monitoring\Detectors\A5ContractAdmission::class,
        'E-1' => \App\Services\Monitoring\Detectors\E1OrderNotice::class,
        'E-7' => \App\Services\Monitoring\Detectors\E7TransferFile::class,
    ],

    // Istisnoga javob berish muddati (detektorda due_workdays berilmagan bo'lsa).
    'default_due_workdays' => 3,

    // Monitoringni to'liq boshqaradigan, ishlaydigan va faqat ko'radigan rollar.
    'roles' => [
        'manage' => ['superadmin', 'admin', 'monitoring_admin'],
        'work' => ['registrator_ofisi'],
        'view' => ['oquv_prorektori'],
    ],

    // Buyruq turlari (normallashtirilgan) va import paytida xom nomdan taxmin qilish uchun kalit so'zlar.
    // Tartib muhim: birinchi mos kelgan tur olinadi.
    'order_types' => [
        'kochirib_kelish' => ['label' => "Ko'chirib kelish (boshqa OTMdan)", 'keywords' => ["ko'chirib kel", 'kochirib kel', 'boshqa otmdan', 'перевод из']],
        'tiklash' => ['label' => 'Tiklash', 'keywords' => ['tiklash', 'tiklan', 'восстанов']],
        'kursda_qoldirish' => ['label' => 'Kursda qoldirish', 'keywords' => ['kursda qoldir', 'kursdan qoldir', 'takroriy', 'повтор']],
        'akademik_tatil' => ['label' => "Akademik ta'til", 'keywords' => ["akademik ta'til", 'akademik tatil', 'академ']],
        'chetlashtirish' => ['label' => 'Chetlashtirish', 'keywords' => ['chetlashtir', 'chetlat', 'отчисл']],
        'kochirish' => ['label' => "Ko'chirish (boshqa)", 'keywords' => ["ko'chir", 'kochir', 'перевод']],
        'qabul' => ['label' => 'Qabul (talabalikka)', 'keywords' => ['qabul', 'talabalikka', 'зачисл']],
        'boshqa' => ['label' => 'Boshqa', 'keywords' => []],
    ],

    // E-7: ko'chirib kelgan talaba yig'majildidagi 6 majburiy hujjat (VMQ 578 3-il. 53).
    'transfer_documents' => [
        'orta_talim' => "O'rta ta'lim hujjati va ilovasining asli",
        'qabul_buyrugi' => "Avvalgi OTMga qabul buyrug'idan ko'chirma",
        'kurs_buyrugi' => "Oxirgi kursga o'tganlik buyrug'idan ko'chirma",
        'transkript' => "Akademik ma'lumotnoma (transkript) asli",
        'chetlatish_buyrugi' => "Chetlatilganlik buyrug'idan ko'chirma",
        'sorovnoma_javobi' => "So'rovnomaning javob xati",
    ],

    // Dalil fayllari (shaxsiy hujjatlar) — local disk (storage/app/private), faqat controller orqali beriladi.
    'evidence_disk' => 'local',
    'evidence_dir' => 'monitoring',
    'evidence_max_kb' => 20480,

    // HEMIS'da buyruqlar endpointini qidirish uchun nomzodlar (monitoring:hemis-probe).
    'hemis_probe_endpoints' => [
        'data/decree-list',
        'data/student-decree-list',
        'data/order-list',
        'data/student-order-list',
    ],
];
