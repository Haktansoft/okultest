<?php
declare(strict_types=1);

namespace App;

/**
 * Okul Olgunluk Raporu için Excel'den yorum verisi yükler.
 *
 * - rapor_yorum.xlsx → "yorum" sayfası: her alt başlık için 4 yüzde bandı (%90-100, %75-89, %60-74, %60 altı)
 * - "olgunluk" sayfası: genel toplam yüzdesine göre olgunluk sınıflandırması + tavsiye
 *
 * Veritabanı kategori adlarını Excel başlıklarıyla eşleştirmek için normalizasyon yapar.
 */

function olgunlukYorumData(): array {
    static $cache = null;
    if ($cache !== null) return $cache;

    $path = dirname(__DIR__) . '/rapor_yorum.xlsx';
    if (!is_file($path)) {
        return $cache = ['yorum' => [], 'olgunluk' => []];
    }
    $all = readXlsxAllSheets($path);

    // ---- yorum sayfası ----
    $yorum = [];
    foreach (($all['yorum'] ?? []) as $i => $row) {
        if ($i === 0) continue; // header
        $alt = trim($row['A'] ?? '');
        if ($alt === '') continue;
        $yorum[olgunlukNormKey($alt)] = [
            'label'       => $alt,
            'description' => trim($row['B'] ?? ''),
            'bands'       => [
                ['min' => 90, 'max' => 100, 'text' => trim($row['C'] ?? '')],
                ['min' => 75, 'max' => 89,  'text' => trim($row['D'] ?? '')],
                ['min' => 60, 'max' => 74,  'text' => trim($row['E'] ?? '')],
                ['min' => 0,  'max' => 59,  'text' => trim($row['F'] ?? '')],
            ],
        ];
    }

    // ---- olgunluk sayfası ----
    $olgunluk = [];
    foreach (($all['olgunluk'] ?? []) as $i => $row) {
        if ($i === 0) continue;
        $band = trim($row['A'] ?? '');
        if ($band === '') continue;
        [$min, $max] = olgunlukParseBand($band);
        $olgunluk[] = [
            'min'      => $min,
            'max'      => $max,
            'label'    => $band,
            'sinif'    => trim($row['B'] ?? ''),
            'karsilik' => trim($row['C'] ?? ''),
            'tavsiye'  => trim($row['D'] ?? ''),
        ];
    }

    return $cache = ['yorum' => $yorum, 'olgunluk' => $olgunluk];
}

/** Kategori/alt başlık adını Türkçe karakter ve boşluk bağımsız hale getirir. */
function olgunlukNormKey(string $s): string {
    $map = [
        'İ'=>'i','I'=>'i','Ş'=>'s','Ğ'=>'g','Ü'=>'u','Ö'=>'o','Ç'=>'c',
        'ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
    ];
    $s = strtr($s, $map);
    $s = mb_strtolower($s, 'UTF-8');
    $s = preg_replace('/[^a-z0-9]+/u', '', $s) ?? '';
    return $s;
}

/** "%90 - %100" gibi etiketlerden [min,max] aralığını çıkarır. */
function olgunlukParseBand(string $s): array {
    if (preg_match_all('/(\d{1,3})/', $s, $m) && count($m[1]) >= 2) {
        $a = (int)$m[1][0]; $b = (int)$m[1][1];
        return [min($a,$b), max($a,$b)];
    }
    if (preg_match('/(\d{1,3})/', $s, $m)) {
        $n = (int)$m[1];
        if (stripos($s, 'alt') !== false) return [0, $n - 1];
        return [$n, 100];
    }
    return [0, 100];
}

/** Bir alt başlık + yüzdeye karşılık gelen yorum metnini döndürür. */
function olgunlukCommentFor(string $altBaslik, int $percent): array {
    $data = olgunlukYorumData()['yorum'];
    $key = olgunlukNormKey($altBaslik);

    // DB kategori adı ↔ Excel başlığı eşleşmeleri (anahtar kelime tabanlı)
    static $aliases = [
        'gorselalgi'        => 'gorseleslestirmevealgi',
        'gorseleslestirme'  => 'gorseleslestirmevealgi',
        'gunlukyasam'       => 'gunlukyasamgenelbilgi',
        'genelbilgi'        => 'gunlukyasamgenelbilgi',
        'erkenmatematik'    => 'erkenmatematikhazirligi',
        'matematik'         => 'erkenmatematikhazirligi',
        'incemotor'         => 'kopyaetmeincemotor',
        'kopya'             => 'kopyaetmeincemotor',
        'kopyamotor'        => 'kopyaetmeincemotor',
    ];
    if (!isset($data[$key]) && isset($aliases[$key]) && isset($data[$aliases[$key]])) {
        $key = $aliases[$key];
    }
    if (!isset($data[$key])) {
        foreach ($data as $k => $entry) {
            if (strpos($k, $key) !== false || strpos($key, $k) !== false) { $key = $k; break; }
        }
    }
    if (!isset($data[$key])) return ['description' => '', 'comment' => ''];

    $entry = $data[$key];
    $text = '';
    foreach ($entry['bands'] as $b) {
        if ($percent >= $b['min'] && $percent <= $b['max']) { $text = $b['text']; break; }
    }
    return ['description' => $entry['description'], 'comment' => $text];
}

/** Genel toplam yüzdesine göre olgunluk sınıflandırması satırını döndürür. */
function olgunlukLevelFor(int $percent): ?array {
    foreach (olgunlukYorumData()['olgunluk'] as $row) {
        if ($percent >= $row['min'] && $percent <= $row['max']) return $row;
    }
    return null;
}
