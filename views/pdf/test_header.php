<?php use function App\e; ?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 10.5pt; }
  h1 { font-size: 16pt; margin: 0 0 4px; }
  .meta { color: #555; font-size: 10pt; margin-bottom: 8px; }
  hr { border: 0; border-top: 1px solid #ddd; margin: 6px 0 10px; }
</style>
<h1><?= e($test['title']) ?></h1>
<div class="meta">
  <?php if (!empty($test['description'])): ?><?= e($test['description']) ?><br><?php endif; ?>
  <?php if (!empty($test['time_limit_minutes'])): ?>Süre: <?= (int)$test['time_limit_minutes'] ?> dakika<?php endif; ?>
</div>
<hr>
