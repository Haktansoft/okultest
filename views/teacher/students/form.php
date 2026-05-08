<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Yeni Öğrenci</h1>
    <div class="page-sub">Şifreyi öğrenciyle paylaş.</div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>
<form method="post" action="/teacher/students" class="card" style="max-width:520px">
  <div class="card-body">
    <?= csrfField() ?>
    <?php if ($isAdmin): ?>
      <div class="mb-3">
        <label class="form-label">Öğretmen</label>
        <select class="form-select" name="teacher_id" required>
          <option value="">— Hangi öğretmenin altına? —</option>
          <?php foreach ($teachers ?? [] as $t): ?>
            <option value="<?= (int)$t['id'] ?>"><?= e($t['full_name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    <?php endif; ?>
    <div class="mb-3"><label class="form-label">Ad-Soyad</label><input class="form-control" name="full_name" required></div>
    <div class="mb-3"><label class="form-label">E-posta</label><input class="form-control" type="email" name="email" required></div>
    <div class="mb-3"><label class="form-label">Şifre <span class="muted tiny">(en az 6 karakter)</span></label><input class="form-control" name="password" required minlength="6"></div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
    <button class="btn btn-primary">Ekle</button>
  </div>
</form>
