<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherClassroomController {
    /** Öğretmenin atanmış sınıflarını listeler (sadece görüntüleme). Admin → /admin/classrooms'a yönlendirir. */
    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        if ($me['role'] === 'admin') {
            redirect('/admin/classrooms');
        }
        $st = db()->prepare("
            SELECT cr.*, c.name AS campus_name, i.name AS institution_name,
                   (SELECT COUNT(*) FROM users u WHERE u.classroom_id=cr.id AND u.role='student') AS scount
              FROM teacher_classrooms tc
              JOIN classrooms cr ON cr.id = tc.classroom_id
              JOIN campuses c ON c.id = cr.campus_id
              JOIN institutions i ON i.id = c.institution_id
             WHERE tc.teacher_id = ?
          ORDER BY cr.grade_level, cr.section, cr.name
        ");
        $st->execute([(int)$me['id']]);
        view('teacher/classrooms/index', ['title' => 'Sınıflarım', 'me' => $me, 'items' => $st->fetchAll()]);
    }
}
