-- =============================================
-- Student Admission Data — barcha migratsiyalar
-- Serverda phpmyadmin yoki mysql console da ishga tushiring
-- =============================================

-- 1. Asosiy jadval yaratish (agar mavjud bo'lmasa)
CREATE TABLE IF NOT EXISTS `student_admission_data` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `student_id` bigint unsigned NOT NULL,

    -- Shaxsiy
    `familya` varchar(255) DEFAULT NULL,
    `ism` varchar(255) DEFAULT NULL,
    `otasining_ismi` varchar(255) DEFAULT NULL,
    `tugilgan_sana` date DEFAULT NULL,
    `jshshir` varchar(14) DEFAULT NULL,
    `jinsi` varchar(10) DEFAULT NULL,
    `tel1` varchar(20) DEFAULT NULL,
    `tel2` varchar(20) DEFAULT NULL,
    `email` varchar(255) DEFAULT NULL,
    `millat` varchar(255) DEFAULT NULL,

    -- Tug'ilgan joy
    `tugilgan_davlat` varchar(255) DEFAULT NULL,
    `tugilgan_viloyat` varchar(255) DEFAULT NULL,
    `tugulgan_tuman` varchar(255) DEFAULT NULL,

    -- Manzil
    `doimiy_manzil` varchar(255) DEFAULT NULL,
    `yashash_davlat` varchar(255) DEFAULT NULL,
    `yashash_viloyat` varchar(255) DEFAULT NULL,
    `yashash_tuman` varchar(255) DEFAULT NULL,
    `yashash_manzil` varchar(255) DEFAULT NULL,
    `vaqtinchalik_manzil` varchar(255) DEFAULT NULL,

    -- Pasport
    `passport_seriya` varchar(10) DEFAULT NULL,
    `passport_raqam` varchar(10) DEFAULT NULL,
    `passport_sana` date DEFAULT NULL,
    `passport_joy` varchar(255) DEFAULT NULL,

    -- Qabul
    `abituriyent_id` varchar(20) DEFAULT NULL,
    `javoblar_varaqasi` varchar(20) DEFAULT NULL,
    `talim_tili` varchar(20) DEFAULT NULL,
    `imtihon_alifbosi` varchar(10) DEFAULT NULL,

    -- Ta'lim
    `oliy_malumot` varchar(255) DEFAULT NULL,
    `otm_nomi` varchar(255) DEFAULT NULL,
    `talim_turi` varchar(255) DEFAULT NULL,
    `talim_shakli` varchar(255) DEFAULT NULL,
    `mutaxassislik` varchar(255) DEFAULT NULL,
    `hozirgi_talim_turi` varchar(20) DEFAULT NULL,
    `toplagan_ball` varchar(255) DEFAULT NULL,
    `tavsiya_turi` varchar(255) DEFAULT NULL,
    `tolov_shakli` varchar(255) DEFAULT NULL,
    `muassasa_nomi` varchar(255) DEFAULT NULL,
    `hujjat_seriya` varchar(255) DEFAULT NULL,
    `ortalacha_ball` varchar(255) DEFAULT NULL,
    `talim_davlat` varchar(255) DEFAULT NULL,
    `talim_viloyat` varchar(255) DEFAULT NULL,
    `talim_tuman` varchar(255) DEFAULT NULL,
    `oqigan_yili_boshi` varchar(4) DEFAULT NULL,
    `oqigan_yili_tugashi` varchar(4) DEFAULT NULL,

    -- Sertifikatlar
    `sertifikat_turi` varchar(255) DEFAULT NULL,
    `sertifikat_ball` varchar(255) DEFAULT NULL,
    `milliy_sertifikat` varchar(255) DEFAULT NULL,
    `chet_til_sertifikat` varchar(30) DEFAULT NULL,
    `chet_til_ball` varchar(10) DEFAULT NULL,

    -- Ota
    `ota_familiya` varchar(255) DEFAULT NULL,
    `ota_ismi` varchar(255) DEFAULT NULL,
    `ota_sharifi` varchar(255) DEFAULT NULL,
    `ota_tel` varchar(20) DEFAULT NULL,
    `ota_ish_joyi` varchar(255) DEFAULT NULL,
    `ota_lavozimi` varchar(255) DEFAULT NULL,

    -- Ona
    `ona_familiya` varchar(255) DEFAULT NULL,
    `ona_ismi` varchar(255) DEFAULT NULL,
    `ona_sharifi` varchar(255) DEFAULT NULL,
    `ona_tel` varchar(20) DEFAULT NULL,
    `ona_ish_joyi` varchar(255) DEFAULT NULL,
    `ona_lavozimi` varchar(255) DEFAULT NULL,

    `updated_by` bigint unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,

    PRIMARY KEY (`id`),
    UNIQUE KEY `student_admission_data_student_id_unique` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Agar jadval allaqachon bor bo'lsa, yangi ustunlarni qo'shish
-- (har birida IF NOT EXISTS tekshiruvi bor, xavfsiz)

-- vaqtinchalik_manzil
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='vaqtinchalik_manzil');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `vaqtinchalik_manzil` varchar(255) DEFAULT NULL AFTER `yashash_manzil`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- talim_davlat
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='talim_davlat');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `talim_davlat` varchar(255) DEFAULT NULL AFTER `ortalacha_ball`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- talim_viloyat
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='talim_viloyat');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `talim_viloyat` varchar(255) DEFAULT NULL AFTER `talim_davlat`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- talim_tuman
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='talim_tuman');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `talim_tuman` varchar(255) DEFAULT NULL AFTER `talim_viloyat`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- oqigan_yili_boshi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='oqigan_yili_boshi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `oqigan_yili_boshi` varchar(4) DEFAULT NULL AFTER `talim_tuman`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- oqigan_yili_tugashi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='oqigan_yili_tugashi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `oqigan_yili_tugashi` varchar(4) DEFAULT NULL AFTER `oqigan_yili_boshi`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- hozirgi_talim_turi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='hozirgi_talim_turi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `hozirgi_talim_turi` varchar(20) DEFAULT NULL AFTER `mutaxassislik`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- abituriyent_id
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='abituriyent_id');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `abituriyent_id` varchar(20) DEFAULT NULL AFTER `passport_joy`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- javoblar_varaqasi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='javoblar_varaqasi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `javoblar_varaqasi` varchar(20) DEFAULT NULL AFTER `abituriyent_id`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- talim_tili
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='talim_tili');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `talim_tili` varchar(20) DEFAULT NULL AFTER `javoblar_varaqasi`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- imtihon_alifbosi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='imtihon_alifbosi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `imtihon_alifbosi` varchar(10) DEFAULT NULL AFTER `talim_tili`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- tavsiya_turi
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='tavsiya_turi');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `tavsiya_turi` varchar(255) DEFAULT NULL AFTER `toplagan_ball`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- chet_til_sertifikat
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='chet_til_sertifikat');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `chet_til_sertifikat` varchar(30) DEFAULT NULL AFTER `milliy_sertifikat`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- chet_til_ball
SET @col_exists = (SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='student_admission_data' AND COLUMN_NAME='chet_til_ball');
SET @sql = IF(@col_exists=0, 'ALTER TABLE student_admission_data ADD COLUMN `chet_til_ball` varchar(10) DEFAULT NULL AFTER `chet_til_sertifikat`', 'SELECT 1');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- 3. student_files jadvali (agar mavjud bo'lmasa)
CREATE TABLE IF NOT EXISTS `student_files` (
    `id` bigint unsigned NOT NULL AUTO_INCREMENT,
    `student_id` bigint unsigned NOT NULL,
    `name` varchar(255) NOT NULL,
    `original_name` varchar(255) NOT NULL,
    `path` varchar(255) NOT NULL,
    `mime_type` varchar(255) DEFAULT NULL,
    `size` bigint unsigned NOT NULL DEFAULT 0,
    `uploaded_by` bigint unsigned DEFAULT NULL,
    `created_at` timestamp NULL DEFAULT NULL,
    `updated_at` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `student_files_student_id_foreign` (`student_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tekshirish
SELECT 'Jadvallar tayyor!' AS status;
DESCRIBE student_admission_data;
