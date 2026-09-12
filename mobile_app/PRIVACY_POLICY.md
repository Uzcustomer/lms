# TDTU LMS — Maxfiylik siyosati

**Oxirgi yangilanish:** 2026-09-11
**Ilova:** TDTU LMS (`uz.tashmedunitf.lms_mobile`)
**Mas'ul tashkilot:** Toshkent Davlat Tibbiyot Universiteti Termiz filiali (TDTU TF)
**Aloqa:** it@tashmedunitf.uz *(o'zgartiring)*

> Bu matn — qoralama. Nashrdan oldin universitet yuridik bo'limi bilan kelishib,
> `https://tashmedunitf.uz/privacy` (yoki boshqa doimiy URL) manzilida joylashtiring
> va Play Console → App content → Privacy policy maydoniga shu URL'ni kiriting.

## 1. Ilova nima uchun mo'ljallangan

TDTU LMS — universitet talabalari va xodimlari uchun ta'lim boshqaruv tizimining
mobil mijozi. Ilova faqat universitetning mavjud hisob yozuvlari (talaba ID /
xodim ID) bilan ishlaydi; ilova ichida yangi hisob yaratilmaydi.

## 2. Qanday ma'lumotlar yig'iladi

| Ma'lumot | Nima uchun | Qayerda saqlanadi |
|---|---|---|
| Login (talaba/xodim ID) va parol | Tizimga kirish | Parol saqlanmaydi; faqat server tomonidan berilgan sessiya tokeni qurilmaning himoyalangan xotirasida (Android Keystore) saqlanadi |
| Ism-familiya, guruh, fakultet, kurs | Profil va o'quv ma'lumotlarini ko'rsatish | Universitet serveri (`mark.tashmedunitf.uz`) |
| Baholar, davomat, dars jadvali, shartnoma to'lovi | Ilovaning asosiy funksiyalari | Universitet serveri; qurilmada 24 soatlik kesh |
| Telefon raqami, Telegram foydalanuvchi nomi | Profilni to'ldirish, bildirishnomalar | Universitet serveri |
| **Yuz tasviri (selfi)** | Faqat "Face ID orqali kirish" tanlanganda, shaxsni tasdiqlash uchun | Rasm serverga yuboriladi, mavjud profil surati bilan solishtiriladi va **saqlanmaydi** |
| Yuklangan hujjatlar (PDF, rasm) | Ruxsatnoma, apellyatsiya, sertifikat, mustaqil ish arizalari | Universitet serveri |
| AI yordamchiga yuborilgan xabar va fayllar | "TDTU AI Yordamchi" funksiyasi | Universitet serveri orqali Google Gemini API'ga uzatiladi (3-bo'limga qarang) |

Ilova reklama identifikatorlari, joylashuv, kontaktlar yoki qurilma
ma'lumotlarini yig'maydi va uchinchi tomon analitika/reklama SDK'larini
ishlatmaydi.

## 3. Uchinchi tomonlar

- **Google Gemini API** — AI yordamchi funksiyasi uchun. Sizning savolingiz,
  biriktirilgan fayllar va o'quv ma'lumotlaringiz (baholar, jadval) javob
  yaratish uchun Google serverlariga yuboriladi. Bu faqat siz AI yordamchini
  ochib, xabar yuborganingizda sodir bo'ladi.
- **Telegram** — bildirishnomalar va profilni tasdiqlash uchun; ilova sizni
  universitet botiga yo'naltiradi.
- **HEMIS** (`student.ttatf.uz`) — davlat ta'lim axborot tizimi; kirish
  ma'lumotlari tekshiruv uchun unga uzatilishi mumkin.

## 4. Qurilma ruxsatlari

- **Kamera** — Face ID orqali kirish uchun selfi olish.
- **Biometrika / qurilma qulfi** — ilovani qayta ochganda himoyalash (ixtiyoriy;
  sozlamalardan o'chiriladi). Biometrik ma'lumotlar ilovaga uzatilmaydi —
  tekshiruvni operatsion tizim bajaradi.
- **Fayllar** — hujjat va rasm yuklash uchun (faqat siz tanlagan fayllar).
- **Internet** — server bilan aloqa.

## 5. Ma'lumotlarni saqlash muddati va o'chirish

O'quv ma'lumotlari universitetning ichki qoidalariga muvofiq saqlanadi.
Qurilmadagi kesh va sessiya tokeni "Chiqish" tugmasi bosilganda yoki ilova
o'chirilganda yo'q qilinadi. Hisob yozuvini o'chirish yoki ma'lumotlaringiz
haqida so'rov yuborish uchun yuqoridagi manzilga murojaat qiling.

## 6. Xavfsizlik

Barcha aloqa faqat HTTPS orqali amalga oshiriladi. Sessiya tokeni Android
Keystore / iOS Keychain'da saqlanadi. Ilova parolni qurilmada saqlamaydi.

## 7. Bolalar

Ilova 16 yoshdan katta talabalar va xodimlar uchun mo'ljallangan.

## 8. O'zgarishlar

Siyosat yangilanganda "Oxirgi yangilanish" sanasi o'zgaradi; muhim
o'zgarishlar haqida ilova orqali xabar beriladi.
