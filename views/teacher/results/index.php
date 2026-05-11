<?php use function App\e; $isAdmin = !empty($isAdmin) || (($me['role'] ?? '') === 'admin'); ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Raporlar</h1>
    <div class="page-sub">Tamamlanmış / kağıt-kalem bekleyen test raporları.</div>
  </div>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <th>Test</th>
        <?php if ($isAdmin): ?><th>Öğretmen</th><?php endif; ?>
        <th>Durum</th>
        <?php if ($isAdmin): ?><th class="text-end">Skor</th><?php endif; ?>
        <th>Bitiş</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 7 : 5 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-clipboard"></i></div>Henüz tamamlanmış test yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['student_name']) ?></td>
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
