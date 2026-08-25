<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class Payment
{
    public static function create(array $data): int
    {
        $sql = 'INSERT INTO payments
                (internal_id, product_id, customer_name, customer_email, customer_phone,
                 customer_doc, customer_external_id, gross_cents, fee_cents, net_cents,
                 method, status, idempotency_key, created_at, updated_at)
                VALUES
                (:internal_id, :product_id, :customer_name, :customer_email, :customer_phone,
                 :customer_doc, :customer_external_id, :gross_cents, :fee_cents, :net_cents,
                 :method, :status, :idempotency_key, NOW(), NOW())';
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute($data);
        return (int) Database::pdo()->lastInsertId();
    }

    public static function findByInternalId(string $internalId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE internal_id = :id');
        $stmt->execute([':id' => $internalId]);
        return $stmt->fetch() ?: null;
    }

    public static function findByExternalId(string $externalId): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE external_id = :id');
        $stmt->execute([':id' => $externalId]);
        return $stmt->fetch() ?: null;
    }

    public static function find(int $id): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE id = :id');
        $stmt->execute([':id' => $id]);
        return $stmt->fetch() ?: null;
    }

    public static function markAccessGranted(int $id): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payments SET access_granted_at = NOW(), updated_at = NOW()
              WHERE id = :id AND access_granted_at IS NULL'
        );
        $stmt->execute([':id' => $id]);
    }

    public static function findByIdempotency(string $key): ?array
    {
        $stmt = Database::pdo()->prepare('SELECT * FROM payments WHERE idempotency_key = :k');
        $stmt->execute([':k' => $key]);
        return $stmt->fetch() ?: null;
    }

    public static function setExternalId(int $id, string $externalId): void
    {
        $stmt = Database::pdo()->prepare(
            'UPDATE payments SET external_id = :ext, updated_at = NOW() WHERE id = :id'
        );
        $stmt->execute([':ext' => $externalId, ':id' => $id]);
    }

    public static function updateStatus(string $externalId, string $status, ?int $netCents = null): void
    {
        $paidAt = $status === 'pago' ? ', paid_at = NOW()' : '';
        $sql = "UPDATE payments
                SET status = :status,
                    net_cents = COALESCE(:net, net_cents),
                    updated_at = NOW() {$paidAt}
                WHERE external_id = :ext";
        $stmt = Database::pdo()->prepare($sql);
        $stmt->execute([':status' => $status, ':net' => $netCents, ':ext' => $externalId]);
    }

    public static function all(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT pay.*, prod.name AS product_name
             FROM payments pay
             JOIN products prod ON prod.id = pay.product_id
             ORDER BY pay.created_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
