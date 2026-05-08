-- =============================================================
-- Migration: sadece-şifre ile giriş
-- Tarih: 2026-05-08
-- Amaç:
--   * users.email kolonunu kaldır
--   * password_hash → password (VARCHAR 64) olarak yeniden adlandır
--   * password için UNIQUE index ekle
--   * mevcut kullanıcılara geçici düz-metin şifre ata
--     (eski bcrypt hash'leri ile yeni giriş akışı çalışmaz)
--
-- Kullanım: phpMyAdmin → veritabanını seç → SQL sekmesi → bu blokun
-- TAMAMINI yapıştır → Çalıştır.
-- Not: MariaDB 10.6+ / MySQL 8.0.29+ için IF EXISTS desteği var.
--      Eski sürümde IF EXISTS satırlarını kaldırmanız gerekebilir.
-- =============================================================

-- 1) email UNIQUE index (varsa) kaldır
ALTER TABLE users DROP INDEX IF EXISTS uniq_users_email;

-- 2) email kolonu (varsa) kaldır
ALTER TABLE users DROP COLUMN IF EXISTS email;

-- 3) password_hash → password (kısa düz metin)
--    Eğer kolon adı zaten 'password' ise bu satırı atla.
ALTER TABLE users CHANGE COLUMN password_hash password VARCHAR(64) NOT NULL;

-- 4) Eski bcrypt hash'leri kullanılamaz; herkese geçici düz şifre ver.
--    Admin: A001, A002, ...   Öğretmen: T001, T002, ...   Öğrenci: S001, S002, ...
SET @aix := 0, @tix := 0, @six := 0;

UPDATE users
   SET password = CASE role
     WHEN 'admin'   THEN CONCAT('A', LPAD((@aix := @aix + 1), 3, '0'))
     WHEN 'teacher' THEN CONCAT('T', LPAD((@tix := @tix + 1), 3, '0'))
     WHEN 'student' THEN CONCAT('S', LPAD((@six := @six + 1), 3, '0'))
   END
 ORDER BY role, id;

-- 5) UNIQUE index — aynı şifre iki kullanıcıda olamaz.
ALTER TABLE users DROP INDEX IF EXISTS uniq_users_password;
ALTER TABLE users ADD UNIQUE KEY uniq_users_password (password);

-- 6) Sonuçları gör (atanan geçici şifreler):
SELECT id, role, full_name, password, is_active
  FROM users
 ORDER BY role, full_name;
