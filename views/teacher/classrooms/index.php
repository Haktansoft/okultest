<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sınıflar</h1>
    <div class="page-sub">Kendi kampüsüne ait sınıfları yönetebilirsin. Öğrenciler sınıflara atanır.</div>
  </div>
  <?php if (!$isAdmin || !empty($me['campus_id'])): ?>
    <a href="/teacher/classrooms/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Sınıf</a>
  <?php endif; ?>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <?php if ($isAdmin): ?><th>Kurum</th><th>Kampüs</th><?php endif; ?>
        <th>Sınıf</th>
        <th class="text-end">Öğrenci</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 4 : 3 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-mortarboard"></i></div>Sınıf yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <?php if ($isAdmin): ?>
            <td><span class="badge text-bg-light"><?= e($i['institution_name']) ?></span></td>
            <td><?= e($i['campus_name']) ?></td>
          <?php endif; ?>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="text-end"><?= (int)$i['scount'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/teacher/classrooms/<?= (int)$i['id'] ?>/edit">Düzenle</a>
            <form method="post" action="/teacher/classrooms/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Sınıf silinsin mi? (Öğrenciler sınıfsız kalır)')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
