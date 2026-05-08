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

echo "Admin e-posta [admin@local]: ";
$email = trim((string)fgets(STDIN));
if ($email === '') $email = 'admin@local';

echo "Admin tam ad [Ana Yönetici]: ";
$name = trim((string)fgets(STDIN));
if ($name === '') $name = 'Ana Yönetici';

echo "Şifre (en az 6 karakter): ";
// Echo'yu kapat
if (function_exists('shell_exec')) {
    shell_exec('stty -echo');
}
$pass = trim((string)fgets(STDIN));
if (function_exists('shell_exec')) {
    shell_exec('stty echo');
}
echo "\n";

if (strlen($pass) < 6) {
    fwrite(STDERR, "Şifre çok kısa.\n");
    exit(1);
}

$hash = password_hash($pass, PASSWORD_BCRYPT);

$stmt = $pdo->prepare("INSERT INTO users (role, full_name, email, password_hash, is_active) VALUES ('admin', ?, ?, ?, 1)
ON DUPLICATE KEY UPDATE full_name = VALUES(full_name), password_hash = VALUES(password_hash), is_active = 1, role = 'admin'");
$stmt->execute([$name, $email, $hash]);

echo "Tamam. {$email} ile giriş yapabilirsin.\n";
