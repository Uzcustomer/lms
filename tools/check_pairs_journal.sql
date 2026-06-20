-- =====================================================================
-- Ko'p juftlikli (pair) kunlarda otrabotka qanday qo'llanishini tekshirish
-- (READ-ONLY). Jurnal: fan_id=177 (Umumiy xirurgiya), guruh d2/23-08a, 6-sem.
-- Savol: bir kunda 1 juftlikka baho bor, 2-3 juftlik NB bo'lsa — otrabotka
--        qilsa hammasiga retake qo'yiladimi yoki ba'zisiga?
-- =====================================================================
SET @fan = 177;
SET @grp = 'd2/23-08a';


-- ---------------------------------------------------------------------
-- Q1) ASOSIY: har (talaba × ko'p-juftlikli kun) uchun juftliklar holati.
--     'tafsilot' ustuni: "juftlik:baho/retake" — masalan "11:NB/64 | 12:NB/-"
--     => 11-juftlikka retake (64) qo'yilgan, 12-juftlikka qo'yilMAGAN.
--     Shu orqali otrabotka HAMMA juftlikka qo'yilganmi ko'rinadi.
-- ---------------------------------------------------------------------
SELECT
    s.full_name,
    DATE(sg.lesson_date)                              AS kun,
    COUNT(*)                                          AS juftliklar,
    SUM(sg.grade IS NULL AND sg.reason = 'absent')    AS nb_soni,
    SUM(sg.retake_grade IS NOT NULL)                  AS retake_qoyilgan,
    SUM(sg.quiz_result_id IS NOT NULL)                AS quizdan_bogliq,
    GROUP_CONCAT(
        CONCAT(sg.lesson_pair_code, ':',
               COALESCE(sg.grade, 'NB'), '/',
               COALESCE(sg.retake_grade, '-'),
               IF(sg.quiz_result_id IS NOT NULL, '(quiz)', ''))
        ORDER BY sg.lesson_pair_code SEPARATOR '  |  ') AS tafsilot
FROM student_grades sg
JOIN students s ON s.hemis_id = sg.student_hemis_id
WHERE sg.subject_id = @fan
  AND s.group_name = @grp
  AND sg.deleted_at IS NULL
  AND sg.training_type_code NOT IN (11, 17, 99, 100, 101, 102, 103)
GROUP BY s.full_name, DATE(sg.lesson_date)
HAVING juftliklar > 1
ORDER BY s.full_name, kun;


-- ---------------------------------------------------------------------
-- Q2) "Yarim qo'llangan" holatlar: ko'p juftlikli kunda retake ba'zi
--     juftliklarga qo'yilgan, ba'zilariga yo'q (NB bo'lsa-da). Bu otrabotka
--     hamma juftlikka tushmaganini ko'rsatadi.
-- ---------------------------------------------------------------------
SELECT * FROM (
    SELECT
        s.full_name,
        DATE(sg.lesson_date)                           AS kun,
        COUNT(*)                                       AS juftliklar,
        SUM(sg.grade IS NULL AND sg.reason='absent')   AS nb_soni,
        SUM(sg.retake_grade IS NOT NULL)               AS retake_soni
    FROM student_grades sg
    JOIN students s ON s.hemis_id = sg.student_hemis_id
    WHERE sg.subject_id = @fan AND s.group_name = @grp
      AND sg.deleted_at IS NULL
      AND sg.training_type_code NOT IN (11,17,99,100,101,102,103)
    GROUP BY s.full_name, DATE(sg.lesson_date)
) t
WHERE juftliklar > 1
  AND retake_soni > 0
  AND retake_soni < juftliklar   -- ba'zisiga qo'yilgan, hammasiga emas
ORDER BY full_name, kun;


-- ---------------------------------------------------------------------
-- Q3) Retake koeffitsiyentini tekshirish: retake_grade / quiz_grade.
--     ~0.80 -> sababsiz; ~1.00 -> sababli. Har juftlik o'z koeffitsiyenti
--     bilan tushganini ko'rsatadi.
-- ---------------------------------------------------------------------
SELECT
    s.full_name,
    DATE(sg.lesson_date)        AS kun,
    sg.lesson_pair_code         AS juftlik,
    sg.grade                    AS jurnal_baho,
    sg.retake_grade,
    h.grade                     AS quiz_baho,
    ROUND(sg.retake_grade / NULLIF(h.grade, 0), 3) AS koef,
    h.shakl, sg.reason, sg.status
FROM student_grades sg
JOIN students s          ON s.hemis_id = sg.student_hemis_id
JOIN hemis_quiz_results h ON h.id = sg.quiz_result_id
WHERE sg.subject_id = @fan AND s.group_name = @grp
  AND sg.deleted_at IS NULL
  AND sg.retake_grade IS NOT NULL
ORDER BY s.full_name, kun, juftlik;


-- ---------------------------------------------------------------------
-- Q4) Sabablilik manbai: shu kun/juftlikda attendances.absent_on > 0 bormi,
--     yoki sanani qamragan tasdiqlangan AbsenceExcuse bormi.
-- ---------------------------------------------------------------------
SELECT
    s.full_name,
    DATE(sg.lesson_date)   AS kun,
    sg.lesson_pair_code    AS juftlik,
    sg.grade, sg.retake_grade,
    (SELECT MAX(a.absent_on) FROM attendances a
       WHERE a.student_hemis_id = sg.student_hemis_id
         AND a.subject_id = sg.subject_id
         AND DATE(a.lesson_date) = DATE(sg.lesson_date)
         AND a.lesson_pair_code = sg.lesson_pair_code)              AS absent_on,
    (SELECT COUNT(*) FROM absence_excuses ae
       WHERE ae.status = 'approved'
         AND ae.student_hemis_id = sg.student_hemis_id
         AND ae.start_date <= DATE(sg.lesson_date)
         AND ae.end_date   >= DATE(sg.lesson_date))                 AS approved_excuse
FROM student_grades sg
JOIN students s ON s.hemis_id = sg.student_hemis_id
WHERE sg.subject_id = @fan AND s.group_name = @grp
  AND sg.deleted_at IS NULL
  AND (sg.grade IS NULL OR sg.retake_grade IS NOT NULL)
ORDER BY s.full_name, kun, juftlik;
