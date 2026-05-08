<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Test Düzenle' : 'Yeni Test' ?></h1>
    <div class="page-sub">Süreyi boş bırakırsan limitsiz olur. Oluşturduktan sonra soru ekleyebilirsin.</div>
  </div>
  <a href="/admin/tests" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/tests/' . (int)$item['id'] . '/update' : '/admin/tests' ?>" class="card" style="max-width:680px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Başlık</label>
      <input class="form-control" name="title" required value="<?= e($item['title'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Açıklama <span class="muted tiny">(opsiyonel)</span></label>
      <textarea class="form-control" name="description" rows="3"><?= e($item['description'] ?? '') ?></textarea>
    </div>
    <div class="mb-1" style="max-width:280px">
      <label class="form-label">Süre limiti (dakika)</label>
      <input class="form-control" name="time_limit_minutes" type="number" min="1" placeholder="Boş = limitsiz" value="<?= e($item['time_limit_minutes'] ?? '') ?>">
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/tests" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Oluştur ve sorular ekle' ?></button>
  </div>
</form>
