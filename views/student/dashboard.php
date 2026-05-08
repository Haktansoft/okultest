<?php use function App\e;
$pending = $progress = $completed = [];
foreach ($items as $i) {
    if ($i['status'] === 'in_progress') $progress[] = $i;
    elseif ($i['status'] === 'pending') $pending[] = $i;
    else $completed[] = $i;
}
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Testlerim</h1>
    <div class="page-sub">Sana atanan testleri buradan görüp çözebilirsin.</div>
  </div>
</div>

<h5 class="mt-2 mb-2">Tamamlanmamış Testler</h5>
<?php if (!$pending && !$progress): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><div class="icon"><i class="bi bi-emoji-smile"></i></div>Bekleyen testin yok.</div></div></div>
<?php else: ?>
  <div class="row g-3 mb-4">
    <?php foreach (array_merge($progress, $pending) as $a): ?>
      <div class="col-md-6">
        <a href="/student/tests/<?= (int)$a['id'] ?>/intro" class="card h-100 text-decoration-none text-dark">
          <div class="card-body d-flex justify-content-between align-items-start">
            <div>
              <div class="fw-semibold mb-1"><?= e($a['test_title']) ?></div>
              <div class="muted tiny">
                <?= (int)$a['visible_q'] ?> soru
                <?php if ($a['time_limit_minutes']): ?> · <?= (int)$a['time_limit_minutes'] ?> dk<?php endif; ?>
                <?php if ((int)$a['phys_q'] > 0): ?>
                  · <span style="color:#92400e">+<?= (int)$a['phys_q'] ?> fiziksel (öğretmenle)</span>
                <?php endif; ?>
              </div>
            </div>
            <span class="badge text-bg-<?= $a['status'] === 'in_progress' ? 'info' : 'secondary' ?>">
              <?= $a['status'] === 'in_progress' ? 'Devam ediyor' : 'Başlamadı' ?>
            </span>
          </div>
        </a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<h5 class="mt-4 mb-2">Tamamlanmış</h5>
<?php if (!$completed): ?>
  <p class="muted">Henüz tamamlanmış testin yok.</p>
<?php else: ?>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Test</th><th>Bitiş</th><th>Durum</th></tr></thead>
      <tbody>
        <?php foreach ($completed as $a): ?>
          <tr>
            <td class="fw-semibold"><?= e($a['test_title']) ?></td>
            <td class="muted tiny"><?= e($a['finished_at']) ?></td>
            <td>
              <?= $a['status'] === 'needs_physical'
                ? '<span class="badge text-bg-warning">Öğretmen değerlendiriyor</span>'
                : '<span class="badge text-bg-success">Tamamlandı</span>' ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>
