<?php use function App\e; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Yönetim Özeti</h1>
    <div class="page-sub">Hoş geldin, <?= e($me['full_name']) ?>.</div>
  </div>
</div>

<div class="row g-3">
  <?php
  $cards = [
    ['Kategoriler',  $stats['categories'], 'bi-folder2',           '/admin/categories'],
    ['Sorular',      $stats['questions'],  'bi-question-circle',   '/admin/questions'],
    ['Testler',      $stats['tests'],      'bi-card-checklist',    '/admin/tests'],
    ['Medya',        $stats['media'],      'bi-collection-play',   '/admin/media'],
    ['Öğretmenler',  $stats['teachers'],   'bi-person-badge',      '/admin/teachers'],
    ['Öğrenciler',   $stats['students'],   'bi-people',            null],
  ];
  foreach ($cards as [$label, $count, $icon, $href]): ?>
    <div class="col-6 col-md-4 col-xl-2">
      <?php if ($href): ?>
        <a class="stat-tile" href="<?= e($href) ?>">
      <?php else: ?>
        <div class="stat-tile">
      <?php endif; ?>
          <div class="ic"><i class="bi <?= e($icon) ?>"></i></div>
          <div>
            <div class="num"><?= e((string)$count) ?></div>
            <div class="lbl"><?= e($label) ?></div>
          </div>
      <?php if ($href): ?></a><?php else: ?></div><?php endif; ?>
    </div>
  <?php endforeach; ?>
</div>
