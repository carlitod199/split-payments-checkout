<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Transport and session hardening applied by the bootstrap (config/payment.php)
 * to every request that goes through it.
 *
 * Both methods are safe to call more than once and are no-ops on the CLI.
 */
final class Security
{
    /** True when the request reached us over TLS. */
    public static function isHttps(): bool
    {
        if (($_SERVER['HTTPS'] ?? '') !== '' && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
            return true;
        }
        // Behind a terminating proxy. Only trust this when the deployment
        // actually sits behind one; an attacker can forge the header otherwise.
        return strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https';
    }

    /**
     * Response security headers.
     *
     * NOTE ON CSP: a strict Content-Security-Policy is deliberately NOT set
     * here. The checkout, login and student pages all ship inline <style>
     * blocks and inline <script> (window.CHECKOUT, the progress poller), and
     * they pull webfonts from fonts.googleapis.com and an icon font from
     * jsdelivr. A meaningful script-src/style-src policy would therefore need
     * either per-request nonces threaded through every view or 'unsafe-inline',
     * which buys nothing. Shipping a policy that breaks the page would be worse
     * than shipping none, so only frame-ancestors - which is complete and
     * costs nothing - is enforced. Adding nonces is the follow-up work.
     */
    public static function headers(): void
    {
        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: frame-ancestors 'none'");
        header('Referrer-Policy: strict-origin-when-cross-origin');

        // HSTS only over TLS: sending it over plain HTTP is meaningless, and
        // sending it from a host that cannot serve HTTPS locks the site out.
        if (self::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    /**
     * Session cookie hardening. Must run BEFORE session_start(); the bootstrap
     * calls it, and every session_start() in this codebase happens later.
     */
    public static function session(): void
    {
        if (PHP_SAPI === 'cli' || session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $params = session_get_cookie_params();
        session_set_cookie_params([
            'lifetime' => $params['lifetime'],
            'path'     => $params['path'],
            'domain'   => $params['domain'],
            'secure'   => self::isHttps(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        // Refuse session ids the client made up.
        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
    }
}
