<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Producer
{
    public static function all(): array
    {
        return Database::pdo()->query(
            'SELECT * FROM producers ORDER BY type, name'
        )->fetchAll();
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM producers WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }
}
