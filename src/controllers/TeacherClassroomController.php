<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherClassroomController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $campusId = self::myCampusId($me);
        if (!$campusId && $me['role'] !== 'admin') {
            flash('err', 'Yöneticiniz size kampüs atamadan sınıf yönetemezsiniz.');
            redirect('/teacher');
        }
        if ($me['role'] === 'admin' && !$campusId) {
            // Admin tüm sınıfları görür
            $items = db()->query("
                SELECT cr.*, c.name AS campus_name, i.name AS institution_name,
                       (SELECT COUNT(*) FROM users u WHERE u.classroom_id=cr.id AND u.role='student') AS scount
                  FROM classrooms cr
                  JOIN campuses c ON c.id=cr.campus_id
                  JOIN institutions i ON i.id=c.institution_id
              ORDER BY i.name, c.name, cr.name
            ")->fetchAll();
        } else {
            $st = db()->prepare("
                SELECT cr.*, c.name AS campus_name, i.name AS institution_name,
                       (SELECT COUNT(*) FROM users u WHERE u.classroom_id=cr.id AND u.role='student') AS scount
                  FROM classrooms cr
                  JOIN campuses c ON c.id=cr.campus_id
                  JOIN institutions i ON i.id=c.institution_id
                 WHERE cr.campus_id = ?
              ORDER BY cr.name
            ");
            $st->execute([$campusId]);
            $items = $st->fetchAll();
        }
        view('teacher/classrooms/index', ['title' => 'Sınıflar', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $campusId = self::myCampusId($me);
        if (!$campusId) {
            flash('err', 'Önce sana bir kampüs atanmalı.');
            redirect('/teacher/classrooms');
        }
        view('teacher/classrooms/form', ['title' => 'Yeni Sınıf', 'me' => $me, 'item' => null]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $campusId = self::myCampusId($me);
        if (!$campusId) { flash('err', 'Kampüs yok.'); redirect('/teacher/classrooms'); }
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash('err', 'Sınıf adı gerekli.'); redirect('/teacher/classrooms/new'); }
        try {
            db()->prepare("INSERT INTO classrooms (campus_id, name) VALUES (?, ?)")->execute([$campusId, $name]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kampüste aynı isimde sınıf var.');
            redirect('/teacher/classrooms/new');
        }
        flash('ok', 'Sınıf eklendi.');
        redirect('/teacher/classrooms');
    }

    public static function editForm(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Sınıf bulunamadı.'); redirect('/teacher/classrooms'); }
        view('teacher/classrooms/form', ['title' => 'Sınıfı Düzenle', 'me' => $me, 'item' => $item]);
    }

    public static function update(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Sınıf bulunamadı.'); redirect('/teacher/classrooms'); }
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') { flash('err', 'Sınıf adı gerekli.'); redirect("/teacher/classrooms/$id/edit"); }
        try {
            db()->prepare("UPDATE classrooms SET name=? WHERE id=?")->execute([$name, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kampüste aynı isimde sınıf var.');
            redirect("/teacher/classrooms/$id/edit");
        }
        flash('ok', 'Sınıf güncellendi.');
        redirect('/teacher/classrooms');
    }

    public static function delete(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Sınıf bulunamadı.'); redirect('/teacher/classrooms'); }
        // Bu sınıftaki öğrencilerin classroom_id'sini NULL yap
        db()->prepare("UPDATE users SET classroom_id=NULL WHERE classroom_id=?")->execute([$id]);
        db()->prepare("DELETE FROM classrooms WHERE id=?")->execute([$id]);
        flash('ok', 'Sınıf silindi (öğrenciler sınıfsız bırakıldı).');
        redirect('/teacher/classrooms');
    }

    public static function myCampusId(array $me): ?int {
        $cid = !empty($me['campus_id']) ? (int)$me['campus_id'] : null;
        return $cid > 0 ? $cid : null;
    }

    private static function loadOwned(int $id, array $me): ?array {
        $st = db()->prepare("SELECT * FROM classrooms WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) return null;
        if ($me['role'] !== 'admin' && (int)$item['campus_id'] !== self::myCampusId($me)) {
            return null; // başkasının kampüsündeki sınıfa dokunamazsın
        }
        return $item;
    }
}
