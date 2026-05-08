<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminCampusController {
    public static function index(): void {
        $me = requireRole('admin');
        $items = db()->query("
            SELECT c.*, i.name AS institution_name,
                   (SELECT COUNT(*) FROM users u WHERE u.campus_id=c.id AND u.role='teacher') AS teacher_count,
                   (SELECT COUNT(*) FROM users u WHERE u.campus_id=c.id AND u.role='student') AS student_count
              FROM campuses c
              JOIN institutions i ON i.id = c.institution_id
          ORDER BY i.name, c.name
        ")->fetchAll();
        view('admin/campuses/index', ['title' => 'Kampüsler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        $insts = db()->query("SELECT id, name FROM institutions ORDER BY name")->fetchAll();
        if (!$insts) {
            flash('err', 'Önce en az bir kurum oluşturun.');
            redirect('/admin/institutions/new');
        }
        view('admin/campuses/form', ['title' => 'Yeni Kampüs', 'me' => $me, 'item' => null, 'insts' => $insts]);
    }

    public static function create(): void {
        requireRole('admin');
        $name   = trim((string)($_POST['name'] ?? ''));
        $instId = (int)($_POST['institution_id'] ?? 0);
        if ($name === '' || $instId <= 0) { flash('err', 'Kurum ve ad gerekli.'); redirect('/admin/campuses/new'); }
        try {
            db()->prepare("INSERT INTO campuses (institution_id, name) VALUES (?, ?)")->execute([$instId, $name]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kurumda aynı isimde kampüs var.');
            redirect('/admin/campuses/new');
        }
        flash('ok', 'Kampüs eklendi.');
        redirect('/admin/campuses');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM campuses WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Kampüs bulunamadı.'); redirect('/admin/campuses'); }
        $insts = db()->query("SELECT id, name FROM institutions ORDER BY name")->fetchAll();
        view('admin/campuses/form', ['title' => 'Kampüs Düzenle', 'me' => $me, 'item' => $item, 'insts' => $insts]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        $name   = trim((string)($_POST['name'] ?? ''));
        $instId = (int)($_POST['institution_id'] ?? 0);
        if ($name === '' || $instId <= 0) { flash('err', 'Kurum ve ad gerekli.'); redirect("/admin/campuses/$id/edit"); }
        try {
            db()->prepare("UPDATE campuses SET institution_id=?, name=? WHERE id=?")->execute([$instId, $name, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kurumda aynı isimde kampüs var.');
            redirect("/admin/campuses/$id/edit");
        }
        flash('ok', 'Kampüs güncellendi.');
        redirect('/admin/campuses');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        // Bağlı öğretmen/öğrenci varsa engelle
        $st = db()->prepare("SELECT COUNT(*) FROM users WHERE campus_id=?");
        $st->execute([$id]);
        if ((int)$st->fetchColumn() > 0) {
            flash('err', 'Bu kampüse bağlı kullanıcılar var. Önce taşı/sil.');
            redirect('/admin/campuses');
        }
        try {
            db()->prepare("DELETE FROM campuses WHERE id=?")->execute([$id]);
            flash('ok', 'Kampüs silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Silme hatası.');
        }
        redirect('/admin/campuses');
    }
}
