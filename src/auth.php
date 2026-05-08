<?php
declare(strict_types=1);

namespace App;

function startSession(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $name = env('SESSION_NAME', 'TESTEGITIMSESSID');
    session_name($name);
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

function user(): ?array {
    startSession();
    if (!isset($_SESSION['user_id'])) return null;
    static $cache = null;
    if ($cache !== null && $cache['id'] === (int)$_SESSION['user_id']) return $cache;
    $stmt = db()->prepare("SELECT id, role, full_name, email, is_active FROM users WHERE id = ? LIMIT 1");
    $stmt->execute([$_SESSION['user_id']]);
    $u = $stmt->fetch();
    if (!$u || !$u['is_active']) {
        logoutUser();
        return null;
    }
    return $cache = $u;
}

function login(string $email, string $password): ?array {
    $stmt = db()->prepare("SELECT * FROM users WHERE email = ? AND is_active = 1 LIMIT 1");
    $stmt->execute([$email]);
    $u = $stmt->fetch();
    if (!$u) return null;
    if (!password_verify($password, $u['password_hash'])) return null;
    startSession();
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int)$u['id'];
    return $u;
}

function logoutUser(): void {
    startSession();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', $params['secure'] ?? false, $params['httponly'] ?? false);
    }
    session_destroy();
}

function requireAuth(): array {
    $u = user();
    if (!$u) {
        header('Location: /login');
        exit;
    }
    return $u;
}

function requireRole(string ...$roles): array {
    $u = requireAuth();
    if (!in_array($u['role'], $roles, true)) {
        http_response_code(403);
        echo "<!doctype html><meta charset=utf-8><title>403</title><h1>403 — Yetkiniz yok</h1>";
        exit;
    }
    return $u;
}
