<?php use function App\{e, csrfField}; $editing = !empty($item); ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $editing ? 'Kurum Düzenle' : 'Yeni Kurum' ?></h1>
    <div class="page-sub">Logo opsiyoneldir — medya kütüphanesinden bir görsel seçilebilir.</div>
  </div>
  <a href="/admin/institutions" class="btn btn-link"><i class="bi bi-arrow-left"></i> Listeye dön</a>
</div>

<form method="post" action="<?= $editing ? '/admin/institutions/' . (int)$item['id'] . '/update' : '/admin/institutions' ?>" class="card" style="max-width:600px">
  <div class="card-body">
    <?= csrfField() ?>
    <div class="mb-3">
      <label class="form-label">Ad</label>
      <input class="form-control" name="name" required maxlength="150" value="<?= e($item['name'] ?? '') ?>">
    </div>

    <div class="mb-1">
      <label class="form-label">Logo <span class="muted tiny">(opsiyonel — sadece görsel)</span></label>
      <input type="hidden" name="logo_media_id" id="inst-logo-id" value="<?= (int)($item['logo_media_id'] ?? 0) ?>">
      <div id="inst-logo-slot" class="d-flex align-items-center gap-2 flex-wrap">
        <?php if (!empty($logo)): ?>
          <div class="d-flex align-items-center gap-2 px-3 py-2 rounded border bg-light" id="inst-logo-current">
            <img src="/media/<?= (int)$logo['id'] ?>" alt="" style="width:48px;height:48px;object-fit:contain">
            <span class="small fw-semibold"><?= e($logo['original_name']) ?></span>
            <button type="button" class="btn btn-sm btn-outline-danger" id="inst-logo-clear"><i class="bi bi-x-lg"></i></button>
          </div>
        <?php endif; ?>
        <button type="button" class="btn btn-outline-primary" id="inst-logo-pick">
          <i class="bi bi-image"></i> Görsel seç
        </button>
      </div>
      <div class="form-help mt-1">Önce <a href="/admin/media?kind=image" target="_blank">/admin/media</a> üzerinden yükle, sonra burada seç.</div>
    </div>
  </div>
  <div class="card-footer text-end">
    <a href="/admin/institutions" class="btn btn-link">İptal</a>
    <button class="btn btn-primary"><?= $editing ? 'Kaydet' : 'Kurum Ekle' ?></button>
  </div>
</form>

<div class="modal fade" id="inst-logo-modal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><i class="bi bi-image me-1"></i> Logo Seç</h5>
        <button class="btn-close" data-bs-dismiss="modal"></button>
      </div>
      <div class="modal-body">
        <div class="mb-3">
          <input type="text" class="form-control" id="inst-logo-search" placeholder="Dosya adında ara…">
        </div>
        <div id="inst-logo-loading" class="muted small d-none">Yükleniyor…</div>
        <div id="inst-logo-empty" class="empty-state d-none"><div class="icon"><i class="bi bi-image"></i></div>Görsel bulunamadı.</div>
        <div class="row g-2" id="inst-logo-grid"></div>
        <button type="button" class="btn btn-outline-secondary btn-sm w-100 mt-3 d-none" id="inst-logo-more">Daha fazla yükle</button>
      </div>
    </div>
  </div>
</div>

<script>
(() => {
  const idInput = document.getElementById('inst-logo-id');
  const slot    = document.getElementById('inst-logo-slot');
  const pickBtn = document.getElementById('inst-logo-pick');
  const modalEl = document.getElementById('inst-logo-modal');
  const grid    = document.getElementById('inst-logo-grid');
  const loading = document.getElementById('inst-logo-loading');
  const emptyEl = document.getElementById('inst-logo-empty');
  const search  = document.getElementById('inst-logo-search');
  const moreBtn = document.getElementById('inst-logo-more');
  let modal = null, page = 1, hasMore = false, searchTimer = null;
  function getModal() { if (!modal) modal = bootstrap.Modal.getOrCreateInstance(modalEl); return modal; }

  document.addEventListener('click', (e) => {
    if (e.target.closest('#inst-logo-clear')) {
      e.preventDefault();
      idInput.value = '0';
      const cur = document.getElementById('inst-logo-current');
      if (cur) cur.remove();
    }
  });

  function applyPick(it) {
    idInput.value = it.id;
    const old = document.getElementById('inst-logo-current');
    if (old) old.remove();
    const div = document.createElement('div');
    div.id = 'inst-logo-current';
    div.className = 'd-flex align-items-center gap-2 px-3 py-2 rounded border bg-light';
    div.innerHTML = `
      <img alt="" style="width:48px;height:48px;object-fit:contain">
      <span class="small fw-semibold"></span>
      <button type="button" class="btn btn-sm btn-outline-danger" id="inst-logo-clear"><i class="bi bi-x-lg"></i></button>`;
    div.querySelector('img').src = it.url;
    div.querySelector('span').textContent = it.name;
    slot.insertBefore(div, pickBtn);
  }

  async function loadList(reset) {
    if (reset) { grid.innerHTML = ''; page = 1; emptyEl.classList.add('d-none'); moreBtn.classList.add('d-none'); }
    else page++;
    loading.classList.remove('d-none');
    try {
      const url = `/admin/media.json?kind=image&q=${encodeURIComponent(search.value || '')}&page=${page}`;
      const res = await fetch(url, { credentials: 'same-origin' });
      const data = await res.json();
      loading.classList.add('d-none');
      if (reset && (!data.items || !data.items.length)) {
        emptyEl.classList.remove('d-none');
        moreBtn.classList.add('d-none');
        return;
      }
      (data.items || []).forEach(it => {
        const col = document.createElement('div');
        col.className = 'col-6 col-md-3';
        col.innerHTML = `
          <div class="border rounded p-2 text-center" style="cursor:pointer">
            <img alt="" style="width:100%;height:80px;object-fit:contain">
            <div class="small mt-1 text-truncate"></div>
          </div>`;
        col.querySelector('img').src = it.url;
        col.querySelector('.small').textContent = it.name;
        col.firstElementChild.addEventListener('click', () => { applyPick(it); getModal().hide(); });
        grid.appendChild(col);
      });
      hasMore = !!data.hasMore;
      moreBtn.classList.toggle('d-none', !hasMore);
    } catch (e) {
      loading.classList.add('d-none');
      if (reset) grid.innerHTML = '<div class="col-12 text-danger small">Yüklenemedi.</div>';
    }
  }

  pickBtn.addEventListener('click', () => { loadList(true); getModal().show(); });
  search.addEventListener('input', () => {
    clearTimeout(searchTimer); searchTimer = setTimeout(() => loadList(true), 250);
  });
  moreBtn.addEventListener('click', () => loadList(false));
})();
</script>
