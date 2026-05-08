<?php
use function App\{e, pdfMediaSrc};
?>
<style>
  body { font-family: dejavusans, sans-serif; font-size: 12pt; }
  h1 { font-size: 18pt; margin: 0 0 4px; }
  .meta { color: #555; font-size: 11pt; margin-bottom: 10px; }
  hr { border: 0; border-top: 1px solid #ddd; margin: 6px 0 14px; }

  .question {
    margin-bottom: 22px;
    page-break-inside: avoid;
  }
  .q-prompt { margin: 0 0 8px; line-height: 1.4; font-size: 12pt; }
  .q-prompt b { font-size: 12.5pt; }
  .badge { background: #fff3cd; padding: 1px 5px; font-size: 10pt; color: #856404; }

  /* Soru görseli — sayfanın tamamından az, ortada */
  .prompt-wrap { text-align: center; margin: 0 0 10px; }
  img.prompt-img {
    width: 150mm;
    max-width: 150mm;
    max-height: 80mm;
    height: auto;
  }

  /* Şık grid: 2-3 sütun, dengeli boşluk */
  table.opt-grid {
    width: 100%;
    border-collapse: separate;
    border-spacing: 3mm 3mm;
    margin: 4px 0 0;
  }
  table.opt-grid td {
    vertical-align: top;
    padding: 5px 6px;
    border: 0.5pt solid #c8ccd6;
    background: #fafbfd;
  }
  table.opt-grid td { font-size: 11pt; }
  table.opt-grid td .l { font-weight: 700; margin-right: 5px; }

  /* Şık görseli — hücreye sığsın */
  img.opt-img {
    margin-top: 4px;
    display: block;
  }
</style>

<h1><?= e($test['title']) ?></h1>
<div class="meta">
  <?php if (!empty($test['description'])): ?><?= e($test['description']) ?><br><?php endif; ?>
  <?php if (!empty($test['time_limit_minutes'])): ?>Süre: <?= (int)$test['time_limit_minutes'] ?> dakika<?php endif; ?>
</div>
<hr>

<?php
// İzin verilen maksimum kutu — tam genişlikten az, baskı için orta-büyük
$PROMPT_MAX_W = 150; // mm
$PROMPT_MAX_H = 90;  // mm
foreach ($questions as $i => $q):
    $pmSrc = null;
    $pmW = $pmH = null;
    if (!empty($q['prompt_media'])) {
        $pm = $q['prompt_media'];
        if ($pm) {
            $pmSrc = pdfMediaSrc($pm);
            if ($pmSrc && @getimagesize($pmSrc)) {
                [$natW, $natH] = getimagesize($pmSrc);
                if ($natW > 0 && $natH > 0) {
                    // En-boy oranını koruyarak hem genişlik hem yükseklik sınırına sığdır
                    $aspect = $natW / $natH;
                    if ($aspect * $PROMPT_MAX_H > $PROMPT_MAX_W) {
                        $pmW = $PROMPT_MAX_W;
                        $pmH = $PROMPT_MAX_W / $aspect;
                    } else {
                        $pmH = $PROMPT_MAX_H;
                        $pmW = $PROMPT_MAX_H * $aspect;
                    }
                }
            }
            if (!$pmW) { $pmW = $PROMPT_MAX_W; $pmH = $PROMPT_MAX_H; }
        }
    }

    // Şıklarda medya var mı? Hepsi yazılı mı?
    $hasOptMedia = false;
    $longestLabel = 0;
    foreach ($q['options'] as $o) {
        if (!empty($o['media_id'])) { $hasOptMedia = true; }
        $longestLabel = max($longestLabel, mb_strlen((string)$o['label'], 'UTF-8'));
    }
    // Sütun sayısı: uzun yazılı şıklar varsa 2, aksi halde 3 (görsel olsa da)
    $cols = ($longestLabel > 22 && !$hasOptMedia) ? 2 : 3;
    $rows = array_chunk($q['options'], $cols);
?>
  <div class="question">
    <p class="q-prompt"><b><?= $i+1 ?>.</b> <?= e($q['prompt']) ?>
      <?php if ($q['is_physical']): ?> <span class="badge">Fiziksel — öğretmenle</span><?php endif; ?>
    </p>
    <?php if ($pmSrc): ?>
      <p style="text-align:center;margin:0 0 10px;">
        <img src="<?= e($pmSrc) ?>" style="width:<?= number_format($pmW, 1) ?>mm;height:<?= number_format($pmH, 1) ?>mm;">
      </p>
    <?php endif; ?>

    <table class="opt-grid">
      <?php foreach ($rows as $rowIdx => $row): ?>
        <tr>
          <?php for ($c = 0; $c < $cols; $c++):
            if (!isset($row[$c])):
              echo '<td style="border:0;background:transparent;width:' . (100/$cols) . '%">&nbsp;</td>';
              continue;
            endif;
            $o = $row[$c];
            $omSrc = null;
            if (!empty($o['media'])) {
                $omSrc = pdfMediaSrc($o['media']);
            }
            $globalIdx = $rowIdx * $cols + $c;
            $letter = chr(65 + $globalIdx);
          ?>
            <td width="<?= (int)round(100/$cols) ?>%">
              <span class="l"><?= $letter ?>)</span><?= e($o['label']) ?>
              <?php if ($omSrc):
                // 3 sütunda hücre içi yaklaşık 50mm, 2 sütunda ~80mm. Tam genişlik için W koy.
                $imgW = $cols === 3 ? '52mm' : ($cols === 2 ? '80mm' : '110mm');
              ?>
                <br><img src="<?= e($omSrc) ?>" style="width:<?= $imgW ?>;max-width:100%;height:auto;margin-top:4px;">
              <?php endif; ?>
            </td>
          <?php endfor; ?>
        </tr>
      <?php endforeach; ?>
    </table>
  </div>
<?php endforeach; ?>
