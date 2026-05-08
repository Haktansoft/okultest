<?php use function App\e; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sonuçlar</h1>
    <div class="page-sub">Tamamlanmış / fiziksel bekleyen test sonuçları.</div>
  </div>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <th>Test</th>
        <?php if ($isAdmin): ?><th>Öğretmen</th><?php endif; ?>
        <th>Durum</th>
        <th class="text-end">Skor</th>
        <th>Bitiş</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 7 : 6 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-clipboard"></i></div>Henüz tamamlanmış test yok.</div></td></tr>
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
          <td class="text-end"><?= $i['total_score'] !== null ? e($i['total_score']) : '—' ?></td>
          <td class="muted tiny"><?= e($i['finished_at']) ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-primary" href="/teacher/results/<?= (int)$i['id'] ?>">Detay</a>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
