<?php use function App\e; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Fiziksel Soruları Tamamla</h1>
    <div class="page-sub">Öğrenci testi bitirdi; öğrenciyle birlikte fiziksel soruları yanıtla.</div>
  </div>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <th>Test</th>
        <?php if ($isAdmin): ?><th>Öğretmen</th><?php endif; ?>
        <th>Bitiş</th>
        <th class="text-end">Kalan</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 6 : 5 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-check2-all"></i></div>Bekleyen yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['student_name']) ?></td>
          <td><?= e($i['test_title']) ?></td>
          <?php if ($isAdmin): ?><td class="muted tiny"><?= e($i['teacher_name'] ?? '—') ?></td><?php endif; ?>
          <td class="muted tiny"><?= e($i['finished_at']) ?></td>
          <td class="text-end"><?= (int)$i['phys_total'] - (int)$i['phys_done'] ?> / <?= (int)$i['phys_total'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-primary" href="/teacher/physical/<?= (int)$i['id'] ?>">Doldur</a>
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/teacher/incomplete-pdf/<?= (int)$i['id'] ?>"><i class="bi bi-file-earmark-pdf"></i></a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
