<?php
declare(strict_types=1);

namespace App\Support;

final class Validator
{
    /** Sanitises a string: trims it and strips HTML tags. */
    public static function clean(?string $v): string
    {
        return trim(strip_tags((string) $v));
    }

    public static function email(?string $v): ?string
    {
        $v = self::clean($v);
        return filter_var($v, FILTER_VALIDATE_EMAIL) ? $v : null;
    }

    /** Keeps digits only (used for tax IDs and phone numbers). */
    public static function digits(?string $v): string
    {
        return preg_replace('/\D+/', '', (string) $v);
    }

    public static function cpfCnpj(?string $v): ?string
    {
        $d = self::digits($v);
        return (strlen($d) === 11 || strlen($d) === 14) ? $d : null;
    }

    public static function phone(?string $v): ?string
    {
        $d = self::digits($v);
        return (strlen($d) >= 10 && strlen($d) <= 11) ? $d : null;
    }

    public static function name(?string $v): ?string
    {
        $v = self::clean($v);
        return mb_strlen($v) >= 3 ? $v : null;
    }
}
