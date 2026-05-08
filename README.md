# Test/Eğitim Platformu

Düz PHP ile yazılmış, üç rollü (admin / öğretmen / öğrenci) test çözüm platformu.

## Gereksinimler

- PHP 8.1+ (PDO MySQL, GD, fileinfo, mbstring, json, session)
- MySQL 8 veya MariaDB 10.6+
- Composer

## Kurulum

```bash
# 1) Bağımlılıklar
composer install

# 2) DB oluştur
mysql -u root -p -e "CREATE DATABASE test_egitim DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mysql -u root -p test_egitim < schema.sql

# 3) Ortam dosyası
cp .env.example .env
# .env içindeki DB_* alanlarını düzenle

# 4) Admin kullanıcısı oluştur (interaktif şifre belirleme)
php tools/install.php

# 5) Geliştirme sunucusu (video yüklemek için yüksek limitler)
php -d upload_max_filesize=512M -d post_max_size=512M -d memory_limit=256M \
    -S localhost:8000 -t public
```

Tarayıcıdan `http://localhost:8000/login` aç → admin@local / belirlediğin şifre.

## Yapı

- `public/` — web kökü (DocumentRoot). `index.php` front controller.
- `src/` — config, db, auth, csrf, helpers, router, controller'lar.
- `views/` — layoutlar ve sayfa şablonları, `views/pdf/` mPDF için.
- `storage/uploads/{images,audio,video}` — yüklenen medyalar (web kökü dışında, `/media/{id}` ile servis edilir).
- `schema.sql` — DB tabloları.
- `tools/install.php` — ilk admin kullanıcısı oluşturur.
- `tools/hash.php` — bcrypt şifre üretir (parola sıfırlama için).

## Roller

- **admin@local** — kategori, medya, soru, test, öğretmen yönetir.
- **öğretmen** — admin tarafından eklenir; kendi öğrencilerini açar, test atar, sonuç ve PDF alır, fiziksel soruları tamamlar.
- **öğrenci** — öğretmen tarafından eklenir; atanmış testleri çözer.

## Notlar

- Ekran tasarımı sade, Bootstrap 5 + minimum custom CSS.
- Test çözümünde yanıtlar hem sunucuya autosave edilir hem localStorage'da tutulur — tarayıcı kapansa bile devam edilir.
- Fiziksel sorular öğrenciye gösterilmez; öğretmen test sonrası ayrı ekrandan yanıt girer.
- mPDF Türkçe karakterli PDF çıktısı (test, sonuç raporu, eksik sorular kâğıdı) üretir.
# okultest
