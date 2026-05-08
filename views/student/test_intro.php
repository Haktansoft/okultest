<?php use function App\{e, csrfField}; ?>
<div class="card mx-auto" style="max-width:680px">
  <div class="card-body p-4">
    <h3 class="mb-1"><?= e($test['title']) ?></h3>
    <?php if ($test['description']): ?><p class="text-muted"><?= e($test['description']) ?></p><?php endif; ?>

    <ul class="list-unstyled my-3">
      <li><i class="bi bi-list-ol me-2 text-secondary"></i><strong><?= (int)$visibleQ ?></strong> soru</li>
      <?php if ($test['time_limit_minutes']): ?>
        <li><i class="bi bi-stopwatch me-2 text-secondary"></i>Süre: <strong><?= (int)$test['time_limit_minutes'] ?> dakika</strong></li>
      <?php else: ?>
        <li><i class="bi bi-stopwatch me-2 text-secondary"></i>Süre limiti yok</li>
      <?php endif; ?>
      <?php if ($physQ > 0): ?>
        <li class="text-warning"><i class="bi bi-info-circle me-2"></i>
          Bu testte <strong><?= (int)$physQ ?> fiziksel soru</strong> var; onları öğretmeninle birlikte yapacaksın (testte sana gösterilmez).
        </li>
      <?php endif; ?>
    </ul>

    <form method="post" action="/student/tests/<?= (int)$assignment['id'] ?>/start">
      <?= csrfField() ?>
      <input type="hidden" name="mode" value="per_question">
      <button class="btn btn-primary btn-lg w-100">
        <i class="bi bi-play-fill"></i>
        <?= $assignment['status'] === 'in_progress' ? 'Devam Et' : 'Testi Başlat' ?>
      </button>
    </form>
  </div>
</div>
