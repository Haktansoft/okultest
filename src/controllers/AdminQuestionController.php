<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class AdminQuestionController {
    public static function index(): void {
        $me = requireRole('admin');
        $cat = (int)($_GET['category_id'] ?? 0);
        $q   = trim((string)($_GET['q'] ?? ''));
        $missing = !empty($_GET['missing_media']);
        $perPage = 50;
        $page = max(1, (int)($_GET['page'] ?? 1));

        $missingExists = "EXISTS (SELECT 1 FROM question_options o WHERE o.question_id = q.id AND o.label LIKE 'GORSEL EKLENECEK:%')";

        $where = []; $params = [];
        if ($cat > 0) { $where[] = "q.category_id = ?"; $params[] = $cat; }
        if ($q !== '') { $where[] = "q.prompt LIKE ?"; $params[] = '%' . $q . '%'; }
        if ($missing)  { $where[] = $missingExists; }
        $whereSql = $where ? (' WHERE ' . implode(' AND ', $where)) : '';

        $cst = db()->prepare("SELECT COUNT(*) FROM questions q" . $whereSql);
        $cst->execute($params);
        $total = (int)$cst->fetchColumn();
        $totalPages = max(1, (int)ceil($total / $perPage));
        if ($page > $totalPages) $page = $totalPages;
        $offset = ($page - 1) * $perPage;

        // Toplam (filtreden bağımsız) eksik görselli soru sayısı
        $missingTotal = (int)db()->query("SELECT COUNT(DISTINCT q.id) FROM questions q WHERE $missingExists")->fetchColumn();

        $sql = "SELECT q.*, c.name AS category_name,
                  (SELECT COUNT(*) FROM question_options o WHERE o.question_id=q.id) AS option_count,
                  (SELECT COALESCE(SUM(o.score), 0) FROM question_options o WHERE o.question_id=q.id) AS total_score,
                  $missingExists AS has_missing_media
                FROM questions q
                JOIN categories c ON c.id=q.category_id"
              . $whereSql
              . " ORDER BY q.id DESC LIMIT $perPage OFFSET $offset";
        $st = db()->prepare($sql); $st->execute($params);
        $items = $st->fetchAll();
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        view('admin/questions/index', [
            'title' => 'Sorular', 'me' => $me, 'items' => $items, 'cats' => $cats,
            'selectedCat' => $cat, 'q' => $q, 'missing' => $missing,
            'page' => $page, 'totalPages' => $totalPages, 'total' => $total,
            'missingTotal' => $missingTotal,
        ]);
    }

    public static function createForm(): void {
        $me = requireRole('admin');
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        if (!$cats) {
            flash('err', 'Önce en az bir kategori oluşturun.');
            redirect('/admin/categories/new');
        }
        $old = self::pullOld();
        view('admin/questions/form', [
            'title' => 'Yeni Soru', 'me' => $me, 'cats' => $cats,
            'item' => $old['item'] ?? null,
            'options' => $old['options'] ?? null,
            'promptMedia' => $old['promptMedia'] ?? null,
        ]);
    }

    public static function create(): void {
        $me = requireRole('admin');
        [$payload, $err] = self::validate();
        if ($err) { self::keepOld(); flash('err', $err); redirect('/admin/questions/new'); }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("INSERT INTO questions (category_id, prompt, prompt_media_id, is_physical, created_by) VALUES (?, ?, ?, ?, ?)");
            $st->execute([$payload['category_id'], $payload['prompt'], $payload['prompt_media_id'], $payload['is_physical'], $me['id']]);
            $qid = (int)$pdo->lastInsertId();
            self::saveOptions($qid, $payload['options']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kayıt hatası: ' . $ex->getMessage());
            redirect('/admin/questions/new');
        }
        flash('ok', 'Soru eklendi.');
        redirect('/admin/questions');
    }

    public static function editForm(string $id): void {
        $me = requireRole('admin');
        $st = db()->prepare("SELECT * FROM questions WHERE id=?");
        $st->execute([$id]);
        $item = $st->fetch();
        if (!$item) { flash('err', 'Soru bulunamadı.'); redirect('/admin/questions'); }
        $os = db()->prepare("
            SELECT o.*, m.kind AS media_kind, m.original_name AS media_name
            FROM question_options o
            LEFT JOIN media m ON m.id = o.media_id
            WHERE o.question_id = ?
            ORDER BY o.sort_order, o.id
        ");
        $os->execute([$id]);
        $options = $os->fetchAll();
        $cats = db()->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
        $pm = null;
        if ($item['prompt_media_id']) {
            $pms = db()->prepare("SELECT * FROM media WHERE id=?");
            $pms->execute([$item['prompt_media_id']]);
            $pm = $pms->fetch() ?: null;
        }
        view('admin/questions/form', ['title' => 'Soru Düzenle', 'me' => $me, 'cats' => $cats, 'item' => $item, 'options' => $options, 'promptMedia' => $pm]);
    }

    public static function update(string $id): void {
        requireRole('admin');
        [$payload, $err] = self::validate();
        if ($err) { self::keepOld(); flash('err', $err); redirect("/admin/questions/$id/edit"); }
        $pdo = db();
        $pdo->beginTransaction();
        try {
            $st = $pdo->prepare("UPDATE questions SET category_id=?, prompt=?, prompt_media_id=?, is_physical=? WHERE id=?");
            $st->execute([$payload['category_id'], $payload['prompt'], $payload['prompt_media_id'], $payload['is_physical'], $id]);
            $pdo->prepare("DELETE FROM question_options WHERE question_id=?")->execute([$id]);
            self::saveOptions((int)$id, $payload['options']);
            $pdo->commit();
        } catch (\Throwable $ex) {
            $pdo->rollBack();
            flash('err', 'Kayıt hatası: ' . $ex->getMessage());
            redirect("/admin/questions/$id/edit");
        }
        flash('ok', 'Soru güncellendi.');
        redirect('/admin/questions');
    }

    public static function delete(string $id): void {
        requireRole('admin');
        try {
            $st = db()->prepare("DELETE FROM questions WHERE id=?");
            $st->execute([$id]);
            flash('ok', 'Soru silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Bu soru bir testte kullanılıyor olabilir, önce testten çıkarın.');
        }
        redirect('/admin/questions');
    }

    private static function validate(): array {
        $cat = (int)($_POST['category_id'] ?? 0);
        $prompt = trim((string)($_POST['prompt'] ?? ''));
        $isPhys = !empty($_POST['is_physical']) ? 1 : 0;
        $promptMediaId = !empty($_POST['prompt_media_id']) ? (int)$_POST['prompt_media_id'] : null;

        if ($cat <= 0) return [null, 'Kategori seçilmeli.'];
        if ($prompt === '') return [null, 'Soru metni boş olamaz.'];

        $labels    = $_POST['option_label']    ?? [];
        $scores    = $_POST['option_score']    ?? [];
        $medias    = $_POST['option_media_id'] ?? [];
        $correct   = $_POST['option_correct']  ?? []; // index -> "1"
        if (!is_array($correct)) $correct = [];
        $count = count($labels);
        if ($count < 2) return [null, 'En az 2 şık girilmeli.'];

        $opts = [];
        for ($i = 0; $i < $count; $i++) {
            $lbl = trim((string)$labels[$i]);
            $mediaId = !empty($medias[$i]) ? (int)$medias[$i] : null;
            if ($lbl === '' && !$mediaId) continue;

            $isCorrect = !empty($correct[$i]);
            $rawScore = isset($scores[$i]) && $scores[$i] !== '' ? (float)$scores[$i] : null;
            // "Doğru" işaretliyse ve özel puan verilmediyse 1 puan ata; değilse manuel girilen puan (yoksa 0)
            $score = $isCorrect
                ? ($rawScore !== null && $rawScore > 0 ? $rawScore : 1)
                : ($rawScore ?? 0);

            $opts[] = ['label' => $lbl, 'score' => $score, 'media_id' => $mediaId, 'sort_order' => $i];
        }
        if (count($opts) < 2) return [null, 'En az 2 şık girilmeli.'];

        $hasScore = array_filter($opts, fn($o) => $o['score'] > 0);
        if (!$hasScore) return [null, 'En az bir şıkkın puanı 0\'dan büyük olmalı (Doğru tik veya manuel puan).'];

        return [[
            'category_id' => $cat,
            'prompt' => $prompt,
            'prompt_media_id' => $promptMediaId,
            'is_physical' => $isPhys,
            'options' => $opts,
        ], null];
    }

    /** Validation hatası sonrası form alanlarını flash'a koy ki form yeniden açıldığında dolu gelsin. */
    private static function keepOld(): void {
        \App\startSession();
        $labels   = $_POST['option_label']    ?? [];
        $scores   = $_POST['option_score']    ?? [];
        $medias   = $_POST['option_media_id'] ?? [];
        $correct  = $_POST['option_correct']  ?? [];
        if (!is_array($correct)) $correct = [];
        $opts = [];
        for ($i = 0, $n = count($labels); $i < $n; $i++) {
            $opts[] = [
                'label'      => (string)$labels[$i],
                'score'      => isset($scores[$i]) && $scores[$i] !== '' ? (float)$scores[$i] : 0,
                'media_id'   => !empty($medias[$i]) ? (int)$medias[$i] : null,
                'is_correct' => !empty($correct[$i]),
            ];
        }
        $_SESSION['_old_question'] = [
            'item' => [
                'category_id'     => (int)($_POST['category_id'] ?? 0),
                'prompt'          => (string)($_POST['prompt'] ?? ''),
                'is_physical'     => !empty($_POST['is_physical']) ? 1 : 0,
                'prompt_media_id' => !empty($_POST['prompt_media_id']) ? (int)$_POST['prompt_media_id'] : null,
            ],
            'options' => $opts,
        ];
    }

    private static function pullOld(): array {
        \App\startSession();
        if (empty($_SESSION['_old_question'])) return [];
        $old = $_SESSION['_old_question'];
        unset($_SESSION['_old_question']);

        // promptMedia bilgisini de getirsin
        $promptMedia = null;
        if (!empty($old['item']['prompt_media_id'])) {
            $st = db()->prepare("SELECT * FROM media WHERE id=?");
            $st->execute([$old['item']['prompt_media_id']]);
            $promptMedia = $st->fetch() ?: null;
        }
        $old['promptMedia'] = $promptMedia;
        return $old;
    }

    private static function saveOptions(int $qid, array $opts): void {
        $st = db()->prepare("INSERT INTO question_options (question_id, label, media_id, score, sort_order) VALUES (?, ?, ?, ?, ?)");
        foreach ($opts as $o) {
            $st->execute([$qid, $o['label'], $o['media_id'], $o['score'], $o['sort_order']]);
        }
    }

    // ========== XLSX TOPLU İÇE AKTARMA ==========

    public static function importForm(): void {
        $me = requireRole('admin');
        view('admin/questions/import', [
            'title' => 'Toplu Soru İçe Aktarma',
            'me' => $me,
            'report' => null,
        ]);
    }

    public static function importRun(): void {
        $me = requireRole('admin');

        if (empty($_FILES['file']) || ($_FILES['file']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            flash('err', 'XLSX dosyası seçilmedi veya yüklenemedi.');
            redirect('/admin/questions/import');
        }
        $tmpPath = $_FILES['file']['tmp_name'];
        $fname   = $_FILES['file']['name'] ?? 'sorular.xlsx';

        try {
            $rows = \App\readXlsx($tmpPath);
        } catch (\Throwable $ex) {
            flash('err', 'XLSX okunamadı: ' . $ex->getMessage());
            redirect('/admin/questions/import');
        }

        // Boş dosya
        if (count($rows) < 2) {
            flash('err', 'Dosyada veri yok (sadece başlık satırı bulundu).');
            redirect('/admin/questions/import');
        }

        // 1. satır = başlık. Sütun harfleri sabit:
        //   B=SORU, D=RESİM, E/G/I/K/M = CEVAP 1-5, F/H/J/L/N = DEĞER 1-5,
        //   O = DOĞRU CEVAP (A..E), P = ALT BAŞLIK (kategori adı)
        $answerCols = [
            'A' => ['E','F'], 'B' => ['G','H'], 'C' => ['I','J'], 'D' => ['K','L'], 'E' => ['M','N'],
        ];

        $pdo = db();
        $report = [
            'total' => 0, 'imported' => 0, 'skipped' => 0,
            'categoriesCreated' => 0,
            'mediaMatched' => 0, 'mediaMissing' => 0,
            'physicalMarked' => 0,
            'errors' => [],
            'created_questions' => [],
        ];

        // Önce tüm medyayı belleğe al (basename → media row eşlemesi)
        $allMedia = $pdo->query("SELECT id, kind, original_name FROM media")->fetchAll();
        $mediaIndex = []; // anahtar: lowercased basename ve full original_name
        foreach ($allMedia as $m) {
            $name = (string)$m['original_name'];
            $key1 = mb_strtolower($name, 'UTF-8');
            $key2 = mb_strtolower(basename($name), 'UTF-8');
            $key3 = mb_strtolower(pathinfo(basename($name), PATHINFO_FILENAME), 'UTF-8');
            $mediaIndex[$key1] = $m;
            $mediaIndex[$key2] = $m;
            $mediaIndex[$key3] = $m;
        }
        $findMedia = function (string $needle) use ($mediaIndex) {
            $needle = trim($needle);
            if ($needle === '') return null;
            $tries = [
                mb_strtolower($needle, 'UTF-8'),
                mb_strtolower(basename($needle), 'UTF-8'),
                mb_strtolower(pathinfo(basename($needle), PATHINFO_FILENAME), 'UTF-8'),
            ];
            foreach ($tries as $k) {
                if (isset($mediaIndex[$k])) return $mediaIndex[$k];
            }
            return null;
        };

        // Bu kategorilerdeki sorular otomatik "fiziksel" (öğretmen girer) olarak işaretlenir.
        // Karşılaştırma case- ve aksan-duyarsız (Türkçe İ/I kombinasyonlarına dayanıklı).
        $physicalCategoryNames = ['İnce Motor', 'Yönerge Takibi'];
        $normalize = function (string $s): string {
            $s = trim($s);
            $map = [
                // Türkçe büyük → ASCII küçük
                'İ'=>'i','I'=>'i','Ş'=>'s','Ğ'=>'g','Ü'=>'u','Ö'=>'o','Ç'=>'c',
                'ç'=>'c','ğ'=>'g','ı'=>'i','ö'=>'o','ş'=>'s','ü'=>'u',
                // Genel ASCII büyük → küçük
                'A'=>'a','B'=>'b','C'=>'c','D'=>'d','E'=>'e','F'=>'f','G'=>'g','H'=>'h',
                'J'=>'j','K'=>'k','L'=>'l','M'=>'m','N'=>'n','O'=>'o','P'=>'p',
                'Q'=>'q','R'=>'r','S'=>'s','T'=>'t','U'=>'u','V'=>'v','W'=>'w','X'=>'x',
                'Y'=>'y','Z'=>'z',
            ];
            return strtr($s, $map);
        };
        $physicalKeys = array_map($normalize, $physicalCategoryNames);

        // Kategori cache (ad → id), ihtiyaç olunca oluştur
        $catCache = [];
        foreach ($pdo->query("SELECT id, name FROM categories")->fetchAll() as $c) {
            $catCache[mb_strtolower($c['name'], 'UTF-8')] = (int)$c['id'];
        }
        $getOrCreateCategory = function (string $name) use ($pdo, &$catCache, &$report, $me): int {
            $name = trim($name);
            $key = mb_strtolower($name, 'UTF-8');
            if (isset($catCache[$key])) return $catCache[$key];
            $st = $pdo->prepare("INSERT INTO categories (name, created_by) VALUES (?, ?)");
            $st->execute([$name, $me['id']]);
            $id = (int)$pdo->lastInsertId();
            $catCache[$key] = $id;
            $report['categoriesCreated']++;
            return $id;
        };

        $isLikelyImageRef = function (string $v): bool {
            $v = trim($v);
            if ($v === '') return false;
            return (bool)preg_match('/\.(png|jpe?g|gif|webp|bmp|svg)$/i', $v);
        };

        $insertQ  = $pdo->prepare("INSERT INTO questions (category_id, prompt, prompt_media_id, is_physical, created_by) VALUES (?, ?, ?, ?, ?)");
        $insertO  = $pdo->prepare("INSERT INTO question_options (question_id, label, media_id, score, sort_order) VALUES (?, ?, ?, ?, ?)");

        // 2. satırdan itibaren işle
        for ($i = 1; $i < count($rows); $i++) {
            $report['total']++;
            $r = $rows[$i];
            $rowNo = $i + 1;

            $prompt = trim((string)($r['B'] ?? ''));
            if ($prompt === '') {
                $report['skipped']++;
                $report['errors'][] = "Satır $rowNo: SORU (B) boş, atlandı.";
                continue;
            }

            $catName = trim((string)($r['P'] ?? ''));
            if ($catName === '') {
                $report['skipped']++;
                $report['errors'][] = "Satır $rowNo: ALT BAŞLIK (P) boş, atlandı.";
                continue;
            }
            $catId = $getOrCreateCategory($catName);
            // Bu kategorideki sorular fiziksel mi (öğretmen girer)?
            $isPhysical = in_array($normalize($catName), $physicalKeys, true) ? 1 : 0;

            // Sorunun kendi görseli (D)
            $promptMediaId = null;
            $promptMediaName = trim((string)($r['D'] ?? ''));
            if ($promptMediaName !== '') {
                $m = $findMedia($promptMediaName);
                if ($m && $m['kind'] === 'image') {
                    $promptMediaId = (int)$m['id'];
                    $report['mediaMatched']++;
                } else {
                    $report['mediaMissing']++;
                    $report['errors'][] = "Satır $rowNo: Sorunun görseli '$promptMediaName' bulunamadı.";
                }
            }

            // Şıkları işle (E,F G,H I,J K,L M,N)
            $opts = [];
            foreach ($answerCols as $letter => [$ansCol, $valCol]) {
                $ansRaw = trim((string)($r[$ansCol] ?? ''));
                if ($ansRaw === '') continue;
                $valRaw = trim((string)($r[$valCol] ?? ''));
                $score  = $valRaw === '' ? 0 : (float)str_replace(',', '.', $valRaw);

                $optMediaId = null;
                $label = $ansRaw;
                if ($isLikelyImageRef($ansRaw)) {
                    $m = $findMedia($ansRaw);
                    if ($m && $m['kind'] === 'image') {
                        $optMediaId = (int)$m['id'];
                        $label = ''; // sadece görsel; metni boş
                        $report['mediaMatched']++;
                    } else {
                        $label = 'GORSEL EKLENECEK: ' . basename($ansRaw);
                        $report['mediaMissing']++;
                    }
                }
                $opts[] = ['letter' => $letter, 'label' => $label, 'media_id' => $optMediaId, 'score' => $score];
            }

            if (count($opts) < 2) {
                $report['skipped']++;
                $report['errors'][] = "Satır $rowNo: En az 2 şık gerekiyor (sadece " . count($opts) . " bulundu).";
                continue;
            }

            // Tüm puanlar 0 ise → DOĞRU CEVAP'a 1 puan ver
            $totalScore = array_sum(array_column($opts, 'score'));
            if ($totalScore <= 0) {
                $correctLetter = strtoupper(trim((string)($r['O'] ?? '')));
                if ($correctLetter !== '') {
                    foreach ($opts as &$o) {
                        if ($o['letter'] === $correctLetter) $o['score'] = 1.0;
                    }
                    unset($o);
                    $totalScore = array_sum(array_column($opts, 'score'));
                }
                if ($totalScore <= 0) {
                    $report['skipped']++;
                    $report['errors'][] = "Satır $rowNo: Hiçbir şıkkın puanı yok ve DOĞRU CEVAP (O) boş.";
                    continue;
                }
            }

            // Soruyu kaydet
            try {
                $pdo->beginTransaction();
                $insertQ->execute([$catId, $prompt, $promptMediaId, $isPhysical, $me['id']]);
                $qid = (int)$pdo->lastInsertId();
                $sortOrder = 0;
                foreach ($opts as $o) {
                    $insertO->execute([$qid, $o['label'], $o['media_id'], $o['score'], $sortOrder++]);
                }
                $pdo->commit();
                $report['imported']++;
                if ($isPhysical) $report['physicalMarked']++;
                $report['created_questions'][] = ['id' => $qid, 'prompt' => $prompt];
            } catch (\Throwable $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $report['skipped']++;
                $report['errors'][] = "Satır $rowNo: DB hatası — " . $ex->getMessage();
            }
        }

        view('admin/questions/import', [
            'title'  => 'Toplu Soru İçe Aktarma — Rapor',
            'me'     => $me,
            'report' => $report,
            'fname'  => $fname,
        ]);
    }
}
