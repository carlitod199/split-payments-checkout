<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Product
{
    /** A product joined with both producers, looked up by its checkout slug. */
    public static function findBySlug(string $slug): ?array
    {
        $sql = 'SELECT
                    p.*,
                    pp.name   AS principal_name,
                    pp.wallet_id AS principal_wallet_id,
                    cp.name   AS coproducer_name,
                    cp.wallet_id AS coproducer_wallet_id
                FROM products p
                JOIN producers pp ON pp.id = p.principal_producer_id
                JOIN producers cp ON cp.id = p.coproducer_producer_id
                WHERE p.checkout_slug = :slug AND p.status = "ativo"
                LIMIT 1';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':slug' => $slug]);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT p.*, pp.name AS principal_name, cp.name AS coproducer_name
             FROM products p
             JOIN producers pp ON pp.id = p.principal_producer_id
             JOIN producers cp ON cp.id = p.coproducer_producer_id
             ORDER BY p.created_at DESC'
        )->fetchAll();
    }
}
