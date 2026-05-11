<?php
use function App\{e, formatDuration};
$a = $assignment;
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sonuç — <?= e($a['student_name']) ?></h1>
    <div class="page-sub">Test: <?= e($a['test_title']) ?> · Bitiş: <?= e($a['finished_at']) ?></div>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a class="btn btn-sm btn-primary" target="_blank" href="/teacher/results/<?= (int)$a['id'] ?>/olgunluk-pdf?v=<?= time() ?>"><i class="bi bi-file-earmark-pdf"></i> Sonuç Raporu</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/teacher/results/<?= (int)$a['id'] ?>/pdf"><i class="bi bi-file-earmark-text"></i> Detaylı Sonuç PDF</a>
    <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/teacher/incomplete-pdf/<?= (int)$a['id'] ?>"><i class="bi bi-file-earmark-pdf"></i> Eksikler PDF</a>
    <?php if ($a['status'] === 'needs_physical'): ?>
      <a class="btn btn-sm btn-warning" href="/teacher/physical/<?= (int)$a['id'] ?>"><i class="bi bi-pencil-square"></i> Kağıt-Kalem doldur</a>
    <?php endif; ?>
  </div>
</div>

<div class="row g-3 mb-4">
  <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-trophy"></i></div><div><div class="num"><?= e($a['total_score'] ?? '—') ?></div><div class="lbl">Toplam Skor</div></div></div></div>
  <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-stopwatch"></i></div><div><div class="num"><?= e(formatDuration($totalDuration)) ?></div><div class="lbl">Toplam Süre</div></div></div></div>
  <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-person-check"></i></div><div><div class="num"><?= e($a['mode'] === 'bulk' ? 'Öğretmen' : ($a['mode'] === 'per_question' ? 'Öğrenci' : '—')) ?></div><div class="lbl">Yanıtları kim girdi</div></div></div></div>
  <div class="col-md-3"><div class="stat-tile"><div class="ic"><i class="bi bi-list-ol"></i></div><div><div class="num"><?= count($questions) ?></div><div class="lbl">Soru sayısı</div></div></div></div>
</div>

<?php foreach ($questions as $idx => $q):
  $isPhys = (bool)$q['is_physical'];
  $ans = $isPhys ? $q['physical_answer'] : $q['answer'];
?>
  <div class="card mb-2">
    <div class="card-body">
      <div class="d-flex justify-content-between align-items-start">
        <div>
          <span class="muted tiny">#<?= $idx + 1 ?> · <?= e($q['category_name']) ?></span>
          <?php if ($isPhys): ?><span class="badge text-bg-warning ms-1">Kağıt-Kalem</span><?php endif; ?>
        </div>
        <div class="muted tiny">
          <?php if (!$isPhys && $q['answer']): ?>Süre: <?= e(formatDuration((int)$q['answer']['time_spent_seconds'])) ?><?php endif; ?>
        </div>
      </div>
      <div class="mt-2 fw-semibold"><?= e($q['prompt']) ?></div>
      <ul class="mt-2 mb-0 list-unstyled">
        <?php foreach ($q['options'] as $j => $o):
          $picked = $ans && (int)$ans['selected_option_id'] === (int)$o['id'];
        ?>
          <li class="<?= $picked ? 'fw-semibold' : '' ?>">
            <strong><?= chr(65+$j) ?>)</strong> <?= e($o['label']) ?>
            <?php if ((float)$o['score'] > 0): ?><span class="text-success tiny ms-1">(+<?= e($o['score']) ?>)</span><?php endif; ?>
            <?php if ($picked): ?><span class="badge text-bg-primary ms-1">öğrenci seçti</span><?php endif; ?>
          </li>
        <?php endforeach; ?>
      </ul>
      <?php if (!$ans): ?>
        <div class="text-danger tiny mt-2"><i class="bi bi-exclamation-triangle"></i>
          <?= $isPhys ? 'Kağıt-kalem yanıtı henüz girilmedi.' : 'Cevaplanmadı.' ?>
        </div>
      <?php else: ?>
        <div class="muted tiny mt-2">Kazanılan: <strong><?= e($ans['option_score'] ?? '0') ?></strong></div>
      <?php endif; ?>
    </div>
  </div>
<?php endforeach; ?>
