<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $isAdmin ? 'Sorular — ' : 'Test İçeriği — ' ?><?= e($test['title']) ?></h1>
    <div class="page-sub">
      <?= $isAdmin
          ? 'Soldaki listeyi sürükleyerek sıralayabilirsin. Sağdan kategoriye göre yeni sorular ekleyebilirsin.'
          : 'Bu testte yer alan sorular.' ?>
    </div>
  </div>
  <div class="d-flex gap-2">
    <a class="btn btn-outline-secondary btn-sm" target="_blank" href="/admin/tests/<?= (int)$test['id'] ?>/pdf"><i class="bi bi-file-earmark-pdf"></i> PDF</a>
    <a href="/admin/tests" class="btn btn-link"><i class="bi bi-arrow-left"></i> Testlere dön</a>
  </div>
</div>

<div class="row g-3">
  <div class="col-lg-<?= $isAdmin ? '6' : '12' ?>">
    <div class="card h-100">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Bu testteki sorular (<?= count($assigned) ?>)</span>
        <?php if ($isAdmin): ?><span class="muted tiny">Sürükleyerek sırala</span><?php endif; ?>
      </div>
      <div class="card-body p-0">
        <ul id="assigned-list" class="list-group list-group-flush" style="border-radius: 0;">
          <?php if (!$assigned): ?>
            <li class="list-group-item p-0"><div class="empty-state"><div class="icon"><i class="bi bi-arrow-right-circle"></i></div><?= $isAdmin ? 'Sağdan soru ekle.' : 'Bu testte henüz soru yok.' ?></div></li>
          <?php else: foreach ($assigned as $idx => $q): ?>
            <li class="list-group-item d-flex align-items-center" data-id="<?= (int)$q['id'] ?>" <?= $isAdmin ? 'draggable="true" style="cursor:move"' : '' ?>>
              <?php if ($isAdmin): ?>
                <i class="bi bi-grip-vertical muted me-2"></i>
              <?php else: ?>
                <span class="badge text-bg-light me-2">#<?= $idx + 1 ?></span>
              <?php endif; ?>
              <div class="flex-grow-1">
                <div><?= e(mb_strimwidth(strip_tags($q['prompt']),0,90,'…','UTF-8')) ?></div>
                <div class="muted tiny">
                  <?= e($q['category_name']) ?>
                  <?php if ($q['is_physical']): ?><span class="badge text-bg-warning ms-1">Fiziksel</span><?php endif; ?>
                </div>
              </div>
              <?php if ($isAdmin): ?>
                <form method="post" action="/admin/tests/<?= (int)$test['id'] ?>/questions" class="m-0">
                  <?= csrfField() ?>
                  <input type="hidden" name="action" value="remove">
                  <input type="hidden" name="question_id" value="<?= (int)$q['id'] ?>">
                  <button class="btn btn-sm btn-outline-danger">Çıkar</button>
                </form>
              <?php endif; ?>
            </li>
          <?php endforeach; endif; ?>
        </ul>
      </div>
      <?php if ($isAdmin && $assigned): ?>
      <div class="card-footer">
        <form method="post" action="/admin/tests/<?= (int)$test['id'] ?>/questions" class="m-0" id="reorder-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="reorder">
          <input type="hidden" name="order" id="order-input">
          <button class="btn btn-sm btn-primary" id="save-order" style="display:none">Sıralamayı kaydet</button>
        </form>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <?php if ($isAdmin): ?>
  <div class="col-lg-6">
    <div class="card">
      <div class="card-header d-flex justify-content-between align-items-center">
        <span>Eklenebilir sorular <span class="muted tiny">(<?= count($available) ?>)</span></span>
        <?php if ($available): ?>
          <div class="d-flex align-items-center gap-2">
            <input id="avail-search" type="search" class="form-control form-control-sm" placeholder="Aramak için yaz…" style="width:200px;">
          </div>
        <?php endif; ?>
      </div>
      <div class="card-body">
        <form method="get" class="d-flex gap-2 mb-3">
          <select name="category_id" class="form-select form-select-sm" onchange="this.form.submit()">
            <option value="0">Tüm kategoriler</option>
            <?php foreach ($cats as $c): ?>
              <option value="<?= (int)$c['id'] ?>" <?= $selectedCat == $c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
          </select>
        </form>

        <form method="post" action="/admin/tests/<?= (int)$test['id'] ?>/questions" id="add-form">
          <?= csrfField() ?>
          <input type="hidden" name="action" value="add">

          <?php if ($available): ?>
            <div class="d-flex justify-content-between align-items-center pb-2 border-bottom mb-1">
              <label class="d-flex align-items-center gap-2 m-0 fw-semibold tiny" style="cursor:pointer;">
                <input type="checkbox" id="select-all" class="form-check-input m-0">
                <span>Tümünü seç</span>
                <span class="muted">(<span id="visible-count"><?= count($available) ?></span> görünür)</span>
              </label>
              <span id="selected-count" class="badge text-bg-primary" style="display:none">0 seçili</span>
            </div>
          <?php endif; ?>

          <div id="avail-list" style="max-height:55vh; overflow:auto">
            <?php if (!$available): ?>
              <div class="empty-state"><div class="icon"><i class="bi bi-check2-circle"></i></div>Eklenebilir soru yok.</div>
            <?php else: foreach ($available as $q):
              $textForSearch = mb_strtolower(strip_tags($q['prompt']) . ' ' . $q['category_name'], 'UTF-8');
            ?>
              <label class="avail-row d-flex align-items-start py-2 px-1 border-bottom" data-search="<?= e($textForSearch) ?>" style="cursor:pointer">
                <input type="checkbox" name="question_ids[]" value="<?= (int)$q['id'] ?>" class="form-check-input me-2 mt-1 q-check">
                <span class="flex-grow-1">
                  <div><?= e(mb_strimwidth(strip_tags($q['prompt']),0,100,'…','UTF-8')) ?></div>
                  <div class="muted tiny">
                    <?= e($q['category_name']) ?>
                    <?php if ($q['is_physical']): ?><span class="badge text-bg-warning ms-1">Fiziksel</span><?php endif; ?>
                  </div>
                </span>
              </label>
            <?php endforeach; endif; ?>
          </div>

          <?php if ($available): ?>
          <div class="mt-3 d-flex justify-content-between align-items-center">
            <span class="muted tiny">İpucu: "Tümünü seç" şu anda <strong>görünen</strong> soruları kapsar.</span>
            <button class="btn btn-primary btn-sm" id="add-btn" disabled>Seçilenleri ekle</button>
          </div>
          <?php endif; ?>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>
</div>

<?php if ($isAdmin): ?>
<script>
(() => {
  // Atanmış sorular: sürükle-sırala
  const list = document.getElementById('assigned-list');
  if (list) {
    let dragSrc = null;
    list.querySelectorAll('li[data-id]').forEach(li => {
      li.addEventListener('dragstart', e => { dragSrc = li; li.style.opacity = .5; });
      li.addEventListener('dragend',   e => { li.style.opacity = 1; });
      li.addEventListener('dragover',  e => { e.preventDefault(); });
      li.addEventListener('drop',      e => {
        e.preventDefault();
        if (!dragSrc || dragSrc === li) return;
        const rect = li.getBoundingClientRect();
        const after = (e.clientY - rect.top) > rect.height/2;
        list.insertBefore(dragSrc, after ? li.nextSibling : li);
        document.getElementById('save-order').style.display = '';
      });
    });
    document.getElementById('reorder-form')?.addEventListener('submit', () => {
      const ids = [...list.querySelectorAll('li[data-id]')].map(li => li.dataset.id);
      document.getElementById('order-input').value = ids.join(',');
    });
  }

  // Eklenebilir: arama + tümünü seç + sayaç
  const availList = document.getElementById('avail-list');
  if (!availList) return;
  const search    = document.getElementById('avail-search');
  const selectAll = document.getElementById('select-all');
  const visibleCt = document.getElementById('visible-count');
  const selectedCt= document.getElementById('selected-count');
  const addBtn    = document.getElementById('add-btn');
  const allRows = () => availList.querySelectorAll('.avail-row');
  const visibleRows = () => [...availList.querySelectorAll('.avail-row')].filter(r => r.style.display !== 'none');
  const checkedRows = () => [...availList.querySelectorAll('.q-check')].filter(c => c.checked);
  function refreshCounts() {
    const visible = visibleRows();
    const checkedVisible = visible.map(r => r.querySelector('.q-check')).filter(c => c.checked).length;
    if (visibleCt) visibleCt.textContent = visible.length;
    const total = checkedRows().length;
    if (selectedCt) {
      selectedCt.style.display = total > 0 ? '' : 'none';
      selectedCt.textContent = total + ' seçili';
    }
    if (addBtn) {
      addBtn.disabled = total === 0;
      addBtn.textContent = total > 0 ? `Seç (${total}) ekle` : 'Seçilenleri ekle';
    }
    if (selectAll) {
      if (visible.length === 0)              { selectAll.checked = false; selectAll.indeterminate = false; }
      else if (checkedVisible === 0)         { selectAll.checked = false; selectAll.indeterminate = false; }
      else if (checkedVisible === visible.length) { selectAll.checked = true; selectAll.indeterminate = false; }
      else                                    { selectAll.checked = false; selectAll.indeterminate = true; }
    }
  }
  search?.addEventListener('input', () => {
    const q = search.value.trim().toLowerCase();
    allRows().forEach(r => { r.style.display = !q || r.dataset.search.includes(q) ? '' : 'none'; });
    refreshCounts();
  });
  selectAll?.addEventListener('change', () => {
    visibleRows().forEach(r => { r.querySelector('.q-check').checked = selectAll.checked; });
    refreshCounts();
  });
  availList.addEventListener('change', e => { if (e.target.classList.contains('q-check')) refreshCounts(); });
  refreshCounts();
})();
</script>
<?php endif; ?>
