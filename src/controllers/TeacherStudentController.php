<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherStudentController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        if ($isAdmin) {
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
            $crIds = self::myClassroomIds((int)$me['id']);
            if (!$crIds) { $items = []; }
            else {
                $place = implode(',', array_fill(0, count($crIds), '?'));
                $st = db()->prepare("
                    SELECT u.*, cr.name AS classroom_name
                      FROM users u
                 LEFT JOIN classrooms cr ON cr.id = u.classroom_id
                     WHERE u.role='student' AND u.classroom_id IN ($place)
                  ORDER BY cr.name, u.full_name
                ");
                $st->execute($crIds);
                $items = $st->fetchAll();
            }
        }
        view('teacher/students/index', ['title' => 'Öğrenciler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        if ($isAdmin) {
            $teachers = self::teachersWithClassrooms();
            if (!$teachers) {
                flash('err', 'Önce en az bir öğretmen oluştur ve sınıf ataması yap.');
                redirect('/admin/teachers');
            }
            $classroomsByTeacher = self::allClassroomsByTeacher();
            view('teacher/students/form', [
                'title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null,
                'classrooms' => [], 'teachers' => $teachers, 'classroomsByTeacher' => $classroomsByTeacher,
            ]);
            return;
        }
        $crIds = self::myClassroomIds((int)$me['id']);
        if (!$crIds) {
            flash('err', 'Sana atanmış bir sınıf yok. Yöneticiyle iletişime geç.');
            redirect('/teacher/students');
        }
        $classrooms = self::classroomsByIds($crIds);
        view('teacher/students/form', ['title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null, 'classrooms' => $classrooms]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';

        $name = trim((string)($_POST['full_name'] ?? ''));
        $tc   = self::cleanTc($_POST['tc'] ?? '');
        $crId = (int)($_POST['classroom_id'] ?? 0);

        // Admin: hangi öğretmene → o öğretmenin kampüsü ve atanmış sınıfından seç
        if ($isAdmin) {
            $teacherId = (int)($_POST['teacher_id'] ?? 0);
            $teacher = self::loadActiveTeacher($teacherId);
            if (!$teacher || empty($teacher['campus_id'])) {
                flash('err', 'Geçerli bir öğretmen seç (kampüsü olmalı).');
                redirect('/teacher/students/new');
            }
            $campusId = (int)$teacher['campus_id'];
            $creatorId = $teacherId;
            $teacherForAssign = $teacherId;
            // Sınıf öğretmenin atanmış sınıfı olmalı
            $okClass = self::teacherHasClassroom($teacherId, $crId);
        } else {
            $campusId = (int)$me['campus_id'];
            $creatorId = (int)$me['id'];
            $teacherForAssign = (int)$me['id'];
            $okClass = self::teacherHasClassroom((int)$me['id'], $crId);
        }

        $err = self::validateBasics($name, $tc);
        if ($err) { flash('err', $err); redirect('/teacher/students/new'); }
        if ($crId <= 0 || !$okClass) {
            flash('err', 'Geçerli bir sınıf seç (öğretmenin atanmış sınıfı olmalı).');
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
        if ($me['role'] === 'admin') {
            $classrooms = self::classroomsForCampus($campusId);
        } else {
            $classrooms = self::classroomsByIds(self::myClassroomIds((int)$me['id']));
        }
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
        $okClass = $me['role'] === 'admin'
            ? self::classroomBelongsToCampus($crId, $campusId)
            : self::teacherHasClassroom((int)$me['id'], $crId);
        if ($crId <= 0 || !$okClass) {
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
        if ($me['role'] !== 'admin') {
            $crIds = self::myClassroomIds((int)$me['id']);
            if (!in_array((int)$u['classroom_id'], $crIds, true)) return null;
        }
        return $u;
    }

    private static function classroomsForCampus(int $campusId): array {
        $st = db()->prepare("SELECT id, name FROM classrooms WHERE campus_id=? ORDER BY grade_level, section, name");
        $st->execute([$campusId]);
        return $st->fetchAll();
    }

    private static function classroomsByIds(array $ids): array {
        if (!$ids) return [];
        $place = implode(',', array_fill(0, count($ids), '?'));
        $st = db()->prepare("SELECT id, name FROM classrooms WHERE id IN ($place) ORDER BY grade_level, section, name");
        $st->execute($ids);
        return $st->fetchAll();
    }

    public static function myClassroomIds(int $teacherId): array {
        $st = db()->prepare("SELECT classroom_id FROM teacher_classrooms WHERE teacher_id=?");
        $st->execute([$teacherId]);
        return array_map('intval', array_column($st->fetchAll(), 'classroom_id'));
    }

    private static function teacherHasClassroom(int $teacherId, int $classroomId): bool {
        $st = db()->prepare("SELECT 1 FROM teacher_classrooms WHERE teacher_id=? AND classroom_id=?");
        $st->execute([$teacherId, $classroomId]);
        return (bool)$st->fetchColumn();
    }

    private static function teachersWithClassrooms(): array {
        return db()->query("
            SELECT DISTINCT u.id, u.full_name, u.campus_id, c.name AS campus_name, i.name AS institution_name
              FROM users u
              JOIN campuses c ON c.id = u.campus_id
              JOIN institutions i ON i.id = c.institution_id
              JOIN teacher_classrooms tc ON tc.teacher_id = u.id
             WHERE u.role='teacher' AND u.is_active=1
          ORDER BY i.name, c.name, u.full_name
        ")->fetchAll();
    }

    private static function allClassroomsByTeacher(): array {
        $rows = db()->query("
            SELECT tc.teacher_id, cr.id, cr.name
              FROM teacher_classrooms tc
              JOIN classrooms cr ON cr.id = tc.classroom_id
          ORDER BY cr.grade_level, cr.section, cr.name
        ")->fetchAll();
        $map = [];
        foreach ($rows as $r) $map[(int)$r['teacher_id']][] = ['id' => (int)$r['id'], 'name' => $r['name']];
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
