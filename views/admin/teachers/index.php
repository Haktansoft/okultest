<?php use function App\{e, csrfField}; $f = $filters ?? []; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğretmenler</h1>
    <div class="page-sub">Hesapları sen oluşturursun. Her öğretmen bir kampüse bağlıdır ve sadece o kampüsü yönetir.</div>
  </div>
  <a href="/admin/teachers/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğretmen</a>
</div>

<form class="card mb-3" method="get">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" name="q" value="<?= e($f['q'] ?? '') ?>" class="form-control form-control-sm" style="max-width:240px" placeholder="Ad ara…">

      <select name="institution_id" class="form-select form-select-sm" style="max-width:200px" id="filter-inst">
        <option value="0">Tüm kurumlar</option>
        <?php foreach ($institutions as $ins): ?>
          <option value="<?= (int)$ins['id'] ?>" <?= ((int)($f['institution_id'] ?? 0) === (int)$ins['id']) ? 'selected' : '' ?>><?= e($ins['name']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="campus_id" class="form-select form-select-sm" style="max-width:240px" id="filter-camp">
        <option value="0">Tüm kampüsler</option>
        <?php foreach ($campuses as $cp): ?>
          <option value="<?= (int)$cp['id'] ?>"
                  data-inst="<?= (int)$cp['institution_id'] ?>"
                  <?= ((int)($f['campus_id'] ?? 0) === (int)$cp['id']) ? 'selected' : '' ?>>
            <?= e($cp['institution_name']) ?> — <?= e($cp['name']) ?>
          </option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-select form-select-sm" style="max-width:110px">
        <option value="">Durum</option>
        <option value="active"  <?= ($f['status'] ?? '') === 'active'  ? 'selected' : '' ?>>Aktif</option>
        <option value="passive" <?= ($f['status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif</option>
      </select>

      <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrele</button>
      <?php
        $hasFilter = !empty($f['q']) || !empty($f['institution_id']) || !empty($f['campus_id']) || !empty($f['status']);
      ?>
      <?php if ($hasFilter): ?>
        <a class="btn btn-sm btn-outline-secondary" href="/admin/teachers">Temizle</a>
      <?php endif; ?>

      <span class="ms-auto muted tiny"><?= count($items) ?> öğretmen</span>
    </div>
  </div>
</form>

<script>
(() => {
  const inst = document.getElementById('filter-inst');
  const camp = document.getElementById('filter-camp');
  if (!inst || !camp) return;
  function refilter() {
    const sel = parseInt(inst.value || '0', 10);
    Array.from(camp.options).forEach(o => {
      if (o.value === '0') return;
      const ci = parseInt(o.dataset.inst || '0', 10);
      const show = sel === 0 || ci === sel;
      o.hidden = !show;
      o.disabled = !show;
    });
    if (camp.selectedOptions[0] && camp.selectedOptions[0].hidden) camp.value = '0';
  }
  inst.addEventListener('change', refilter);
  refilter();
})();
</script>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Ad</th><th>Kurum / Kampüs</th><th>Şifre</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-person-badge"></i></div><?= $hasFilter ? 'Filtreye uyan öğretmen yok.' : 'Henüz öğretmen yok.' ?></div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td>
            <?php if (!empty($i['campus_id'])): ?>
              <span class="badge text-bg-light"><?= e($i['institution_name']) ?></span>
              <span class="muted">/</span>
              <span class="fw-semibold"><?= e($i['campus_name']) ?></span>
            <?php else: ?>
              <span class="badge text-bg-warning">Kampüs atanmamış</span>
            <?php endif; ?>
          </td>
          <td><code><?= e($i['password']) ?></code></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/teachers/<?= (int)$i['id'] ?>/edit"><i class="bi bi-pencil"></i> Düzenle</a>
            <form class="d-inline-flex gap-1 ms-1" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/reset">
              <?= csrfField() ?>
              <input class="form-control form-control-sm" name="password" placeholder="yeni şifre" style="width:130px" autocomplete="off">
              <button class="btn btn-sm btn-outline-secondary">Şifre</button>
            </form>
            <form class="d-inline" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/toggle">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-secondary"><?= $i['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
            </form>
            <form class="d-inline ms-1" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('<?= e($i['full_name']) ?> adlı öğretmeni silmek istediğine emin misin? Bu işlem, öğretmenin öğrenci atamalarını ve girdiği fiziksel ölçümleri de silecek.');">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i> Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
