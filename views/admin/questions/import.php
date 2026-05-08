<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Toplu Soru İçe Aktarma</h1>
    <div class="page-sub">Excel (.xlsx) dosyasından soruları topluca yükle.</div>
  </div>
  <a href="/admin/questions" class="btn btn-link"><i class="bi bi-arrow-left"></i> Sorulara dön</a>
</div>

<?php if (empty($report)): ?>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="mb-3">Beklenen sütunlar (1. satır başlık)</h5>
    <div class="table-wrap mb-3" style="max-width:640px">
      <table class="table m-0">
        <thead><tr><th style="width:120px">Sütun</th><th>İçerik</th></tr></thead>
        <tbody>
          <tr><td><code>B</code></td><td><strong>SORU</strong> — soru metni (zorunlu)</td></tr>
          <tr><td><code>D</code></td><td><strong>RESİM</strong> — sorunun kendi görselinin adı (opsiyonel)</td></tr>
          <tr><td><code>E,G,I,K,M</code></td><td><strong>CEVAP 1-5</strong> — şık metni veya görsel adı</td></tr>
          <tr><td><code>F,H,J,L,N</code></td><td><strong>DEĞER 1-5</strong> — şıkkın puanı (boşsa 0)</td></tr>
          <tr><td><code>O</code></td><td><strong>DOĞRU CEVAP</strong> — A/B/C/D/E (tüm puanlar 0 ise bu şıkka 1 verilir)</td></tr>
          <tr><td><code>P</code></td><td><strong>ALT BAŞLIK</strong> — kategori adı (yoksa otomatik oluşturulur)</td></tr>
        </tbody>
      </table>
    </div>
    <div class="alert alert-info py-2">
      <strong>Görsel eşleştirme:</strong> Cevap içeriği <code>.png/.jpg/.gif/.webp</code> ile bitiyorsa görsel sayılır.
      Medya kütüphanesinde aynı dosya adıyla yüklü görsel varsa şıkka o görsel atanır;
      yoksa şık metni <em>"GORSEL EKLENECEK: ad.png"</em> olarak yazılır.
    </div>
    <div class="alert alert-warning py-2 mb-0">
      <strong>Fiziksel sorular:</strong> Kategorisi <code>İnce Motor</code> veya <code>Yönerge Takibi</code> olan sorular
      otomatik olarak <em>"öğretmen girecek"</em> (fiziksel) olarak işaretlenir; öğrenciye gösterilmez,
      öğretmen test sonrası ayrı ekrandan yanıt girer.
    </div>
  </div>
</div>

<form method="post" action="/admin/questions/import" enctype="multipart/form-data" class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">XLSX dosyası</label>
      <input class="form-control" type="file" name="file" accept=".xlsx" required>
      <div class="form-help">Maks. yükleme limitin kadar (büyük dosyalar dakikalar sürebilir).</div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/questions" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><i class="bi bi-upload"></i> Yükle ve İçe Aktar</button>
  </div>
</form>

<?php else: ?>

<?php $hasErrors = !empty($report['errors']); ?>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="mb-3"><?= e($fname ?? 'Dosya') ?> — özet</h5>
    <div class="row g-3">
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-file-earmark-spreadsheet"></i></div><div><div class="num"><?= (int)$report['total'] ?></div><div class="lbl">Satır</div></div></div></div>
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-check2-circle"></i></div><div><div class="num text-success"><?= (int)$report['imported'] ?></div><div class="lbl">Eklenen Soru</div></div></div></div>
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-folder-plus"></i></div><div><div class="num"><?= (int)$report['categoriesCreated'] ?></div><div class="lbl">Yeni Kategori</div></div></div></div>
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-image"></i></div><div><div class="num"><?= (int)$report['mediaMatched'] ?> / <?= (int)$report['mediaMatched'] + (int)$report['mediaMissing'] ?></div><div class="lbl">Eşleşen Görsel</div></div></div></div>
    </div>
    <?php if (!empty($report['physicalMarked'])): ?>
      <div class="alert alert-warning py-2 mt-3 mb-0">
        <i class="bi bi-pencil-square"></i>
        <strong><?= (int)$report['physicalMarked'] ?> soru</strong> "fiziksel — öğretmen girecek" olarak işaretlendi
        (İnce Motor / Yönerge Takibi kategorileri).
      </div>
    <?php endif; ?>
  </div>
</div>

<?php if ($report['mediaMissing']): ?>
  <div class="alert alert-warning">
    <strong><?= (int)$report['mediaMissing'] ?> görsel</strong> medya kütüphanesinde bulunamadı.
    Bu şıkların metninde <em>"GORSEL EKLENECEK: …"</em> ibaresi var.
    Görselleri <a href="/admin/media">/admin/media</a> sayfasından yükleyip ilgili soruları düzenleyerek eşleştirebilirsin.
  </div>
<?php endif; ?>

<?php if ($report['skipped']): ?>
  <div class="alert alert-danger">
    <strong><?= (int)$report['skipped'] ?> satır</strong> atlandı (eksik veri veya hata).
  </div>
<?php endif; ?>

<?php if ($hasErrors): ?>
  <div class="card mb-3">
    <div class="card-header">Uyarılar / Hatalar (<?= count($report['errors']) ?>)</div>
    <div class="card-body p-0">
      <ul class="list-group list-group-flush" style="max-height:50vh; overflow:auto">
        <?php foreach ($report['errors'] as $err): ?>
          <li class="list-group-item small"><?= e($err) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  </div>
<?php endif; ?>

<div class="d-flex gap-2">
  <a href="/admin/questions" class="btn btn-primary"><i class="bi bi-list-ul"></i> Soru Listesine Git</a>
  <a href="/admin/questions/import" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Yeniden Yükle</a>
</div>

<?php endif; ?>
