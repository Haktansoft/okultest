<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Öğrenciyi Düzenle' : 'Yeni Öğrenci' ?></h1>
    <div class="page-sub">Öğrencinin giriş şifresi <strong>T.C. Kimlik Numarasıdır</strong>. Yeni eklenen öğrenciye mevcut tüm testler otomatik atanır.</div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<?php if (!$classrooms): ?>
  <div class="alert alert-warning">
    Önce <a href="/teacher/classrooms/new">en az bir sınıf</a> oluşturmalısın.
  </div>
<?php else: ?>
<form method="post"
      action="<?= $editing ? '/teacher/students/' . (int)$item['id'] . '/update' : '/teacher/students' ?>"
      class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>

    <div class="mb-3">
      <label class="form-label">Ad-Soyad</label>
      <input class="form-control" name="full_name" required maxlength="150" value="<?= e($item['full_name'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">T.C. Kimlik No <span class="muted tiny">(11 hane — şifre olarak kullanılacak)</span></label>
      <input class="form-control" name="tc" inputmode="numeric" pattern="\d{11}" maxlength="11"
             required value="<?= e($item['tc'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">Sınıf</label>
      <select class="form-select" name="classroom_id" required>
        <option value="">— Seçin —</option>
        <?php foreach ($classrooms as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($item && $item['classroom_id'] == $c['id']) ? 'selected' : '' ?>><?= e($c['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
<?php endif; ?>
