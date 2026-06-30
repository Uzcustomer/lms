<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Tokensiz "Qayta o'qish" quizlari uchun cutoff sanasi
    |--------------------------------------------------------------------------
    | Moodle quiz nomlari 2026-apreldan boshlab yil-fasl tokeni bilan
    | (mas. "...Qayta-o'qish-2025-2026-yozgi") kelmoqda. Undan oldin esa
    | tokensiz ("...Qayta-o'qish") kelgan va bitta quizda bir nechta o'tgan
    | fasl natijalari aralash.
    |
    | Tokensiz qayta o'qish urinishi date_finish SHU SANADAN >= bo'lsa —
    | joriy ochiq sessiyaga (yozgi 2025-2026) tegishli deb olinadi. Undan
    | oldingilari o'tgan fasllarga tegishli va joriy sessiyaga olinmaydi.
    |
    | Yozgi 2025-2026 sessiyasi 2026-05-07 dan boshlangan.
    */
    'tokenless_open_cutoff' => env('RETAKE_TOKENLESS_CUTOFF', '2026-05-07'),
];
