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
      <strong>Test tamamlanmıştır, teşekkür ederiz.</strong>
    </p>
    <div class="finish-actions">
      <?php $role = $me['role'] ?? 'student'; ?>
      <?php if ($role === 'student'): ?>
        <a href="/student" class="btn btn-primary btn-finish-cta">
          <i class="bi bi-arrow-left"></i> Testlerime dön
        </a>
      <?php else: ?>
        <a href="/teacher/results" class="btn btn-primary btn-finish-cta">
          <i class="bi bi-clipboard-data"></i> Sonuçlar sayfasına git
        </a>
      <?php endif; ?>
    </div>
  </div>
</div>
