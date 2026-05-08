<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Kampüsler</h1>
    <div class="page-sub">Her kampüs bir kuruma bağlıdır. Öğretmenler ve öğrenciler bir kampüse aittir.</div>
  </div>
  <a href="/admin/campuses/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Kampüs</a>
</div>

<div class="table-wrap">
  <table class="table align-middle">
    <thead><tr><th>Kurum</th><th>Kampüs</th><th class="text-end">Öğretmen</th><th class="text-end">Öğrenci</th><th></th></tr></thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="5"><div class="empty-state"><div class="icon"><i class="bi bi-geo-alt"></i></div>Kampüs yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td><span class="badge text-bg-light"><?= e($i['institution_name']) ?></span></td>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="text-end"><?= (int)$i['teacher_count'] ?></td>
          <td class="text-end"><?= (int)$i['student_count'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/campuses/<?= (int)$i['id'] ?>/edit">Düzenle</a>
            <form method="post" action="/admin/campuses/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Kampüs silinsin mi?')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
