<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, env, flash, json, redirect, requireRole, view, user};
use const App\UPLOAD_PATH;

class AdminMediaController {
    private const KIND_BY_MIME = [
        'image/jpeg' => 'image', 'image/png' => 'image', 'image/gif' => 'image',
        'image/webp' => 'image', 'image/heic' => 'image', 'image/heif' => 'image',

        'audio/mpeg' => 'audio', 'audio/mp3' => 'audio', 'audio/wav' => 'audio',
        'audio/x-wav' => 'audio', 'audio/wave' => 'audio', 'audio/vnd.wave' => 'audio',
        'audio/ogg' => 'audio', 'audio/mp4' => 'audio', 'audio/aac' => 'audio',
        'audio/x-m4a' => 'audio', 'audio/m4a' => 'audio', 'audio/flac' => 'audio',
        'audio/x-flac' => 'audio', 'audio/webm' => 'audio',

        'video/mp4' => 'video', 'video/webm' => 'video', 'video/quicktime' => 'video',
        'video/x-msvideo' => 'video', 'video/x-matroska' => 'video', 'video/3gpp' => 'video',
        'video/3gpp2' => 'video', 'video/mpeg' => 'video', 'video/x-m4v' => 'video',
        'video/x-flv' => 'video', 'video/ogg' => 'video',
    ];

    private const KIND_BY_EXT = [
        'jpg'=>'image','jpeg'=>'image','png'=>'image','gif'=>'image','webp'=>'image','heic'=>'image','heif'=>'image',
        'mp3'=>'audio','wav'=>'audio','ogg'=>'audio','m4a'=>'audio','aac'=>'audio','flac'=>'audio',
        'mp4'=>'video','webm'=>'video','mov'=>'video','m4v'=>'video','avi'=>'video','mkv'=>'video',
        '3gp'=>'video','3g2'=>'video','mpg'=>'video','mpeg'=>'video','flv'=>'video','ogv'=>'video',
    ];

    public static function index(): void {
        $me = requireRole('admin');
        $kind = $_GET['kind'] ?? 'image';
        if (!in_array($kind, ['image','audio','video'], true)) $kind = 'image';

        $perPage = 60;
        $page = max(1, (int)($_GET['page'] ?? 1));
        $offset = ($page - 1) * $perPage;

        $cst = db()->prepare("SELECT COUNT(*) FROM media WHERE kind=?");
        $cst->execute([$kind]);
        $total = (int)$cst->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) { $page = $totalPages; $offset = ($page - 1) * $perPage; }

        $st = db()->prepare("SELECT * FROM media WHERE kind=? ORDER BY id DESC LIMIT $perPage OFFSET $offset");
        $st->execute([$kind]);
        $items = $st->fetchAll();

        $counts = ['image'=>0, 'audio'=>0, 'video'=>0];
        foreach (db()->query("SELECT kind, COUNT(*) c FROM media GROUP BY kind") as $r) {
            $counts[$r['kind']] = (int)$r['c'];
        }

        view('admin/media/index', [
            'title' => 'Medya', 'me' => $me, 'kind' => $kind, 'items' => $items,
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total, 'counts' => $counts,
        ]);
    }

    /** JSON medya listesi (soru formundaki seçici için) */
    public static function listJson(): void {
        requireRole('admin');
        $kind = $_GET['kind'] ?? 'image';
        if (!in_array($kind, ['image','audio','video'], true)) $kind = 'image';
        $q = trim((string)($_GET['q'] ?? ''));

        $sql = "SELECT id, kind, original_name, mime, size_bytes, created_at FROM media WHERE kind = ?";
        $params = [$kind];
        if ($q !== '') {
            $sql .= " AND original_name LIKE ?";
            $params[] = '%' . $q . '%';
        }
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = 60;
        $offset = ($page - 1) * $perPage;
        $sql .= " ORDER BY id DESC LIMIT $perPage OFFSET $offset";
        $st = db()->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll();

        $countSql = "SELECT COUNT(*) FROM media WHERE kind = ?";
        $countParams = [$kind];
        if ($q !== '') { $countSql .= " AND original_name LIKE ?"; $countParams[] = '%' . $q . '%'; }
        $cst = db()->prepare($countSql);
        $cst->execute($countParams);
        $total = (int)$cst->fetchColumn();

        // Toplam sayılar (sekme rozetleri için)
        $counts = [];
        foreach (db()->query("SELECT kind, COUNT(*) c FROM media GROUP BY kind") as $r) {
            $counts[$r['kind']] = (int)$r['c'];
        }
        $counts += ['image'=>0, 'audio'=>0, 'video'=>0];

        $items = array_map(fn($m) => [
            'id'    => (int)$m['id'],
            'kind'  => $m['kind'],
            'name'  => $m['original_name'],
            'mime'  => $m['mime'],
            'size'  => (int)$m['size_bytes'],
            'url'   => '/media/' . (int)$m['id'],
        ], $rows);

        \App\json([
            'ok' => true,
            'items' => $items,
            'counts' => $counts,
            'page' => $page,
            'perPage' => $perPage,
            'total' => $total,
            'hasMore' => ($offset + count($items)) < $total,
        ]);
    }

    public static function upload(): void {
        $me = requireRole('admin');

        // post_max_size aşılmışsa $_FILES tamamen boş gelir; bunu net mesajla bildir.
        $contentLen = (int)($_SERVER['CONTENT_LENGTH'] ?? 0);
        if (empty($_FILES) && $contentLen > 0) {
            $postMax = self::iniSize('post_max_size');
            json([
                'ok' => false,
                'error' => "Dosya(lar) PHP'nin post_max_size limitini aştı (≈ " . self::humanSize($postMax) . ").",
                'items' => [],
            ], 413);
        }
        if (empty($_FILES['files'])) json(['ok' => false, 'error' => 'Dosya yok'], 400);

        $files = $_FILES['files'];
        $count = is_array($files['name']) ? count($files['name']) : 1;
        $maxBytes = (int)(env('UPLOAD_MAX_BYTES', 524288000)); // 500MB
        $iniMax   = self::iniSize('upload_max_filesize');
        $effectiveMax = $iniMax > 0 ? min($iniMax, $maxBytes) : $maxBytes;
        $results = [];

        for ($i = 0; $i < $count; $i++) {
            $name  = is_array($files['name'])     ? $files['name'][$i]     : $files['name'];
            $tmp   = is_array($files['tmp_name']) ? $files['tmp_name'][$i] : $files['tmp_name'];
            $err   = is_array($files['error'])    ? $files['error'][$i]    : $files['error'];
            $size  = is_array($files['size'])     ? $files['size'][$i]     : $files['size'];

            if ($err !== UPLOAD_ERR_OK) {
                $results[] = ['ok'=>false, 'name'=>$name, 'error'=>self::uploadErrMsg((int)$err, $iniMax)];
                continue;
            }
            if ($size > $effectiveMax) {
                $results[] = ['ok'=>false, 'name'=>$name,
                    'error'=>'Dosya çok büyük (' . self::humanSize($size) . '). Limit: ' . self::humanSize($effectiveMax)];
                continue;
            }
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mime  = (string)finfo_file($finfo, $tmp);
            // PHP 8.5'te finfo_close deprecate edildi, finfo objesi GC ile temizleniyor.

            $ext  = strtolower(pathinfo($name, PATHINFO_EXTENSION));
            $kind = self::KIND_BY_MIME[$mime] ?? self::KIND_BY_EXT[$ext] ?? null;
            if (!$kind) {
                $results[] = ['ok'=>false, 'name'=>$name, 'error'=>'Desteklenmeyen tür: ' . $mime . ' (.' . $ext . ')'];
                continue;
            }
            // Dosya adını üret
            $base = preg_replace('/[^a-zA-Z0-9._-]+/', '_', pathinfo($name, PATHINFO_FILENAME)) ?: 'file';
            $ext  = $ext ?: self::extFromMime($mime);
            $rel  = $kind . 's/' . date('Ymd_His') . '_' . substr(bin2hex(random_bytes(4)), 0, 6) . '_' . $base . ($ext ? ".$ext" : '');
            $dst  = UPLOAD_PATH . '/' . $rel;

            if (!is_dir(dirname($dst))) mkdir(dirname($dst), 0775, true);
            if (!move_uploaded_file($tmp, $dst)) {
                $results[] = ['ok'=>false, 'name'=>$name, 'error'=>'Yazma hatası'];
                continue;
            }
            $st = db()->prepare("INSERT INTO media (kind, path, original_name, mime, size_bytes, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
            $st->execute([$kind, $rel, $name, $mime, $size, $me['id']]);
            $id = (int)db()->lastInsertId();
            $results[] = ['ok'=>true, 'id'=>$id, 'kind'=>$kind, 'url'=>"/media/$id", 'name'=>$name];
        }
        json(['ok' => true, 'items' => $results]);
    }

    /** PHP php.ini boyut değerlerini byte'a çevir ("8M", "2G" vb.) */
    private static function iniSize(string $key): int {
        $v = trim((string)ini_get($key));
        if ($v === '') return 0;
        $unit = strtolower(substr($v, -1));
        $n = (int)$v;
        switch ($unit) {
            case 'g': return $n * 1024 * 1024 * 1024;
            case 'm': return $n * 1024 * 1024;
            case 'k': return $n * 1024;
            default:  return (int)$v;
        }
    }

    private static function humanSize(int $bytes): string {
        if ($bytes >= 1024*1024*1024) return number_format($bytes/(1024*1024*1024), 1) . ' GB';
        if ($bytes >= 1024*1024)      return number_format($bytes/(1024*1024), 1) . ' MB';
        if ($bytes >= 1024)           return number_format($bytes/1024, 0) . ' KB';
        return $bytes . ' B';
    }

    private static function uploadErrMsg(int $err, int $iniMax): string {
        switch ($err) {
            case UPLOAD_ERR_INI_SIZE:   return 'Dosya çok büyük (PHP upload_max_filesize ≈ ' . self::humanSize($iniMax) . ').';
            case UPLOAD_ERR_FORM_SIZE:  return 'Form limit aşıldı.';
            case UPLOAD_ERR_PARTIAL:    return 'Dosya kısmen yüklendi, tekrar dene.';
            case UPLOAD_ERR_NO_FILE:    return 'Dosya seçilmedi.';
            case UPLOAD_ERR_NO_TMP_DIR: return 'Sunucuda geçici dizin yok.';
            case UPLOAD_ERR_CANT_WRITE: return 'Sunucuda yazma hatası.';
            case UPLOAD_ERR_EXTENSION:  return 'Bir PHP eklentisi yüklemeyi durdurdu.';
            default:                    return 'Upload hatası kodu: ' . $err;
        }
    }

    public static function delete(string $id): void {
        requireRole('admin');
        $st = db()->prepare("SELECT * FROM media WHERE id=?");
        $st->execute([$id]);
        $m = $st->fetch();
        if (!$m) { flash('err', 'Medya bulunamadı.'); redirect('/admin/media'); }
        $abs = UPLOAD_PATH . '/' . $m['path'];
        if (is_file($abs)) @unlink($abs);
        db()->prepare("DELETE FROM media WHERE id=?")->execute([$id]);
        flash('ok', 'Medya silindi.');
        redirect('/admin/media?kind=' . $m['kind']);
    }

    // /media/{id} — yetkili her kullanıcıya servis et
    public static function serve(string $id): void {
        $u = \App\user();
        if (!$u) { http_response_code(403); exit; }
        $st = db()->prepare("SELECT * FROM media WHERE id=?");
        $st->execute([$id]);
        $m = $st->fetch();
        if (!$m) { http_response_code(404); exit; }
        $abs = UPLOAD_PATH . '/' . $m['path'];
        if (!is_file($abs)) { http_response_code(404); exit; }
        header('Content-Type: ' . $m['mime']);
        header('Content-Length: ' . filesize($abs));
        header('Content-Disposition: inline; filename="' . rawurlencode($m['original_name']) . '"');
        header('Cache-Control: private, max-age=3600');
        // Range desteği basit — büyük dosyalar için tam dosya
        readfile($abs);
        exit;
    }

    private static function extFromMime(string $mime): string {
        $map = [
            'image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp',
            'audio/mpeg' => 'mp3', 'audio/mp3' => 'mp3',
            'audio/wav'  => 'wav', 'audio/x-wav' => 'wav',
            'audio/ogg'  => 'ogg',
            'audio/mp4'  => 'm4a', 'audio/x-m4a' => 'm4a', 'audio/m4a' => 'm4a',
            'video/mp4' => 'mp4', 'video/webm' => 'webm', 'video/quicktime' => 'mov',
        ];
        return $map[$mime] ?? '';
    }
}
