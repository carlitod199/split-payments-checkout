<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Simple per-IP rate limiter backed by the rate_limits table.
 * Enough to protect the checkout against basic flooding.
 */
final class RateLimiter
{
    public static function tooMany(string $key, int $max, int $windowSeconds): bool
    {
        $pdo = Database::pdo();
        $now = time();
        $windowStart = $now - $windowSeconds;

        $pdo->prepare('DELETE FROM rate_limits WHERE created_at < :ws')
            ->execute([':ws' => date('Y-m-d H:i:s', $windowStart)]);

        $stmt = $pdo->prepare(
            'SELECT COUNT(*) AS c FROM rate_limits WHERE rl_key = :k AND created_at >= :ws'
        );
        $stmt->execute([':k' => $key, ':ws' => date('Y-m-d H:i:s', $windowStart)]);
        $count = (int) $stmt->fetchColumn();

        $pdo->prepare('INSERT INTO rate_limits (rl_key, created_at) VALUES (:k, :now)')
            ->execute([':k' => $key, ':now' => date('Y-m-d H:i:s', $now)]);

        return $count >= $max;
    }
}
