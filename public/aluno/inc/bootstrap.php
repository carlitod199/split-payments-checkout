<?php
/**
 * Bootstrap das páginas /aluno.
 *
 * Acesso restrito: exige sessão de aluno autenticado. Sem login -> redireciona
 * para o login. Logado sem matrícula ativa -> tela "sem cursos". Os dados vêm
 * sempre do banco via StudentDashboardService (sem modo demonstração).
 */
declare(strict_types=1);

require __DIR__ . '/helpers.php';

// Autoload + .env: habilita App\Support\* e os Services.
$config = require dirname(__DIR__, 3) . '/config/payment.php';

use App\Support\Auth;
use App\Support\Database;
use App\Services\Student\StudentDashboardService;

Auth::start();

// 1) Exige login.
if (!Auth::check()) {
    $next = ltrim((string) ($_SERVER['REQUEST_URI'] ?? ''), '/');
    // mantém só o nome do arquivo + query, relativo a /public
    $next = preg_replace('~^.*/public/~', '', $next);
    header('Location: ../login.php' . ($next ? '?next=' . urlencode($next) : ''));
    exit;
}

$currentUser = Auth::user();
Database::init($config['db']);

$slug = isset($_GET['slug']) ? (string) $_GET['slug'] : null;
$DATA = StudentDashboardService::forUser((int) $currentUser['id'], $slug);

// 2) Logado, mas sem matrícula ativa -> estado vazio (sem demo).
if ($DATA === null) {
    require __DIR__ . '/no_access.php';
    render_no_access($currentUser);
    exit;
}

$student = $DATA['student'];
$course  = $DATA['course'];
$modules = $DATA['modules'];
$lessons = $DATA['lessons'];
$continueLesson = $DATA['continue'];

/** Caminho base para links internos da área do aluno. */
function aluno_url(string $path = ''): string
{
    return $path === '' ? 'index.php' : $path;
}
