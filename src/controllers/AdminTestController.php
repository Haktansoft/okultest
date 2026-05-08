<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminTestController {
    public static function index(): void {
        $me = requireRole('admin', 'teacher');
        $items = db()->query("
            SELECT t.*, (SELECT COUNT(*) FROM test_questions tq WHERE tq.test_id=t.id) AS qcount,
                       (SELECT COUNT(*) FROM test_assignments ta WHERE ta.test_id=t.id) AS acount
            FROM tests t ORDER BY t.id DESC")->fetchAll();
        view('admin/tests/index', ['title' => 'Testler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        view('admin/tests/form', ['title' => 'Yeni Test', 'me' => $me, 'item' => null]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        $title = trim((string)($_POST['title'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $time  = $_POST['time_limit_minutes'] ?? '';
        if ($title === '') { flash('err', 'Başlık gerekli.'); redirect('/admin/tests/new'); }
        $time = $time === '' ? null : (int)$time;
        $st = db()->prepare("INSERT INTO tests (title, description, time_limit_minutes, created_by) VALUES (?, ?, ?, ?)");
        $st->execute([$title, $desc !== '' ? $desc : null, $time, $me['id']]);
        $id = (int)db()->lastInsertId();
        flash('ok', 'Test oluşturuldu. Şimdi soru ekleyin.');
        redirect("/admin/tests/$id/questions");
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM tests WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Test bulunamadı.'); redirect('/admin/tests'); }
        view('admin/tests/form', ['title' => 'Test Düzenle', 'me' => $me, 'item' => $item]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        $title = trim((string)($_POST['title'] ?? ''));
        $desc  = trim((string)($_POST['description'] ?? ''));
        $time  = $_POST['time_limit_minutes'] ?? '';
        if ($title === '') { flash('err', 'Başlık gerekli.'); redirect("/admin/tests/$id/edit"); }
        $time = $time === '' ? null : (int)$time;
        $st = db()->prepare("UPDATE tests SET title=?, description=?, time_limit_minutes=? WHERE id=?");
        $st->execute([$title, $desc !== '' ? $desc : null, $time, $id]);
        flash('ok', 'Test güncellendi.');
        redirect('/admin/tests');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        $st = db()->prepare("DELETE FROM tests WHERE id=?");
        $st->execute([$id]);
        flash('ok', 'Test silindi.');
        redirect('/admin/tests');
    }

    public static function manageQuestions(string $id): void {
        $me = requireRole('admin', 'teacher');
        $st = db()->prepare("SELECT * FROM tests WHERE id=?");
        $st->execute([$id]);
        $test = $st->fetch();
        if (!$test) { flash('err', 'Test bulunamadı.'); redirect('/admin/tests'); }

        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

        $assigned = db()->prepare("
            SELECT q.id, q.prompt, q.is_physical, c.name AS category_name, tq.sort_order
            FROM test_questions tq
            JOIN questions q ON q.id = tq.question_id
            JOIN categories c ON c.id = q.category_id
            WHERE tq.test_id = ?
            ORDER BY tq.sort_order, q.id
        ");
        $assigned->execute([$id]);
        $assignedRows = $assigned->fetchAll();

        $availStmt = "
            SELECT q.id, q.prompt, q.is_physical, c.name AS category_name
            FROM questions q JOIN categories c ON c.id = q.category_id
            WHERE q.id NOT IN (SELECT question_id FROM test_questions WHERE test_id = ?)
        ";
        $params = [$id];
        $cat = (int)($_GET['category_id'] ?? 0);
        if ($cat > 0) { $availStmt .= " AND q.category_id = ?"; $params[] = $cat; }
        $availStmt .= " ORDER BY q.id DESC LIMIT 500";
        $avail = db()->prepare($availStmt);
        $avail->execute($params);
        $availRows = $avail->fetchAll();

        view('admin/tests/questions', [
            'title' => 'Test Soruları — ' . $test['title'],
            'me' => $me,
            'test' => $test,
            'assigned' => $assignedRows,
            'available' => $availRows,
            'cats' => $cats,
            'selectedCat' => $cat,
        ]);
    }

    public static function saveQuestions(string $id): void {
        requireRole('admin');
        $action = $_POST['action'] ?? '';
        $pdo = db();
        if ($action === 'add') {
            $ids = array_map('intval', (array)($_POST['question_ids'] ?? []));
            if (!$ids) { flash('err', 'Soru seçilmedi.'); redirect("/admin/tests/$id/questions"); }
            // mevcut max sort_order
            $st = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM test_questions WHERE test_id=?");
            $st->execute([$id]);
            $next = (int)$st->fetchColumn() + 1;
            $ins = $pdo->prepare("INSERT IGNORE INTO test_questions (test_id, question_id, sort_order) VALUES (?, ?, ?)");
            foreach ($ids as $qid) {
                $ins->execute([$id, $qid, $next++]);
            }
            flash('ok', count($ids) . ' soru eklendi.');
        } elseif ($action === 'remove') {
            $qid = (int)($_POST['question_id'] ?? 0);
            $pdo->prepare("DELETE FROM test_questions WHERE test_id=? AND question_id=?")->execute([$id, $qid]);
            flash('ok', 'Soru testten çıkarıldı.');
        } elseif ($action === 'reorder') {
            $orderRaw = (string)($_POST['order'] ?? '');
            $orders = array_filter(array_map('intval', explode(',', $orderRaw)));
            $upd = $pdo->prepare("UPDATE test_questions SET sort_order=? WHERE test_id=? AND question_id=?");
            $i = 1;
            foreach ($orders as $qid) {
                $upd->execute([$i++, $id, $qid]);
            }
            flash('ok', 'Sıralama güncellendi.');
        }
        redirect("/admin/tests/$id/questions");
    }

    public static function pdf(string $id): void {
        requireRole('admin', 'teacher');
        $pdo = db();
        $st = $pdo->prepare("SELECT * FROM tests WHERE id=?");
        $st->execute([$id]);
        $test = $st->fetch();
        if (!$test) { http_response_code(404); echo "Bulunamadı"; return; }

        $qs = $pdo->prepare("
            SELECT q.* FROM test_questions tq JOIN questions q ON q.id=tq.question_id
            WHERE tq.test_id=? ORDER BY tq.sort_order, q.id
        ");
        $qs->execute([$id]);
        $questions = $qs->fetchAll();
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
            $q['prompt_media'] = !empty($q['prompt_media_id']) ? ($mediaById[(int)$q['prompt_media_id']] ?? null) : null;
            foreach ($q['options'] as &$o) {
                $o['media'] = !empty($o['media_id']) ? ($mediaById[(int)$o['media_id']] ?? null) : null;
            }
            unset($o);
        }
        unset($q);

        \App\renderPdfFromView('pdf/test', [
            'test' => $test,
            'questions' => $questions,
        ], "test-{$test['id']}.pdf");
    }
}
