<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminTeacherController {
    public static function index(): void {
        $me = requireRole('admin');
        $items = db()->query("
            SELECT u.*, c.name AS campus_name, i.name AS institution_name,
                   (SELECT COUNT(*) FROM users s WHERE s.role='student' AND s.campus_id=u.campus_id) AS scount
              FROM users u
         LEFT JOIN campuses c ON c.id = u.campus_id
         LEFT JOIN institutions i ON i.id = c.institution_id
             WHERE u.role='teacher'
          ORDER BY u.full_name
        ")->fetchAll();
        view('admin/teachers/index', ['title' => 'Öğretmenler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        $campuses = self::campusOptions();
        view('admin/teachers/form', ['title' => 'Yeni Öğretmen', 'me' => $me, 'item' => null, 'campuses' => $campuses]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        $name = trim((string)($_POST['full_name'] ?? ''));
        $pass = trim((string)($_POST['password'] ?? ''));
        $campusId = (int)($_POST['campus_id'] ?? 0);
        if ($name === '' || strlen($pass) < 4) {
            flash('err', 'Ad-soyad ve şifre (en az 4 karakter) gerekli.');
            redirect('/admin/teachers/new');
        }
        if ($campusId <= 0 || !self::campusExists($campusId)) {
            flash('err', 'Geçerli bir kampüs seç.');
            redirect('/admin/teachers/new');
        }
        if (self::passwordExists($pass)) {
            flash('err', 'Bu şifre başka bir kullanıcı tarafından kullanılıyor.');
            redirect('/admin/teachers/new');
        }
        try {
            $st = db()->prepare("
                INSERT INTO users (role, full_name, password, campus_id, is_active, created_by)
                VALUES ('teacher', ?, ?, ?, 1, ?)
            ");
            $st->execute([$name, $pass, $campusId, $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Kayıt yapılamadı.');
            redirect('/admin/teachers/new');
        }
        flash('ok', 'Öğretmen eklendi.');
        redirect('/admin/teachers');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM users WHERE id=? AND role='teacher'");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Öğretmen bulunamadı.'); redirect('/admin/teachers'); }
        $campuses = self::campusOptions();
        view('admin/teachers/form', ['title' => 'Öğretmeni Düzenle', 'me' => $me, 'item' => $item, 'campuses' => $campuses]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        $name = trim((string)($_POST['full_name'] ?? ''));
        $campusId = (int)($_POST['campus_id'] ?? 0);
        if ($name === '') { flash('err', 'Ad-soyad gerekli.'); redirect("/admin/teachers/$id/edit"); }
        if ($campusId <= 0 || !self::campusExists($campusId)) {
            flash('err', 'Geçerli bir kampüs seç.');
            redirect("/admin/teachers/$id/edit");
        }
        db()->prepare("UPDATE users SET full_name=?, campus_id=? WHERE id=? AND role='teacher'")
            ->execute([$name, $campusId, $id]);
        flash('ok', 'Öğretmen güncellendi.');
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
        db()->prepare("UPDATE users SET password=? WHERE id=? AND role='teacher'")->execute([$pass, $id]);
        flash('ok', 'Şifre güncellendi.');
        redirect('/admin/teachers');
    }

    public static function toggle(string $id): void {
        requireRole('admin');
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='teacher'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/admin/teachers');
    }

    private static function passwordExists(string $pass, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE password = ? AND id <> ? LIMIT 1");
        $st->execute([$pass, $excludeId]);
        return (bool)$st->fetchColumn();
    }

    private static function campusExists(int $id): bool {
        $st = db()->prepare("SELECT id FROM campuses WHERE id=?");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    private static function campusOptions(): array {
        return db()->query("
            SELECT c.id, c.name AS campus_name, i.name AS institution_name
              FROM campuses c JOIN institutions i ON i.id = c.institution_id
          ORDER BY i.name, c.name
        ")->fetchAll();
    }
}
