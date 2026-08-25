<?php
declare(strict_types=1);

namespace App\Services\Payments;

/**
 * Builds the split rule according to the configured issuing model.
 *
 * - platform : YOUR account issues the charge. The split carries the main
 *              producer (85%) plus the co-producer (15%). Whatever is left
 *              over (a future platform fee) stays with you.
 * - principal: the main producer's account issues the charge. The split
 *              carries the co-producer only (15%); the remaining 85% stays
 *              with the issuer automatically. The issuer's own wallet must
 *              NOT be sent in the split.
 */
final class SplitService
{
    public function __construct(private string $issuerMode) {}

    /**
     * @param array $product  requires principal_wallet_id, coproducer_wallet_id,
     *                        principal_percent and coproducer_percent
     * @return array<int,array{wallet_id:string,percentual:float,role:string}>
     */
    public function buildSplits(array $product): array
    {
        $coproducer = [
            'wallet_id'  => $product['coproducer_wallet_id'],
            'percentual' => (float) $product['coproducer_percent'],
            'role'       => 'coprodutor',
        ];

        if ($this->issuerMode === 'principal') {
            // issuer = main producer => only the co-producer goes into the split
            return [$coproducer];
        }

        // platform => both parties go into the split
        return [
            [
                'wallet_id'  => $product['principal_wallet_id'],
                'percentual' => (float) $product['principal_percent'],
                'role'       => 'produtor_principal',
            ],
            $coproducer,
        ];
    }
}
