<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Lesson
{
    /** The published lessons of a course, joined with the module title, in display order. */
    public static function forCourse(int $courseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT l.*, m.title AS module_title, m.order_index AS module_order
               FROM lessons l
               JOIN course_modules m ON m.id = l.module_id
              WHERE l.course_id = :cid AND l.status = "published"
              ORDER BY m.order_index, m.id, l.order_index, l.id'
        );
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM lessons WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function create(array $d): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO lessons
                (course_id, module_id, title, description, video_url, video_provider,
                 thumbnail, duration_seconds, order_index, status)
             VALUES
                (:course_id, :module_id, :title, :description, :video_url, :video_provider,
                 :thumbnail, :duration_seconds, :order_index, :status)'
        );
        $stmt->execute(self::params($d));
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE lessons SET
                module_id=:module_id, title=:title, description=:description,
                video_url=:video_url, video_provider=:video_provider, thumbnail=:thumbnail,
                duration_seconds=:duration_seconds, order_index=:order_index, status=:status,
                updated_at=NOW()
             WHERE id=:id'
        );
        $params = self::params($d);
        unset($params[':course_id']);
        $params[':id'] = $id;
        $stmt->execute($params);
    }

    public static function delete(int $id): void
    {
        $stmt = Database::pdo()->prepare('DELETE FROM lessons WHERE id = :id');
        $stmt->execute([':id' => $id]);
    }

    private static function params(array $d): array
    {
        return [
            ':course_id'        => $d['course_id'] ?? null,
            ':module_id'        => $d['module_id'],
            ':title'            => $d['title'],
            ':description'      => $d['description'] ?? null,
            ':video_url'        => $d['video_url'] ?? null,
            ':video_provider'   => $d['video_provider'] ?? 'other',
            ':thumbnail'        => $d['thumbnail'] ?? null,
            ':duration_seconds' => (int) ($d['duration_seconds'] ?? 0),
            ':order_index'      => (int) ($d['order_index'] ?? 0),
            ':status'           => $d['status'] ?? 'published',
        ];
    }
}
