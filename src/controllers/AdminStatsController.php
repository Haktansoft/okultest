<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, requireRole, view};

class AdminStatsController {
    public static function index(): void {
        $me  = requireRole('admin');
        $pdo = db();

        // ---- Filtreler ----
        $instId = isset($_GET['institution_id']) && $_GET['institution_id'] !== '' ? (int)$_GET['institution_id'] : 0;
        $campId = isset($_GET['campus_id'])      && $_GET['campus_id']      !== '' ? (int)$_GET['campus_id']      : 0;
        $status = (string)($_GET['status'] ?? '');
        if (!in_array($status, ['', 'done', 'undone'], true)) $status = '';
        $fromRaw = trim((string)($_GET['from'] ?? ''));
        $toRaw   = trim((string)($_GET['to']   ?? ''));
        $from = preg_match('/^\d{4}-\d{2}-\d{2}$/', $fromRaw) ? $fromRaw : '';
        $to   = preg_match('/^\d{4}-\d{2}-\d{2}$/', $toRaw)   ? $toRaw   : '';

        // ---- Filtre seçenekleri ----
        $institutions = $pdo->query("SELECT id, name FROM institutions ORDER BY name")->fetchAll();
        if ($instId > 0) {
            $st = $pdo->prepare("SELECT id, name FROM campuses WHERE institution_id=? ORDER BY name");
            $st->execute([$instId]);
            $campuses = $st->fetchAll();
        } else {
            $campuses = $pdo->query("
                SELECT c.id, c.name, i.name AS inst_name
                  FROM campuses c
                  JOIN institutions i ON i.id = c.institution_id
              ORDER BY i.name, c.name
            ")->fetchAll();
        }
        // Kampüs seçili ama kuruma ait değilse temizle
        if ($instId > 0 && $campId > 0) {
            $ok = false;
            foreach ($campuses as $c) { if ((int)$c['id'] === $campId) { $ok = true; break; } }
            if (!$ok) $campId = 0;
        }

        // ---- Öğrenci WHERE ----
        $where   = ["u.role='student'", "u.is_active=1"];
        $whereP  = [];
        if ($campId > 0) {
            $where[] = "u.campus_id=?";        $whereP[] = $campId;
        } elseif ($instId > 0) {
            $where[] = "c.institution_id=?";   $whereP[] = $instId;
        }
        $whereSql = implode(' AND ', $where);

        // ---- Tarih aralığı (started_at) — bir öğrencinin testi "uyguladı" sayılması için ----
        $dateClause = "ta.started_at IS NOT NULL";
        $dateP = [];
        if ($from !== '') {
            $dateClause .= " AND ta.started_at >= ?";
            $dateP[] = $from . ' 00:00:00';
        }
        if ($to !== '') {
            $dateClause .= " AND ta.started_at < ?";
            $dateP[] = date('Y-m-d 00:00:00', strtotime($to . ' +1 day'));
        }

        // ---- Kurum/Kampüs bazlı kırılım ----
        $sql = "
            SELECT
              i.id   AS inst_id,
              i.name AS inst_name,
              c.id   AS camp_id,
              c.name AS camp_name,
              COUNT(DISTINCT u.id) AS total_students,
              COUNT(DISTINCT CASE WHEN $dateClause THEN u.id END) AS done_students
            FROM users u
            JOIN campuses c     ON c.id = u.campus_id
            JOIN institutions i ON i.id = c.institution_id
            LEFT JOIN test_assignments ta ON ta.student_id = u.id
            WHERE $whereSql
            GROUP BY i.id, c.id
            ORDER BY i.name, c.name
        ";
        $st = $pdo->prepare($sql);
        $st->execute(array_merge($dateP, $whereP));
        $rows = $st->fetchAll();
        foreach ($rows as &$r) {
            $r['undone_students'] = (int)$r['total_students'] - (int)$r['done_students'];
        }
        unset($r);

        // ---- Toplamlar ----
        $totalStudents = array_sum(array_column($rows, 'total_students'));
        $totalDone     = array_sum(array_column($rows, 'done_students'));
        $totalUndone   = (int)$totalStudents - (int)$totalDone;

        // ---- Öğrenci listesi (durum filtresine göre) ----
        $sql2 = "
            SELECT
              u.id, u.full_name, u.grade_level, u.section,
              c.name AS camp_name, i.name AS inst_name,
              MAX(CASE WHEN $dateClause THEN ta.started_at  END) AS last_started_at,
              MAX(CASE WHEN $dateClause THEN ta.finished_at END) AS last_finished_at
            FROM users u
            JOIN campuses c     ON c.id = u.campus_id
            JOIN institutions i ON i.id = c.institution_id
            LEFT JOIN test_assignments ta ON ta.student_id = u.id
            WHERE $whereSql
            GROUP BY u.id, u.full_name, u.grade_level, u.section, c.name, i.name
        ";
        if ($status === 'done')   $sql2 .= " HAVING last_started_at IS NOT NULL";
        if ($status === 'undone') $sql2 .= " HAVING last_started_at IS NULL";
        $sql2 .= " ORDER BY i.name, c.name, u.full_name LIMIT 500";
        $st2 = $pdo->prepare($sql2);
        $st2->execute(array_merge($dateP, $whereP));
        $students = $st2->fetchAll();

        view('admin/stats/index', [
            'title'        => 'İstatistikler',
            'me'           => $me,
            'institutions' => $institutions,
            'campuses'     => $campuses,
            'filters'      => [
                'institution_id' => $instId,
                'campus_id'      => $campId,
                'status'         => $status,
                'from'           => $from,
                'to'             => $to,
            ],
            'rows'   => $rows,
            'totals' => [
                'students' => (int)$totalStudents,
                'done'     => (int)$totalDone,
                'undone'   => (int)$totalUndone,
            ],
            'students' => $students,
        ]);
    }
}
