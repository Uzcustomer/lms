# Variant 2: Ma'ruza jadvalini joylashtirish — Amalga oshirish rejasi

## Umumiy maqsad
O'quv bo'limi Excel orqali dars jadvalini yuklaydi, ASC Timetable stilida vizual grid ko'radi,
Hemis dagi jadval bilan solishtirib nomuvofiqliklarni aniqlaydi.

---

## 1-qadam: Ma'lumotlar bazasi — `lecture_schedules` jadvali

Yangi migration yaratiladi. Exceldan yuklangan jadval shu jadvalga saqlanadi.

```
lecture_schedules:
  id, uploaded_by (teacher/user id), batch_id (yuklash sessiyasi),
  week_day (1=Dush...6=Shanba), lesson_pair_code, lesson_pair_name,
  lesson_pair_start_time, lesson_pair_end_time,
  group_name, group_id (nullable, Hemis group_hemis_id),
  subject_name, subject_id (nullable),
  employee_name, employee_id (nullable),
  auditorium_name, training_type_name,
  education_year, semester_code,
  status (pending/approved/conflict),
  created_at, updated_at
```

`lecture_schedule_batches` jadvali ham kerak — har bir yuklash haqida meta ma'lumot:
```
lecture_schedule_batches:
  id, uploaded_by, file_name, total_rows,
  conflicts_count, hemis_mismatches_count,
  status (processing/completed/error),
  created_at, updated_at
```

## 2-qadam: Excel import — `LectureScheduleImport` class

**Fayl:** `app/Imports/LectureScheduleImport.php`

Excel formati (kutilayotgan ustunlar):
| Kun | Juftlik | Boshlanish | Tugash | Guruh | Fan | O'qituvchi | Auditoriya | Turi |
|-----|---------|-----------|--------|-------|-----|-----------|------------|------|
| Dushanba | 1-juftlik | 08:00 | 09:20 | 101-guruh | Matematika | Aliyev A. | 301 | Ma'ruza |

- `ToCollection` + `WithHeadingRow` + `WithValidation` interfeyslar
- Har bir qator `lecture_schedules` jadvaliga saqlanadi
- Guruh/fan/o'qituvchi nomlarini mavjud modellar bilan moslashtirish (fuzzy match)
- Xatolarni yig'ish va qaytarish

## 3-qadam: Konflikt aniqlash service — `LectureScheduleConflictService`

**Fayl:** `app/Services/LectureScheduleConflictService.php`

### A) Ichki konfliktlar (yuklangan jadval ichida):
1. **O'qituvchi konflikti** — bitta o'qituvchiga bir vaqtda 2 dars
2. **Auditoriya konflikti** — bitta xonaga bir vaqtda 2 guruh
3. **Guruh konflikti** — bitta guruhga bir vaqtda 2 fan

### B) Hemis bilan solishtirish:
1. `schedules` jadvalidan shu hafta/semestr uchun Hemis jadvalini olish
2. Har bir yuklangan qatorni Hemis jadvali bilan solishtirish:
   - Guruh + kun + juftlik bo'yicha matching
   - Fan, o'qituvchi, auditoriya farqlarini aniqlash
3. Nomuvofiqlik turlari: `fan_notogri`, `oqituvchi_notogri`, `auditoriya_notogri`, `hemis_topilmadi`, `ortiqcha_hemis`

## 4-qadam: Controller metodlari

**Fayl:** `app/Http/Controllers/Admin/LectureScheduleController.php`

```
index()          — Asosiy sahifa (jadval + upload form)
import()         — Excel faylni qabul qilish va import qilish (POST)
data()           — AJAX: yuklangan jadval ma'lumotlari (GET, JSON)
compare()        — AJAX: Hemis bilan solishtirish natijalari (GET, JSON)
conflicts()      — AJAX: ichki konfliktlar ro'yxati (GET, JSON)
destroy($id)     — Batch ni o'chirish (DELETE)
downloadTemplate() — Namuna Excel faylni yuklab olish (GET)
```

## 5-qadam: Route'lar

Admin va teacher guruhlarga qo'shiladi:
```
GET   /lecture-schedule                → index (mavjud)
POST  /lecture-schedule/import         → import
GET   /lecture-schedule/data           → data
GET   /lecture-schedule/compare        → compare
GET   /lecture-schedule/conflicts      → conflicts
DELETE /lecture-schedule/{id}          → destroy
GET   /lecture-schedule/template       → downloadTemplate
```

## 6-qadam: View — ASC Timetable stilidagi grid

**Fayl:** `resources/views/admin/lecture-schedule/index.blade.php`

### Sahifa tarkibi:

**A) Yuqori panel:**
- Excel yuklash tugmasi (drag-and-drop zona)
- Namuna yuklab olish tugmasi
- Filtrlar: semestr, hafta, guruh/o'qituvchi ko'rinishi

**B) ASC stilidagi grid jadval:**
```
         | Dushanba | Seshanba | Chorshanba | Payshanba | Juma | Shanba |
---------|----------|----------|------------|-----------|------|--------|
1-juftlik|  [karta] |  [karta] |   [karta]  |  [karta]  |      |        |
2-juftlik|  [karta] |          |   [karta]  |           |      |        |
3-juftlik|          |  [karta] |            |  [karta]  |      |        |
...
```

Har bir karta ichida:
- Fan nomi (qalin)
- O'qituvchi ismi
- Auditoriya
- Dars turi (rang bilan: ma'ruza=ko'k, seminar=yashil, amaliy=sariq)

**C) Rang kodlari (Hemis solishtirish):**
- 🟢 Yashil fon — Hemis bilan to'liq mos
- 🟡 Sariq fon — Qisman mos (auditoriya farq)
- 🔴 Qizil fon — Mos kelmaydi (fan/o'qituvchi farq)
- ⬜ Kulrang fon — Hemis da topilmadi
- 🟣 Binafsha chegara — Ichki konflikt bor

**D) Ogohlantirish paneli (o'ng taraf yoki pastda):**
- Jami moslik foizi (pie chart)
- Konfliktlar soni (ichki + hemis)
- Xatolar ro'yxati (bosilsa jadvalda highlight bo'ladi)

## 7-qadam: Excel template

`public/templates/lecture-schedule-template.xlsx` — namuna fayl:
- Ustun sarlavhalari tayyor
- Namuna 2-3 qator ma'lumot
- Validatsiya qoidalari (dropdown kunlar, juftliklar)

---

## Fayllar ro'yxati (yaratiladi/o'zgartiriladi):

### Yangi fayllar:
1. `database/migrations/xxxx_create_lecture_schedule_tables.php`
2. `app/Models/LectureSchedule.php`
3. `app/Models/LectureScheduleBatch.php`
4. `app/Imports/LectureScheduleImport.php`
5. `app/Services/LectureScheduleConflictService.php`
6. `app/Exports/LectureScheduleTemplate.php`
7. `resources/views/admin/lecture-schedule/index.blade.php` (qayta yoziladi)

### O'zgartiriladigan fayllar:
8. `app/Http/Controllers/Admin/LectureScheduleController.php` (kengaytiriladi)
9. `routes/web.php` (yangi route'lar)
