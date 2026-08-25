<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

/** Product -> course(s) bridge. Defines what each purchase unlocks. */
final class ProductCourse
{
    /** The courses linked to a product (full courses rows). */
    public static function coursesForProduct(int $productId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT c.*
               FROM product_courses pc
               JOIN courses c ON c.id = pc.course_id
              WHERE pc.product_id = :pid
              ORDER BY c.title'
        );
        $stmt->execute([':pid' => $productId]);
        return $stmt->fetchAll();
    }

    public static function link(int $productId, int $courseId): void
    {
        $stmt = Database::pdo()->prepare(
            'INSERT IGNORE INTO product_courses (product_id, course_id) VALUES (:p, :c)'
        );
        $stmt->execute([':p' => $productId, ':c' => $courseId]);
    }

    public static function unlink(int $productId, int $courseId): void
    {
        $stmt = Database::pdo()->prepare(
            'DELETE FROM product_courses WHERE product_id = :p AND course_id = :c'
        );
        $stmt->execute([':p' => $productId, ':c' => $courseId]);
    }

    /** The products linked to a course, with their names. */
    public static function productsForCourse(int $courseId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT p.id, p.name, p.checkout_slug
               FROM product_courses pc
               JOIN products p ON p.id = pc.product_id
              WHERE pc.course_id = :cid
              ORDER BY p.name'
        );
        $stmt->execute([':cid' => $courseId]);
        return $stmt->fetchAll();
    }
}
