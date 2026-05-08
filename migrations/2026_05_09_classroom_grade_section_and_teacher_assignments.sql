-- =============================================================
-- Migration: Sınıf yapısı (Sınıf + Şube), öğretmen-sınıf ataması
-- Tarih: 2026-05-09
-- Amaç:
--   * classrooms tablosuna grade_level, section sütunları
--   * teacher_classrooms join tablosu (öğretmenlere sınıf ataması)
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır.
-- =============================================================

ALTER TABLE classrooms
  ADD COLUMN grade_level VARCHAR(20) NULL AFTER campus_id,
  ADD COLUMN section VARCHAR(10) NULL AFTER grade_level;

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

SELECT 'classrooms' AS t, COUNT(*) AS c FROM classrooms
UNION ALL SELECT 'teacher_classrooms', COUNT(*) FROM teacher_classrooms;
