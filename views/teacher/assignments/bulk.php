<?php use function App\{e, csrfToken, csrfField}; $a = $assignment; ?>
<div class="page-header">
  <div>
    <h1 class="page-title">Toplu Yanıt — <?= e($a['student_name']) ?></h1>
    <div class="page-sub">
      Test: <strong><?= e($a['test_title']) ?></strong>.
      Öğrencinin yerine yanıtları sen gir; fiziksel soruları da bu ekrandan çözebilirsin.
    </div>
  </div>
  <a href="/teacher/assignments" class="btn btn-link"><i class="bi bi-arrow-left"></i> Testlere dön</a>
</div>

<?php if (!$questions): ?>
  <div class="card"><div class="card-body"><div class="empty-state"><div class="icon"><i class="bi bi-info-circle"></i></div>Bu testte soru yok.</div></div></div>
<?php else: ?>

<div class="test-runner test-runner-teacher">
  <aside class="qnav">
    <div class="qnav-title">
      <i class="bi bi-compass"></i>
      <span>Soru Gezgini</span>
    </div>
    <div id="qnav-groups"></div>
    <div class="qnav-legend">
      <div class="legend-row"><span class="dot dot-current"></span> Aktif soru</div>
      <div class="legend-row"><span class="dot dot-marked"></span> Cevaplandı</div>
      <div class="legend-row"><span class="dot dot-physical"></span> Fiziksel soru</div>
      <div class="legend-row"><span class="dot dot-pending"></span> Bekliyor</div>
    </div>
  </aside>

  <main class="test-main">
    <div class="test-topbar">
      <div class="test-topbar-left">
        <div class="topbar-title"><?= e($a['test_title']) ?></div>
        <div class="topbar-meta">
          <span class="meta-pill meta-progress">
            <i class="bi bi-collection"></i>
            <span id="progress-counter">1 / <?= count($questions) ?></span>
          </span>
          <span class="meta-pill meta-remaining">
            <i class="bi bi-hourglass-split"></i>
            <span id="remaining-counter">Kalan <?= count($questions) ?></span>
          </span>
        </div>
      </div>
      <div class="test-topbar-center">
        <button class="btn nav-arrow" id="prev-btn" title="Önceki" aria-label="Önceki">
          <i class="bi bi-arrow-left"></i> <span>Önceki</span>
        </button>
        <button class="btn nav-arrow nav-arrow-primary" id="next-btn" title="Sonraki" aria-label="Sonraki">
          <span>Sonraki</span> <i class="bi bi-arrow-right"></i>
        </button>
        <button class="btn btn-finish" id="finish-btn" style="display:none">
          <i class="bi bi-check2-circle"></i> Yanıtları kaydet
        </button>
      </div>
      <div class="test-topbar-right">
        <span id="current-category" class="meta-pill meta-cat" style="display:none">
          <i class="bi bi-bookmark"></i> <span class="cat-text"></span>
        </span>
        <span id="current-physical" class="meta-pill meta-physical" style="display:none">
          <i class="bi bi-pencil-square"></i> Fiziksel soru
        </span>
        <span id="timer" class="timer-pill" style="display:none"><span class="timer-text">--:--</span></span>
        <span id="save-status" class="save-status"></span>
      </div>
      <div class="topbar-progress" aria-hidden="true"><div class="topbar-progress-fill"></div></div>
    </div>

    <div id="question-container"></div>
  </main>
</div>

<template id="opt-template">
  <label class="option">
    <input type="radio" class="form-check-input" name="opt">
    <div class="opt-letter"></div>
    <div class="opt-body">
      <div class="opt-media-slot"></div>
      <span class="opt-label"></span>
    </div>
    <i class="opt-check bi bi-check-circle-fill"></i>
  </label>
</template>

<script>
window.TEST_DATA = <?php
$payloadQuestions = [];
foreach ($questions as $q) {
    $payloadQuestions[] = [
        'id' => (int)$q['id'],
        'category' => $q['category_name'] ?? 'Diğer',
        'is_physical' => (int)$q['is_physical'] === 1,
        'prompt' => $q['prompt'],
        'prompt_media' => $q['prompt_media'] ? [
            'kind' => $q['prompt_media']['kind'],
            'url' => '/media/' . (int)$q['prompt_media']['id'],
            'name' => $q['prompt_media']['original_name'],
        ] : null,
        'options' => array_map(fn($o) => [
            'id' => (int)$o['id'],
            'label' => $o['label'],
            'media' => $o['media'] ? [
                'kind' => $o['media']['kind'],
                'url' => '/media/' . (int)$o['media']['id'],
                'name' => $o['media']['original_name'],
            ] : null,
        ], $q['options']),
    ];
}
$serverAnswers = [];
foreach ($questions as $q) {
    if (!empty($q['existing_option_id'])) {
        $serverAnswers[(int)$q['id']] = (int)$q['existing_option_id'];
    } elseif (!empty($q['is_blank'])) {
        // Öğrenci bilinçli boş bıraktı → "Boş bırakıldı" durumunda göster
        $serverAnswers[(int)$q['id']] = '__blank__';
    }
}
echo json_encode([
    'mode' => 'teacher_bulk',
    'assignment_id' => (int)$a['id'],
    'attempt_token' => !empty($a['started_at']) ? strtotime($a['started_at']) : 0,
    'questions' => $payloadQuestions,
    'serverAnswers' => $serverAnswers,
    'serverTimings' => [],
    'remainingSeconds' => null,
    'csrf' => csrfToken(),
    'autoAdvance' => true,
    'endpoints' => [
        'submit' => '/teacher/assignments/' . (int)$a['id'] . '/bulk',
    ],
], JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="/assets/js/test_runner.js"></script>

<?php endif; ?>
