<?php
declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\User;
use App\Support\Auth;

/**
 * Reusable authentication core, free of any HTTP concerns.
 * Used by AuthController (web) and by the webhook-driven access grant
 * (create the user, then set the password through a token).
 */
final class AuthService
{
    public function __construct(private array $config) {}

    /** Validates credentials. Returns the user row, or null. */
    public function attempt(string $email, string $password): ?array
    {
        $user = User::findByEmail($email);
        if ($user === null
            || $user['status'] !== 'active'
            || empty($user['password_hash'])) {
            return null;
        }
        if (!password_verify($password, (string) $user['password_hash'])) {
            return null;
        }
        return $user;
    }

    /** Creates the session and records the last login timestamp. */
    public function completeLogin(array $user): void
    {
        Auth::login($user);
        User::updateLastLogin((int) $user['id']);
    }

    /** Sets or updates the password (bcrypt). */
    public function setPassword(int $userId, string $plainPassword): void
    {
        User::setPasswordHash($userId, password_hash($plainPassword, PASSWORD_BCRYPT));
    }
}
