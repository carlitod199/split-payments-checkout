<?php
declare(strict_types=1);

namespace App\Services\Payments;

use RuntimeException;

/**
 * Single creation point for the gateway. To add another provider, write a
 * class that implements PaymentGatewayInterface and register it here.
 */
final class GatewayFactory
{
    public static function make(array $config, string $driver = 'asaas'): PaymentGatewayInterface
    {
        return match ($driver) {
            'asaas' => new AsaasGateway(
                $config['asaas']['api_key'],
                $config['asaas']['base_url'],
                $config['asaas']['webhook_token'],
            ),
            // 'pagarme' => new PagarmeGateway(...),
            // 'mercadopago' => new MercadoPagoGateway(...),
            default => throw new RuntimeException("Unsupported gateway: {$driver}"),
        };
    }
}
