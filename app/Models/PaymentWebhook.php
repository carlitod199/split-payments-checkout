<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;
use PDOException;

final class PaymentWebhook
{
    /**
     * Attempts to record the webhook. Returns false when the idempotency_key
     * already exists, meaning it is a duplicate delivery that must not be
     * processed again.
     */
    public static function record(string $gateway, string $event, string $idempotencyKey, array $payload): bool
    {
        try {
            $sql = 'INSERT INTO payment_webhooks
                    (gateway, event, idempotency_key, payload, process_status, received_at)
                    VALUES (:g, :e, :k, :p, "recebido", NOW())';
            Database::pdo()->prepare($sql)->execute([
                ':g' => $gateway,
                ':e' => $event,
                ':k' => $idempotencyKey,
                ':p' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            ]);
            return true;
        } catch (PDOException $e) {
            // 23000 = unique constraint violation (already processed)
            if ($e->getCode() === '23000') {
                return false;
            }
            throw $e;
        }
    }

    public static function markProcessed(string $idempotencyKey, string $status): void
    {
        Database::pdo()->prepare(
            'UPDATE payment_webhooks SET process_status = :s WHERE idempotency_key = :k'
        )->execute([':s' => $status, ':k' => $idempotencyKey]);
    }

    public static function all(int $limit = 200): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT * FROM payment_webhooks ORDER BY received_at DESC LIMIT :lim'
        );
        $stmt->bindValue(':lim', $limit, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    }
}
