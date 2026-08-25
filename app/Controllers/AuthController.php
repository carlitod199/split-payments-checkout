<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\PasswordResetToken;
use App\Models\User;
use App\Services\Auth\AuthService;
use App\Support\Auth;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\RateLimiter;

/**
 * Handles the authentication forms. The pages under /public stay thin: they
 * call these handlers and simply render the state that comes back.
 *
 * Language convention: the `error` strings returned here are customer-facing
 * UI copy, rendered as-is by the Portuguese login and set-password pages, so
 * they stay in Portuguese. Developer-facing text elsewhere is English.
 */
final class AuthController
{
    private AuthService $auth;

    public function __construct(private array $config)
    {
        $this->auth = new AuthService($config);
    }

    /** login.php - redirects on success, otherwise returns the state for the view. */
    public function handleLogin(): array
    {
        Auth::start();
        $next = $this->safeNext((string) ($_GET['next'] ?? $_POST['next'] ?? ''));

        if (Auth::check()) {
            $this->redirect($next !== '' ? $next : $this->home(Auth::role() ?? 'student'));
        }

        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Sessão expirada. Recarregue a página e tente novamente.';
            } elseif (RateLimiter::tooMany('login:' . Http::clientIp(), $this->config['rate_limit']['max'], $this->config['rate_limit']['window'])) {
                $error = 'Muitas tentativas. Aguarde alguns instantes.';
            } else {
                $user = $this->auth->attempt((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
                if ($user !== null) {
                    $this->auth->completeLogin($user);
                    $this->redirect($next !== '' ? $next : $this->home($user['role']));
                }
                $error = 'E-mail ou senha inválidos.';
            }
        }

        return ['error' => $error, 'next' => $next];
    }

    public function handleLogout(): void
    {
        Auth::logout();
        $this->redirect('login.php');
    }

    /** definir-senha.php (set password) - validates the token, stores the new password and logs the user in. */
    public function handleSetPassword(): array
    {
        Auth::start();
        $token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
        $row = $token !== '' ? PasswordResetToken::findValid($token) : null;

        if ($row === null) {
            return ['valid' => false, 'error' => 'Link inválido ou expirado. Solicite um novo.', 'token' => $token, 'name' => ''];
        }

        $error = '';
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            if (!Csrf::check($_POST['_csrf'] ?? null)) {
                $error = 'Sessão expirada. Recarregue a página e tente novamente.';
            } else {
                $p1 = (string) ($_POST['password'] ?? '');
                $p2 = (string) ($_POST['password_confirm'] ?? '');
                if (strlen($p1) < 8) {
                    $error = 'A senha precisa ter ao menos 8 caracteres.';
                } elseif ($p1 !== $p2) {
                    $error = 'As senhas não conferem.';
                } else {
                    $this->auth->setPassword((int) $row['user_id'], $p1);
                    PasswordResetToken::consume((int) $row['id']);
                    $user = User::findById((int) $row['user_id']);
                    if ($user !== null) {
                        $this->auth->completeLogin($user);
                        $this->redirect($this->home($user['role']));
                    }
                }
            }
        }

        return ['valid' => true, 'error' => $error, 'token' => $token, 'name' => (string) $row['name']];
    }

    private function home(string $role = 'student'): string
    {
        // Paths are relative to /public.
        return $role === 'admin' ? '../admin/index.php' : 'aluno/index.php';
    }

    /** Only relative local paths are accepted as a post-login destination. */
    private function safeNext(string $next): string
    {
        if ($next === '' || str_starts_with($next, '//') || preg_match('~^[a-z]+://~i', $next)) {
            return '';
        }
        return $next;
    }

    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }
}
