-- =====================================================================
-- Ko'p juftlikli (pair) kunlarda otrabotka tekshiruvi (READ-ONLY).
-- Jurnal: fan_id=177 (Umumiy xirurgiya), guruh d2/23-08a, 6-sem.
-- Eslatma: hemis_id ustunlari turli collation'da bo'lgani uchun JOIN'larda
--          COLLATE utf8mb4_unicode_ci ishlatilgan (collation mismatch fix).
-- =====================================================================
SET @fan = 177;


-- ---------------------------------------------------------------------
-- Q1) ASOSIY: har (talaba × ko'p-juftlikli kun) uchun juftliklar holati.
--     'tafsilot': "juftlik:baho/retake(quiz)" — masalan "11:NB/64(quiz) | 12:NB/-"
--     => 11-juftlikka retake qo'yilgan, 12-juftlikka qo'yilMAGAN.
-- ---------------------------------------------------------------------
SELECT
    s.full_name,
    DATE(sg.lesson_date)                              AS kun,
    COUNT(*)                                          AS juftliklar,
    SUM(sg.grade IS NULL AND sg.reason = 'absent')    AS nb_soni,
    SUM(sg.retake_grade IS NOT NULL)                  AS retake_qoyilgan,
    GROUP_CONCAT(
        CONCAT(sg.lesson_pair_code, ':',
               COALESCE(sg.grade, 'NB'), '/',
               COALESCE(sg.retake_grade, '-'),
               IF(sg.quiz_result_id IS NOT NULL, '(quiz)', ''))
        ORDER BY sg.lesson_pair_code SEPARATOR '  |  ') AS tafsilot
FROM student_grades sg
JOIN students s
  ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
WHERE sg.subject_id = @fan
  AND s.group_name = 'd2/23-08a'
  AND sg.deleted_at IS NULL
  AND sg.training_type_code NOT IN (11, 17, 99, 100, 101, 102, 103)
GROUP BY s.full_name, DATE(sg.lesson_date)
HAVING juftliklar > 1
ORDER BY s.full_name, kun;


-- ---------------------------------------------------------------------
-- Q2) "Yarim qo'llangan": ko'p juftlikli kunda retake ba'zi juftliklarga
--     qo'yilgan, hammasiga emas. Bo'sh qaytsa -> har doim to'liq qo'yilyapti.
-- ---------------------------------------------------------------------
SELECT * FROM (
    SELECT
        s.full_name,
        DATE(sg.lesson_date)                           AS kun,
        COUNT(*)                                       AS juftliklar,
        SUM(sg.grade IS NULL AND sg.reason='absent')   AS nb_soni,
        SUM(sg.retake_grade IS NOT NULL)               AS retake_soni
    FROM student_grades sg
    JOIN students s
      ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
    WHERE sg.subject_id = @fan AND s.group_name = 'd2/23-08a'
      AND sg.deleted_at IS NULL
      AND sg.training_type_code NOT IN (11,17,99,100,101,102,103)
    GROUP BY s.full_name, DATE(sg.lesson_date)
) t
WHERE juftliklar > 1
  AND retake_soni > 0
  AND retake_soni < juftliklar
ORDER BY full_name, kun;


-- ---------------------------------------------------------------------
-- Q3) Retake koeffitsiyenti: retake_grade / quiz_grade (~0.80 sababsiz, ~1.00 sababli).
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
JOIN students s
  ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
JOIN hemis_quiz_results h ON h.id = sg.quiz_result_id
WHERE sg.subject_id = @fan AND s.group_name = 'd2/23-08a'
  AND sg.deleted_at IS NULL
  AND sg.retake_grade IS NOT NULL
ORDER BY s.full_name, kun, juftlik;


-- ---------------------------------------------------------------------
-- Q4) Sabablilik manbai: attendances.absent_on>0 yoki tasdiqlangan AbsenceExcuse.
-- ---------------------------------------------------------------------
SELECT
    s.full_name,
    DATE(sg.lesson_date)   AS kun,
    sg.lesson_pair_code    AS juftlik,
    sg.grade, sg.retake_grade,
    (SELECT MAX(a.absent_on) FROM attendances a
       WHERE a.student_hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
         AND a.subject_id = sg.subject_id
         AND DATE(a.lesson_date) = DATE(sg.lesson_date)
         AND a.lesson_pair_code = sg.lesson_pair_code COLLATE utf8mb4_unicode_ci) AS absent_on,
    (SELECT COUNT(*) FROM absence_excuses ae
       WHERE ae.status = 'approved'
         AND ae.student_hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
         AND ae.start_date <= DATE(sg.lesson_date)
         AND ae.end_date   >= DATE(sg.lesson_date)) AS approved_excuse
FROM student_grades sg
JOIN students s
  ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
WHERE sg.subject_id = @fan AND s.group_name = 'd2/23-08a'
  AND sg.deleted_at IS NULL
  AND (sg.grade IS NULL OR sg.retake_grade IS NOT NULL)
ORDER BY s.full_name, kun, juftlik;


-- ---------------------------------------------------------------------
-- Q5) GLOBAL: butun bazada ko'p-juftlikli kunda otrabotka (retake) ba'zi
--     juftliklarga tushgan, HAMMASIGA emas holatlar. Bo'sh qaytsa -> doim
--     to'liq tushyapti. Qatorlar -> "yarim tushgan", tekshirish kerak.
-- ---------------------------------------------------------------------
SELECT s.full_name, s.group_name, sg.subject_id, DATE(sg.lesson_date) AS kun,
       COUNT(*) AS juftliklar, SUM(sg.retake_grade IS NOT NULL) AS retake_soni,
       GROUP_CONCAT(CONCAT(sg.lesson_pair_code,':',COALESCE(sg.grade,'NB'),
         '/',COALESCE(sg.retake_grade,'-')) ORDER BY sg.lesson_pair_code SEPARATOR ' | ') AS tafsilot
FROM student_grades sg
JOIN students s ON s.hemis_id = sg.student_hemis_id COLLATE utf8mb4_unicode_ci
WHERE sg.deleted_at IS NULL
  AND sg.training_type_code NOT IN (11,17,99,100,101,102,103)
GROUP BY sg.student_hemis_id, sg.subject_id, DATE(sg.lesson_date)
HAVING juftliklar > 1 AND retake_soni > 0 AND retake_soni < juftliklar
ORDER BY kun DESC
LIMIT 50;
