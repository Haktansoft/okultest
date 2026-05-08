<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminClassroomController {
    public const GRADE_LEVELS = ['4 YAŞ', '5 YAŞ', '6 YAŞ', '1. SINIF', '2. SINIF', '3. SINIF', '4. SINIF'];
    public const SECTIONS     = ['A','B','C','D','E','F','G','H'];

    public static function index(): void {
        $me = requireRole('admin');
        $campusFilter = (int)($_GET['campus_id'] ?? 0);
        $sql = "
            SELECT cr.*, c.name AS campus_name, i.name AS institution_name,
                   (SELECT COUNT(*) FROM users u WHERE u.classroom_id=cr.id AND u.role='student') AS scount,
                   (SELECT COUNT(*) FROM teacher_classrooms tc WHERE tc.classroom_id=cr.id) AS tcount
              FROM classrooms cr
              JOIN campuses c ON c.id = cr.campus_id
              JOIN institutions i ON i.id = c.institution_id";
        $params = [];
        if ($campusFilter > 0) { $sql .= " WHERE cr.campus_id=?"; $params[] = $campusFilter; }
        $sql .= " ORDER BY i.name, c.name, cr.grade_level, cr.section, cr.name";
        $st = db()->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll();

        $campuses = db()->query("
            SELECT c.id, c.name AS campus_name, i.name AS institution_name
              FROM campuses c JOIN institutions i ON i.id = c.institution_id
          ORDER BY i.name, c.name
        ")->fetchAll();

        view('admin/classrooms/index', [
            'title' => 'Sınıflar', 'me' => $me, 'items' => $items,
            'campuses' => $campuses, 'campusFilter' => $campusFilter,
        ]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        $campuses = self::loadCampuses();
        if (!$campuses) {
            flash('err', 'Önce bir kampüs oluştur.');
            redirect('/admin/campuses/new');
        }
        view('admin/classrooms/form', [
            'title' => 'Yeni Sınıf', 'me' => $me, 'item' => null,
            'campuses' => $campuses,
            'gradeLevels' => self::GRADE_LEVELS,
            'sections' => self::SECTIONS,
        ]);
    }

    public static function create(): void {
        requireRole('admin');
        [$campusId, $grade, $section, $name, $err] = self::readForm();
        if ($err) { flash('err', $err); redirect('/admin/classrooms/new'); }
        try {
            db()->prepare("INSERT INTO classrooms (campus_id, grade_level, section, name) VALUES (?, ?, ?, ?)")
                ->execute([$campusId, $grade, $section, $name]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kampüste aynı sınıf zaten var.');
            redirect('/admin/classrooms/new');
        }
        flash('ok', 'Sınıf eklendi.');
        redirect('/admin/classrooms');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM classrooms WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Sınıf bulunamadı.'); redirect('/admin/classrooms'); }
        view('admin/classrooms/form', [
            'title' => 'Sınıfı Düzenle', 'me' => $me, 'item' => $item,
            'campuses' => self::loadCampuses(),
            'gradeLevels' => self::GRADE_LEVELS,
            'sections' => self::SECTIONS,
        ]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        [$campusId, $grade, $section, $name, $err] = self::readForm();
        if ($err) { flash('err', $err); redirect("/admin/classrooms/$id/edit"); }
        try {
            db()->prepare("UPDATE classrooms SET campus_id=?, grade_level=?, section=?, name=? WHERE id=?")
                ->execute([$campusId, $grade, $section, $name, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Bu kampüste aynı sınıf zaten var.');
            redirect("/admin/classrooms/$id/edit");
        }
        flash('ok', 'Sınıf güncellendi.');
        redirect('/admin/classrooms');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        // Bağlı öğrenciler classroom_id=NULL'a düşer
        db()->prepare("UPDATE users SET classroom_id=NULL WHERE classroom_id=?")->execute([$id]);
        db()->prepare("DELETE FROM classrooms WHERE id=?")->execute([$id]);
        flash('ok', 'Sınıf silindi (öğrenciler sınıfsız bırakıldı, atamalar kaldırıldı).');
        redirect('/admin/classrooms');
    }

    private static function readForm(): array {
        $campusId = (int)($_POST['campus_id'] ?? 0);
        $grade = trim((string)($_POST['grade_level'] ?? ''));
        $section = trim((string)($_POST['section'] ?? ''));
        if ($campusId <= 0) return [0, '', '', '', 'Kampüs seç.'];
        if ($grade === '' || $section === '') return [0, '', '', '', 'Sınıf ve şube seç.'];
        if (!in_array($grade, self::GRADE_LEVELS, true)) return [0, '', '', '', 'Geçersiz sınıf.'];
        if (!in_array($section, self::SECTIONS, true)) return [0, '', '', '', 'Geçersiz şube.'];
        $name = $grade . ' ' . $section;
        return [$campusId, $grade, $section, $name, null];
    }

    private static function loadCampuses(): array {
        return db()->query("
            SELECT c.id, c.name AS campus_name, i.name AS institution_name
              FROM campuses c JOIN institutions i ON i.id = c.institution_id
          ORDER BY i.name, c.name
        ")->fetchAll();
    }
}
