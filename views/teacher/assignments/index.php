<?php use function App\{e, csrfField};
$statusBadge = [
  'pending'        => '<span class="badge text-bg-secondary">Bekliyor</span>',
  'in_progress'    => '<span class="badge text-bg-info">Devam ediyor</span>',
  'needs_physical' => '<span class="badge text-bg-warning">Fiziksel bekliyor</span>',
  'completed'      => '<span class="badge text-bg-success">Tamamlandı</span>',
];
?>
<div class="page-header">
  <div>
    <h1 class="page-title">Atamalar</h1>
    <div class="page-sub">Hangi öğrenciye hangi test verildi. Bekleyen testler için "Toplu gir" ile yanıtları sen de girebilirsin.</div>
  </div>
  <a href="/teacher/assignments/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Atama</a>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Öğrenci</th>
        <th>Test</th>
        <th>Atayan</th>
        <th>Durum</th>
        <th class="text-end">Skor</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="icon"><i class="bi bi-send"></i></div>Atama yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['student_name']) ?></td>
          <td><?= e($i['test_title']) ?></td>
          <td class="muted tiny"><?= e($i['teacher_name'] ?? '—') ?></td>
          <td><?= $statusBadge[$i['status']] ?? e($i['status']) ?></td>
          <td class="text-end"><?= $i['total_score'] !== null ? e($i['total_score']) : '<span class="muted">—</span>' ?></td>
          <td class="text-end">
            <?php if (in_array($i['status'], ['pending','in_progress'], true)): ?>
              <a class="btn btn-sm btn-primary" href="/teacher/assignments/<?= (int)$i['id'] ?>/bulk" title="Toplu yanıt gir">
                <i class="bi bi-input-cursor-text"></i> Toplu gir
              </a>
              <a class="btn btn-sm btn-success" href="/teacher/assignments/<?= (int)$i['id'] ?>/run" title="Öğrenci ekranı gibi çözdür">
                <i class="bi bi-play-circle"></i> Öğrenci gibi çöz
              </a>
            <?php endif; ?>
            <?php if (in_array($i['status'], ['completed','needs_physical'], true)): ?>
              <a class="btn btn-sm btn-outline-primary" href="/teacher/results/<?= (int)$i['id'] ?>">Sonuç</a>
            <?php endif; ?>
            <?php if ($i['status'] === 'needs_physical'): ?>
              <a class="btn btn-sm btn-warning" href="/teacher/physical/<?= (int)$i['id'] ?>">Fiziksel</a>
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
