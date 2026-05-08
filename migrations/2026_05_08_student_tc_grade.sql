-- =============================================================
-- Migration: öğrenciye T.C. Kimlik No, Sınıf ve Şube alanları
-- Tarih: 2026-05-08
-- Amaç:
--   * users tablosuna tc, grade_level, section sütunlarını ekle
--   * öğrencilerin şifresi = T.C. (mevcut öğrencilerin password değeri tc'ye taşınır)
--   * tc için UNIQUE index
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır → Çalıştır.
-- =============================================================

ALTER TABLE users
  ADD COLUMN tc VARCHAR(11) NULL AFTER full_name,
  ADD COLUMN grade_level VARCHAR(20) NULL AFTER tc,
  ADD COLUMN section VARCHAR(20) NULL AFTER grade_level;

-- Mevcut öğrencilerin password'ünü tc kabul et (zaten şifre = TC olacak)
UPDATE users SET tc = password WHERE role='student';

-- TC için benzersiz index (boş olabilir; teacher/admin null kalır)
ALTER TABLE users
  ADD UNIQUE KEY uniq_users_tc (tc);

-- Doğrula
SELECT id, role, full_name, tc, grade_level, section, is_active
  FROM users WHERE role='student' ORDER BY full_name LIMIT 20;
