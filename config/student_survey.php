<?php

/**
 * Talabalar uchun bir martalik so'rovnoma — Registrator ofisi faoliyati.
 * Ko'p tilli (UZ / RU / EN) — talaba boshida tilni tanlaydi.
 *
 * Matn strukturasi:
 *   $arr = ['uz' => '...', 'ru' => '...', 'en' => '...'];
 *   svT($arr, $locale) helper'i kerakli tilni qaytaradi (fallback UZ).
 */

return [
    'key'      => 'students_survey26',
    'deadline' => '2026-06-08 23:59:59',
    'locales'  => ['uz', 'ru', 'en'],

    'title' => [
        'uz' => "Registrator ofisi faoliyatini baholash bo'yicha anonim so'rovnoma",
        'ru' => "Анонимный опрос об оценке деятельности офиса Регистратора",
        'en' => "Anonymous survey on the Registration Office performance",
    ],

    'description' => [
        'uz' => "Hurmatli talaba!\n\nUshbu so'rovnoma mutlaqo anonim bo'lib, undan maqsad — Registrator ofisi faoliyatidagi muammolarni aniqlash va xizmat ko'rsatish sifatini oshirishdir. Iltimos, savollarga samimiy va xolis javob bering.",
        'ru' => "Уважаемый студент!\n\nДанный опрос полностью анонимный. Его цель — выявить проблемы в работе офиса Регистратора и улучшить качество обслуживания. Пожалуйста, отвечайте искренне и беспристрастно.",
        'en' => "Dear student!\n\nThis survey is completely anonymous. Its purpose is to identify issues in the Registration Office and improve the quality of service. Please answer sincerely and impartially.",
    ],

    // UI matnlari — show.blade.php va _question-block ishlatadi
    'ui' => [
        'select_language' => [
            'uz' => "Iltimos, so'rovnomani bajarish tilini tanlang",
            'ru' => "Пожалуйста, выберите язык опроса",
            'en' => "Please choose the survey language",
        ],
        'anonymous_note' => [
            'uz' => "Bu so'rovnoma anonim — javoblaringiz hech kimga ko'rinmaydi.",
            'ru' => "Этот опрос анонимный — ваши ответы никому не видны.",
            'en' => "This survey is anonymous — your answers are not shown to anyone.",
        ],
        'deadline_label' => [
            'uz' => "Tugash muddati",
            'ru' => "Срок окончания",
            'en' => "Deadline",
        ],
        'start_button' => [
            'uz' => "So'rovnomani boshlash",
            'ru' => "Начать опрос",
            'en' => "Start the survey",
        ],
        'later_button' => [
            'uz' => "Keyinroq bajarish",
            'ru' => "Пройти позже",
            'en' => "Complete later",
        ],
        'back_button' => [
            'uz' => "Orqaga",
            'ru' => "Назад",
            'en' => "Back",
        ],
        'next_button' => [
            'uz' => "Keyingisi",
            'ru' => "Далее",
            'en' => "Next",
        ],
        'submit_button' => [
            'uz' => "Yuborish",
            'ru' => "Отправить",
            'en' => "Submit",
        ],
        'multi_select_hint' => [
            'uz' => "Bir nechtasini tanlash mumkin",
            'ru' => "Можно выбрать несколько",
            'en' => "Multiple selections allowed",
        ],
        'optional_hint' => [
            'uz' => "Ixtiyoriy — javob bermaslik mumkin",
            'ru' => "Необязательно — можно не отвечать",
            'en' => "Optional — you may skip this",
        ],
        'other_label' => [
            'uz' => "Boshqa",
            'ru' => "Другое",
            'en' => "Other",
        ],
        'other_placeholder' => [
            'uz' => "Iltimos, izoh yozing...",
            'ru' => "Пожалуйста, уточните...",
            'en' => "Please specify...",
        ],
        'text_placeholder_default' => [
            'uz' => "Javobingizni yozing...",
            'ru' => "Напишите ваш ответ...",
            'en' => "Write your answer...",
        ],
        'validation_error' => [
            'uz' => "Iltimos, javob tanlang yoki to'ldiring",
            'ru' => "Пожалуйста, выберите ответ или заполните поле",
            'en' => "Please select an answer or fill in the field",
        ],
        'submitting' => [
            'uz' => "Javoblar yuborilmoqda...",
            'ru' => "Ответы отправляются...",
            'en' => "Submitting your answers...",
        ],
        'submitting_wait' => [
            'uz' => "Biroz kuting",
            'ru' => "Подождите немного",
            'en' => "Please wait",
        ],
        'thanks_title' => [
            'uz' => "Rahmat!",
            'ru' => "Спасибо!",
            'en' => "Thank you!",
        ],
        'thanks_message' => [
            'uz' => "Javoblaringiz qabul qilindi.",
            'ru' => "Ваши ответы приняты.",
            'en' => "Your answers have been recorded.",
        ],
        'already_completed' => [
            'uz' => "Siz bu so'rovnomani allaqachon bajargansiz. Vaqtingiz uchun rahmat.",
            'ru' => "Вы уже прошли этот опрос. Спасибо за уделённое время.",
            'en' => "You have already completed this survey. Thank you for your time.",
        ],
        'profile_back' => [
            'uz' => "Profilga qaytish",
            'ru' => "Вернуться в профиль",
            'en' => "Back to profile",
        ],
        'question_label' => [
            'uz' => "Savol",
            'ru' => "Вопрос",
            'en' => "Question",
        ],
        'later_warning_title' => [
            'uz' => "E'tibor!",
            'ru' => "Внимание!",
            'en' => "Notice!",
        ],
        'later_warning_msg' => [
            'uz' => "Sizga :deadline gacha muhlat berilgan. Agar so'rovnomani belgilangan muddatda bajarmasangiz, tizim xizmatlaridan foydalanish cheklanadi.",
            'ru' => "Вам предоставлен срок до :deadline. Если вы не пройдёте опрос вовремя, доступ к сервисам платформы будет ограничен.",
            'en' => "You have until :deadline. If you do not complete the survey by then, access to the platform services will be restricted.",
        ],
        'continue' => [
            'uz' => "Davom etish",
            'ru' => "Продолжить",
            'en' => "Continue",
        ],
    ],

    'questions' => [
        [
            'id'       => '1',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi nima, qanday xizmat ko'rsatishi va qayerda joylashganini bilasizmi?",
                'ru' => "Знаете ли вы, что такое офис Регистратора, какие услуги он предоставляет и где он расположен?",
                'en' => "Do you know what the Registration Office is, what services it provides, and where it is located?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Ha, hamma ma'lumotlarga egaman va qayerda joylashganini aniq bilaman",
                    'ru' => "Да, владею всей информацией и точно знаю, где он находится",
                    'en' => "Yes, I have all the information and know exactly where it is",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Nimaligini bilaman, lekin qanday xizmatlar ko'rsatishi yoki qayerda joylashganini to'liq bilmayman",
                    'ru' => "Знаю, что это, но какие услуги оказывает и где находится — знаю не полностью",
                    'en' => "I know what it is, but I don't fully know what services it provides or where it is located",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Yo'q, u haqida deyarli ma'lumotga ega emasman",
                    'ru' => "Нет, у меня почти нет информации об этом",
                    'en' => "No, I have almost no information about it",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '2',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisining hozirgi joylashuvi (lokatsiyasi) sizga qulaymi?",
                'ru' => "Удобно ли вам нынешнее расположение офиса Регистратора?",
                'en' => "Is the current location of the Registration Office convenient for you?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Juda qulay (topish oson, markazda yoki asosiy binoda)",
                    'ru' => "Очень удобно (легко найти, в центре или в главном корпусе)",
                    'en' => "Very convenient (easy to find, in the centre or main building)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "O'rtacha (biroz chekkaroq binoda yoki topish qiyinroq)",
                    'ru' => "Средне (в немного отдалённом здании или искать сложнее)",
                    'en' => "Average (in a somewhat remote building or harder to find)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Umuman qulay emas (juda uzoq yoki noqulay joyda)",
                    'ru' => "Совсем неудобно (очень далеко или в неудобном месте)",
                    'en' => "Not convenient at all (too far or in an inconvenient place)",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '3',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi xizmatlarining umumiy sifatini qanday baholaysiz?",
                'ru' => "Как вы оцениваете общее качество услуг офиса Регистратора?",
                'en' => "How would you rate the overall quality of the Registration Office services?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "A'lo (Muammosiz va tez)",
                    'ru' => "Отлично (без проблем и быстро)",
                    'en' => "Excellent (smooth and fast)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Qoniqarli (Yaxshi, lekin kamchiliklari bor)",
                    'ru' => "Удовлетворительно (хорошо, но есть недостатки)",
                    'en' => "Satisfactory (good, but with some shortcomings)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Qoniqarsiz (Xizmat ko'rsatish darajasi past)",
                    'ru' => "Неудовлетворительно (низкий уровень обслуживания)",
                    'en' => "Unsatisfactory (low service level)",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Umuman qoniqmayman",
                    'ru' => "Совсем не удовлетворён",
                    'en' => "Not satisfied at all",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '4',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi menejerlarining talabalarga bo'lgan munosabati va muomalasi qanday?",
                'ru' => "Каково отношение и обращение менеджеров офиса Регистратора со студентами?",
                'en' => "How would you describe the attitude and behaviour of the Registration Office managers towards students?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Juda xushmuomala va yordamga tayyor",
                    'ru' => "Очень вежливы и готовы помочь",
                    'en' => "Very polite and ready to help",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Neytral / Oddiy",
                    'ru' => "Нейтрально / обычно",
                    'en' => "Neutral / ordinary",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Qo'pol, beparvo yoki mensimaslik bilan munosabatda bo'lishadi",
                    'ru' => "Грубо, безразлично или с пренебрежением",
                    'en' => "Rude, indifferent, or dismissive",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Ko'pincha talabalarni eshitishni ham xohlashmaydi",
                    'ru' => "Часто даже не хотят выслушать студентов",
                    'en' => "Often they don't even want to listen to students",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '5',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Ofisga biror xizmat bilan borganingizda, ortiqcha ovoragarchilik (\"u yoqqa bor, bu yoqqa bor\" deb yugurtirish) holatlari kuzatiladimi?",
                'ru' => "Бывают ли случаи, когда при обращении в офис вас лишний раз гоняют («туда сходи, сюда сходи»)?",
                'en' => "When you visit the office for a service, do you encounter unnecessary running around (\"go here, go there\")?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Yo'q, hamma narsa bir joyda tez hal bo'ladi",
                    'ru' => "Нет, всё быстро решается в одном месте",
                    'en' => "No, everything is resolved quickly in one place",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Ba'zida chalg'ituvchi ma'lumotlar tufayli biroz yugurtirishadi",
                    'ru' => "Иногда из-за неверной информации немного гоняют",
                    'en' => "Sometimes I'm sent around due to misleading information",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Ha, tez-tez xonama-xona yoki bino-mabino sarson qilishadi",
                    'ru' => "Да, часто гоняют из кабинета в кабинет или из здания в здание",
                    'en' => "Yes, I'm often sent from room to room or building to building",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Mening muammom umuman hal bo'lmay, arosatda qolib ketgan",
                    'ru' => "Моя проблема вообще не решена и зависла",
                    'en' => "My issue hasn't been resolved at all and is stuck",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '5.1',
            'type'     => 'checkbox',
            'required' => true,
            'show_if'  => ['question_id' => '5', 'when_option' => 'c'],
            'text' => [
                'uz' => "Ko'pincha aynan qaysi masalalar yoki xizmatlar bo'yicha asossiz sarson bo'lish va yugur-yugurlar kuzatiladi? (Bir nechtasini tanlash mumkin)",
                'ru' => "По каким именно вопросам или услугам чаще всего наблюдается необоснованная беготня? (можно выбрать несколько)",
                'en' => "Which specific matters or services cause unjustified back-and-forth most often? (multiple choice)",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Imtihon sanalari, jadvallari yoki nazoratlarni aniqlashtirishda",
                    'ru' => "При уточнении дат экзаменов, расписаний или контролей",
                    'en' => "Clarifying exam dates, schedules, or controls",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Fandan fanga o'tish, qayta o'qish (kreditlarni qayta topshirish) yoki kreditlarni hisoblashda",
                    'ru' => "При переходе с предмета на предмет, повторном прохождении (пересдаче кредитов) или подсчёте кредитов",
                    'en' => "Switching subjects, retaking (re-doing credits), or calculating credits",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Ma'lumotnoma (spravka) yoki akademik transkript (baholar varaqasi) olishda",
                    'ru' => "При получении справки или академической транскрипта (выписки оценок)",
                    'en' => "Getting a reference letter or an academic transcript",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Arizalar topshirish yoki shartnomaviy masalalarni rasmiylashtirishda",
                    'ru' => "При подаче заявлений или оформлении договорных вопросов",
                    'en' => "Submitting applications or processing contract matters",
                ]],
                ['id' => 'e', 'text' => [
                    'uz' => "O'quv tizimidagi elektron xatoliklarni to'g'rilashda",
                    'ru' => "При исправлении электронных ошибок в учебной системе",
                    'en' => "Fixing electronic errors in the academic system",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '6',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Siz o'z muammoingiz yoki arizangiz bo'yicha Registrator ofisiga necha marta qayta-qayta borishga majbur bo'lasiz?",
                'ru' => "Сколько раз вам приходится повторно обращаться в офис Регистратора по своему вопросу или заявлению?",
                'en' => "How many times do you typically have to return to the Registration Office for the same issue or application?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Bir marta borishda hamma narsa hal bo'ladi",
                    'ru' => "С одного раза всё решается",
                    'en' => "Everything is resolved on the first visit",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Odatda 2-3 marta qayta borishga to'g'ri keladi",
                    'ru' => "Обычно приходится приходить 2–3 раза",
                    'en' => "Usually I have to return 2–3 times",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Bir muammo bo'yicha haftalab, 4 martadan ko'p qatnayman",
                    'ru' => "По одному вопросу хожу неделями, больше 4 раз",
                    'en' => "I keep coming for one issue for weeks, more than 4 times",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Murojaat qilsam ham muammom oylar davomida hal bo'lmay qolib ketadi",
                    'ru' => "Даже после обращения мой вопрос месяцами не решается",
                    'en' => "Even after applying, my issue stays unresolved for months",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '7',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Ofis menejerlarining o'z vazifasini qay darajada bilishi va professionalligini qanday baholaysiz?",
                'ru' => "Как вы оцениваете уровень компетентности и профессионализм менеджеров офиса?",
                'en' => "How would you rate the competence and professionalism of the office managers?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Yuqori (Savollarga aniq va to'g'ri javob berishadi)",
                    'ru' => "Высокий (на вопросы отвечают чётко и правильно)",
                    'en' => "High (they answer questions clearly and correctly)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "O'rtacha (Ba'zan aniq javob bera olishmaydi yoki tizimni yaxshi tushunishmaydi)",
                    'ru' => "Средний (иногда не могут дать точный ответ или плохо понимают систему)",
                    'en' => "Average (sometimes they can't give a precise answer or don't fully understand the system)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Past (Noto'g'ri ma'lumot berishadi yoki muammoni hal qilishni bilishmaydi)",
                    'ru' => "Низкий (дают неверную информацию или не знают, как решить проблему)",
                    'en' => "Low (they give incorrect information or don't know how to resolve the issue)",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '8',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi xodimlarini ish joyidan (belgilangan qabul vaqtida) topish darajasi qanday?",
                'ru' => "Насколько легко застать сотрудников офиса Регистратора на рабочем месте в часы приёма?",
                'en' => "How easy is it to find Registration Office staff at their workstation during the appointed hours?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Har doim o'z joyida bo'lishadi, navbat kelishi bilan qabul qilishadi",
                    'ru' => "Всегда на месте, принимают сразу, как подходит очередь",
                    'en' => "They are always present and receive students as the queue advances",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Ba'zida joyida bo'lishmaydi (kutib qolamiz)",
                    'ru' => "Иногда отсутствуют (приходится ждать)",
                    'en' => "Sometimes they are absent (I have to wait)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Ko'pincha ish vaqti bo'lishiga qaramay, xonada hech kim bo'lmaydi yoki tanaffus bahonasi bilan qabul qilishmaydi",
                    'ru' => "Часто, несмотря на рабочее время, в кабинете никого нет или под предлогом перерыва не принимают",
                    'en' => "Often, despite working hours, the room is empty or they refuse on the pretext of a break",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '9',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Menejerlar tomonidan qabul soatlari tugamasdan oldin qabulni asossiz to'xtatish yoki talabalarni qaytarib yuborish holatlari kuzatiladimi?",
                'ru' => "Бывают ли случаи, когда менеджеры необоснованно прекращают приём до окончания приёмных часов или отправляют студентов обратно?",
                'en' => "Do managers sometimes unjustifiably stop receiving before the appointed hours end or send students away?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Yo'q, qabul vaqtining oxirigacha navbatdagi hamma talabalarni qabul qilishadi",
                    'ru' => "Нет, принимают всех студентов из очереди до конца приёмных часов",
                    'en' => "No, they receive every student in the queue until the end of the hours",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Ba'zida qabul vaqti tugashiga 15-20 daqiqa qolganda \"bugun ulgurmaymiz\" deb qaytarishadi",
                    'ru' => "Иногда за 15–20 минут до конца говорят «сегодня не успеваем» и разворачивают",
                    'en' => "Sometimes 15–20 minutes before the end, they say \"we won't make it today\" and send you away",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Ha, tez-tez qabul vaqti to'liq tugamay turib eshikni yopib olishadi",
                    'ru' => "Да, часто закрывают двери задолго до окончания приёма",
                    'en' => "Yes, they often close the door well before the receiving hours end",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '10',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Ofisga murojaat qilganingizda navbatda o'rtacha qancha vaqt yo'qotasiz?",
                'ru' => "Сколько времени в среднем вы теряете в очереди при обращении в офис?",
                'en' => "On average, how much time do you lose in the queue when visiting the office?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "5-10 daqiqa (tez fursatda)",
                    'ru' => "5–10 минут (очень быстро)",
                    'en' => "5–10 minutes (very quickly)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "15-30 daqiqa",
                    'ru' => "15–30 минут",
                    'en' => "15–30 minutes",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "30 daqiqadan 1 soatgacha",
                    'ru' => "От 30 минут до 1 часа",
                    'en' => "From 30 minutes to 1 hour",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "1 soatdan ko'p vaqtim ketadi",
                    'ru' => "Больше 1 часа",
                    'en' => "More than 1 hour",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '11',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi tomonidan beriladigan ma'lumotlar, hujjatlar, buyruqlar va akademik qoidalarning shaffofligini (ochiqligini) qanday baholaysiz?",
                'ru' => "Как вы оцениваете прозрачность (открытость) предоставляемой офисом Регистратора информации, документов, приказов и академических правил?",
                'en' => "How would you rate the transparency (openness) of information, documents, orders, and academic rules provided by the Registration Office?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "To'liq shaffof (Hamma ma'lumotlar, mezonlar va natijalar ochiq, hamma uchun teng)",
                    'ru' => "Полностью прозрачно (вся информация, критерии и результаты открыты и равны для всех)",
                    'en' => "Fully transparent (all information, criteria, and results are open and equal for everyone)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Qisman shaffof (Ayrim ma'lumotlar ochiq, lekin ba'zi ichki qarorlar yoki qoidalar yopiq qoladi)",
                    'ru' => "Частично прозрачно (часть информации открыта, но некоторые внутренние решения и правила скрыты)",
                    'en' => "Partly transparent (some information is open, but some internal decisions or rules stay hidden)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Umuman shaffof emas (Qoidalar qanday ishlaydi, talabalar bilmaydi)",
                    'ru' => "Совсем не прозрачно (студенты не знают, как работают правила)",
                    'en' => "Not transparent at all (students don't know how the rules work)",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '12',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Akademik qarzdorlikni yopish, akademik ta'til yoki o'qishni ko'chirish kabi jarayonlar bo'yicha ma'lumotlar va yo'riqnomalar sizga qanchalik tushunarli yetkaziladi?",
                'ru' => "Насколько понятно вам доносят информацию и инструкции по таким процессам, как закрытие академической задолженности, академический отпуск или перевод?",
                'en' => "How clearly is information and guidance about processes such as closing academic debt, taking academic leave, or transferring delivered to you?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Juda tushunarli, hamma qoidalar va muddatlarni aniq bilamiz",
                    'ru' => "Очень понятно, мы чётко знаем все правила и сроки",
                    'en' => "Very clearly, we know all the rules and deadlines exactly",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Ma'lumotlar bor, lekin juda chalkash yoki atayin kech e'lon qilinadi",
                    'ru' => "Информация есть, но очень запутанная или нарочно объявляется поздно",
                    'en' => "Information exists, but it is confusing or deliberately announced late",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Umuman ma'lumot berilmaydi, hammasini ofisga borib, so'rab bilishga majburmiz",
                    'ru' => "Никакой информации не дают, всё приходится узнавать самим в офисе",
                    'en' => "No information is shared at all; we have to ask at the office to find out",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '13',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisi faoliyatini va xizmat ko'rsatish darajasini o'tgan yilgi holatga nisbatan qanday baholaysiz?",
                'ru' => "Как вы оцениваете работу и уровень обслуживания офиса Регистратора по сравнению с прошлым годом?",
                'en' => "How would you rate the Registration Office work and service level compared to last year?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Ijobiy tomonga o'zgargan (Sifat va tezlik oshgan)",
                    'ru' => "Изменилось в лучшую сторону (качество и скорость выросли)",
                    'en' => "Changed for the better (quality and speed have improved)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Deyarli o'zgarish yo'q (Xuddi o'tgan yilgidek)",
                    'ru' => "Почти без изменений (как и в прошлом году)",
                    'en' => "Almost no change (same as last year)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Salbiy tomonga o'zgargan (Muammolar ko'paygan, sifat tushgan)",
                    'ru' => "Изменилось в худшую сторону (проблем больше, качество ниже)",
                    'en' => "Changed for the worse (more issues, lower quality)",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Solishtira olmayman (Birinchi kursman)",
                    'ru' => "Не могу сравнить (я первокурсник)",
                    'en' => "Can't compare (I'm a first-year student)",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '14',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "So'nggi bir yil ichida ma'lumotlarni onlayn olish va onlayn xizmatlardan (oliy ta'lim elektron tizimi orqali ariza topshirish, ma'lumotnoma olish va h.k.) foydalanish imkoniyatlari qanchalik yaxshilandi?",
                'ru' => "Насколько улучшились за последний год возможности получать информацию онлайн и пользоваться онлайн-сервисами (подача заявлений через электронную систему высшего образования, получение справок и т.п.)?",
                'en' => "Over the past year, how much have the possibilities to get information online and use online services (submitting applications via the higher-education electronic system, getting references, etc.) improved?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Juda yaxshi tomonga o'zgardi, hozir ko'p narsani uydan turib onlayn hal qilsa bo'ladi",
                    'ru' => "Изменилось очень в лучшую сторону, теперь многое можно решить онлайн из дома",
                    'en' => "Greatly improved — many things can now be handled online from home",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Onlayn xizmatlar qo'shildi, lekin tizimlar ko'p qotadi yoki arizalar baribir qabul qilinmaydi",
                    'ru' => "Онлайн-сервисы появились, но системы часто зависают или заявления всё равно не принимаются",
                    'en' => "Online services were added, but the systems often freeze or applications still aren't accepted",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Hech qanday o'zgarish bo'lmadi, onlayn xizmatlar hali ham deyarli ishlamaydi",
                    'ru' => "Никаких изменений не произошло, онлайн-сервисы до сих пор почти не работают",
                    'en' => "Nothing has changed; online services still barely work",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '15',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Imtihonga bevosita kirish jarayonini (talabalarni tekshirish, kiritish tartibi va tashkilotchilikni) o'tgan yilga nisbatan qanday baholaysiz?",
                'ru' => "Как вы оцениваете сам процесс входа на экзамен (проверка студентов, порядок допуска и организация) по сравнению с прошлым годом?",
                'en' => "How would you rate the exam entry process itself (student check-in, admission order, organisation) compared to last year?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Ijobiy tomonga o'zgargan: Navbatlar kamaygan, ortiqcha kutishlarsiz, tez va tartibli kirib boryapmiz.",
                    'ru' => "Изменилось в лучшую сторону: очереди уменьшились, без лишних ожиданий, заходим быстро и организованно.",
                    'en' => "Changed for the better: queues are shorter, no extra waiting, we enter quickly and in order.",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "O'zgarish yo'q: O'tgan yilgidek o'rtacha darajada, ba'zida biroz kutishlar bo'ladi.",
                    'ru' => "Без изменений: на том же среднем уровне, иногда приходится немного подождать.",
                    'en' => "No change: same average level as last year, sometimes a small wait.",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Vaziyat yomonlashgan: Kirish qismida juda uzun navbatlar yuzaga kelyapti, tashkilotchilik past, ko'p kutamiz va ortiqcha ovoragarchiliklar bor.",
                    'ru' => "Стало хуже: на входе образуются очень длинные очереди, организация низкая, много ждём, есть лишние неудобства.",
                    'en' => "Got worse: very long queues form at the entrance, organisation is poor, we wait a lot and face extra hassle.",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Solishtira olmayman: Birinchi kursman.",
                    'ru' => "Не могу сравнить: я первокурсник.",
                    'en' => "Can't compare: I'm a first-year student.",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '16',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Imtihonlarga kirish uchun joriy etilgan yuzni tanish (Face ID) tizimi ishini qanday baholaysiz?",
                'ru' => "Как вы оцениваете работу системы распознавания лиц (Face ID), внедрённой для входа на экзамены?",
                'en' => "How would you rate the face-recognition (Face ID) system used to enter exams?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Juda yaxshi (Tez aniqlaydi, navbatlar hosil bo'lishining oldini oladi)",
                    'ru' => "Очень хорошо (быстро распознаёт, предотвращает образование очередей)",
                    'en' => "Very good (recognises quickly, prevents queues from forming)",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "O'rtacha (Ba'zida yaxshi tanimaydi yoki tizim sekin ishlaydi, biroz kutib qolamiz)",
                    'ru' => "Средне (иногда плохо распознаёт или работает медленно, приходится подождать)",
                    'en' => "Average (sometimes it doesn't recognise well or works slowly, we have to wait)",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Juda yomon (Yuzni qiyinchilik bilan taniydi, texnik nosozliklar ko'p bo'ladi va eshik tagida katta tirbandlik yaratadi)",
                    'ru' => "Очень плохо (с трудом распознаёт лицо, частые сбои, создаёт большие пробки у двери)",
                    'en' => "Very bad (struggles to recognise faces, frequent technical failures, creates big jams at the door)",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "Bizda bu tizim hali joriy etilmagan / foydalanmaganman",
                    'ru' => "У нас эта система пока не внедрена / я ею не пользовался",
                    'en' => "This system isn't in place for us yet / I haven't used it",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '17',
            'type'     => 'radio',
            'required' => true,
            'text' => [
                'uz' => "Registrator ofisida arizalarni ko'rib chiqish, navbatni chetlab o'tish yoki hujjatlarni rasmiylashtirishda tanish-bilishchilik, adolatsizlik yoki kamsitish holatlariga duch kelganmisiz?",
                'ru' => "Сталкивались ли вы со случаями кумовства, несправедливости или дискриминации при рассмотрении заявлений, обходе очереди или оформлении документов в офисе Регистратора?",
                'en' => "Have you encountered cases of favouritism, unfairness, or discrimination when applications were reviewed, queues were skipped, or documents were processed at the Registration Office?",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Yo'q, hamma talabaga teng, xolis va adolatli munosabatda bo'linadi",
                    'ru' => "Нет, ко всем студентам относятся одинаково, беспристрастно и справедливо",
                    'en' => "No, every student is treated equally, impartially, and fairly",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Shaxsan duch kelmaganman, lekin talabalar orasida shunday gaplarni ko'p eshitganman",
                    'ru' => "Лично не сталкивался, но среди студентов часто слышу такие разговоры",
                    'en' => "I haven't experienced it personally, but I've often heard such stories among students",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Ha, shaxsan o'zim tanish-bilishchilik yoki adolatsizlik holatiga duch kelganman",
                    'ru' => "Да, лично сталкивался со случаем кумовства или несправедливости",
                    'en' => "Yes, I have personally encountered favouritism or unfairness",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '18',
            'type'     => 'checkbox',
            'required' => true,
            'text' => [
                'uz' => "Sizningcha, Registrator ofisi faoliyatidagi eng katta muammo nimada? (Bir nechtasini tanlash mumkin)",
                'ru' => "По-вашему, в чём самая большая проблема в работе офиса Регистратора? (можно выбрать несколько)",
                'en' => "In your view, what is the biggest issue with the Registration Office? (multiple choice)",
            ],
            'options' => [
                ['id' => 'a', 'text' => [
                    'uz' => "Uzun navbatlar va ko'p vaqtning yo'qotilishi",
                    'ru' => "Длинные очереди и большие потери времени",
                    'en' => "Long queues and a lot of wasted time",
                ]],
                ['id' => 'b', 'text' => [
                    'uz' => "Imtihon kunlari kirish joyidagi tirbandliklar va yuzni tanish tizimidagi muammolar",
                    'ru' => "Заторы на входе в дни экзаменов и проблемы с системой распознавания лиц",
                    'en' => "Crowds at the entrance on exam days and issues with the face-recognition system",
                ]],
                ['id' => 'c', 'text' => [
                    'uz' => "Menejerlarning qo'pol muomalasi yoki mas'uliyatsizligi",
                    'ru' => "Грубое обращение или безответственность менеджеров",
                    'en' => "Rude behaviour or irresponsibility of the managers",
                ]],
                ['id' => 'd', 'text' => [
                    'uz' => "O'quv dasturi va elektron tizimlardagi texnik xatoliklar hamda baholarning noto'g'ri yoki kech kiritilishi",
                    'ru' => "Технические ошибки в учебной программе и электронных системах, неверный или поздний ввод оценок",
                    'en' => "Technical errors in the curriculum and electronic systems; wrong or late entry of grades",
                ]],
                ['id' => 'e', 'text' => [
                    'uz' => "Axborot va ko'rsatmalarning yetarli emasligi (talaba qayerga murojaat qilishni bilmaydi)",
                    'ru' => "Недостаток информации и инструкций (студент не знает, куда обращаться)",
                    'en' => "Insufficient information and guidance (students don't know where to apply)",
                ]],
                ['id' => 'f', 'text' => [
                    'uz' => "Ish vaqtiga rioya qilmaslik va xodimlarni joyidan topib bo'lmasligi",
                    'ru' => "Несоблюдение рабочего времени и невозможность застать сотрудников на месте",
                    'en' => "Not following working hours and being unable to find staff at their workstation",
                ]],
                ['id' => 'g', 'text' => [
                    'uz' => "Ofis joylashuvining noqulayligi",
                    'ru' => "Неудобное расположение офиса",
                    'en' => "Inconvenient office location",
                ]],
                ['id' => 'h', 'text' => [
                    'uz' => "Muammo yo'q, hammasi yaxshi",
                    'ru' => "Проблем нет, всё хорошо",
                    'en' => "No issues, everything is fine",
                ]],
                ['id' => 'other', 'text' => ['uz' => "Boshqa", 'ru' => "Другое", 'en' => "Other"], 'has_other' => true],
            ],
        ],
        [
            'id'       => '19',
            'type'     => 'text',
            'required' => false,
            'text' => [
                'uz' => "Qaysi menejer(lar) talabalarga nisbatan yomon munosabatda bo'ladi, qo'pollik qiladi yoki o'z vazifasini lozim darajada bajarmaydi? (Menejerning ismi-sharifi, fakulteti yoki oyna raqamini yozishingiz mumkin)",
                'ru' => "Какой менеджер (или менеджеры) плохо относится к студентам, грубит или не выполняет свои обязанности должным образом? (Можно указать ФИО менеджера, факультет или номер окна)",
                'en' => "Which manager(s) behave poorly with students, are rude, or don't perform their duties properly? (You may give the manager's name, faculty, or window number)",
            ],
            'placeholder' => [
                'uz' => "Menejer ismi, fakulteti yoki oyna raqami...",
                'ru' => "Имя менеджера, факультет или номер окна...",
                'en' => "Manager name, faculty, or window number...",
            ],
        ],
        [
            'id'       => '20',
            'type'     => 'text',
            'required' => false,
            'text' => [
                'uz' => "Aksincha, qaysi menejerning xizmatidan va muomalasidan juda mamnunsiz? (Namuna xodim)",
                'ru' => "Наоборот, услугами и обращением какого менеджера вы очень довольны? (образцовый сотрудник)",
                'en' => "On the contrary, which manager's service and behaviour are you very satisfied with? (an exemplary staff member)",
            ],
            'placeholder' => [
                'uz' => "Mamnun bo'lgan menejer ismi...",
                'ru' => "Имя менеджера, которым вы довольны...",
                'en' => "Name of the manager you are satisfied with...",
            ],
        ],
        [
            'id'       => '21',
            'type'     => 'text',
            'required' => false,
            'text' => [
                'uz' => "Registrator ofisi faoliyatini tubdan yaxshilash, imtihon jarayonlari hamda yuzni tanish tizimini takomillashtirish bo'yicha qanday aniq takliflaringiz bor?",
                'ru' => "Какие у вас конкретные предложения по коренному улучшению работы офиса Регистратора, экзаменационных процессов и совершенствованию системы распознавания лиц?",
                'en' => "What specific suggestions do you have for fundamentally improving the Registration Office, exam processes, and the face-recognition system?",
            ],
            'placeholder' => [
                'uz' => "Takliflaringizni yozing...",
                'ru' => "Напишите ваши предложения...",
                'en' => "Write your suggestions...",
            ],
        ],
    ],
];
