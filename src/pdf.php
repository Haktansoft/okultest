<?php
declare(strict_types=1);

namespace App;

use const App\VIEWS_PATH;

function renderViewToString(string $view, array $data = []): string {
    $file = VIEWS_PATH . '/' . trim($view, '/') . '.php';
    if (!is_file($file)) {
        throw new \RuntimeException("View bulunamadı: $view");
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    return (string)ob_get_clean();
}

function renderPdfFromView(string $view, array $data, string $filename = 'output.pdf'): void {
    $file = VIEWS_PATH . '/' . trim($view, '/') . '.php';
    if (!is_file($file)) {
        http_response_code(500);
        echo "PDF şablonu bulunamadı: $view";
        return;
    }
    extract($data, EXTR_SKIP);
    ob_start();
    require $file;
    $html = ob_get_clean();

    if (!class_exists(\Mpdf\Mpdf::class)) {
        http_response_code(500);
        echo "mPDF yüklü değil. Lütfen 'composer install' çalıştırın.";
        return;
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans',
        'margin_top' => 16, 'margin_bottom' => 16,
        'margin_left' => 14, 'margin_right' => 14,
    ]);
    $mpdf->WriteHTML($html);
    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
}

// PDF'lerde inline image olarak servis etmek için medya yolunu döndürür.
function pdfMediaSrc(?array $media): ?string {
    if (!$media) return null;
    if (($media['kind'] ?? null) !== 'image') return null;
    return UPLOAD_PATH . '/' . $media['path'];
}

/**
 * Okul Olgunluk Raporu — Benego şablonu (rapor.pdf) üstüne FPDI ile dinamik veri overlay'i.
 *
 * $data:
 *   - student_name, date (string)
 *   - rows: 7 alan [['name','qcount','correct','percent','level','comment'], ...]
 *   - combined: 4 birleşik satır [['label','q','c','pct','level'], ...]
 *   - totalQ, totalC, totalP (int)
 *   - level (?array) — genel olgunluk düzeyi satırı
 */
function renderOlgunlukPdf(array $data, string $filename = 'okul_olgunluk.pdf'): void {
    if (!class_exists(\Mpdf\Mpdf::class)) {
        http_response_code(500); echo "mPDF yüklü değil."; return;
    }
    $template = dirname(__DIR__) . '/public/assets/olgunluk/template.pdf';
    if (!is_file($template)) {
        http_response_code(500); echo "Şablon bulunamadı: public/assets/olgunluk/template.pdf"; return;
    }

    $mpdf = new \Mpdf\Mpdf([
        'mode' => 'utf-8',
        'format' => 'A4',
        'default_font' => 'dejavusans',
        'margin_top' => 0, 'margin_bottom' => 0, 'margin_left' => 0, 'margin_right' => 0,
        'tempDir' => sys_get_temp_dir(),
    ]);

    $pageCount = $mpdf->setSourceFile($template);

    // Overlay stilleri (her sayfa için yeniden yazılabilir)
    $S = [
        'val'     => 'font-family:dejavusans;font-size:11pt;color:#1a2a3a;text-align:left;',
        'cell'    => 'font-family:dejavusans;font-size:9pt;color:#1a2a3a;text-align:center;',
        'cellsm'  => 'font-family:dejavusans;font-size:8.5pt;color:#1a2a3a;text-align:center;line-height:1.15;',
        'comment' => 'font-family:dejavusans;font-size:9.5pt;color:#374151;text-align:center;line-height:1.45;',
        'cellL'   => 'font-family:dejavusans;font-size:9pt;color:#1a2a3a;text-align:left;line-height:1.2;',
    ];

    for ($p = 1; $p <= $pageCount; $p++) {
        $tpl = $mpdf->importPage($p);
        $mpdf->AddPage();
        $mpdf->useTemplate($tpl, 0, 0, 210, 297);

        if ($p === 1) {
            // Adı Soyadı + Uygulama Tarihi
            $mpdf->WriteFixedPosHTML(
                '<div style="'.$S['val'].'">'.htmlspecialchars($data['student_name'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                115, 244, 60, 8, 'hidden'
            );
            $mpdf->WriteFixedPosHTML(
                '<div style="'.$S['val'].'">'.htmlspecialchars($data['date'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                115, 262, 60, 8, 'hidden'
            );
        }

        if ($p === 3) {
            // ---- Sayısal Değerlendirme Tablosu — 7 satır + GENEL TOPLAM ----
            $rows = $data['rows'] ?? [];
            // Y koordinatları (satır metnine bastırılan üst y — şablon satır merkezleri için)
            $ys = [50.0, 58.3, 66.6, 74.9, 83.2, 91.5, 99.8, 108.1]; // 7 alan + GENEL TOPLAM
            // X koordinatları (sütun başlangıçları, hücre genişlikleri)
            $cols = [
                ['x' => 73, 'w' => 30, 'key' => 'qcount'],
                ['x' => 105,'w' => 30, 'key' => 'correct'],
                ['x' => 137,'w' => 30, 'key' => 'percent'],
                ['x' => 167,'w' => 36, 'key' => 'level'],
            ];
            foreach ($rows as $i => $r) {
                if (!isset($ys[$i])) break;
                foreach ($cols as $c) {
                    $v = (string)($r[$c['key']] ?? '');
                    if ($c['key'] === 'percent') $v = $v . '%';
                    $style = $c['key'] === 'level' ? $S['cellsm'] : $S['cell'];
                    $mpdf->WriteFixedPosHTML(
                        '<div style="'.$style.'">'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'</div>',
                        $c['x'], $ys[$i], $c['w'], 8, 'hidden'
                    );
                }
            }
            // GENEL TOPLAM
            $totals = [
                ['x' => 73, 'w' => 30, 'v' => (string)($data['totalQ'] ?? '')],
                ['x' => 105,'w' => 30, 'v' => (string)($data['totalC'] ?? '')],
                ['x' => 137,'w' => 30, 'v' => (string)($data['totalP'] ?? '').'%'],
                ['x' => 167,'w' => 36, 'v' => (string)($data['level']['sinif'] ?? '—')],
            ];
            foreach ($totals as $t) {
                $mpdf->WriteFixedPosHTML(
                    '<div style="'.$S['cellsm'].'font-weight:bold;">'.htmlspecialchars($t['v'], ENT_QUOTES, 'UTF-8').'</div>',
                    $t['x'], $ys[7], $t['w'], 8, 'hidden'
                );
            }

            // ---- Alan Bazlı Detaylı Analiz — 4 satır ----
            $combY = [188.5, 198.5, 208.5, 218.5];
            $combo = $data['combined'] ?? [];
            $colsC = [
                ['x' => 88, 'w' => 27, 'k' => 'q'],
                ['x' => 115,'w' => 27, 'k' => 'c'],
                ['x' => 142,'w' => 23, 'k' => 'pct'],
                ['x' => 165,'w' => 38, 'k' => 'level'],
            ];
            foreach ($combo as $i => $r) {
                if (!isset($combY[$i])) break;
                foreach ($colsC as $c) {
                    $v = (string)($r[$c['k']] ?? '');
                    if ($c['k'] === 'pct') $v .= '%';
                    $style = $c['k'] === 'level' ? $S['cellsm'] : $S['cell'];
                    $mpdf->WriteFixedPosHTML(
                        '<div style="'.$style.'">'.htmlspecialchars($v, ENT_QUOTES, 'UTF-8').'</div>',
                        $c['x'], $combY[$i], $c['w'], 7, 'hidden'
                    );
                }
            }

            // ---- Olgunluk Düzeyi Tablosu — tek satır ----
            $lvl = $data['level'] ?? null;
            if ($lvl) {
                $rowY = 265;
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cell'].'font-weight:bold;">'.htmlspecialchars($lvl['label'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    15, $rowY, 28, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cellsm'].'">'.htmlspecialchars($lvl['sinif'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    44, $rowY, 39, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cellsm'].'">'.htmlspecialchars($lvl['karsilik'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    84, $rowY, 44, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cellL'].'">'.htmlspecialchars($lvl['tavsiye'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    129, $rowY - 1, 66, 14, 'visible');
            }
        }

        if ($p === 4) {
            // 4 alan yorumu — Kelime, Cümle, Günlük, Görsel
            $ys = [62, 124, 186, 248];
            $names = ['Kelime Anlama','Cümle Anlama','Günlük Yaşam','Görsel Algı'];
            foreach ($names as $i => $n) {
                $cmt = pdfCommentFor($data['rows'] ?? [], $n);
                if (!$cmt) continue;
                $mpdf->WriteFixedPosHTML(
                    '<div style="'.$S['comment'].'">'.nl2br(htmlspecialchars($cmt, ENT_QUOTES, 'UTF-8')).'</div>',
                    18, $ys[$i], 174, 14, 'hidden'
                );
            }
        }

        if ($p === 5) {
            // 3 alan yorumu — Erken Matematik, Kopya/İnce Motor, Yönerge Takibi
            $ys = [62, 124, 186];
            $names = ['Erken Matematik','İnce Motor','Yönerge Takibi'];
            foreach ($names as $i => $n) {
                $cmt = pdfCommentFor($data['rows'] ?? [], $n);
                if (!$cmt) continue;
                $mpdf->WriteFixedPosHTML(
                    '<div style="'.$S['comment'].'">'.nl2br(htmlspecialchars($cmt, ENT_QUOTES, 'UTF-8')).'</div>',
                    18, $ys[$i], 174, 14, 'hidden'
                );
            }
        }
    }

    $mpdf->Output($filename, \Mpdf\Output\Destination::INLINE);
}

/** Verilen satırlar arasında ad ile eşleşen yoruma ulaşır (fuzzy match). */
function pdfCommentFor(array $rows, string $name): string {
    $norm = function (string $s): string {
        $map = ['İ'=>'i','I'=>'i','Ş'=>'s','Ğ'=>'g','Ü'=>'u','Ö'=>'o','Ç'=>'c','ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u'];
        $s = strtr($s, $map);
        $s = mb_strtolower($s, 'UTF-8');
        return preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
    };
    $key = $norm($name);
    foreach ($rows as $r) {
        $rk = $norm($r['name'] ?? '');
        if ($rk === $key || strpos($rk, $key) !== false || strpos($key, $rk) !== false) {
            return (string)($r['comment'] ?? '');
        }
    }
    return '';
}
