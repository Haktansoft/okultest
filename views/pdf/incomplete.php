<?php use function App\e; $a = $assignment; ?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 11pt; }
  h1 { font-size: 15pt; margin: 0 0 2px; }
  .meta { color: #555; font-size: 10pt; }
  .question { margin-bottom: 14px; page-break-inside: avoid; }
  .opt { margin-left: 14px; }
  .check { display: inline-block; width: 14px; height: 14px; border: 1px solid #555; vertical-align: middle; margin-right: 6px; }
  .header { margin-bottom: 14px; border-bottom: 1px solid #ddd; padding-bottom: 6px; }
  .badge { background: #fff3cd; padding: 1px 6px; border-radius: 4px; font-size: 9pt; color: #856404; }
</style>
<div class="header">
  <h1>Eksik / Fiziksel Sorular</h1>
  <div class="meta">
    Öğrenci: <strong><?= e($a['student_name']) ?></strong> ·
    Test: <strong><?= e($a['test_title']) ?></strong>
  </div>
</div>

<?php if (!$questions): ?>
  <p>Eksik veya fiziksel soru bulunmuyor.</p>
<?php else: foreach ($questions as $i => $q): ?>
  <div class="question">
    <div><strong><?= $i+1 ?>.</strong> <?= e($q['prompt']) ?>
      <?php if (!empty($q['is_physical'])): ?><span class="badge">Fiziksel</span><?php endif; ?>
    </div>
    <?php foreach ($q['options'] as $j => $o): ?>
      <div class="opt"><span class="check"></span><strong><?= chr(65+$j) ?>)</strong> <?= e($o['label']) ?>
        <?php if ((float)$o['score'] > 0): ?> <span class="meta">(+<?= e($o['score']) ?>)</span><?php endif; ?>
      </div>
    <?php endforeach; ?>
  </div>
<?php endforeach; endif; ?>
