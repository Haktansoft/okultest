<?php
declare(strict_types=1);

namespace App;

// .env dosyasını yükle (basit parser)
function loadEnv(string $path): void {
    if (!is_file($path)) return;
    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') continue;
        if (!str_contains($line, '=')) continue;
        [$k, $v] = array_map('trim', explode('=', $line, 2));
        if ($k === '') continue;
        $v = trim($v, "\"' \t");
        if (getenv($k) === false) {
            putenv("$k=$v");
            $_ENV[$k] = $v;
        }
    }
}

loadEnv(dirname(__DIR__) . '/.env');

function env(string $key, $default = null) {
    $v = getenv($key);
    return $v === false ? $default : $v;
}

const APP_ROOT = __DIR__ . '/..';
defined(__NAMESPACE__ . '\\STORAGE_PATH') || define(__NAMESPACE__ . '\\STORAGE_PATH', dirname(__DIR__) . '/storage');
defined(__NAMESPACE__ . '\\UPLOAD_PATH') || define(__NAMESPACE__ . '\\UPLOAD_PATH', dirname(__DIR__) . '/storage/uploads');
defined(__NAMESPACE__ . '\\VIEWS_PATH') || define(__NAMESPACE__ . '\\VIEWS_PATH', dirname(__DIR__) . '/views');
