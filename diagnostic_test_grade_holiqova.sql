-- =====================================================================
-- DIAGNOSTIKA: Holiqova test bahosi nega jurnal/qaydnoma (82) va
-- vedomost-tekshirish (89) da farq qiladi?
--
-- Ishga tushirish (production serverda, MySQL):
--   mysql -u <user> -p <database> < diagnostic_test_grade_holiqova.sql
-- yoki phpMyAdmin / DBeaver da bittadan ishlatib chiqing.
--
-- Talaba: HOLIQOVA POKIZA SAMANDAR QIZI
--   student_id_number = 368241100269
--   hemis_id          = 7416   (skrinshotdagi "Talaba HEMIS ID")
-- =====================================================================


-- 0) Avval talabaning ID larini tasdiqlaymiz (ism/hemis_id/id mosligini)
SELECT id, hemis_id, student_id_number, full_name, group_id
FROM students
WHERE student_id_number = '368241100269'
   OR full_name LIKE '%HOLIQOVA POKIZA%';


-- =====================================================================
-- 1) ENG MUHIM SO'ROV: barcha TEST qatorlari (training_type_code = 102)
--    soft-delete qilinganlari ham ko'rinsin (deleted_at NOT NULL).
--    Bu yerda 82 va 89 alohida qatorlar bo'lib chiqishi kerak.
-- =====================================================================
SELECT
    sg.id,
    sg.student_hemis_id,
    sg.subject_id,
    sg.semester_code,
    sg.education_year_code,
    sg.training_type_code,
    sg.attempt,                       -- urinish raqami (1 = asosiy)
    sg.test_id,                       -- exam_tests.id ga bog'lanadi
    et.shakl        AS exam_test_shakl,
    sg.grade,                         -- <-- 82 / 89 shu yerda
    sg.retake_grade,
    sg.status,
    sg.is_qoshimcha,
    sg.is_final,
    sg.lesson_date,
    sg.created_at,                    -- qachon qo'shilgan (qaydnoma 25.05 da qulflangan)
    sg.updated_at,
    sg.deleted_at                     -- NULL bo'lmasa - soft-delete qilingan
FROM student_grades sg
LEFT JOIN exam_tests et ON et.id = sg.test_id
WHERE sg.student_hemis_id = '7416'
  AND sg.training_type_code = 102
ORDER BY sg.deleted_at IS NULL DESC, sg.attempt, sg.created_at;


-- =====================================================================
-- 2) Vedomost-tekshirish AYNAN shu so'rovni ishlatadi (MAX, deleted_at IS NULL).
--    Natija = 89 chiqsa, demak 89 li (deleted_at = NULL) qator mavjud.
--    subject_id ni yuqoridagi 1-so'rovdan oling (masalan @sid).
-- =====================================================================
SELECT
    student_hemis_id,
    MAX(grade) AS test_max_grade,     -- tekshirish/qaydnoma shuni oladi
    COUNT(*)   AS qatorlar_soni,
    GROUP_CONCAT(grade ORDER BY grade) AS barcha_baholar
FROM student_grades
WHERE student_hemis_id = '7416'
  AND training_type_code = 102
  AND deleted_at IS NULL
  -- AND subject_id = '<1-so'rovdan oling>'
  -- AND semester_code = '<1-so'rovdan oling>'
GROUP BY student_hemis_id;


-- =====================================================================
-- 3) Jurnal ASOSIY "Test" ustuni faqat attempt = 1 ni ko'rsatadi.
--    Bu 82 chiqishi kerak.
-- =====================================================================
SELECT
    student_hemis_id,
    attempt,
    grade,
    test_id,
    created_at,
    deleted_at
FROM student_grades
WHERE student_hemis_id = '7416'
  AND training_type_code = 102
  AND deleted_at IS NULL
  AND attempt = 1
ORDER BY created_at;


-- =====================================================================
-- 4) OSKI (101) va ON (100) ham tekshiramiz - balki 89 boshqa koddan
--    noto'g'ri 102 ga tushib qolgandir yoki dubl bo'lgandir.
-- =====================================================================
SELECT
    training_type_code,
    attempt,
    grade,
    test_id,
    status,
    created_at,
    deleted_at
FROM student_grades
WHERE student_hemis_id = '7416'
  AND training_type_code IN (100, 101, 102)
ORDER BY training_type_code, attempt, created_at;
