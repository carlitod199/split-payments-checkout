<?php
declare(strict_types=1);

use App\Models\Enrollment;
use App\Models\Lesson;
use App\Models\LessonProgress;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Database;
use App\Support\Http;

$config = require dirname(__DIR__, 3) . '/config/payment.php';
Database::init($config['db']);

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    Http::json(['ok' => false, 'error' => 'Método não permitido.'], 405);
}

Auth::start();
if (!Auth::check()) {
    Http::json(['ok' => false, 'error' => 'Não autenticado.'], 401);
}

$body = Http::body();
$csrf = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['_csrf'] ?? null);
if (!Csrf::check(is_string($csrf) ? $csrf : null)) {
    Http::json(['ok' => false, 'error' => 'CSRF inválido.'], 419);
}

$userId   = (int) Auth::id();
$lessonId = (int) ($body['lesson_id'] ?? 0);
$completed = !empty($body['completed']);
$watchedIn = isset($body['watched_seconds']) ? (int) $body['watched_seconds'] : null;

$lesson = $lessonId > 0 ? Lesson::find($lessonId) : null;
if ($lesson === null) {
    Http::json(['ok' => false, 'error' => 'Aula não encontrada.'], 404);
}

$courseId = (int) $lesson['course_id'];
$duration = (int) $lesson['duration_seconds'];

// Segurança: só salva se o aluno tem matrícula ativa NESTE curso.
if (!Enrollment::isActive($userId, $courseId)) {
    Http::json(['ok' => false, 'error' => 'Sem acesso a este curso.'], 403);
}

// watched: se concluiu, vale a duração; senão usa o enviado (ou 0).
$watched = $completed ? $duration : max(0, $watchedIn ?? 0);
LessonProgress::save($userId, $lessonId, $courseId, $watched, $completed);

// Recalcula o progresso do curso para a UI atualizar.
$lessons = Lesson::forCourse($courseId);
$map = LessonProgress::mapForUserCourse($userId, $courseId);
$total = count($lessons);
$done = 0;
foreach ($lessons as $l) {
    $p = $map[(int) $l['id']] ?? null;
    $dur = (int) $l['duration_seconds'];
    $w = ($p && (int) $p['completed'] === 1) ? $dur : (int) ($p['watched_seconds'] ?? 0);
    if ($dur > 0 && $w >= $dur) {
        $done++;
    }
}

Http::json([
    'ok'        => true,
    'lesson_id' => $lessonId,
    'completed' => $completed,
    'course'    => [
        'lessons_total' => $total,
        'lessons_done'  => $done,
        'progress'      => $total ? (int) round($done / $total * 100) : 0,
    ],
]);
