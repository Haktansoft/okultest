<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminTeacherController {
    public static function index(): void {
        $me = requireRole('admin');
        $items = db()->query("
            SELECT u.*, (SELECT COUNT(*) FROM teacher_students ts WHERE ts.teacher_id=u.id) AS scount
            FROM users u WHERE u.role='teacher' ORDER BY u.full_name
        ")->fetchAll();
        view('admin/teachers/index', ['title' => 'Öğretmenler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        view('admin/teachers/form', ['title' => 'Yeni Öğretmen', 'me' => $me]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        $name = trim((string)($_POST['full_name'] ?? ''));
        $pass = trim((string)($_POST['password'] ?? ''));
        if ($name === '' || strlen($pass) < 4) {
            flash('err', 'Ad-soyad ve şifre (en az 4 karakter) gerekli.');
            redirect('/admin/teachers/new');
        }
        if (self::passwordExists($pass)) {
            flash('err', 'Bu şifre başka bir kullanıcı tarafından kullanılıyor. Farklı bir şifre seç.');
            redirect('/admin/teachers/new');
        }
        try {
            $st = db()->prepare("INSERT INTO users (role, full_name, password, is_active, created_by) VALUES ('teacher', ?, ?, 1, ?)");
            $st->execute([$name, $pass, $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Kayıt yapılamadı (şifre zaten kullanılıyor olabilir).');
            redirect('/admin/teachers/new');
        }
        flash('ok', 'Öğretmen eklendi.');
        redirect('/admin/teachers');
    }

    public static function reset(string $id): void {
        requireRole('admin');
        $pass = trim((string)($_POST['password'] ?? ''));
        if (strlen($pass) < 4) { flash('err', 'Şifre en az 4 karakter.'); redirect('/admin/teachers'); }
        if (self::passwordExists($pass, (int)$id)) {
            flash('err', 'Bu şifre başka bir kullanıcıda var.');
            redirect('/admin/teachers');
        }
        $st = db()->prepare("UPDATE users SET password=? WHERE id=? AND role='teacher'");
        $st->execute([$pass, $id]);
        flash('ok', 'Şifre güncellendi.');
        redirect('/admin/teachers');
    }

    public static function toggle(string $id): void {
        requireRole('admin');
        $st = db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='teacher'");
        $st->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/admin/teachers');
    }

    private static function passwordExists(string $pass, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE password = ? AND id <> ? LIMIT 1");
        $st->execute([$pass, $excludeId]);
        return (bool)$st->fetchColumn();
    }
}
