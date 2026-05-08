<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Öğretmeni Düzenle' : 'Yeni Öğretmen' ?></h1>
    <div class="page-sub">Öğretmen bir kampüse bağlanır ve sadece o kampüsteki sınıfları/öğrencileri yönetir.</div>
  </div>
  <a href="/admin/teachers" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/teachers/' . (int)$item['id'] . '/update' : '/admin/teachers' ?>" class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Ad-Soyad</label>
      <input class="form-control" name="full_name" required value="<?= e($item['full_name'] ?? '') ?>">
    </div>
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
    <?php if (!$editing): ?>
      <div class="mb-3">
        <label class="form-label">Şifre <span class="muted tiny">(en az 4 karakter, sistemde benzersiz olmalı)</span></label>
        <input class="form-control" name="password" required minlength="4" autocomplete="off">
      </div>
    <?php endif; ?>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/teachers" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Ekle' ?></button>
  </div>
</form>
