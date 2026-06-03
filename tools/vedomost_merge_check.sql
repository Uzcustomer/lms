-- ============================================================================
-- Vedomost topshirish — o'zak guruh × o'zak fan birlashtirish DIAGNOSTIKASI
-- MySQL / MariaDB (8.0+ / 10.0.5+) — REGEXP_SUBSTR / REGEXP_REPLACE kerak.
-- Hammasi READ-ONLY (faqat SELECT).
--
-- MUHIM: faqat FAOL guruhlar. vedomost_submissions `groups` bilan bog'lanib,
--        g.active = 1 bo'lganlari olinadi. (`groups` — MySQL reserved so'z → backtick.)
--
-- O'ZAK QOIDALAR (PHP VedomostMergeService bilan AYNAN bir xil):
--   GROUP : o'zak = nom boshidan birinchi "...NN-NN" gacha. Undan keyingi BUTUN
--           quyruq (variant harfi a/b/c/с, til tegi (ang)/(rus)/(ing), o'lcham
--           tegi (2 talik guruh)/(3 talik), qavsli " (a)") tashlanadi.
--           d1/21-09a (ang) -> d1/21-09   |   d1/22-01 (b) -> d1/22-01
--   SUBJECT: oxirgi " (a)" / "(1)" qavsli VARIANT (bitta belgi) kesiladi.
--
--   <RG> = COALESCE(REGEXP_SUBSTR(group_name, '^.*?[0-9]+-[0-9]+'), group_name)
--   <RS> = REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '')
-- ============================================================================


-- ---------------------------------------------------------------------------
-- 1) Barcha (faol) guruhlar va ularning o'zagi
-- ---------------------------------------------------------------------------
SELECT
    group_name,
    COALESCE(REGEXP_SUBSTR(group_name, '^.*?[0-9]+-[0-9]+'), group_name) AS root_group,
    COUNT(*) AS yozuvlar
FROM vedomost_submissions vs
JOIN `groups` g ON g.group_hemis_id = vs.group_hemis_id AND g.active = 1
GROUP BY group_name
ORDER BY group_name;


-- ---------------------------------------------------------------------------
-- 2) GURUH "quyrug'i" — o'zakdan keyingi BUTUN qism (til/o'lcham tegi + harf).
--    Noyob variantlar: "a", "b", "c", "с", "(a)", "a (ang)", "b (rus)" ...
-- ---------------------------------------------------------------------------
SELECT
    REGEXP_REPLACE(group_name, '^.*?[0-9]+-[0-9]+', '') AS guruh_quyruq,
    COUNT(*)                   AS nechta_yozuv,
    COUNT(DISTINCT group_name) AS nechta_guruh
FROM vedomost_submissions vs
JOIN `groups` g ON g.group_hemis_id = vs.group_hemis_id AND g.active = 1
GROUP BY guruh_quyruq
ORDER BY nechta_yozuv DESC;


-- ---------------------------------------------------------------------------
-- 3) FAN nomidagi har qanday oxirgi qavs (1 yoki ko'p harfli)
-- ---------------------------------------------------------------------------
SELECT
    REGEXP_SUBSTR(subject_name, '\\([^)]*\\) *$') AS fan_oxirgi_qavs,
    COUNT(*)                     AS nechta_yozuv,
    COUNT(DISTINCT subject_name) AS nechta_fan
FROM vedomost_submissions vs
JOIN `groups` g ON g.group_hemis_id = vs.group_hemis_id AND g.active = 1
WHERE subject_name REGEXP '\\([^)]*\\) *$'
GROUP BY fan_oxirgi_qavs
ORDER BY nechta_yozuv DESC;


-- ---------------------------------------------------------------------------
-- 4) BIRLASHTIRISH KO'RINISHI (eng muhim) — qaysi (faol) yozuvlar bitta
--    vedomostga jamlanadi va o'qituvchilar qanday birlashadi.
--    Merge kaliti: education_year | semester_code | specialty_name | closing_form
--                  | root_group | root_subject.   Faqat 1 tadan ko'p qatorlar.
-- ---------------------------------------------------------------------------
SELECT
    education_year,
    semester_code,
    specialty_name,
    closing_form,
    COALESCE(REGEXP_SUBSTR(group_name, '^.*?[0-9]+-[0-9]+'), group_name) AS root_group,
    REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '')    AS root_subject,
    COUNT(*) AS guruhcha_soni,
    GROUP_CONCAT(DISTINCT group_name   ORDER BY group_name   SEPARATOR ', ') AS guruhchalar,
    GROUP_CONCAT(DISTINCT subject_name ORDER BY subject_name SEPARATOR ', ') AS fan_variantlari,
    GROUP_CONCAT(DISTINCT teacher_name ORDER BY teacher_name SEPARATOR ', ') AS oqituvchilar
FROM vedomost_submissions vs
JOIN `groups` g ON g.group_hemis_id = vs.group_hemis_id AND g.active = 1
GROUP BY education_year, semester_code, specialty_name, closing_form, root_group, root_subject
HAVING guruhcha_soni > 1
ORDER BY guruhcha_soni DESC, root_group, root_subject;


-- ---------------------------------------------------------------------------
-- 5) YIG'MA SON: birlashtirishdan oldin/keyin nechta qator (faqat faol)
-- ---------------------------------------------------------------------------
SELECT
    COUNT(*) AS yozuvlar_jami,
    COUNT(DISTINCT CONCAT_WS('|',
        education_year, semester_code, specialty_name, closing_form,
        COALESCE(REGEXP_SUBSTR(group_name, '^.*?[0-9]+-[0-9]+'), group_name),
        REGEXP_REPLACE(subject_name, ' *\\([A-Za-zА-Яа-яёЁ0-9]\\) *$', '')
    )) AS ozak_vedomostlar
FROM vedomost_submissions vs
JOIN `groups` g ON g.group_hemis_id = vs.group_hemis_id AND g.active = 1;
