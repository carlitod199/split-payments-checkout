<?php
declare(strict_types=1);

/**
 * DEVELOPMENT TOOL - simulates an Asaas webhook against a local environment.
 *
 * Why it exists: Asaas cannot reach http://localhost, so a real webhook never
 * arrives during local development. This script builds a payload shaped exactly
 * like the one Asaas sends and posts it to the local /api/webhook.php endpoint
 * with the correct token, which triggers the access grant (creates the student
 * and the enrollment, and writes the e-mail to storage/logs/mail.log).
 *
 * USAGE (from the project root):
 *   php tools/simulate_webhook.php                 # uses the most recent PENDING payment
 *   php tools/simulate_webhook.php TST-123         # by internal_id
 *   php tools/simulate_webhook.php TST-123 pago    # status: pago (default) | estornado | recusado
 *
 * The status argument uses the internal Portuguese status names, which are the
 * values persisted in the database.
 *
 * Safe by construction: CLI only, never reachable from a browser.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('This tool can only be run from the CLI.');
}

use App\Support\Database;
use App\Models\Payment;

$config = require __DIR__ . '/../config/payment.php';
Database::init($config['db']);

$internalId = $argv[1] ?? null;
$statusWord = strtolower($argv[2] ?? 'pago');

// internal status -> Asaas status
$asaasStatus = match ($statusWord) {
    'pago'      => 'RECEIVED',
    'estornado' => 'REFUNDED',
    'recusado'  => 'REFUSED',
    'cancelado' => 'CANCELLED',
    default     => 'RECEIVED',
};
$event = match ($statusWord) {
    'pago'      => 'PAYMENT_RECEIVED',
    'estornado' => 'PAYMENT_REFUNDED',
    default     => 'PAYMENT_UPDATED',
};

// Locate the payment
if ($internalId !== null) {
    $payment = Payment::findByInternalId($internalId);
} else {
    $row = Database::pdo()->query(
        "SELECT * FROM payments WHERE status='pendente' ORDER BY created_at DESC LIMIT 1"
    )->fetch();
    $payment = $row ?: null;
}

if ($payment === null) {
    fwrite(STDERR, "No payment found. Create one through the checkout first, or pass an internal_id.\n");
    exit(1);
}

if (empty($payment['external_id'])) {
    fwrite(STDERR, "Payment {$payment['internal_id']} has no external_id (the charge was never created on Asaas).\n");
    exit(1);
}

$token = $config['asaas']['webhook_token'] ?? '';
if ($token === '') {
    fwrite(STDERR, "ASAAS_WEBHOOK_TOKEN is not set in .env - the webhook will reject the call with a 401.\n");
    exit(1);
}

$netValue = round(((int) $payment['net_cents']) / 100, 2);
$payload = [
    'event'   => $event,
    'payment' => [
        'id'                => $payment['external_id'],
        'externalReference' => $payment['internal_id'],
        'status'            => $asaasStatus,
        'netValue'          => $netValue,
        'value'             => round(((int) $payment['gross_cents']) / 100, 2),
    ],
];

$url = rtrim((string) $config['app_url'], '/') . '/api/webhook.php';
$body = json_encode($payload, JSON_UNESCAPED_UNICODE);

echo "Sending simulated webhook:\n";
echo "  endpoint : {$url}\n";
echo "  internal : {$payment['internal_id']}  external: {$payment['external_id']}\n";
echo "  event    : {$event}  status: {$asaasStatus}\n\n";

$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => $body,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER     => [
        'Content-Type: application/json',
        'asaas-access-token: ' . $token,
    ],
    CURLOPT_TIMEOUT        => 20,
]);
$resp = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if ($resp === false) {
    fwrite(STDERR, "Request failed: {$err}\n");
    fwrite(STDERR, "Is the web server running, and does APP_URL point at it?\n");
    exit(1);
}

echo "HTTP {$code}\nResponse: {$resp}\n\n";
echo $code === 200
    ? "OK. If the status was 'pago', check the admin panel for the new user and enrollment, and storage/logs/mail.log for the e-mail.\n"
    : "Warning: HTTP 200 was expected. Check the token and the logs under storage/logs/.\n";
