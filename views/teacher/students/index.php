<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; $f = $filters ?? []; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğrenciler</h1>
    <div class="page-sub">Yeni öğrenci eklediğinde mevcut tüm testler otomatik atanır.</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isAdmin): ?>
      <a href="/teacher/students/import" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet"></i> XLSX İçe Aktar</a>
    <?php endif; ?>
    <a href="/teacher/students/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğrenci</a>
  </div>
</div>

<form class="card mb-3" method="get">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" name="q" value="<?= e($f['q'] ?? '') ?>" class="form-control form-control-sm" style="max-width:240px" placeholder="Ad veya T.C. ara…">

      <?php if ($isAdmin): ?>
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
      <?php endif; ?>

      <select name="grade_level" class="form-select form-select-sm" style="max-width:120px">
        <option value="">Sınıf</option>
        <?php foreach ($gradeLevels as $g): ?>
          <option value="<?= e($g) ?>" <?= ($f['grade_level'] ?? '') === $g ? 'selected' : '' ?>><?= e($g) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="section" class="form-select form-select-sm" style="max-width:90px">
        <option value="">Şube</option>
        <?php foreach ($sections as $s): ?>
          <option value="<?= e($s) ?>" <?= ($f['section'] ?? '') === $s ? 'selected' : '' ?>><?= e($s) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-select form-select-sm" style="max-width:110px">
        <option value="">Durum</option>
        <option value="active"  <?= ($f['status'] ?? '') === 'active'  ? 'selected' : '' ?>>Aktif</option>
        <option value="passive" <?= ($f['status'] ?? '') === 'passive' ? 'selected' : '' ?>>Pasif</option>
      </select>

      <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrele</button>
      <?php
        $hasFilter = !empty($f['q']) || !empty($f['institution_id']) || !empty($f['campus_id'])
                  || !empty($f['grade_level']) || !empty($f['section']) || !empty($f['status']);
      ?>
      <?php if ($hasFilter): ?>
        <a class="btn btn-sm btn-outline-secondary" href="/teacher/students">Temizle</a>
      <?php endif; ?>

      <span class="ms-auto muted tiny"><?= count($items) ?> öğrenci</span>
    </div>
  </div>
</form>

<?php if ($isAdmin): ?>
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
    // Eğer seçili kampüs gizlendiyse "Tümü"ne düşür
    if (camp.selectedOptions[0] && camp.selectedOptions[0].hidden) camp.value = '0';
  }
  inst.addEventListener('change', refilter);
  refilter();
})();
</script>
<?php endif; ?>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Ad-Soyad</th>
        <th>T.C. (Şifre)</th>
        <?php if ($isAdmin): ?><th>Kurum</th><th>Kampüs</th><?php endif; ?>
        <th>Sınıf</th>
        <th>Şube</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 8 : 6 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-people"></i></div><?= $hasFilter ? 'Filtreye uyan öğrenci yok.' : 'Henüz öğrenci yok.' ?></div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td><code><?= e($i['tc'] ?? $i['password']) ?></code></td>
          <?php if ($isAdmin): ?>
            <td><span class="badge text-bg-light"><?= e($i['institution_name'] ?? '—') ?></span></td>
            <td><?= e($i['campus_name'] ?? '—') ?></td>
          <?php endif; ?>
          <td><?= e($i['grade_level'] ?? '—') ?></td>
          <td><?= e($i['section'] ?? '—') ?></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/teacher/students/<?= (int)$i['id'] ?>/edit"><i class="bi bi-pencil"></i> Düzenle</a>
            <form class="d-inline" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/toggle">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-secondary"><?= $i['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
            </form>
            <form class="d-inline" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('Öğrenci silinsin mi? Tüm test kayıtları da silinir.')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
