<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherStudentController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $campusId = self::myCampusId($me);
        if (!$campusId && $me['role'] !== 'admin') {
            flash('err', 'Yöneticiniz size kampüs atamadan öğrenci yönetemezsiniz.');
            redirect('/teacher');
        }
        if ($me['role'] === 'admin' && !$campusId) {
            $items = db()->query("
                SELECT u.*, cr.name AS classroom_name, c.name AS campus_name, i.name AS institution_name
                  FROM users u
             LEFT JOIN classrooms cr ON cr.id = u.classroom_id
             LEFT JOIN campuses c ON c.id = u.campus_id
             LEFT JOIN institutions i ON i.id = c.institution_id
                 WHERE u.role='student'
              ORDER BY i.name, c.name, cr.name, u.full_name
            ")->fetchAll();
        } else {
            $st = db()->prepare("
                SELECT u.*, cr.name AS classroom_name
                  FROM users u
             LEFT JOIN classrooms cr ON cr.id = u.classroom_id
                 WHERE u.role='student' AND u.campus_id = ?
              ORDER BY cr.name, u.full_name
            ");
            $st->execute([$campusId]);
            $items = $st->fetchAll();
        }
        view('teacher/students/index', ['title' => 'Öğrenciler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        if ($isAdmin) {
            $teachers = self::teachersWithCampus();
            if (!$teachers) {
                flash('err', 'Önce en az bir öğretmen oluştur (kampüs atamalı).');
                redirect('/admin/teachers/new');
            }
            $classroomsByCampus = self::allClassroomsByCampus();
            view('teacher/students/form', [
                'title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null,
                'classrooms' => [], 'teachers' => $teachers, 'classroomsByCampus' => $classroomsByCampus,
            ]);
            return;
        }
        $campusId = self::myCampusId($me);
        if (!$campusId) {
            flash('err', 'Önce sana bir kampüs atanmalı.');
            redirect('/teacher/students');
        }
        $classrooms = self::classroomsForCampus($campusId);
        view('teacher/students/form', ['title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null, 'classrooms' => $classrooms]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';

        $name = trim((string)($_POST['full_name'] ?? ''));
        $tc   = self::cleanTc($_POST['tc'] ?? '');
        $crId = (int)($_POST['classroom_id'] ?? 0);

        // Admin: hangi öğretmene → o öğretmenin kampüsü kullanılır
        if ($isAdmin) {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $teacher = self::loadActiveTeacher($teacherId);
            if (!$teacher || empty($teacher['campus_id'])) {
                flash('err', 'Geçerli bir öğretmen seç (kampüsü olmalı).');
                redirect('/teacher/students/new');
            }
            $campusId = (int)$teacher['campus_id'];
            $creatorId = $teacherId; // öğrenciyi ait olduğu öğretmen oluşturmuş gibi davran
            $teacherForAssign = $teacherId;
        } else {
            $campusId = self::myCampusId($me);
            if (!$campusId) { flash('err', 'Kampüs yok.'); redirect('/teacher/students'); }
            $creatorId = (int)$me['id'];
            $teacherForAssign = (int)$me['id'];
        }

        $err = self::validateBasics($name, $tc);
        if ($err) { flash('err', $err); redirect('/teacher/students/new'); }
        if ($crId <= 0 || !self::classroomBelongsToCampus($crId, $campusId)) {
            flash('err', 'Sınıf seçilen öğretmenin kampüsüne ait olmalı.');
            redirect('/teacher/students/new');
        }
        if (self::tcExists($tc)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcıda kayıtlı.');
            redirect('/teacher/students/new');
        }
        if (self::passwordExists($tc)) {
            flash('err', 'Bu T.C. başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect('/teacher/students/new');
        }

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("
                INSERT INTO users (role, full_name, tc, password, campus_id, classroom_id, is_active, created_by)
                VALUES ('student', ?, ?, ?, ?, ?, 1, ?)
            ");
            $st->execute([$name, $tc, $tc, $campusId, $crId, $creatorId]);
            $studentId = (int)$pdo->lastInsertId();
            self::autoAssignAllTests($studentId, $teacherForAssign);
            $pdo->commit();
        } catch (\PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('err', 'Kayıt yapılamadı.');
            redirect('/teacher/students/new');
        }
        flash('ok', 'Öğrenci eklendi ve mevcut testler otomatik atandı.');
        redirect('/teacher/students');
    }

    public static function editForm(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        $campusId = (int)$item['campus_id'];
        $classrooms = self::classroomsForCampus($campusId);
        view('teacher/students/form', ['title' => 'Öğrenciyi Düzenle', 'me' => $me, 'item' => $item, 'classrooms' => $classrooms]);
    }

    public static function update(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        $campusId = (int)$item['campus_id'];

        $name = trim((string)($_POST['full_name'] ?? ''));
        $tc   = self::cleanTc($_POST['tc'] ?? '');
        $crId = (int)($_POST['classroom_id'] ?? 0);

        $err = self::validateBasics($name, $tc);
        if ($err) { flash('err', $err); redirect("/teacher/students/$id/edit"); }
        if ($crId <= 0 || !self::classroomBelongsToCampus($crId, $campusId)) {
            flash('err', 'Geçerli bir sınıf seç.');
            redirect("/teacher/students/$id/edit");
        }
        if (self::tcExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. başka bir kullanıcıda kayıtlı.');
            redirect("/teacher/students/$id/edit");
        }
        if (self::passwordExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect("/teacher/students/$id/edit");
        }
        try {
            db()->prepare("
                UPDATE users
                   SET full_name=?, tc=?, password=?, classroom_id=?
                 WHERE id=? AND role='student'
            ")->execute([$name, $tc, $tc, $crId, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Güncelleme yapılamadı.');
            redirect("/teacher/students/$id/edit");
        }
        flash('ok', 'Öğrenci güncellendi.');
        redirect('/teacher/students');
    }

    public static function delete(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        try {
            db()->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$id]);
            flash('ok', 'Öğrenci silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Silme hatası.');
        }
        redirect('/teacher/students');
    }

    public static function toggle(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/teacher/students');
    }

    // -------- Helpers --------

    public static function myCampusId(array $me): ?int {
        $cid = !empty($me['campus_id']) ? (int)$me['campus_id'] : null;
        return $cid > 0 ? $cid : null;
    }

    private static function loadOwned(int $id, array $me): ?array {
        $st = db()->prepare("SELECT * FROM users WHERE id=? AND role='student'");
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) return null;
        if ($me['role'] !== 'admin' && (int)$u['campus_id'] !== self::myCampusId($me)) {
            return null;
        }
        return $u;
    }

    private static function classroomsForCampus(int $campusId): array {
        $st = db()->prepare("SELECT id, name FROM classrooms WHERE campus_id=? ORDER BY name");
        $st->execute([$campusId]);
        return $st->fetchAll();
    }

    private static function teachersWithCampus(): array {
        return db()->query("
            SELECT u.id, u.full_name, u.campus_id, c.name AS campus_name, i.name AS institution_name
              FROM users u
              JOIN campuses c ON c.id = u.campus_id
              JOIN institutions i ON i.id = c.institution_id
             WHERE u.role='teacher' AND u.is_active=1 AND u.campus_id IS NOT NULL
          ORDER BY i.name, c.name, u.full_name
        ")->fetchAll();
    }

    private static function allClassroomsByCampus(): array {
        $rows = db()->query("SELECT id, campus_id, name FROM classrooms ORDER BY name")->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[(int)$r['campus_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
        return $map;
    }

    private static function loadActiveTeacher(int $id): ?array {
        if ($id <= 0) return null;
        $st = db()->prepare("SELECT id, full_name, campus_id FROM users WHERE id=? AND role='teacher' AND is_active=1");
        $st->execute([$id]);
        $u = $st->fetch();
        return $u ?: null;
    }

    private static function classroomBelongsToCampus(int $crId, int $campusId): bool {
        $st = db()->prepare("SELECT id FROM classrooms WHERE id=? AND campus_id=?");
        $st->execute([$crId, $campusId]);
        return (bool)$st->fetchColumn();
    }

    private static function pickTeacherForCampus(int $campusId, int $fallback): int {
        $st = db()->prepare("SELECT id FROM users WHERE role='teacher' AND campus_id=? AND is_active=1 ORDER BY id LIMIT 1");
        $st->execute([$campusId]);
        $tid = (int)$st->fetchColumn();
        return $tid > 0 ? $tid : $fallback;
    }

    private static function autoAssignAllTests(int $studentId, int $teacherId): void {
        $tests = db()->query("SELECT id FROM tests")->fetchAll();
        if (!$tests) return;
        $ins = db()->prepare("
            INSERT IGNORE INTO test_assignments (test_id, student_id, teacher_id, status)
            VALUES (?, ?, ?, 'pending')
        ");
        foreach ($tests as $t) {
            $ins->execute([(int)$t['id'], $studentId, $teacherId]);
        }
    }

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
