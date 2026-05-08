<?php
use function App\{e, formatDuration};
$a = $assignment;
?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 11pt; }
  h1 { font-size: 15pt; margin: 0 0 2px; }
  .meta { color: #555; font-size: 10pt; }
  .summary { margin: 8px 0 14px; }
  .summary td { padding: 2px 10px 2px 0; font-size: 10pt; }
  .question { margin-bottom: 12px; page-break-inside: avoid; }
  .opt { margin-left: 14px; }
  .picked { background: #e7f1ff; padding: 1px 4px; border-radius: 3px; }
  .badge { background: #fff3cd; padding: 1px 6px; border-radius: 4px; font-size: 9pt; color: #856404; }
  hr { border: 0; border-top: 1px solid #ddd; }
</style>
<h1>Sonuç Raporu — <?= e($a['student_name']) ?></h1>
<div class="meta">Test: <?= e($a['test_title']) ?> · Bitiş: <?= e($a['finished_at'] ?: '—') ?></div>
<table class="summary">
  <tr>
    <td><strong>Toplam Skor:</strong> <?= e($a['total_score'] ?? '—') ?></td>
    <td><strong>Süre:</strong> <?= e(formatDuration($totalDuration ?? 0)) ?></td>
    <td><strong>Mod:</strong> <?= e($a['mode'] === 'bulk' ? 'Toplu' : 'Soru-soru') ?></td>
    <td><strong>Soru:</strong> <?= count($questions) ?></td>
  </tr>
</table>
<hr>

<?php foreach ($questions as $i => $q):
  $isPhys = (bool)$q['is_physical'];
  $ans = $isPhys ? $q['physical_answer'] : $q['answer'];
  $pickedId = (int)($ans['selected_option_id'] ?? 0);
?>
  <div class="question">
    <div><strong><?= $i+1 ?>.</strong> <?= e($q['prompt']) ?>
      <span class="meta">(<?= e($q['category_name']) ?>)</span>
      <?php if ($isPhys): ?><span class="badge">Fiziksel</span><?php endif; ?>
    </div>
    <?php foreach ($q['options'] as $j => $o):
      $isPicked = $pickedId === (int)$o['id'];
    ?>
      <div class="opt <?= $isPicked ? 'picked' : '' ?>">
        <strong><?= chr(65+$j) ?>)</strong> <?= e($o['label']) ?>
        <?php if ((float)$o['score'] > 0): ?> <span class="meta">(+<?= e($o['score']) ?>)</span><?php endif; ?>
        <?php if ($isPicked): ?> ←<?php endif; ?>
      </div>
    <?php endforeach; ?>
    <?php if (!$ans): ?>
      <div class="meta" style="color:#a40000">— <?= $isPhys ? 'fiziksel yanıt girilmemiş' : 'cevaplanmadı' ?></div>
    <?php elseif (!$isPhys && isset($q['answer']['time_spent_seconds'])): ?>
      <div class="meta">Süre: <?= e(formatDuration((int)$q['answer']['time_spent_seconds'])) ?> · Kazanılan: <?= e($q['answer']['option_score'] ?? '0') ?></div>
    <?php endif; ?>
  </div>
<?php endforeach; ?>
