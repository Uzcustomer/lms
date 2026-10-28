# Ta’lim dasturlari bo‘yicha jadval kalendari

Bir fakultet, yo‘nalish va kursdagi turli HEMIS o‘quv rejalari alohida kalendar saqlaydi. Bog‘lanish `curriculum_hemis_id` orqali guruhdan aniqlanadi. Dastur nomi yoki «PIMU» yozuviga qarab taxmin qilinmaydi. Bir guruh bir nechta faol rejaga mos kelsa yoki eski ma’ruza bir necha rejani birlashtirsa, umumiy sozlama ishlaydi; bunday biriktirishni manbada aniqlashtirish kerak.

## Deploy

```sh
php artisan migrate --force
php artisan view:clear
```

Sozlamalar → Dars kunlari va paralar bo‘limida HEMIS o‘quv reja nomlari alohida qatorda ko‘rinadi. PIMU stomatologiya 1-kursining o‘z qatorida hafta sonini 17 qilib saqlang. Shu qatorda boshlanish sanasi va ta’tilni ham alohida belgilash mumkin. «Umumiy ta’til» belgilansa doska ta’tili olinadi; belgi o‘chirilgan va sanalar bo‘sh bo‘lsa dasturda ta’til yo‘q hisoblanadi.

Kalendarni saqlash kartalarni qayta yaratmaydi, hech bir joylashuv yoki haftalik istisnoni o‘chirmaydi. Qisqargan semestrdan tashqaridagi eski yozuvlar saqlanib, faol dars sifatida hisoblanmaydi. Migratsiya ham mavjud kartalar yoki joylashuvlarni o‘zgartirmaydi. O‘quv rejalarining qaysi biri PIMU ekanini tekshirmasdan avtomatik 17 hafta qo‘yilmaydi.

KTR va yangi kartochkalar guruhning o‘z rejasidan olinadi. Mavjud kartalar o‘z holida qoladi. KTR haftalarini qayta taqsimlash va yangi kartalarni yaratish alohida amallardir; ularni butun doska uchun avtomatik ishga tushirmang.

Siklning «shu fan boshqa oqimga joylangan» cheklovi fakultet va o‘quv reja doirasida qo‘llanadi. O‘qituvchi va aniq xona bandligi dasturlar orasida ham tekshiriladi. Haftalik bandlikda turli dasturlardagi bir xil hafta raqami bir xil sana deb olinmaydi.

Bu PR jadvallarni ishlab turgan serverga joylamaydi. Xorijiy 5-kurs va PIMU 1-/6-kurs Excel jadvallarini joylash deploy va kalendar sozlamalaridan keyingi alohida bosqichdir.

## Tekshiruv

```sh
php vendor/bin/pest tests/Unit/TimetableProgramCalendarTest.php tests/Unit/TimetableProgramCalendarIntegrationTest.php tests/Unit/HemisSchedulePlannerTest.php
node tests/Frontend/timetable-program-calendar.cjs
node tests/Frontend/timetable-compact-week.cjs
node tests/Frontend/timetable-chain-drag.cjs
node tests/Frontend/timetable-week-chain.cjs
node tests/Frontend/timetable-weekly-excel.cjs
```

Yangi integratsiya testlari faqat vaqtinchalik SQLite jadvallarida ishlaydi. Umumiy Feature testlari eski MySQL-only migratsiyadagi `information_schema.TABLE_CONSTRAINTS` so‘rovida SQLite bilan to‘xtaydi. `TimetableSpreadKeyTest` va `TimetableClusterPlacementTest` mainda allaqachon yo‘q `spreadKey` / `clusterPlacement` metodlariga murojaat qiladi.
