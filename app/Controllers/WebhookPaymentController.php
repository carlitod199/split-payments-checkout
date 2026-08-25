<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Payment;
use App\Models\PaymentSplit;
use App\Models\PaymentWebhook;
use App\Services\Access\StudentAccessService;
use App\Services\Payments\GatewayFactory;
use App\Support\Http;
use App\Support\Logger;
use Throwable;

final class WebhookPaymentController
{
    public function __construct(private array $config) {}

    /**
     * POST /api/webhook.php  (called by Asaas)
     */
    public function handle(): void
    {
        $gateway = GatewayFactory::make($this->config, 'asaas');
        $rawBody = Http::rawBody();
        $headers = $this->headers();

        // 1. verify authenticity (token configured in the Asaas dashboard)
        if (!$gateway->verifyWebhook($headers, $rawBody)) {
            Logger::log('webhook', 'Invalid token', []);
            Http::json(['ok' => false], 401);
        }

        $payload = json_decode($rawBody, true) ?: [];
        $data = $gateway->handleWebhook($payload);

        // 2. idempotency: unique event id + status (blocks duplicates)
        $eventId = $payload['id']
            ?? (($data['external_id'] ?? 'na') . ':' . ($data['status'] ?? 'na') . ':' . ($data['event'] ?? 'na'));

        $isNew = PaymentWebhook::record('asaas', $data['event'] ?? '', $eventId, $payload);
        if (!$isNew) {
            // already processed: answer 200 so Asaas stops retrying
            Http::json(['ok' => true, 'duplicate' => true]);
        }

        try {
            if (!empty($data['external_id'])) {
                Payment::updateStatus($data['external_id'], $data['status'], $data['net_value'] ?? null);

                if ($data['status'] === 'pago') {
                    $payment = Payment::findByExternalId($data['external_id']);
                    if ($payment) {
                        PaymentSplit::markReceivedByPayment((int) $payment['id']);
                        Logger::log('webhook', 'Payment confirmed', [
                            'internal_id' => $payment['internal_id'],
                        ]);
                        // Grant course access: creates the student, the enrollment and sends the e-mail (idempotent).
                        (new StudentAccessService($this->config))->grant($payment);
                    }
                }
            }
            PaymentWebhook::markProcessed($eventId, 'processado');
            Http::json(['ok' => true]);
        } catch (Throwable $e) {
            PaymentWebhook::markProcessed($eventId, 'erro');
            Logger::log('webhook', 'Processing error', ['msg' => $e->getMessage()]);
            // a 500 makes Asaas retry; idempotency prevents double processing
            Http::json(['ok' => false], 500);
        }
    }

    private function headers(): array
    {
        if (function_exists('getallheaders')) {
            return getallheaders() ?: [];
        }
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $name = str_replace('_', '-', strtolower(substr($k, 5)));
                $headers[$name] = $v;
            }
        }
        return $headers;
    }
}
