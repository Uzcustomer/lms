-- =====================================================================
-- Bitta "FARQ" attempt uchun aniq diagnoz (READ-ONLY).
-- Misol: UROLOV FAYZULLO — attempt_id=143987, HEMIS=368231100046, 2026-05-20
-- Boshqa holat uchun pastdagi 2 ta o'zgaruvchini almashtiring.
-- =====================================================================
SET @aid   = 143987;
SET @hemis = '368231100046';


-- ---------------------------------------------------------------------
-- Q1) ENG MUHIM: attempt'ning hemis_quiz_results dagi holati.
--     is_active = 0 bo'lsa  -> diagnostika/upload uni KO'RMAYDI, lekin kunlik
--        monitoring sanaydi  => hisobotdagi FARQ NOTO'G'RI (report bug).
--     is_active = 1 bo'lsa  -> yozuv aktiv, diagnostikada ko'rinishi kerak;
--        unda muammo boshqa joyda (dedup / jurnal / yuklash).
-- ---------------------------------------------------------------------
SELECT id, attempt_id, is_active, student_id, student_name,
       fan_id, fan_name, shakl, quiz_type, semester,
       grade, date_finish, synced_at, updated_at
FROM hemis_quiz_results
WHERE attempt_id = @aid;


-- ---------------------------------------------------------------------
-- Q2) Shu attempt jurnalga (student_grades) yuklanganmi?
--     Bo'sh bo'lsa -> yuklanmagan (mark-gap haqiqat).
-- ---------------------------------------------------------------------
SELECT sg.id, sg.student_hemis_id, sg.subject_id, sg.semester_code,
       sg.lesson_date, sg.grade, sg.retake_grade, sg.reason, sg.status,
       sg.training_type_code, sg.training_type_name, sg.quiz_result_id
FROM student_grades sg
WHERE sg.quiz_result_id = (SELECT id FROM hemis_quiz_results WHERE attempt_id = @aid);


-- ---------------------------------------------------------------------
-- Q3) Shu (talaba+fan+quiz_type+shakl) bo'yicha BOSHQA urinishlar bormi?
--     Diagnostika eng yuqori baholi, is_active=1 urinishni qoldiradi (dedup).
--     Agar balandroq, is_active=1 va 'uploaded=1' sibling bo'lsa — 143987
--     o'rnini bosilgan dublikat: yuklanishi shart emas (report shovqini).
-- ---------------------------------------------------------------------
SELECT h.attempt_id, h.is_active, h.grade, h.date_finish, h.shakl, h.quiz_type,
       (SELECT COUNT(*) FROM student_grades sg WHERE sg.quiz_result_id = h.id) AS uploaded
FROM hemis_quiz_results h
WHERE h.student_id = @hemis
  AND h.fan_id    = (SELECT fan_id    FROM hemis_quiz_results WHERE attempt_id = @aid)
  AND h.quiz_type = (SELECT quiz_type FROM hemis_quiz_results WHERE attempt_id = @aid)
  AND h.shakl     = (SELECT shakl     FROM hemis_quiz_results WHERE attempt_id = @aid)
ORDER BY h.grade DESC, h.attempt_id;


-- ---------------------------------------------------------------------
-- Q4) Talabaning shu fan bo'yicha jurnali (mavzu bahosi bormi?).
--     Mavzu sanasida allaqachon baho bo'lsa -> "ogohlantirish" bo'lishi kerak,
--     FARQ emas. Hech baho yo'q bo'lsa -> haqiqiy yuklanmagan.
-- ---------------------------------------------------------------------
SELECT sg.lesson_date, sg.training_type_code, sg.training_type_name,
       sg.grade, sg.retake_grade, sg.reason, sg.status, sg.quiz_result_id
FROM student_grades sg
WHERE sg.student_hemis_id = @hemis
  AND sg.subject_id = (SELECT fan_id FROM hemis_quiz_results WHERE attempt_id = @aid)
ORDER BY sg.lesson_date;


-- ---------------------------------------------------------------------
-- Q5) Talaba students jadvalida bormi va joriy semestri (semestr filtri uchun).
-- ---------------------------------------------------------------------
SELECT hemis_id, student_id_number, full_name, student_status_code,
       semester_code, semester_name, group_id, department_name, specialty_name
FROM students
WHERE hemis_id = @hemis OR student_id_number = @hemis;


-- =====================================================================
-- TIZIMLI TEKSHIRUV: kunlik monitoring is_active ni filtrlamaydi.
-- Quyidagi so'rov 2026-05-20 da is_active=0 bo'lib, yuklanmagan (ya'ni
-- hisobotda FARQ/Ogohlantirish bo'lib chiqadigan, lekin diagnostikada
-- KO'RINMAYDIGAN) nechta yozuv borligini ko'rsatadi.
-- Bu son > 0 bo'lsa — hisobot is_active=0 yozuvlarni noto'g'ri sanayapti.
-- =====================================================================
SELECT
    SUM(h.is_active = 0) AS inactive_count,
    SUM(h.is_active = 1) AS active_count,
    COUNT(*)             AS total_not_uploaded
FROM hemis_quiz_results h
WHERE DATE(h.date_finish) = '2026-05-20'
  AND NOT EXISTS (
        SELECT 1 FROM student_grades sg WHERE sg.quiz_result_id = h.id
  );
