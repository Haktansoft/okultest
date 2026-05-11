-- =============================================================
-- Migration: Sorulara ayrı ses medyası (görselin yanı sıra)
-- Tarih: 2026-05-11
-- Idempotent.
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır → Çalıştır.
-- =============================================================

-- questions.prompt_audio_id (varsa atla)
SET @x := (SELECT COUNT(*) FROM information_schema.COLUMNS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions' AND COLUMN_NAME = 'prompt_audio_id');
SET @sql := IF(@x = 0,
  'ALTER TABLE questions ADD COLUMN prompt_audio_id BIGINT UNSIGNED NULL AFTER prompt_media_id',
  'SELECT "prompt_audio_id zaten var" AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

-- FK
SET @x := (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
           WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'questions'
             AND CONSTRAINT_NAME = 'fk_questions_paudio');
SET @sql := IF(@x = 0,
  'ALTER TABLE questions ADD CONSTRAINT fk_questions_paudio FOREIGN KEY (prompt_audio_id) REFERENCES media(id) ON DELETE SET NULL',
  'SELECT "fk_questions_paudio zaten var" AS info');
PREPARE s FROM @sql; EXECUTE s; DEALLOCATE PREPARE s;

SELECT 'questions.prompt_audio_id' AS info, COUNT(*) AS toplam FROM questions;
