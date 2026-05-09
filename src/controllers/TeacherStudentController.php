<?php
declare(strict_types=1);

namespace App\Controllers;

use function App\{db, e, flash, redirect, requireRole, view};

class TeacherStudentController {
    public const GRADE_LEVELS = ['4 YAŞ', '5 YAŞ', '6 YAŞ', '1. SINIF', '2. SINIF', '3. SINIF', '4. SINIF'];
    public const SECTIONS     = ['A','B','C','D','E','F','G','H'];

    public static function index(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        if ($isAdmin) {
            $items = db()->query("
                SELECT u.*, c.name AS campus_name, i.name AS institution_name
                  FROM users u
             LEFT JOIN campuses c ON c.id = u.campus_id
             LEFT JOIN institutions i ON i.id = c.institution_id
                 WHERE u.role='student'
              ORDER BY i.name, c.name, u.grade_level, u.section, u.full_name
            ")->fetchAll();
        } else {
            $campusId = self::myCampusId($me);
            if (!$campusId) {
                flash('err', 'Yönetici sana bir kampüs atamadan öğrenci listeleyemezsin.');
                redirect('/teacher');
            }
            $st = db()->prepare("
                SELECT * FROM users
                 WHERE role='student' AND campus_id=?
              ORDER BY grade_level, section, full_name
            ");
            $st->execute([$campusId]);
            $items = $st->fetchAll();
        }
        view('teacher/students/index', ['title' => 'Öğrenciler', 'me' => $me, 'items' => $items]);
    }

    public static function createForm(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';
        $campuses = $isAdmin ? self::campusOptions() : [];
        if ($isAdmin && !$campuses) {
            flash('err', 'Önce en az bir kampüs oluşturun.');
            redirect('/admin/campuses/new');
        }
        if (!$isAdmin && !self::myCampusId($me)) {
            flash('err', 'Önce sana bir kampüs atanmalı.');
            redirect('/teacher/students');
        }
        view('teacher/students/form', [
            'title' => 'Yeni Öğrenci', 'me' => $me, 'item' => null,
            'campuses' => $campuses,
            'gradeLevels' => self::GRADE_LEVELS,
            'sections' => self::SECTIONS,
        ]);
    }

    public static function create(): void {
        $me = requireRole('teacher', 'admin');
        $isAdmin = $me['role'] === 'admin';

        $name  = trim((string)($_POST['full_name'] ?? ''));
        $tc    = self::cleanTc($_POST['tc'] ?? '');
        $grade = trim((string)($_POST['grade_level'] ?? ''));
        $sect  = trim((string)($_POST['section'] ?? ''));

        if ($isAdmin) {
            $campusId = (int)($_POST['campus_id'] ?? 0);
            if ($campusId <= 0 || !self::campusExists($campusId)) {
                flash('err', 'Geçerli bir kampüs seç.');
                redirect('/teacher/students/new');
            }
        } else {
            $campusId = self::myCampusId($me);
            if (!$campusId) { flash('err', 'Kampüs yok.'); redirect('/teacher/students'); }
        }

        $err = self::validateBasics($name, $tc, $grade, $sect);
        if ($err) { flash('err', $err); redirect('/teacher/students/new'); }
        if (self::tcExists($tc)) {
            flash('err', 'Bu T.C. numarası başka bir kullanıcıda kayıtlı.');
            redirect('/teacher/students/new');
        }
        if (self::passwordExists($tc)) {
            flash('err', 'Bu T.C. başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect('/teacher/students/new');
        }

        $teacherForAssign = $isAdmin
            ? self::pickTeacherForCampus($campusId, (int)$me['id'])
            : (int)$me['id'];
        $creatorId = (int)$me['id'];

        $pdo = db();
        try {
            $pdo->beginTransaction();
            $st = $pdo->prepare("
                INSERT INTO users (role, full_name, tc, grade_level, section, password, campus_id, is_active, created_by)
                VALUES ('student', ?, ?, ?, ?, ?, ?, 1, ?)
            ");
            $st->execute([$name, $tc, $grade, $sect, $tc, $campusId, $creatorId]);
            $studentId = (int)$pdo->lastInsertId();
            self::autoAssignAllTests($studentId, $teacherForAssign);
            $pdo->commit();
        } catch (\PDOException $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            flash('err', 'Kayıt yapılamadı.');
            redirect('/teacher/students/new');
        }
        flash('ok', 'Öğrenci eklendi ve mevcut testler otomatik atandı.');
        redirect('/teacher/students');
    }

    public static function editForm(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        $isAdmin = $me['role'] === 'admin';
        view('teacher/students/form', [
            'title' => 'Öğrenciyi Düzenle', 'me' => $me, 'item' => $item,
            'campuses' => $isAdmin ? self::campusOptions() : [],
            'gradeLevels' => self::GRADE_LEVELS,
            'sections' => self::SECTIONS,
        ]);
    }

    public static function update(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        $isAdmin = $me['role'] === 'admin';

        $name  = trim((string)($_POST['full_name'] ?? ''));
        $tc    = self::cleanTc($_POST['tc'] ?? '');
        $grade = trim((string)($_POST['grade_level'] ?? ''));
        $sect  = trim((string)($_POST['section'] ?? ''));

        // Sadece admin kampüs değiştirebilir; öğretmen kendi kampüsündeki kalır
        if ($isAdmin) {
            $campusId = (int)($_POST['campus_id'] ?? 0);
            if ($campusId <= 0 || !self::campusExists($campusId)) {
                flash('err', 'Geçerli bir kampüs seç.');
                redirect("/teacher/students/$id/edit");
            }
        } else {
            $campusId = (int)$item['campus_id'];
        }

        $err = self::validateBasics($name, $tc, $grade, $sect);
        if ($err) { flash('err', $err); redirect("/teacher/students/$id/edit"); }
        if (self::tcExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. başka bir kullanıcıda kayıtlı.');
            redirect("/teacher/students/$id/edit");
        }
        if (self::passwordExists($tc, (int)$id)) {
            flash('err', 'Bu T.C. başka bir kullanıcının şifresi olarak kullanılıyor.');
            redirect("/teacher/students/$id/edit");
        }
        try {
            db()->prepare("
                UPDATE users
                   SET full_name=?, tc=?, password=?, grade_level=?, section=?, campus_id=?
                 WHERE id=? AND role='student'
            ")->execute([$name, $tc, $tc, $grade, $sect, $campusId, $id]);
        } catch (\PDOException $ex) {
            flash('err', 'Güncelleme yapılamadı.');
            redirect("/teacher/students/$id/edit");
        }
        flash('ok', 'Öğrenci güncellendi.');
        redirect('/teacher/students');
    }

    public static function delete(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        try {
            db()->prepare("DELETE FROM users WHERE id=? AND role='student'")->execute([$id]);
            flash('ok', 'Öğrenci silindi.');
        } catch (\PDOException $ex) {
            flash('err', 'Silme hatası.');
        }
        redirect('/teacher/students');
    }

    public static function toggle(string $id): void {
        $me = requireRole('teacher', 'admin');
        $item = self::loadOwned((int)$id, $me);
        if (!$item) { flash('err', 'Öğrenci bulunamadı.'); redirect('/teacher/students'); }
        db()->prepare("UPDATE users SET is_active = 1 - is_active WHERE id=? AND role='student'")->execute([$id]);
        flash('ok', 'Durum güncellendi.');
        redirect('/teacher/students');
    }

    public static function importForm(): void {
        $me = requireRole('admin');
        view('teacher/students/import', [
            'title' => 'Toplu Öğrenci İçe Aktarma', 'me' => $me,
            'report' => null, 'fname' => null,
            'gradeLevels' => self::GRADE_LEVELS,
            'sections' => self::SECTIONS,
        ]);
    }

    public static function importRun(): void {
        $me = requireRole('admin');
        if (empty($_FILES['file']) || !is_uploaded_file($_FILES['file']['tmp_name'] ?? '')) {
            flash('err', 'Dosya yüklenemedi.');
            redirect('/teacher/students/import');
        }
        $fname = (string)($_FILES['file']['name'] ?? 'kayit.xlsx');

        try {
            $rows = \App\readXlsx($_FILES['file']['tmp_name']);
        } catch (\Throwable $e) {
            flash('err', 'XLSX okunamadı: ' . $e->getMessage());
            redirect('/teacher/students/import');
        }

        $report = [
            'total' => 0, 'imported' => 0, 'skipped' => 0,
            'autoAssigned' => 0, 'errors' => [],
        ];

        if (count($rows) < 2) {
            $report['errors'][] = 'Dosyada veri satırı yok (sadece başlık var).';
            view('teacher/students/import', [
                'title' => 'Toplu Öğrenci İçe Aktarma', 'me' => $me,
                'report' => $report, 'fname' => $fname,
                'gradeLevels' => self::GRADE_LEVELS, 'sections' => self::SECTIONS,
            ]);
            return;
        }

        $pdo = db();
        // Hızlı erişim için kampüsleri yükle (id => institution_id)
        $campusMap = [];
        foreach ($pdo->query("SELECT id, institution_id FROM campuses") as $r) {
            $campusMap[(int)$r['id']] = (int)$r['institution_id'];
        }
        $totalTests = (int)$pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();

        // Header satırını atla (1. satır)
        $dataRows = array_slice($rows, 1);
        foreach ($dataRows as $idx => $row) {
            $rowNum = $idx + 2;
            $kurumId  = (int)($row['A'] ?? 0);
            $kampusId = (int)($row['B'] ?? 0);
            $name     = trim((string)($row['C'] ?? ''));
            $tcRaw    = (string)($row['D'] ?? '');
            $tc       = preg_replace('/\D+/', '', $tcRaw);
            $grade    = self::normalizeGrade((string)($row['E'] ?? ''));
            $sect     = strtoupper(trim((string)($row['F'] ?? '')));

            // Tamamen boş satır → atla
            if ($name === '' && $tc === '' && $kurumId === 0 && $kampusId === 0) continue;
            $report['total']++;

            // Validation
            if ($kampusId <= 0)          { $report['errors'][] = "Satır $rowNum: Kampüs ID eksik (B kolonu)."; $report['skipped']++; continue; }
            if (!isset($campusMap[$kampusId])) { $report['errors'][] = "Satır $rowNum: Kampüs ID #$kampusId bulunamadı."; $report['skipped']++; continue; }
            if ($kurumId > 0 && $campusMap[$kampusId] !== $kurumId) {
                $report['errors'][] = "Satır $rowNum: Kampüs #$kampusId, Kurum #$kurumId'a ait değil (gerçek kurum: #{$campusMap[$kampusId]})."; $report['skipped']++; continue;
            }
            if ($name === '')             { $report['errors'][] = "Satır $rowNum: Ad-soyad boş."; $report['skipped']++; continue; }
            if (strlen($tc) !== 11)       { $report['errors'][] = "Satır $rowNum: T.C. 11 haneli olmalı (\"$tcRaw\")."; $report['skipped']++; continue; }
            if ($tc[0] === '0')           { $report['errors'][] = "Satır $rowNum: T.C. 0 ile başlayamaz."; $report['skipped']++; continue; }
            if ($grade === null)          { $report['errors'][] = "Satır $rowNum: Geçersiz sınıf '" . ($row['E'] ?? '') . "'. İzin verilen: " . implode(', ', self::GRADE_LEVELS) . "."; $report['skipped']++; continue; }
            if (!in_array($sect, self::SECTIONS, true)) {
                $report['errors'][] = "Satır $rowNum: Geçersiz şube '$sect'. İzin verilen: " . implode(', ', self::SECTIONS) . "."; $report['skipped']++; continue;
            }
            if (self::tcExists($tc))      { $report['errors'][] = "Satır $rowNum: T.C. $tc zaten kayıtlı, atlandı."; $report['skipped']++; continue; }
            if (self::passwordExists($tc)){ $report['errors'][] = "Satır $rowNum: T.C. başka kullanıcının şifresi olarak kullanılıyor, atlandı."; $report['skipped']++; continue; }

            try {
                $teacherForAssign = self::pickTeacherForCampus($kampusId, (int)$me['id']);
                $pdo->beginTransaction();
                $st = $pdo->prepare("
                    INSERT INTO users (role, full_name, tc, grade_level, section, password, campus_id, is_active, created_by)
                    VALUES ('student', ?, ?, ?, ?, ?, ?, 1, ?)
                ");
                $st->execute([$name, $tc, $grade, $sect, $tc, $kampusId, (int)$me['id']]);
                $studentId = (int)$pdo->lastInsertId();

                if ($totalTests > 0) {
                    $ins = $pdo->prepare("INSERT IGNORE INTO test_assignments (test_id, student_id, teacher_id, status) VALUES (?, ?, ?, 'pending')");
                    foreach ($pdo->query("SELECT id FROM tests")->fetchAll() as $t) {
                        $ins->execute([(int)$t['id'], $studentId, $teacherForAssign]);
                        if ($ins->rowCount() > 0) $report['autoAssigned']++;
                    }
                }
                $pdo->commit();
                $report['imported']++;
            } catch (\PDOException $ex) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                $report['errors'][] = "Satır $rowNum: Kayıt hatası — " . $ex->getMessage();
                $report['skipped']++;
            }
        }

        view('teacher/students/import', [
            'title' => 'Toplu Öğrenci İçe Aktarma', 'me' => $me,
            'report' => $report, 'fname' => $fname,
            'gradeLevels' => self::GRADE_LEVELS, 'sections' => self::SECTIONS,
        ]);
    }

    /** "5 yaş" / "5 YAŞ" / "5  YAŞ" → "5 YAŞ" */
    private static function normalizeGrade(string $raw): ?string {
        $s = trim($raw);
        if ($s === '') return null;
        $s = preg_replace('/\s+/', ' ', $s);
        // Türkçe büyük harf — manuel map (mb_strtoupper Türkçe i için bozuk)
        $up = strtr($s, [
            'ı'=>'I','i'=>'İ','ş'=>'Ş','ğ'=>'Ğ','ü'=>'Ü','ö'=>'Ö','ç'=>'Ç',
            'a'=>'A','b'=>'B','c'=>'C','d'=>'D','e'=>'E','f'=>'F','g'=>'G','h'=>'H',
            'j'=>'J','k'=>'K','l'=>'L','m'=>'M','n'=>'N','o'=>'O','p'=>'P','r'=>'R',
            's'=>'S','t'=>'T','u'=>'U','v'=>'V','y'=>'Y','z'=>'Z',
        ]);
        foreach (self::GRADE_LEVELS as $g) {
            if ($g === $up) return $g;
        }
        return null;
    }

    // -------- Helpers --------

    public static function myCampusId(array $me): ?int {
        $cid = !empty($me['campus_id']) ? (int)$me['campus_id'] : null;
        return $cid > 0 ? $cid : null;
    }

    private static function loadOwned(int $id, array $me): ?array {
        $st = db()->prepare("SELECT * FROM users WHERE id=? AND role='student'");
        $st->execute([$id]);
        $u = $st->fetch();
        if (!$u) return null;
        if ($me['role'] !== 'admin') {
            $myCampus = self::myCampusId($me);
            if (!$myCampus || (int)$u['campus_id'] !== $myCampus) return null;
        }
        return $u;
    }

    private static function campusOptions(): array {
        return db()->query("
            SELECT c.id, c.name AS campus_name, i.name AS institution_name
              FROM campuses c JOIN institutions i ON i.id = c.institution_id
          ORDER BY i.name, c.name
        ")->fetchAll();
    }

    private static function campusExists(int $id): bool {
        $st = db()->prepare("SELECT id FROM campuses WHERE id=?");
        $st->execute([$id]);
        return (bool)$st->fetchColumn();
    }

    private static function pickTeacherForCampus(int $campusId, int $fallback): int {
        $st = db()->prepare("SELECT id FROM users WHERE role='teacher' AND campus_id=? AND is_active=1 ORDER BY id LIMIT 1");
        $st->execute([$campusId]);
        $tid = (int)$st->fetchColumn();
        return $tid > 0 ? $tid : $fallback;
    }

    private static function autoAssignAllTests(int $studentId, int $teacherId): void {
        $tests = db()->query("SELECT id FROM tests")->fetchAll();
        if (!$tests) return;
        $ins = db()->prepare("
            INSERT IGNORE INTO test_assignments (test_id, student_id, teacher_id, status)
            VALUES (?, ?, ?, 'pending')
        ");
        foreach ($tests as $t) $ins->execute([(int)$t['id'], $studentId, $teacherId]);
    }

    private static function cleanTc($raw): string {
        return preg_replace('/\D+/', '', (string)$raw);
    }

    private static function validateBasics(string $name, string $tc, string $grade, string $section): ?string {
        if ($name === '') return 'Ad-soyad gerekli.';
        if (strlen($tc) !== 11) return 'T.C. numarası 11 haneli olmalı.';
        if ($tc[0] === '0') return 'T.C. numarası 0 ile başlayamaz.';
        if ($grade === '' || !in_array($grade, self::GRADE_LEVELS, true)) return 'Sınıf seç.';
        if ($section === '' || !in_array($section, self::SECTIONS, true)) return 'Şube seç.';
        return null;
    }

    private static function tcExists(string $tc, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE tc=? AND id<>? LIMIT 1");
        $st->execute([$tc, $excludeId]);
        return (bool)$st->fetchColumn();
    }

    private static function passwordExists(string $pass, int $excludeId = 0): bool {
        $st = db()->prepare("SELECT id FROM users WHERE password=? AND id<>? LIMIT 1");
        $st->execute([$pass, $excludeId]);
        return (bool)$st->fetchColumn();
    }
}
