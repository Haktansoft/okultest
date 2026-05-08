<?php use function App\{e, csrfToken, mediaUrl}; ?>
<div class="test-runner test-runner-student">
  <main class="test-main">
    <div class="test-topbar">
      <div class="test-topbar-left">
        <div class="topbar-title"><?= e($test['title']) ?></div>
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
        <button class="btn nav-arrow" id="prev-btn" title="Önceki soru" aria-label="Önceki">
          <i class="bi bi-arrow-left"></i> <span>Önceki</span>
        </button>
        <button class="btn nav-arrow nav-arrow-primary" id="next-btn" title="Sonraki soru" aria-label="Sonraki">
          <span>Sonraki</span> <i class="bi bi-arrow-right"></i>
        </button>
        <button class="btn btn-finish" id="finish-btn" style="display:none">
          <i class="bi bi-check2-circle"></i> Testi bitir
        </button>
      </div>
      <div class="test-topbar-right">
        <span id="current-category" class="meta-pill meta-cat" style="display:none">
          <i class="bi bi-bookmark"></i> <span class="cat-text"></span>
        </span>
        <span id="current-physical" class="meta-pill meta-physical" style="display:none">
          <i class="bi bi-pencil-square"></i> Fiziksel soru
        </span>
        <span id="timer" class="timer-pill" <?= $remainingSeconds === null ? 'style="display:none"' : '' ?>>
          <i class="bi bi-clock"></i> <span class="timer-text">--:--</span>
        </span>
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
        'category_id' => (int)$q['category_id'],
        'category' => $q['category_name'] ?? 'Diğer',
        'category_description' => $q['category_description'] ?? null,
        'category_audio' => $q['category_audio'] ? [
            'kind' => 'audio',
            'url' => '/media/' . (int)$q['category_audio']['id'],
            'name' => $q['category_audio']['original_name'],
        ] : null,
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
echo json_encode([
    'assignment_id' => (int)$assignment['id'],
    'attempt_token' => !empty($assignment['started_at']) ? strtotime($assignment['started_at']) : 0,
    'questions' => $payloadQuestions,
    'serverAnswers' => $serverAnswers,
    'serverTimings' => $serverTimings,
    'remainingSeconds' => $remainingSeconds,
    'csrf' => csrfToken(),
    'autoAdvance' => true,
], JSON_UNESCAPED_UNICODE);
?>;
</script>
<script src="/assets/js/test_runner.js"></script>
