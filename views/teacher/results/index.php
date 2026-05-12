<?php
use function App\e;
$isAdmin = !empty($isAdmin) || (($me['role'] ?? '') === 'admin');
$institutions = $institutions ?? [];
$campuses     = $campuses     ?? [];
$filters      = $filters      ?? ['institution_id' => 0, 'campus_id' => 0];
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Raporlar</h1>
    <div class="page-sub">Tamamlanmış / kağıt-kalem bekleyen test raporları.</div>
  </div>
</div>

<?php if ($isAdmin): ?>
<form id="reports-filter" method="get" action="/teacher/results" class="card shadow-sm mb-3">
  <div class="card-body">
    <div class="row g-2 align-items-end">
      <div class="col-12 col-md-4">
        <label class="form-label small mb-1">Kurum</label>
        <select name="institution_id" id="f_inst" class="form-select form-select-sm">
          <option value="">Tüm kurumlar</option>
          <?php foreach ($institutions as $i): ?>
            <option value="<?= (int)$i['id'] ?>" <?= (int)$filters['institution_id'] === (int)$i['id'] ? 'selected' : '' ?>>
              <?= e($i['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4">
        <label class="form-label small mb-1">Kampüs</label>
        <select name="campus_id" id="f_camp" class="form-select form-select-sm">
          <option value="">Tüm kampüsler</option>
          <?php foreach ($campuses as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (int)$filters['campus_id'] === (int)$c['id'] ? 'selected' : '' ?>>
              <?= e($c['name']) ?><?= isset($c['inst_name']) ? ' — ' . e($c['inst_name']) : '' ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="col-12 col-md-4 d-flex gap-2">
        <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-funnel"></i> Filtrele</button>
        <a href="/teacher/results" class="btn btn-outline-secondary btn-sm"><i class="bi bi-x-lg"></i> Temizle</a>
        <span class="ms-auto muted tiny align-self-center"><?= count($items) ?> kayıt</span>
      </div>
    </div>
  </div>
</form>
<?php endif; ?>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <?php if ($isAdmin): ?><th>Kurum / Kampüs</th><?php endif; ?>
        <th>Test</th>
        <?php if ($isAdmin): ?><th>Öğretmen</th><?php endif; ?>
        <th>Durum</th>
        <?php if ($isAdmin): ?><th class="text-end">Skor</th><?php endif; ?>
        <th>Bitiş</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php
      $colspan = $isAdmin ? 8 : 5;
      if (!$items): ?>
        <tr><td colspan="<?= $colspan ?>"><div class="empty-state"><div class="icon"><i class="bi bi-clipboard"></i></div>Henüz tamamlanmış test yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['student_name']) ?></td>
          <?php if ($isAdmin): ?>
            <td class="tiny">
              <div class="fw-semibold"><?= e($i['institution_name'] ?? '—') ?></div>
              <div class="muted"><?= e($i['campus_name'] ?? '—') ?></div>
            </td>
          <?php endif; ?>
          <td><?= e($i['test_title']) ?></td>
          <?php if ($isAdmin): ?><td class="muted tiny"><?= e($i['teacher_name'] ?? '—') ?></td><?php endif; ?>
          <td>
            <?= $i['status'] === 'completed'
              ? '<span class="badge text-bg-success">Tamamlandı</span>'
              : '<span class="badge text-bg-warning">Fiziksel bekliyor</span>' ?>
          </td>
          <?php if ($isAdmin): ?><td class="text-end"><?= $i['total_score'] !== null ? e($i['total_score']) : '—' ?></td><?php endif; ?>
          <td class="muted tiny"><?= e($i['finished_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="/teacher/results/<?= (int)$i['id'] ?>/olgunluk-pdf?v=<?= time() ?>" target="_blank" title="Sonuç Raporu">
              <i class="bi bi-file-earmark-pdf"></i> Sonuç Raporu
            </a>
            <?php if ($i['status'] === 'needs_physical'): ?>
              <a class="btn btn-sm btn-warning" href="/teacher/physical/<?= (int)$i['id'] ?>" title="Kağıt-kalem sorularını gir">
                <i class="bi bi-pencil-square"></i> Kağıt-Kalem gir
              </a>
            <?php endif; ?>
            <?php if ($isAdmin): ?>
              <a class="btn btn-sm btn-outline-secondary" href="/teacher/results/<?= (int)$i['id'] ?>" title="Detay">Detay</a>
              <a class="btn btn-sm btn-outline-secondary" href="/teacher/results/<?= (int)$i['id'] ?>/pdf" target="_blank" title="Detaylı PDF">
                <i class="bi bi-file-earmark-pdf"></i> Detaylı
              </a>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>

<?php if ($isAdmin): ?>
<script>
(() => {
  const inst = document.getElementById('f_inst');
  const camp = document.getElementById('f_camp');
  const form = document.getElementById('reports-filter');
  inst?.addEventListener('change', () => {
    if (camp) camp.value = '';
    form?.submit();
  });
})();
</script>
<?php endif; ?>
