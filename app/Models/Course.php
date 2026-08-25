<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Course
{
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT c.*, pr.name AS producer_name,
                    (SELECT COUNT(*) FROM lessons l WHERE l.course_id = c.id) AS lessons_count
               FROM courses c
               JOIN producers pr ON pr.id = c.producer_id
              ORDER BY c.created_at DESC'
        )->fetchAll();
    }

    public static function create(array $d): int
    {
        $stmt = Database::pdo()->prepare(
            'INSERT INTO courses (title, slug, description, cover_image, banner_image, producer_id, status)
             VALUES (:title, :slug, :description, :cover_image, :banner_image, :producer_id, :status)'
        );
        $stmt->execute([
            ':title'        => $d['title'],
            ':slug'         => $d['slug'],
            ':description'  => $d['description'] ?? null,
            ':cover_image'  => $d['cover_image'] ?? null,
            ':banner_image' => $d['banner_image'] ?? null,
            ':producer_id'  => $d['producer_id'],
            ':status'       => $d['status'] ?? 'draft',
        ]);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function update(int $id, array $d): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE courses SET title=:title, slug=:slug, description=:description,
                    cover_image=:cover_image, banner_image=:banner_image,
                    producer_id=:producer_id, status=:status, updated_at=NOW()
             WHERE id=:id'
        );
        $stmt->execute([
            ':title'        => $d['title'],
            ':slug'         => $d['slug'],
            ':description'  => $d['description'] ?? null,
            ':cover_image'  => $d['cover_image'] ?? null,
            ':banner_image' => $d['banner_image'] ?? null,
            ':producer_id'  => $d['producer_id'],
            ':status'       => $d['status'] ?? 'draft',
            ':id'           => $id,
        ]);
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM courses WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function findBySlug(string $slug): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM courses WHERE slug = :s LIMIT 1');
        $stmt->execute([':s' => $slug]);
        return $stmt->fetch() ?: null;
    }
}
