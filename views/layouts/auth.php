<?php use function App\e; ?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Giriş') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body class="auth-body d-flex align-items-center justify-content-center min-vh-100 bg-light">
  <div class="auth-card card shadow-sm" style="width: 360px;">
    <div class="card-body p-4">
      <h5 class="card-title mb-3 text-center fw-semibold">Test Eğitim</h5>
      <?= $bodyContent ?>
    </div>
  </div>
</body>
</html>
