<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view, recomputeScore};

class TeacherPhysicalController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        $sql = "
            SELECT ta.id, ta.status, ta.finished_at, t.title AS test_title, u.full_name AS student_name,
                   te.full_name AS teacher_name, u.campus_id AS student_campus_id,
                   u.classroom_id AS student_classroom_id,
                   (SELECT COUNT(*) FROM test_questions tq JOIN questions q ON q.id=tq.question_id WHERE tq.test_id=ta.test_id AND q.is_physical=1) AS phys_total,
                   (SELECT COUNT(*) FROM physical_answers pa WHERE pa.assignment_id=ta.id) AS phys_done
            FROM test_assignments ta
            JOIN tests t ON t.id=ta.test_id
            JOIN users u ON u.id=ta.student_id
            JOIN users te ON te.id=ta.teacher_id
            WHERE ta.status='needs_physical'";
        $params = [];
        if (!$isAdmin) {
            $crIds = TeacherStudentController::myClassroomIds((int)$me['id']);
            if (!$crIds) {
                view('teacher/physical/index', ['title' => 'Fiziksel Sorular', 'me' => $me, 'items' => []]);
                return;
            }
            $place = implode(',', array_fill(0, count($crIds), '?'));
            $sql .= " AND u.classroom_id IN ($place)";
            $params = $crIds;
        }
        $sql .= " ORDER BY ta.finished_at DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        view('teacher/physical/index', ['title' => 'Fiziksel Sorular', 'me' => $me, 'items' => $st->fetchAll()]);
    }

    public static function show(string $id): void {
        $me = requireRole('teacher', 'admin');
        $data = self::loadFor((int)$id, null);
        if (!$data) { flash('err', 'Bulunamadı.'); redirect('/teacher/physical'); }
        if (!self::canAccess($me, $data)) { flash('err', 'Yetki yok.'); redirect('/teacher/physical'); }
        $data['title'] = 'Fiziksel — ' . $data['assignment']['student_name'];
        $data['me'] = $me;
        view('teacher/physical/show', $data);
    }

    public static function save(string $id): void {
        $me = requireRole('teacher', 'admin');
        $data = self::loadFor((int)$id, null);
        if (!$data) { flash('err', 'Bulunamadı.'); redirect('/teacher/physical'); }
        if (!self::canAccess($me, $data)) { flash('err', 'Yetki yok.'); redirect('/teacher/physical'); }

        $picks = $_POST['option'] ?? [];
        if (!is_array($picks)) $picks = [];

        $pdo = db();
        $pdo->beginTransaction();
        try {
            $del = $pdo->prepare("DELETE FROM physical_answers WHERE assignment_id=? AND question_id=?");
            $ins = $pdo->prepare("INSERT INTO physical_answers (assignment_id, question_id, selected_option_id, entered_by_teacher_id) VALUES (?, ?, ?, ?)");
            foreach ($data['questions'] as $q) {
                $qid = (int)$q['id'];
                $optId = isset($picks[$qid]) ? (int)$picks[$qid] : 0;
                if ($optId <= 0) continue;
                // Şıkkın bu soruya ait olduğunu doğrula
                $valid = array_filter($q['options'], fn($o) => (int)$o['id'] === $optId);
                if (!$valid) continue;
                $del->execute([$id, $qid]);
                $ins->execute([$id, $qid, $optId, $me['id']]);
            }
            // Tamamlandı mı bak
            $stmt = $pdo->prepare("
                SELECT COUNT(*) FROM test_questions tq
                JOIN questions q ON q.id=tq.question_id
                LEFT JOIN physical_answers pa ON pa.question_id=q.id AND pa.assignment_id=?
                WHERE tq.test_id=? AND q.is_physical=1 AND pa.id IS NULL
            ");
            $stmt->execute([$id, $data['assignment']['test_id']]);
            $remaining = (int)$stmt->fetchColumn();
            if ($remaining === 0) {
                $pdo->prepare("UPDATE test_assignments SET status='completed' WHERE id=?")->execute([$id]);
            }
            recomputeScore((int)$id);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kaydedilemedi: ' . $ex->getMessage());
            redirect("/teacher/physical/$id");
        }
        flash('ok', 'Fiziksel yanıtlar kaydedildi.');
        redirect("/teacher/results/$id");
    }

    private static function canAccess(array $me, array $data): bool {
        if ($me['role'] === 'admin') return true;
        $crIds = TeacherStudentController::myClassroomIds((int)$me['id']);
        $studentCr = (int)($data['assignment']['student_classroom_id'] ?? 0);
        return $studentCr > 0 && in_array($studentCr, $crIds, true);
    }

    private static function loadFor(int $assignmentId, ?int $teacherId): ?array {
        $pdo = db();
        if ($teacherId === null) {
            $st = $pdo->prepare("
                SELECT ta.*, t.title AS test_title, u.full_name AS student_name, u.campus_id AS student_campus_id, u.classroom_id AS student_classroom_id
                FROM test_assignments ta
                JOIN tests t ON t.id=ta.test_id
                JOIN users u ON u.id=ta.student_id
                WHERE ta.id=?
            ");
            $st->execute([$assignmentId]);
        } else {
            $st = $pdo->prepare("
                SELECT ta.*, t.title AS test_title, u.full_name AS student_name
                FROM test_assignments ta
                JOIN tests t ON t.id=ta.test_id
                JOIN users u ON u.id=ta.student_id
                WHERE ta.id=? AND ta.teacher_id=?
            ");
            $st->execute([$assignmentId, $teacherId]);
        }
        $a = $st->fetch();
        if (!$a) return null;

        $qs = $pdo->prepare("
            SELECT q.*, c.name AS category_name
            FROM test_questions tq
            JOIN questions q ON q.id=tq.question_id
            JOIN categories c ON c.id=q.category_id
            WHERE tq.test_id=? AND q.is_physical=1
            ORDER BY tq.sort_order, q.id
        ");
        $qs->execute([$a['test_id']]);
        $questions = $qs->fetchAll();

        // Mevcut fiziksel yanıtlar (önceden girilmişse)
        $paAll = $pdo->prepare("SELECT * FROM physical_answers WHERE assignment_id=?");
        $paAll->execute([$assignmentId]);
        $paByQ = [];
        foreach ($paAll->fetchAll() as $r) $paByQ[(int)$r['question_id']] = $r;

        // Şıklar ve medyalar — toplu
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
            $q['options']      = $optionsByQ[(int)$q['id']] ?? [];
            $q['existing']     = $paByQ[(int)$q['id']] ?? null;
            $q['prompt_media'] = !empty($q['prompt_media_id']) ? ($mediaById[(int)$q['prompt_media_id']] ?? null) : null;
            foreach ($q['options'] as &$o) {
                $o['media'] = !empty($o['media_id']) ? ($mediaById[(int)$o['media_id']] ?? null) : null;
            }
            unset($o);
        }
        unset($q);
        return ['assignment' => $a, 'questions' => $questions];
    }
}
