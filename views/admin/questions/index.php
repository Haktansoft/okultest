<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sorular</h1>
    <div class="page-sub">Tüm soru havuzun. Kategoriye göre filtreleyebilirsin.</div>
  </div>
  <div class="d-flex gap-2">
    <a href="/admin/questions/import" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet"></i> XLSX İçe Aktar</a>
    <a href="/admin/questions/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Soru</a>
  </div>
</div>

<form class="card mb-3" method="get"><div class="card-body py-2">
  <div class="d-flex gap-2 align-items-center">
    <label class="form-label m-0 muted tiny">Kategori</label>
    <select name="category_id" class="form-select form-select-sm" style="max-width:240px" onchange="this.form.submit()">
      <option value="0">Tümü</option>
      <?php foreach ($cats as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= $selectedCat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
</div></form>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Soru</th><th>Kategori</th><th class="text-end">Şık</th><th class="text-end">Top. Puan</th><th>Tip</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6">
          <div class="empty-state"><div class="icon"><i class="bi bi-question-square"></i></div>Henüz soru yok.</div>
        </td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td style="max-width:520px"><?= e(mb_strimwidth(strip_tags($i['prompt']),0,140,'…','UTF-8')) ?></td>
          <td><span class="badge text-bg-light"><?= e($i['category_name']) ?></span></td>
          <td class="text-end"><?= (int)$i['option_count'] ?></td>
          <td class="text-end"><?= e($i['total_score']) ?></td>
          <td><?= $i['is_physical'] ? '<span class="badge text-bg-warning">Fiziksel</span>' : '<span class="badge text-bg-secondary">Standart</span>' ?></td>
          <td class="text-end">
            <a href="/admin/questions/<?= (int)$i['id'] ?>/edit" class="btn btn-sm btn-outline-secondary">Düzenle</a>
            <form method="post" action="/admin/questions/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
