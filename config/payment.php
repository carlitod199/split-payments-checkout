<?php
declare(strict_types=1);

/**
 * Single application bootstrap.
 * Require this file at the top of every endpoint:
 *   require __DIR__ . '/../../config/payment.php';
 *
 * It registers the simple autoloader, loads the .env file and returns the
 * configuration array.
 */

use App\Support\Env;
use App\Support\Security;

// ----- minimal PSR-4 autoloader (App\ => app/) -----
spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    if (strncmp($class, $prefix, strlen($prefix)) !== 0) {
        return;
    }
    $relative = str_replace('\\', '/', substr($class, strlen($prefix)));
    $file = dirname(__DIR__) . '/app/' . $relative . '.php';
    if (is_file($file)) {
        require $file;
    }
});

// Looks for the .env file in the project root first and, as a fallback,
// inside config/. Some deployments keep the file at config/.env, so both
// locations are supported; the first one found wins.
// Lookup order: <project-root>/.env, then <project-root>/config/.env
foreach ([dirname(__DIR__) . '/.env', __DIR__ . '/.env'] as $envPath) {
    if (is_file($envPath)) {
        Env::load($envPath);
        break;
    }
}

// Harden the session cookie before anything can call session_start(), and
// send the response security headers before any output. Both are no-ops on the
// CLI, so the installer and the dev tools are unaffected.
Security::session();
Security::headers();

return [
    'env'       => Env::get('APP_ENV', 'sandbox'),
    'app_url'   => Env::get('APP_URL', ''),
    'debug'     => Env::bool('APP_DEBUG', false),
    'app_key'   => Env::get('APP_KEY', ''),

    'db' => [
        'host' => Env::get('DB_HOST', '127.0.0.1'),
        'port' => Env::int('DB_PORT', 3306),
        'name' => Env::get('DB_NAME', 'coproducao'),
        'user' => Env::get('DB_USER', 'root'),
        'pass' => Env::get('DB_PASS', ''),
    ],

    'asaas' => [
        'api_key'       => Env::get('ASAAS_API_KEY', ''),
        'base_url'      => rtrim(Env::get('ASAAS_BASE_URL', 'https://sandbox.asaas.com/api/v3'), '/'),
        'webhook_token' => Env::get('ASAAS_WEBHOOK_TOKEN', ''),
    ],

    // platform  = your own account issues the charge (split carries the main
    //             producer + the co-producer)
    // principal = the main producer's account issues the charge (the split
    //             carries the co-producer only)
    'issuer_mode' => Env::get('ISSUER_MODE', 'platform'),

    'fees' => [
        'pix_fixed'    => Env::float('FEE_PIX_FIXED', 1.99),
        'card_percent' => Env::float('FEE_CARD_PERCENT', 2.99),
        'card_fixed'   => Env::float('FEE_CARD_FIXED', 0.49),
    ],

    'admin' => [
        // A bcrypt hash, never a plaintext password. Empty disables the
        // .env login path entirely (the role=admin database login still works).
        'user'      => Env::get('ADMIN_USER', 'admin'),
        'pass_hash' => Env::get('ADMIN_PASS_HASH', ''),
    ],

    'rate_limit' => [
        'max'    => Env::int('RATE_LIMIT_MAX', 10),
        'window' => Env::int('RATE_LIMIT_WINDOW', 60),
    ],

    'mail' => [
        'driver'      => Env::get('MAIL_DRIVER', 'log'),   // log | smtp
        'from'        => Env::get('MAIL_FROM', 'no-reply@localhost'),
        'from_name'   => Env::get('MAIL_FROM_NAME', 'Área de Aulas'),
        'smtp_host'   => Env::get('SMTP_HOST', ''),
        'smtp_port'   => Env::int('SMTP_PORT', 587),
        'smtp_user'   => Env::get('SMTP_USER', ''),
        'smtp_pass'   => Env::get('SMTP_PASS', ''),
        'smtp_secure' => Env::get('SMTP_SECURE', 'tls'),   // tls | ssl | (empty)
        'app_url'     => Env::get('APP_URL', ''),
    ],
];
