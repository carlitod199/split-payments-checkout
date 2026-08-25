<?php
declare(strict_types=1);

namespace App\Services\Payments;

/**
 * The contract every gateway must fulfil. Swapping Asaas for another provider
 * means writing a new class that implements this interface, without touching
 * PaymentService or the controllers.
 *
 * Every method takes and returns provider-neutral arrays.
 */
interface PaymentGatewayInterface
{
    /**
     * Creates (or reuses) the customer record on the gateway side.
     * @return string The gateway's external customer id.
     */
    public function createCustomer(array $customer): string;

    /**
     * Creates a Pix charge.
     * @return array{external_id:string,status:string,pix_qr_code:?string,pix_copy_paste:?string,raw:array}
     */
    public function createPixPayment(array $charge): array;

    /**
     * Creates a credit card charge from a token, so the charge call itself
     * carries no PAN.
     * @return array{external_id:string,status:string,raw:array}
     */
    public function createCreditCardPayment(array $charge): array;

    /**
     * Tokenizes a card. The number and CVV are forwarded once and are NEVER
     * persisted or logged. Returns a reusable token.
     */
    public function createCardToken(array $card, array $holder, string $customerExternalId, ?string $remoteIp): string;

    /**
     * Creates a charge with the split already attached. $charge['method']
     * selects 'pix' or 'cartao' (card).
     * @return array{external_id:string,status:string,pix_qr_code:?string,pix_copy_paste:?string,raw:array}
     */
    public function createSplitPayment(array $charge): array;

    /**
     * Fetches the current status of a charge.
     * @return array{status:string,net_value:?int,raw:array}
     */
    public function getPaymentStatus(string $externalId): array;

    /**
     * Refunds a charge.
     */
    public function refundPayment(string $externalId): array;

    /**
     * Normalises an incoming webhook into the internal format.
     * @return array{event:string,external_id:?string,status:string,net_value:?int,raw:array}
     */
    public function handleWebhook(array $payload): array;

    /**
     * Verifies the authenticity of a webhook (token / signature).
     */
    public function verifyWebhook(array $headers, string $rawBody): bool;

    /** Maps a gateway status onto the standardised internal status. */
    public function mapStatus(string $gatewayStatus): string;
}
