<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Kategoriler</h1>
    <div class="page-sub">Soruları gruplandırmak için kategoriler.</div>
  </div>
  <a href="/admin/categories/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Kategori</a>
</div>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Ad</th><th>Açıklama</th><th class="text-end">Soru</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4">
          <div class="empty-state"><div class="icon"><i class="bi bi-folder-x"></i></div>Henüz kategori eklenmemiş.</div>
        </td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="muted"><?= e($i['description']) ?></td>
          <td class="text-end"><?= e((string)$i['qcount']) ?></td>
          <td class="text-end">
            <a href="/admin/categories/<?= (int)$i['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Düzenle</a>
            <form method="post" action="/admin/categories/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
