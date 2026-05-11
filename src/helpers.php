<?php
declare(strict_types=1);

namespace App;

function e($v): string {
    return htmlspecialchars((string)($v ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $path): void {
    header('Location: ' . $path);
    exit;
}

function flash(string $key, ?string $msg = null): ?string {
    startSession();
    if ($msg !== null) {
        $_SESSION['_flash'][$key] = $msg;
        return null;
    }
    $val = $_SESSION['_flash'][$key] ?? null;
    unset($_SESSION['_flash'][$key]);
    return $val;
}

function view(string $path, array $data = []): void {
    $layout  = $data['_layout']  ?? 'layouts/base';
    $content = $data['_content'] ?? null;
    $file = VIEWS_PATH . '/' . trim($path, '/') . '.php';
    if (!is_file($file)) {
        throw new \RuntimeException("View bulunamadı: $path");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $bodyContent = ob_get_clean();
    if ($layout === false || $layout === '') {
        echo $bodyContent;
        return;
    }
    $layoutFile = VIEWS_PATH . '/' . trim($layout, '/') . '.php';
    if (!is_file($layoutFile)) {
        echo $bodyContent;
        return;
    }
    $title = $data['title'] ?? 'Test Eğitim';
    require $layoutFile;
}

function json($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function input(string $key, $default = null) {
    return $_POST[$key] ?? $_GET[$key] ?? $default;
}

function requireMethod(string ...$methods): void {
    if (!in_array($_SERVER['REQUEST_METHOD'], $methods, true)) {
        http_response_code(405);
        header('Allow: ' . implode(', ', $methods));
        echo "405 — Yöntem desteklenmiyor";
        exit;
    }
}

// Medya yolunu /media/{id} URL'i olarak döndür
function mediaUrl(?array $media): ?string {
    if (!$media) return null;
    return '/media/' . (int)$media['id'];
}

// Düz yazıyı paragraf + maddeler halinde HTML'e dönüştür.
// Boş satır → yeni paragraf. "- ", "* " veya "• " ile başlayan satırlar liste maddesi.
// Tek satır içinde 2+ kez geçen " • " ayraçları da maddelere dönüştürülür.
function formatRichText(?string $text): string {
    $text = trim((string)$text);
    if ($text === '') return '';
    $text = str_replace(["\r\n", "\r"], "\n", $text);
    $blocks = preg_split('/\n{2,}/', $text);
    $html = '';
    foreach ($blocks as $block) {
        // İçinde inline "•" ayraçları varsa madde listesine çevir
        if (substr_count($block, '•') >= 2) {
            $parts = preg_split('/\s*•\s*/u', $block);
            $parts = array_values(array_filter(array_map('trim', $parts), fn($p) => $p !== ''));
            if ($parts) {
                // İlk parça maddeyle başlamıyorsa intro paragraf yap
                $first = $parts[0];
                $items = array_slice($parts, 1);
                if ($items) {
                    if ($first !== '') $html .= '<p>' . nl2br(e($first)) . '</p>';
                    $html .= '<ul>';
                    foreach ($items as $li) $html .= '<li>' . nl2br(e($li)) . '</li>';
                    $html .= '</ul>';
                    continue;
                }
            }
        }

        $lines = explode("\n", $block);
        $listItems = [];
        $paragraphLines = [];
        $flushList = function () use (&$listItems, &$html) {
            if (!$listItems) return;
            $html .= '<ul>';
            foreach ($listItems as $li) {
                $html .= '<li>' . nl2br(e($li)) . '</li>';
            }
            $html .= '</ul>';
            $listItems = [];
        };
        $flushParagraph = function () use (&$paragraphLines, &$html) {
            if (!$paragraphLines) return;
            $html .= '<p>' . nl2br(e(implode("\n", $paragraphLines))) . '</p>';
            $paragraphLines = [];
        };
        foreach ($lines as $line) {
            $trimmed = ltrim($line);
            if (preg_match('/^[-*•]\s+(.*)$/u', $trimmed, $m)) {
                $flushParagraph();
                $listItems[] = $m[1];
            } else {
                $flushList();
                if ($trimmed !== '') $paragraphLines[] = $trimmed;
            }
        }
        $flushList();
        $flushParagraph();
    }
    return $html;
}

// Saniye → "Xdk Ysn" gibi format
function formatDuration(int $seconds): string {
    if ($seconds < 60) return $seconds . ' sn';
    $m = intdiv($seconds, 60);
    $s = $seconds % 60;
    return $s ? "{$m}dk {$s}sn" : "{$m}dk";
}

// Bir öğrencinin atamasının skorunu hesapla
function recomputeScore(int $assignmentId): float {
    $pdo = db();
    // Normal cevaplardan
    $sumA = (float)$pdo->query("
        SELECT COALESCE(SUM(o.score), 0)
        FROM attempt_answers a
        JOIN question_options o ON o.id = a.selected_option_id
        WHERE a.assignment_id = " . (int)$assignmentId
    )->fetchColumn();
    // Fiziksel cevaplardan
    $sumB = (float)$pdo->query("
        SELECT COALESCE(SUM(o.score), 0)
        FROM physical_answers pa
        JOIN question_options o ON o.id = pa.selected_option_id
        WHERE pa.assignment_id = " . (int)$assignmentId
    )->fetchColumn();
    $total = $sumA + $sumB;
    $u = $pdo->prepare("UPDATE test_assignments SET total_score = ? WHERE id = ?");
    $u->execute([$total, $assignmentId]);
    return $total;
}
