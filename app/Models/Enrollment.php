<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Enrollment
{
    /**
     * Creates (or reactivates) the student's enrollment in the course. Made
     * idempotent by the UNIQUE (user_id, course_id) key. Returns true when the
     * enrollment was created by this call.
     */
    public static function grant(int $userId, int $courseId, ?int $paymentId): bool
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO enrollments (user_id, course_id, payment_id, status, access_starts_at)
             VALUES (:uid, :cid, :pid, "active", NOW())
             ON DUPLICATE KEY UPDATE
                status = "active",
                payment_id = COALESCE(payment_id, VALUES(payment_id)),
                updated_at = NOW()'
        );
        $stmt->execute([':uid' => $userId, ':cid' => $courseId, ':pid' => $paymentId]);
        // rowCount(): 1 = fresh insert, 2 = update (MySQL), 0 = nothing changed
        return $stmt->rowCount() === 1;
    }

    public static function activeForUser(int $userId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, c.title, c.slug
               FROM enrollments e
               JOIN courses c ON c.id = e.course_id
              WHERE e.user_id = :uid AND e.status = "active"
              ORDER BY e.created_at DESC'
        );
        $stmt->execute([':uid' => $userId]);
        return $stmt->fetchAll();
    }

    public static function isActive(int $userId, int $courseId): bool
    {
        $stmt = Database::pdo()->prepare(
            'SELECT 1 FROM enrollments
              WHERE user_id = :uid AND course_id = :cid AND status = "active" LIMIT 1'
        );
        $stmt->execute([':uid' => $userId, ':cid' => $courseId]);
        return (bool) $stmt->fetchColumn();
    }

    /** Lists every enrollment, joined with student and course, for the admin panel. */
    public static function listAll(int $limit = 300): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT e.*, u.name AS user_name, u.email AS user_email, c.title AS course_title
               FROM enrollments e
               JOIN users u   ON u.id = e.user_id
               JOIN courses c ON c.id = e.course_id
              ORDER BY e.created_at DESC
              LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }

    public static function setStatus(int $id, string $status): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE enrollments SET status = :s, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':s' => $status, ':id' => $id]);
    }
}
