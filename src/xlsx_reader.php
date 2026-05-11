<?php
declare(strict_types=1);

namespace App;

/**
 * Bağımlılıksız basit XLSX (xlsx) okuyucu.
 * Salt-okunur, formül değerlendirmez, stil bilgisi okumaz.
 *
 * readXlsx() → sadece ilk sayfayı döner (geriye uyumlu).
 * readXlsxAllSheets() → tüm sayfaları sayfa adına göre döner.
 *
 * Dönüş satırı: array<int, array<string, string>>
 *   Her satır kolon harfine göre indekslenir: ['A' => 'soru', 'B' => '...', ...]
 *   Header satırı (genelde 1.) dahil; çağıran istediği indekslemeyi yapar.
 */
function readXlsx(string $path): array {
    $all = readXlsxAllSheets($path);
    return $all ? reset($all) : [];
}

/**
 * Tüm sayfaları okur, sayfa adına göre indeksler.
 * @return array<string, array<int, array<string,string>>>
 */
function readXlsxAllSheets(string $path): array {
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

        // Sayfa adı → dosya yolu eşlemesi (workbook.xml + rels)
        $sheetIndex = []; // name → sheetN.xml
        $wbFile = $tmp . '/xl/workbook.xml';
        $relsFile = $tmp . '/xl/_rels/workbook.xml.rels';
        if (is_file($wbFile) && is_file($relsFile)) {
            $wb = new \SimpleXMLElement(file_get_contents($wbFile));
            $rels = new \SimpleXMLElement(file_get_contents($relsFile));
            $relMap = [];
            foreach ($rels->Relationship as $rel) {
                $relMap[(string)$rel['Id']] = (string)$rel['Target']; // ör. worksheets/sheet2.xml
            }
            foreach ($wb->sheets->sheet as $s) {
                $name = (string)$s['name'];
                $rid  = (string)$s->attributes('r', true)['id'];
                $target = $relMap[$rid] ?? '';
                if ($target) $sheetIndex[$name] = $tmp . '/xl/' . ltrim($target, '/');
            }
        }
        if (!$sheetIndex) {
            // Geri düşüş: tek sayfa
            $sheetIndex = ['Sheet1' => $tmp . '/xl/worksheets/sheet1.xml'];
        }

        $out = [];
        foreach ($sheetIndex as $name => $sheetFile) {
            if (!is_file($sheetFile)) continue;
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
                    $r[$col] = $val;
                }
                $rows[] = $r;
            }
            $out[$name] = $rows;
        }
        return $out;
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
