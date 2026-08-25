<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Additional student details (1:1 with users). */
final class Student
{
    public static function findByUser(int $userId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM students WHERE user_id = :uid LIMIT 1');
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetch() ?: null;
    }

    /** Creates or updates the student record. Only non-empty values overwrite existing ones. */
    public static function upsert(int $userId, ?string $phone, ?string $document): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO students (user_id, phone, document)
             VALUES (:uid, :phone, :doc)
             ON DUPLICATE KEY UPDATE
                phone    = COALESCE(NULLIF(VALUES(phone), ""), phone),
                document = COALESCE(NULLIF(VALUES(document), ""), document),
                updated_at = NOW()'
        );
        $stmt->execute([
            ':uid'   => $userId,
            ':phone' => $phone ?? '',
            ':doc'   => $document ?? '',
        ]);
    }
}
