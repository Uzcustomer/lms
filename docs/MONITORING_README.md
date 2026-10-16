# Monitoring moduli — o'rnatish va ishga tushirish

Registrator ofisi nazorat detektorlari (TZ: `docs/MONITORING_TZ.md`, katalog: `docs/detectors.yaml`).
1-bosqich (pilot): yadro + 5 detektor — **D-2, Q-1, A-5, E-1, E-7**; qo'shimcha **D-1** (xavf zonasi, 30–74 soat).

## 1. Serverga chiqarish

```bash
composer install --no-dev          # symfony/yaml endi prod bog'liqligi
php artisan migrate                # 7 ta migratsiya: jadvallar, manba reyestrlari,
                                   # hemis_exam_grades ga kiritilgan sana, katalog seed'i,
                                   # buyurtmachi qarorlari (16.10.2026), registrator taqsimoti,
                                   # Q-1 vedomost tekshiruvi va D-1
php artisan config:cache
```

Chiqarishdan oldin zaxira nusxa oling (`./backup_db.sh`). Staging yo'q, orqaga qaytarish:
`php artisan migrate:rollback --step=7` (birinchi chiqarishda; avvalroq bajarilgan migratsiyalar bo'lsa — mos ravishda kamroq).
Migratsiya katalogni o'qiy olmasa (masalan `symfony/yaml` o'rnatilmagan), xato logga yoziladi:
`composer install --no-dev` dan keyin `php artisan monitoring:sync-catalog` ni bajaring.

Migratsiya:
- `monitoring_*` jadvallarini yaratadi;
- `monitoring_admin` rolini qo'shadi;
- `docs/detectors.yaml` dagi 54 detektorni yuklaydi;
- katalog avvalroq yuklangan bo'lsa, buyurtmachi qarorlarini qo'llaydi: Q-1 da qayta topshirishlar yoqiladi,
  hali ishlamagan pilot detektorlar sinov (ogohlantirish) rejimiga o'tadi;
- `hemis_exam_grades` ga `hemis_created_at` va `first_seen_at` ustunlarini qo'shadi. Mavjud qatorlarda
  `first_seen_at` qiymati `created_at` dan olinadi.

`.env` ga yangi kalit kerak emas. Telegram (`TELEGRAM_BOT_TOKEN`) va HEMIS (`HEMIS_API_*`) avvalgidek ishlatiladi.
`.env.example` ga `HEMIS_API_BASE_URL` va `HEMIS_API_TOKEN` nomlari qo'shildi; ular avval ro'yxatda yo'q edi.

## 2. Rejalashtiruvchi va navbat

- `routes/console.php`: `monitoring:run --all` **har kuni 05:30** da ishlaydi, tungi HEMIS importlaridan keyin.
- Paneldagi «Hozir ishga tushirish» tugmasi `RunMonitoringDetectorJob` ni `database` navbatiga qo'yadi.
  Uni mavjud `queue:work` (supervisord) bajaradi.

## 3. Birinchi sozlash (Monitoring administratori)

1. RTM xodimga **Monitoring administratori** rolini beradi: Xodimlar → xodim → Rollar.
2. **Detektorlar** sahifasida pilot detektorlarni tekshirish:
   - **E-1:** `recipient_ijara_kotibi` maydonida ijara kotibini tanlash (Muhammadzafar). `recipient_buxgalteriya`
     bo'sh qolsa, xabar buxgalteriya rolidagi barcha xodimlarga boradi.
     Qoida faqat ijarada yashovchilarga qo'llanadi: `accommodation_keywords` (standart `ijara`) HEMIS turar joyi
     nomida qidiriladi. Nomlarni Talabalar → Statistika → «Yashash joyi» bo'limida ko'rish mumkin.
   - **A-5, E-1, E-7:** istisno talabaning registratoriga boradi — **Registrator bo'linmalari** sahifasidagi
     fakultet / yo'nalish / kurs taqsimoti bo'yicha (`registrar_office`: Back ofis yoki Front ofis).
     Taqsimotda topilmagan talabalar uchun «Standart mas'ul» maydonida registrator ofisi xodimini tanlang.
   - **D-1, D-2** dekanga, **Q-1** kafedra mudiriga avtomatik biriktiriladi.
   - **Q-1** standart holatda (`check_against: vedomost`) vedomost YN sanasidan 3 ish kunida mark'ka yuklanganini
     tekshiradi (Vedomost topshirish jarayoni muddati). HEMIS'ga kiritish tekshiruvi — `check_against: hemis`
     (yakuniy natijalar HEMIS'ga kiritiladigan davrda yoqiladi).
3. **Importlar** sahifasida:
   - buyruqlar reyestrini yuklash (HEMIS'dan eksport yoki shablon);
   - kontrakt bo'yicha tavsiya etilganlar ro'yxatini yuklash (mandat shabloni, skani dalil sifatida).
4. **Sinov rejimini o'chirish.** Staging yo'q, shuning uchun pilot detektorlar «Faqat ogohlantirish» rejimida
   boshlanadi: istisnolar panelda ko'rinadi, lekin hech kimga yuborilmaydi. Birinchi natijalarni ko'rib chiqib,
   har bir detektor sozlamasida bu belgini olib tashlang (A-5 uchun 01.11.2026 gacha).
5. HEMIS'da buyruqlar endpointi bor-yo'qligini RTM tekshiradi:
   ```bash
   php artisan monitoring:hemis-probe            # faqat HTTP holati, soni va maydon nomlari
   php artisan monitoring:hemis-probe --no-verify  # HEMIS sertifikati muammo bersa
   ```

## 4. Buyruqlar

| Buyruq | Vazifasi |
|---|---|
| `php artisan monitoring:run --all` | yoqilgan barcha detektorlar |
| `php artisan monitoring:run D-2 Q-1` | tanlangan detektorlar |
| `php artisan monitoring:sync-catalog` | `docs/detectors.yaml` o'zgarganda katalogni yangilash (paneldagi qiymatlar saqlanadi) |
| `php artisan monitoring:sync-catalog --reset-params` | parametrlarni yaml'dagi holatga qaytarish |
| `php artisan monitoring:hemis-probe` | HEMIS buyruqlar endpointini tekshirish |

## 5. Testlar

Mavjud migratsiyalar MySQL'ga xos SQL ishlatadi, shuning uchun testlar alohida bo'sh **MySQL** bazasida ishlaydi:

```bash
cp .env.testing.example .env.testing   # DB_* ni to'ldiring, APP_KEY: php artisan key:generate --env=testing
mysql -e "CREATE DATABASE lms_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
php artisan test tests/Feature/Monitoring     # 90 test
```

Test ma'lumotlari faqat soxta: Faker yoki «Sinov ...» nomlari ishlatiladi.

## 6. Tuzilma

```
app/Services/Monitoring/
  Contracts/Detector.php          detektor interfeysi: run(params) → DetectorResult
  Detectors/                      D1AbsenceRiskZone, D2UnexcusedAbsence, Q1FinalGradesEntry, A5ContractAdmission,
                                  E1OrderNotice, E7TransferFile
  DetectorRunner.php              istisno ochish / yangilash / avtomatik yopish (fingerprint)
  IstisnoWorkflow.php             holat o'tishlari, javob, dalil, biriktirish
  IstisnoNotifier.php             mark ichki bildirishnomasi + Telegram
  MonitoringAccess.php            kirish qoidalari (rollar: config/monitoring.php)
  MonitoringCatalog.php           detectors.yaml → monitoring_detectors
  OwnerResolver.php               dekan / kafedra mudiri / standart mas'ul
  Imports/                        Excel/CSV: buyruqlar, tavsiya etilganlar
app/Http/Controllers/Admin/Monitoring/   panel, istisnolar, detektorlar, importlar, reyestrlar
resources/views/admin/monitoring/        sahifalar
config/monitoring.php                    detektor klasslari, rollar, buyruq turlari, E-7 hujjatlari
```

Yangi detektor qo'shish:
1. `Detectors/` ga `Detector` interfeysini amalga oshiruvchi klass yozish.
2. Klassni `config/monitoring.php` → `detectors` ga qo'shish.
3. `docs/detectors.yaml` dagi qatoriga `params` yozib, `monitoring:sync-catalog` ni ishga tushirish.
4. Kamida ikkita test yozish: «buzilish bor» va «buzilish yo'q».
