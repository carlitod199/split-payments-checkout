<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Money helper. Every calculation runs on integer cents to avoid floating
 * point errors; decimal amounts are only exposed at the boundary.
 *
 * IMPORTANT: the fee computed here is an ESTIMATE, used for display and for
 * the initial database record. The REAL net amount is the netValue field
 * returned by Asaas in the webhook, and the percentage split is computed by
 * Asaas itself over that real net amount.
 */
final class MoneyCalculator
{
    public static function toCents(float $reais): int
    {
        return (int) round($reais * 100);
    }

    public static function toReais(int $cents): float
    {
        return round($cents / 100, 2);
    }

    public static function format(int $cents): string
    {
        return 'R$ ' . number_format($cents / 100, 2, ',', '.');
    }

    /**
     * Estimated gateway fee, in cents.
     */
    public static function estimatedFeeCents(int $grossCents, string $method, array $fees): int
    {
        if ($method === 'pix') {
            return self::toCents($fees['pix_fixed']);
        }
        // card: percentage + fixed amount
        $percent = (int) round($grossCents * ($fees['card_percent'] / 100));
        return $percent + self::toCents($fees['card_fixed']);
    }

    /**
     * Returns the full breakdown, in cents.
     *
     * @return array{gross:int,fee:int,net:int,principal:int,coproducer:int}
     */
    public static function breakdown(
        int $grossCents,
        string $method,
        array $fees,
        float $principalPercent,
        float $coproducerPercent
    ): array {
        $fee = self::estimatedFeeCents($grossCents, $method, $fees);
        $net = max(0, $grossCents - $fee);

        $coproducer = (int) round($net * ($coproducerPercent / 100));
        // the main producer takes the remainder (avoids losing 1 cent to rounding)
        $principal = $net - $coproducer;

        return [
            'gross'      => $grossCents,
            'fee'        => $fee,
            'net'        => $net,
            'principal'  => $principal,
            'coproducer' => $coproducer,
        ];
    }
}
