<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğrenciler</h1>
    <div class="page-sub">Tüm öğrenciler. Giriş şifresi T.C. Kimlik Numarasıdır.</div>
  </div>
  <a href="/teacher/students/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğrenci</a>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th>Ad-Soyad</th>
        <th>T.C. (Şifre)</th>
        <th>Sınıf</th>
        <th>Şube</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="6"><div class="empty-state"><div class="icon"><i class="bi bi-people"></i></div>Henüz öğrenci yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td><code><?= e($i['tc'] ?? $i['password']) ?></code></td>
          <td><?= e($i['grade_level'] ?? '—') ?></td>
          <td><?= e($i['section'] ?? '—') ?></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/teacher/students/<?= (int)$i['id'] ?>/edit">
              <i class="bi bi-pencil"></i> Düzenle
            </a>
            <form class="d-inline" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/toggle">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-secondary"><?= $i['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
