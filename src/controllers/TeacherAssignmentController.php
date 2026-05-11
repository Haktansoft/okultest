<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherAssignmentController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';

        $f = [
            'q'              => trim((string)($_GET['q'] ?? '')),
            'institution_id' => (int)($_GET['institution_id'] ?? 0),
            'campus_id'      => (int)($_GET['campus_id'] ?? 0),
            'grade_level'    => trim((string)($_GET['grade_level'] ?? '')),
            'section'        => trim((string)($_GET['section'] ?? '')),
            'status'         => trim((string)($_GET['status'] ?? '')),
            'test_id'        => (int)($_GET['test_id'] ?? 0),
        ];

        $where = [];
        $params = [];

        if (!$isAdmin) {
            $where[] = "u.campus_id = ?";
            $params[] = (int)($me['campus_id'] ?? 0);
        } else {
            if ($f['campus_id'] > 0) {
                $where[] = "u.campus_id = ?";
                $params[] = $f['campus_id'];
            } elseif ($f['institution_id'] > 0) {
                $where[] = "u.campus_id IN (SELECT id FROM campuses WHERE institution_id = ?)";
                $params[] = $f['institution_id'];
            }
        }
        if ($f['q'] !== '') {
            $where[] = "(u.full_name LIKE ? OR u.tc LIKE ? OR t.title LIKE ?)";
            $like = '%' . $f['q'] . '%';
            $params[] = $like; $params[] = $like; $params[] = $like;
        }
        if ($f['grade_level'] !== '' && in_array($f['grade_level'], TeacherStudentController::GRADE_LEVELS, true)) {
            $where[] = "u.grade_level = ?"; $params[] = $f['grade_level'];
        }
        if ($f['section'] !== '' && in_array($f['section'], TeacherStudentController::SECTIONS, true)) {
            $where[] = "u.section = ?"; $params[] = $f['section'];
        }
        $allowedStatus = ['pending','in_progress','needs_physical','completed'];
        if (in_array($f['status'], $allowedStatus, true)) {
            $where[] = "ta.status = ?"; $params[] = $f['status'];
        }
        if ($f['test_id'] > 0) { $where[] = "ta.test_id = ?"; $params[] = $f['test_id']; }

        $sql = "
            SELECT ta.*, t.title AS test_title, u.full_name AS student_name, u.tc AS student_tc, te.full_name AS teacher_name,
                   u.grade_level AS student_grade, u.section AS student_section
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            JOIN users u ON u.id = ta.student_id
            JOIN users te ON te.id = ta.teacher_id";
        if ($where) $sql .= " WHERE " . implode(' AND ', $where);
        $sql .= " ORDER BY ta.id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        $items = $st->fetchAll();

        $institutions = $isAdmin
            ? db()->query("SELECT id, name FROM institutions ORDER BY name")->fetchAll() : [];
        $campuses = $isAdmin
            ? db()->query("
                SELECT c.id, c.name, c.institution_id, i.name AS institution_name
                  FROM campuses c JOIN institutions i ON i.id = c.institution_id
              ORDER BY i.name, c.name
            ")->fetchAll() : [];
        $tests = db()->query("SELECT id, title FROM tests ORDER BY title")->fetchAll();

        view('teacher/assignments/index', [
            'title' => 'Testler', 'me' => $me, 'items' => $items,
            'filters' => $f,
            'institutions' => $institutions, 'campuses' => $campuses, 'tests' => $tests,
            'gradeLevels' => TeacherStudentController::GRADE_LEVELS,
            'sections' => TeacherStudentController::SECTIONS,
        ]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        if ($isAdmin) {
            $students = db()->query("
                SELECT id, full_name FROM users
                WHERE role='student' AND is_active=1
                ORDER BY full_name
            ")->fetchAll();
        } else {
            $st = db()->prepare("
                SELECT id, full_name FROM users
                WHERE role='student' AND is_active=1 AND campus_id=?
                ORDER BY full_name
            ");
            $st->execute([(int)($me['campus_id'] ?? 0)]);
            $students = $st->fetchAll();
        }
        $tests = db()->query("SELECT id, title FROM tests ORDER BY title")->fetchAll();
        view('teacher/assignments/form', [
            'title' => 'Yeni Atama', 'me' => $me,
            'students' => $students, 'tests' => $tests,
        ]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        $testId = (int)($_POST['test_id'] ?? 0);
        $sids   = array_map('intval', (array)($_POST['student_ids'] ?? []));
        if (!$testId || !$sids) {
            flash('err', 'Test ve en az bir öğrenci seçin.');
            redirect('/teacher/assignments/new');
        }

        // Öğrenci id'lerinin gerçekten student olduğunu (ve teacher ise aynı kampüste) doğrula
        $in = implode(',', array_fill(0, count($sids), '?'));
        $where = "role='student' AND is_active=1 AND id IN ($in)";
        $params = $sids;
        if (!$isAdmin) {
            $where .= " AND campus_id = ?";
            $params[] = (int)($me['campus_id'] ?? 0);
        }
        $st = db()->prepare("SELECT id FROM users WHERE $where");
        $st->execute($params);
        $valid = array_column($st->fetchAll(), 'id');
        if (!$valid) { flash('err', 'Geçerli öğrenci yok.'); redirect('/teacher/assignments'); }

        $ins = db()->prepare("
            INSERT IGNORE INTO test_assignments (test_id, student_id, teacher_id, status)
            VALUES (?, ?, ?, 'pending')
        ");
        $count = 0;
        foreach ($valid as $sid) {
            $ins->execute([$testId, $sid, $me['id']]);
            if ($ins->rowCount() > 0) $count++;
        }
        flash('ok', "$count atama oluşturuldu.");
        redirect('/teacher/assignments');
    }

    public static function delete(string $id): void {
        $me = requireRole('teacher', 'admin');
        if (!self::canTouchAssignment((int)$id, $me)) {
            flash('err', 'Yetki yok.'); redirect('/teacher/assignments');
        }
        $st = db()->prepare("DELETE FROM test_assignments WHERE id=? AND status IN ('pending','in_progress')");
        $st->execute([$id]);
        flash('ok', $st->rowCount() ? 'Atama silindi.' : 'Tamamlanmış atama silinemez.');
        redirect('/teacher/assignments');
    }

    /** Atamayı tamamen sıfırla — öğrenci testi yeniden çözebilsin */
    public static function reset(string $id): void {
        $me = requireRole('teacher', 'admin');
        if (!self::canTouchAssignment((int)$id, $me)) {
            flash('err', 'Yetki yok.'); redirect('/teacher/assignments');
        }
        $pdo = db();
        $st = $pdo->prepare("SELECT id FROM test_assignments WHERE id=?");
        $st->execute([$id]);
        if (!$st->fetchColumn()) {
            flash('err', 'Atama bulunamadı.');
            redirect('/teacher/assignments');
        }
        try {
            $pdo->beginTransaction();
            $pdo->prepare("DELETE FROM attempt_answers   WHERE assignment_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM physical_answers  WHERE assignment_id=?")->execute([$id]);
            $pdo->prepare("DELETE FROM attempt_events    WHERE assignment_id=?")->execute([$id]);
            $pdo->prepare("
                UPDATE test_assignments
                   SET status='pending',
                       mode=NULL,
                       started_at=NULL,
                       finished_at=NULL,
                       total_score=NULL
                 WHERE id=?
            ")->execute([$id]);
            $pdo->commit();
        } catch (\PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('err', 'Sıfırlama sırasında hata oluştu.');
            redirect('/teacher/assignments');
        }
        flash('ok', 'Atama sıfırlandı — öğrenci testi yeniden çözebilir.');
        redirect('/teacher/assignments');
    }

    /** Öğrenci gibi çöz — testi başlatır (gerekirse) ve öğrenci runner'ına yönlendirir. */
    public static function runAsStudent(string $id): void {
        $me = requireRole('teacher', 'admin');
        if (!self::canTouchAssignment((int)$id, $me)) {
            flash('err', 'Yetki yok.'); redirect('/teacher/assignments');
        }
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM test_assignments WHERE id=?");
        $st->execute([$id]);
        $a = $st->fetch();
        if (!$a) { flash('err', 'Atama bulunamadı.'); redirect('/teacher/assignments'); }

        if (in_array($a['status'], ['completed','needs_physical'], true)) {
            flash('err', 'Bu test tamamlanmış. Yeniden çözmek için önce sıfırla.');
            redirect('/teacher/assignments');
        }

        if ($a['status'] === 'pending') {
            $pdo->prepare("UPDATE test_assignments SET status='in_progress', mode='per_question', started_at=COALESCE(started_at, NOW()) WHERE id=?")
                ->execute([$id]);
            $pdo->prepare("INSERT INTO attempt_events (assignment_id, event_type, payload) VALUES (?, 'start', ?)")
                ->execute([$id, json_encode(['mode' => 'per_question', 'by' => 'teacher_proxy'], JSON_UNESCAPED_UNICODE)]);
        }
        redirect('/student/tests/' . (int)$id . '/run');
    }

    /** Öğrenci adına toplu yanıt giriş ekranı */
    public static function bulkForm(string $id): void {
        $me = requireRole('teacher', 'admin');
        if (!self::canTouchAssignment((int)$id, $me)) {
            flash('err', 'Yetki yok.'); redirect('/teacher/assignments');
        }
        $data = self::loadAssignmentForBulk((int)$id);
        if (!$data) { flash('err', 'Atama bulunamadı.'); redirect('/teacher/assignments'); }
        if (!in_array($data['assignment']['status'], ['pending','in_progress'], true)) {
            flash('err', 'Bu atama tamamlanmış, toplu giriş yapılamaz.');
            redirect('/teacher/assignments');
        }
        $data['title'] = 'Toplu Yanıt — ' . $data['assignment']['student_name'];
        $data['me'] = $me;
        view('teacher/assignments/bulk', $data);
    }

    public static function bulkSave(string $id): void {
        $me = requireRole('teacher', 'admin');
        if (!self::canTouchAssignment((int)$id, $me)) {
            flash('err', 'Yetki yok.'); redirect('/teacher/assignments');
        }
        $data = self::loadAssignmentForBulk((int)$id);
        if (!$data) { flash('err', 'Atama bulunamadı.'); redirect('/teacher/assignments'); }
        if (!in_array($data['assignment']['status'], ['pending','in_progress'], true)) {
            flash('err', 'Bu atama tamamlanmış.');
            redirect('/teacher/assignments');
        }

        $picks = $_POST['option'] ?? [];
        if (!is_array($picks)) $picks = [];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            // Atamayı in_progress yap (henüz değilse) ve mode=bulk + started_at
            $pdo->prepare("UPDATE test_assignments SET status='in_progress', mode='bulk', started_at=COALESCE(started_at, NOW()) WHERE id=?")->execute([$id]);

            $upsert = $pdo->prepare("
                INSERT INTO attempt_answers (assignment_id, question_id, selected_option_id, time_spent_seconds)
                VALUES (?, ?, ?, 0)
                ON DUPLICATE KEY UPDATE selected_option_id=VALUES(selected_option_id), answered_at=CURRENT_TIMESTAMP
            ");
            foreach ($data['questions'] as $q) {
                $qid = (int)$q['id'];
                $oid = isset($picks[$qid]) ? (int)$picks[$qid] : 0;
                if ($oid <= 0) continue;
                $valid = array_filter($q['options'], fn($o) => (int)$o['id'] === $oid);
                if (!$valid) continue;
                $upsert->execute([$id, $qid, $oid]);
            }
            // Cevaplanmamış fiziksel soru kaldı mı? Hepsi cevaplandıysa completed.
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM test_questions tq
                JOIN questions q ON q.id=tq.question_id
                LEFT JOIN attempt_answers aa ON aa.question_id=q.id AND aa.assignment_id=?
                WHERE tq.test_id=? AND q.is_physical=1
                AND (aa.selected_option_id IS NULL)
            ");
            $stmt->execute([$id, $data['assignment']['test_id']]);
            $unansweredPhys = (int)$stmt->fetchColumn();
            $newStatus = $unansweredPhys > 0 ? 'needs_physical' : 'completed';
            $pdo->prepare("UPDATE test_assignments SET status=?, finished_at=COALESCE(finished_at, NOW()) WHERE id=?")->execute([$newStatus, $id]);
            $pdo->prepare("INSERT INTO attempt_events (assignment_id, event_type, payload) VALUES (?, 'submit', ?)")
                ->execute([$id, json_encode(['by_teacher' => (int)$me['id']], JSON_UNESCAPED_UNICODE)]);
            \App\recomputeScore((int)$id);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kaydedilemedi: ' . $ex->getMessage());
            redirect("/teacher/assignments/$id/bulk");
        }
        flash('ok', 'Yanıtlar kaydedildi.');
        redirect('/teacher/results/' . (int)$id);
    }

    private static function canTouchAssignment(int $assignmentId, array $me): bool {
        if ($me['role'] === 'admin') return true;
        $myCampus = (int)($me['campus_id'] ?? 0);
        if ($myCampus <= 0) return false;
        $st = db()->prepare("
            SELECT u.campus_id FROM test_assignments ta
            JOIN users u ON u.id = ta.student_id
            WHERE ta.id=?
        ");
        $st->execute([$assignmentId]);
        $cid = (int)$st->fetchColumn();
        return $cid > 0 && $cid === $myCampus;
    }

    private static function loadAssignmentForBulk(int $assignmentId): ?array {
        $pdo = db();
        $st = $pdo->prepare("
            SELECT ta.*, t.title AS test_title, u.full_name AS student_name
            FROM test_assignments ta
            JOIN tests t ON t.id=ta.test_id
            JOIN users u ON u.id=ta.student_id
            WHERE ta.id=?
        ");
        $st->execute([$assignmentId]);
        $a = $st->fetch();
        if (!$a) return null;

        // Tüm sorular — öğretmen fiziksel olanları da bu ekrandan çözebilir
        $qs = $pdo->prepare("
            SELECT q.*, c.name AS category_name
            FROM test_questions tq
            JOIN questions q ON q.id=tq.question_id
            JOIN categories c ON c.id=q.category_id
            WHERE tq.test_id=?
            ORDER BY tq.sort_order, q.id
        ");
        $qs->execute([$a['test_id']]);
        $questions = $qs->fetchAll();

        // Mevcut yanıtlar:
        //  - selected_option_id != NULL  → öğrenci cevapladı
        //  - selected_option_id == NULL  → öğrenci bilinçli olarak boş bıraktı
        //  - hiç satır yok               → soruya hiç dokunmadı
        $existing = $pdo->prepare("SELECT question_id, selected_option_id FROM attempt_answers WHERE assignment_id=?");
        $existing->execute([$assignmentId]);
        $existingMap = [];
        $blankSet = [];
        foreach ($existing->fetchAll() as $r) {
            $qid = (int)$r['question_id'];
            if ($r['selected_option_id'] === null) {
                $blankSet[$qid] = true;
            } else {
                $existingMap[$qid] = (int)$r['selected_option_id'];
            }
        }
        // Fiziksel sorulara öğretmen daha önce yanıt girmişse onu da yükle
        $physical = $pdo->prepare("SELECT question_id, selected_option_id FROM physical_answers WHERE assignment_id=?");
        $physical->execute([$assignmentId]);
        foreach ($physical->fetchAll() as $r) {
            $existingMap[(int)$r['question_id']] = (int)$r['selected_option_id'];
        }

        // Tüm şıkları + medyaları tek seferde topla
        $optionsByQ = [];
        $mediaIds = [];
        if ($questions) {
            $qIds = array_map(fn($r) => (int)$r['id'], $questions);
            $place = implode(',', array_fill(0, count($qIds), '?'));
            $optsAll = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN ($place) ORDER BY question_id, sort_order, id");
            $optsAll->execute($qIds);
            foreach ($optsAll->fetchAll() as $o) {
                $optionsByQ[(int)$o['question_id']][] = $o;
                if (!empty($o['media_id'])) $mediaIds[(int)$o['media_id']] = true;
            }
            foreach ($questions as $q) {
                if (!empty($q['prompt_media_id'])) $mediaIds[(int)$q['prompt_media_id']] = true;
            }
        }
        $mediaById = [];
        if ($mediaIds) {
            $ids = array_keys($mediaIds);
            $place = implode(',', array_fill(0, count($ids), '?'));
            $mst = $pdo->prepare("SELECT * FROM media WHERE id IN ($place)");
            $mst->execute($ids);
            foreach ($mst->fetchAll() as $m) $mediaById[(int)$m['id']] = $m;
        }

        foreach ($questions as &$q) {
            $q['options']            = $optionsByQ[(int)$q['id']] ?? [];
            $q['existing_option_id'] = $existingMap[(int)$q['id']] ?? 0;
            $q['is_blank']           = isset($blankSet[(int)$q['id']]);
            $q['prompt_media']       = !empty($q['prompt_media_id']) ? ($mediaById[(int)$q['prompt_media_id']] ?? null) : null;
            foreach ($q['options'] as &$o) {
                $o['media'] = !empty($o['media_id']) ? ($mediaById[(int)$o['media_id']] ?? null) : null;
            }
            unset($o);
        }
        unset($q);
        return ['assignment' => $a, 'questions' => $questions];
    }
}
