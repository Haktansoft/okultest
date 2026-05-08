-- =============================================================
-- Migration: Sınıf yapısı (Sınıf + Şube), öğretmen-sınıf ataması
-- Tarih: 2026-05-09
-- Idempotent: tekrar çalıştırılırsa "Duplicate column" hatası vermez.
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır → Çalıştır.
-- =============================================================

-- 1) classrooms.grade_level (varsa atla)
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classrooms' AND COLUMN_NAME = 'grade_level');
SET @sql := IF(@x = 0,
  'ALTER TABLE classrooms ADD COLUMN grade_level VARCHAR(20) NULL AFTER campus_id',
  'SELECT "grade_level zaten var" AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 2) classrooms.section (varsa atla)
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'classrooms' AND COLUMN_NAME = 'section');
SET @sql := IF(@x = 0,
  'ALTER TABLE classrooms ADD COLUMN section VARCHAR(10) NULL AFTER grade_level',
  'SELECT "section zaten var" AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 3) teacher_classrooms tablosu
CREATE TABLE IF NOT EXISTS teacher_classrooms (
  teacher_id BIGINT UNSIGNED NOT NULL,
  classroom_id BIGINT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (teacher_id, classroom_id),
  KEY idx_tc_teacher (teacher_id),
  KEY idx_tc_classroom (classroom_id),
  CONSTRAINT fk_tc_teacher FOREIGN KEY (teacher_id) REFERENCES users(id) ON DELETE CASCADE,
  CONSTRAINT fk_tc_classroom FOREIGN KEY (classroom_id) REFERENCES classrooms(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Doğrula
SELECT 'classrooms' AS t, COUNT(*) AS c FROM classrooms
UNION ALL SELECT 'teacher_classrooms', COUNT(*) FROM teacher_classrooms;
