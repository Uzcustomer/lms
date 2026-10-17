<?php

/*
 * Kafedralar bo'linishi / birlashishi.
 *
 * 'predecessors' — yangi kafedra (HEMIS department id) => eski kafedra(lar) id.
 * Yangi kafedra mudiri o'qituvchi biriktirishda eski kafedra o'qituvchilarini ham
 * ko'radi va biriktira oladi (HEMIS da o'qituvchilar hali eski kafedrada turgan bo'lsa).
 *
 * 2026-10: "Mikrobiologiya, jamoat salomatligi, gigiyena va menejment" (41) ikkiga
 * bo'lindi: "Jamoat salomatligi, gigiyena va menejment" (37) va
 * "Mikrobiologiya va epidemiologiya" (128).
 */
return [
    'predecessors' => [
        37 => [41],
        128 => [41],
    ],
];
