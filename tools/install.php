<?php
// İlk admin kullanıcısı için interaktif kurulum.
declare(strict_types=1);

require __DIR__ . '/../src/config.php';
require __DIR__ . '/../src/db.php';

echo "Test/Eğitim Platformu — admin kurulum\n";
echo "-------------------------------------\n";

$pdo = App\db();

$existing = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
if ((int)$existing > 0) {
    echo "Zaten en az bir admin kullanıcısı var. Devam edilsin mi? (y/N): ";
    $ans = strtolower(trim((string)fgets(STDIN)));
    if ($ans !== 'y') {
        echo "İptal edildi.\n";
        exit(0);
    }
}

echo "Admin tam ad [Ana Yönetici]: ";
$name = trim((string)fgets(STDIN));
if ($name === '') $name = 'Ana Yönetici';

echo "Şifre (en az 4 karakter, sistemde benzersiz olmalı): ";
if (function_exists('shell_exec')) { shell_exec('stty -echo'); }
$pass = trim((string)fgets(STDIN));
if (function_exists('shell_exec')) { shell_exec('stty echo'); }
echo "\n";

if (strlen($pass) < 4) {
    fwrite(STDERR, "Şifre çok kısa.\n");
    exit(1);
}

$dup = $pdo->prepare("SELECT id FROM users WHERE password = ? LIMIT 1");
$dup->execute([$pass]);
if ($dup->fetch()) {
    fwrite(STDERR, "Bu şifre zaten başka bir kullanıcıda var. Farklı bir şifre seç.\n");
    exit(1);
}

$stmt = $pdo->prepare("INSERT INTO users (role, full_name, password, is_active) VALUES ('admin', ?, ?, 1)");
$stmt->execute([$name, $pass]);

echo "Tamam. \"{$name}\" admin olarak eklendi. Bu şifreyle giriş yapabilirsin: {$pass}\n";
