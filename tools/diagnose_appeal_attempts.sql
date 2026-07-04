-- =====================================================================
-- Apelyatsiya qidiruvida nega faqat ba'zi urinishlar chiqishini aniqlash
-- (READ-ONLY). Misol: AVAZXONOV MIRJAHON RUSLAN O'G'LI.
-- Maqsad: hemis_quiz_results (Moodle manbasi) va student_grades (jurnalga
--         yuklangan) o'rtasidagi bog'lanishni ko'rish.
-- =====================================================================
SET @name = '%AVAZXONOV%';


-- ---------------------------------------------------------------------
-- Q1) Moodle'da (hemis_quiz_results) shu talaba uchun BARCHA natijalar,
--     shakl (1-urinish/2-urinish/qayta o'qish...) va quiz turi bo'yicha.
--     uploaded_cnt = shulardan nechtasi student_grades'ga bog'langan.
--     Agar cnt > uploaded_cnt bo'lsa -> ba'zi urinishlar jurnalga
--     yuklanmagan, shuning uchun apelyatsiyada ko'rinmaydi.
-- ---------------------------------------------------------------------
SELECT
    h.shakl,
    h.quiz_type,
    COUNT(*)                                                    AS moodle_cnt,
    SUM(h.is_active)                                            AS active_cnt,
    SUM(CASE WHEN sg.id IS NOT NULL THEN 1 ELSE 0 END)         AS uploaded_cnt
FROM hemis_quiz_results h
LEFT JOIN student_grades sg
       ON sg.quiz_result_id = h.id AND sg.deleted_at IS NULL
WHERE h.student_name LIKE @name
GROUP BY h.shakl, h.quiz_type
ORDER BY h.quiz_type, h.shakl;


-- ---------------------------------------------------------------------
-- Q2) ASOSIY: har bir Moodle natijasi (attempt) tafsiloti — jurnalga
--     yuklanganmi (student_grade_id NULL bo'lsa = yuklanmagan =
--     apelyatsiyada CHIQMAYDI).
-- ---------------------------------------------------------------------
SELECT
    h.attempt_id,
    h.student_id                AS moodle_student_id,
    h.fan_name,
    h.quiz_type,
    h.shakl,
    h.grade                     AS moodle_baho,
    h.is_active,
    DATE(h.date_finish)         AS sana,
    sg.id                       AS student_grade_id,
    sg.grade                    AS journal_grade,
    sg.retake_grade,
    sg.reason,
    sg.student_hemis_id
FROM hemis_quiz_results h
LEFT JOIN student_grades sg
       ON sg.quiz_result_id = h.id AND sg.deleted_at IS NULL
WHERE h.student_name LIKE @name
ORDER BY h.fan_name, h.quiz_type, h.shakl, h.date_finish;


-- ---------------------------------------------------------------------
-- Q3) Xulosa: nechta attempt bor, nechtasi yuklangan, nechtasi yo'q.
-- ---------------------------------------------------------------------
SELECT
    COUNT(*)                                                   AS jami_attempt,
    SUM(CASE WHEN sg.id IS NOT NULL THEN 1 ELSE 0 END)        AS yuklangan,
    SUM(CASE WHEN sg.id IS NULL THEN 1 ELSE 0 END)            AS yuklanmagan,
    COUNT(DISTINCT h.student_id)                               AS moodle_student_id_soni
FROM hemis_quiz_results h
LEFT JOIN student_grades sg
       ON sg.quiz_result_id = h.id AND sg.deleted_at IS NULL
WHERE h.student_name LIKE @name;


-- ---------------------------------------------------------------------
-- Q4) Tizimда "urinish" (shakl) qanday ko'rinishda saqlanadi — umumiy
--     ro'yxat (qayta urinish / qo'shimcha / qayta o'qish nomlarini bilish).
-- ---------------------------------------------------------------------
SELECT shakl, COUNT(*) AS cnt
FROM hemis_quiz_results
GROUP BY shakl
ORDER BY cnt DESC
LIMIT 40;
