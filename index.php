<?php
declare(strict_types=1);

/*
 * Kök seviye giriş noktası — projenin tamamı domain'in DocumentRoot'una
 * (cPanel'de örn. /home/<user>/<domain>/) yüklendiğinde kullanılır.
 *
 * Yerel geliştirmede `public/index.php` çalışır, bu dosyaya gerek yoktur.
 */

// PHP sürüm kontrolü — kod 8.1+ özellikleri (match, str_contains, never tipi) kullanır.
if (PHP_VERSION_ID < 80100) {
    http_response_code(500);
    echo "<!doctype html><meta charset=utf-8><title>PHP sürümü yetersiz</title>"
       . "<h1>PHP " . PHP_VERSION . " — uygulama 8.1+ ister</h1>"
       . "<p>cPanel → <strong>Select PHP Version</strong> menüsünden bu domain için"
       . " <strong>PHP 8.1</strong> veya üstü seçin. Ardından <code>pdo_mysql, gd, fileinfo, mbstring</code> eklentilerini açın.</p>";
    exit;
}

// Tanı modu (dağıtım sorunları için): https://siteadi.com/?diag=1
if (isset($_GET['diag'])) {
    header('Content-Type: text/plain; charset=utf-8');
    echo "TANI RAPORU\n===========\n\n";
    echo "PHP sürümü: " . PHP_VERSION . "\n";
    echo "DocumentRoot (script dir): " . __DIR__ . "\n";
    echo "REQUEST_URI: " . ($_SERVER['REQUEST_URI'] ?? '?') . "\n";
    echo "REQUEST_METHOD: " . ($_SERVER['REQUEST_METHOD'] ?? '?') . "\n\n";
    echo "Gerekli yollar:\n";
    foreach ([
        __DIR__ . '/vendor/autoload.php',
        __DIR__ . '/src/bootstrap.php',
        __DIR__ . '/.env',
        __DIR__ . '/public/assets/css/app.css',
    ] as $p) {
        echo (is_file($p) ? '  ✓ ' : '  ✗ ') . $p . "\n";
    }
    echo "\nGerekli klasörler (yazılabilir mi?):\n";
    foreach ([
        __DIR__ . '/storage',
        __DIR__ . '/storage/uploads',
        __DIR__ . '/storage/uploads/images',
        __DIR__ . '/storage/uploads/audio',
        __DIR__ . '/storage/uploads/video',
    ] as $d) {
        $st = is_dir($d) ? (is_writable($d) ? '✓ yazılabilir' : '⚠ salt-okunur') : '✗ yok';
        echo "  $st  $d\n";
    }
    echo "\nApache rewrite testi:\n";
    echo "  /login (rewrite ile) çağrısında REQUEST_URI '/login' olmalı.\n";
    echo "  Şu an gelen URI: " . ($_SERVER['REQUEST_URI'] ?? '?') . "\n";
    exit;
}

// Composer
$autoload = __DIR__ . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo "<!doctype html><meta charset=utf-8><title>Kurulum hatası</title>"
       . "<h1>vendor/ klasörü yok</h1>"
       . "<p>Yerel makinede <code>composer install --no-dev --optimize-autoloader</code> "
       . "çalıştırıp sonuç olan <code>vendor/</code> klasörünü FTP ile bu dizine yükleyin.</p>"
       . "<p>Detaylı tanı: <a href=\"?diag=1\">?diag=1</a></p>";
    exit;
}
require $autoload;

// Bootstrap (router + tüm rotalar)
require __DIR__ . '/src/bootstrap.php';
