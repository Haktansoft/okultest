-- =============================================================
-- Migration (REVERT): Sınıf entity'sini kaldır
-- Tarih: 2026-05-09
-- Amaç:
--   * users.classroom_id'deki sınıf bilgilerini grade_level/section'a taşı
--   * teacher_classrooms ve classrooms tablolarını sil
--   * users.classroom_id kolonunu sil
-- Idempotent.
-- =============================================================

-- 1) classroom verilerini öğrencinin grade_level/section kolonlarına taşı (varsa)
UPDATE users u
  LEFT JOIN classrooms cr ON cr.id = u.classroom_id
   SET u.grade_level = COALESCE(cr.grade_level, u.grade_level),
       u.section     = COALESCE(cr.section, u.section)
 WHERE u.role = 'student' AND u.classroom_id IS NOT NULL;

-- 2) teacher_classrooms tablosunu sil
DROP TABLE IF EXISTS teacher_classrooms;

-- 3) users.classroom_id kolonunu (varsa) sil
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'classroom_id');
SET @sql := IF(@x > 0,
  'ALTER TABLE users DROP COLUMN classroom_id',
  'SELECT "users.classroom_id zaten yok" AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- 4) classrooms tablosunu sil
DROP TABLE IF EXISTS classrooms;

-- Doğrula
SELECT id, role, full_name, grade_level, section, campus_id
  FROM users WHERE role='student' ORDER BY full_name LIMIT 20;
