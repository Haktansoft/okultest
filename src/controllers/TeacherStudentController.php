<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherStudentController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $items = db()->query("SELECT * FROM users WHERE role='student' ORDER BY full_name")->fetchAll();
        view('teacher/students/index', ['title' => 'Öğrenciler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        view('teacher/students/form', ['title' => 'Yeni Öğrenci', 'me' => $me]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $pass  = (string)($_POST['password'] ?? '');
        if ($name === '' || $email === '' || strlen($pass) < 6) {
            flash('err', 'Tüm alanlar gerekli, şifre en az 6 karakter.');
            redirect('/teacher/students/new');
        }
        try {
            $st = db()->prepare("INSERT INTO users (role, full_name, email, password_hash, is_active, created_by) VALUES ('student', ?, ?, ?, 1, ?)");
            $st->execute([$name, $email, password_hash($pass, PASSWORD_BCRYPT), $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu e-posta zaten kayıtlı.');
            redirect('/teacher/students/new');
        }
        flash('ok', 'Öğrenci eklendi.');
        redirect('/teacher/students');
    }

    public static function reset(string $id): void {
        requireRole('teacher', 'admin');
        $pass = (string)($_POST['password'] ?? '');
        if (strlen($pass) < 6) { flash('err', 'Şifre en az 6 karakter.'); redirect('/teacher/students'); }
        db()->prepare("UPDATE users SET password_hash=? WHERE id=? AND role='student'")
            ->execute([password_hash($pass, PASSWORD_BCRYPT), $id]);
        flash('ok', 'Şifre sıfırlandı.');
        redirect('/teacher/students');
    }

    public static function toggle(string $id): void {
        requireRole('teacher', 'admin');
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/teacher/students');
    }
}
