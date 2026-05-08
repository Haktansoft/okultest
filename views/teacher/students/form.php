<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Öğrenciyi Düzenle' : 'Yeni Öğrenci' ?></h1>
    <div class="page-sub">Öğrencinin giriş şifresi <strong>T.C. Kimlik Numarasıdır</strong>. T.C. değişirse şifre de otomatik güncellenir.</div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post"
      action="<?= $editing ? '/teacher/students/' . (int)$item['id'] . '/update' : '/teacher/students' ?>"
      class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>

    <div class="mb-3">
      <label class="form-label">Ad-Soyad</label>
      <input class="form-control" name="full_name" required maxlength="150"
             value="<?= e($item['full_name'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">T.C. Kimlik No <span class="muted tiny">(11 hane — şifre olarak kullanılacak)</span></label>
      <input class="form-control" name="tc" inputmode="numeric" pattern="\d{11}" maxlength="11"
             required value="<?= e($item['tc'] ?? '') ?>">
    </div>

    <div class="row g-3">
      <div class="col-7">
        <label class="form-label">Sınıf</label>
        <input class="form-control" name="grade_level" maxlength="20"
               placeholder="örn. Anasınıfı, 1. Sınıf, 5"
               value="<?= e($item['grade_level'] ?? '') ?>">
      </div>
      <div class="col-5">
        <label class="form-label">Şube</label>
        <input class="form-control" name="section" maxlength="20"
               placeholder="örn. A"
               value="<?= e($item['section'] ?? '') ?>">
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
