<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, json, redirect, requireRole, view, recomputeScore};

class StudentTestController {
    public static function intro(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) { flash('err', 'Test bulunamadı.'); redirect('/student'); }
        if (in_array($a['status'], ['completed', 'needs_physical'], true)) {
            redirect('/student/tests/' . (int)$id . '/finished');
        }

        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM tests WHERE id=?");
        $st->execute([$a['test_id']]);
        $test = $st->fetch();

        $count = $pdo->prepare("
            SELECT
              SUM(CASE WHEN q.is_physical=0 THEN 1 ELSE 0 END) AS visible_q,
              SUM(CASE WHEN q.is_physical=1 THEN 1 ELSE 0 END) AS phys_q
            FROM test_questions tq JOIN questions q ON q.id=tq.question_id WHERE tq.test_id=?
        ");
        $count->execute([$a['test_id']]);
        $row = $count->fetch();

        view('student/test_intro', [
            '_layout' => 'layouts/base',
            'title' => $test['title'],
            'me' => $me, 'assignment' => $a, 'test' => $test,
            'visibleQ' => (int)$row['visible_q'], 'physQ' => (int)$row['phys_q'],
        ]);
    }

    public static function start(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) { flash('err', 'Test bulunamadı.'); redirect('/student'); }
        if (in_array($a['status'], ['completed', 'needs_physical'], true)) {
            redirect('/student/tests/' . (int)$id . '/finished');
        }

        // Öğrenci tarafında her zaman per-question mod (toplu mod öğretmen panelinden)
        $mode = 'per_question';

        $pdo = db();
        $upd = $pdo->prepare("UPDATE test_assignments SET status='in_progress', mode=?, started_at = COALESCE(started_at, NOW()) WHERE id=?");
        $upd->execute([$mode, $id]);

        $ev = $pdo->prepare("INSERT INTO attempt_events (assignment_id, event_type, payload) VALUES (?, 'start', ?)");
        $ev->execute([$id, json_encode(['mode' => $mode], JSON_UNESCAPED_UNICODE)]);

        redirect('/student/tests/' . (int)$id . '/run');
    }

    public static function run(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) { flash('err', 'Test bulunamadı.'); redirect('/student'); }
        if ($a['status'] !== 'in_progress') {
            redirect('/student/tests/' . (int)$id . '/' . (in_array($a['status'], ['completed','needs_physical'], true) ? 'finished' : 'intro'));
        }
        $data = self::loadQuestions((int)$id, (int)$a['test_id']);
        $data['assignment'] = $a;
        $data['_layout'] = 'layouts/base';
        $data['title'] = 'Test';
        $data['me'] = $me;
        view('student/test_runner', $data);
    }

    /** Eski mod=bulk atamaları run'a yönlendir; öğrenci için toplu yanıt yok. */
    public static function bulk(string $id): void {
        requireRole('student');
        redirect('/student/tests/' . (int)$id . '/run');
    }

    public static function autosave(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) json(['ok'=>false, 'error'=>'not_found'], 404);
        if ($a['status'] !== 'in_progress') json(['ok'=>false, 'error'=>'not_active'], 409);

        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (!is_array($body)) $body = $_POST;

        $answers = $body['answers'] ?? [];
        $timings = $body['timings'] ?? [];
        if (!is_array($answers)) $answers = [];
        if (!is_array($timings)) $timings = [];

        // Sadece bu testin görünür sorularına ait şıkları kabul et
        $pdo = db();
        $valid = $pdo->prepare("
            SELECT q.id AS qid, GROUP_CONCAT(o.id) AS oids
            FROM test_questions tq
            JOIN questions q ON q.id = tq.question_id AND q.is_physical = 0
            LEFT JOIN question_options o ON o.question_id = q.id
            WHERE tq.test_id = ?
            GROUP BY q.id
        ");
        $valid->execute([$a['test_id']]);
        $validMap = [];
        foreach ($valid->fetchAll() as $row) {
            $validMap[(int)$row['qid']] = array_map('intval', explode(',', (string)$row['oids']));
        }

        $upsert = $pdo->prepare("
            INSERT INTO attempt_answers (assignment_id, question_id, selected_option_id, time_spent_seconds)
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id),
                                    time_spent_seconds = VALUES(time_spent_seconds),
                                    answered_at = CURRENT_TIMESTAMP
        ");

        $accepted = 0;
        foreach ($answers as $qid => $oid) {
            $qid = (int)$qid;
            $oid = $oid === null ? null : (int)$oid;
            if (!isset($validMap[$qid])) continue;
            if ($oid !== null && !in_array($oid, $validMap[$qid], true)) continue;
            $time = isset($timings[$qid]) ? max(0, (int)$timings[$qid]) : 0;
            $upsert->execute([$id, $qid, $oid, $time]);
            $accepted++;
        }
        // log autosave (kompakt)
        $pdo->prepare("INSERT INTO attempt_events (assignment_id, event_type, payload) VALUES (?, 'autosave', ?)")
            ->execute([$id, json_encode(['n' => $accepted], JSON_UNESCAPED_UNICODE)]);

        json(['ok' => true, 'saved' => $accepted]);
    }

    public static function logEvent(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) json(['ok'=>false], 404);
        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true) ?: $_POST;
        $type = $body['type'] ?? '';
        $allowed = ['focus_question', 'blur_question', 'end'];
        if (!in_array($type, $allowed, true)) json(['ok'=>false], 400);
        $qid = isset($body['question_id']) ? (int)$body['question_id'] : null;
        $payload = $body['payload'] ?? null;
        $st = db()->prepare("INSERT INTO attempt_events (assignment_id, event_type, question_id, payload) VALUES (?, ?, ?, ?)");
        $st->execute([$id, $type, $qid, $payload ? json_encode($payload, JSON_UNESCAPED_UNICODE) : null]);
        json(['ok' => true]);
    }

    public static function submit(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) { flash('err', 'Test bulunamadı.'); redirect('/student'); }
        if ($a['status'] !== 'in_progress') redirect('/student/tests/' . (int)$id . '/finished');

        // Önce final autosave (varsa)
        $raw = file_get_contents('php://input') ?: '';
        $body = json_decode($raw, true);
        if (is_array($body) && (!empty($body['answers']) || !empty($body['timings']))) {
            $_REQUEST = array_merge($_REQUEST, $body);
            // submit ile birlikte gelen son cevapları kaydet
            $pdo = db();
            $valid = $pdo->prepare("
                SELECT q.id AS qid, GROUP_CONCAT(o.id) AS oids
                FROM test_questions tq
                JOIN questions q ON q.id = tq.question_id AND q.is_physical = 0
                LEFT JOIN question_options o ON o.question_id = q.id
                WHERE tq.test_id = ? GROUP BY q.id
            ");
            $valid->execute([$a['test_id']]);
            $validMap = [];
            foreach ($valid->fetchAll() as $row) {
                $validMap[(int)$row['qid']] = array_map('intval', explode(',', (string)$row['oids']));
            }
            $upsert = $pdo->prepare("
                INSERT INTO attempt_answers (assignment_id, question_id, selected_option_id, time_spent_seconds)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE selected_option_id = VALUES(selected_option_id),
                                        time_spent_seconds = VALUES(time_spent_seconds),
                                        answered_at = CURRENT_TIMESTAMP
            ");
            foreach (($body['answers'] ?? []) as $qid => $oid) {
                $qid = (int)$qid; $oid = $oid === null ? null : (int)$oid;
                if (!isset($validMap[$qid])) continue;
                if ($oid !== null && !in_array($oid, $validMap[$qid], true)) continue;
                $time = isset($body['timings'][$qid]) ? max(0, (int)$body['timings'][$qid]) : 0;
                $upsert->execute([$id, $qid, $oid, $time]);
            }
        }

        $pdo = db();
        // Fiziksel soru var mı?
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_questions tq JOIN questions q ON q.id=tq.question_id WHERE tq.test_id=? AND q.is_physical=1");
        $stmt->execute([$a['test_id']]);
        $hasPhys = (int)$stmt->fetchColumn() > 0;

        $newStatus = $hasPhys ? 'needs_physical' : 'completed';
        $pdo->prepare("UPDATE test_assignments SET status=?, finished_at = COALESCE(finished_at, NOW()) WHERE id=?")->execute([$newStatus, $id]);
        $pdo->prepare("INSERT INTO attempt_events (assignment_id, event_type) VALUES (?, 'submit')")->execute([$id]);
        recomputeScore((int)$id);

        if (strpos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false) {
            json(['ok' => true, 'status' => $newStatus, 'redirect' => "/student/tests/$id/finished"]);
        }
        redirect("/student/tests/$id/finished");
    }

    public static function finished(string $id): void {
        $me = requireRole('student');
        $a = self::loadMine((int)$id, (int)$me['id']);
        if (!$a) { flash('err', 'Test bulunamadı.'); redirect('/student'); }
        $st = db()->prepare("SELECT title FROM tests WHERE id=?");
        $st->execute([$a['test_id']]);
        $test = $st->fetch();
        view('student/finished', ['_layout' => 'layouts/base', 'title' => 'Test bitti', 'me' => $me, 'assignment' => $a, 'test' => $test]);
    }

    private static function loadMine(int $assignmentId, int $studentId): ?array {
        $st = db()->prepare("SELECT * FROM test_assignments WHERE id=? AND student_id=?");
        $st->execute([$assignmentId, $studentId]);
        $a = $st->fetch();
        return $a ?: null;
    }

    private static function loadQuestions(int $assignmentId, int $testId): array {
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM tests WHERE id=?");
        $st->execute([$testId]);
        $test = $st->fetch();

        $qs = $pdo->prepare("
            SELECT q.*, c.name AS category_name FROM test_questions tq
            JOIN questions q ON q.id = tq.question_id
            LEFT JOIN categories c ON c.id = q.category_id
            WHERE tq.test_id=? AND q.is_physical=0
            ORDER BY tq.sort_order, q.id
        ");
        $qs->execute([$testId]);
        $questions = $qs->fetchAll();

        $optsStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order, id");
        $mediaStmt= $pdo->prepare("SELECT * FROM media WHERE id=?");
        foreach ($questions as &$q) {
            $optsStmt->execute([$q['id']]);
            $q['options'] = $optsStmt->fetchAll();
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

        // Mevcut cevaplar (autosave'den)
        $sa = $pdo->prepare("SELECT question_id, selected_option_id, time_spent_seconds FROM attempt_answers WHERE assignment_id=?");
        $sa->execute([$assignmentId]);
        $serverAnswers = [];
        $serverTimings = [];
        foreach ($sa->fetchAll() as $r) {
            if ($r['selected_option_id'] !== null) $serverAnswers[(int)$r['question_id']] = (int)$r['selected_option_id'];
            $serverTimings[(int)$r['question_id']] = (int)$r['time_spent_seconds'];
        }

        // Süre limiti — kalan süre
        $remainingSeconds = null;
        if ($test['time_limit_minutes']) {
            $startedAt = $pdo->prepare("SELECT started_at FROM test_assignments WHERE id=?");
            $startedAt->execute([$assignmentId]);
            $sa2 = $startedAt->fetchColumn();
            if ($sa2) {
                $end = strtotime($sa2) + ((int)$test['time_limit_minutes'] * 60);
                $remainingSeconds = max(0, $end - time());
            }
        }

        return [
            'test' => $test,
            'questions' => $questions,
            'serverAnswers' => $serverAnswers,
            'serverTimings' => $serverTimings,
            'remainingSeconds' => $remainingSeconds,
        ];
    }
}
