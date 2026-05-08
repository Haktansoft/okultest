<?php
declare(strict_types=1);

// Hem `public/index.php` (yerel `php -S`) hem de `deploy/public_html/index.php`
// (cPanel) buradan beslenir. Composer autoload'u önceden gelmiş olmalı.

use App\Router;

if (!class_exists(Router::class)) {
    require __DIR__ . '/router.php';
}

// Çekirdek dosyalar (composer "files" autoload zaten yükler ama emin olalım)
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/pdf.php';

foreach (glob(__DIR__ . '/controllers/*.php') as $cf) {
    require_once $cf;
}

App\startSession();
if ($_SERVER['REQUEST_METHOD'] === 'POST') App\csrfCheck();

$r = new Router();

// === Public ===
$r->any('/', function () {
    $u = App\user();
    if (!$u) App\redirect('/login');
    App\redirect(match ($u['role']) {
        'admin'   => '/admin',
        'teacher' => '/teacher',
        'student' => '/student',
        default   => '/login',
    });
});
$r->get ('/login',  ['App\\Controllers\\AuthController', 'showLogin']);
$r->post('/login',  ['App\\Controllers\\AuthController', 'login']);
$r->post('/logout', ['App\\Controllers\\AuthController', 'logout']);

$r->get ('/media/{id}', ['App\\Controllers\\AdminMediaController', 'serve']);

// === Admin ===
$r->get('/admin', function () {
    $u = App\requireRole('admin');
    $stats = [
        'categories' => (int)App\db()->query("SELECT COUNT(*) FROM categories")->fetchColumn(),
        'questions'  => (int)App\db()->query("SELECT COUNT(*) FROM questions")->fetchColumn(),
        'tests'      => (int)App\db()->query("SELECT COUNT(*) FROM tests")->fetchColumn(),
        'media'      => (int)App\db()->query("SELECT COUNT(*) FROM media")->fetchColumn(),
        'teachers'   => (int)App\db()->query("SELECT COUNT(*) FROM users WHERE role='teacher'")->fetchColumn(),
        'students'   => (int)App\db()->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn(),
    ];
    App\view('admin/dashboard/index', ['title' => 'Yönetim', 'me' => $u, 'stats' => $stats]);
});

$r->get ('/admin/categories',                   ['App\\Controllers\\AdminCategoryController', 'index']);
$r->get ('/admin/categories/new',               ['App\\Controllers\\AdminCategoryController', 'createForm']);
$r->post('/admin/categories',                   ['App\\Controllers\\AdminCategoryController', 'create']);
$r->get ('/admin/categories/{id}/edit',         ['App\\Controllers\\AdminCategoryController', 'editForm']);
$r->post('/admin/categories/{id}/update',       ['App\\Controllers\\AdminCategoryController', 'update']);
$r->post('/admin/categories/{id}/delete',       ['App\\Controllers\\AdminCategoryController', 'delete']);

$r->get ('/admin/media',                        ['App\\Controllers\\AdminMediaController', 'index']);
$r->get ('/admin/media.json',                   ['App\\Controllers\\AdminMediaController', 'listJson']);
$r->post('/admin/media/upload',                 ['App\\Controllers\\AdminMediaController', 'upload']);
$r->post('/admin/media/{id}/delete',            ['App\\Controllers\\AdminMediaController', 'delete']);

$r->get ('/admin/questions',                    ['App\\Controllers\\AdminQuestionController', 'index']);
$r->get ('/admin/questions/new',                ['App\\Controllers\\AdminQuestionController', 'createForm']);
$r->post('/admin/questions',                    ['App\\Controllers\\AdminQuestionController', 'create']);
$r->get ('/admin/questions/{id}/edit',          ['App\\Controllers\\AdminQuestionController', 'editForm']);
$r->post('/admin/questions/{id}/update',        ['App\\Controllers\\AdminQuestionController', 'update']);
$r->post('/admin/questions/{id}/delete',        ['App\\Controllers\\AdminQuestionController', 'delete']);

$r->get ('/admin/tests',                        ['App\\Controllers\\AdminTestController', 'index']);
$r->get ('/admin/tests/new',                    ['App\\Controllers\\AdminTestController', 'createForm']);
$r->post('/admin/tests',                        ['App\\Controllers\\AdminTestController', 'create']);
$r->get ('/admin/tests/{id}/edit',              ['App\\Controllers\\AdminTestController', 'editForm']);
$r->post('/admin/tests/{id}/update',            ['App\\Controllers\\AdminTestController', 'update']);
$r->post('/admin/tests/{id}/delete',            ['App\\Controllers\\AdminTestController', 'delete']);
$r->get ('/admin/tests/{id}/questions',         ['App\\Controllers\\AdminTestController', 'manageQuestions']);
$r->post('/admin/tests/{id}/questions',         ['App\\Controllers\\AdminTestController', 'saveQuestions']);
$r->get ('/admin/tests/{id}/pdf',               ['App\\Controllers\\AdminTestController', 'pdf']);

$r->get ('/admin/teachers',                     ['App\\Controllers\\AdminTeacherController', 'index']);
$r->get ('/admin/teachers/new',                 ['App\\Controllers\\AdminTeacherController', 'createForm']);
$r->post('/admin/teachers',                     ['App\\Controllers\\AdminTeacherController', 'create']);
$r->post('/admin/teachers/{id}/reset',          ['App\\Controllers\\AdminTeacherController', 'reset']);
$r->post('/admin/teachers/{id}/toggle',         ['App\\Controllers\\AdminTeacherController', 'toggle']);

// === Teacher ===
$r->get ('/teacher', function () {
    $u = App\requireRole('teacher', 'admin');
    $pdo = App\db();
    $students    = (int)$pdo->query("SELECT COUNT(*) FROM users WHERE role='student'")->fetchColumn();
    $assignments = (int)$pdo->query("SELECT COUNT(*) FROM test_assignments")->fetchColumn();
    $needsPhys   = (int)$pdo->query("SELECT COUNT(*) FROM test_assignments WHERE status='needs_physical'")->fetchColumn();
    $completed   = (int)$pdo->query("SELECT COUNT(*) FROM test_assignments WHERE status='completed'")->fetchColumn();
    App\view('teacher/dashboard/index', [
        'title' => $u['role']==='admin' ? 'Sınıf Özeti' : 'Öğretmen Paneli', 'me' => $u,
        'stats' => ['students' => $students, 'assignments' => $assignments, 'needs_physical' => $needsPhys, 'completed' => $completed],
    ]);
});

$r->get ('/teacher/students',                ['App\\Controllers\\TeacherStudentController', 'index']);
$r->get ('/teacher/students/new',            ['App\\Controllers\\TeacherStudentController', 'createForm']);
$r->post('/teacher/students',                ['App\\Controllers\\TeacherStudentController', 'create']);
$r->post('/teacher/students/{id}/reset',     ['App\\Controllers\\TeacherStudentController', 'reset']);
$r->post('/teacher/students/{id}/toggle',    ['App\\Controllers\\TeacherStudentController', 'toggle']);

$r->get ('/teacher/assignments',             ['App\\Controllers\\TeacherAssignmentController', 'index']);
$r->get ('/teacher/assignments/new',         ['App\\Controllers\\TeacherAssignmentController', 'createForm']);
$r->post('/teacher/assignments',             ['App\\Controllers\\TeacherAssignmentController', 'create']);
$r->post('/teacher/assignments/{id}/delete', ['App\\Controllers\\TeacherAssignmentController', 'delete']);
$r->get ('/teacher/assignments/{id}/bulk',   ['App\\Controllers\\TeacherAssignmentController', 'bulkForm']);
$r->post('/teacher/assignments/{id}/bulk',   ['App\\Controllers\\TeacherAssignmentController', 'bulkSave']);

$r->get ('/teacher/results',                 ['App\\Controllers\\TeacherResultController', 'index']);
$r->get ('/teacher/results/{id}',            ['App\\Controllers\\TeacherResultController', 'show']);
$r->get ('/teacher/results/{id}/pdf',        ['App\\Controllers\\TeacherResultController', 'pdf']);
$r->get ('/teacher/results/{id}/summary-pdf',['App\\Controllers\\TeacherResultController', 'summaryPdf']);
$r->get ('/teacher/incomplete-pdf/{id}',     ['App\\Controllers\\TeacherResultController', 'incompletePdf']);

$r->get ('/teacher/physical',                ['App\\Controllers\\TeacherPhysicalController', 'index']);
$r->get ('/teacher/physical/{id}',           ['App\\Controllers\\TeacherPhysicalController', 'show']);
$r->post('/teacher/physical/{id}',           ['App\\Controllers\\TeacherPhysicalController', 'save']);

// === Student ===
$r->get ('/student',                                ['App\\Controllers\\StudentDashboardController', 'index']);
$r->get ('/student/tests/{id}/intro',               ['App\\Controllers\\StudentTestController', 'intro']);
$r->post('/student/tests/{id}/start',               ['App\\Controllers\\StudentTestController', 'start']);
$r->get ('/student/tests/{id}/run',                 ['App\\Controllers\\StudentTestController', 'run']);
$r->get ('/student/tests/{id}/bulk',                ['App\\Controllers\\StudentTestController', 'bulk']);
$r->post('/student/tests/{id}/autosave',            ['App\\Controllers\\StudentTestController', 'autosave']);
$r->post('/student/tests/{id}/event',               ['App\\Controllers\\StudentTestController', 'logEvent']);
$r->post('/student/tests/{id}/submit',              ['App\\Controllers\\StudentTestController', 'submit']);
$r->get ('/student/tests/{id}/finished',            ['App\\Controllers\\StudentTestController', 'finished']);

$r->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);
