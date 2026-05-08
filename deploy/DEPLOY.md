# cPanel Deployment Rehberi

Sorun: cPanel'de domain açıldığında `cgi-sys/defaultwebpage.cgi`'ye yönlendiriliyorsa, `public_html/` içinde **`index.php` veya `index.html` bulunamamış** demektir. Aşağıdaki yapıya getirin.

## Hedef yapı

```
/home/<cpaneluser>/
├── test_egitim_app/        ← uygulama kaynak kodu (web kökü dışında!)
│   ├── src/
│   ├── views/
│   ├── vendor/             ← composer install ile gelir
│   ├── storage/
│   │   └── uploads/
│   │       ├── images/
│   │       ├── audio/
│   │       └── video/
│   ├── tools/
│   ├── composer.json
│   ├── schema.sql
│   └── .env                ← DB ayarları (aşağıda)
└── public_html/            ← cPanel'in DocumentRoot'u
    ├── index.php           ← `deploy/public_html/index.php` dosyasını koy
    ├── .htaccess           ← `deploy/public_html/.htaccess` dosyasını koy
    └── assets/             ← yerel `public/assets/` klasörünün KOPYASI
        ├── css/
        └── js/
```

## Adım adım kurulum

### 1) Dosyaları yükle (FTP / cPanel File Manager)

Yerel makinedeki proje:
```
test_egitim/
├── public/                 ← BURASI public_html'e gidecek
│   ├── assets/
│   ├── index.php           ← KULLANMA, deploy/public_html/index.php'yi kullan
│   └── .htaccess           ← KULLANMA, deploy/public_html/.htaccess'i kullan
├── src/
├── views/
├── storage/
├── tools/
├── vendor/                 ← composer install ile oluşturulan
├── composer.json
└── schema.sql
```

Sunucudaki hedef:

- `public/assets/` içeriği → `~/public_html/assets/`
- `deploy/public_html/index.php` → `~/public_html/index.php`
- `deploy/public_html/.htaccess` → `~/public_html/.htaccess`
- Diğer her şey (src, views, storage, tools, vendor, composer.json, schema.sql) → `~/test_egitim_app/`

### 2) `vendor/` (composer paketleri)

İki seçenek:

**A) Yerelde build et, FTP ile yükle (kolay yol):**
```bash
cd test_egitim
composer install --no-dev --optimize-autoloader
# Sonra vendor/ klasörünü ~/test_egitim_app/vendor/ altına yükle
```

**B) Sunucuda kur (SSH erişimi varsa):**
```bash
ssh cpanel-user@okulolgunluktesti.com
cd ~/test_egitim_app
composer install --no-dev --optimize-autoloader
```

### 3) Dosya izinleri

```bash
# Klasörler 755, dosyalar 644
find ~/test_egitim_app -type d -exec chmod 755 {} \;
find ~/test_egitim_app -type f -exec chmod 644 {} \;

# uploads/ klasörü PHP tarafından yazılabilir olmalı
chmod -R 775 ~/test_egitim_app/storage/uploads
```

### 4) Veritabanı

cPanel → **MySQL Databases** üzerinden:
1. Yeni database oluştur (örn. `cpaneluser_testegitim`)
2. Yeni user oluştur ve database'e tüm yetkileri ver
3. Şifreyi not et

Sonra schema'yı yükle:
- cPanel → **phpMyAdmin** → veritabanını seç → **Import** → `schema.sql` dosyasını yükle

### 5) `.env` dosyası

`~/test_egitim_app/.env` (cPanel File Manager'dan oluştur):

```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=cpaneluser_testegitim
DB_USER=cpaneluser_dbuser
DB_PASS=oluşturduğun-şifre

APP_URL=https://okulolgunluktesti.com
APP_ENV=production

UPLOAD_MAX_BYTES=524288000
SESSION_NAME=TESTEGITIMSESSID
```

### 6) İlk admin kullanıcısı

SSH varsa:
```bash
cd ~/test_egitim_app
php tools/install.php
```

SSH yoksa: phpMyAdmin'de SQL sekmesinde:
```sql
INSERT INTO users (role, full_name, email, password_hash, is_active)
VALUES ('admin', 'Ana Yönetici', 'admin@local',
        '$2y$12$REPLACE_WITH_BCRYPT_HASH', 1);
```

`$2y$12$...` hash'ini yerel makinede üretin:
```bash
php -r 'echo password_hash("istediğin-şifre", PASSWORD_BCRYPT);'
```

### 7) PHP sürümü

cPanel → **MultiPHP Manager** veya **Select PHP Version**:
- Domain'i seç → **PHP 8.1+** olarak ayarla
- Aktif eklentiler: `pdo_mysql`, `gd`, `fileinfo`, `mbstring`, `json`, `session`

### 8) PHP yükleme limitleri (video için)

cPanel → **Select PHP Version** → **Options** sekmesinde:
- `upload_max_filesize` = `512M`
- `post_max_size` = `512M`
- `memory_limit` = `256M`
- `max_execution_time` = `300`

(Veya `.htaccess`'teki `php_value` satırları işe yararsa onlar yeterli.)

### 9) Test et

`https://okulolgunluktesti.com/login` aç → admin@local + belirlediğin şifre.

## Sık karşılaşılan hatalar

| Hata | Çözüm |
|---|---|
| `cgi-sys/defaultwebpage.cgi`'ye yönlendiriyor | `public_html/index.php` yok ya da DocumentRoot yanlış. Yukarıdaki dosyayı koy. |
| 500 internal server error | Sunucuda PHP sürümü 8.1+ olmalı. cPanel Error Log'a bak. |
| "composer install çalıştırılmamış" mesajı | `~/test_egitim_app/vendor/` yok. Adım 2'yi yap. |
| "Uygulama kök dizini bulunamadı" | Klasör adı `test_egitim_app` değilse, `public_html/index.php` içindeki `$candidates`'a doğru yolu ekle. |
| Görsel/CSS yüklenmiyor | `public_html/assets/` içine yerel `public/assets/` kopyalanmadı. |
| Login sonrası 419 (CSRF) | Tarayıcının cookie'leri kabul ettiğinden emin ol; `APP_URL`'deki domain doğru. |
| Medya yüklenmiyor | Adım 8'deki PHP limit ayarları + `storage/uploads` izinleri (775). |
| Türkçe karakter PDF'de bozuk | mPDF zaten dejavusans font kullanıyor; `default_font` ayarı `src/pdf.php`'de. |
