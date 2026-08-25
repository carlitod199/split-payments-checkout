<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Models\Product;
use App\Services\Payments\GatewayFactory;
use App\Services\Payments\PaymentService;
use App\Services\Payments\SplitService;
use App\Support\Csrf;
use App\Support\Http;
use App\Support\Logger;
use App\Support\RateLimiter;
use App\Support\Validator;
use Throwable;

/**
 * Checkout API handlers.
 *
 * Language convention: developer-facing text (exceptions, log lines) is in
 * English, while the strings returned in the JSON `error` / `errors` fields are
 * in Portuguese on purpose - they are rendered verbatim by checkout.js inside
 * the Portuguese checkout page, so they are customer-facing UI copy.
 */
final class CheckoutController
{
    /**
     * How often the checkout page polls the Pix status endpoint, in seconds.
     * Kept in sync with startPolling() in public/assets/checkout.js.
     */
    private const PIX_POLL_INTERVAL_SECONDS = 4;

    public function __construct(private array $config) {}

    /**
     * POST /api/create_payment.php  (JSON)
     */
    public function createPayment(): void
    {
        $body = Http::body();

        // CSRF
        if (!Csrf::check($body['csrf'] ?? null)) {
            Http::json(['ok' => false, 'error' => 'Sessão expirada. Recarregue a página.'], 419);
        }

        // Per-IP rate limit
        $rl = $this->config['rate_limit'];
        if (RateLimiter::tooMany('checkout:' . Http::clientIp(), $rl['max'], $rl['window'])) {
            Http::json(['ok' => false, 'error' => 'Muitas tentativas. Aguarde um instante.'], 429);
        }

        // Server-side validation
        $name  = Validator::name($body['name'] ?? null);
        $email = Validator::email($body['email'] ?? null);
        $phone = Validator::phone($body['phone'] ?? null);
        $doc   = Validator::cpfCnpj($body['document'] ?? null);
        $method = ($body['method'] ?? '') === 'cartao' ? 'cartao' : 'pix';
        $slug  = Validator::clean($body['slug'] ?? '');
        $idempotencyKey = Validator::clean($body['idempotency_key'] ?? '');

        $errors = [];
        if (!$name)  $errors['name'] = 'Informe seu nome completo.';
        if (!$email) $errors['email'] = 'Informe um e-mail válido.';
        if (!$phone) $errors['phone'] = 'Informe um telefone com DDD.';
        if (!$doc)   $errors['document'] = 'Informe um CPF ou CNPJ válido.';
        if ($idempotencyKey === '') $errors['idempotency_key'] = 'Tente novamente.';
        $card = null;
        if ($method === 'cartao') {
            $card = [
                'holder_name'    => Validator::clean($body['card']['holder_name'] ?? ''),
                'number'         => Validator::digits($body['card']['number'] ?? ''),
                'expiry_month'   => Validator::digits($body['card']['expiry_month'] ?? ''),
                'expiry_year'    => Validator::digits($body['card']['expiry_year'] ?? ''),
                'ccv'            => Validator::digits($body['card']['ccv'] ?? ''),
                'postal_code'    => Validator::digits($body['card']['postal_code'] ?? ''),
                'address_number' => Validator::clean($body['card']['address_number'] ?? ''),
            ];
            if (strlen($card['number']) < 13 || strlen($card['ccv']) < 3
                || $card['holder_name'] === '' || strlen($card['expiry_year']) < 4) {
                $errors['card'] = 'Revise os dados do cartão.';
            }
        }
        if ($errors) {
            Http::json(['ok' => false, 'errors' => $errors], 422);
        }

        $product = Product::findBySlug($slug);
        if (!$product) {
            Http::json(['ok' => false, 'error' => 'Produto indisponível.'], 404);
        }

        try {
            $service = new PaymentService(
                GatewayFactory::make($this->config, 'asaas'),
                new SplitService($this->config['issuer_mode']),
                $this->config
            );

            $result = $service->checkout(
                $product,
                [
                    'name'     => $name,
                    'email'    => $email,
                    'phone'    => $phone,
                    'cpf_cnpj' => $doc,
                ],
                [
                    'method'            => $method,
                    'card'              => $card,
                    'installment_count' => (int) ($body['installments'] ?? 1),
                    'remote_ip'         => Http::clientIp(),
                    'idempotency_key'   => $idempotencyKey,
                ]
            );

            Http::json(['ok' => true, 'data' => $result]);
        } catch (Throwable $e) {
            Logger::log('checkout', 'Failed to create the payment', ['msg' => $e->getMessage()]);
            $msg = $this->config['debug'] ? $e->getMessage() : 'Não foi possível concluir o pagamento. Tente novamente.';
            Http::json(['ok' => false, 'error' => $msg], 500);
        }
    }

    /**
     * GET /api/pix_status.php?external_id=...
     *
     * Deliberately unauthenticated. The buyer is not logged in while a Pix QR
     * code is on screen, and the only thing this endpoint reveals is the status
     * of a charge whose gateway id the caller must already know - an opaque,
     * unguessable identifier that is never listed anywhere. Adding an ownership
     * check would mean inventing a session for an anonymous buyer.
     *
     * What it does need is a throttle, because every call costs one outbound
     * request to the gateway. The budget is derived from the configured window
     * rather than reusing RATE_LIMIT_MAX directly: legitimate polling already
     * costs one call every PIX_POLL_INTERVAL_SECONDS, which would exhaust the
     * checkout budget in seconds. The configured allowance is added on top as
     * headroom for retries and a second tab.
     */
    public function pixStatus(string $externalId): void
    {
        $rl = $this->config['rate_limit'];
        $pollBudget = (int) ceil($rl['window'] / self::PIX_POLL_INTERVAL_SECONDS) + $rl['max'];
        if (RateLimiter::tooMany('pixstatus:' . Http::clientIp(), $pollBudget, $rl['window'])) {
            Http::json(['ok' => false, 'error' => 'Muitas consultas. Aguarde um instante.'], 429);
        }

        try {
            $service = new PaymentService(
                GatewayFactory::make($this->config, 'asaas'),
                new SplitService($this->config['issuer_mode']),
                $this->config
            );
            $status = $service->syncStatus($externalId);
            Http::json(['ok' => true, 'status' => $status['status']]);
        } catch (Throwable $e) {
            Http::json(['ok' => false, 'error' => 'Falha ao consultar status.'], 500);
        }
    }
}
