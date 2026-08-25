<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class User
{
    public static function findByEmail(string $email): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE email = :email LIMIT 1');
        $stmt->execute([':email' => strtolower(trim($email))]);
        return $stmt->fetch() ?: null;
    }

    public static function findById(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM users WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function emailExists(string $email): bool
    {
        return self::findByEmail($email) !== null;
    }

    /** Lists users, optionally filtered by role, with their active enrollment count. */
    public static function listAll(?string $role = null, int $limit = 300): array
    {
        $where = $role !== null ? 'WHERE u.role = :role' : '';
        $sql = "SELECT u.id, u.name, u.email, u.role, u.status, u.password_hash, u.last_login_at,
                       (SELECT COUNT(*) FROM enrollments e WHERE e.user_id = u.id AND e.status='active') AS active_enrollments
                  FROM users u $where
                 ORDER BY u.created_at DESC
                 LIMIT :lim";
        $stmt = Database::pdo()->prepare($sql);
        if ($role !== null) {
            $stmt->bindValue(':role', $role);
        }
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    /** Creates the user and returns its id. password_hash stays NULL until the student sets a password. */
    public static function create(string $name, string $email, string $role = 'student'): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO users (name, email, role, status) VALUES (:name, :email, :role, :status)'
        );
        $stmt->execute([
            ':name'   => trim($name),
            ':email'  => strtolower(trim($email)),
            ':role'   => $role,
            ':status' => 'active',
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function setPasswordHash(int $id, string $hash): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET password_hash = :h WHERE id = :id'
        );
        $stmt->execute([':h' => $hash, ':id' => $id]);
    }

    public static function updateLastLogin(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE users SET last_login_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':id' => $id]);
    }
}
