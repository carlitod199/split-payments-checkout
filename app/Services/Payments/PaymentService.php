<?php
declare(strict_types=1);

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\PaymentSplit;
use App\Support\Logger;
use App\Support\MoneyCalculator;

/**
 * Orchestrates the creation of a payment: customer -> charge -> split ->
 * persistence. It knows nothing about Asaas specifically; it only talks to
 * PaymentGatewayInterface.
 */
final class PaymentService
{
    public function __construct(
        private PaymentGatewayInterface $gateway,
        private SplitService $splitService,
        private array $config
    ) {}

    /**
     * @param array $product   a products row, joined with the wallet ids and percentages
     * @param array $customer  name, email, phone, cpf_cnpj
     * @param array $options   method (pix|cartao), card_token?, installment_count?, remote_ip?, idempotency_key
     */
    public function checkout(array $product, array $customer, array $options): array
    {
        $method = $options['method'] === 'cartao' ? 'cartao' : 'pix';
        $idempotencyKey = $options['idempotency_key'];

        // 1. Idempotency: the same attempt must not create a duplicate charge
        $existing = Payment::findByIdempotency($idempotencyKey);
        if ($existing) {
            Logger::log('payment', 'Idempotency hit: returning the existing payment', [
                'internal_id' => $existing['internal_id'],
            ]);
            return $this->existingResult($existing);
        }

        $grossCents = (int) $product['price_cents'];
        $breakdown = MoneyCalculator::breakdown(
            $grossCents,
            $method,
            $this->config['fees'],
            (float) $product['principal_percent'],
            (float) $product['coproducer_percent']
        );

        $internalId = $this->generateInternalId();

        // 2. create the customer on the gateway
        $customerExternalId = $this->gateway->createCustomer($customer);

        // 2b. card: tokenize now (the PAN/CVV are neither persisted nor logged)
        $cardToken = $options['card_token'] ?? null;
        if ($method === 'cartao' && !$cardToken && !empty($options['card'])) {
            $cardToken = $this->gateway->createCardToken(
                $options['card'],
                [
                    'name'           => $customer['name'],
                    'email'          => $customer['email'],
                    'cpf_cnpj'       => $customer['cpf_cnpj'],
                    'phone'          => $customer['phone'] ?? null,
                    'postal_code'    => $options['card']['postal_code'] ?? null,
                    'address_number' => $options['card']['address_number'] ?? null,
                ],
                $customerExternalId,
                $options['remote_ip'] ?? null
            );
        }

        // 3. record the payment as pending BEFORE calling the gateway
        $paymentId = Payment::create([
            ':internal_id'         => $internalId,
            ':product_id'          => (int) $product['id'],
            ':customer_name'       => $customer['name'],
            ':customer_email'      => $customer['email'],
            ':customer_phone'      => $customer['phone'] ?? null,
            ':customer_doc'        => $customer['cpf_cnpj'],
            ':customer_external_id'=> $customerExternalId,
            ':gross_cents'         => $breakdown['gross'],
            ':fee_cents'           => $breakdown['fee'],
            ':net_cents'           => $breakdown['net'],
            ':method'              => $method,
            ':status'              => 'pendente',
            ':idempotency_key'     => $idempotencyKey,
        ]);

        // 4. build the split and record the forecast
        $splits = $this->splitService->buildSplits($product);
        $this->recordSplitForecast($paymentId, $product, $splits, $breakdown);

        // 5. create the charge with the split attached
        $charge = [
            'method'               => $method,
            'customer_external_id' => $customerExternalId,
            'gross_cents'          => $grossCents,
            'description'          => $product['name'],
            'internal_id'          => $internalId,
            'splits'               => $splits,
            'card_token'           => $cardToken,
            'installment_count'    => $options['installment_count'] ?? 1,
            'remote_ip'            => $options['remote_ip'] ?? null,
            'due_date'             => date('Y-m-d'),
        ];

        $result = $this->gateway->createSplitPayment($charge);

        // 6. store the returned external id and status
        Payment::setExternalId($paymentId, $result['external_id']);
        Payment::updateStatus($result['external_id'], $result['status'], $result['net_value'] ?? null);

        Logger::log('payment', 'Charge created', [
            'internal_id' => $internalId,
            'external_id' => $result['external_id'],
            'method'      => $method,
            'status'      => $result['status'],
        ]);

        return [
            'internal_id'    => $internalId,
            'external_id'    => $result['external_id'],
            'status'         => $result['status'],
            'method'         => $method,
            'pix_qr_code'    => $result['pix_qr_code'] ?? null,
            'pix_copy_paste' => $result['pix_copy_paste'] ?? null,
            'amount'         => MoneyCalculator::format($grossCents),
        ];
    }

    public function syncStatus(string $externalId): array
    {
        $status = $this->gateway->getPaymentStatus($externalId);
        Payment::updateStatus($externalId, $status['status'], $status['net_value'] ?? null);
        if ($status['status'] === 'pago') {
            $p = Payment::findByExternalId($externalId);
            if ($p) {
                PaymentSplit::markReceivedByPayment((int) $p['id']);
            }
        }
        return $status;
    }

    private function recordSplitForecast(int $paymentId, array $product, array $splits, array $breakdown): void
    {
        foreach ($splits as $s) {
            $producerId = $s['role'] === 'produtor_principal'
                ? (int) $product['principal_producer_id']
                : (int) $product['coproducer_producer_id'];
            $expected = $s['role'] === 'produtor_principal'
                ? $breakdown['principal']
                : $breakdown['coproducer'];

            PaymentSplit::create([
                ':payment_id'     => $paymentId,
                ':producer_id'    => $producerId,
                ':role'           => $s['role'],
                ':percentual'     => $s['percentual'],
                ':expected_cents' => $expected,
            ]);
        }
    }

    private function existingResult(array $payment): array
    {
        return [
            'internal_id' => $payment['internal_id'],
            'external_id' => $payment['external_id'],
            'status'      => $payment['status'],
            'method'      => $payment['method'],
            'amount'      => MoneyCalculator::format((int) $payment['gross_cents']),
            'duplicate'   => true,
        ];
    }

    private function generateInternalId(): string
    {
        return 'PMT-' . date('Ymd') . '-' . bin2hex(random_bytes(6));
    }
}
