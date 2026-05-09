<?php
use function App\{e, formatDuration};
$a = $assignment;
$pct = ($total_possible > 0 && $a['total_score'] !== null)
    ? round(((float)$a['total_score'] / (float)$total_possible) * 100)
    : null;

// Yüzdeye göre renk
$colorFor = function (int $p): array {
    if ($p >= 85) return ['#16a34a', '#dcfce7']; // yeşil
    if ($p >= 70) return ['#4f46e5', '#eef2ff']; // mor/mavi
    if ($p >= 50) return ['#d97706', '#fef3c7']; // turuncu
    return ['#dc2626', '#fee2e2']; // kırmızı
};
?>
<style>
  @page { margin: 18mm 18mm; }
  body { font-family: dejavusans, sans-serif; color: #1f2733; font-size: 11pt; }
  .doc-title { font-size: 18pt; font-weight: 700; margin: 0; letter-spacing: -0.01em; }
  .doc-sub   { color: #666; font-size: 11pt; margin-top: 4px; }

  /* Üst başlık — logo solda, kurum bilgisi sağda */
  .top-header { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
  .top-header td { vertical-align: middle; padding: 0; }
  .top-header td.logo-cell { width: 80px; }
  .top-header img.logo { width: 64px; height: 64px; object-fit: contain; }
  .top-header .org-name { font-size: 14pt; font-weight: 700; color: #1f2937; }
  .top-header .org-camp { color: #6b7280; font-size: 11pt; margin-top: 2px; }

  .header {
    border-bottom: 2px solid #4f46e5; padding-bottom: 10px; margin-bottom: 18px;
  }

  /* Ana skor bandı */
  .score-band {
    background: #eef2ff; border: 1px solid #c7d2fe; border-radius: 8px;
    padding: 14px 20px; margin-bottom: 16px;
    text-align: center;
  }
  .score-band .lead { font-size: 11pt; color: #4338ca; text-transform: uppercase; letter-spacing: 0.05em; }
  .score-band .big  { font-size: 32pt; font-weight: 700; color: #312e81; line-height: 1.1; margin-top: 4px; }
  .score-band .of   { font-size: 14pt; color: #6366f1; margin-left: 4px; }
  .score-band .pct  { font-size: 12pt; color: #4338ca; margin-top: 4px; }

  /* İstatistik kartları */
  .stats { width: 100%; border-collapse: separate; border-spacing: 8px 0; margin-bottom: 16px; }
  .stats td {
    width: 33.33%; vertical-align: top;
    border: 1px solid #e7eaf0; border-radius: 8px; padding: 10px 12px;
    background: #fafbfd;
  }
  .stat-lbl { font-size: 9.5pt; color: #6b7280; text-transform: uppercase; letter-spacing: 0.04em; }
  .stat-val { font-size: 16pt; font-weight: 700; color: #1f2937; line-height: 1.1; margin-top: 4px; }
  .stat-sub { font-size: 9.5pt; color: #6b7280; margin-top: 2px; }

  /* Bilgi tablosu */
  .info-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
  .info-table td { padding: 6px 10px; border-bottom: 1px solid #eef0f5; vertical-align: top; }
  .info-table td.k { color: #6b7280; width: 32%; font-size: 10.5pt; }
  .info-table td.v { color: #111827; font-size: 11pt; font-weight: 500; }

  .badge {
    display: inline-block; padding: 2px 8px; border-radius: 4px; font-size: 10pt; font-weight: 600;
  }
  .badge-completed     { background: #dcfce7; color: #166534; }
  .badge-needs_physical { background: #fef3c7; color: #92400e; }

  /* Kategori grafik bölümü */
  .cat-section { margin-top: 22px; page-break-inside: avoid; }
  .cat-title { font-size: 13pt; font-weight: 700; color: #1f2937; margin: 0 0 10px; }
  .cat-row { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  .cat-row td { vertical-align: middle; padding: 0; }
  .cat-name { font-size: 10.5pt; font-weight: 600; color: #1f2937; padding-right: 10px; width: 38%; }
  .cat-bar-cell { width: 50%; }
  .cat-bar {
    background: #f1f5f9; border: 1px solid #e2e8f0; border-radius: 6px;
    height: 18px; overflow: hidden; padding: 0;
  }
  .cat-fill { height: 18px; }
  .cat-stats { font-size: 10pt; color: #4b5563; padding-left: 10px; width: 12%; text-align: right; }
  .cat-pct { font-weight: 700; }

  .footer-note {
    margin-top: 22px; padding-top: 10px; border-top: 1px dashed #d8dde6;
    color: #6b7280; font-size: 9.5pt; text-align: center;
  }
</style>

<?php if (!empty($a['institution_name']) || !empty($logoPath)): ?>
<table class="top-header">
  <tr>
    <?php if (!empty($logoPath)): ?>
      <td class="logo-cell"><img src="<?= e($logoPath) ?>" class="logo" alt=""></td>
    <?php endif; ?>
    <td>
      <?php if (!empty($a['institution_name'])): ?>
        <div class="org-name"><?= e($a['institution_name']) ?></div>
      <?php endif; ?>
      <?php if (!empty($a['campus_name'])): ?>
        <div class="org-camp"><?= e($a['campus_name']) ?></div>
      <?php endif; ?>
    </td>
  </tr>
</table>
<?php endif; ?>

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
  <tr><td class="k">Öğrenci</td><td class="v"><?= e($a['student_name']) ?><?= !empty($a['student_tc']) ? ' <span style="color:#6b7280;">· T.C. ' . e($a['student_tc']) . '</span>' : '' ?></td></tr>
  <?php if (!empty($a['student_grade']) || !empty($a['student_section'])): ?>
    <tr><td class="k">Sınıf / Şube</td><td class="v"><?= e(trim(($a['student_grade'] ?? '') . ' ' . ($a['student_section'] ?? ''))) ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($a['institution_name'])): ?>
    <tr><td class="k">Kurum</td><td class="v"><?= e($a['institution_name']) ?></td></tr>
  <?php endif; ?>
  <?php if (!empty($a['campus_name'])): ?>
    <tr><td class="k">Kampüs</td><td class="v"><?= e($a['campus_name']) ?></td></tr>
  <?php endif; ?>
  <tr><td class="k">Test</td><td class="v"><?= e($a['test_title']) ?></td></tr>
  <tr><td class="k">Başlama</td><td class="v"><?= e($a['started_at'] ?? '—') ?></td></tr>
  <tr><td class="k">Bitiş</td><td class="v"><?= e($a['finished_at'] ?? '—') ?></td></tr>
  <tr><td class="k">Durum</td><td class="v">
    <span class="badge badge-<?= e($a['status']) ?>">
      <?= $a['status'] === 'completed' ? 'Tamamlandı' : ($a['status'] === 'needs_physical' ? 'Fiziksel sorular bekliyor' : e($a['status'])) ?>
    </span>
  </td></tr>
</table>

<?php if (!empty($categoryStats)): ?>
<div class="cat-section">
  <h2 class="cat-title">Kategori Bazlı Değerlendirme</h2>
  <?php foreach ($categoryStats as $c):
    $p = (int)$c['percent'];
    [$fg, $bg] = $colorFor($p);
    $earned = rtrim(rtrim(number_format((float)$c['earned'], 2, '.', ''), '0'), '.');
    $possible = rtrim(rtrim(number_format((float)$c['possible'], 2, '.', ''), '0'), '.');
  ?>
    <table class="cat-row">
      <tr>
        <td class="cat-name"><?= e($c['name']) ?></td>
        <td class="cat-bar-cell">
          <div class="cat-bar" style="background:<?= $bg ?>; border-color:<?= $bg ?>;">
            <div class="cat-fill" style="width:<?= $p ?>%; background:<?= $fg ?>;"></div>
          </div>
        </td>
        <td class="cat-stats">
          <span class="cat-pct" style="color:<?= $fg ?>;"><?= $p ?>%</span>
          <div style="font-size:9pt;color:#6b7280;"><?= e($earned) ?> / <?= e($possible) ?></div>
        </td>
      </tr>
    </table>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<div class="footer-note">
  Bu rapor, öğrencinin test performansının özetidir. Soru-bazlı detaylar için "Detaylı Sonuç PDF" çıktısını alabilirsiniz.
</div>
