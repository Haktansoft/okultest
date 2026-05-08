<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminInstitutionController {
    public static function index(): void {
        $me = requireRole('admin');
        $items = db()->query("
            SELECT i.*, m.path AS logo_path,
                   (SELECT COUNT(*) FROM campuses c WHERE c.institution_id=i.id) AS camp_count
              FROM institutions i
         LEFT JOIN media m ON m.id = i.logo_media_id AND m.kind='image'
          ORDER BY i.name
        ")->fetchAll();
        view('admin/institutions/index', ['title' => 'Kurumlar', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        view('admin/institutions/form', ['title' => 'Yeni Kurum', 'me' => $me, 'item' => null, 'logo' => null]);
    }

    public static function create(): void {
        requireRole('admin');
        $name = trim((string)($_POST['name'] ?? ''));
        $logo = self::validateImageId($_POST['logo_media_id'] ?? null);
        if ($name === '') { flash('err', 'Kurum adı gerekli.'); redirect('/admin/institutions/new'); }
        try {
            db()->prepare("INSERT INTO institutions (name, logo_media_id) VALUES (?, ?)")
                ->execute([$name, $logo]);
        } catch (\PDOException $ex) {
            flash('err', 'Aynı isimde kurum var.');
            redirect('/admin/institutions/new');
        }
        flash('ok', 'Kurum eklendi.');
        redirect('/admin/institutions');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM institutions WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Kurum bulunamadı.'); redirect('/admin/institutions'); }
        $logo = null;
        if (!empty($item['logo_media_id'])) {
            $ms = db()->prepare("SELECT id, original_name FROM media WHERE id=? AND kind='image'");
            $ms->execute([$item['logo_media_id']]);
            $logo = $ms->fetch() ?: null;
        }
        view('admin/institutions/form', ['title' => 'Kurum Düzenle', 'me' => $me, 'item' => $item, 'logo' => $logo]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        $name = trim((string)($_POST['name'] ?? ''));
        $logo = self::validateImageId($_POST['logo_media_id'] ?? null);
        if ($name === '') { flash('err', 'Kurum adı gerekli.'); redirect("/admin/institutions/$id/edit"); }
        try {
            db()->prepare("UPDATE institutions SET name=?, logo_media_id=? WHERE id=?")
                ->execute([$name, $logo, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Aynı isimde başka kurum var.');
            redirect("/admin/institutions/$id/edit");
        }
        flash('ok', 'Kurum güncellendi.');
        redirect('/admin/institutions');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        try {
            db()->prepare("DELETE FROM institutions WHERE id=?")->execute([$id]);
            flash('ok', 'Kurum silindi (ilgili kampüsler ve sınıflar da silindi).');
        } catch (\PDOException $ex) {
            flash('err', 'Silme hatası.');
        }
        redirect('/admin/institutions');
    }

    private static function validateImageId($raw): ?int {
        $id = (int)$raw;
        if ($id <= 0) return null;
        $st = db()->prepare("SELECT id FROM media WHERE id=? AND kind='image'");
        $st->execute([$id]);
        return $st->fetchColumn() ? $id : null;
    }
}
