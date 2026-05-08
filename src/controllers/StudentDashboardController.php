<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, requireRole, view};

class StudentDashboardController {
    public static function index(): void {
        $me = requireRole('student');
        $st = db()->prepare("
            SELECT ta.*, t.title AS test_title, t.time_limit_minutes,
                   (SELECT COUNT(*) FROM test_questions tq JOIN questions q ON q.id=tq.question_id WHERE tq.test_id=t.id AND q.is_physical=0) AS visible_q,
                   (SELECT COUNT(*) FROM test_questions tq JOIN questions q ON q.id=tq.question_id WHERE tq.test_id=t.id AND q.is_physical=1) AS phys_q
            FROM test_assignments ta
            JOIN tests t ON t.id = ta.test_id
            WHERE ta.student_id = ?
            ORDER BY ta.id DESC
        ");
        $st->execute([$me['id']]);
        $items = $st->fetchAll();
        view('student/dashboard', ['title' => 'Testlerim', 'me' => $me, 'items' => $items]);
    }
}
