<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view, formatDuration, renderPdfFromView};

class TeacherResultController {
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        $sql = "
            SELECT ta.*, t.title AS test_title, u.full_name AS student_name, te.full_name AS teacher_name,
                   u.campus_id AS student_campus_id, u.classroom_id AS student_classroom_id
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            JOIN users u ON u.id = ta.student_id
            JOIN users te ON te.id = ta.teacher_id
            WHERE ta.status IN ('completed','needs_physical')";
        $params = [];
        if (!$isAdmin) {
            $crIds = TeacherStudentController::myClassroomIds((int)$me['id']);
            if (!$crIds) {
                view('teacher/results/index', ['title' => 'Sonuçlar', 'me' => $me, 'items' => [], 'isAdmin' => false]);
                return;
            }
            $place = implode(',', array_fill(0, count($crIds), '?'));
            $sql .= " AND u.classroom_id IN ($place)";
            $params = $crIds;
        }
        $sql .= " ORDER BY ta.finished_at DESC, ta.id DESC";
        $st = db()->prepare($sql);
        $st->execute($params);
        view('teacher/results/index', ['title' => 'Sonuçlar', 'me' => $me, 'items' => $st->fetchAll(), 'isAdmin' => $isAdmin]);
    }

    public static function show(string $id): void {
        $me = requireRole('admin'); // detayı sadece admin görür
        $data = self::loadDetail((int)$id, null);
        if (!$data) { flash('err', 'Sonuç bulunamadı.'); redirect('/teacher/results'); }
        $data['title'] = 'Sonuç — ' . $data['assignment']['student_name'];
        $data['me'] = $me;
        view('teacher/results/show', $data);
    }

    public static function pdf(string $id): void {
        requireRole('admin'); // detaylı PDF sadece admin
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }
        renderPdfFromView('pdf/result', $data, "sonuc-detayli-{$id}.pdf");
    }

    /** Özet sonuç PDF'i — sadece toplam skor, süre, soru sayısı */
    public static function summaryPdf(string $id): void {
        $me = requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }
        if (!self::canAccess($me, $data)) { http_response_code(403); echo "Yetki yok"; return; }

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
        $me = requireRole('teacher', 'admin');
        $data = self::loadDetail((int)$id, null);
        if (!$data) { http_response_code(404); echo "Bulunamadı"; return; }
        if (!self::canAccess($me, $data)) { http_response_code(403); echo "Yetki yok"; return; }
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

    private static function canAccess(array $me, array $data): bool {
        if ($me['role'] === 'admin') return true;
        $crIds = TeacherStudentController::myClassroomIds((int)$me['id']);
        $studentCr = (int)($data['assignment']['student_classroom_id'] ?? 0);
        return $studentCr > 0 && in_array($studentCr, $crIds, true);
    }

    private static function loadDetail(int $assignmentId, ?int $teacherId): ?array {
        $pdo = db();
        $st = $pdo->prepare("
            SELECT ta.*, t.title AS test_title, t.description AS test_description,
                   u.full_name AS student_name, u.campus_id AS student_campus_id,
                   u.classroom_id AS student_classroom_id
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            JOIN users u ON u.id = ta.student_id
            WHERE ta.id = ?
        ");
        $st->execute([$assignmentId]);
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

        // Şıkları toplu çek
        $optionsByQ = [];
        if ($questions) {
            $qIds = array_map(fn($r) => (int)$r['id'], $questions);
            $place = implode(',', array_fill(0, count($qIds), '?'));
            $optsAll = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN ($place) ORDER BY question_id, sort_order, id");
            $optsAll->execute($qIds);
            foreach ($optsAll->fetchAll() as $o) $optionsByQ[(int)$o['question_id']][] = $o;
        }

        // Standart cevaplar (toplu)
        $aaAll = $pdo->prepare("SELECT a.*, o.label AS option_label, o.score AS option_score
                                  FROM attempt_answers a
                                  LEFT JOIN question_options o ON o.id = a.selected_option_id
                                 WHERE a.assignment_id = ?");
        $aaAll->execute([$assignmentId]);
        $aaByQ = [];
        foreach ($aaAll->fetchAll() as $r) $aaByQ[(int)$r['question_id']] = $r;

        // Fiziksel cevaplar (toplu)
        $paAll = $pdo->prepare("SELECT p.*, o.label AS option_label, o.score AS option_score
                                  FROM physical_answers p
                                  JOIN question_options o ON o.id = p.selected_option_id
                                 WHERE p.assignment_id = ?");
        $paAll->execute([$assignmentId]);
        $paByQ = [];
        foreach ($paAll->fetchAll() as $r) $paByQ[(int)$r['question_id']] = $r;

        foreach ($questions as &$q) {
            $q['options']         = $optionsByQ[(int)$q['id']] ?? [];
            $q['answer']          = null;
            $q['physical_answer'] = null;
            if ($q['is_physical']) {
                $q['physical_answer'] = $paByQ[(int)$q['id']] ?? null;
            } else {
                $q['answer'] = $aaByQ[(int)$q['id']] ?? null;
            }
        }
        unset($q);

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
