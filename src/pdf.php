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

    // Cache'i kesin olarak devre dışı bırak ve dosya adını her seferinde benzersiz yap.
    // Bu sayede tarayıcı/CDN eski PDF'i kullanmaz.
    if (!headers_sent()) {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    $stamp = date('YmdHis') . '-' . substr(bin2hex(random_bytes(3)), 0, 6);
    if (preg_match('/^(.*?)(\.pdf)?$/i', $filename, $m)) {
        $filename = ($m[1] !== '' ? $m[1] : 'okul-olgunluk') . '-' . $stamp . '.pdf';
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
        'val'     => 'font-family:dejavusans;font-size:11pt;font-weight:bold;color:#1a2a3a;text-align:left;',
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
            // Adı Soyadı + Uygulama Tarihi — değer metni dotted line üzerinde otursun
            // (auto-detect: dotted lines at y=255.8mm ve y=273.7mm; 11pt baseline offset ≈3.5mm)
            $mpdf->WriteFixedPosHTML(
                '<div style="'.$S['val'].'">'.htmlspecialchars($data['student_name'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                115, 252.5, 60, 8, 'hidden'
            );
            $mpdf->WriteFixedPosHTML(
                '<div style="'.$S['val'].'">'.htmlspecialchars($data['date'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                115, 270.5, 60, 8, 'hidden'
            );
        }

        if ($p === 3) {
            // ---- Sayısal Değerlendirme Tablosu — 7 satır + GENEL TOPLAM ----
            // Koordinatlar rapor.pdf'in piksel-perfect grid line tespitinden gelir (300 DPI tarama).
            // Hat tespit komutu: tools/detect_grid.py
            $rows = $data['rows'] ?? [];
            // Satır merkez Y'leri (row_center) - 2mm (text üst offset)
            $ys = [49.63, 57.59, 65.55, 73.54, 81.54, 89.50, 97.46, 105.46];
            // Sütun: hücre sol-x ve hücre genişliği (text-align:center hücre içinde merkezleyecek)
            $cols = [
                ['x' => 68.56,  'w' => 28.36, 'key' => 'qcount'],
                ['x' => 96.92,  'w' => 28.44, 'key' => 'correct'],
                ['x' => 125.36, 'w' => 28.52, 'key' => 'percent'],
                ['x' => 153.88, 'w' => 36.06, 'key' => 'level'],
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
            // GENEL TOPLAM — son satır, Sayısal tablosuyla aynı sütun konumları
            $totals = [
                ['x' => 68.56,  'w' => 28.36, 'v' => (string)($data['totalQ'] ?? '')],
                ['x' => 96.92,  'w' => 28.44, 'v' => (string)($data['totalC'] ?? '')],
                ['x' => 125.36, 'w' => 28.52, 'v' => (string)($data['totalP'] ?? '').'%'],
                ['x' => 153.88, 'w' => 36.06, 'v' => (string)($data['level']['sinif'] ?? '—')],
            ];
            foreach ($totals as $t) {
                $mpdf->WriteFixedPosHTML(
                    '<div style="'.$S['cellsm'].'font-weight:bold;">'.htmlspecialchars($t['v'], ENT_QUOTES, 'UTF-8').'</div>',
                    $t['x'], $ys[7], $t['w'], 8, 'hidden'
                );
            }

            // ---- Alan Bazlı Detaylı Analiz — 4 satır ----
            // Satır merkezleri (row centers): [189.86, 200.57, 211.32, 222.07] — text top = center - 2
            $combY = [187.86, 198.57, 209.32, 220.07];
            $combo = $data['combined'] ?? [];
            $colsC = [
                ['x' => 77.45,  'w' => 26.66, 'k' => 'q'],
                ['x' => 104.11, 'w' => 28.53, 'k' => 'c'],
                ['x' => 132.64, 'w' => 24.37, 'k' => 'pct'],
                ['x' => 157.01, 'w' => 32.93, 'k' => 'level'],
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

            // ---- Olgunluk Düzeyi Tablosu — tek veri satırı (center y = 267.64) ----
            $lvl = $data['level'] ?? null;
            if ($lvl) {
                $rowY = 265.64; // 267.64 - 2 (text üst offset)
                // Sütunlar (auto-detect): Başarı x=20.06 w=23.62, Sınıf x=43.68 w=33.18,
                //                         Karşılık x=76.86 w=33.26, Tavsiyemiz x=110.12 w=79.82
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cell'].'font-weight:bold;">'.htmlspecialchars($lvl['label'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    20.06, $rowY, 23.62, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cellsm'].'">'.htmlspecialchars($lvl['sinif'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    43.68, $rowY, 33.18, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="'.$S['cellsm'].'">'.htmlspecialchars($lvl['karsilik'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    76.86, $rowY, 33.26, 8, 'hidden');
                $mpdf->WriteFixedPosHTML('<div style="font-family:dejavusans;font-size:8.5pt;color:#1a2a3a;text-align:center;line-height:1.25;">'.htmlspecialchars($lvl['tavsiye'] ?? '', ENT_QUOTES, 'UTF-8').'</div>',
                    110.12, $rowY - 1, 79.82, 13, 'visible');
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

    // mPDF'i STRING modunda al ve no-cache header'larıyla biz servis et.
    // Bu sayede CDN/tarayıcı cache'i kesin geçilir.
    $pdfBytes = $mpdf->Output('', \Mpdf\Output\Destination::STRING_RETURN);
    if (!headers_sent()) {
        header('Content-Type: application/pdf');
        header('Content-Disposition: inline; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($pdfBytes));
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Expires: 0');
    }
    echo $pdfBytes;
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
