<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view, formatDuration, renderPdfFromView};

class TeacherResultController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $st = db()->query("
            SELECT ta.*, t.title AS test_title, u.full_name AS student_name, te.full_name AS teacher_name
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            JOIN users u ON u.id = ta.student_id
            JOIN users te ON te.id = ta.teacher_id
            WHERE ta.status IN ('completed','needs_physical')
            ORDER BY ta.finished_at DESC, ta.id DESC
        ");
        view('teacher/results/index', ['title' => 'Sonuçlar', 'me' => $me, 'items' => $st->fetchAll()]);
    }

    public static function show(string $id): void {
        $me = requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { flash('err', 'Sonuç bulunamadı.'); redirect('/teacher/results'); }
        $data['title'] = 'Sonuç — ' . $data['assignment']['student_name'];
        $data['me'] = $me;
        view('teacher/results/show', $data);
    }

    public static function pdf(string $id): void {
        requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }
        renderPdfFromView('pdf/result', $data, "sonuc-detayli-{$id}.pdf");
    }

    /** Özet sonuç PDF'i — sadece toplam skor, süre, soru sayısı */
    public static function summaryPdf(string $id): void {
        requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }

        $total = count($data['questions']);
        $answered = 0;
        $totalPossible = 0;
        foreach ($data['questions'] as $q) {
            $isPhys = (bool)$q['is_physical'];
            $ans = $isPhys ? $q['physical_answer'] : $q['answer'];
            if ($ans && !empty($ans['selected_option_id'])) $answered++;
            // Toplam mümkün puan: her sorudaki en yüksek şık puanı
            $maxOpt = 0;
            foreach ($q['options'] as $o) {
                if ((float)$o['score'] > $maxOpt) $maxOpt = (float)$o['score'];
            }
            $totalPossible += $maxOpt;
        }

        $data['total_questions']  = $total;
        $data['answered_questions'] = $answered;
        $data['total_possible']   = $totalPossible;
        renderPdfFromView('pdf/result_summary', $data, "sonuc-{$id}.pdf");
    }

    public static function incompletePdf(string $id): void {
        requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }
        // Sadece fiziksel veya cevapsız sorular
        $missing = [];
        foreach ($data['questions'] as $q) {
            if ($q['is_physical'] && empty($q['physical_answer'])) {
                $missing[] = $q;
                continue;
            }
            if (!$q['is_physical'] && empty($q['answer'])) {
                $missing[] = $q;
            }
        }
        $data['questions'] = $missing;
        renderPdfFromView('pdf/incomplete', $data, "eksik-{$id}.pdf");
    }

    private static function loadDetail(int $assignmentId, ?int $teacherId): ?array {
        $pdo = db();
        if ($teacherId === null) {
            $st = $pdo->prepare("
                SELECT ta.*, t.title AS test_title, t.description AS test_description,
                       u.full_name AS student_name
                FROM test_assignments ta
                JOIN tests t ON t.id = ta.test_id
                JOIN users u ON u.id = ta.student_id
                WHERE ta.id = ?
            ");
            $st->execute([$assignmentId]);
        } else {
            $st = $pdo->prepare("
                SELECT ta.*, t.title AS test_title, t.description AS test_description,
                       u.full_name AS student_name
                FROM test_assignments ta
                JOIN tests t ON t.id = ta.test_id
                JOIN users u ON u.id = ta.student_id
                WHERE ta.id = ? AND ta.teacher_id = ?
            ");
            $st->execute([$assignmentId, $teacherId]);
        }
        $a = $st->fetch();
        if (!$a) return null;

        $qs = $pdo->prepare("
            SELECT q.*, c.name AS category_name
            FROM test_questions tq
            JOIN questions q ON q.id = tq.question_id
            JOIN categories c ON c.id = q.category_id
            WHERE tq.test_id = ?
            ORDER BY tq.sort_order, q.id
        ");
        $qs->execute([$a['test_id']]);
        $questions = $qs->fetchAll();

        $optsStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id=? ORDER BY sort_order, id");
        $aaStmt   = $pdo->prepare("SELECT a.*, o.label AS option_label, o.score AS option_score FROM attempt_answers a LEFT JOIN question_options o ON o.id = a.selected_option_id WHERE a.assignment_id=? AND a.question_id=?");
        $paStmt   = $pdo->prepare("SELECT p.*, o.label AS option_label, o.score AS option_score FROM physical_answers p JOIN question_options o ON o.id = p.selected_option_id WHERE p.assignment_id=? AND p.question_id=?");

        foreach ($questions as &$q) {
            $optsStmt->execute([$q['id']]);
            $q['options'] = $optsStmt->fetchAll();
            $q['answer'] = null;
            $q['physical_answer'] = null;
            if ($q['is_physical']) {
                $paStmt->execute([$assignmentId, $q['id']]);
                $q['physical_answer'] = $paStmt->fetch() ?: null;
            } else {
                $aaStmt->execute([$assignmentId, $q['id']]);
                $q['answer'] = $aaStmt->fetch() ?: null;
            }
        }

        $totalDuration = 0;
        if ($a['started_at'] && $a['finished_at']) {
            $totalDuration = strtotime($a['finished_at']) - strtotime($a['started_at']);
        }

        return [
            'assignment' => $a,
            'questions' => $questions,
            'totalDuration' => max(0, $totalDuration),
        ];
    }
}
