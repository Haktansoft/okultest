-- =============================================================
-- Migration: Kurum, Kampüs, Sınıf yapısı
-- Tarih: 2026-05-09
-- Amaç:
--   * institutions, campuses, classrooms tabloları
--   * users tablosuna campus_id ve classroom_id kolonları
--   * mevcut tüm öğretmen/öğrenciyi "Ana Kurum / Ana Kampüs" altına al
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır → Çalıştır.
-- =============================================================

CREATE TABLE IF NOT EXISTS institutions (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  name VARCHAR(150) NOT NULL,
  logo_media_id BIGINT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uniq_inst_name (name),
  KEY idx_inst_logo (logo_media_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS campuses (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  institution_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_camp_inst (institution_id),
  UNIQUE KEY uniq_camp_name_per_inst (institution_id, name),
  CONSTRAINT fk_camp_inst FOREIGN KEY (institution_id) REFERENCES institutions(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS classrooms (
  id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  campus_id BIGINT UNSIGNED NOT NULL,
  name VARCHAR(150) NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_class_campus (campus_id),
  UNIQUE KEY uniq_class_name_per_camp (campus_id, name),
  CONSTRAINT fk_class_campus FOREIGN KEY (campus_id) REFERENCES campuses(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- users'a kolonlar
ALTER TABLE users
  ADD COLUMN campus_id BIGINT UNSIGNED NULL AFTER section,
  ADD COLUMN classroom_id BIGINT UNSIGNED NULL AFTER campus_id,
  ADD KEY idx_users_campus (campus_id),
  ADD KEY idx_users_classroom (classroom_id);

-- Mevcut hesapları "Ana Kurum / Ana Kampüs" altına taşı
INSERT INTO institutions (name) VALUES ('Ana Kurum')
  ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @inst_id = LAST_INSERT_ID();

INSERT INTO campuses (institution_id, name) VALUES (@inst_id, 'Ana Kampüs')
  ON DUPLICATE KEY UPDATE id = LAST_INSERT_ID(id);
SET @camp_id = LAST_INSERT_ID();

UPDATE users
   SET campus_id = @camp_id
 WHERE role IN ('teacher','student') AND campus_id IS NULL;

-- Doğrula
SELECT 'institutions' AS t, COUNT(*) AS c FROM institutions
UNION ALL SELECT 'campuses', COUNT(*) FROM campuses
UNION ALL SELECT 'classrooms', COUNT(*) FROM classrooms
UNION ALL SELECT 'users_with_campus', COUNT(*) FROM users WHERE campus_id IS NOT NULL;
