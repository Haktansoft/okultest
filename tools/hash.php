<?php
// Bcrypt şifre üretici. Kullanım: php tools/hash.php "yeni-sifre"
$pwd = $argv[1] ?? null;
if (!$pwd) {
    fwrite(STDERR, "Kullanım: php tools/hash.php \"sifre\"\n");
    exit(1);
}
echo password_hash($pwd, PASSWORD_BCRYPT), "\n";
