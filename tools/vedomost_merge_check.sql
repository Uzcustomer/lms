-- ============================================================================
-- Vedomost topshirish — o'zak guruh × o'zak fan birlashtirish DIAGNOSTIKASI
-- MySQL / MariaDB (8.0+ / 10.0.5+) — REGEXP_REPLACE kerak.
--
-- Maqsad: bazadagi guruh/fan nomlari REAL formatini ko'rib, birlashtirish
-- qoidasi to'g'ri ishlashini tekshirish (lotin/kirill, qavsli "(a)" yoki
-- bevosita oxirgi harf "...01a").
--
-- O'zak qoidalar (PHP VedomostMergeService bilan AYNAN bir xil):
--   GROUP: avval " (a)" qavsli variant, keyin raqamdan keyingi bevosita harf kesiladi
--   SUBJECT: oxirgi " (a)" / "(1)" qavsli variant kesiladi
-- ============================================================================


-- ---------------------------------------------------------------------------
-- 1) Barcha guruh nomlari va ularning o'zagi (faqat o'zgaganlarini ko'rish oson)
-- ---------------------------------------------------------------------------
SELECT
    group_name,
    REGEXP_REPLACE(
        REGEXP_REPLACE(group_name, ' *\\([A-Za-zА-Яа-яёЁ]\\) *$', ''),
        '(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', ''
    ) AS root_group,
    COUNT(*) AS yozuvlar
FROM vedomost_submissions
GROUP BY group_name
ORDER BY group_name;


-- ---------------------------------------------------------------------------
-- 2) FAQAT qo'shimchasi bor guruhlar (kesilganda o'zgargan) — formatni tasdiqlash
-- ---------------------------------------------------------------------------
SELECT DISTINCT
    group_name,
    REGEXP_REPLACE(
        REGEXP_REPLACE(group_name, ' *\\([A-Za-zА-Яа-яёЁ]\\) *$', ''),
        '(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', ''
    ) AS root_group
FROM vedomost_submissions
WHERE group_name <> REGEXP_REPLACE(
        REGEXP_REPLACE(group_name, ' *\\([A-Za-zА-Яа-яёЁ]\\) *$', ''),
        '(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', '')
ORDER BY group_name;


-- ---------------------------------------------------------------------------
-- 3) FAQAT qo'shimchasi bor fanlar (kesilganda o'zgargan) — formatni tasdiqlash
-- ---------------------------------------------------------------------------
SELECT DISTINCT
    subject_name,
    REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '') AS root_subject
FROM vedomost_submissions
WHERE subject_name <> REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '')
ORDER BY subject_name;


-- ---------------------------------------------------------------------------
-- 4) BIRLASHTIRISH KO'RINISHI (eng muhim) — qaysi yozuvlar bitta vedomostga
--    jamlanadi va o'qituvchilar qanday birlashadi. PHP merge kaliti bilan bir xil:
--    education_year | semester_code | specialty_name | closing_form | root_group | root_subject
--    Faqat 1 tadan ko'p guruhcha/variant bor (= haqiqatan birlashadigan) qatorlar.
-- ---------------------------------------------------------------------------
SELECT
    education_year,
    semester_code,
    specialty_name,
    closing_form,
    REGEXP_REPLACE(
        REGEXP_REPLACE(group_name, ' *\\([A-Za-zА-Яа-яёЁ]\\) *$', ''),
        '(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', ''
    ) AS root_group,
    REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '') AS root_subject,
    COUNT(*) AS guruhcha_soni,
    GROUP_CONCAT(DISTINCT group_name   ORDER BY group_name   SEPARATOR ', ') AS guruhchalar,
    GROUP_CONCAT(DISTINCT subject_name ORDER BY subject_name SEPARATOR ', ') AS fan_variantlari,
    GROUP_CONCAT(DISTINCT teacher_name ORDER BY teacher_name SEPARATOR ', ') AS oqituvchilar
FROM vedomost_submissions
GROUP BY education_year, semester_code, specialty_name, closing_form, root_group, root_subject
HAVING guruhcha_soni > 1
ORDER BY guruhcha_soni DESC, root_group, root_subject;


-- ---------------------------------------------------------------------------
-- 5) YIG'MA SON: birlashtirishdan oldin/keyin nechta qator bo'lishi
-- ---------------------------------------------------------------------------
SELECT
    COUNT(*) AS yozuvlar_jami,
    COUNT(DISTINCT CONCAT_WS('|',
        education_year, semester_code, specialty_name, closing_form,
        REGEXP_REPLACE(
            REGEXP_REPLACE(group_name, ' *\\([A-Za-zА-Яа-яёЁ]\\) *$', ''),
            '(?<=[0-9])[A-Za-zА-Яа-яёЁ]$', ''),
        REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '')
    )) AS ozak_vedomostlar
FROM vedomost_submissions;
