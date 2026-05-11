<?php use function App\{e, csrfField, formatRichText}; ?>
<div class="card mx-auto" style="max-width:680px">
  <div class="card-body p-4">
    <h3 class="mb-1"><?= e($test['title']) ?></h3>
    <?php if ($test['description']):
      $descHtml = formatRichText($test['description']);
    ?>
      <div class="test-intro-desc text-muted"><?= $descHtml ?: '<p>' . e($test['description']) . '</p>' ?></div>
    <?php endif; ?>

    <ul class="list-unstyled my-3">
      <li><i class="bi bi-list-ol me-2 text-secondary"></i><strong><?= (int)$visibleQ ?></strong> soru</li>
      <?php if ($test['time_limit_minutes']): ?>
        <li><i class="bi bi-stopwatch me-2 text-secondary"></i>Süre: <strong><?= (int)$test['time_limit_minutes'] ?> dakika</strong></li>
      <?php else: ?>
        <li><i class="bi bi-stopwatch me-2 text-secondary"></i>Süre limiti yok</li>
      <?php endif; ?>
    </ul>

    <form method="post" action="/student/tests/<?= (int)$assignment['id'] ?>/start">
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="per_question">
      <button class="btn btn-primary btn-lg w-100">
        <i class="bi bi-play-fill"></i>
        <?= $assignment['status'] === 'in_progress' ? 'Devam Et' : 'Teste Başla' ?>
      </button>
    </form>
  </div>
</div>
