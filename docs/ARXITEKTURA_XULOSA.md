# mark.tashmedunitf.uz — arxitektura xulosasi

Maqsad: «Monitoring» modulini (`docs/MONITORING_TZ.md`) yozishdan oldin repozitoriyning
hozirgi holatini qayd etish. Hujjat 15.10.2026 holatidagi `main` (commit `091bbee`) bo'yicha
yozilgan; faqat kod o'qilgan, hech narsa o'zgartirilmagan. Fayl yo'llari repozitoriy ildiziga nisbatan.

---

## 1. Stek

| Qatlam | Nima ishlatiladi |
|---|---|
| Til / freymvork | PHP 8.2 (Docker `php:8.2-fpm`), **Laravel 11.44** |
| Ma'lumotlar bazasi | **MySQL** (prod — host'dagi MySQL, dev — `mysql:8.0.40` konteyneri). `.env.example` da `sqlite` turibdi, lekin migratsiyalarda MySQL'ga xos SQL bor (`SHOW INDEX`, `information_schema`) |
| Rollar | `spatie/laravel-permission` 6.17 — faqat **rollar**, permission'lar ishlatilmaydi |
| Frontend | Blade + Tailwind 3 / Flowbite (Vite) **va** CDN'dan Bootstrap 4.5, jQuery, select2, DataTables 1.10, Font Awesome 5; Alpine.js (71 view); Chart.js 4.4 (4 view). Livewire o'rnatilgan, lekin **komponent yo'q** |
| Excel / PDF / Word | `maatwebsite/excel` 3.1, PhpSpreadsheet (to'g'ridan-to'g'ri ham), `barryvdh/laravel-dompdf`, `phpoffice/phpword` |
| Navbat | `QUEUE_CONNECTION=database`, supervisord'da 1 ta `queue:work` |
| Rejalashtiruvchi | Docker ichida cron → `schedule:run` har daqiqada; TZ `Asia/Tashkent` |
| Mobil ilova | `mobile_app/` — Flutter, `/api/v1` (Sanctum Bearer token) |
| Tashqi tizimlar | HEMIS (REST + OAuth), Moodle WS, Telegram bot, Firebase FCM, Face ID (lokal FastAPI servis), Gemini va Anthropic API |

**Qayerda ishlaydi:** `docker-compose.yml` / `docker-compose.production.yml` — `lmsttatf_app`
(php-fpm + cron + queue, supervisord) va `lmsttatf_nginx` (`server_name mark.tashmedunitf.uz`).
Ma'lumotlar bazasi `lmsttatf` host'da, zaxira nusxa `backup_db.sh` (mysqldump, 10 kun).
`docker-compose.dev.yml` — lokal ishlab chiqish muhiti. **Staging (sinov) serveri yo'q**
(buyurtmachi, 16.10.2026).

## 2. Papka tuzilishi va kod uslubi

```
app/
  Console/Commands/   104 ta artisan buyruq (importlar, Telegram hisobotlar, diagnostika)
  Enums/ProjectRole.php   barcha rollar (19 ta)
  Exports/ (55)  Imports/ (9)   maatwebsite/excel klasslari
  Helpers/helpers.php     global funksiyalar: is_active_dekan(), get_dekan_faculty_ids() ...
  Http/Controllers/
    Admin/ (66)  Teacher/ (18)  Student/ (24)  Api/V1/ (13)  Auth/
  Http/Middleware/ (14)
  Jobs/ (24)
  Models/ (146)           flat, nomlar aralash: AbsenceExcuse, OraliqNazorat, Qaytnoma ...
  Services/ (71)          flat + Services/Retake/ pastki papka
  Support/WorkdayCalculator.php
  Traits/LogsActivity.php
database/migrations/ (365)   database/seeders/ (3)   database/factories/ (faqat UserFactory)
resources/views/admin|teacher|student/...   layouts/app.blade.php (asosiy admin layout)
routes/web.php (2178 qator)  routes/api.php  routes/console.php  bootstrap/app.php (scheduler ham)
```

Uslub bo'yicha kuzatuvlar:
- Biznes-mantiqning katta qismi **controller'larda** (`Admin/ReportController.php` — 14 390 qator,
  `JournalController` — 11 383). Yangi kodni `app/Services/...` ga chiqarish — mavjud yaxshi namuna
  (`Services/Retake/`, `VedomostSubmissionService`, `TeacherMissedLessons`).
- Klass nomlari asosan inglizcha, domen so'zlari o'zbekcha (`kafedra`, `oqim`, `sabab_key`);
  izohlar, log va test nomlari — o'zbekcha. Commit xabarlari — inglizcha, buyruq maylida.
- Hisobot sahifasining odatiy shakli: `x-app-layout` → select2 filtrlar → jQuery AJAX bilan JSON
  endpoint → jadval; Excel — o'sha endpoint `export=excel` bilan yoki og'ir bo'lsa Job +
  holatni so'rab turish (`LessonOpeningTeacherReportJob` namunasi).
- Menyu: `resources/views/components/admin-sidebar-menu.blade.php` (2111 qator),
  bandlar `@if($hasActiveRole([...]))` bilan.
- Lokalizatsiya: `lang/uz|ru|en`, standart `uz`; ko'p matnlar Blade'da to'g'ridan-to'g'ri o'zbekcha.

## 3. Autentifikatsiya va rollar

**Uchta guard** (`config/auth.php`), har biri alohida jadval:

| Guard | Model | Kim | Kirish |
|---|---|---|---|
| `web` | `User` | adminlar, ayrim xodimlar (masalan prorektor) | `/admin/login`, email+parol |
| `teacher` | `Teacher` | **barcha xodimlar**: o'qituvchi, dekan, kafedra mudiri, tyutor, registrator ... | `/teacher/login`, login+parol |
| `student` | `Student` | talabalar | lokal parol (+Telegram kod), HEMIS OAuth, Face ID |

Uchala model Spatie'da `guard_name = 'web'` bilan ishlaydi — rollar jadvali umumiy,
`model_has_roles.model_type` bilan ajratiladi. Admin panel (`/admin/*`) ga ham `web`, ham
`teacher` guard bilan kiriladi (`AdminMultiGuardAuth`).

**Rollar** (`app/Enums/ProjectRole.php`): `superadmin`, `admin`, `kichik_admin`, `inspeksiya`,
`oquv_prorektori`, `registrator_ofisi`, `oquv_bolimi`, `oquv_bolimi_boshligi`, `buxgalteriya`,
`manaviyat`, `tyutor`, `nazoratchi` (faqat o'qish, `NazoratchiReadOnly`), `dekan`,
`kafedra_mudiri`, `fan_masuli`, `oqituvchi`, `test_markazi`, `javobgar_firma`, `talaba`.

- Bir xodimda bir nechta rol bo'lishi mumkin; **faol rol** sessiyada (`session('active_role')`),
  `admin/switch-role` / `teacher/switch-role` orqali almashtiriladi.
- Route darajasidagi `RoleMiddleware` foydalanuvchining **istalgan** rolini tekshiradi, faol rolni emas;
  faol rol helper'lar, controller'lar va menyuda hisobga olinadi.
- **Ko'rish doirasi** (scope) markazlashmagan, `app/Helpers/helpers.php` dagi funksiyalar bilan:
  - dekan → fakultetlar: `dean_faculties` pivot (`get_dekan_faculty_ids()`);
  - kafedra mudiri → `teachers.head_department_hemis_id`;
  - tyutor → guruhlar: `group_teacher` pivot (`get_tyutor_group_db_ids()`);
  - nazoratchi → `nazoratchi_groups`; fan mas'uli → `teacher_responsible_subjects`;
  - o'qituvchi → `schedules.employee_id = teachers.hemis_id`.
- Rol berish: `Admin/TeacherController::updateRoles` (scope pivotlari bilan birga), `Admin/UserController`.
- Impersonatsiya bor (faqat superadmin).

**Audit:** `activity_logs` jadvali (guard, user, faol rol, action, module, subject, old/new JSON,
IP, user agent). Avtomatik — `App\Traits\LogsActivity` (created/updated/deleted, parol va tokenlar
chiqarib tashlanadi); qo'lda — `App\Services\ActivityLogService` (login/logout, rol o'zgarishi va
h.k.). Ko'rish sahifasi: `/admin/activity-log`. **Ko'rish (view) hodisalari hozircha yozilmaydi** —
TZ 0.5 talabi uchun buni qo'shish kerak.

## 4. Mavjud ma'lumotlar modeli (monitoring uchun muhimlari)

Asosiy tamoyil: o'quv ma'lumotlari **HEMIS nusxasi** — jadvallarda HEMIS id'lari saqlanadi va
ular bo'yicha bog'lanadi (`group_hemis_id`, `employee_id = teachers.hemis_id`, `subject_id`,
`semester_code`). Foreign key'lar kam (365 migratsiyadan 70 tasida), asosan LMS'ning o'z jadvallarida.

| Ma'no | Model → jadval | Muhim ustunlar |
|---|---|---|
| Talaba | `Student` → `students` | `hemis_id`, `student_id_number`, `group_id` (= group_hemis_id), `department_id` (fakultet), `level_code` (11–16 = 1–6 kurs), `student_status_code` (`11` = o'qimoqda; HEMIS'da topilmaganlarga lokal `60`), `passport_number/serial`, `telegram_chat_id`. **JSHSHIR (PINFL) ustuni yo'q** |
| Guruh | `Group` → `groups` | `group_hemis_id`, `department_hemis_id`, `specialty_hemis_id`, `curriculum_hemis_id` |
| Fakultet / kafedra | `Department` → `departments` | `structure_type_code`: `11` = fakultet, `12` = kafedra; kafedra `parent_id` = fakultet HEMIS id |
| Fan (o'quv rejada) | `CurriculumSubject` → `curriculum_subjects` | `subject_id`, `semester_code`, `closing_form` (oski/test/sinov...), `department_id` (kafedra) |
| Dars jadvali | `Schedule` → `schedules` | `schedule_hemis_id`, `lesson_date`, `employee_id`, `group_id`, `subject_id`, `training_type_code` (soft delete) |
| Davomat | `Attendance` → `attendances` | `student_hemis_id`, `subject_id`, `semester_code`, `education_year_code`, `lesson_date`, **`absent_on` = sababli soat, `absent_off` = sababsiz soat** |
| Davomat olinganligi | `AttendanceControl` → `attendance_controls` | o'qituvchi HEMIS'da davomat olgan dars (`load > 0`) |
| Kunlik baholar | `StudentGrade` → `student_grades` | `lesson_date`, `grade`, `created_at_api` (HEMIS'da kiritilgan vaqt), `status`, `deadline`, `retake_*` |
| YN jadvali | `ExamSchedule` → `exam_schedules` | guruh × fan: `oski_date`, `test_date`, qayta topshirish sanalari (LMS'da kiritiladi) |
| YN baholari (HEMIS) | `HemisExamGrade` → `hemis_exam_grades` | `exam_date`, `grade`, `regrade`, `employee_hemis_id`, `hemis_updated_at` (**faqat oxirgi o'zgarish vaqti; birinchi kiritilgan sana saqlanmaydi**) |
| Qaydnoma (vedomost) topshirish | `VedomostSubmission` → `vedomost_submissions` | `base_date`, `deadline` (= base_date + 3 ish kuni), `status`, `uploaded_at`, `warning_stage` |
| Sababli ariza | `AbsenceExcuse` → `absence_excuses` | `reason` (8 toifa — `AbsenceExcuse::REASONS`; D-4 da «9 rasmiy toifa» deyilgan), `start_date`, `end_date`, `status`, `reviewed_at` |
| Dars ochish | `LessonOpening` → `lesson_openings` | o'tgan kunga baho qo'yish uchun ko'p bosqichli ruxsat |
| Kontrakt to'lovi | `ContractList` → `contract_list` | HEMIS `contract-list`: `student_hemis_id`, `edu_year`, `edu_contract_sum`, `paid_credit_amount`, `contract_debet_amount` ... |
| Qabul ko'rsatkichlari | `AdmissionIndicator` → `admission_indicators` | Excel importi: `jshshir_kod`, `farmoyish`, `buyruq`, `tolov_shakli`, kontrakt summasi |
| Qabul anketasi | `StudentAdmissionData` | abituriyent ma'lumotlari, `jshshir`, `tavsiya_turi`, `tolov_shakli` |
| Talaba buyrug'i (lokal) | `StudentAcademicOrder` | `farmoyish_*`, `qabul_*` raqam/sana/fayl — bitta talaba uchun qo'lda |
| Talaba fayllari | `StudentFile` | talabaga biriktirilgan ixtiyoriy fayllar |
| Tyutor | `group_teacher` pivot, `TutorHistory` | tyutor ↔ guruh |
| Sozlamalar | `Setting` → `settings` | `key`/`value`, `Setting::get()/set()`, o'zgarish audit'ga yoziladi; ~60 kalit |
| Bildirishnomalar | `notifications` (User), `teacher_notifications`, `student_notifications` | ichki «qo'ng'iroqcha»; Laravel `Notifiable` ishlatilmaydi |

**Yo'q narsalar (monitoring uchun muhim):**
- **HEMIS buyruqlari (decree) umuman sinxronlanmaydi.** Chetlashtirish, akademik ta'til,
  kursda qoldirish, ko'chirish buyruqlari bazada yo'q; faqat talabaning joriy `student_status_code`
  bor (haftalik import, o'zgarish sanasi saqlanmaydi).
- Bayramlar jadvali yo'q: `WorkdayCalculator` faqat yakshanbani dam olish kuni deb hisoblaydi.
- Ko'chirib kelgan talabalar yig'majildi (M8), xabarnoma jurnali (M3) — yo'q.

## 5. HEMIS integratsiyasi

- **Manzil:** `https://student.ttatf.uz/rest/v1/` (`config/services.php` → `HEMIS_API_BASE_URL`,
  token — `HEMIS_API_TOKEN`, Bearer). Ko'p buyruqlarda URL **kodga qotirilgan**. TZ da
  `hemis.ttatf.uz` deyilgan — qaysi biri to'g'riligi aniqlanishi kerak.
- **Yo'nalish — faqat o'qish (pull).** HEMIS'ga baho yoki davomat yozilmaydi; yagona «yozish» —
  `import:contracts` ichida HEMIS veb-interfeysiga login qilib bank sinxronini ishga tushirish.
- Asosiy klient: `app/Services/HemisService.php`; boshqa importlar alohida buyruqlarda
  (`app/Console/Commands/Import*.php`, `ScheduleImportService`). Umumiy wrapper va rate limiter yo'q.

| Ma'lumot | HEMIS endpoint | Jadval | Qachon |
|---|---|---|---|
| Talabalar | `student-list` | `students` | haftalik |
| Guruh, fakultet/kafedra, mutaxassislik, semestr, o'quv reja, reja fanlari | `group-list`, `department-list`, `specialty-list`, `semester-list`, `curriculum-list`, `curriculum-subject-list` | tegishli jadvallar | haftalik |
| Xodimlar | `employee-list` | `teachers` | 2 kunda bir |
| Dars jadvali | `schedule-list` | `schedules` | har tun 00:30 |
| Kunlik baholar, davomat | `student-grade-list`, `attendance-list` | `student_grades`, `attendances` | 08:30–23:59 har 30 daqiqa; tunda yakuniy; davomat 03:00 da 30 kunlik backfill |
| Davomat olinganligi | `attendance-control-list` | `attendance_controls` | tunda + kun davomida |
| YN baholari | `student-performance-list` | `hemis_exam_grades` | har kuni 03:00 |
| Akademik yozuvlar, talaba fanlari | `academic-record-list`, `student-subject-list` | `academic_records`, `student_subjects` | kunlik / oylik |
| Kontraktlar | `contract-list` | `contract_list` | har kuni 23:00 |

## 6. Rejalashtiruvchi, navbat, Telegram

- Jadval ikki joyda: `bootstrap/app.php` (`withSchedule`) va `routes/console.php` — jami ~35 vazifa.
  `runInBackground`/`onOneServer`/xato hook'i yo'q; bir daqiqadagi vazifalar ketma-ket bajariladi.
  `import:contracts` va `import:academic-records` ikki marta rejalashtirilgan.
- Navbat: `database` driver, bitta worker (`--tries=1 --timeout=1800`).
- **Telegram bot bor** (`app/Services/TelegramService.php`): `sendToUser`, `notifyChat`, `sendPhoto`,
  `sendDocument`. Xodim/talaba `/start <kod>` orqali ulanadi (`TelegramWebhookController`),
  `telegram_chat_id` saqlanadi. Guruh chatlari `config/services.php` → `services.telegram.*`
  (registrator guruhi, davomat guruhi va h.k.).
- Odatiy xabar yuborish namunasi: `teacher_notifications` ga yozish + chat_id bo'lsa Telegram
  (`VedomostSubmissionNotifier::sendToTeacher`).
- SMS va email integratsiyasi **yo'q**.

## 7. Monitoringga yaqin mavjud funksiyalar

Yangi modul ularni takrorlamasligi, aksincha qayta ishlatishi kerak:

| Mavjud | Nima qiladi | Qaysi detektorga yaqin |
|---|---|---|
| `teachers:send-reminders`, `teachers:send-group-summary`, `teachers:send-final-daily-report`, `registrar:send-unrated-*` | davomat/baho qo'yilmagan darslar → Telegram | D-5 |
| `TeacherMissedLessons` servisi | «belgilanmagan dars» mantig'i (kanonik) | D-5 |
| `VedomostSubmission` + `vedomost:deadline-warnings` | qaydnoma muddati (base_date + 3 ish kuni), ogohlantirish | Q-1, Q-1b |
| `AbsenceExcuse` + `absence-excuses:send-pending-reminder` | sababli arizalar va ularni ko'rib chiqish | D-4 |
| `YnAdmissionService` | YN ga ruxsat (sababsiz ≥ 25 %, JN/MT chegaralari, kontrakt) | D-3, Q-3 |
| `retake:*` | qayta o'qish oynalari va guruhlari | Q-6 |
| `kunlik-monitoring:send-report` | Moodle ↔ LMS sinxron farqi (test markazi) | — (texnik) |
| `nazoratchi` roli | faqat o'qish huquqi — «Rahbariyat» roli uchun namuna | 2-bo'lim |

## 8. Testlar

- **Pest 3**; `tests/Feature` (13 fayl) va `tests/Unit` (6 fayl). Breeze'ning standart auth testlari
  o'zgarishsiz qolgan; real testlar: baho importi, dars ochish hisoboti, dars jadvali algoritmi,
  o'quv reja solishtirish.
- Factory faqat `UserFactory` — testlar ma'lumotni `DB::table()->insert()` bilan yaratadi.
- `phpunit.xml` da SQLite qatorlari izohga olingan; ayrim migratsiyalar SQLite'da ishlamaydi
  → Feature testlar amalda **MySQL test bazasi** talab qiladi.
- **CI yo'q** (`.github/` papkasi yo'q). Ushbu konteynerda `vendor/` yo'q — testlar `composer install`
  dan keyin ishga tushadi: `php artisan test` yoki `./vendor/bin/pest`.

## 9. Migratsiyalar

- Laravel standart migratsiyalari, 365 fayl, nomlash `YYYY_MM_DD_HHMMSS_create_xxx_table.php`
  (vaqt odatda yaxlit: `_100000`, `_120000`). Oxirgisi — `2026_10_15_140000_...`.
- Ko'p migratsiyalar `Schema::hasTable/hasColumn` bilan himoyalangan; ayrim sozlama
  boshlang'ich qiymatlari migratsiya ichida yoziladi.
- Seeder'lar: `DatabaseSeeder`, `RoleSeeder` (barcha `ProjectRole` rollarini yaratadi),
  `AbsenceExcuseSeeder`.
- `symfony/yaml` faqat dev-bog'liqlik (Sail orqali); prod'da (`composer install --no-dev`)
  YAML o'qib bo'lmaydi.

## 10. Xavfsizlik va texnik qarz — **tezda ko'rib chiqish kerak**

Monitoring moduli shu tizimning rollari va audit'iga tayanadi, shuning uchun quyidagilarni
RTM e'tiboriga alohida yetkazaman. Bular Monitoring TZ doirasidan tashqarida, men ularni
o'zgartirmadim.

1. **Rol berishda huquq tekshirilmaydi.** `PUT /admin/teachers/{teacher}/roles`
   (`Admin/TeacherController::updateRoles`) va `Route::resource('users', ...)` faqat «istalgan xodim
   roli bor» umumiy guruhida turadi; controller ichida chaqiruvchining huquqi tekshirilmaydi
   (`ProjectRole::canAssignRoles()` hech qayerda chaqirilmaydi). Natijada istalgan xodim
   (masalan oddiy `oqituvchi`) o'ziga yoki boshqaga yuqori rol bera olishi mumkin.
   Monitoring rollari ham shu mexanizm orqali beriladi — **pilotdan oldin tuzatish tavsiya etiladi.**
2. **Web-ildizda (`public/`) phpMyAdmin'ning ikki nusxasi** (`public/MyAdmin` — 5.2.3, `public/php` —
   5.1.1) va `public/phpmyadmin` symlink commit qilingan; nginx `public/` ichidagi har qanday `.php` ni
   bajaradi. Shuningdek `public/_dbg_cols.php`, `check_db.php`, `rest_*.php` — `.env` ni o'qib bazaga
   to'g'ridan-to'g'ri ulanadigan skriptlar (biri autentifikatsiyasiz).
3. **Repozitoriyda sirlar:** `HEMIS_OAUTH_CLIENT_SECRET` qiymati `.env.example` da, DB paroli
   `docker-compose*.yml` da, Moodle `SYNC_SECRET` — `scripts/moodle_check_trigger.php` da.
   TZ 0.4 ga zid; qiymatlarni almashtirish (rotate) va fayllardan olib tashlash kerak.
4. **Haqiqiy shaxsiy ma'lumot:** `tests/re_apply.csv` (80 qator — F.I.Sh., ID raqam, ...).
   TZ 0.5 ga zid; o'chirish kerak (git tarixida qoladi).
5. HEMIS so'rovlari `Http::withoutVerifying()` bilan — TLS sertifikati tekshirilmaydi.
6. `YnAdmissionService` da kontrakt yili `'2025'` / `'2025-2026'` qotirilgan (TODO bor) — 2026/2027
   o'quv yilida YN ga ruxsat hisobidagi kontrakt tekshiruvi eski yilga qaraydi.

## 11. TZ 11-bo'limidagi ochiq savollarga javoblar

**1. mark qaysi stekda yozilgan va qayerda ishlaydi? Staging bormi?**
Laravel 11 / PHP 8.2 / MySQL, Docker (php-fpm + nginx + cron + queue, supervisord),
`mark.tashmedunitf.uz`. Lokal dev muhiti bor (`docker-compose.dev.yml`). **Staging serveri yo'q**
(buyurtmachi, 16.10.2026) — shuning uchun Monitoring prod'da «ogohlantirish» rejimida boshlanadi.

**2. Talaba/guruh/fan ma'lumotlari mark'da saqlanadimi yoki HEMIS'dan olinadimi?**
Ikkalasi: HEMIS'dan muntazam tortiladi va mark bazasida **nusxa** sifatida saqlanadi
(talabalar — haftalik, dars jadvali — har tun, baho va davomat — har 30 daqiqa). HEMIS — asosiy
manba; mark'ning o'zida qaydnoma topshirish, sababli arizalar, YN jadvali, qayta o'qish,
dars ochish kabi jarayonlar yaratiladi. Detektorlar shu mahalliy nusxa ustida ishlashi mumkin —
har safar HEMIS'ga so'rov yuborish shart emas.

**3. HEMIS API mavjudmi, qaysi endpointlar, token kimda?**
Ha, `https://student.ttatf.uz/rest/v1/` — yuqoridagi 5-bo'lim jadvalidagi endpointlar ishlatiladi.
Token `.env` dagi `HEMIS_API_TOKEN` da (prod serverda; repozitoriyda yo'q — to'g'ri). **Buyruqlar
(decree) uchun endpoint kodda hech qachon chaqirilmagan** — HEMIS API'da bunday endpoint borligini
va tokenning unga huquqi borligini RTM prod token bilan tekshirishi kerak (mavjud
`php artisan hemis:diagnose` buyrug'i namunasida kichik tekshiruv buyrug'i yozish mumkin).

**4. Mavjud rol va bildirishnoma tizimi qanday?**
Rollar — 3-bo'lim (Spatie, 19 rol, faol rol sessiyada, scope helper'lar orqali).
Bildirishnomalar — ichki jadvallar (`notifications`, `teacher_notifications`,
`student_notifications`) + Telegram; email/SMS yo'q.

**5. Telegram bot integratsiyasi bormi?**
Ha — `TelegramService`, webhook, foydalanuvchini `/start <kod>` bilan ulash, shaxsiy va guruh
chatlariga matn, rasm (jadval rasmi) va hujjat yuborish. Monitoring eslatmalari shu orqali ketadi.

---

## 12. Monitoring moduli uchun xulosa

- **Moslashish:** yangi freymvork kerak emas. Laravel model + migratsiya, `app/Services/Monitoring/`
  (detektorlar), artisan buyruq + scheduler, Blade + jQuery/Alpine sahifalar, maatwebsite Excel.
- **Mas'ul (owner) polimorf bo'lishi kerak** — mas'ul xodim `Teacher` (dekan, kafedra mudiri, tyutor)
  yoki `User` bo'lishi mumkin (`notifications` jadvalidagi `recipient_type/recipient_id` namunasi).
- **Sozlamalar:** detektor parametrlari umumiy `settings` jadvalida emas, alohida
  `monitoring_detectors.params` (JSON) da — TZ 3-bo'limiga mos; o'zgarish `LogsActivity` orqali audit'ga.
- **Eng katta ma'lumot bo'shlig'i — buyruqlar reyestri.** D-2, E-1, A-5 (va keyinchalik Q-6, Q-7,
  E-*, F-2, F-8) buyruqlarga tayanadi. Pilotda «Buyruqlar reyestri» (HEMIS API bo'lsa — API, bo'lmasa —
  Excel/CSV importi ustun moslash ekrani bilan) yadroning bir qismi bo'lishi kerak.
- Batafsil reja: `docs/MONITORING_1_BOSQICH_REJA.md`.
