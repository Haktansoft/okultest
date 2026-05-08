<?php use function App\{e, csrfField, csrfToken}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Medya Kütüphanesi</h1>
    <div class="page-sub">Görsel, ses ve videoları yükle — sorulara eklerken seçersin.</div>
  </div>
  <ul class="nav nav-pills">
    <li class="nav-item"><a class="nav-link <?= $kind==='image'?'active':'' ?>" href="?kind=image"><i class="bi bi-image me-1"></i> Görseller <span class="badge text-bg-light ms-1"><?= (int)($counts['image'] ?? 0) ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $kind==='audio'?'active':'' ?>" href="?kind=audio"><i class="bi bi-music-note me-1"></i> Sesler <span class="badge text-bg-light ms-1"><?= (int)($counts['audio'] ?? 0) ?></span></a></li>
    <li class="nav-item"><a class="nav-link <?= $kind==='video'?'active':'' ?>" href="?kind=video"><i class="bi bi-camera-video me-1"></i> Videolar <span class="badge text-bg-light ms-1"><?= (int)($counts['video'] ?? 0) ?></span></a></li>
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

<?php if (($totalPages ?? 1) > 1): ?>
  <nav class="mt-4 d-flex justify-content-between align-items-center flex-wrap gap-2">
    <div class="muted tiny">Toplam <?= (int)$total ?> öğe — sayfa <?= (int)$page ?> / <?= (int)$totalPages ?></div>
    <ul class="pagination pagination-sm m-0">
      <?php
        $win = 2; $start = max(1, $page - $win); $end = min($totalPages, $page + $win);
        $url = function ($p) use ($kind) { return '?kind=' . urlencode($kind) . '&page=' . (int)$p; };
      ?>
      <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $url(max(1, $page - 1)) ?>">&laquo;</a>
      </li>
      <?php if ($start > 1): ?>
        <li class="page-item"><a class="page-link" href="<?= $url(1) ?>">1</a></li>
        <?php if ($start > 2): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
      <?php endif; ?>
      <?php for ($p = $start; $p <= $end; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>"><a class="page-link" href="<?= $url($p) ?>"><?= $p ?></a></li>
      <?php endfor; ?>
      <?php if ($end < $totalPages): ?>
        <?php if ($end < $totalPages - 1): ?><li class="page-item disabled"><span class="page-link">…</span></li><?php endif; ?>
        <li class="page-item"><a class="page-link" href="<?= $url($totalPages) ?>"><?= (int)$totalPages ?></a></li>
      <?php endif; ?>
      <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
        <a class="page-link" href="<?= $url(min($totalPages, $page + 1)) ?>">&raquo;</a>
      </li>
    </ul>
  </nav>
<?php endif; ?>

<script>window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;</script>
<script src="/assets/js/media_upload.js"></script>
