<?php
declare(strict_types=1);

/*
 * cPanel deployment için front controller.
 *
 * Beklenen yapı:
 *   /home/<cpaneluser>/
 *     ├── test_egitim_app/    (uygulama kaynak kodu)
 *     │   ├── src/
 *     │   ├── views/
 *     │   ├── vendor/
 *     │   ├── storage/uploads/
 *     │   └── .env
 *     └── public_html/        (bu dosya BURADA)
 *         ├── index.php
 *         ├── .htaccess
 *         └── assets/
 *
 * Uygulama kök adı farklıysa $candidates listesini düzenle.
 */

$candidates = [
    dirname(__DIR__) . '/test_egitim_app',
    dirname(__DIR__) . '/test-egitim',
    dirname(__DIR__) . '/test_egitim',
    dirname(__DIR__) . '/app',
];
$appRoot = null;
foreach ($candidates as $c) {
    if (is_dir($c) && is_file($c . '/src/bootstrap.php')) { $appRoot = $c; break; }
}
if (!$appRoot) {
    http_response_code(500);
    echo "<!doctype html><meta charset=utf-8><title>Kurulum hatası</title>"
       . "<h1>Uygulama kök dizini bulunamadı</h1>"
       . "<p>Beklenen yerler:</p><ul>";
    foreach ($candidates as $c) echo "<li><code>" . htmlspecialchars($c) . "</code></li>";
    echo "</ul><p>Uygulamayı bunlardan birine yükleyin veya <code>public_html/index.php</code>"
       . " içindeki <code>\$candidates</code> listesine doğru yolu ekleyin.</p>";
    exit;
}

$autoload = $appRoot . '/vendor/autoload.php';
if (!is_file($autoload)) {
    http_response_code(500);
    echo "<!doctype html><meta charset=utf-8><title>Kurulum hatası</title>"
       . "<h1>composer install çalıştırılmamış</h1>"
       . "<p>" . htmlspecialchars($appRoot) . " içine girip <code>composer install --no-dev</code> "
       . "çalıştırın ya da yerel <code>vendor/</code> klasörünü FTP ile yükleyin.</p>";
    exit;
}

require $autoload;
require $appRoot . '/src/bootstrap.php';
