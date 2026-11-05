<?php

/*
 * Dars jadvali tuzish.
 *
 * 'profile' — sikl kunlarini surish (cycle-lecture-slots, day_indexes) bosqichlari vaqtini daily logga yozish (TIMETABLE_PROFILE=1).
 * Vaqtincha: sekinlik sababini haqiqiy doskada o'lchash uchun; sukut bo'yicha o'chiq.
 */
return [
    'profile' => (bool) env('TIMETABLE_PROFILE', false),
];
