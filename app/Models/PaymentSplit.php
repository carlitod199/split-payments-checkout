<?php
declare(strict_types=1);

namespace App\Models;

use App\Support\Database;

final class PaymentSplit
{
    public static function create(array $data): void
    {
        $sql = 'INSERT INTO payment_splits
                (payment_id, producer_id, role, percentual, expected_cents, received_cents, status, created_at, updated_at)
                VALUES
                (:payment_id, :producer_id, :role, :percentual, :expected_cents, 0, "previsto", NOW(), NOW())';
        Database::pdo()->prepare($sql)->execute($data);
    }

    public static function markReceivedByPayment(int $paymentId): void
    {
        // Marks the split as received by mirroring the forecast amount. For
        // exact reconciliation, the Asaas /payments/{id}/splits endpoint can be
        // queried for the real per-wallet values.
        Database::pdo()->prepare(
            'UPDATE payment_splits
             SET status = "recebido", received_cents = expected_cents, updated_at = NOW()
             WHERE payment_id = :pid'
        )->execute([':pid' => $paymentId]);
    }

    public static function byPayment(int $paymentId): array
    {
        $stmt = Database::pdo()->prepare(
            'SELECT s.*, pr.name AS producer_name
             FROM payment_splits s
             JOIN producers pr ON pr.id = s.producer_id
             WHERE s.payment_id = :pid'
        );
        $stmt->execute([':pid' => $paymentId]);
        return $stmt->fetchAll();
    }
}
