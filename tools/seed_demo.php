<?php
// Olgunluk PDF test seed — kurum + öğretmen + öğrenci + 7 kategori + sorular + tamamlanmış atama.
declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

$pdo = App\db();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$pdo->beginTransaction();

try {
    // Kurum + kampüs
    $pdo->exec("INSERT INTO institutions (id, name) VALUES (1, 'Demo Anaokulu') ON DUPLICATE KEY UPDATE name=VALUES(name)");
    $pdo->exec("INSERT INTO campuses (id, institution_id, name) VALUES (1, 1, 'Merkez Şube') ON DUPLICATE KEY UPDATE name=VALUES(name)");

    // Öğretmen + öğrenci (admin zaten admin1234)
    $pdo->exec("INSERT INTO users (id, role, full_name, password, campus_id, is_active) VALUES
        (2, 'teacher', 'Demo Öğretmen', 'teacher1234', 1, 1),
        (3, 'student', 'Ayşe Yılmaz', 'student1234', 1, 1)
        ON DUPLICATE KEY UPDATE full_name=VALUES(full_name)");

    // 7 kategori
    $cats = [
        1 => 'Kelime Anlama', 2 => 'Cümle Anlama', 3 => 'Günlük Yaşam',
        4 => 'Görsel Algı',   5 => 'Erken Matematik',
        6 => 'İnce Motor',    7 => 'Yönerge Takibi',
    ];
    foreach ($cats as $id => $name) {
        $pdo->prepare("INSERT INTO categories (id, name) VALUES (?, ?) ON DUPLICATE KEY UPDATE name=VALUES(name)")
            ->execute([$id, $name]);
    }

    // Soru sayıları (Benego ile uyumlu) — kategori başına soru üret
    $perCat = [1=>18, 2=>18, 3=>12, 4=>16, 5=>14, 6=>10, 7=>12];
    $physicalCats = [6, 7]; // İnce Motor + Yönerge Takibi

    $pdo->exec("INSERT INTO tests (id, title, description, time_limit_minutes, created_by) VALUES
        (1, 'Okul Olgunluk Demo', 'Demo veri seti', 30, 1)
        ON DUPLICATE KEY UPDATE title=VALUES(title)");

    // Temizle (yeniden çalıştırılabilir olsun)
    $pdo->exec("DELETE FROM test_questions WHERE test_id=1");
    $pdo->exec("DELETE FROM question_options WHERE question_id IN (SELECT id FROM questions WHERE category_id BETWEEN 1 AND 7)");
    $pdo->exec("DELETE FROM questions WHERE category_id BETWEEN 1 AND 7");

    $sort = 0;
    foreach ($perCat as $cid => $count) {
        $isPhys = in_array($cid, $physicalCats, true) ? 1 : 0;
        for ($i = 1; $i <= $count; $i++) {
            $pdo->prepare("INSERT INTO questions (category_id, prompt, is_physical, created_by) VALUES (?, ?, ?, 1)")
                ->execute([$cid, $cats[$cid] . " - Soru #{$i}", $isPhys]);
            $qid = (int)$pdo->lastInsertId();

            // 2 seçenek: doğru (1.00) + yanlış (0.00)
            $pdo->prepare("INSERT INTO question_options (question_id, label, score, sort_order) VALUES (?, 'Doğru', 1.00, 0), (?, 'Yanlış', 0.00, 1)")
                ->execute([$qid, $qid]);
            $pdo->prepare("INSERT INTO test_questions (test_id, question_id, sort_order) VALUES (1, ?, ?)")
                ->execute([$qid, $sort++]);
        }
    }

    // Assignment (tamamlanmış)
    $pdo->exec("DELETE FROM test_assignments WHERE id=1");
    $pdo->exec("INSERT INTO test_assignments (id, test_id, student_id, teacher_id, status, mode, started_at, finished_at, total_score) VALUES
        (1, 1, 3, 2, 'completed', 'per_question', '2026-05-11 13:00:00', '2026-05-11 13:25:00', 85.00)");

    // Cevaplar: gerçekçi başarı oranları (kategoriye göre)
    $successRate = [1=>0.89, 2=>0.83, 3=>0.83, 4=>0.69, 5=>0.93, 6=>0.90, 7=>0.92];

    $qs = $pdo->query("SELECT q.id, q.category_id, q.is_physical, (SELECT id FROM question_options WHERE question_id=q.id AND score>0 LIMIT 1) AS correct_opt, (SELECT id FROM question_options WHERE question_id=q.id AND score=0 LIMIT 1) AS wrong_opt FROM questions q WHERE category_id BETWEEN 1 AND 7")->fetchAll(PDO::FETCH_ASSOC);

    $byCat = [];
    foreach ($qs as $q) $byCat[(int)$q['category_id']][] = $q;

    foreach ($byCat as $cid => $rows) {
        $target = (int)round(count($rows) * $successRate[$cid]);
        foreach ($rows as $i => $q) {
            $optId = $i < $target ? $q['correct_opt'] : $q['wrong_opt'];
            if ($q['is_physical']) {
                $pdo->prepare("INSERT INTO physical_answers (assignment_id, question_id, selected_option_id, entered_by_teacher_id) VALUES (1, ?, ?, 2)")
                    ->execute([$q['id'], $optId]);
            } else {
                $pdo->prepare("INSERT INTO attempt_answers (assignment_id, question_id, selected_option_id, time_spent_seconds) VALUES (1, ?, ?, 12)")
                    ->execute([$q['id'], $optId]);
            }
        }
    }

    $pdo->commit();
    echo "Seed tamamlandı.".PHP_EOL;
    echo "  - Admin şifresi: admin1234".PHP_EOL;
    echo "  - Öğretmen şifresi: teacher1234".PHP_EOL;
    echo "  - Assignment ID: 1 (Ayşe Yılmaz / Okul Olgunluk Demo)".PHP_EOL;
} catch (Throwable $e) {
    $pdo->rollBack();
    fwrite(STDERR, "HATA: ".$e->getMessage().PHP_EOL);
    exit(1);
}
