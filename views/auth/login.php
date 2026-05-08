<?php use function App\{e, csrfField}; ?>
<?php if (!empty($error)): ?>
  <div class="alert alert-danger py-2 small"><?= e($error) ?></div>
<?php endif; ?>
<form method="post" action="/login" autocomplete="off">
  <?= csrfField() ?>
  <div class="mb-3">
    <label class="form-label">Şifre</label>
    <input class="form-control" type="password" name="password" autofocus required>
    <div class="form-help mt-1">Sisteme yalnızca şifrenizle giriş yaparsınız.</div>
  </div>
  <button class="btn btn-primary w-100">Giriş yap</button>
</form>
