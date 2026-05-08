<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Sınıfı Düzenle' : 'Yeni Sınıf' ?></h1>
    <div class="page-sub">Sınıf "Sınıf + Şube" şeklinde adlandırılır (örn. <code>4 YAŞ A</code>).</div>
  </div>
  <a href="/admin/classrooms" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post"
      action="<?= $editing ? '/admin/classrooms/' . (int)$item['id'] . '/update' : '/admin/classrooms' ?>"
      class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>

    <div class="mb-3">
      <label class="form-label">Kampüs</label>
      <select class="form-select" name="campus_id" required>
        <option value="">— Seçin —</option>
        <?php foreach ($campuses as $c): ?>
          <option value="<?= (int)$c['id'] ?>" <?= ($item && $item['campus_id'] == $c['id']) ? 'selected' : '' ?>>
            <?= e($c['institution_name']) ?> — <?= e($c['campus_name']) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="row g-3">
      <div class="col-7">
        <label class="form-label">Sınıf</label>
        <select class="form-select" name="grade_level" required>
          <option value="">— Seçin —</option>
          <?php foreach ($gradeLevels as $g): ?>
            <option value="<?= e($g) ?>" <?= ($item && $item['grade_level'] === $g) ? 'selected' : '' ?>><?= e($g) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-5">
        <label class="form-label">Şube</label>
        <select class="form-select" name="section" required>
          <option value="">— Seçin —</option>
          <?php foreach ($sections as $s): ?>
            <option value="<?= e($s) ?>" <?= ($item && $item['section'] === $s) ? 'selected' : '' ?>><?= e($s) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/classrooms" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
