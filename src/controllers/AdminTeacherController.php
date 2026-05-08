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
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($name === '' || $email === '' || strlen($pass) < 6) {
            flash('err', 'Tüm alanlar gerekli, şifre en az 6 karakter.');
            redirect('/admin/teachers/new');
        }
        try {
            $st = db()->prepare("INSERT INTO users (role, full_name, email, password_hash, is_active, created_by) VALUES ('teacher', ?, ?, ?, 1, ?)");
            $st->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu e-posta zaten kayıtlı.');
            redirect('/admin/teachers/new');
        }
        flash('ok', 'Öğretmen eklendi.');
        redirect('/admin/teachers');
    }

    public static function reset(string $id): void {
        requireRole('admin');
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 6) { flash('err', 'Şifre en az 6 karakter.'); redirect('/admin/teachers'); }
        $st = db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='teacher'");
        $st->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        flash('ok', 'Şifre sıfırlandı.');
        redirect('/admin/teachers');
    }

    public static function toggle(string $id): void {
        requireRole('admin');
        $st = db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='teacher'");
        $st->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/admin/teachers');
    }
}
