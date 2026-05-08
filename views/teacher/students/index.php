<?php use function App\{e, csrfField}; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Öğrenciler</h1>
    <div class="page-sub">Tüm öğrenciler. Her öğretmen düzenleyebilir. Giriş sadece şifre ile yapılır.</div>
  </div>
  <a href="/teacher/students/new" class="btn btn-primary"><i class="bi bi-plus-lg"></i> Yeni Öğrenci</a>
</div>

<div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th>Ad</th>
        <th>Şifre</th>
        <th>Durum</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php if (!$items): ?>
        <tr><td colspan="4"><div class="empty-state"><div class="icon"><i class="bi bi-people"></i></div>Henüz öğrenci yok.</div></td></tr>
      <?php else: foreach ($items as $i): ?>
        <tr data-row-id="<?= (int)$i['id'] ?>">
          <td>
            <div class="name-cell d-flex align-items-center gap-2">
              <span class="name-text fw-semibold"><?= e($i['full_name']) ?></span>
              <button type="button" class="btn btn-sm btn-link p-0 muted name-edit-btn" title="Düzenle"><i class="bi bi-pencil"></i></button>
            </div>
            <form class="name-edit-form d-none d-flex align-items-center gap-1" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/rename">
              <?= csrfField() ?>
              <input class="form-control form-control-sm" name="full_name" value="<?= e($i['full_name']) ?>" required maxlength="150">
              <button class="btn btn-sm btn-primary" title="Kaydet"><i class="bi bi-check-lg"></i></button>
              <button type="button" class="btn btn-sm btn-outline-secondary name-edit-cancel" title="İptal"><i class="bi bi-x-lg"></i></button>
            </form>
          </td>
          <td><code><?= e($i['password']) ?></code></td>
          <td><?= $i['is_active'] ? '<span class="badge text-bg-success">Aktif</span>' : '<span class="badge text-bg-secondary">Pasif</span>' ?></td>
          <td class="text-end">
            <form class="d-inline-flex gap-1" method="post" action="/teacher/students/<?= (int)$i['id'] ?>/reset">
              <?= csrfField() ?>
              <input class="form-control form-control-sm" name="password" placeholder="yeni şifre" style="width:140px" autocomplete="off">
              <button class="btn btn-sm btn-outline-secondary">Şifre Değiştir</button>
            </form>
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

<script>
document.querySelectorAll('.name-edit-btn').forEach(btn => {
  btn.addEventListener('click', () => {
    const tr = btn.closest('tr');
    tr.querySelector('.name-cell').classList.add('d-none');
    const form = tr.querySelector('.name-edit-form');
    form.classList.remove('d-none');
    const inp = form.querySelector('input[name="full_name"]');
    inp.focus(); inp.select();
  });
});
document.querySelectorAll('.name-edit-cancel').forEach(btn => {
  btn.addEventListener('click', () => {
    const tr = btn.closest('tr');
    tr.querySelector('.name-edit-form').classList.add('d-none');
    tr.querySelector('.name-cell').classList.remove('d-none');
  });
});
</script>
