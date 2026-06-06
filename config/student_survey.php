<?php

/**
 * Talabalar uchun bir martalik anonim so'rovnoma — Registrator ofisi faoliyati.
 *
 * Hujjat strukturasi:
 *   key            — DB'da survey_key sifatida ishlatiladi (qaytarish/o'zgartirish uchun)
 *   title          — sarlavha
 *   description    — kirish matni
 *   deadline       — strtotime() formati, undan keyin "Keyinroq" tugmasi yashiriladi
 *   questions[]    — savollar massivi (id, type, text, options, required, conditional)
 *
 * Savol turlari:
 *   - radio       — bitta variant
 *   - checkbox    — bir nechta variant (multi)
 *   - text        — erkin matnli javob (textarea)
 *
 * Variant strukturasi:
 *   - id          — kichik kalit (a, b, c... yoki 1, 2, 3...)
 *   - text        — UI'da ko'rinadigan matn
 *   - has_other   — true bo'lsa "Boshqa: ___" sifatida ko'rinadi, javob bilan birga
 *                   talaba kiritgan matn ham yoziladi
 *
 * Conditional:
 *   show_if       — ['question_id' => '5', 'when_option' => 'c']
 *                   — faqat shu shart bajarilsa savol ko'rinadi
 */

return [
    'key'         => 'registrar_2026',
    'title'       => "Registrator ofisi faoliyatini baholash bo'yicha anonim so'rovnoma",
    'description' => "Hurmatli talaba!\n\nUshbu so'rovnoma mutlaqo anonim bo'lib, undan maqsad — Registrator ofisi faoliyatidagi muammolarni aniqlash va xizmat ko'rsatish sifatini oshirishdir. Iltimos, savollarga samimiy va xolis javob bering.",
    'deadline'   => '2026-06-06 10:30:00',

    'questions' => [
        [
            'id'       => '1',
            'type'     => 'radio',
            'text'     => "Registrator ofisi nima, qanday xizmat ko'rsatishi va qayerda joylashganini bilasizmi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Ha, hamma ma'lumotlarga egaman va qayerda joylashganini aniq bilaman"],
                ['id' => 'b', 'text' => "Nimaligini bilaman, lekin qanday xizmatlar ko'rsatishi yoki qayerda joylashganini to'liq bilmayman"],
                ['id' => 'c', 'text' => "Yo'q, u haqida deyarli ma'lumotga ega emasman"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '2',
            'type'     => 'radio',
            'text'     => "Registrator ofisining hozirgi joylashuvi (lokatsiyasi) sizga qulaymi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Juda qulay (topish oson, markazda yoki asosiy binoda)"],
                ['id' => 'b', 'text' => "O'rtacha (biroz chekkaroq binoda yoki topish qiyinroq)"],
                ['id' => 'c', 'text' => "Umuman qulay emas (juda uzoq yoki noqulay joyda)"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '3',
            'type'     => 'radio',
            'text'     => "Registrator ofisi xizmatlarining umumiy sifatini qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "A'lo (Muammosiz va tez)"],
                ['id' => 'b', 'text' => "Qoniqarli (Yaxshi, lekin kamchiliklari bor)"],
                ['id' => 'c', 'text' => "Qoniqarsiz (Xizmat ko'rsatish darajasi past)"],
                ['id' => 'd', 'text' => "Umuman qoniqmayman"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '4',
            'type'     => 'radio',
            'text'     => "Registrator ofisi menejerlarining talabalarga bo'lgan munosabati va muomalasi qanday?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Juda xushmuomala va yordamga tayyor"],
                ['id' => 'b', 'text' => "Neytral / Oddiy"],
                ['id' => 'c', 'text' => "Qo'pol, beparvo yoki mensimaslik bilan munosabatda bo'lishadi"],
                ['id' => 'd', 'text' => "Ko'pincha talabalarni eshitishni ham xohlashmaydi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '5',
            'type'     => 'radio',
            'text'     => "Ofisga biror xizmat bilan borganingizda, ortiqcha ovoragarchilik (\"u yoqqa bor, bu yoqqa bor\" deb yugurtirish) holatlari kuzatiladimi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Yo'q, hamma narsa bir joyda tez hal bo'ladi"],
                ['id' => 'b', 'text' => "Ba'zida chalg'ituvchi ma'lumotlar tufayli biroz yugurtirishadi"],
                ['id' => 'c', 'text' => "Ha, tez-tez xonama-xona yoki bino-mabino sarson qilishadi"],
                ['id' => 'd', 'text' => "Mening muammom umuman hal bo'lmay, arosatda qolib ketgan"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '5.1',
            'type'     => 'checkbox',
            'text'     => "Ko'pincha aynan qaysi masalalar yoki xizmatlar bo'yicha asossiz sarson bo'lish va yugur-yugurlar kuzatiladi? (Bir nechtasini tanlash mumkin)",
            'required' => true,
            'show_if'  => ['question_id' => '5', 'when_option' => 'c'],
            'options' => [
                ['id' => 'a', 'text' => "Imtihon sanalari, jadvallari yoki nazoratlarni aniqlashtirishda"],
                ['id' => 'b', 'text' => "Fandan fanga o'tish, qayta o'qish (kreditlarni qayta topshirish) yoki kreditlarni hisoblashda"],
                ['id' => 'c', 'text' => "Ma'lumotnoma (spravka) yoki akademik transkript (baholar varaqasi) olishda"],
                ['id' => 'd', 'text' => "Arizalar topshirish yoki shartnomaviy masalalarni rasmiylashtirishda"],
                ['id' => 'e', 'text' => "O'quv tizimidagi elektron xatoliklarni to'g'rilashda"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '6',
            'type'     => 'radio',
            'text'     => "Siz o'z muammoingiz yoki arizangiz bo'yicha Registrator ofisiga necha marta qayta-qayta borishga majbur bo'lasiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Bir marta borishda hamma narsa hal bo'ladi"],
                ['id' => 'b', 'text' => "Odatda 2-3 marta qayta borishga to'g'ri keladi"],
                ['id' => 'c', 'text' => "Bir muammo bo'yicha haftalab, 4 martadan ko'p qatnayman"],
                ['id' => 'd', 'text' => "Murojaat qilsam ham muammom oylar davomida hal bo'lmay qolib ketadi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '7',
            'type'     => 'radio',
            'text'     => "Ofis menejerlarining o'z vazifasini qay darajada bilishi va professionalligini qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Yuqori (Savollarga aniq va to'g'ri javob berishadi)"],
                ['id' => 'b', 'text' => "O'rtacha (Ba'zan aniq javob bera olishmaydi yoki tizimni yaxshi tushunishmaydi)"],
                ['id' => 'c', 'text' => "Past (Noto'g'ri ma'lumot berishadi yoki muammoni hal qilishni bilishmaydi)"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '8',
            'type'     => 'radio',
            'text'     => "Registrator ofisi xodimlarini ish joyidan (belgilangan qabul vaqtida) topish darajasi qanday?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Har doim o'z joyida bo'lishadi, navbat kelishi bilan qabul qilishadi"],
                ['id' => 'b', 'text' => "Ba'zida joyida bo'lishmaydi (kutib qolamiz)"],
                ['id' => 'c', 'text' => "Ko'pincha ish vaqti bo'lishiga qaramay, xonada hech kim bo'lmaydi yoki tanaffus bahonasi bilan qabul qilishmaydi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '9',
            'type'     => 'radio',
            'text'     => "Menejerlar tomonidan qabul soatlari tugamasdan oldin qabulni asossiz to'xtatish yoki talabalarni qaytarib yuborish holatlari kuzatiladimi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Yo'q, qabul vaqtining oxirigacha navbatdagi hamma talabalarni qabul qilishadi"],
                ['id' => 'b', 'text' => "Ba'zida qabul vaqti tugashiga 15-20 daqiqa qolganda \"bugun ulgurmaymiz\" deb qaytarishadi"],
                ['id' => 'c', 'text' => "Ha, tez-tez qabul vaqti to'liq tugamay turib eshikni yopib olishadi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '10',
            'type'     => 'radio',
            'text'     => "Ofisga murojaat qilganingizda navbatda o'rtacha qancha vaqt yo'qotasiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "5-10 daqiqa (tez fursatda)"],
                ['id' => 'b', 'text' => "15-30 daqiqa"],
                ['id' => 'c', 'text' => "30 daqiqadan 1 soatgacha"],
                ['id' => 'd', 'text' => "1 soatdan ko'p vaqtim ketadi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '11',
            'type'     => 'radio',
            'text'     => "Registrator ofisi tomonidan beriladigan ma'lumotlar, hujjatlar, buyruqlar va akademik qoidalarning shaffofligini (ochiqligini) qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "To'liq shaffof (Hamma ma'lumotlar, mezonlar va natijalar ochiq, hamma uchun teng)"],
                ['id' => 'b', 'text' => "Qisman shaffof (Ayrim ma'lumotlar ochiq, lekin ba'zi ichki qarorlar yoki qoidalar yopiq qoladi)"],
                ['id' => 'c', 'text' => "Umuman shaffof emas (Qoidalar qanday ishlaydi, talabalar bilmaydi)"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '12',
            'type'     => 'radio',
            'text'     => "Akademik qarzdorlikni yopish, akademik ta'til yoki o'qishni ko'chirish kabi jarayonlar bo'yicha ma'lumotlar va yo'riqnomalar sizga qanchalik tushunarli yetkaziladi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Juda tushunarli, hamma qoidalar va muddatlarni aniq bilamiz"],
                ['id' => 'b', 'text' => "Ma'lumotlar bor, lekin juda chalkash yoki atayin kech e'lon qilinadi"],
                ['id' => 'c', 'text' => "Umuman ma'lumot berilmaydi, hammasini ofisga borib, so'rab bilishga majburmiz"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '13',
            'type'     => 'radio',
            'text'     => "Registrator ofisi faoliyatini va xizmat ko'rsatish darajasini o'tgan yilgi holatga nisbatan qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Ijobiy tomonga o'zgargan (Sifat va tezlik oshgan)"],
                ['id' => 'b', 'text' => "Deyarli o'zgarish yo'q (Xuddi o'tgan yilgidek)"],
                ['id' => 'c', 'text' => "Salbiy tomonga o'zgargan (Muammolar ko'paygan, sifat tushgan)"],
                ['id' => 'd', 'text' => "Solishtira olmayman (Birinchi kursman)"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '14',
            'type'     => 'radio',
            'text'     => "So'nggi bir yil ichida ma'lumotlarni onlayn olish va onlayn xizmatlardan (oliy ta'lim elektron tizimi orqali ariza topshirish, ma'lumotnoma olish va h.k.) foydalanish imkoniyatlari qanchalik yaxshilandi?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Juda yaxshi tomonga o'zgardi, hozir ko'p narsani uydan turib onlayn hal qilsa bo'ladi"],
                ['id' => 'b', 'text' => "Onlayn xizmatlar qo'shildi, lekin tizimlar ko'p qotadi yoki arizalar baribir qabul qilinmaydi"],
                ['id' => 'c', 'text' => "Hech qanday o'zgarish bo'lmadi, onlayn xizmatlar hali ham deyarli ishlamaydi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '15',
            'type'     => 'radio',
            'text'     => "Imtihonga bevosita kirish jarayonini (talabalarni tekshirish, kiritish tartibi va tashkilotchilikni) o'tgan yilga nisbatan qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Ijobiy tomonga o'zgargan: Navbatlar kamaygan, ortiqcha kutishlarsiz, tez va tartibli kirib boryapmiz."],
                ['id' => 'b', 'text' => "O'zgarish yo'q: O'tgan yilgidek o'rtacha darajada, ba'zida biroz kutishlar bo'ladi."],
                ['id' => 'c', 'text' => "Vaziyat yomonlashgan: Kirish qismida juda uzun navbatlar yuzaga kelyapti, tashkilotchilik past, ko'p kutamiz va ortiqcha ovoragarchiliklar bor."],
                ['id' => 'd', 'text' => "Solishtira olmayman: Birinchi kursman."],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '16',
            'type'     => 'radio',
            'text'     => "Imtihonlarga kirish uchun joriy etilgan yuzni tanish (Face ID) tizimi ishini qanday baholaysiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Juda yaxshi (Tez aniqlaydi, navbatlar hosil bo'lishining oldini oladi)"],
                ['id' => 'b', 'text' => "O'rtacha (Ba'zida yaxshi tanimaydi yoki tizim sekin ishlaydi, biroz kutib qolamiz)"],
                ['id' => 'c', 'text' => "Juda yomon (Yuzni qiyinchilik bilan taniydi, texnik nosozliklar ko'p bo'ladi va eshik tagida katta tirbandlik yaratadi)"],
                ['id' => 'd', 'text' => "Bizda bu tizim hali joriy etilmagan / foydalanmaganman"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '17',
            'type'     => 'radio',
            'text'     => "Registrator ofisida arizalarni ko'rib chiqish, navbatni chetlab o'tish yoki hujjatlarni rasmiylashtirishda tanish-bilishchilik, adolatsizlik yoki kamsitish holatlariga duch kelganmisiz?",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Yo'q, hamma talabaga teng, xolis va adolatli munosabatda bo'linadi"],
                ['id' => 'b', 'text' => "Shaxsan duch kelmaganman, lekin talabalar orasida shunday gaplarni ko'p eshitganman"],
                ['id' => 'c', 'text' => "Ha, shaxsan o'zim tanish-bilishchilik yoki adolatsizlik holatiga duch kelganman"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'       => '18',
            'type'     => 'checkbox',
            'text'     => "Sizningcha, Registrator ofisi faoliyatidagi eng katta muammo nimada? (Bir nechtasini tanlash mumkin)",
            'required' => true,
            'options' => [
                ['id' => 'a', 'text' => "Uzun navbatlar va ko'p vaqtning yo'qotilishi"],
                ['id' => 'b', 'text' => "Imtihon kunlari kirish joyidagi tirbandliklar va yuzni tanish tizimidagi muammolar"],
                ['id' => 'c', 'text' => "Menejerlarning qo'pol muomalasi yoki mas'uliyatsizligi"],
                ['id' => 'd', 'text' => "O'quv dasturi va elektron tizimlardagi texnik xatoliklar hamda baholarning noto'g'ri yoki kech kiritilishi"],
                ['id' => 'e', 'text' => "Axborot va ko'rsatmalarning yetarli emasligi (talaba qayerga murojaat qilishni bilmaydi)"],
                ['id' => 'f', 'text' => "Ish vaqtiga rioya qilmaslik va xodimlarni joyidan topib bo'lmasligi"],
                ['id' => 'g', 'text' => "Ofis joylashuvining noqulayligi"],
                ['id' => 'h', 'text' => "Muammo yo'q, hammasi yaxshi"],
                ['id' => 'other', 'text' => "Boshqa", 'has_other' => true],
            ],
        ],
        [
            'id'          => '19',
            'type'        => 'text',
            'text'        => "Qaysi menejer(lar) talabalarga nisbatan yomon munosabatda bo'ladi, qo'pollik qiladi yoki o'z vazifasini lozim darajada bajarmaydi? (Menejerning ismi-sharifi, fakulteti yoki oyna raqamini yozishingiz mumkin)",
            'required'    => false,
            'placeholder' => "Menejer ismi, fakulteti yoki oyna raqami...",
        ],
        [
            'id'          => '20',
            'type'        => 'text',
            'text'        => "Aksincha, qaysi menejerning xizmatidan va muomalasidan juda mamnunsiz? (Namuna xodim)",
            'required'    => false,
            'placeholder' => "Mamnun bo'lgan menejer ismi...",
        ],
        [
            'id'          => '21',
            'type'        => 'text',
            'text'        => "Registrator ofisi faoliyatini tubdan yaxshilash, imtihon jarayonlari hamda yuzni tanish tizimini takomillashtirish bo'yicha qanday aniq takliflaringiz bor?",
            'required'    => false,
            'placeholder' => "Takliflaringizni yozing...",
        ],
    ],
];
