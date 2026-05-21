-- ============================================================
-- YN test sanasi saqlanmasligi diagnostikasi — guruh: p22-02a
-- Fan: "Bolalar xirurgiyasi"
-- Ishlatish:  mysql lmsttatf < diagnostic_yn_test_date.sql
-- ============================================================

-- ------------------------------------------------------------
-- 1) p22-02a guruh ma'lumotlari (group_hemis_id, curriculum_hemis_id)
-- ------------------------------------------------------------
SELECT '=== 1. GURUH ===' AS step;
SELECT id, group_hemis_id, name, curriculum_hemis_id,
       department_hemis_id, specialty_hemis_id, active
FROM groups
WHERE name = 'p22-02a';

-- ------------------------------------------------------------
-- 2) p22-02a uchun BARCHA exam_schedules yozuvlari (Bolalar xirurgiyasi)
--    Guruh sathidagi (student_hemis_id IS NULL) va per-student qatorlar.
--    Bu yerda test_resit_date / oski_resit_date qiymatlarini ko'ramiz.
-- ------------------------------------------------------------
SELECT '=== 2. EXAM_SCHEDULES (p22-02a / Bolalar xirurgiyasi) ===' AS step;
SELECT es.id, es.group_hemis_id, es.subject_id, es.semester_code,
       es.student_hemis_id, es.curriculum_hemis_id,
       es.oski_date,  es.test_date,
       es.oski_resit_date,  es.test_resit_date,
       es.oski_resit2_date, es.test_resit2_date,
       es.oski_na, es.test_na,
       es.updated_at, es.updated_by
FROM exam_schedules es
JOIN groups g ON g.group_hemis_id = es.group_hemis_id
WHERE g.name = 'p22-02a'
  AND es.subject_name LIKE '%Bolalar xirurgiya%'
ORDER BY (es.student_hemis_id IS NOT NULL), es.id;

-- ------------------------------------------------------------
-- 3) DUBLIKAT tekshiruvi: bitta guruh+fan+semestr uchun bir nechta
--    GURUH sathidagi (student_hemis_id IS NULL) yozuv bormi?
--    (exam_schedules da unique constraint yo'q — dublikat mumkin.)
--    Agar cnt > 1 chiqsa: store() bir qatorga yozadi, sahifa boshqasini
--    o'qiydi — shu sababli "saqlandi" deydi-yu, ko'rinmaydi.
-- ------------------------------------------------------------
SELECT '=== 3. DUBLIKAT GURUH-SATHI YOZUVLAR ===' AS step;
SELECT es.group_hemis_id, es.subject_id, es.semester_code, COUNT(*) AS cnt
FROM exam_schedules es
JOIN groups g ON g.group_hemis_id = es.group_hemis_id
WHERE g.name = 'p22-02a'
  AND es.student_hemis_id IS NULL
GROUP BY es.group_hemis_id, es.subject_id, es.semester_code
HAVING COUNT(*) > 1;

-- ------------------------------------------------------------
-- 4) p22-02a NING O'Z o'quv rejasidagi fan qatori (sahifa shuni ko'rsatadi).
--    closing_form bu yerda 'oski_test' bo'lishi kerak (badge "OSKI + Test").
-- ------------------------------------------------------------
SELECT '=== 4. p22-02a O\'Z CURRICULUM_SUBJECTS QATORI ===' AS step;
SELECT cs.id, cs.curricula_hemis_id, cs.subject_id, cs.semester_code,
       cs.subject_name, cs.closing_form
FROM curriculum_subjects cs
JOIN groups g ON g.curriculum_hemis_id = cs.curricula_hemis_id
WHERE g.name = 'p22-02a'
  AND cs.subject_name LIKE '%Bolalar xirurgiya%';

-- ------------------------------------------------------------
-- 5) ENG MUHIM — store() AYNAN SHU NATIJANI keyBy(subject_id|semester_code)
--    bilan SIQADI. Bir xil (subject_id, semester_code) ga ega BARCHA
--    o'quv rejalardagi qatorlar, har birining closing_form qiymati bilan.
--    Agar bu yerda har xil closing_form chiqsa (masalan biri 'oski_test',
--    boshqasi 'oski') — XATO TASDIQLANADI: store() noto'g'ri 'oski' ni
--    olib, test sanasini jimgina null qiladi.
-- ------------------------------------------------------------
SELECT '=== 5. SHU (subject_id, semester_code) BO\'YICHA BARCHA REJALAR ===' AS step;
SELECT cs.id, cs.curricula_hemis_id, cs.subject_id, cs.semester_code,
       cs.subject_name, cs.closing_form
FROM curriculum_subjects cs
WHERE (cs.subject_id, cs.semester_code) IN (
    SELECT cs2.subject_id, cs2.semester_code
    FROM curriculum_subjects cs2
    JOIN groups g ON g.curriculum_hemis_id = cs2.curricula_hemis_id
    WHERE g.name = 'p22-02a'
      AND cs2.subject_name LIKE '%Bolalar xirurgiya%'
)
ORDER BY cs.subject_id, cs.semester_code, cs.curricula_hemis_id;

-- ------------------------------------------------------------
-- 6) Solishtirish uchun — p22-01a (test sanasi ISHLAYDI) o'quv rejasi
-- ------------------------------------------------------------
SELECT '=== 6. p22-01a SOLISHTIRISH ===' AS step;
SELECT g.name AS guruh, cs.curricula_hemis_id, cs.subject_id,
       cs.semester_code, cs.closing_form
FROM curriculum_subjects cs
JOIN groups g ON g.curriculum_hemis_id = cs.curricula_hemis_id
WHERE g.name IN ('p22-01a', 'p22-02a')
  AND cs.subject_name LIKE '%Bolalar xirurgiya%'
ORDER BY g.name;
