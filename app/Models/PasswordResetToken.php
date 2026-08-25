<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/**
 * Password creation / reset tokens.
 *
 * The RAW high-entropy token only ever appears in the link sent to the user.
 * The database stores nothing but its SHA-256 hash, which is still indexable
 * for lookups.
 */
final class PasswordResetToken
{
    /** Generates a token, stores only its hash and returns the raw value for the link. */
    public static function issue(int $userId, int $ttlHours = 24): string
    {
        $raw  = bin2hex(random_bytes(32));
        $hash = hash('sha256', $raw);

        $stmt = Database::pdo()->prepare(
            'INSERT INTO password_reset_tokens (user_id, token_hash, expires_at)
             VALUES (:uid, :hash, :exp)'
        );
        $stmt->execute([
            ':uid'  => $userId,
            ':hash' => $hash,
            ':exp'  => date('Y-m-d H:i:s', time() + $ttlHours * 3600),
        ]);

        return $raw;
    }

    /** Returns the token plus the user data when it is valid (unused and unexpired); null otherwise. */
    public static function findValid(string $rawToken): ?array
    {
        $hash = hash('sha256', $rawToken);
        $stmt = Database::pdo()->prepare(
            'SELECT t.id, t.user_id, u.name, u.email, u.role
               FROM password_reset_tokens t
               JOIN users u ON u.id = t.user_id
              WHERE t.token_hash = :hash
                AND t.used_at IS NULL
                AND t.expires_at > NOW()
              LIMIT 1'
        );
        $stmt->execute([':hash' => $hash]);
        return $stmt->fetch() ?: null;
    }

    public static function consume(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE password_reset_tokens SET used_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function purgeExpired(): void
    {
        Database::pdo()->query(
            'DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL'
        );
    }
}
