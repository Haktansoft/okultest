<?php use function App\e; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sınıflarım</h1>
    <div class="page-sub">Sana atanmış sınıflar. Sınıf yönetimi yöneticidedir; ekleme/değişiklik için yöneticiye başvur.</div>
  </div>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Kampüs</th><th>Sınıf</th><th>Şube</th><th>Tam Ad</th><th class="text-end">Öğrenci</th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-mortarboard"></i></div>Sana atanmış sınıf yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="muted tiny"><?= e($i['institution_name']) ?> / <?= e($i['campus_name']) ?></td>
          <td><?= e($i['grade_level'] ?? '—') ?></td>
          <td><?= e($i['section'] ?? '—') ?></td>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="text-end"><?= (int)$i['scount'] ?></td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
