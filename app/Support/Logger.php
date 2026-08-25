<?php
declare(strict_types=1);

namespace App\Support;

/**
 * File-based logger. It NEVER writes card numbers, CVVs or tokens: sensitive
 * fields are redacted before the line is persisted.
 */
final class Logger
{
    private const SENSITIVE = [
        'creditCard', 'number', 'ccv', 'cvv', 'creditCardNumber',
        'creditCardCcv', 'holderName', 'creditCardToken', 'password',
    ];

    public static function log(string $channel, string $message, array $context = []): void
    {
        $dir = dirname(__DIR__, 2) . '/storage/logs';
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }
        $line = sprintf(
            "[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            strtoupper($channel),
            $message,
            $context ? json_encode(self::scrub($context), JSON_UNESCAPED_UNICODE) : ''
        );
        @file_put_contents($dir . '/' . $channel . '.log', $line, FILE_APPEND | LOCK_EX);
    }

    public static function scrub(array $data): array
    {
        foreach ($data as $k => $v) {
            if (is_array($v)) {
                $data[$k] = self::scrub($v);
            } elseif (in_array($k, self::SENSITIVE, true)) {
                $data[$k] = '***';
            }
        }
        return $data;
    }
}
