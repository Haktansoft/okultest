<?php use function App\{e, csrfField, csrfToken}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Kategori Düzenle' : 'Yeni Kategori' ?></h1>
    <div class="page-sub">Açıklamaya ses dosyası eklersen, öğrenci o kategorinin sorularına geçmeden önce yönergeyi dinleyebilir.</div>
  </div>
  <a href="/admin/categories" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/categories/' . (int)$item['id'] . '/update' : '/admin/categories' ?>" class="card" style="max-width:680px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Ad</label>
      <input class="form-control" name="name" required value="<?= e($item['name'] ?? '') ?>">
    </div>
    <div class="mb-3">
      <label class="form-label">Açıklama / Yönerge <span class="muted tiny">(opsiyonel)</span></label>
      <textarea class="form-control" name="description" rows="3" placeholder="Bu kategori öncesinde öğrenciye gösterilecek açıklama metni…"><?= e($item['description'] ?? '') ?></textarea>
    </div>
    <div class="mb-1">
      <label class="form-label">Yönerge sesi <span class="muted tiny">(opsiyonel — sadece ses dosyası)</span></label>
      <input type="hidden" name="description_media_id" id="cat-audio-id" value="<?= (int)($item['description_media_id'] ?? 0) ?>">
      <div id="cat-audio-slot" class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (!empty($audio)): ?>
          <div class="d-flex align-items-center gap-2 px-3 py-2 rounded border bg-light" id="cat-audio-current">
            <i class="bi bi-music-note-beamed"></i>
            <span class="small fw-semibold"><?= e($audio['original_name']) ?></span>
            <audio controls preload="none" src="/media/<?= (int)$audio['id'] ?>" style="height:32px"></audio>
            <button type="button" class="btn btn-sm btn-outline-danger" id="cat-audio-clear"><i class="bi bi-x-lg"></i></button>
          </div>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-primary" id="cat-audio-pick">
          <i class="bi bi-music-note-list"></i> Ses dosyası seç
        </button>
      </div>
      <div class="form-help mt-1">Önce <a href="/admin/media?kind=audio" target="_blank">/admin/media</a> üzerinden ses dosyasını yükle, sonra burada seç.</div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/categories" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Kategori Ekle' ?></button>
  </div>
</form>

<!-- Picker modalı -->
<div class="modal fade" id="cat-audio-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-music-note-beamed me-1"></i> Ses dosyası seç</h5>
        <button class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
      </div>
      <div class="modal-body">
        <div class="d-flex gap-2 mb-3 align-items-center">
          <input type="text" class="form-control" id="cat-audio-search" placeholder="Dosya adında ara…">
          <button type="button" class="btn btn-outline-secondary" id="cat-audio-more" style="display:none">Daha fazla</button>
        </div>
        <div id="cat-audio-loading" class="muted small d-none">Yükleniyor…</div>
        <div id="cat-audio-empty" class="empty-state d-none"><div class="icon"><i class="bi bi-music-note-beamed"></i></div>Ses dosyası bulunamadı.</div>
        <ul class="list-group" id="cat-audio-list"></ul>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const idInput   = document.getElementById('cat-audio-id');
  const slot      = document.getElementById('cat-audio-slot');
  const pickBtn   = document.getElementById('cat-audio-pick');
  const modalEl   = document.getElementById('cat-audio-modal');
  const list      = document.getElementById('cat-audio-list');
  const loading   = document.getElementById('cat-audio-loading');
  const emptyEl   = document.getElementById('cat-audio-empty');
  const search    = document.getElementById('cat-audio-search');
  const moreBtn   = document.getElementById('cat-audio-more');
  let modal = null;
  let page = 1, hasMore = false, searchTimer = null;

  function getModal() {
    if (!modal) modal = bootstrap.Modal.getOrCreateInstance(modalEl);
    return modal;
  }

  function clearCurrent() {
    idInput.value = '0';
    const cur = document.getElementById('cat-audio-current');
    if (cur) cur.remove();
  }
  document.addEventListener('click', (e) => {
    if (e.target.closest('#cat-audio-clear')) {
      e.preventDefault();
      clearCurrent();
    }
  });

  function applyPick(item) {
    idInput.value = item.id;
    const old = document.getElementById('cat-audio-current');
    if (old) old.remove();
    const div = document.createElement('div');
    div.id = 'cat-audio-current';
    div.className = 'd-flex align-items-center gap-2 px-3 py-2 rounded border bg-light';
    div.innerHTML = `
      <i class="bi bi-music-note-beamed"></i>
      <span class="small fw-semibold"></span>
      <audio controls preload="none" style="height:32px"></audio>
      <button type="button" class="btn btn-sm btn-outline-danger" id="cat-audio-clear"><i class="bi bi-x-lg"></i></button>
    `;
    div.querySelector('span').textContent = item.name;
    div.querySelector('audio').src = item.url;
    slot.insertBefore(div, pickBtn);
  }

  async function loadList(reset) {
    if (reset) { list.innerHTML = ''; page = 1; emptyEl.classList.add('d-none'); moreBtn.style.display = 'none'; }
    else page++;
    loading.classList.remove('d-none');
    try {
      const url = `/admin/media.json?kind=audio&q=${encodeURIComponent(search.value || '')}&page=${page}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json();
      loading.classList.add('d-none');
      if (reset && (!data.items || !data.items.length)) {
        emptyEl.classList.remove('d-none');
        return;
      }
      (data.items || []).forEach(it => {
        const li = document.createElement('li');
        li.className = 'list-group-item d-flex align-items-center gap-2';
        li.innerHTML = `
          <i class="bi bi-music-note-beamed text-primary"></i>
          <span class="fw-semibold flex-grow-1"></span>
          <audio controls preload="none" style="height:32px"></audio>
          <button type="button" class="btn btn-sm btn-primary">Seç</button>
        `;
        li.querySelector('span').textContent = it.name;
        li.querySelector('audio').src = it.url;
        li.querySelector('button').addEventListener('click', () => {
          applyPick(it);
          getModal().hide();
        });
        list.appendChild(li);
      });
      hasMore = !!data.hasMore;
      moreBtn.style.display = hasMore ? '' : 'none';
    } catch (e) {
      loading.classList.add('d-none');
      if (reset) list.innerHTML = '<li class="list-group-item text-danger small">Yüklenemedi.</li>';
    }
  }

  pickBtn.addEventListener('click', () => { loadList(true); getModal().show(); });
  search.addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => loadList(true), 250);
  });
  moreBtn.addEventListener('click', () => loadList(false));
})();
</script>
