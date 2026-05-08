<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Kategori Düzenle' : 'Yeni Kategori' ?></h1>
    <div class="page-sub">Sorular bu kategori altında toplanacak.</div>
  </div>
  <a href="/admin/categories" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/categories/' . (int)$item['id'] . '/update' : '/admin/categories' ?>" class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Ad</label>
      <input class="form-control" name="name" required value="<?= e($item['name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Açıklama <span class="muted tiny">(opsiyonel)</span></label>
      <textarea class="form-control" name="description" rows="3"><?= e($item['description'] ?? '') ?></textarea>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/categories" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Kategori Ekle' ?></button>
  </div>
</form>
