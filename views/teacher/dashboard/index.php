<?php use function App\e;
$isAdmin = ($me['role'] ?? '') === 'admin';
?>
<div class="page-header">
  <div>
    <h1 class="page-title"><?= $isAdmin ? 'Sınıf Özeti' : 'Öğretmen Paneli' ?></h1>
    <div class="page-sub">Hoş geldin, <?= e($me['full_name']) ?>.</div>
  </div>
</div>

<div class="row g-3">
  <?php foreach ([
    ['Öğrenciler',           $stats['students'],       'bi-people',          '/teacher/students'],
    ['Testler',              $stats['assignments'],    'bi-send',            '/teacher/assignments'],
    ['Tamamlanan',           $stats['completed'],      'bi-check2-circle',   '/teacher/results'],
    ['Kağıt-Kalem Bekleyen', $stats['needs_physical'], 'bi-pencil-square',   '/teacher/physical'],
  ] as [$lbl, $cnt, $ic, $href]): ?>
    <div class="col-6 col-md-3">
      <a class="stat-tile" href="<?= e($href) ?>">
        <div class="ic"><i class="bi <?= e($ic) ?>"></i></div>
        <div>
          <div class="num"><?= e((string)$cnt) ?></div>
          <div class="lbl"><?= e($lbl) ?></div>
        </div>
      </a>
    </div>
  <?php endforeach; ?>
</div>
