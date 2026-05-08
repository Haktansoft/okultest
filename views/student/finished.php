<?php use function App\e; ?>
<div class="finish-screen">
  <div class="finish-card">
    <div class="finish-glow"></div>
    <div class="finish-icon">
      <i class="bi bi-patch-check-fill"></i>
    </div>
    <div class="finish-tag">Cevapların kaydedildi</div>
    <h2 class="finish-title"><?= e($test['title']) ?></h2>
    <p class="finish-message">
      Testi tamamlamak için <strong>öğretmenine danış</strong>.<br>
      Öğretmenin değerlendirmeyi yaptıktan sonra sonucun görünür olacak.
    </p>
    <div class="finish-actions">
      <a href="/student" class="btn btn-primary btn-finish-cta">
        <i class="bi bi-arrow-left"></i> Testlerime dön
      </a>
    </div>
  </div>
</div>
