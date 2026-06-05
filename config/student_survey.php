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
    'deadline'   => '2026-06-12 23:59:59',

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
    ],
];
