<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Kurumlar</h1>
    <div class="page-sub">Bir kurum birden fazla kampüse sahip olabilir; öğretmenler ve öğrenciler kampüslere bağlanır.</div>
  </div>
  <a href="/admin/institutions/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Kurum</a>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th style="width:80px">Logo</th><th>Ad</th><th class="text-end">Kampüs</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4"><div class="empty-state"><div class="icon"><i class="bi bi-building"></i></div>Kurum yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td>
            <?php if (!empty($i['logo_media_id'])): ?>
              <img src="/media/<?= (int)$i['logo_media_id'] ?>" alt="" style="width:48px;height:48px;object-fit:contain;border-radius:8px;border:1px solid #e2e8f0;background:#fff">
            <?php else: ?>
              <span class="muted"><i class="bi bi-image"></i></span>
            <?php endif; ?>
          </td>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="text-end"><?= (int)$i['camp_count'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/institutions/<?= (int)$i['id'] ?>/edit">Düzenle</a>
            <form method="post" action="/admin/institutions/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Kurum (ve tüm kampüs/sınıfları) silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
