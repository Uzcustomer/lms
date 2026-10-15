# mark.tashmedunitf.uz — «MONITORING» MODULI: TEXNIK TOPSHIRIQ

Buyurtmachi: TDTU Termiz filiali, Registrator ofisi boshlig'i A.K. Sharipov
Ijrochi: Claude Code (kod) + Raqamli ta'lim texnologiyalari markazi (ko'rib chiqish, deploy)
Versiya: 1.0 — 15.10.2026
Ilova: `docs/detectors.yaml` — 54 ta nazorat qoidasi (mashina o'qiy oladigan katalog)

---

## 0. CLAUDE CODE UCHUN ISH QOIDALARI (avval shuni o'qing)

1. **Birinchi qadam — o'rganish, kod emas.** Repozitoriyni o'qing va `docs/ARXITEKTURA_XULOSA.md`
   yozing: til/freymvork, papka tuzilishi, autentifikatsiya va rollar qanday ishlaydi, mavjud
   modellar (talaba, guruh, fan, qaydnoma, tyutor), mavjud HEMIS integratsiyasi bormi, testlar
   qanday ishga tushadi, migratsiya tizimi. Shundan keyin ushbu TZ ni repozitoriy uslubiga
   moslab **reja** taklif qiling va buyurtmachi tasdiqlagach yozishni boshlang.
2. **Mavjud uslubga moslashing.** Yangi freymvork, ORM yoki og'ir kutubxona qo'shmang; repozitoriyda
   qabul qilingan naming, struktura va UI komponentlaridan foydalaning.
3. **Faqat alohida branch:** `feature/monitoring`. `main`/`master` ga to'g'ridan-to'g'ri push qilinmaydi.
   Har bosqich — alohida pull request, RTM ko'rib chiqadi.
4. **Maxfiy ma'lumotlar:** tokenlar, parollar, HEMIS API kaliti faqat `.env` da; `.env.example` ga
   faqat kalit nomlari. Hech qachon commit qilinmaydi.
5. **Shaxsga doir ma'lumotlar:** testlar va seed — faqat soxta (fake) ma'lumotlar. Haqiqiy talaba
   ma'lumotlari repozitoriyga, loglarga, test fixture'larga tushmasin. Kirish rollar bo'yicha
   cheklanadi; har bir ko'rish/o'zgartirish audit jurnaliga yoziladi.
6. **Muddatlar va chegaralar kodga qotirilmaydi.** Ular `detectors.yaml` → keyin DB dagi sozlamalar
   jadvalidan o'qiladi; panel orqali dasturchisiz o'zgartiriladi (qonun o'zgarganda).
7. **Har detektor uchun test:** kamida bitta «buzilish bor» va bitta «buzilish yo'q» holati.
8. Til — interfeys o'zbek tilida (lotin). Kod, izoh va commit xabarlari — loyiha uslubida.

---

## 1. MAQSAD

Registrator ofisi zimmasidagi normativ muddat va qoidalarning bajarilishini **avtomatik**
kuzatish: tizim ma'lumotlarini solishtirib, qoidabuzarliklarni (istisnolarni) topadi, ularni
mas'ulga biriktiradi, hal bo'lguncha kuzatadi va rahbariyatga statistikani beradi.

Asosiy tamoyil: **har bir detektor = ikki to'plamning farqi yoki ikki sananing farqi.**
Masalan: «semestrda sababsiz 74 soatdan ortiq qoldirganlar» to'plami ↔ «chetlashtirish
buyruqlari» to'plami; birinchisida bor, ikkinchisida yo'q bo'lgan talaba = istisno.

## 2. FOYDALANUVCHI ROLLARI

| Rol | Ko'radi | Qiladi |
|---|---|---|
| **Monitoring administratori** (Registrator ofisi boshlig'i) | hammasi | detektorlarni yoqish/o'chirish, chegara va muddatlarni sozlash, istisnoni mas'ulga biriktirish, yopish/qayta ochish, «asosli istisno» deb belgilash |
| **Registrator ofisi menejeri** | hammasi | ma'lumot importi, istisnolar bilan ishlash |
| **Mas'ul xodim** (dekan, kafedra mudiri, buxgalter, tyutor, kotib) | faqat o'ziga biriktirilgan istisnolar | javob yozish, dalil fayl yuklash, «bajarildi» deb belgilash (yopishni administrator tasdiqlaydi) |
| **Rahbariyat** (direktor, o'rinbosarlar) | panel va svodlar (o'qish) | — |
| **RTM** | texnik loglar | integratsiya va importni sozlash |

Rollar mark dagi mavjud rol tizimiga qo'shiladi.

## 3. MA'LUMOTLAR MODELI (taklif — mavjud sxemaga moslang)

- **Detector** — `code` (D-2…), `group`, `title`, `rule_text`, `legal_basis`, `params` (JSON:
  chegaralar, muddatlar), `severity`, `enabled`, `pilot`, `default_owner_role`, `schedule`.
  Boshlang'ich ma'lumot `docs/detectors.yaml` dan seed qilinadi.
- **DetectorRun** — qachon, qaysi detektor, nechta istisno topdi, xato bo'ldimi, manba ma'lumot
  versiyasi (import id).
- **Exception (istisno)** — `detector`, `subject_type` (talaba / buyruq / qaydnoma / ariza / to'lov),
  `subject_id`, `subject_label`, `found_at`, `due_at` (javob muddati; standart — 3 ish kuni),
  `owner_user`, `status` (`ochiq` → `javob berildi` → `yopildi` | `asosli istisno` | `qayta ochildi`),
  `details` (JSON: nima topildi), `fingerprint` (bir xil istisno qayta-qayta yaratilmasligi uchun).
- **ExceptionComment** — mas'ul javobi, administrator izohi, holat o'zgarishi tarixi.
- **ExceptionEvidence** — dalil fayl (buyruq nusxasi, xat, skan).
- **CalendarDeadline** — yillik qat'iy sanalar (7.9-bo'lim), har biriga eslatma qoidasi.
- **DataImport** — HEMIS/Excel importlari: manba, sana, qatorlar soni, xatolar.
- **AuditLog** — kim qachon nimani ko'rdi/o'zgartirdi (mavjud audit bo'lsa, o'shandan foydalaning).

**Takrorlanmaslik:** detektor har ishga tushganda bir xil holat uchun yangi istisno ochmaydi —
`fingerprint` bo'yicha mavjud ochiq istisno yangilanadi. Holat o'z-o'zidan bartaraf bo'lsa
(masalan buyruq chiqdi), istisno avtomatik «yopildi (tizim)» bo'ladi, lekin tarixda qoladi.

## 4. MA'LUMOT MANBALARI

1. **mark ning o'z bazasi** — qaydnomalar, tasdiqnomalar, mavjud shakllar.
2. **HEMIS** (hemis.ttatf.uz) — kontingent, buyruqlar (19 tur), qaydnomalar va baholar, davomat,
   GPA, qarzdorlik. Avval HEMIS API mavjudligini va RTM dagi tokenni aniqlang; API bo'lmasa —
   **Excel/CSV import** (ustunlarni moslashtirish ekrani bilan).
3. **Qo'lda kiritiladigan shakllar** (mark ichida): M1–M8 (8-bo'lim).
4. **Tashqi tizimlar (billing.edu.uz, EDO, Agentlik platformasi)** — API yo'q; hozircha qo'lda
   kiritish shakli yoki fayl importi. Avtomatik skreping QILINMASIN.

## 5. DETEKTORLAR DVIGATELI

- Har bir detektor — alohida klass/funksiya, umumiy interfeys: `run(params) -> list[Finding]`.
- Parametrlar `Detector.params` dan (masalan D-2: `threshold_hours: 74`, `exclude_medical: true`).
- Ishga tushirish: rejalashtiruvchi (cron / mavjud queue) — standart **har kuni tunda**;
  administrator «hozir ishga tushir» tugmasi bilan ham.
- `status: ⚠` bo'lgan detektorlar `enabled: false` — ular faqat administrator yoqqanda,
  «ogohlantirish» rejimida ishlaydi (istisno mas'ulga avtomatik yuborilmaydi).
- Detektor xato bersa — run «xato» bilan yoziladi, administratorga xabar.

## 6. INTERFEYS

1. **Administrator paneli:** ochiq / muddati o'tgan / shu hafta yopilgan istisnolar soni;
   detektor guruhlari va bo'linmalar kesimida; eng yaqin kalendar sanalari (14 kun ichida);
   oxirgi importlar holati.
2. **Istisnolar ro'yxati:** filtrlar (detektor, guruh, holat, mas'ul, bo'linma, muddat), ommaviy
   biriktirish, Excel eksport.
3. **Istisno kartochkasi:** nima topildi, huquqiy asos (band raqami bilan), tarix, javoblar,
   dalillar, holat tugmalari.
4. **Mas'ul kabineti:** «Menga biriktirilganlar» — faqat o'zinikilar, javob va fayl yuklash.
5. **Detektorlar sozlamasi:** ro'yxat, yoqish/o'chirish, parametrlarni tahrirlash (o'zgarish
   audit jurnaliga yoziladi), oxirgi ishga tushish natijasi.
6. **Oylik svod:** detektor va bo'linma kesimida topilgan/yopilgan/muddati o'tgan; PDF/Excel.

## 7. ESLATMALAR

- Istisno biriktirilganda va muddatdan 1 kun oldin — mas'ulga xabar (mark ichki bildirishnoma;
  Telegram bot mavjud bo'lsa — u orqali ham).
- Muddati o'tganda — mas'ul va administratorga.
- Kalendar sanalaridan 14 va 3 kun oldin — administratorga.

## 8. QO'SHIMCHA SHAKLLAR (M1–M8) — keyingi bosqichlar

| Kod | Shakl | Qaysi detektorlarni oziqlantiradi |
|---|---|---|
| M1 | Mas'ul xodim tasdiqnomasi (ijara) — imzolangan skanni yuklash, yuklagan shaxs/sana-vaqt qayd etiladi | F-1 |
| M2 | Ijara/TTJ sikli paneli: 7 → 12 → 13 → 14 → 15 checkpointlari | F-1, F-5 |
| M3 | Muddatli majburiyatlar jurnali (xabarnomalar: buyruqdan keyin 1 ish kuni va h.k.) | E-1, A-5 |
| M4 | Qaydnoma intizomi hisoboti | Q-1, Q-1b |
| M5 | Apellyatsiya jurnali | Q-5, H-7 |
| M6 | Komissiya va kengashlar reestri (tarkib, amal muddati, yig'ilish) | O-1, H-4 |
| M7 | Murojaatlar jurnali | P-1 |
| M8 | Ko'chirib kelinganlar yig'majild cheklisti (6 majburiy hujjat + asl tekshiruvi + so'rovnoma reyestri) | E-7, E-5 |

## 9. BOSQICHLAR

**1-bosqich (PILOT) — 5 detektor + yadro:**
yadro (Detector, Exception, DetectorRun, rollar, ro'yxat, kartochka, mas'ul kabineti, panel) va
pilot detektorlar (`pilot: true`):

| Kod | Qoida | Minimal ma'lumot |
|---|---|---|
| **A-5** | 2026/2027 kontrakt bo'yicha tavsiya etilganlar: to'lov va qabul buyrug'i — 01.11.2026 gacha | tavsiya etilganlar ro'yxati, to'lovlar, qabul buyruqlari |
| **D-2** | Semestrda sababsiz > 74 soat (tibbiy ma'lumotnoma istisno) ↔ chetlashtirish buyrug'i | davomat (sababsiz soat), buyruqlar |
| **Q-1** | Yakuniy nazorat baholari nazoratdan keyin 3 kun ichida HEMISga | nazorat jadvali sanasi, baho kiritilgan sana |
| **E-1** | Akademik ta'til / chetlashtirish / kursda qoldirish buyrug'i → 1 ish kunida ijara kotibi va buxgalteriyaga xabar | buyruqlar, xabarnoma jurnali (M3 soddalashtirilgan) |
| **E-7** | Ko'chirib kelgan talaba yig'majildida 6 majburiy hujjat | M8 cheklisti (soddalashtirilgan) |

**Qabul qilish mezonlari (pilot):**
- [ ] 5 detektor test ma'lumotlarida to'g'ri istisno topadi (har biriga ≥ 2 test);
- [ ] qayta ishga tushirishda dublikat istisno yaratilmaydi;
- [ ] holat bartaraf bo'lsa istisno avtomatik yopiladi, tarixda qoladi;
- [ ] mas'ul faqat o'z istisnolarini ko'radi;
- [ ] detektor parametri panel orqali o'zgartirilsa — keyingi ishga tushishda kuchga kiradi;
- [ ] `.env.example`, migratsiyalar, README (o'rnatish va ishga tushirish) bor;
- [ ] haqiqiy shaxsiy ma'lumot repozitoriyga tushmagan.

**2-bosqich:** qolgan `enabled: true` detektorlar guruhma-guruh (davomat → baholash → harakat →
moliya → YaDA), kalendar va eslatmalar, oylik svod.
**3-bosqich:** M1–M8 shakllar, HEMIS API to'liq integratsiyasi, KPI ga ulash.

## 10. HUQUQIY MANBALAR (detektorlardagi «legal_basis» uchun)

Asosiy: Filial Baholash tartibi (2025–2026) · Direktor buyrug'i 02216 (23.05.2026, 42–46-bandlar
talqini) · VMQ 578 (13.09.2025; 1-il. qabul, 3-il. ko'chirish/tiklash/chetlashtirish) ·
VMQ 836 (YaDA) · DR-2431 (to'lov-kontrakt) · VMQ 605, VMQ 32 (ijara, TTJ) · VMQ 299 ·
VMQ 501 + OTFIV 417 + buyruq 02458 · OTFIV 451 (davomat, baho tuzatish) · PF-58 va OTFIV
komissiyasi 7-son bayoni (01.11.2026) · O'RQ-445 (murojaatlar).
To'liq matnlar Registrator ofisining hujjatlar papkasida (`_MATN/`). Kodga normativ matn
ko'chirilmaydi — faqat band raqami va qisqa izoh.

## 11. OCHIQ SAVOLLAR (Claude Code o'rganish bosqichida javob topsin yoki so'rasin)

1. mark qaysi stekda yozilgan va qayerda ishlaydi? Sinov (staging) muhiti bormi?
2. mark da talaba/guruh/fan ma'lumotlari o'zida saqlanadimi yoki HEMIS dan olinadimi?
3. HEMIS API mavjudmi, qaysi endpointlar, token kimda?
4. Mavjud rol va bildirishnoma tizimi qanday?
5. Telegram bot integratsiyasi bormi?
