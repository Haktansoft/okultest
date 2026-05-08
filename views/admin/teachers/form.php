<?php use function App\csrfField; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Yeni Öğretmen</h1>
    <div class="page-sub">Sistemde giriş sadece şifre ile yapılır — şifreyi öğretmenle güvenli şekilde paylaş.</div>
  </div>
  <a href="/admin/teachers" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="/admin/teachers" class="card" style="max-width:520px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3"><label class="form-label">Ad-Soyad</label><input class="form-control" name="full_name" required></div>
    <div class="mb-3">
      <label class="form-label">Şifre <span class="muted tiny">(en az 4 karakter, sistemde benzersiz olmalı)</span></label>
      <input class="form-control" name="password" required minlength="4" autocomplete="off">
      <div class="form-help mt-1">Aynı şifreyi başka kullanıcı kullanıyor olamaz; çakışma olursa farklı bir şifre seç.</div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/teachers" class="btn btn-link">İptal</a>
    <button class="btn btn-primary">Ekle</button>
  </div>
</form>
