<?php use function App\{e, csrfField};
$statusBadge = [
  'pending'        => '<span class="badge text-bg-secondary">Bekliyor</span>',
  'in_progress'    => '<span class="badge text-bg-info">Devam ediyor</span>',
  'needs_physical' => '<span class="badge text-bg-warning">Kağıt-Kalem bekliyor</span>',
  'completed'      => '<span class="badge text-bg-success">Tamamlandı</span>',
];
$isAdmin = ($me['role'] ?? '') === 'admin';
$f = $filters ?? [];
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Testler</h1>
    <div class="page-sub">Hangi öğrenciye hangi test verildi.</div>
  </div>
  <a href="/teacher/assignments/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Test Ata</a>
</div>

<form class="card mb-3" method="get">
  <div class="card-body py-2">
    <div class="d-flex flex-wrap gap-2 align-items-center">
      <input type="text" name="q" value="<?= e($f['q'] ?? '') ?>" class="form-control form-control-sm" style="max-width:240px" placeholder="Öğrenci/T.C./test ara…">

      <?php if ($isAdmin): ?>
        <select name="institution_id" class="form-select form-select-sm" style="max-width:180px" id="filter-inst">
          <option value="0">Tüm kurumlar</option>
          <?php foreach ($institutions as $ins): ?>
            <option value="<?= (int)$ins['id'] ?>" <?= ((int)($f['institution_id'] ?? 0) === (int)$ins['id']) ? 'selected' : '' ?>><?= e($ins['name']) ?></option>
          <?php endforeach; ?>
        </select>
        <select name="campus_id" class="form-select form-select-sm" style="max-width:220px" id="filter-camp">
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

      <select name="test_id" class="form-select form-select-sm" style="max-width:200px">
        <option value="0">Tüm testler</option>
        <?php foreach ($tests as $t): ?>
          <option value="<?= (int)$t['id'] ?>" <?= ((int)($f['test_id'] ?? 0) === (int)$t['id']) ? 'selected' : '' ?>><?= e($t['title']) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="status" class="form-select form-select-sm" style="max-width:160px">
        <option value="">Tüm durumlar</option>
        <option value="pending"        <?= ($f['status'] ?? '') === 'pending'        ? 'selected' : '' ?>>Bekliyor</option>
        <option value="in_progress"    <?= ($f['status'] ?? '') === 'in_progress'    ? 'selected' : '' ?>>Devam ediyor</option>
        <option value="needs_physical" <?= ($f['status'] ?? '') === 'needs_physical' ? 'selected' : '' ?>>Kağıt-Kalem bekliyor</option>
        <option value="completed"      <?= ($f['status'] ?? '') === 'completed'      ? 'selected' : '' ?>>Tamamlandı</option>
      </select>

      <button class="btn btn-sm btn-primary"><i class="bi bi-search"></i> Filtrele</button>
      <?php
        $hasFilter = !empty($f['q']) || !empty($f['institution_id']) || !empty($f['campus_id'])
                  || !empty($f['grade_level']) || !empty($f['section']) || !empty($f['status']) || !empty($f['test_id']);
      ?>
      <?php if ($hasFilter): ?>
        <a class="btn btn-sm btn-outline-secondary" href="/teacher/assignments">Temizle</a>
      <?php endif; ?>
      <span class="ms-auto muted tiny"><?= count($items) ?> atama</span>
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
    if (camp.selectedOptions[0] && camp.selectedOptions[0].hidden) camp.value = '0';
  }
  inst.addEventListener('change', refilter);
  refilter();
})();
</script>
<?php endif; ?>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <th>T.C.</th>
        <th>Test</th>
        <th>Durum</th>
        <th class="text-end">Skor</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="icon"><i class="bi bi-send"></i></div><?= $hasFilter ? 'Filtreye uyan atama yok.' : 'Atama yok.' ?></div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['student_name']) ?></td>
          <td><code class="small"><?= e($i['student_tc'] ?? '—') ?></code></td>
          <td style="letter-spacing:-0.02em; font-size:13px;"><?= e($i['test_title']) ?></td>
          <td><?= $statusBadge[$i['status']] ?? e($i['status']) ?></td>
          <td class="text-end"><?= $i['total_score'] !== null ? e($i['total_score']) : '<span class="muted">—</span>' ?></td>
          <td class="text-end">
            <?php if (in_array($i['status'], ['pending','in_progress'], true)): ?>
              <a class="btn btn-sm btn-success" href="/teacher/assignments/<?= (int)$i['id'] ?>/run" title="Öğrenci ekranı gibi çözdür">
                <i class="bi bi-play-circle"></i> Öğrenci çöz
              </a>
            <?php endif; ?>
            <?php if (in_array($i['status'], ['completed','needs_physical'], true)): ?>
              <a class="btn btn-sm btn-outline-primary" href="/teacher/results/<?= (int)$i['id'] ?>">Sonuç</a>
            <?php endif; ?>
            <?php if ($i['status'] === 'needs_physical'): ?>
              <a class="btn btn-sm btn-warning" href="/teacher/physical/<?= (int)$i['id'] ?>">Kağıt-Kalem</a>
            <?php endif; ?>
            <?php if (in_array($i['status'], ['completed','needs_physical'], true)): ?>
              <form class="d-inline" method="post" action="/teacher/assignments/<?= (int)$i['id'] ?>/reset-physical"
                    onsubmit="return confirm('Kağıt-kalem yanıtları sıfırlansın mı?\n\nSadece fiziksel cevaplar silinir; öğretmen yeniden girebilir.')">
                <?= csrfField() ?>
                <button class="btn btn-sm btn-outline-warning" title="Kağıt-kalem yanıtlarını sıfırla">
                  <i class="bi bi-eraser"></i> Kağıt-kalem sıfırla
                </button>
              </form>
            <?php endif; ?>
            <?php if (in_array($i['status'], ['in_progress','completed','needs_physical'], true)): ?>
              <form class="d-inline" method="post" action="/teacher/assignments/<?= (int)$i['id'] ?>/reset"
                    onsubmit="return confirm('Test sıfırlansın mı?\n\nÖğrencinin tüm yanıtları, fiziksel cevaplar ve süre kayıtları silinir; test yeniden \'bekliyor\' durumuna döner.')">
                <?= csrfField() ?>
                <button class="btn btn-sm btn-outline-warning" title="Testi sıfırla — öğrenci yeniden çözebilir">
                  <i class="bi bi-arrow-counterclockwise"></i> Sıfırla
                </button>
              </form>
            <?php endif; ?>
            <?php if (in_array($i['status'], ['pending','in_progress'], true)): ?>
              <form class="d-inline" method="post" action="/teacher/assignments/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('Atama silinsin mi?')">
                <?= csrfField() ?>
                <button class="btn btn-sm btn-outline-danger" title="Atamayı sil"><i class="bi bi-trash"></i></button>
              </form>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
