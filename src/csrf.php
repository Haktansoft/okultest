<?php
declare(strict_types=1);

namespace App;

function csrfToken(): string {
    startSession();
    if (empty($_SESSION['_csrf'])) {
        $_SESSION['_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['_csrf'];
}

function csrfField(): string {
    return '<input type="hidden" name="_csrf" value="' . htmlspecialchars(csrfToken(), ENT_QUOTES, 'UTF-8') . '">';
}

function csrfCheck(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') return;
    startSession();
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    // JSON body içinde _csrf gelmiş olabilir (sendBeacon vb.)
    if ($token === '' && strpos($_SERVER['CONTENT_TYPE'] ?? '', 'json') !== false) {
        $raw = file_get_contents('php://input');
        if ($raw !== false && $raw !== '') {
            $body = json_decode($raw, true);
            if (is_array($body) && !empty($body['_csrf'])) {
                $token = (string)$body['_csrf'];
            }
        }
        // Tekrar okumak için pointer sıfırlanamaz, raw'ı saklayalım
        $GLOBALS['__raw_post'] = $raw;
    }
    if (!hash_equals($_SESSION['_csrf'] ?? '', (string)$token)) {
        if (isApiRequest()) {
            http_response_code(419);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'csrf_expired']);
            exit;
        }
        logoutUser();
        startSession();
        flash('login_error', 'Oturum süresi doldu, lütfen tekrar giriş yapın.');
        redirect('/login');
    }
}
