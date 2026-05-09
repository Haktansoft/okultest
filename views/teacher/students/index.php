<?php use function App\{e, csrfField}; $isAdmin = ($me['role'] ?? '') === 'admin'; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğrenciler</h1>
    <div class="page-sub">Yeni öğrenci eklediğinde mevcut tüm testler otomatik atanır.</div>
  </div>
  <div class="d-flex gap-2">
    <?php if ($isAdmin): ?>
      <a href="/teacher/students/import" class="btn btn-outline-primary"><i class="bi bi-file-earmark-spreadsheet"></i> XLSX İçe Aktar</a>
    <?php endif; ?>
    <a href="/teacher/students/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğrenci</a>
  </div>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Ad-Soyad</th>
        <th>T.C. (Şifre)</th>
        <?php if ($isAdmin): ?><th>Kurum</th><th>Kampüs</th><?php endif; ?>
        <th>Sınıf</th>
        <th>Şube</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="<?= $isAdmin ? 8 : 6 ?>"><div class="empty-state"><div class="icon"><i class="bi bi-people"></i></div>Henüz öğrenci yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td><code><?= e($i['tc'] ?? $i['password']) ?></code></td>
          <?php if ($isAdmin): ?>
            <td><span class="badge text-bg-light"><?= e($i['institution_name'] ?? '—') ?></span></td>
            <td><?= e($i['campus_name'] ?? '—') ?></td>
          <?php endif; ?>
          <td><?= e($i['grade_level'] ?? '—') ?></td>
          <td><?= e($i['section'] ?? '—') ?></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/teacher/students/<?= (int)$i['id'] ?>/edit"><i class="bi bi-pencil"></i> Düzenle</a>
            <form class="d-inline" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/toggle">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-secondary"><?= $i['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
            </form>
            <form class="d-inline" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/delete" onsubmit="return confirm('Öğrenci silinsin mi? Tüm test kayıtları da silinir.')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger"><i class="bi bi-trash"></i></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
