<?php
use function App\{e, pdfMediaSrc};
?>
<style>
  .question { margin: 0 0 12px; page-break-inside: avoid; }
  .q-prompt { margin: 0 0 5px; line-height: 1.35; font-size: 10.5pt; }
  .q-prompt b { font-size: 11pt; }
  .badge { background: #fff3cd; padding: 1px 5px; font-size: 9pt; color: #856404; }
  /* Sütun genişliğini kapla */
  img.prompt-img { width: 100%; height: auto; margin: 0 0 6px; display: block; }
  .opt {
    border: 0.5pt solid #d0d4db;
    padding: 5px 7px;
    margin-bottom: 4px;
    page-break-inside: avoid;
  }
  .opt .l { font-weight: 700; margin-right: 4px; }
  img.opt-img { width: 70%; height: auto; margin-top: 4px; display: block; }
</style>
<?php foreach ($questions as $i => $q):
    $pmSrc = null;
    if (!empty($q['prompt_media'])) {
        $pmSrc = pdfMediaSrc($q['prompt_media']);
    }
?>
  <div class="question">
    <p class="q-prompt"><b><?= $i+1 ?>.</b> <?= e($q['prompt']) ?>
      <?php if ($q['is_physical']): ?> <span class="badge">Fiziksel — öğretmenle</span><?php endif; ?>
    </p>
    <?php if ($pmSrc): ?>
      <img class="prompt-img" src="<?= e($pmSrc) ?>" width="100%">
    <?php endif; ?>
    <?php foreach ($q['options'] as $j => $o):
      $omSrc = !empty($o['media']) ? pdfMediaSrc($o['media']) : null;
      $letter = chr(65 + $j);
    ?>
      <div class="opt">
        <span class="l"><?= $letter ?>)</span><?= e($o['label']) ?>
        <?php if ($omSrc): ?><br><img class="opt-img" src="<?= e($omSrc) ?>"><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; ?>
