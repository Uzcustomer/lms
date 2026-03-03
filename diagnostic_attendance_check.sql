-- ============================================================================
-- DIAGNOSTIKA: ABULFAYZOV Z. I. — 25.02.2026 dars davomat tekshirish
-- Bu SQL zaproslarni production serverda ketma-ket bajaring
-- ============================================================================

-- ============================================================================
-- 1-QADAM: Schedules jadvalidan dars yozuvini topish
-- ============================================================================
SELECT
    sch.id,
    sch.schedule_hemis_id,
    sch.employee_id,
    sch.employee_name,
    sch.subject_id,
    sch.subject_name,
    sch.group_id,
    sch.group_name,
    sch.training_type_code,
    sch.training_type_name,
    sch.lesson_pair_code,
    sch.lesson_pair_start_time,
    sch.lesson_pair_end_time,
    sch.lesson_date,
    sch.semester_code,
    sch.department_name,
    sch.deleted_at
FROM schedules sch
WHERE sch.group_name LIKE '%d2/22-04a%'
  AND DATE(sch.lesson_date) = '2026-02-25'
  AND sch.training_type_name LIKE '%maliy%'
  AND sch.lesson_pair_start_time = '08:30:00'
  AND sch.deleted_at IS NULL;

-- ============================================================================
-- 2-QADAM: Attendance controls jadvalidan shu darsga tegishli yozuvlarni izlash
-- subject_schedule_id orqali (1-usul — asosiy)
-- ============================================================================
SELECT
    ac.id,
    ac.hemis_id,
    ac.subject_schedule_id,
    ac.employee_id,
    ac.employee_name,
    ac.subject_id,
    ac.subject_name,
    ac.group_id,
    ac.group_name,
    ac.training_type_code,
    ac.training_type_name,
    ac.lesson_pair_code,
    ac.lesson_pair_start_time,
    ac.lesson_pair_end_time,
    ac.lesson_date,
    ac.load,
    ac.is_final,
    ac.deleted_at,
    ac.created_at,
    ac.updated_at
FROM attendance_controls ac
WHERE ac.subject_schedule_id IN (
    SELECT sch.schedule_hemis_id
    FROM schedules sch
    WHERE sch.group_name LIKE '%d2/22-04a%'
      AND DATE(sch.lesson_date) = '2026-02-25'
      AND sch.training_type_name LIKE '%maliy%'
      AND sch.lesson_pair_start_time = '08:30:00'
      AND sch.deleted_at IS NULL
);

-- ============================================================================
-- 3-QADAM: Attendance controls — shu guruh, sana, mashg'ulot turi bo'yicha
-- barcha yozuvlar (soft-deleted ham, subject_schedule_id farq qilsa ham)
-- Bu 2-usul tekshirish va umumiy holatni ko'rish uchun
-- ============================================================================
SELECT
    ac.id,
    ac.hemis_id,
    ac.subject_schedule_id,
    ac.employee_id,
    ac.employee_name,
    ac.subject_id,
    ac.subject_name,
    ac.group_id,
    ac.group_name,
    ac.training_type_code,
    ac.training_type_name,
    ac.lesson_pair_code,
    ac.lesson_pair_start_time,
    ac.lesson_date,
    ac.load,
    ac.is_final,
    ac.deleted_at
FROM attendance_controls ac
WHERE ac.group_name LIKE '%d2/22-04a%'
  AND DATE(ac.lesson_date) = '2026-02-25';

-- ============================================================================
-- 4-QADAM: MUHIM — schedule_hemis_id va subject_schedule_id solishtiruv
-- Agar attendance_controls da yozuv bor lekin subject_schedule_id farq qilsa,
-- 1-usul topmasligi mumkin
-- ============================================================================
SELECT
    'SCHEDULE' as source,
    sch.schedule_hemis_id as hemis_id_or_schedule_id,
    sch.employee_id,
    sch.employee_name,
    sch.subject_id,
    sch.group_id,
    sch.group_name,
    sch.training_type_code,
    sch.lesson_pair_code,
    DATE(sch.lesson_date) as lesson_date,
    NULL as load_value,
    sch.deleted_at
FROM schedules sch
WHERE sch.group_name LIKE '%d2/22-04a%'
  AND DATE(sch.lesson_date) = '2026-02-25'
  AND sch.training_type_name LIKE '%maliy%'

UNION ALL

SELECT
    'ATT_CTRL' as source,
    ac.subject_schedule_id as hemis_id_or_schedule_id,
    ac.employee_id,
    ac.employee_name,
    ac.subject_id,
    ac.group_id,
    ac.group_name,
    ac.training_type_code,
    ac.lesson_pair_code,
    DATE(ac.lesson_date) as lesson_date,
    ac.load as load_value,
    ac.deleted_at
FROM attendance_controls ac
WHERE ac.group_name LIKE '%d2/22-04a%'
  AND DATE(ac.lesson_date) = '2026-02-25';

-- ============================================================================
-- 5-QADAM: Report logikasini simulyatsiya qilish
-- Aynan hisobot qanday tekshirayotganini ko'rsatadi
-- ============================================================================
SELECT
    sch.schedule_hemis_id,
    sch.employee_name,
    sch.group_name,
    sch.subject_name,
    sch.training_type_name,
    CONCAT(SUBSTRING(sch.lesson_pair_start_time, 1, 5), '-', SUBSTRING(sch.lesson_pair_end_time, 1, 5)) as pair_time,
    DATE(sch.lesson_date) as lesson_date,

    -- 1-USUL: subject_schedule_id orqali
    (SELECT COUNT(*)
     FROM attendance_controls ac1
     WHERE ac1.deleted_at IS NULL
       AND ac1.subject_schedule_id = sch.schedule_hemis_id
       AND ac1.load > 0
    ) as usul1_topildi,

    -- 2-USUL: composite key orqali
    (SELECT COUNT(*)
     FROM attendance_controls ac2
     WHERE ac2.deleted_at IS NULL
       AND ac2.employee_id = sch.employee_id
       AND ac2.group_id = sch.group_id
       AND ac2.subject_id = sch.subject_id
       AND DATE(ac2.lesson_date) = DATE(sch.lesson_date)
       AND ac2.training_type_code = sch.training_type_code
       AND ac2.lesson_pair_code = sch.lesson_pair_code
       AND ac2.load > 0
    ) as usul2_topildi,

    -- Soft-deleted yozuv bormi?
    (SELECT COUNT(*)
     FROM attendance_controls ac3
     WHERE ac3.deleted_at IS NOT NULL
       AND ac3.subject_schedule_id = sch.schedule_hemis_id
    ) as soft_deleted_bor,

    -- Load = 0 yozuv bormi?
    (SELECT ac4.load
     FROM attendance_controls ac4
     WHERE ac4.deleted_at IS NULL
       AND ac4.subject_schedule_id = sch.schedule_hemis_id
     LIMIT 1
    ) as actual_load,

    -- NATIJA: hisobot nima ko'rsatadi
    CASE
        WHEN EXISTS (
            SELECT 1 FROM attendance_controls ac5
            WHERE ac5.deleted_at IS NULL
              AND ac5.subject_schedule_id = sch.schedule_hemis_id
              AND ac5.load > 0
        ) THEN 'Ha (1-usul)'
        WHEN EXISTS (
            SELECT 1 FROM attendance_controls ac6
            WHERE ac6.deleted_at IS NULL
              AND ac6.employee_id = sch.employee_id
              AND ac6.group_id = sch.group_id
              AND ac6.subject_id = sch.subject_id
              AND DATE(ac6.lesson_date) = DATE(sch.lesson_date)
              AND ac6.training_type_code = sch.training_type_code
              AND ac6.lesson_pair_code = sch.lesson_pair_code
              AND ac6.load > 0
        ) THEN 'Ha (2-usul)'
        ELSE 'Yo''q — MUAMMO SHU YERDA!'
    END as hisobot_natijasi

FROM schedules sch
WHERE sch.group_name LIKE '%d2/22-04a%'
  AND DATE(sch.lesson_date) = '2026-02-25'
  AND sch.deleted_at IS NULL
  AND sch.training_type_name LIKE '%maliy%'
  AND sch.lesson_pair_start_time = '08:30:00';

-- ============================================================================
-- 6-QADAM: HEMIS API bilan solishtirish uchun — schedule_hemis_id ni oling
-- va HEMIS API ga quyidagi URL bilan so'rov yuboring:
--
-- GET https://student.ttatf.uz/rest/v1/data/attendance-control-list
--     ?lesson_date_from=UNIX_TIMESTAMP
--     &lesson_date_to=UNIX_TIMESTAMP
--     &limit=200&page=1
--
-- 25.02.2026 uchun:
--   lesson_date_from = 1740434400 (2026-02-25 00:00:00 UTC)
--   lesson_date_to   = 1740520799 (2026-02-25 23:59:59 UTC)
--
-- HEMIS API javobida _subject_schedule qiymatini tekshiring:
--   - Bu qiymat schedules.schedule_hemis_id ga teng bo'lishi kerak
--   - Agar farq qilsa — import qilganda noto'g'ri yozilgan
-- ============================================================================

-- 25.02.2026 Unix timestamps (UTC):
SELECT
    UNIX_TIMESTAMP('2026-02-25 00:00:00') as lesson_date_from,
    UNIX_TIMESTAMP('2026-02-25 23:59:59') as lesson_date_to;

-- ============================================================================
-- 7-QADAM: Oxirgi import qachon bo'lganini tekshirish
-- ============================================================================
SELECT
    MAX(ac.updated_at) as oxirgi_yangilangan,
    MAX(ac.created_at) as oxirgi_yaratilgan,
    COUNT(*) as jami_yozuvlar,
    SUM(CASE WHEN ac.deleted_at IS NULL THEN 1 ELSE 0 END) as faol_yozuvlar,
    SUM(CASE WHEN ac.deleted_at IS NOT NULL THEN 1 ELSE 0 END) as ochirilgan_yozuvlar
FROM attendance_controls ac
WHERE DATE(ac.lesson_date) = '2026-02-25';

-- ============================================================================
-- 8-QADAM: Ehtimoliy sabablar diagnostikasi
-- Bu zapros barcha mumkin bo'lgan muammo sabablarini tekshiradi
-- ============================================================================
SELECT
    'attendance_controls da yozuv umuman mavjud emas' as muammo,
    CASE WHEN (
        SELECT COUNT(*) FROM attendance_controls
        WHERE group_name LIKE '%d2/22-04a%'
          AND DATE(lesson_date) = '2026-02-25'
    ) = 0 THEN 'HA — yozuv yo''q, import qilinmagan!'
    ELSE 'Yo''q — yozuv mavjud'
    END as holat

UNION ALL

SELECT
    'Yozuv bor lekin soft-deleted (deleted_at IS NOT NULL)' as muammo,
    CASE WHEN (
        SELECT COUNT(*) FROM attendance_controls
        WHERE group_name LIKE '%d2/22-04a%'
          AND DATE(lesson_date) = '2026-02-25'
          AND deleted_at IS NOT NULL
    ) > 0 THEN 'HA — yozuv soft-delete qilingan!'
    ELSE 'Yo''q'
    END as holat

UNION ALL

SELECT
    'Yozuv bor lekin load = 0' as muammo,
    CASE WHEN (
        SELECT COUNT(*) FROM attendance_controls
        WHERE group_name LIKE '%d2/22-04a%'
          AND DATE(lesson_date) = '2026-02-25'
          AND deleted_at IS NULL
          AND load = 0
    ) > 0 THEN 'HA — load nol!'
    ELSE 'Yo''q'
    END as holat

UNION ALL

SELECT
    'subject_schedule_id schedule_hemis_id ga mos kelmayapti' as muammo,
    CASE WHEN (
        SELECT COUNT(*) FROM attendance_controls ac
        WHERE ac.group_name LIKE '%d2/22-04a%'
          AND DATE(ac.lesson_date) = '2026-02-25'
          AND ac.deleted_at IS NULL
          AND ac.load > 0
          AND ac.subject_schedule_id NOT IN (
              SELECT sch.schedule_hemis_id FROM schedules sch
              WHERE sch.group_name LIKE '%d2/22-04a%'
                AND DATE(sch.lesson_date) = '2026-02-25'
                AND sch.deleted_at IS NULL
          )
    ) > 0 THEN 'HA — ID mos kelmayapti! Bu asosiy muammo bo''lishi mumkin'
    ELSE 'Yo''q — IDlar mos'
    END as holat

UNION ALL

SELECT
    'employee_id farq qiladi (schedule vs attendance_controls)' as muammo,
    CASE WHEN EXISTS (
        SELECT 1 FROM attendance_controls ac
        JOIN schedules sch ON sch.group_name LIKE '%d2/22-04a%'
            AND DATE(sch.lesson_date) = '2026-02-25'
            AND sch.deleted_at IS NULL
        WHERE ac.group_name LIKE '%d2/22-04a%'
          AND DATE(ac.lesson_date) = '2026-02-25'
          AND ac.deleted_at IS NULL
          AND ac.employee_id != sch.employee_id
          AND ac.subject_id = sch.subject_id
          AND ac.training_type_code = sch.training_type_code
          AND ac.lesson_pair_code = sch.lesson_pair_code
        LIMIT 1
    ) THEN 'HA — boshqa o''qituvchi ID si!'
    ELSE 'Yo''q'
    END as holat

UNION ALL

SELECT
    'training_type_code farq qiladi' as muammo,
    CONCAT(
        'Schedule: ',
        COALESCE((SELECT GROUP_CONCAT(DISTINCT sch.training_type_code)
         FROM schedules sch
         WHERE sch.group_name LIKE '%d2/22-04a%'
           AND DATE(sch.lesson_date) = '2026-02-25'
           AND sch.deleted_at IS NULL
           AND sch.lesson_pair_start_time = '08:30:00'), 'null'),
        ' | AttCtrl: ',
        COALESCE((SELECT GROUP_CONCAT(DISTINCT ac.training_type_code)
         FROM attendance_controls ac
         WHERE ac.group_name LIKE '%d2/22-04a%'
           AND DATE(ac.lesson_date) = '2026-02-25'
           AND ac.deleted_at IS NULL), 'null')
    ) as holat;
