<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Sınıfı Düzenle' : 'Yeni Sınıf' ?></h1>
    <div class="page-sub">Sınıf adı genelde "Anasınıfı A", "1/A", "5/B" gibi olur.</div>
  </div>
  <a href="/teacher/classrooms" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/teacher/classrooms/' . (int)$item['id'] . '/update' : '/teacher/classrooms' ?>" class="card" style="max-width:480px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Sınıf Adı</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= e($item['name'] ?? '') ?>" autofocus>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/classrooms" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
