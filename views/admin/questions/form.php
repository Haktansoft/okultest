<?php
use function App\{e, csrfField, csrfToken};
$editing = !empty($item) && !empty($item['id']);
$action  = $editing ? '/admin/questions/' . (int)$item['id'] . '/update' : '/admin/questions';
// $options array can come either from DB (option_options table rows) or from "old" flash (with is_correct)
$opts = $options ?: [
    ['label'=>'', 'score'=>0, 'media_id'=>null, 'is_correct'=>true],
    ['label'=>'', 'score'=>0, 'media_id'=>null, 'is_correct'=>false],
    ['label'=>'', 'score'=>0, 'media_id'=>null, 'is_correct'=>false],
    ['label'=>'', 'score'=>0, 'media_id'=>null, 'is_correct'=>false],
];
?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Soru Düzenle' : 'Yeni Soru' ?></h1>
    <div class="page-sub">Şıklardan birini "Doğru" olarak işaretle. İstersen şıklara farklı puan da verebilirsin (kısmi puan).</div>
  </div>
  <a href="/admin/questions" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= e($action) ?>" id="qform">
  <?= csrfField() ?>

  <div class="card mb-3"><div class="card-body">
    <div class="row g-3">
      <div class="col-md-8">
        <label class="form-label">Kategori</label>
        <select name="category_id" class="form-select" required>
          <option value="">— Seçin —</option>
          <?php foreach ($cats as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= ($item['category_id'] ?? 0) == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-md-4 d-flex align-items-end">
        <div class="form-check">
          <input class="form-check-input" type="checkbox" name="is_physical" id="is_physical" <?= !empty($item['is_physical']) ? 'checked' : '' ?>>
          <label class="form-check-label" for="is_physical">
            <strong>Fiziksel soru</strong>
            <div class="form-help">Öğrenciye gösterilmez; öğretmen sonradan girer.</div>
          </label>
        </div>
      </div>
    </div>

    <div class="mt-3">
      <label class="form-label">Soru metni</label>
      <textarea name="prompt" class="form-control" rows="3" required placeholder="Sorunun metnini yaz…"><?= e($item['prompt'] ?? '') ?></textarea>
    </div>

    <div class="mt-3">
      <label class="form-label">Sorunun medyası <span class="muted tiny">(opsiyonel — görsel/ses/video)</span></label>
      <input type="hidden" name="prompt_media_id" id="prompt_media_id"
             data-kind="<?= e($promptMedia['kind'] ?? '') ?>"
             data-name="<?= e($promptMedia['original_name'] ?? '') ?>"
             value="<?= e($item['prompt_media_id'] ?? '') ?>">
      <div id="prompt-media-slot" class="media-slot"></div>
    </div>
  </div></div>

  <div class="card mb-3"><div class="card-body">
    <div class="d-flex justify-content-between align-items-center mb-2">
      <div>
        <h5 class="m-0">Şıklar</h5>
        <div class="form-help">Doğru cevabı yeşil tik ile işaretle. Birden fazla doğru de seçilebilir.</div>
      </div>
      <button type="button" class="btn btn-sm btn-outline-secondary" id="add-opt"><i class="bi bi-plus-lg"></i> Şık ekle</button>
    </div>
    <div id="options" class="q-options-list"></div>
  </div></div>

  <div class="text-end mb-5">
    <a href="/admin/questions" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Soruyu Ekle' ?></button>
  </div>
</form>

<!-- Medya seçim modali -->
<div class="modal fade" id="mediaModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-xl modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header py-2 px-3 align-items-center">
        <div class="d-flex flex-wrap align-items-center gap-2 flex-grow-1">
          <h5 class="modal-title m-0 me-3"><i class="bi bi-collection-play me-2"></i>Medya seç</h5>
          <ul class="nav nav-pills small" id="mediaTabs" style="gap:4px">
            <li class="nav-item"><a class="nav-link py-1 px-2 active" data-kind="image" href="#"><i class="bi bi-image me-1"></i>Görseller <span class="mp-count" data-kind="image"></span></a></li>
            <li class="nav-item"><a class="nav-link py-1 px-2" data-kind="audio" href="#"><i class="bi bi-music-note me-1"></i>Sesler <span class="mp-count" data-kind="audio"></span></a></li>
            <li class="nav-item"><a class="nav-link py-1 px-2" data-kind="video" href="#"><i class="bi bi-camera-video me-1"></i>Videolar <span class="mp-count" data-kind="video"></span></a></li>
          </ul>
          <div class="ms-auto" style="min-width:220px">
            <div class="input-group input-group-sm">
              <span class="input-group-text bg-white"><i class="bi bi-search"></i></span>
              <input id="media-search" type="search" class="form-control" placeholder="Dosya adıyla ara…" autocomplete="off">
            </div>
          </div>
        </div>
        <button class="btn-close ms-2" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body p-3" style="background:#fafbfd">
        <div id="media-grid" class="media-picker-grid"></div>
        <div id="media-empty" class="empty-state d-none">
          <div class="icon"><i class="bi bi-inbox"></i></div>
          <div>Bu kategoride medya yok.</div>
          <a href="/admin/media" target="_blank" class="btn btn-sm btn-outline-primary mt-2"><i class="bi bi-cloud-arrow-up"></i> Medya kütüphanesini aç</a>
        </div>
        <div id="media-loading" class="empty-state">
          <div class="icon"><i class="bi bi-hourglass-split"></i></div>
          <div class="muted">Yükleniyor…</div>
        </div>
      </div>
      <div class="modal-footer py-2 px-3">
        <div class="muted tiny me-auto" id="media-selected-name"></div>
        <button class="btn btn-link" data-bs-dismiss="modal">Vazgeç</button>
        <button class="btn btn-primary" id="media-confirm" disabled><i class="bi bi-check2"></i> Bu medyayı kullan</button>
      </div>
    </div>
  </div>
</div>

<template id="opt-template">
  <div class="option-row">
    <span class="opt-letter"></span>
    <div class="opt-body">
      <div class="opt-row-1">
        <input class="form-control opt-label" name="option_label[]" placeholder="Şık metni…">
        <button type="button" class="correct-toggle" title="Bu şık doğru cevap mı?">
          <i class="bi bi-check-circle"></i><span>Doğru</span>
        </button>
        <input type="hidden" name="option_correct[]" class="correct-input" value="">
        <input class="form-control score-input" name="option_score[]" type="number" step="0.01" min="0" placeholder="puan" title="Boşsa, doğru ise sorunun tam puanı uygulanır.">
        <button type="button" class="btn btn-sm btn-link text-danger p-1" data-remove title="Şıkkı sil"><i class="bi bi-x-lg"></i></button>
      </div>
      <input type="hidden" name="option_media_id[]" class="opt-media-id">
      <div class="opt-media-slot media-slot"></div>
    </div>
  </div>
</template>

<script>
window.CSRF_TOKEN = <?= json_encode(csrfToken()) ?>;
window.PRELOADED_OPTIONS = <?= json_encode(array_map(function($o) {
    return [
        'label'      => $o['label'] ?? '',
        'score'      => isset($o['score']) ? (float)$o['score'] : 0,
        'media_id'   => $o['media_id'] ?? null,
        'media_kind' => $o['media_kind'] ?? null,
        'media_name' => $o['media_name'] ?? null,
        'is_correct' => array_key_exists('is_correct', $o) ? (bool)$o['is_correct'] : ((float)($o['score'] ?? 0) > 0),
    ];
}, $opts)) ?>;
</script>
<script src="/assets/js/question_form.js"></script>
