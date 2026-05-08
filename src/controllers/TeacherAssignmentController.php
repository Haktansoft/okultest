<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherAssignmentController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        // Tüm atamalar — öğretmen-öğrenci bağı yok, herkes görür
        $items = db()->query("
            SELECT ta.*, t.title AS test_title, u.full_name AS student_name, te.full_name AS teacher_name
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            JOIN users u ON u.id = ta.student_id
            JOIN users te ON te.id = ta.teacher_id
            ORDER BY ta.id DESC
        ")->fetchAll();
        view('teacher/assignments/index', ['title' => 'Atamalar', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $students = db()->query("
            SELECT id, full_name FROM users
            WHERE role='student' AND is_active=1
            ORDER BY full_name
        ")->fetchAll();
        $tests = db()->query("SELECT id, title FROM tests ORDER BY title")->fetchAll();
        view('teacher/assignments/form', [
            'title' => 'Yeni Atama', 'me' => $me,
            'students' => $students, 'tests' => $tests,
        ]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $testId = (int)($_POST['test_id'] ?? 0);
        $sids   = array_map('intval', (array)($_POST['student_ids'] ?? []));
        if (!$testId || !$sids) {
            flash('err', 'Test ve en az bir öğrenci seçin.');
            redirect('/teacher/assignments/new');
        }

        // Öğrenci id'lerinin gerçekten student olduğunu doğrula
        $in = implode(',', array_fill(0, count($sids), '?'));
        $st = db()->prepare("SELECT id FROM users WHERE role='student' AND is_active=1 AND id IN ($in)");
        $st->execute($sids);
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
        requireRole('teacher', 'admin');
        $st = db()->prepare("DELETE FROM test_assignments WHERE id=? AND status IN ('pending','in_progress')");
        $st->execute([$id]);
        flash('ok', $st->rowCount() ? 'Atama silindi.' : 'Tamamlanmış atama silinemez.');
        redirect('/teacher/assignments');
    }

    /** Öğrenci adına toplu yanıt giriş ekranı */
    public static function bulkForm(string $id): void {
        $me = requireRole('teacher', 'admin');
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

        $optsStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order, id");
        $mediaStmt = $pdo->prepare("SELECT * FROM media WHERE id=?");

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

        foreach ($questions as &$q) {
            $optsStmt->execute([$q['id']]);
            $q['options'] = $optsStmt->fetchAll();
            $q['existing_option_id'] = $existingMap[(int)$q['id']] ?? 0;
            $q['is_blank']           = isset($blankSet[(int)$q['id']]);
            $q['prompt_media'] = null;
            if ($q['prompt_media_id']) {
                $mediaStmt->execute([$q['prompt_media_id']]);
                $q['prompt_media'] = $mediaStmt->fetch() ?: null;
            }
            foreach ($q['options'] as &$o) {
                $o['media'] = null;
                if ($o['media_id']) {
                    $mediaStmt->execute([$o['media_id']]);
                    $o['media'] = $mediaStmt->fetch() ?: null;
                }
            }
        }
        return ['assignment' => $a, 'questions' => $questions];
    }
}
