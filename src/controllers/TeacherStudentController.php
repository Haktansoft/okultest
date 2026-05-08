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
        $name = trim((string)($_POST['full_name'] ?? ''));
        $pass = trim((string)($_POST['password'] ?? ''));
        if ($name === '' || strlen($pass) < 4) {
            flash('err', 'Ad-soyad ve şifre (en az 4 karakter) gerekli.');
            redirect('/teacher/students/new');
        }
        if (self::passwordExists($pass)) {
            flash('err', 'Bu şifre başka bir kullanıcıda kayıtlı. Farklı bir şifre gir.');
            redirect('/teacher/students/new');
        }
        try {
            $st = db()->prepare("INSERT INTO users (role, full_name, password, is_active, created_by) VALUES ('student', ?, ?, 1, ?)");
            $st->execute([$name, $pass, $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Kayıt yapılamadı (şifre zaten kullanılıyor olabilir).');
            redirect('/teacher/students/new');
        }
        flash('ok', 'Öğrenci eklendi.');
        redirect('/teacher/students');
    }

    public static function reset(string $id): void {
        requireRole('teacher', 'admin');
        $pass = trim((string)($_POST['password'] ?? ''));
        if (strlen($pass) < 4) { flash('err', 'Şifre en az 4 karakter.'); redirect('/teacher/students'); }
        if (self::passwordExists($pass, (int)$id)) {
            flash('err', 'Bu şifre başka bir kullanıcıda var.');
            redirect('/teacher/students');
        }
        db()->prepare("UPDATE users SET password=? WHERE id=? AND role='student'")
            ->execute([$pass, $id]);
        flash('ok', 'Şifre güncellendi.');
        redirect('/teacher/students');
    }

    public static function toggle(string $id): void {
        requireRole('teacher', 'admin');
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/teacher/students');
    }

    private static function passwordExists(string $pass, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE password = ? AND id <> ? LIMIT 1");
        $st->execute([$pass, $excludeId]);
        return (bool)$st->fetchColumn();
    }
}
