<?php use function App\{e, csrfField}; $editing = !empty($item); $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Öğrenciyi Düzenle' : 'Yeni Öğrenci' ?></h1>
    <div class="page-sub">
      Giriş şifresi <strong>T.C. Kimlik Numarasıdır</strong>.
      <?php if (!$editing): ?>Yeni öğrenciye mevcut tüm testler otomatik atanır.<?php endif; ?>
    </div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post"
      action="<?= $editing ? '/teacher/students/' . (int)$item['id'] . '/update' : '/teacher/students' ?>"
      class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>

    <?php if (!$editing && $isAdmin): ?>
      <div class="mb-3">
        <label class="form-label">Kampüs <span class="muted tiny">(öğrencinin bağlanacağı kampüs)</span></label>
        <select class="form-select" name="campus_id" required>
          <option value="">— Kampüs seç —</option>
          <?php foreach ($campuses as $c): ?>
            <option value="<?= (int)$c['id'] ?>">
              <?= e($c['institution_name']) ?> — <?= e($c['campus_name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>

    <div class="mb-3">
      <label class="form-label">Ad-Soyad</label>
      <input class="form-control" name="full_name" required maxlength="150" value="<?= e($item['full_name'] ?? '') ?>">
    </div>

    <div class="mb-3">
      <label class="form-label">T.C. Kimlik No <span class="muted tiny">(11 hane — şifre olarak kullanılacak)</span></label>
      <input class="form-control" name="tc" inputmode="numeric" pattern="\d{11}" maxlength="11"
             required value="<?= e($item['tc'] ?? '') ?>">
    </div>

    <div class="row g-3">
      <div class="col-7">
        <label class="form-label">Sınıf</label>
        <select class="form-select" name="grade_level" required>
          <option value="">— Seç —</option>
          <?php foreach ($gradeLevels as $g): ?>
            <option value="<?= e($g) ?>" <?= ($item && $item['grade_level'] === $g) ? 'selected' : '' ?>><?= e($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-5">
        <label class="form-label">Şube</label>
        <select class="form-select" name="section" required>
          <option value="">— Seç —</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= e($s) ?>" <?= ($item && $item['section'] === $s) ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
