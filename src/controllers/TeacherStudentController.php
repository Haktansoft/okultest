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
        view('teacher/students/form', ['title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $tc    = self::cleanTc($_POST['tc'] ?? '');
        $grade = trim((string)($_POST['grade_level'] ?? ''));
        $sect  = trim((string)($_POST['section'] ?? ''));

        $err = self::validateBasics($name, $tc);
        if ($err) { flash('err', $err); redirect('/teacher/students/new'); }

        if (self::tcExists($tc)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcıda kayıtlı.');
            redirect('/teacher/students/new');
        }
        if (self::passwordExists($tc)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect('/teacher/students/new');
        }

        try {
            $st = db()->prepare("
                INSERT INTO users (role, full_name, tc, grade_level, section, password, is_active, created_by)
                VALUES ('student', ?, ?, ?, ?, ?, 1, ?)
            ");
            $st->execute([$name, $tc, $grade !== '' ? $grade : null, $sect !== '' ? $sect : null, $tc, $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Kayıt yapılamadı (T.C. veya şifre çakışması olabilir).');
            redirect('/teacher/students/new');
        }
        flash('ok', 'Öğrenci eklendi. Giriş şifresi: T.C. numarası.');
        redirect('/teacher/students');
    }

    public static function editForm(string $id): void {
        $me = requireRole('teacher', 'admin');
        $st = db()->prepare("SELECT * FROM users WHERE id=? AND role='student'");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        view('teacher/students/form', ['title' => 'Öğrenciyi Düzenle', 'me' => $me, 'item' => $item]);
    }

    public static function update(string $id): void {
        requireRole('teacher', 'admin');
        $name  = trim((string)($_POST['full_name'] ?? ''));
        $tc    = self::cleanTc($_POST['tc'] ?? '');
        $grade = trim((string)($_POST['grade_level'] ?? ''));
        $sect  = trim((string)($_POST['section'] ?? ''));

        $err = self::validateBasics($name, $tc);
        if ($err) { flash('err', $err); redirect("/teacher/students/$id/edit"); }

        if (self::tcExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcıda kayıtlı.');
            redirect("/teacher/students/$id/edit");
        }
        if (self::passwordExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect("/teacher/students/$id/edit");
        }

        try {
            $st = db()->prepare("
                UPDATE users
                   SET full_name=?, tc=?, grade_level=?, section=?, password=?
                 WHERE id=? AND role='student'
            ");
            $st->execute([
                $name, $tc,
                $grade !== '' ? $grade : null,
                $sect !== '' ? $sect : null,
                $tc, $id,
            ]);
        } catch (\PDOException $ex) {
            flash('err', 'Güncelleme yapılamadı (çakışma olabilir).');
            redirect("/teacher/students/$id/edit");
        }
        flash('ok', 'Öğrenci güncellendi.');
        redirect('/teacher/students');
    }

    public static function toggle(string $id): void {
        requireRole('teacher', 'admin');
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/teacher/students');
    }

    // -------- Helpers --------

    private static function cleanTc($raw): string {
        return preg_replace('/\D+/', '', (string)$raw);
    }

    private static function validateBasics(string $name, string $tc): ?string {
        if ($name === '') return 'Ad-soyad gerekli.';
        if (strlen($tc) !== 11) return 'T.C. numarası 11 haneli olmalı.';
        if ($tc[0] === '0') return 'T.C. numarası 0 ile başlayamaz.';
        return null;
    }

    private static function tcExists(string $tc, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE tc=? AND id<>? LIMIT 1");
        $st->execute([$tc, $excludeId]);
        return (bool)$st->fetchColumn();
    }

    private static function passwordExists(string $pass, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE password=? AND id<>? LIMIT 1");
        $st->execute([$pass, $excludeId]);
        return (bool)$st->fetchColumn();
    }
}
