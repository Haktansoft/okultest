<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Kampüs Düzenle' : 'Yeni Kampüs' ?></h1>
    <div class="page-sub">Kampüs bir kuruma bağlıdır.</div>
  </div>
  <a href="/admin/campuses" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/campuses/' . (int)$item['id'] . '/update' : '/admin/campuses' ?>" class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Kurum</label>
      <select class="form-select" name="institution_id" required>
        <option value="">— Seçin —</option>
        <?php foreach ($insts as $ins): ?>
          <option value="<?= (int)$ins['id'] ?>" <?= ($item && $item['institution_id'] == $ins['id']) ? 'selected' : '' ?>><?= e($ins['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Kampüs Adı</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= e($item['name'] ?? '') ?>">
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/campuses" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Kampüs Ekle' ?></button>
  </div>
</form>
