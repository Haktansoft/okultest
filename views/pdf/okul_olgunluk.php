<?php
use function App\e;
$a = $assignment;
$rows = $olgunlukRows;        // 7 alan
$combo = $olgunlukCombo;      // 4 birleşik
$totalQ = $olgunlukTotalQ;
$totalC = $olgunlukTotalC;
$totalP = $olgunlukTotalP;
$lvl = $olgunlukLevel;        // genel toplam sınıfı

// Uygulama tarihi (finished_at varsa onu, yoksa started_at)
$uygTs = $a['finished_at'] ?? $a['started_at'] ?? null;
$uygDate = $uygTs ? date('d.m.Y', strtotime($uygTs)) : '—';

// Renk paleti — Benego şablonunu yansıtan ton
$accent  = '#173261';      // koyu lacivert (başlık)
$accent2 = '#f59e0b';      // turuncu vurgu
$accent3 = '#5fb7c0';      // soft turkuaz
$soft    = '#fff7e6';      // krem arkaplan
$border  = '#e7e2d3';
$light   = '#fff8ee';
?>
<style>
  @page { margin: 14mm 14mm 14mm 14mm; }
  body { font-family: dejavusans, sans-serif; color: #1f2733; font-size: 10.5pt; }
  h1, h2, h3, h4 { font-family: dejavusansb, sans-serif; color: <?= $accent ?>; margin: 0; }
  .display { font-family: dejavusansb, sans-serif; color: <?= $accent ?>; letter-spacing: -0.02em; }
  .muted { color: #6b7280; }

  /* ============ KAPAK ============ */
  .cover { text-align: center; padding-top: 18mm; }
  .cover .brand { font-size: 11pt; color: <?= $accent ?>; font-weight: 700; letter-spacing: 0.25em; margin-bottom: 4mm; }
  .cover .badge {
    display: inline-block; padding: 4px 14px; border-radius: 18px;
    background: <?= $accent3 ?>; color: #fff; font-size: 9.5pt; font-weight: 700; letter-spacing: 0.18em;
  }
  .cover h1 { font-size: 34pt; line-height: 1.05; margin-top: 10mm; }
  .cover h1 .c1 { color: <?= $accent ?>; }
  .cover h1 .c2 { color: <?= $accent2 ?>; }
  .cover h1 .c3 { color: <?= $accent3 ?>; }
  .cover .tag { font-size: 12pt; color: <?= $accent ?>; margin-top: 6mm; }
  .cover .logo-wrap { margin-top: 14mm; }
  .cover .logo-wrap img { max-width: 38mm; max-height: 38mm; }
  .cover .fields { width: 80%; margin: 18mm auto 0; }
  .cover .fields td { padding: 6px 0; font-size: 11pt; vertical-align: middle; }
  .cover .fields td.k { width: 35%; color: <?= $accent ?>; font-weight: 700; }
  .cover .fields td.v { border-bottom: 1px solid <?= $accent ?>; color: #1f2937; font-weight: 600; }

  /* ============ SAYFA 2 ============ */
  .section-title { font-size: 22pt; text-align: center; margin: 0 0 6mm; font-family: dejavusansb, sans-serif; color: <?= $accent ?>; }
  .lead { font-size: 10.5pt; line-height: 1.5; }
  .info-box { background: <?= $soft ?>; border: 1px solid <?= $border ?>; border-radius: 8px; padding: 10px 14px; margin: 6mm 0; }
  .info-box ul { margin: 4px 0 0 14px; padding: 0; }
  .info-box li { margin: 3px 0; }

  /* ============ TABLOLAR ============ */
  .data-table { width: 100%; border-collapse: collapse; margin: 4mm 0 6mm; }
  .data-table th, .data-table td { border: 1px solid <?= $border ?>; padding: 7px 8px; font-size: 10pt; vertical-align: middle; }
  .data-table th { background: <?= $accent ?>; color: #fff; font-family: dejavusansb, sans-serif; text-align: center; }
  .data-table td.label { background: <?= $light ?>; font-weight: 700; color: <?= $accent ?>; }
  .data-table td.num { text-align: center; }
  .data-table tr.total td { background: <?= $accent2 ?>; color: #fff; font-family: dejavusansb, sans-serif; }
  .data-table tr.total td.label { background: <?= $accent2 ?>; color: #fff; }
  .pill {
    display: inline-block; padding: 2px 8px; border-radius: 10px; font-size: 9pt; font-weight: 700;
  }
  .pill-ileri  { background: #dcfce7; color: #166534; }
  .pill-yeter  { background: #dbeafe; color: #1e40af; }
  .pill-sinir  { background: #fef3c7; color: #92400e; }
  .pill-dusuk  { background: #fee2e2; color: #991b1b; }

  /* ============ ALAN KARTLARI ============ */
  .area-card { margin: 0 0 10mm; padding: 0; page-break-inside: avoid; }
  .area-title { font-size: 16pt; text-align: center; font-family: dejavusansb, sans-serif; color: <?= $accent ?>; margin: 0 0 3mm; }
  .area-desc { font-size: 10pt; line-height: 1.5; color: #374151; padding: 0 6mm; text-align: center; }
  .area-meta { text-align: center; margin: 3mm 0 4mm; }
  .area-meta .chip {
    display: inline-block; padding: 3px 10px; border-radius: 12px; font-size: 9.5pt;
    background: <?= $light ?>; border: 1px solid <?= $border ?>; color: <?= $accent ?>; font-weight: 700;
    margin: 0 2px;
  }
  .yorum-label {
    text-align: center; font-size: 12pt; font-family: dejavusansb, sans-serif; color: <?= $accent ?>;
    border-bottom: 1.5pt solid <?= $accent ?>; display: inline-block; padding-bottom: 2px; margin: 1mm 0 2mm;
  }
  .yorum-text { font-size: 10pt; line-height: 1.55; color: #1f2937; text-align: center; padding: 0 8mm; }
  .area-sep { border-top: 1px solid <?= $border ?>; margin: 7mm 30mm; }

  /* ============ PAGE NUMBER ============ */
  .pageno {
    text-align: center; margin-top: 4mm;
    color: <?= $accent ?>; font-family: dejavusansb, sans-serif;
  }
  .pageno .dot {
    display: inline-block; width: 22px; height: 22px; line-height: 22px;
    background: <?= $accent ?>; color: #fff; border-radius: 50%;
    font-size: 9pt;
  }

  .footer-strip {
    margin-top: 8mm; padding-top: 4mm; border-top: 1px dashed <?= $border ?>;
    text-align: center; color: #6b7280; font-size: 9pt;
  }
</style>

<?php
// Sınıfa göre pill class
$pillClass = function (string $sinif): string {
    $s = mb_strtolower($sinif, 'UTF-8');
    if (strpos($s, 'ileri') !== false)  return 'pill pill-ileri';
    if (strpos($s, 'yeterli') !== false) return 'pill pill-yeter';
    if (strpos($s, 'sınırda') !== false || strpos($s, 'sinirda') !== false) return 'pill pill-sinir';
    return 'pill pill-dusuk';
};
?>

<!-- ==================== KAPAK ==================== -->
<div class="cover">
  <?php if (!empty($logoPath)): ?>
    <div class="logo-wrap"><img src="<?= e($logoPath) ?>" alt=""></div>
  <?php endif; ?>
  <div class="badge">YENİ BİR BAŞLANGIÇ</div>
  <h1>
    <span class="c1">OKUL</span><br>
    <span class="c2">OLGUNLUK</span><br>
    <span class="c3">RAPORU</span>
  </h1>
  <div class="tag">İlkokula Başlarken<br>Kendini Tanıyorum, Hazırım!</div>

  <table class="fields" cellpadding="0" cellspacing="0">
    <tr><td class="k">Adı Soyadı</td><td class="v"><?= e($a['student_name'] ?? '') ?></td></tr>
    <tr><td style="height:6px;"></td><td></td></tr>
    <tr><td class="k">Uygulama Tarihi</td><td class="v"><?= e($uygDate) ?></td></tr>
  </table>
</div>

<pagebreak />

<!-- ==================== SAYFA 2 — RAPOR HAKKINDA ==================== -->
<h2 class="section-title">Rapor Hakkında</h2>
<div class="lead">
  <p><strong>Değerli Velimiz,</strong></p>
  <p>Çocuğunuzun okul öncesinden ilkokul kademesine geçiş sürecini desteklemek ve gelişimsel hazır bulunuşluk düzeyini belirlemek amacıyla uyguladığımız <strong>Okul Olgunluk Testi</strong> sonuç raporu ekte bilginize sunulmuştur. Bu rapor, çocuğunuzun akademik ve sosyal hayata uyum sağlama potansiyelini <strong>7 farklı gelişim alanı</strong> üzerinden analiz eden bilimsel bir veri setidir.</p>
</div>

<h3 style="margin-top:6mm; color:<?= $accent ?>;">Raporun İçeriği ve Ölçülen Alanlar</h3>
<div class="info-box">
  <ul>
    <li><strong>Dil Becerileri (Kelime ve Cümle Anlama):</strong> Alıcı dil düzeyi ve işitsel dikkat.</li>
    <li><strong>Bilişsel Hazırlık (Günlük Yaşam ve Matematik):</strong> Genel kültür, nesne işlevleri, temel sayısal mantık.</li>
    <li><strong>Görsel ve Motor Gelişim (Görsel Algı ve İnce Motor):</strong> Şekilleri ayırt etme ve el-göz koordinasyonu.</li>
    <li><strong>Uyum Becerisi (Yönerge Takibi):</strong> Sözel komutları anlama ve sırasıyla uygulama yetisi.</li>
  </ul>
</div>

<h3 style="margin-top:4mm; color:<?= $accent ?>;">Raporu Nasıl Değerlendirmelisiniz?</h3>
<div class="info-box">
  <ul>
    <li><strong>Olgunluk Sınıflandırması:</strong> Çocuğunuzun biyolojik yaşı ile gelişimsel becerilerinin örtüşmesini gösterir.</li>
    <li><strong>Güçlü Alanlar (%85+):</strong> Okul hayatında en az zorlanacağı, özgüvenle ilerleyeceği yönlerdir.</li>
    <li><strong>Desteklenmesi Gereken Alanlar (%70 altı):</strong> "Başarısızlık" değil, ilk aylarda odaklanılacak <em>gelişim fırsatları</em>dır.</li>
    <li><strong>İnce Motor Vurgusu:</strong> Bilişsel puan yüksek ama ince motor düşükse — zihinsel olarak hazırdır, yazı yazmada çabuk yorulabilir.</li>
  </ul>
</div>

<h3 style="margin-top:4mm; color:<?= $accent ?>;">Sonraki Adımlar</h3>
<div class="lead">
  Bu rapor bir <em>zeka testi</em> değil, bir <strong>hazır bulunuşluk</strong> ölçümüdür. Sonuçlar doğrultusunda öğretmeniyle iletişim içinde olmanızı öneririz.
</div>

<div class="pageno"><span class="dot">01</span></div>

<pagebreak />

<!-- ==================== SAYFA 3 — TABLOLAR ==================== -->
<h2 class="section-title">Sayısal Değerlendirme Tablosu</h2>
<div class="lead" style="text-align:center; margin-bottom:3mm;">
  Aşağıdaki tablo, testteki soru sayılarına ve puan ağırlıklarına göre öğrencinin performansını gösterir.
</div>

<table class="data-table">
  <thead>
    <tr>
      <th style="text-align:left;">Test Bölümü</th>
      <th>Soru Sayısı</th>
      <th>Doğru Sayısı</th>
      <th>Başarı (%)</th>
      <th>Olgunluk Düzeyi</th>
    </tr>
  </thead>
  <tbody>
    <?php $letters = ['A','B','C','D','E','F','G']; foreach ($rows as $i => $r): ?>
    <tr>
      <td class="label"><?= $letters[$i] ?>- <?= e($r['name']) ?></td>
      <td class="num"><?= (int)$r['qcount'] ?></td>
      <td class="num"><?= (int)$r['correct'] ?></td>
      <td class="num"><?= (int)$r['percent'] ?>%</td>
      <td class="num"><span class="<?= $pillClass($r['level']) ?>"><?= e($r['level']) ?></span></td>
    </tr>
    <?php endforeach; ?>
    <tr class="total">
      <td class="label">GENEL TOPLAM</td>
      <td class="num"><?= (int)$totalQ ?></td>
      <td class="num"><?= (int)$totalC ?></td>
      <td class="num"><?= (int)$totalP ?>%</td>
      <td class="num"><?= e($lvl['sinif'] ?? '—') ?></td>
    </tr>
  </tbody>
</table>

<h2 class="section-title" style="font-size:18pt; margin-top:4mm;">Alan Bazlı Detaylı Analiz</h2>
<div class="lead" style="font-size:9.5pt; margin-bottom:2mm;">
  Test, çocuğunuzun ihtiyaç duyacağı temel becerileri kapsar.
</div>
<table class="data-table">
  <thead>
    <tr>
      <th style="text-align:left;">Temel Beceriler</th>
      <th>Soru Sayısı</th>
      <th>Doğru Sayısı</th>
      <th>Başarı (%)</th>
      <th>Değerlendirme</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($combo as $c): ?>
    <tr>
      <td class="label"><?= e($c['label']) ?></td>
      <td class="num"><?= (int)$c['q'] ?></td>
      <td class="num"><?= (int)$c['c'] ?></td>
      <td class="num"><?= (int)$c['pct'] ?>%</td>
      <td class="num"><span class="<?= $pillClass($c['level']) ?>"><?= e($c['level']) ?></span></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2 class="section-title" style="font-size:18pt; margin-top:4mm;">Olgunluk Düzeyi Tablosu</h2>
<div class="lead" style="font-size:9.5pt; margin-bottom:2mm;">
  Bu tablo, öğrencinin testten aldığı <strong><?= (int)$totalP ?>%</strong> genel başarı yüzdesini gelişimsel yaş beklentileriyle eşleştirir.
</div>
<table class="data-table">
  <thead>
    <tr>
      <th>Başarı Yüzdesi</th>
      <th>Olgunluk Sınıflandırması</th>
      <th>Gelişimsel Karşılık</th>
      <th style="text-align:left;">Tavsiyemiz</th>
    </tr>
  </thead>
  <tbody>
    <?php if ($lvl): ?>
    <tr class="total">
      <td class="num label"><?= e($lvl['label']) ?></td>
      <td class="num label"><?= e($lvl['sinif']) ?></td>
      <td class="num label"><?= e($lvl['karsilik']) ?></td>
      <td class="label" style="text-align:left;"><?= e($lvl['tavsiye']) ?></td>
    </tr>
    <?php else: ?>
    <tr><td colspan="4" class="num">—</td></tr>
    <?php endif; ?>
  </tbody>
</table>

<div class="pageno"><span class="dot">02</span></div>

<pagebreak />

<!-- ==================== SAYFA 4-5 — ALAN DETAYLARI ==================== -->
<?php foreach ($rows as $i => $r):
  if ($i > 0 && $i % 4 === 0): ?>
    <div class="pageno"><span class="dot"><?= sprintf('%02d', 2 + intdiv($i, 4)) ?></span></div>
    <pagebreak />
  <?php endif; ?>
  <div class="area-card">
    <h3 class="area-title"><?= e($r['name']) ?></h3>
    <?php if (!empty($r['description'])): ?>
      <div class="area-desc"><?= e($r['description']) ?></div>
    <?php endif; ?>
    <div class="area-meta">
      <span class="chip"><?= (int)$r['correct'] ?> / <?= (int)$r['qcount'] ?> doğru</span>
      <span class="chip">Başarı: <?= (int)$r['percent'] ?>%</span>
      <span class="chip"><?= e($r['level']) ?></span>
    </div>
    <div style="text-align:center;"><span class="yorum-label">Yorumlar</span></div>
    <?php if (!empty($r['comment'])): ?>
      <div class="yorum-text"><?= e($r['comment']) ?></div>
    <?php endif; ?>
  </div>
  <?php if ($i < count($rows) - 1 && ($i + 1) % 4 !== 0): ?>
    <div class="area-sep"></div>
  <?php endif; ?>
<?php endforeach; ?>

<div class="pageno"><span class="dot"><?= sprintf('%02d', 2 + intdiv(count($rows) - 1, 4) + 1) ?></span></div>

<pagebreak />

<!-- ==================== SON SAYFA ==================== -->
<div style="text-align:center; padding-top: 60mm;">
  <div style="font-size: 30pt; font-family: dejavusansb, sans-serif; line-height: 1.4;">
    <span style="color: <?= $accent ?>;">Keşfet</span><br>
    <span style="color: <?= $accent2 ?>; font-size: 14pt;">●</span><br>
    <span style="color: <?= $accent3 ?>;">Geliştir</span><br>
    <span style="color: <?= $accent2 ?>; font-size: 14pt;">●</span><br>
    <span style="color: <?= $accent2 ?>;">Başar</span>
  </div>
  <?php if (!empty($logoPath)): ?>
    <div style="margin-top: 30mm;"><img src="<?= e($logoPath) ?>" style="max-height:25mm;"></div>
  <?php endif; ?>
  <?php if (!empty($a['institution_name'])): ?>
    <div style="margin-top: 10mm; font-size: 12pt; color: <?= $accent ?>; font-weight: 700;"><?= e($a['institution_name']) ?></div>
    <?php if (!empty($a['campus_name'])): ?>
      <div style="font-size: 10pt; color: #6b7280;"><?= e($a['campus_name']) ?></div>
    <?php endif; ?>
  <?php endif; ?>
</div>
