<?php use function App\csrfField; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Yeni Öğretmen</h1>
    <div class="page-sub">Şifreyi öğretmenle paylaş — ilk girişten sonra istediği zaman değiştirilemiyor şu an, daha sonra "Sıfırla" yapabilirsin.</div>
  </div>
  <a href="/admin/teachers" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="/admin/teachers" class="card" style="max-width:520px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3"><label class="form-label">Ad-Soyad</label><input class="form-control" name="full_name" required></div>
    <div class="mb-3"><label class="form-label">E-posta</label><input class="form-control" type="email" name="email" required></div>
    <div class="mb-3"><label class="form-label">Şifre <span class="muted tiny">(en az 6 karakter)</span></label><input class="form-control" name="password" required minlength="6"></div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/teachers" class="btn btn-link">İptal</a>
    <button class="btn btn-primary">Ekle</button>
  </div>
</form>
