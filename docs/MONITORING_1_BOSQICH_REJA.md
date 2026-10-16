# «Monitoring» moduli — 1-bosqich (pilot) rejasi

Holat: **ish boshlandi** (16.10.2026). Buyurtmachi javoblari 11-bo'limda; ish `feature/monitoring`
branch'ida.
Asos: `docs/MONITORING_TZ.md` (v1.0), `docs/detectors.yaml` (54 detektor), `docs/ARXITEKTURA_XULOSA.md`.

---

## 1. Umumiy yondashuv

- Yangi freymvork yoki og'ir kutubxona yo'q: Laravel model/migratsiya, `app/Services/Monitoring/`,
  artisan buyruq va mavjud scheduler, Blade va jQuery/Alpine sahifalar (`x-app-layout`),
  Excel uchun `maatwebsite/excel`. Yagona yangi bog'liqlik — `symfony/yaml`. U hozir faqat
  dev-bog'liqlik; prod'da `detectors.yaml` ni o'qish uchun `require` ga ko'chiriladi. Bu kichik
  Symfony komponenti, yangi tranzitiv bog'liqlik qo'shmaydi.
- Detektorlar HEMIS'ga to'g'ridan-to'g'ri so'rov yubormaydi. Ular mark bazasidagi HEMIS nusxasi
  ustida ishlaydi va shu sababli tungi HEMIS importlaridan keyin ishga tushadi.
- Hamma narsa `/admin/monitoring/*` ostida. Kirish huquqi route'dagi rol bilan **va** controller'dagi
  Policy bilan alohida tekshiriladi, chunki mavjud route middleware faol rolni emas, istalgan rolni
  tekshiradi.

## 2. Branch va pull request'lar

- Kod `feature/monitoring` branch'ida yoziladi (TZ 0.3); `main` ga push qilinmaydi.
- Pilotni ko'rib chiqish oson bo'lishi uchun ikkita PR taklif qilaman:
  - **PR 1a — yadro + A-5 + D-2.** A-5 ning muddati 01.11.2026, ya'ni 17 kun qoldi, shuning uchun
    u birinchi navbatda.
  - **PR 1b — Q-1, E-1, E-7** va M3/M8 ning soddalashtirilgan shakllari.
- **PR 0 (Monitoring'dan tashqari) — `claude/new-session-qm95zg` branch'ida tayyor.** Arxitektura
  xulosasining 10-bo'limidagi xavfsizlik muammolari, platforma ishiga ta'sir qilmaydigan qismi:
  rol berish, parol tiklash va foydalanuvchilarni boshqarish faqat superadmin/admin/kichik_admin'ga;
  `public/` dagi debug skriptlar va phpMyAdmin yopildi; `.env.example` va Moodle skriptidagi sirlar
  olib tashlandi; haqiqiy shaxsiy ma'lumotli CSV o'chirildi.

## 3. Ma'lumotlar modeli

Nomlash repozitoriy uslubida: modellar `app/Models` da flat, `Monitoring` prefiksi bilan;
jadvallar `monitoring_` prefiksi bilan. «Exception» o'rniga **`MonitoringIstisno`** nomini taklif
qilaman. Sabab: PHP'ning `\Exception` klassi bilan chalkashmaydi va interfeysdagi atama bilan bir xil.
Jadval nomi repozitoriydagi `oraliq_nazorats` uslubida `monitoring_istisnos` bo'ladi.

| Jadval | Asosiy ustunlar |
|---|---|
| `monitoring_detectors` | `code` (unique), `group_name`, `title`, `rule_text`, `legal_basis`, `detection_text`, `catalog_status` (✅/✏️/🆕/⚠), **`params` JSON**, `severity`, `enabled`, `pilot`, `warning_mode`, `default_owner_role`, `due_workdays` (standart 3), `schedule`, `last_run_at` |
| `monitoring_runs` | `detector_id`, `started_at`, `finished_at`, `status` (ishlamoqda/ok/xato), `found` / `opened` / `updated` / `closed` sonlari, `error_message`, `triggered_by` (scheduler yoki foydalanuvchi), `params_snapshot` JSON, `data_import_id` |
| `monitoring_istisnos` | `detector_id`, `first_run_id`, `fingerprint` (detektor ichida unique), `subject_type` (talaba/buyruq/qaydnoma/ariza/to'lov/guruh_fan), `subject_ref`, `subject_label`, `faculty_hemis_id` va `department_hemis_id` (filtr va svod uchun), `found_at`, `last_seen_at`, `due_at`, **`owner_type` + `owner_id`** (polimorf: `Teacher` yoki `User`), `assigned_by_*`, `assigned_at`, `status` (`ochiq` → `javob_berildi` → `yopildi` / `asosli_istisno` / `qayta_ochildi`), `closed_by_system`, `closed_at`, `warning_only`, `details` JSON |
| `monitoring_istisno_comments` | `istisno_id`, `author_type/id`, `kind` (javob/izoh/holat), `body`, `old_status`, `new_status` |
| `monitoring_istisno_evidences` | `istisno_id`, fayl (`storage/app/private/monitoring/...`, faqat Policy orqali beriladi), `original_name`, `mime`, `size`, `uploaded_by_*` |
| `monitoring_data_imports` | `kind` (buyruqlar/tavsiya_etilganlar/...), `source` (hemis_api/excel/csv), `file_name`, `rows_total`, `rows_ok`, `rows_error`, `errors` JSON, `imported_by_*` |
| `monitoring_orders` — **buyruqlar reyestri** | `data_import_id`, `hemis_id` (agar API bo'lsa), `order_number`, `order_date`, `order_type` (normallashtirilgan: chetlashtirish, akademik_tatil, kursda_qoldirish, qabul, kochirish, tiklash ...), `order_type_raw`, `student_hemis_id`, `student_id_number`, `full_name`, `basis` |
| `monitoring_admission_recommendations` (A-5) | import: abituriyent identifikatori, F.I.Sh., mutaxassislik, to'lov shakli, tavsiya sanasi, o'quv yili |
| `monitoring_notice_logs` (M3, soddalashtirilgan) | `order_id`, `recipient_kind` (ijara_kotibi/buxgalteriya), `recipient_name`, `sent_at`, `method`, dalil fayli, kim kiritgani |
| `monitoring_transfer_checklists` (M8, soddalashtirilgan) | `student_id`, 6 hujjat (har biri: bor/yo'q, asli tekshirildi, sana, fayl), `checked_by`, `checked_at`, `note` |

- `CalendarDeadline` va oylik svod 2-bosqichga qoldiriladi (TZ 9-bo'lim).
- Audit: `MonitoringDetector` va `MonitoringIstisno` modellariga `LogsActivity` trait'i ulanadi
  (parametr, holat va mas'ul o'zgarishlari). Kartochkani ko'rish va eksport
  `ActivityLogService::log('view'|'export', 'monitoring', ...)` orqali yoziladi (TZ 0.5).
- Shaxsiy ma'lumot minimal saqlanadi. `details` JSON ga faqat aniqlash uchun kerakli raqamlar
  yoziladi (masalan soatlar, sanalar). Pasport yoki telefon yozilmaydi.

## 4. Detektorlar dvigateli

```
app/Services/Monitoring/
  Contracts/Detector.php        code(), run(array $params, RunContext $ctx): iterable<Finding>
  Finding.php                   fingerprint, subject_*, faculty/department, owner (ixtiyoriy), details
  DetectorRegistry.php          code → klass (config/monitoring.php)
  DetectorRunner.php            run yozuvi, natijalarni istisnolar bilan solishtirish
  OwnerResolver.php             default_owner_role → aniq xodim (dekan, kafedra mudiri ...)
  Detectors/D2UnexcusedAbsence.php, Q1FinalGradesLate.php, A5ContractAdmission.php,
            E1OrderNotice.php, E7TransferFile.php
app/Console/Commands/MonitoringRun.php   monitoring:run {code?} {--all} {--dry-run}
app/Jobs/RunMonitoringDetectorJob.php    «Hozir ishga tushir» tugmasi uchun (database queue)
```

`DetectorRunner` solishtirish qoidalari:
1. Yangi `fingerprint` → yangi istisno (`ochiq`), `due_at = bugun + due_workdays` ish kuni
   (`WorkdayCalculator`). Mas'ul aniqlansa, darhol biriktiriladi va xabar yuboriladi.
2. Mavjud ochiq istisno yana topilsa → `last_seen_at` va `details` yangilanadi, dublikat yaratilmaydi.
3. Ochiq istisno bu safar topilmasa → `yopildi`, `closed_by_system = true`; tarix saqlanadi.
   Bu qoida **faqat run to'liq muvaffaqiyatli bo'lganda** qo'llanadi. Xato bo'lsa, hech narsa yopilmaydi.
4. Tizim yopgan istisno qayta topilsa → `qayta_ochildi`. Administrator `asosli_istisno` yoki
   `yopildi` qilgani avtomatik qayta ochilmaydi.
5. `warning_mode` (katalogda ⚠) → istisno `warning_only` bilan yaratiladi, mas'ulga
   biriktirilmaydi va xabar yuborilmaydi (TZ 5-bo'lim).
6. Xato → run `xato` holatida yoziladi. Administratorga ichki bildirishnoma, Telegram admin chatiga xabar.

Jadval: `routes/console.php` ga `monitoring:run --all` har kuni **05:30** da,
`withoutOverlapping()` bilan. Bu vaqtda tungi HEMIS importlari (00:30, 03:00, 04:00) tugagan bo'ladi.

**Parametrlar.** `detectors.yaml` dagi yozuvlarda hozir `params` yo'q. Pilot detektorlar uchun
quyidagi kalitlarni yaml'ga qo'shishni taklif qilaman (qiymatlar TZ va katalog matnidan olingan):

```yaml
  - code: D-2
    params: { threshold_hours: 74, exclude_medical: true, medical_reasons: [kasallik, tibbiy_korik] }
    default_owner_role: dekan
  - code: Q-1
    params: { days: 3, day_type: workdays, include_resits: false, late_completion: auto_close }
    default_owner_role: kafedra_mudiri
  - code: A-5
    params: { deadline: "2026-11-01", education_year: "2026-2027",
              min_paid_percent: 25, min_paid_percent_special: 50 }
    default_owner_role: registrator_ofisi
  - code: E-1
    params: { workdays: 1, order_types: [akademik_tatil, chetlashtirish, kursda_qoldirish],
              recipients: [ijara_kotibi, buxgalteriya], since_date: "2026-10-01" }
    # ijara kotibi (Muhammadzafar) va buxgalteriya qabul qiluvchilari panelda tanlanadi
    default_owner_role: registrator_ofisi
  - code: E-7
    params: { required_docs: 6, require_original_check: true }
    default_owner_role: registrator_ofisi
```

`MonitoringDetectorSeeder` `docs/detectors.yaml` ni o'qib, `code` bo'yicha upsert qiladi.
Mavjud yozuvlarda faqat matnli maydonlar yangilanadi. Panelda o'zgartirilgan `params` va `enabled`
qayta yozilmaydi, buning uchun alohida `--reset-params` kerak bo'ladi. 49 ta pilot bo'lmagan
detektor ham katalogga yoziladi, lekin klassi yo'q bo'lgani uchun «hali amalga oshirilmagan»
holatida ko'rinadi.

## 5. Pilot detektorlar

| Kod | Trigger to'plami | Kutilgan harakat to'plami | Fingerprint | Avtomatik yopilish | Mas'ul |
|---|---|---|---|---|---|
| **D-2** | Joriy semestr bo'yicha `SUM(attendances.absent_off)` > `threshold_hours`. `exclude_medical` yoqilgan bo'lsa, LMS'da tasdiqlangan tibbiy `AbsenceExcuse` qamragan kunlardagi soatlar ayiriladi | `monitoring_orders` da shu talabaga `chetlashtirish` buyrug'i bor yoki `student_status_code` endi «o'qimoqda» emas | `D-2:{student_hemis_id}:{o'quv yili}:{semestr}` | buyruq paydo bo'ldi, holat o'zgardi yoki soat chegaradan tushdi | talaba fakulteti dekani (`dean_faculties`) |
| **Q-1** | YN sanasi — `exam_schedules` dagi OSKI va test sanalarining kechrog'i (vedomost `base_date` mantig'i bilan bir xil) + **3 ish kuni** < bugun | guruhning faol talabalari uchun `hemis_exam_grades` da YN bahosi (`exam_type_code = 13`) bor | `Q-1:{group}:{subject}:{semestr}:{tur}:{sana}` | barcha baholar kiritildi (kechikish `details` da qoladi) | fan kafedrasi mudiri (`curriculum_subjects.department_id`) |
| **A-5** | 2026/2027 kontrakt bo'yicha tavsiya etilganlar (import) | (a) to'lov ≥ `min_paid_percent` (`contract_list`), (b) qabul buyrug'i (`monitoring_orders`), (c) HEMIS kontingentida bor. Signal: to'lamagan bo'lib kontingentda turgan yoki to'lab buyrug'i chiqmagan. Xabarnoma dalillari (sayt, SMS) — bitta tekshiruv nuqtasi | `A-5:{abituriyent id}` (+ `A-5:xabarnoma`) | uchala shart bajarildi | registrator ofisi |
| **E-1** | `monitoring_orders` dagi akademik ta'til, chetlashtirish yoki kursda qoldirish buyrug'i | ijara kotibi va buxgalteriyaga xabar yuborilgani `monitoring_notice_logs` da qayd etilgan, `sent_at` ≤ buyruq sanasi + 1 ish kuni. Registrator «Xabar yuborish» tugmasini bossa, mark xabarni o'zi yuboradi (ichki bildirishnoma + Telegram) va yozuvni o'zi qo'yadi | `E-1:{order_id}` | ikkala yozuv ham kiritildi (kechikish `details` da qoladi) | registrator ofisi |
| **E-7** | ko'chirib kelgan talabalar (reyestrdagi `kochirish` buyrug'i yoki M8 ga qo'lda qo'shilgan) | M8 cheklisti: 6 hujjat bor va asli tekshirilgan | `E-7:{student_hemis_id}` | cheklist to'liq | registrator ofisi (yoki dekan) |

A-5 uchun to'lov chegarasi 578-qaror 1-ilovasidan: oddiy kontraktda yillik summaning **kamida 25 %**,
qo'shimcha va tabaqalashtirilgan kontraktda birinchi yilning **kamida 50 %** (katalogdagi F-7 bilan bir
xil). To'lov ulushi mavjud kod bilan bir xil hisoblanadi: `paid_credit_amount / edu_contract_sum`.

A-5 01.11.2026 gacha **prognoz** rejimida ishlaydi: kim xavf ostida ekani `warning_only` sifatida
ko'rsatiladi. 01.11 dan keyin haqiqiy istisnolarga o'tadi.

**Ma'lumot bo'shliqlari** (pilotda yopiladi):
- **Buyruqlar reyestri** (D-2, E-1, A-5, E-7). HEMIS API'da buyruqlar endpointi bo'lsa — API orqali
  sinxron. Bo'lmasa — Excel/CSV importi, ustunlarni moslashtirish ekrani bilan (TZ 4.2).
  `AdmissionIndicatorController` dagi Excel ustunlarini aniqlash usuli qayta ishlatiladi.
- **YN bahosining HEMIS'ga kiritilgan sanasi** (Q-1). Hozir faqat `hemis_updated_at` (oxirgi
  o'zgarish) saqlanadi. `hemis_exam_grades` ga `hemis_created_at` (API qaytarsa) yoki
  `first_seen_at` qo'shiladi. Lekin sinxron kuniga bir marta (03:00), shuning uchun `first_seen_at`
  ±1 kun xatoli bo'ladi.
- **Tavsiya etilganlar ro'yxati** (A-5). Mandat test markaziga qog'ozda keladi, shuning uchun:
  1) test markazi yoki registrator Agentlikdan ro'yxatning elektron nusxasini (Excel) so'raydi;
  2) elektron nusxa bo'lmasa, mark beradigan Excel shablonga ro'yxat bir marta kiritiladi.
  Fayl «Importlar» sahifasidan yuklanadi, qog'oz mandatning skani esa importga dalil sifatida biriktiriladi.
  Talabaga moslash tartibi: pasport (seriya + raqam) → HEMIS ID → F.I.Sh. Faqat F.I.Sh. bo'yicha
  topilganlar «tasdiqlanmagan moslik» deb belgilanadi va qo'lda tasdiqlanadi.
- **Buyruqlar endpointi.** `php artisan monitoring:hemis-probe` buyrug'i HEMIS'dagi ehtimoliy buyruq
  endpointlariga so'rov yuboradi. U faqat HTTP holatini, yozuvlar sonini va maydon **nomlarini**
  chiqaradi, qiymatlarni chiqarmaydi. RTM uni prod serverda bir marta ishga tushiradi; endpoint
  topilmaguncha buyruqlar Excel/CSV orqali yuklanadi.
- **M3 va M8** — mark ichida oddiy shakllar (soddalashtirilgan).

## 6. Rollar va kirish

| TZ dagi rol | mark'dagi yechim |
|---|---|
| Monitoring administratori | **yangi rol** `monitoring_admin` («Monitoring administratori») |
| Registrator ofisi menejeri | mavjud `registrator_ofisi` |
| Mas'ul xodim | alohida rol shart emas: istisno biriktirilgan har bir xodim «Menga biriktirilganlar» sahifasida faqat o'zinikini ko'radi |
| Rahbariyat | mavjud `oquv_prorektori` (faqat o'qish). Alohida `rahbariyat` roli hozircha qo'shilmaydi |
| RTM | mavjud `superadmin` / `admin`: run'lar va importlar jurnali |

- Ko'rish doirasi `MonitoringIstisno::scopeVisibleTo($actor)` va `MonitoringIstisnoPolicy` da
  markazlashtiriladi. Mas'ul faqat `owner_type/owner_id` o'ziniki bo'lgan istisnolarni ko'radi.
- Mas'ul «bajarildi» deb belgilaydi (`javob_berildi`). Yopishni faqat administrator qiladi (TZ 2-bo'lim).

## 7. Interfeys (pilot)

Hammasi `x-app-layout` va mavjud uslubda. Menyu `admin-sidebar-menu` ga `hasActiveRole` bilan qo'shiladi.

1. **Panel** — ochiq, muddati o'tgan va shu hafta yopilgan istisnolar soni; detektor guruhi va
   fakultet/kafedra kesimida; oxirgi run'lar va importlar holati.
2. **Istisnolar ro'yxati** — filtrlar (detektor, guruh, holat, mas'ul, bo'linma, muddat),
   ommaviy biriktirish, Excel eksport.
3. **Istisno kartochkasi** — nima topildi, huquqiy asos, tarix, javoblar, dalillar, holat tugmalari.
4. **Menga biriktirilganlar** — javob yozish, fayl yuklash, «bajarildi».
5. **Detektorlar** — ro'yxat, yoqish/o'chirish, parametrlarni tahrirlash (audit'ga yoziladi),
   oxirgi run, «Hozir ishga tushir».
6. **Importlar** — buyruqlar va tavsiya etilganlar (fayl yuklash, ustun moslash, xatolar).
7. **M3 xabarnoma jurnali**, **M8 yig'majild cheklisti** (soddalashtirilgan).

**Eslatmalar (pilotda):** istisno biriktirilganda mas'ulga ichki bildirishnoma (`teacher_notifications`
yoki `notifications`) yuboriladi, `telegram_chat_id` bo'lsa Telegram ham. Muddatdan 1 kun oldin va
muddat o'tganda yuboriladigan eslatmalar, kalendar va oylik svod — 2-bosqichda.

## 8. Testlar

- Pest, `tests/Feature/Monitoring/*`. Yangi modellar uchun factory'lar yoziladi, faqat Faker
  ma'lumoti bilan; haqiqiy shaxsiy ma'lumot ishlatilmaydi.
- Har bir detektor uchun kamida ikkita test: «buzilish bor» va «buzilish yo'q», kerak bo'lsa chegara holat.
- Dvigatel testlari: dublikat yaratilmaydi; holat bartaraf bo'lsa avtomatik yopiladi va tarixda
  qoladi; xatoli run hech narsani yopmaydi; parametr panelda o'zgarsa keyingi run'da kuchga kiradi;
  `warning_mode`.
- Kirish testlari: mas'ul faqat o'zinikini ko'radi; rahbariyat yoza olmaydi.
- Test bazasi: mavjud migratsiyalar SQLite'da ishlamaydi, shuning uchun **MySQL test bazasi** kerak
  (`.env.testing` — kalit nomlari `.env.example` ga). Buyruq: `php artisan test --filter=Monitoring`.
- README'ga «Monitoring: o'rnatish va ishga tushirish» bo'limi qo'shiladi: migratsiya, seeder,
  `.env` kalitlari, scheduler, testlar.

## 9. Qabul mezonlari (TZ 9-bo'lim) — qanday tekshiriladi

| Mezon | Tekshiruv |
|---|---|
| 5 detektor test ma'lumotida to'g'ri ishlaydi (har biriga ≥ 2 test) | `tests/Feature/Monitoring/Detectors/*` |
| Qayta ishga tushirishda dublikat yo'q | `DetectorRunnerTest` |
| Bartaraf bo'lsa avtomatik yopiladi, tarixda qoladi | `DetectorRunnerTest` |
| Mas'ul faqat o'zinikini ko'radi | `IstisnoAccessTest` |
| Parametr panelda o'zgarsa kuchga kiradi | `DetectorSettingsTest` |
| `.env.example`, migratsiyalar, README | PR tarkibida |
| Haqiqiy shaxsiy ma'lumot yo'q | faqat factory/Faker; PR'dan oldin diff tekshiriladi |

## 10. Savollar va javoblar

### 10.1. Buyurtmachi javoblari (16.10.2026)

| Savol | Javob | Rejadagi natija |
|---|---|---|
| A-5: tavsiya etilganlar | Mandat test markaziga qog'ozda keladi | Excel shablon orqali import, skan dalil sifatida (5-bo'lim) |
| A-5: to'lov ulushi | 578-qarordagi qoida | 25 % (oddiy), 50 % (qo'shimcha/tabaqalashtirilgan) |
| Q-1: «3 kun» | 3 ish kuni | `day_type: workdays` |
| E-1: ijara kotibi | Muhammadzafar | qabul qiluvchi panelda tanlanadi (kodga yozilmaydi) |
| Rollar | `monitoring_admin` ma'qul | qo'shiladi; rahbariyat — `oquv_prorektori` |
| Xavfsizlik | platformaga ta'sir qilmasa tuzatilsin | PR 0 tayyor (2-bo'lim) |
| Branch | `feature/monitoring` ga ruxsat | ish shu branch'da |

Javobsiz qolgan savollar bo'yicha standart qarorlar (panelda o'zgartiriladi):
- **Q-1:** baho kechikib kiritilsa, istisno avtomatik yopiladi (`late_completion: auto_close`).
  Kechikish istisno tarixida qoladi. Qayta topshirishlar hozircha tekshirilmaydi
  (`include_resits: false`), chunki HEMIS'dagi urinish kodlari tasdiqlanmagan.
- **D-2:** barcha fanlar hisobga olinadi. LMS'da tasdiqlangan «kasallik» va «tibbiy ko'rik»
  arizalari qamragan kunlar ayiriladi.
- **E-1:** qoida barcha talabalarga qo'llanadi. Tekshiruv 01.10.2026 dan keyingi buyruqlardan boshlanadi.
- **E-7:** ro'yxat buyruqlar reyestridan («ko'chirib kelish» turi) va qo'lda qo'shishdan olinadi.

### 10.2. Hali ochiq savollar

**Infratuzilma va ma'lumot manbalari**
1. Staging (sinov) serveri bormi? Yo'q bo'lsa, PR'lar prod'ga chiqishdan oldin qayerda tekshiriladi?
2. HEMIS'ning to'g'ri manzili qaysi: kodda `student.ttatf.uz`, TZ da `hemis.ttatf.uz`?
3. HEMIS API'da buyruqlar (decree) endpointi bormi va prod tokenning unga huquqi bormi? RTM prod
   token bilan tekshirishi kerak. Endpoint bo'lmasa, buyruqlarni HEMIS'dan kim, qaysi formatda
   eksport qiladi? Soxta ma'lumotli namuna fayl kerak.
4. Ish kuni hisobi: hozir faqat yakshanba dam olish kuni. Shanba ish kunimi? Bayramlar ro'yxati
   kerakmi, yoki u 2-bosqichdagi kalendar bilan birga qo'shiladimi?

**Pilot detektorlar**
5. **A-5:** mandatning elektron (Excel) nusxasini Agentlikdan olish mumkinmi?
6. **D-2:** 74 soatga semestrdagi barcha fanlar va mashg'ulot turlari kiradimi yoki ayrim turlar
   (masalan 99–102 kodlilar) chiqariladimi? LMS'da tasdiqlangan, lekin HEMIS'da hali sababli
   bo'lmagan tibbiy arizalar ayiriladimi? Qaysi sabablar «tibbiy» hisoblanadi?
7. **Q-1:** qayta topshirishlar ham tekshiriladimi? Kechikib kiritilgan baholar ochiq qolsinmi?
8. **E-1:** qoida faqat ijarada/TTJ da yashovchilarga qo'llanadimi?
9. **E-7:** ko'chirib kelgan talabalar ro'yxati qayerdan olinadi (buyruqlar reyestri yoki qo'lda)?
   Qaysi sanadan boshlab tekshiriladi: faqat 2026/2027 yoki avvalgilar ham?
10. `AbsenceExcuse` da 8 ta sabab toifasi bor, D-4 da esa «9 rasmiy toifa» deyilgan.
    Yetishmayotgani qaysi? Bu 2-bosqich uchun.

**Rollar va tartib**
11. `monitoring_admin` roli kimlarga beriladi (Registrator ofisi boshlig'i A.K. Sharipov)?
12. Server tomonida (RTM): HEMIS OAuth va Moodle sirlarini almashtirish, docker-compose'dagi DB
    parolini `.env` ga ko'chirish, `public/rest_*.php` dan kim foydalanishini aniqlash.
