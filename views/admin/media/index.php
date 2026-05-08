<?php use function App\{e, csrfField, csrfToken}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Medya Kütüphanesi</h1>
    <div class="page-sub">Görsel, ses ve videoları yükle — sorulara eklerken seçersin.</div>
  </div>
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $kind==='image'?'active':'' ?>" href="?kind=image"><i class="bi bi-image me-1"></i> Görseller</a></li>
    <li class="nav-item"><a class="nav-link <?= $kind==='audio'?'active':'' ?>" href="?kind=audio"><i class="bi bi-music-note me-1"></i> Sesler</a></li>
    <li class="nav-item"><a class="nav-link <?= $kind==='video'?'active':'' ?>" href="?kind=video"><i class="bi bi-camera-video me-1"></i> Videolar</a></li>
  </ul>
</div>

<div id="dropzone" class="dropzone mb-4">
  <div><i class="bi bi-cloud-arrow-up fs-2"></i></div>
  <div class="mt-2">Dosyaları buraya <strong>sürükle</strong> veya <label for="file-input" class="text-primary fw-semibold" style="cursor:pointer;text-decoration:underline">seç</label></div>
  <div class="tiny mt-1 muted">Görseller, sesler, videolar — otomatik kategorilenir.</div>
  <input id="file-input" type="file" multiple accept="image/*,audio/*,video/*" class="d-none">
  <div id="upload-progress" class="mt-3 d-none">
    <div class="progress"><div class="progress-bar" style="width:0%"></div></div>
    <div class="tiny mt-2 muted" id="upload-status">Yükleniyor…</div>
  </div>
</div>

<div class="row g-3">
  <?php if (!$items): ?>
    <div class="col-12">
      <div class="empty-state"><div class="icon"><i class="bi bi-inbox"></i></div>Bu kategoride medya yok.</div>
    </div>
  <?php else: foreach ($items as $m): ?>
    <div class="col-6 col-md-3 col-xl-2">
      <div class="media-card">
        <div class="thumb">
          <?php if ($kind === 'image'): ?>
            <img src="/media/<?= (int)$m['id'] ?>" alt="">
          <?php elseif ($kind === 'audio'): ?>
            <i class="bi bi-music-note-beamed"></i>
          <?php else: ?>
            <i class="bi bi-camera-video"></i>
          <?php endif; ?>
        </div>
        <div class="meta">
          <div class="name" title="<?= e($m['original_name']) ?>"><?= e($m['original_name']) ?></div>
          <div class="d-flex justify-content-between align-items-center mt-1">
            <span class="muted tiny"><?= number_format($m['size_bytes']/1024,0) ?> KB</span>
            <form method="post" action="/admin/media/<?= (int)$m['id'] ?>/delete" onsubmit="return confirm('Silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-link text-danger p-0"><i class="bi bi-trash"></i></button>
            </form>
          </div>
        </div>
      </div>
    </div>
  <?php endforeach; endif; ?>
</div>

<script>window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="/assets/js/media_upload.js"></script>
