<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Session guard. Keeps only the bare minimum in the session (id, name, e-mail,
 * role) so that a database round trip is not needed on every request. Full
 * records are loaded from the Models when they are actually required.
 */
final class Auth
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    /** Completes the login: regenerates the session id (anti session fixation) and stores the user. */
    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true);
        $_SESSION['user_id']    = (int) $user['id'];
        $_SESSION['user_name']  = (string) ($user['name'] ?? '');
        $_SESSION['user_email'] = (string) ($user['email'] ?? '');
        $_SESSION['user_role']  = (string) ($user['role'] ?? 'student');
    }

    public static function logout(): void
    {
        self::start();
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
        }
        session_destroy();
    }

    public static function check(): bool
    {
        self::start();
        return !empty($_SESSION['user_id']);
    }

    public static function id(): ?int
    {
        return self::check() ? (int) $_SESSION['user_id'] : null;
    }

    public static function role(): ?string
    {
        return self::check() ? ($_SESSION['user_role'] ?? null) : null;
    }

    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }
        return [
            'id'    => (int) $_SESSION['user_id'],
            'name'  => $_SESSION['user_name'] ?? '',
            'email' => $_SESSION['user_email'] ?? '',
            'role'  => $_SESSION['user_role'] ?? 'student',
        ];
    }

    /** Redirects to the login page when the visitor is not authenticated. */
    public static function requireLogin(string $loginUrl): void
    {
        if (!self::check()) {
            header('Location: ' . $loginUrl);
            exit;
        }
    }

    /** Requires a specific role; otherwise redirects to $denyUrl. */
    public static function requireRole(string $role, string $denyUrl): void
    {
        if (self::role() !== $role) {
            header('Location: ' . $denyUrl);
            exit;
        }
    }
}
