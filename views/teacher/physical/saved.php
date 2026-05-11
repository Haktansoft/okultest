<?php use function App\e; $a = $assignment; ?>
<div class="card mx-auto" style="max-width:560px">
  <div class="card-body p-4 text-center">
    <div class="mb-3" style="font-size:3rem;color:#16a34a">
      <i class="bi bi-check-circle-fill"></i>
    </div>
    <h3 class="mb-2">Yanıtlar kaydedildi</h3>
    <p class="text-muted mb-3">
      <strong><?= e($a['student_name']) ?></strong> için <strong><?= e($a['test_title']) ?></strong> testine ait kağıt-kalem yanıtları başarıyla kaydedildi.
    </p>
    <div class="d-flex gap-2 justify-content-center flex-wrap">
      <a href="/teacher/physical" class="btn btn-primary">
        <i class="bi bi-list-check"></i> Listeye dön
      </a>
      <a href="/teacher/physical/<?= (int)$a['id'] ?>" class="btn btn-outline-secondary">
        <i class="bi bi-pencil"></i> Yeniden düzenle
      </a>
    </div>
  </div>
</div>
