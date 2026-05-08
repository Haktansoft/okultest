<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Yeni Atama</h1>
    <div class="page-sub">Bir testi birden fazla öğrenciye aynı anda atayabilirsin.</div>
  </div>
  <a href="/teacher/assignments" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="/teacher/assignments" class="card" style="max-width:680px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Test</label>
      <select class="form-select" name="test_id" required>
        <option value="">— Seçin —</option>
        <?php foreach ($tests as $t): ?>
          <option value="<?= (int)$t['id'] ?>"><?= e($t['title']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="mb-3">
      <label class="form-label">Öğrenciler</label>
      <?php if (!$students): ?>
        <p class="muted">Önce öğrenci ekleyin.</p>
      <?php else: ?>
        <div style="max-height:50vh; overflow:auto; border:1px solid var(--c-border); border-radius:10px; padding:8px">
          <?php foreach ($students as $s): ?>
            <label class="d-flex align-items-center py-1 px-2">
              <input type="checkbox" class="form-check-input me-2" name="student_ids[]" value="<?= (int)$s['id'] ?>">
              <?= e($s['full_name']) ?>
            </label>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/assignments" class="btn btn-link">İptal</a>
    <button class="btn btn-primary" <?= !$students ? 'disabled' : '' ?>>Ata</button>
  </div>
</form>
