<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Toplu Öğrenci İçe Aktarma</h1>
    <div class="page-sub">Excel (.xlsx) dosyasından öğrencileri topluca yükle. Her öğrenciye mevcut testler otomatik atanır.</div>
  </div>
  <a href="/teacher/students" class="btn btn-link"><i class="bi bi-arrow-left"></i> Öğrencilere dön</a>
</div>

<?php if (empty($report)): ?>

<div class="card mb-3">
  <div class="card-body">
    <h5 class="mb-3">Beklenen sütunlar (1. satır başlık)</h5>
    <div class="table-wrap mb-3" style="max-width:720px">
      <table class="table m-0">
        <thead><tr><th style="width:80px">Sütun</th><th>İçerik</th></tr></thead>
        <tbody>
          <tr><td><code>A</code></td><td><strong>KURUM</strong> — kurum ID (opsiyonel; girilirse kampüs-kurum tutarlılığı kontrol edilir)</td></tr>
          <tr><td><code>B</code></td><td><strong>KAMPÜS</strong> — kampüs ID (zorunlu)</td></tr>
          <tr><td><code>C</code></td><td><strong>ÖĞRENCİ ADI SOYADI</strong> — tam ad (zorunlu)</td></tr>
          <tr><td><code>D</code></td><td><strong>ÖĞRENCİ T.C.</strong> — 11 hane, sıfırla başlayamaz; şifre olarak kullanılır</td></tr>
          <tr><td><code>E</code></td><td><strong>SINIF</strong> — izin verilenler: <code><?= e(implode(', ', $gradeLevels)) ?></code></td></tr>
          <tr><td><code>F</code></td><td><strong>ŞUBE</strong> — izin verilenler: <code><?= e(implode(', ', $sections)) ?></code></td></tr>
        </tbody>
      </table>
    </div>
    <div class="alert alert-info py-2 mb-0">
      Kurum ve kampüs ID'lerini <a href="/admin/institutions">/admin/institutions</a> ve
      <a href="/admin/campuses">/admin/campuses</a> sayfalarında görebilirsin.
      Aynı T.C. zaten kayıtlıysa o satır atlanır.
    </div>
  </div>
</div>

<form method="post" action="/teacher/students/import" enctype="multipart/form-data" class="card" style="max-width:560px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">XLSX dosyası</label>
      <input class="form-control" type="file" name="file" accept=".xlsx" required>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/teacher/students" class="btn btn-link">İptal</a>
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
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-person-plus"></i></div><div><div class="num text-success"><?= (int)$report['imported'] ?></div><div class="lbl">Eklenen Öğrenci</div></div></div></div>
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-send"></i></div><div><div class="num"><?= (int)$report['autoAssigned'] ?></div><div class="lbl">Otomatik Atama</div></div></div></div>
      <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-skip-forward"></i></div><div><div class="num text-danger"><?= (int)$report['skipped'] ?></div><div class="lbl">Atlanan</div></div></div></div>
    </div>
  </div>
</div>

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
  <a href="/teacher/students" class="btn btn-primary"><i class="bi bi-people"></i> Öğrencilere git</a>
  <a href="/teacher/students/import" class="btn btn-outline-secondary"><i class="bi bi-arrow-clockwise"></i> Yeniden yükle</a>
</div>

<?php endif; ?>
