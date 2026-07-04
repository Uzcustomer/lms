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


-- ---------------------------------------------------------------------
-- Q5) TO'LIQ JURNAL RASMI: shu talabaning student_grades'dagi BARCHA
--     yozuvlari — apelyatsiya aynan shu yozuvlarni (quiz_result_id IS NOT
--     NULL bo'lganlarini) ko'rsatadi. attempt = urinish (12=1, 12a=2,
--     12b=3). training_type_code: 101=OSKE, 102=Test. Qayta-o'qish
--     natijasi ham (diagnostika orqali yuklangan bo'lsa) shu yerda
--     quiz_result_id bilan turadi.
-- ---------------------------------------------------------------------
SELECT
    sg.id                AS student_grade_id,
    sg.subject_name,
    sg.training_type_code,
    sg.attempt,
    sg.reason,
    sg.grade,
    sg.retake_grade,
    sg.quiz_result_id,
    DATE(sg.lesson_date) AS sana,
    sg.is_qoshimcha
FROM student_grades sg
JOIN students s
      ON s.hemis_id COLLATE utf8mb4_unicode_ci = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
WHERE s.full_name LIKE '%AVAZXONOV%'
  AND sg.deleted_at IS NULL
ORDER BY sg.subject_name, sg.training_type_code, sg.attempt, sg.lesson_date;


-- ---------------------------------------------------------------------
-- Q6) 2/3-URINISH HAQIQATAN YUKLANADIMI: attempt>1 va quiz_result_id li
--     yozuvlar — bular apelyatsiyada ko'rinadi (quiz_result_id bor).
--     Bo'sh bo'lmasa -> multi-urinish apelyatsiyada chiqadi; muammo
--     faqat shu talabada 2/3-urinish YO'Qligida.
-- ---------------------------------------------------------------------
SELECT
    sg.student_hemis_id,
    sg.subject_name,
    sg.training_type_code,
    sg.attempt,
    sg.grade,
    sg.quiz_result_id
FROM student_grades sg
WHERE sg.deleted_at IS NULL
  AND sg.attempt > 1
  AND sg.quiz_result_id IS NOT NULL
ORDER BY sg.student_hemis_id, sg.subject_name, sg.attempt
LIMIT 30;
