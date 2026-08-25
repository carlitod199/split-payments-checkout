<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class CourseModule
{
    /** The modules of a course, in display order. */
    public static function forCourse(int $courseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM course_modules WHERE course_id = :cid ORDER BY order_index, id'
        );
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM course_modules WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(int $courseId, string $title, ?string $description, int $orderIndex): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO course_modules (course_id, title, description, order_index)
             VALUES (:cid, :title, :desc, :ord)'
        );
        $stmt->execute([':cid' => $courseId, ':title' => $title, ':desc' => $description, ':ord' => $orderIndex]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, string $title, ?string $description, int $orderIndex): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE course_modules SET title=:title, description=:desc, order_index=:ord, updated_at=NOW() WHERE id=:id'
        );
        $stmt->execute([':title' => $title, ':desc' => $description, ':ord' => $orderIndex, ':id' => $id]);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM course_modules WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }
}
