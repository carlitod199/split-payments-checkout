<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Support\Logger;
use App\Support\MoneyCalculator;
use RuntimeException;

/**
 * Asaas gateway implementation (API v3).
 *
 * Per the split documentation, percentualValue is applied to the NET amount
 * and whatever is left over stays with the account that issued the charge.
 * The issuer's own wallet must therefore never be listed in the split.
 */
final class AsaasGateway implements PaymentGatewayInterface
{
    public function __construct(
        private string $apiKey,
        private string $baseUrl,
        private string $webhookToken
    ) {}

    // ---------- Customer ----------

    public function createCustomer(array $customer): string
    {
        $resp = $this->request('POST', '/customers', [
            'name'     => $customer['name'],
            'email'    => $customer['email'],
            'mobilePhone' => $customer['phone'] ?? null,
            'cpfCnpj'  => $customer['cpf_cnpj'],
        ]);
        return (string) $resp['id'];
    }

    // ---------- Charges ----------

    public function createPixPayment(array $charge): array
    {
        $charge['method'] = 'pix';
        return $this->createSplitPayment($charge);
    }

    public function createCreditCardPayment(array $charge): array
    {
        $charge['method'] = 'cartao';
        return $this->createSplitPayment($charge);
    }

    public function createCardToken(array $card, array $holder, string $customerExternalId, ?string $remoteIp): string
    {
        // Asaas tokenization endpoint. The PAN/CVV are forwarded here once and are never stored.
        $resp = $this->request('POST', '/creditCard/tokenizeCreditCard', [
            'customer'   => $customerExternalId,
            'creditCard' => [
                'holderName'  => $card['holder_name'],
                'number'      => $card['number'],
                'expiryMonth' => $card['expiry_month'],
                'expiryYear'  => $card['expiry_year'],
                'ccv'         => $card['ccv'],
            ],
            'creditCardHolderInfo' => [
                'name'         => $holder['name'],
                'email'        => $holder['email'],
                'cpfCnpj'      => $holder['cpf_cnpj'],
                'postalCode'   => $holder['postal_code'] ?? '00000000',
                'addressNumber'=> $holder['address_number'] ?? '0',
                'phone'        => $holder['phone'] ?? null,
            ],
            'remoteIp' => $remoteIp,
        ]);
        return (string) $resp['creditCardToken'];
    }

    /**
     * Creates the charge (Pix or card) with the split array already attached.
     */
    public function createSplitPayment(array $charge): array
    {
        $isPix = ($charge['method'] ?? 'pix') === 'pix';

        $body = [
            'customer'          => $charge['customer_external_id'],
            'billingType'       => $isPix ? 'PIX' : 'CREDIT_CARD',
            'value'             => MoneyCalculator::toReais($charge['gross_cents']),
            'dueDate'           => $charge['due_date'] ?? date('Y-m-d'),
            'description'       => $charge['description'] ?? '',
            'externalReference' => $charge['internal_id'],          // logical idempotency key
        ];

        // split: a list of [{walletId, percentualValue}, ...]
        if (!empty($charge['splits'])) {
            $body['split'] = array_map(static fn (array $s) => [
                'walletId'        => $s['wallet_id'],
                'percentualValue' => $s['percentual'],
            ], $charge['splits']);
        }

        if (!$isPix) {
            // Card: use the token produced by the tokenization step, so the charge itself carries no PAN.
            $body['creditCardToken'] = $charge['card_token'];
            $body['remoteIp'] = $charge['remote_ip'] ?? null;
            if (!empty($charge['installment_count']) && $charge['installment_count'] > 1) {
                $body['installmentCount'] = (int) $charge['installment_count'];
                $body['totalValue'] = MoneyCalculator::toReais($charge['gross_cents']);
                unset($body['value']);
            }
        }

        $payment = $this->request('POST', '/payments', $body);

        $qr = null;
        $copyPaste = null;
        if ($isPix) {
            $pix = $this->request('GET', "/payments/{$payment['id']}/pixQrCode");
            $qr = $pix['encodedImage'] ?? null;        // base64-encoded PNG
            $copyPaste = $pix['payload'] ?? null;      // copy-and-paste payload
        }

        return [
            'external_id'    => (string) $payment['id'],
            'status'         => $this->mapStatus($payment['status'] ?? 'PENDING'),
            'pix_qr_code'    => $qr,
            'pix_copy_paste' => $copyPaste,
            'net_value'      => isset($payment['netValue'])
                ? MoneyCalculator::toCents((float) $payment['netValue'])
                : null,
            'raw'            => $payment,
        ];
    }

    public function getPaymentStatus(string $externalId): array
    {
        $p = $this->request('GET', "/payments/{$externalId}");
        return [
            'status'    => $this->mapStatus($p['status'] ?? ''),
            'net_value' => isset($p['netValue']) ? MoneyCalculator::toCents((float) $p['netValue']) : null,
            'raw'       => $p,
        ];
    }

    public function refundPayment(string $externalId): array
    {
        return $this->request('POST', "/payments/{$externalId}/refund");
    }

    // ---------- Webhook ----------

    public function verifyWebhook(array $headers, string $rawBody): bool
    {
        // Asaas sends the token configured in its dashboard in the 'asaas-access-token' header.
        $headers = array_change_key_case($headers, CASE_LOWER);
        $sent = $headers['asaas-access-token'] ?? '';
        return $this->webhookToken !== '' && hash_equals($this->webhookToken, (string) $sent);
    }

    public function handleWebhook(array $payload): array
    {
        $event = $payload['event'] ?? '';
        $payment = $payload['payment'] ?? [];

        return [
            'event'       => $event,
            'external_id' => $payment['id'] ?? null,
            'internal_id' => $payment['externalReference'] ?? null,
            'status'      => $this->mapStatus($payment['status'] ?? ''),
            'net_value'   => isset($payment['netValue'])
                ? MoneyCalculator::toCents((float) $payment['netValue'])
                : null,
            'raw'         => $payload,
        ];
    }

    public function mapStatus(string $gatewayStatus): string
    {
        return match (strtoupper($gatewayStatus)) {
            'CONFIRMED', 'RECEIVED', 'RECEIVED_IN_CASH' => 'pago',
            'PENDING', 'AWAITING_RISK_ANALYSIS', 'AWAITING_PAYMENT' => 'pendente',
            'REFUNDED', 'REFUND_REQUESTED', 'CHARGEBACK_REQUESTED', 'CHARGEBACK_DISPUTE' => 'estornado',
            'OVERDUE' => 'cancelado',
            'DELETED', 'CANCELLED' => 'cancelado',
            'REFUSED', 'DECLINED' => 'recusado',
            default => 'pendente',
        };
    }

    // ---------- HTTP ----------

    private function request(string $method, string $path, ?array $body = null): array
    {
        $ch = curl_init($this->baseUrl . $path);
        $headers = [
            'Content-Type: application/json',
            'User-Agent: CoproducaoCheckout/1.0',
            'access_token: ' . $this->apiKey,
        ];
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);

        // SSL verification is ALWAYS on. On environments without a CA bundle
        // configured in php.ini (e.g. WAMP on Windows) we fall back to the
        // cacert.pem shipped with the project. In production the server's own
        // curl.cainfo takes precedence.
        if (ini_get('curl.cainfo') === '' && ini_get('openssl.cafile') === '') {
            $caBundle = dirname(__DIR__, 3) . '/config/cacert.pem';
            if (is_file($caBundle)) {
                curl_setopt($ch, CURLOPT_CAINFO, $caBundle);
            }
        }
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body, JSON_UNESCAPED_UNICODE));
        }

        $raw = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            Logger::log('asaas', 'Connection error', ['path' => $path, 'error' => $err]);
            throw new RuntimeException('Could not reach the payment gateway.');
        }

        $data = json_decode($raw, true) ?: [];

        if ($code >= 400) {
            Logger::log('asaas', 'API error', [
                'path' => $path,
                'code' => $code,
                'response' => $data,
                'sent' => $body, // the Logger redacts sensitive fields (creditCard etc.)
            ]);
            $msg = $data['errors'][0]['description'] ?? 'Payment gateway error.';
            throw new RuntimeException($msg);
        }

        return $data;
    }
}
