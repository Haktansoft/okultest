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
