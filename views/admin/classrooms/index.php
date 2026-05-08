<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Sınıflar</h1>
    <div class="page-sub">Sınıfları sen oluşturursun. Öğretmenlere sınıf ataması Öğretmen Düzenle ekranından yapılır.</div>
  </div>
  <a href="/admin/classrooms/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Sınıf</a>
</div>

<form class="card mb-3" method="get"><div class="card-body py-2">
  <div class="d-flex flex-wrap gap-2 align-items-center">
    <label class="form-label m-0 muted tiny">Kampüs</label>
    <select name="campus_id" class="form-select form-select-sm" style="max-width:320px" onchange="this.form.submit()">
      <option value="0">Tümü</option>
      <?php foreach ($campuses as $c): ?>
        <option value="<?= (int)$c['id'] ?>" <?= ($campusFilter == $c['id']) ? 'selected' : '' ?>>
          <?= e($c['institution_name']) ?> — <?= e($c['campus_name']) ?>
        </option>
      <?php endforeach; ?>
    </select>
  </div>
</div></form>

<div class="table-wrap">
  <table class="table align-middle">
    <thead>
      <tr>
        <th style="width:60px">ID</th>
        <th>Kurum / Kampüs</th>
        <th>Sınıf</th>
        <th>Şube</th>
        <th>Tam Ad</th>
        <th class="text-end">Öğretmen</th>
        <th class="text-end">Öğrenci</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="8"><div class="empty-state"><div class="icon"><i class="bi bi-mortarboard"></i></div>Sınıf yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr>
          <td class="muted tiny">#<?= (int)$i['id'] ?></td>
          <td>
            <span class="badge text-bg-light"><?= e($i['institution_name']) ?></span>
            <span class="muted">/</span>
            <span><?= e($i['campus_name']) ?></span>
          </td>
          <td><?= e($i['grade_level'] ?? '—') ?></td>
          <td><?= e($i['section'] ?? '—') ?></td>
          <td class="fw-semibold"><?= e($i['name']) ?></td>
          <td class="text-end"><?= (int)$i['tcount'] ?></td>
          <td class="text-end"><?= (int)$i['scount'] ?></td>
          <td class="text-end">
            <a class="btn btn-sm btn-outline-secondary" href="/admin/classrooms/<?= (int)$i['id'] ?>/edit">Düzenle</a>
            <form method="post" action="/admin/classrooms/<?= (int)$i['id'] ?>/delete" class="d-inline" onsubmit="return confirm('Sınıf silinsin mi? (Öğrenciler sınıfsız kalır, öğretmen atamaları silinir.)')">
              <?= csrfField() ?>
              <button class="btn btn-sm btn-outline-danger">Sil</button>
            </form>
          </td>
        </tr>
      <?php endforeach; endif; ?>
    </tbody>
  </table>
</div>
