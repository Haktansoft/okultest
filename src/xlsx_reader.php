<?php
declare(strict_types=1);

namespace App;

/**
 * Bağımlılıksız basit XLSX (xlsx) okuyucu — sadece ilk sayfa.
 * Salt-okunur, formül değerlendirmez, stil bilgisi okumaz.
 *
 * Dönüş: array<int, array<string, string>>
 *   Her satır kolon harfine göre indekslenir: ['A' => 'soru', 'B' => '...', ...]
 *   Header satırı (genelde 1.) dahil; çağıran istediği indekslemeyi yapar.
 */
function readXlsx(string $path): array {
    if (!is_file($path)) {
        throw new \RuntimeException("XLSX bulunamadı: $path");
    }

    $tmp = sys_get_temp_dir() . '/xlsx_' . bin2hex(random_bytes(6));
    if (!mkdir($tmp, 0700, true)) {
        throw new \RuntimeException("Geçici dizin oluşturulamadı.");
    }
    try {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException("XLSX açılamadı (zip hatası).");
        }
        $zip->extractTo($tmp);
        $zip->close();

        // SharedStrings
        $strings = [];
        $ssFile = $tmp . '/xl/sharedStrings.xml';
        if (is_file($ssFile)) {
            $ss = new \SimpleXMLElement(file_get_contents($ssFile));
            foreach ($ss->si as $si) {
                if (isset($si->t)) {
                    $strings[] = (string)$si->t;
                } else {
                    // Rich text: <r><t>...</t></r> parçalarını birleştir
                    $t = '';
                    foreach ($si->r as $r) $t .= (string)$r->t;
                    $strings[] = $t;
                }
            }
        }

        // İlk sayfa
        $sheetFile = $tmp . '/xl/worksheets/sheet1.xml';
        if (!is_file($sheetFile)) {
            throw new \RuntimeException("XLSX'te sheet1.xml yok.");
        }
        $sheet = new \SimpleXMLElement(file_get_contents($sheetFile));

        $rows = [];
        foreach ($sheet->sheetData->row as $row) {
            $r = [];
            foreach ($row->c as $c) {
                $ref  = (string)$c['r'];
                $col  = preg_replace('/\d+/', '', $ref);
                $type = (string)$c['t'];
                $val  = (string)$c->v;
                if ($type === 's') {
                    $val = $strings[(int)$val] ?? '';
                } elseif ($type === 'inlineStr') {
                    $val = (string)$c->is->t;
                } elseif ($type === 'b') {
                    $val = $val === '1' ? 'TRUE' : 'FALSE';
                }
                // Sayı / tarih → string olarak kalır; çağıran yorumlar
                $r[$col] = $val;
            }
            $rows[] = $r;
        }
        return $rows;
    } finally {
        // Geçici dosyaları temizle
        $rm = function ($p) use (&$rm) {
            if (is_dir($p)) {
                foreach (scandir($p) ?: [] as $i) {
                    if ($i === '.' || $i === '..') continue;
                    $rm($p . '/' . $i);
                }
                @rmdir($p);
            } elseif (is_file($p)) {
                @unlink($p);
            }
        };
        $rm($tmp);
    }
}
