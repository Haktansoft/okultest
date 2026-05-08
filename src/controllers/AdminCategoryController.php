<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminCategoryController {
    public static function index(): void {
        $me = requireRole('admin');
        $items = db()->query("SELECT c.*, (SELECT COUNT(*) FROM questions q WHERE q.category_id=c.id) AS qcount FROM categories c ORDER BY c.name ASC")->fetchAll();
        view('admin/categories/index', ['title' => 'Kategoriler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        view('admin/categories/form', ['title' => 'Yeni Kategori', 'me' => $me, 'item' => null, 'audio' => null]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        $name   = trim((string)($_POST['name'] ?? ''));
        $desc   = trim((string)($_POST['description'] ?? ''));
        $audio  = self::validateAudioId($_POST['description_media_id'] ?? null);
        if ($name === '') { flash('err', 'Kategori adı boş olamaz.'); redirect('/admin/categories/new'); }
        try {
            $st = db()->prepare("INSERT INTO categories (name, description, description_media_id, created_by) VALUES (?, ?, ?, ?)");
            $st->execute([$name, $desc !== '' ? $desc : null, $audio, $me['id']]);
        } catch (\PDOException $ex) {
            flash('err', 'Aynı isimde bir kategori zaten var.');
            redirect('/admin/categories/new');
        }
        flash('ok', 'Kategori eklendi.');
        redirect('/admin/categories');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM categories WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Kategori bulunamadı.'); redirect('/admin/categories'); }
        $audio = null;
        if (!empty($item['description_media_id'])) {
            $ms = db()->prepare("SELECT id, original_name, kind FROM media WHERE id=? AND kind='audio'");
            $ms->execute([$item['description_media_id']]);
            $audio = $ms->fetch() ?: null;
        }
        view('admin/categories/form', [
            'title' => 'Kategori Düzenle', 'me' => $me, 'item' => $item, 'audio' => $audio,
        ]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        $name   = trim((string)($_POST['name'] ?? ''));
        $desc   = trim((string)($_POST['description'] ?? ''));
        $audio  = self::validateAudioId($_POST['description_media_id'] ?? null);
        if ($name === '') { flash('err', 'Kategori adı boş olamaz.'); redirect("/admin/categories/$id/edit"); }
        try {
            $st = db()->prepare("UPDATE categories SET name=?, description=?, description_media_id=? WHERE id=?");
            $st->execute([$name, $desc !== '' ? $desc : null, $audio, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Aynı isimde başka kategori var.');
            redirect("/admin/categories/$id/edit");
        }
        flash('ok', 'Kategori güncellendi.');
        redirect('/admin/categories');
    }

    private static function validateAudioId($raw): ?int {
        $id = (int)$raw;
        if ($id <= 0) return null;
        $st = db()->prepare("SELECT id FROM media WHERE id=? AND kind='audio'");
        $st->execute([$id]);
        return $st->fetchColumn() ? $id : null;
    }

    public static function delete(string $id): void {
        requireRole('admin');
        try {
            $st = db()->prepare("DELETE FROM categories WHERE id=?");
            $st->execute([$id]);
            flash('ok', 'Kategori silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Bu kategoriye bağlı sorular var, önce onları taşıyın/silin.');
        }
        redirect('/admin/categories');
    }
}
