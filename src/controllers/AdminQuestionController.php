<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminQuestionController {
    public static function index(): void {
        $me = requireRole('admin');
        $cat = (int)($_GET['category_id'] ?? 0);
        $sql = "SELECT q.*, c.name AS category_name,
                  (SELECT COUNT(*) FROM question_options o WHERE o.question_id=q.id) AS option_count,
                  (SELECT COALESCE(SUM(o.score), 0) FROM question_options o WHERE o.question_id=q.id) AS total_score
                FROM questions q
                JOIN categories c ON c.id=q.category_id";
        $params = [];
        if ($cat > 0) { $sql .= " WHERE q.category_id=?"; $params[] = $cat; }
        $sql .= " ORDER BY q.id DESC LIMIT 500";
        $st = db()->prepare($sql); $st->execute($params);
        $items = $st->fetchAll();
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        view('admin/questions/index', ['title' => 'Sorular', 'me' => $me, 'items' => $items, 'cats' => $cats, 'selectedCat' => $cat]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        if (!$cats) {
            flash('err', 'Önce en az bir kategori oluşturun.');
            redirect('/admin/categories/new');
        }
        $old = self::pullOld();
        view('admin/questions/form', [
            'title' => 'Yeni Soru', 'me' => $me, 'cats' => $cats,
            'item' => $old['item'] ?? null,
            'options' => $old['options'] ?? null,
            'promptMedia' => $old['promptMedia'] ?? null,
        ]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        [$payload, $err] = self::validate();
        if ($err) { self::keepOld(); flash('err', $err); redirect('/admin/questions/new'); }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("INSERT INTO questions (category_id, prompt, prompt_media_id, is_physical, created_by) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$payload['category_id'], $payload['prompt'], $payload['prompt_media_id'], $payload['is_physical'], $me['id']]);
            $qid = (int)$pdo->lastInsertId();
            self::saveOptions($qid, $payload['options']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kayıt hatası: ' . $ex->getMessage());
            redirect('/admin/questions/new');
        }
        flash('ok', 'Soru eklendi.');
        redirect('/admin/questions');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM questions WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Soru bulunamadı.'); redirect('/admin/questions'); }
        $os = db()->prepare("
            SELECT o.*, m.kind AS media_kind, m.original_name AS media_name
            FROM question_options o
            LEFT JOIN media m ON m.id = o.media_id
            WHERE o.question_id = ?
            ORDER BY o.sort_order, o.id
        ");
        $os->execute([$id]);
        $options = $os->fetchAll();
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        $pm = null;
        if ($item['prompt_media_id']) {
            $pms = db()->prepare("SELECT * FROM media WHERE id=?");
            $pms->execute([$item['prompt_media_id']]);
            $pm = $pms->fetch() ?: null;
        }
        view('admin/questions/form', ['title' => 'Soru Düzenle', 'me' => $me, 'cats' => $cats, 'item' => $item, 'options' => $options, 'promptMedia' => $pm]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        [$payload, $err] = self::validate();
        if ($err) { self::keepOld(); flash('err', $err); redirect("/admin/questions/$id/edit"); }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("UPDATE questions SET category_id=?, prompt=?, prompt_media_id=?, is_physical=? WHERE id=?");
            $st->execute([$payload['category_id'], $payload['prompt'], $payload['prompt_media_id'], $payload['is_physical'], $id]);
            $pdo->prepare("DELETE FROM question_options WHERE question_id=?")->execute([$id]);
            self::saveOptions((int)$id, $payload['options']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kayıt hatası: ' . $ex->getMessage());
            redirect("/admin/questions/$id/edit");
        }
        flash('ok', 'Soru güncellendi.');
        redirect('/admin/questions');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        try {
            $st = db()->prepare("DELETE FROM questions WHERE id=?");
            $st->execute([$id]);
            flash('ok', 'Soru silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Bu soru bir testte kullanılıyor olabilir, önce testten çıkarın.');
        }
        redirect('/admin/questions');
    }

    private static function validate(): array {
        $cat = (int)($_POST['category_id'] ?? 0);
        $prompt = trim((string)($_POST['prompt'] ?? ''));
        $isPhys = !empty($_POST['is_physical']) ? 1 : 0;
        $promptMediaId = !empty($_POST['prompt_media_id']) ? (int)$_POST['prompt_media_id'] : null;

        if ($cat <= 0) return [null, 'Kategori seçilmeli.'];
        if ($prompt === '') return [null, 'Soru metni boş olamaz.'];

        $labels    = $_POST['option_label']    ?? [];
        $scores    = $_POST['option_score']    ?? [];
        $medias    = $_POST['option_media_id'] ?? [];
        $correct   = $_POST['option_correct']  ?? []; // index -> "1"
        if (!is_array($correct)) $correct = [];
        $count = count($labels);
        if ($count < 2) return [null, 'En az 2 şık girilmeli.'];

        $opts = [];
        for ($i = 0; $i < $count; $i++) {
            $lbl = trim((string)$labels[$i]);
            $mediaId = !empty($medias[$i]) ? (int)$medias[$i] : null;
            if ($lbl === '' && !$mediaId) continue;

            $isCorrect = !empty($correct[$i]);
            $rawScore = isset($scores[$i]) && $scores[$i] !== '' ? (float)$scores[$i] : null;
            // "Doğru" işaretliyse ve özel puan verilmediyse 1 puan ata; değilse manuel girilen puan (yoksa 0)
            $score = $isCorrect
                ? ($rawScore !== null && $rawScore > 0 ? $rawScore : 1)
                : ($rawScore ?? 0);

            $opts[] = ['label' => $lbl, 'score' => $score, 'media_id' => $mediaId, 'sort_order' => $i];
        }
        if (count($opts) < 2) return [null, 'En az 2 şık girilmeli.'];

        $hasScore = array_filter($opts, fn($o) => $o['score'] > 0);
        if (!$hasScore) return [null, 'En az bir şıkkın puanı 0\'dan büyük olmalı (Doğru tik veya manuel puan).'];

        return [[
            'category_id' => $cat,
            'prompt' => $prompt,
            'prompt_media_id' => $promptMediaId,
            'is_physical' => $isPhys,
            'options' => $opts,
        ], null];
    }

    /** Validation hatası sonrası form alanlarını flash'a koy ki form yeniden açıldığında dolu gelsin. */
    private static function keepOld(): void {
        \App\startSession();
        $labels   = $_POST['option_label']    ?? [];
        $scores   = $_POST['option_score']    ?? [];
        $medias   = $_POST['option_media_id'] ?? [];
        $correct  = $_POST['option_correct']  ?? [];
        if (!is_array($correct)) $correct = [];
        $opts = [];
        for ($i = 0, $n = count($labels); $i < $n; $i++) {
            $opts[] = [
                'label'      => (string)$labels[$i],
                'score'      => isset($scores[$i]) && $scores[$i] !== '' ? (float)$scores[$i] : 0,
                'media_id'   => !empty($medias[$i]) ? (int)$medias[$i] : null,
                'is_correct' => !empty($correct[$i]),
            ];
        }
        $_SESSION['_old_question'] = [
            'item' => [
                'category_id'     => (int)($_POST['category_id'] ?? 0),
                'prompt'          => (string)($_POST['prompt'] ?? ''),
                'is_physical'     => !empty($_POST['is_physical']) ? 1 : 0,
                'prompt_media_id' => !empty($_POST['prompt_media_id']) ? (int)$_POST['prompt_media_id'] : null,
            ],
            'options' => $opts,
        ];
    }

    private static function pullOld(): array {
        \App\startSession();
        if (empty($_SESSION['_old_question'])) return [];
        $old = $_SESSION['_old_question'];
        unset($_SESSION['_old_question']);

        // promptMedia bilgisini de getirsin
        $promptMedia = null;
        if (!empty($old['item']['prompt_media_id'])) {
            $st = db()->prepare("SELECT * FROM media WHERE id=?");
            $st->execute([$old['item']['prompt_media_id']]);
            $promptMedia = $st->fetch() ?: null;
        }
        $old['promptMedia'] = $promptMedia;
        return $old;
    }

    private static function saveOptions(int $qid, array $opts): void {
        $st = db()->prepare("INSERT INTO question_options (question_id, label, media_id, score, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($opts as $o) {
            $st->execute([$qid, $o['label'], $o['media_id'], $o['score'], $o['sort_order']]);
        }
    }
}
