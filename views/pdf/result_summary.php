<?php
use function App\{e, formatDuration};
$a = $assignment;
$pct = ($total_possible > 0 && $a['total_score'] !== null)
    ? round(((float)$a['total_score'] / (float)$total_possible) * 100)
    : null;
?>
<style>
  @page { margin: 18mm 18mm; }
  body { font-family: dejavusans, sans-serif; color: #1f2733; font-size: 11pt; }
  .doc-title { font-size: 18pt; font-weight: 700; margin: 0; letter-spacing: -0.01em; }
  .doc-sub   { color: #666; font-size: 11pt; margin-top: 4px; }
  .header {
    border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 18px;
  }

  /* Ana skor bandı */
  .score-band {
    background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px;
    padding: 16px 20px; margin-bottom: 18px;
    text-align: center;
  }
  .score-band .lead { font-size: 11pt; color: #4338ca; text-transform: uppercase; letter-spacing: 0.05em; }
  .score-band .big  { font-size: 38pt; font-weight: 700; color: #312e81; line-height: 1.1; margin-top: 4px; }
  .score-band .of   { font-size: 14pt; color: #6366f1; margin-left: 4px; }
  .score-band .pct  { font-size: 12pt; color: #4338ca; margin-top: 4px; }

  /* İstatistik kartları */
  .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 18px; }
  .stats td {
    width: 33.33%; vertical-align: top;
    border: 1px solid #e7eaf0; border-radius: 8px; padding: 12px 14px;
    background: #fafbfd;
  }
  .stat-lbl { font-size: 9.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
  .stat-val { font-size: 18pt; font-weight: 700; color: #1f2937; line-height: 1.1; margin-top: 4px; }
  .stat-sub { font-size: 9.5pt; color: #6b7280; margin-top: 2px; }

  /* Bilgi tablosu */
  .info-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
  .info-table td { padding: 8px 10px; border-bottom: 1px solid #eef0f5; vertical-align: top; }
  .info-table td.k { color: #6b7280; width: 35%; font-size: 10.5pt; }
  .info-table td.v { color: #111827; font-size: 11pt; font-weight: 500; }

  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10pt; font-weight: 600;
  }
  .badge-completed     { background: #dcfce7; color: #166534; }
  .badge-needs_physical { background: #fef3c7; color: #92400e; }

  .footer-note {
    margin-top: 26px; padding-top: 10px; border-top: 1px dashed #d8dde6;
    color: #6b7280; font-size: 9.5pt; text-align: center;
  }
</style>

<div class="header">
  <h1 class="doc-title">Test Sonuç Raporu</h1>
  <div class="doc-sub"><?= e($a['student_name']) ?> · <?= e($a['test_title']) ?></div>
</div>

<div class="score-band">
  <div class="lead">Toplam Puan</div>
  <div class="big">
    <?= $a['total_score'] !== null ? e(rtrim(rtrim(number_format((float)$a['total_score'], 2, '.', ''), '0'), '.')) : '—' ?>
    <?php if ($total_possible > 0): ?>
      <span class="of">/ <?= e(rtrim(rtrim(number_format((float)$total_possible, 2, '.', ''), '0'), '.')) ?></span>
    <?php endif; ?>
  </div>
  <?php if ($pct !== null): ?><div class="pct">Başarı: <?= e((string)$pct) ?>%</div><?php endif; ?>
</div>

<table class="stats">
  <tr>
    <td>
      <div class="stat-lbl">Cevaplanan</div>
      <div class="stat-val"><?= (int)$answered_questions ?> / <?= (int)$total_questions ?></div>
      <div class="stat-sub">soru</div>
    </td>
    <td>
      <div class="stat-lbl">Toplam Süre</div>
      <div class="stat-val"><?= e(formatDuration((int)$totalDuration)) ?></div>
      <div class="stat-sub">başlangıçtan bitime</div>
    </td>
    <td>
      <div class="stat-lbl">Yanıt Girişi</div>
      <div class="stat-val">
        <?= e($a['mode'] === 'bulk' ? 'Öğretmen' : ($a['mode'] === 'per_question' ? 'Öğrenci' : '—')) ?>
      </div>
      <div class="stat-sub">
        <?= $a['mode'] === 'bulk' ? 'toplu girildi' : ($a['mode'] === 'per_question' ? 'kendi çözdü' : '') ?>
      </div>
    </td>
  </tr>
</table>

<table class="info-table">
  <tr><td class="k">Öğrenci</td><td class="v"><?= e($a['student_name']) ?></td></tr>
  <tr><td class="k">Test</td><td class="v"><?= e($a['test_title']) ?></td></tr>
  <tr><td class="k">Başlama</td><td class="v"><?= e($a['started_at'] ?? '—') ?></td></tr>
  <tr><td class="k">Bitiş</td><td class="v"><?= e($a['finished_at'] ?? '—') ?></td></tr>
  <tr><td class="k">Durum</td><td class="v">
    <span class="badge badge-<?= e($a['status']) ?>">
      <?= $a['status'] === 'completed' ? 'Tamamlandı' : ($a['status'] === 'needs_physical' ? 'Fiziksel sorular bekliyor' : e($a['status'])) ?>
    </span>
  </td></tr>
</table>

<div class="footer-note">
  Bu rapor, öğrencinin test performansının özetidir. Soru-bazlı detaylar için "Detaylı Sonuç PDF" çıktısını alabilirsiniz.
</div>
