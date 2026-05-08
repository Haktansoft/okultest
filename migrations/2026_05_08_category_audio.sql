-- =============================================================
-- Migration: kategori açıklamasına ses dosyası eklenebilir
-- Tarih: 2026-05-08
-- Amaç:
--   * categories tablosuna description_media_id BIGINT UNSIGNED NULL ekle.
--     Öğrenci o kategorinin sorularına geçmeden önce sesli yönerge dinleyebilsin.
-- Kullanım: phpMyAdmin → SQL → tamamını yapıştır.
-- =============================================================

ALTER TABLE categories
  ADD COLUMN description_media_id BIGINT UNSIGNED NULL AFTER description,
  ADD KEY idx_categories_desc_media (description_media_id);

-- Doğrula
SELECT id, name, description_media_id FROM categories ORDER BY name LIMIT 10;
