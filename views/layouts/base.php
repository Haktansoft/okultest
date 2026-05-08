<?php
use function App\{e, csrfField, user, flash};
$me = $me ?? user();
$role = $me['role'] ?? null;

$navGroups = [];
if ($role === 'admin') {
    $navGroups = [
        'Genel' => [
            ['/admin',                 'Özet',         'bi-speedometer2'],
        ],
        'Yapı' => [
            ['/admin/institutions',    'Kurumlar',     'bi-building'],
            ['/admin/campuses',        'Kampüsler',    'bi-geo-alt'],
            ['/teacher/classrooms',    'Sınıflar',     'bi-mortarboard'],
        ],
        'İçerik' => [
            ['/admin/categories',      'Kategoriler',  'bi-folder2'],
            ['/admin/media',           'Medya',        'bi-collection-play'],
            ['/admin/questions',       'Sorular',      'bi-question-circle'],
            ['/admin/tests',           'Testler',      'bi-card-checklist'],
        ],
        'Sınıf' => [
            ['/teacher/students',      'Öğrenciler',     'bi-people'],
            ['/teacher/assignments',   'Atamalar',       'bi-send'],
        ],
        'Sonuçlar' => [
            ['/teacher/results',       'Sonuçlar',       'bi-clipboard-data'],
            ['/teacher/physical',      'Fiziksel Sorular','bi-pencil-square'],
        ],
        'Kullanıcılar' => [
            ['/admin/teachers',        'Öğretmenler',    'bi-person-badge'],
        ],
    ];
} elseif ($role === 'teacher') {
    $navGroups = [
        'Genel' => [
            ['/teacher',               'Özet',           'bi-speedometer2'],
        ],
        'Sınıf' => [
            ['/teacher/classrooms',    'Sınıflar',       'bi-mortarboard'],
            ['/teacher/students',      'Öğrenciler',     'bi-people'],
            ['/teacher/assignments',   'Atamalar',       'bi-send'],
        ],
        'İçerik' => [
            ['/admin/tests',           'Testler',        'bi-card-checklist'],
        ],
        'Sonuçlar' => [
            ['/teacher/results',       'Sonuçlar',       'bi-clipboard-data'],
            ['/teacher/physical',      'Fiziksel Sorular','bi-pencil-square'],
        ],
    ];
} elseif ($role === 'student') {
    $navGroups = [
        '' => [
            ['/student',             'Testlerim',    'bi-card-checklist'],
        ],
    ];
}

$flashOk  = flash('ok');
$flashErr = flash('err');
$currentUri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);

function _isActive(string $href, string $current): bool {
    if ($href === $current) return true;
    if ($href !== '/' && str_starts_with($current, $href . '/')) return true;
    return false;
}
?>
<!doctype html>
<html lang="tr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? 'Test Eğitim') ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="/assets/css/app.css">
</head>
<body>
<div class="app-shell">
  <!-- Mobil topbar (sadece <992px görünür) -->
  <header class="mobile-topbar">
    <button class="mobile-burger" type="button" aria-label="Menüyü aç" id="drawer-open">
      <i class="bi bi-list"></i>
    </button>
    <div class="mobile-brand">
      <span class="logo">T</span>
      <span>Test Eğitim</span>
    </div>
    <?php if ($me): ?>
      <div class="mobile-user dropdown">
        <button class="mobile-user-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="Kullanıcı menüsü">
          <i class="bi bi-person-circle"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end">
          <div class="dropdown-header">
            <div class="fw-semibold"><?= e($me['full_name']) ?></div>
            <div class="muted tiny text-capitalize"><?= e($me['role']) ?></div>
          </div>
          <div class="dropdown-divider"></div>
          <form method="post" action="/logout" class="m-0 px-2">
            <?= csrfField() ?>
            <button class="btn btn-sm btn-outline-danger w-100"><i class="bi bi-box-arrow-right"></i> Çıkış</button>
          </form>
        </div>
      </div>
    <?php endif; ?>
  </header>

  <!-- Sidebar (drawer) -->
  <aside class="app-sidebar" id="app-sidebar">
    <div class="sidebar-brand">
      <span class="logo">T</span>
      <span>Test Eğitim</span>
      <button class="sidebar-close d-lg-none" type="button" aria-label="Menüyü kapat" id="drawer-close">
        <i class="bi bi-x-lg"></i>
      </button>
    </div>

    <?php foreach ($navGroups as $section => $links): ?>
      <?php if ($section): ?><div class="sidebar-section"><?= e($section) ?></div><?php endif; ?>
      <nav class="sidebar-nav">
        <?php foreach ($links as [$href, $label, $icon]): ?>
          <a href="<?= e($href) ?>" class="<?= _isActive($href, (string)$currentUri) ? 'active' : '' ?>">
            <i class="bi <?= e($icon) ?>"></i>
            <span><?= e($label) ?></span>
          </a>
        <?php endforeach; ?>
      </nav>
    <?php endforeach; ?>

    <div class="sidebar-foot">
      <?php if ($me): ?>
        <div class="who">
          <div class="nm"><?= e($me['full_name']) ?></div>
          <div class="rl"><?= e($me['role']) ?></div>
        </div>
        <form method="post" action="/logout" class="m-0">
          <?= csrfField() ?>
          <button title="Çıkış"><i class="bi bi-box-arrow-right"></i></button>
        </form>
      <?php endif; ?>
    </div>
  </aside>

  <!-- Drawer backdrop (sadece mobile) -->
  <div class="drawer-backdrop" id="drawer-backdrop"></div>

  <main class="app-main">
    <?php if ($flashOk): ?><div class="alert alert-success"><?= e($flashOk) ?></div><?php endif; ?>
    <?php if ($flashErr): ?><div class="alert alert-danger"><?= e($flashErr) ?></div><?php endif; ?>
    <?= $bodyContent ?>
  </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(() => {
  const sidebar = document.getElementById('app-sidebar');
  const backdrop = document.getElementById('drawer-backdrop');
  const openBtn = document.getElementById('drawer-open');
  const closeBtn = document.getElementById('drawer-close');
  if (!sidebar) return;
  const open  = () => { document.body.classList.add('drawer-open'); };
  const close = () => { document.body.classList.remove('drawer-open'); };
  openBtn?.addEventListener('click', open);
  closeBtn?.addEventListener('click', close);
  backdrop?.addEventListener('click', close);
  // Sidebar içinden bir bağlantıya tıklayınca otomatik kapansın
  sidebar.querySelectorAll('a[href]').forEach(a => a.addEventListener('click', close));
  // ESC ile kapatma
  document.addEventListener('keydown', e => { if (e.key === 'Escape') close(); });
  // Resize'da geniş ekrana geçince kapansın
  window.addEventListener('resize', () => { if (window.innerWidth >= 992) close(); });
})();
</script>
</body>
</html>
