<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğretmenler</h1>
    <div class="page-sub">Hesapları sen oluşturursun; öğretmenler kendi öğrencilerini ekler.</div>
  </div>
  <a href="/admin/teachers/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğretmen</a>
</div>

<div class="table-wrap">
  <table class="table">
    <thead><tr><th>Ad</th><th>E-posta</th><th class="text-end">Öğrenci</th><th>Durum</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-person-badge"></i></div>Henüz öğretmen yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="fw-semibold"><?= e($i['full_name']) ?></td>
          <td class="muted"><?= e($i['email']) ?></td>
          <td class="text-end"><?= (int)$i['scount'] ?></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <form class="d-inline-flex gap-1" method="post" action="/admin/teachers/<?= (int)$i['id'] ?>/reset">
              <?= csrfField() ?>
              <input class="form-control form-control-sm" name="password" placeholder="yeni şifre" style="width:130px">
              <button class="btn btn-sm btn-outline-secondary">Sıfırla</button>
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
