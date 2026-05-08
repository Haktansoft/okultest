<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğretmenler</h1>
    <div class="page-sub">Hesapları sen oluşturursun. Her öğretmen bir kampüse bağlıdır ve sadece o kampüsü yönetir.</div>
  </div>
  <a href="/admin/teachers/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğretmen</a>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Ad</th><th>Kurum / Kampüs</th><th>Şifre</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-person-badge"></i></div>Henüz öğretmen yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td>
            <?php if (!empty($i['campus_id'])): ?>
              <span class="badge text-bg-light"><?= e($i['institution_name']) ?></span>
              <span class="muted">/</span>
              <span class="fw-semibold"><?= e($i['campus_name']) ?></span>
            <?php else: ?>
              <span class="badge text-bg-warning">Kampüs atanmamış</span>
            <?php endif; ?>
          </td>
          <td><code><?= e($i['password']) ?></code></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/teachers/<?= (int)$i['id'] ?>/edit"><i class="bi bi-pencil"></i> Düzenle</a>
            <form class="d-inline-flex gap-1 ms-1" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/reset">
              <?= csrfField() ?>
              <input class="form-control form-control-sm" name="password" placeholder="yeni şifre" style="width:130px" autocomplete="off">
              <button class="btn btn-sm btn-outline-secondary">Şifre</button>
            </form>
            <form class="d-inline" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/toggle">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-secondary"><?= $i['is_active'] ? 'Pasifleştir' : 'Aktifleştir' ?></button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
