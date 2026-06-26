-- =====================================================================
-- Kunlik monitoring "joriy semestr" filtrini tekshirish (READ-ONLY)
-- Maqsad: hemis_quiz_results.semester va students.semester_name
--         formatlarini ko'rib, raqam bo'yicha solishtirish to'g'ri
--         ishlashini tasdiqlash.
-- Eslatma: REGEXP_SUBSTR MySQL 8.0+ / MariaDB 10.0.5+ da ishlaydi.
-- =====================================================================


-- 1) hemis_quiz_results.semester maydonida qanday qiymatlar bor?
--    (format: "5" / "5-sem" / "5-semestr" / HEMIS kodi "15" ... ?)
SELECT semester, COUNT(*) AS cnt
FROM hemis_quiz_results
GROUP BY semester
ORDER BY cnt DESC
LIMIT 50;


-- 2) students.semester_name va semester_code formatlari
SELECT semester_name, semester_code, COUNT(*) AS cnt
FROM students
WHERE student_status_code = 11           -- faqat o'qiyotganlar
GROUP BY semester_name, semester_code
ORDER BY semester_code
LIMIT 50;


-- 3) ASOSIY TEKSHIRUV — PHP mantig'ining aynan nusxasi.
--    Har bir natija uchun: natija semestri vs talabaning joriy semestri,
--    va filtr qarori (KEEP = qoladi, EXCLUDE = o'tgan semestr, chiqariladi).
--    Sanani o'zingiz ko'rgan kunga moslang.
SELECT
    hqr.attempt_id,
    hqr.student_id,
    s.full_name,
    hqr.semester                                              AS result_semester,
    s.semester_name                                           AS student_semester_name,
    s.semester_code                                           AS student_semester_code,
    CAST(REGEXP_SUBSTR(hqr.semester, '[0-9]+') AS UNSIGNED)   AS result_sem_num,
    CAST(REGEXP_SUBSTR(s.semester_name, '[0-9]+') AS UNSIGNED) AS student_sem_num,
    CASE
        WHEN s.semester_name IS NULL OR REGEXP_SUBSTR(s.semester_name,'[0-9]+') = ''
            THEN 'KEEP (talaba semestri noma''lum)'
        WHEN hqr.semester IS NULL OR REGEXP_SUBSTR(hqr.semester,'[0-9]+') = ''
            THEN 'KEEP (natija semestri noma''lum)'
        WHEN CAST(REGEXP_SUBSTR(hqr.semester,'[0-9]+') AS UNSIGNED)
             = CAST(REGEXP_SUBSTR(s.semester_name,'[0-9]+') AS UNSIGNED)
            THEN 'KEEP (joriy semestr)'
        ELSE 'EXCLUDE (o''tgan semestr)'
    END AS verdict
FROM hemis_quiz_results hqr
JOIN students s
    ON s.hemis_id = hqr.student_id
    OR s.student_id_number = hqr.student_id
WHERE hqr.date_finish >= '2026-02-03'
  AND hqr.date_finish <  '2026-02-08'
ORDER BY verdict DESC, hqr.attempt_id
LIMIT 200;


-- 4) XULOSA — nechta natija KEEP, nechta EXCLUDE bo'ladi?
--    Agar EXCLUDE soni kutilganidek (o'tgan semestrlar) bo'lsa — mantiq to'g'ri.
SELECT
    CASE
        WHEN s.semester_name IS NULL OR REGEXP_SUBSTR(s.semester_name,'[0-9]+') = ''
            THEN 'KEEP (talaba semestri noma''lum)'
        WHEN hqr.semester IS NULL OR REGEXP_SUBSTR(hqr.semester,'[0-9]+') = ''
            THEN 'KEEP (natija semestri noma''lum)'
        WHEN CAST(REGEXP_SUBSTR(hqr.semester,'[0-9]+') AS UNSIGNED)
             = CAST(REGEXP_SUBSTR(s.semester_name,'[0-9]+') AS UNSIGNED)
            THEN 'KEEP (joriy semestr)'
        ELSE 'EXCLUDE (o''tgan semestr)'
    END AS verdict,
    COUNT(*) AS cnt
FROM hemis_quiz_results hqr
JOIN students s
    ON s.hemis_id = hqr.student_id
    OR s.student_id_number = hqr.student_id
WHERE hqr.date_finish >= '2026-02-03'
  AND hqr.date_finish <  '2026-02-08'
GROUP BY verdict
ORDER BY cnt DESC;


-- 5) (ixtiyoriy) EXCLUDE bo'ladiganlarning aniq tafsiloti — rostdan o'tgan
--    semestrmi yoki format nomuvofiqligi tufayli noto'g'ri chiqyaptimi, ko'rish.
SELECT
    hqr.attempt_id,
    s.full_name,
    s.education_type_name,
    hqr.semester        AS result_semester,
    s.semester_name     AS student_semester_name,
    hqr.fan_name,
    hqr.shakl
FROM hemis_quiz_results hqr
JOIN students s
    ON s.hemis_id = hqr.student_id
    OR s.student_id_number = hqr.student_id
WHERE hqr.date_finish >= '2026-02-03'
  AND hqr.date_finish <  '2026-02-08'
  AND s.semester_name IS NOT NULL
  AND REGEXP_SUBSTR(s.semester_name,'[0-9]+') <> ''
  AND hqr.semester IS NOT NULL
  AND REGEXP_SUBSTR(hqr.semester,'[0-9]+') <> ''
  AND CAST(REGEXP_SUBSTR(hqr.semester,'[0-9]+') AS UNSIGNED)
      <> CAST(REGEXP_SUBSTR(s.semester_name,'[0-9]+') AS UNSIGNED)
ORDER BY hqr.attempt_id
LIMIT 100;
