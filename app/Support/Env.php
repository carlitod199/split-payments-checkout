<?php
declare(strict_types=1);

namespace App\Support;

/**
 * Minimal .env reader (no external dependencies). Loads the file once and
 * caches the values in memory.
 */
final class Env
{
    private static array $cache = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        // A missing file must NOT mark the reader as loaded: the bootstrap tries
        // several candidate paths, and giving up on the first miss would leave
        // the cache permanently empty.
        if (self::$loaded || !is_file($path)) {
            return;
        }
        foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
            $line = trim($line);
            if ($line === '' || $line[0] === '#') {
                continue;
            }
            [$key, $value] = array_pad(explode('=', $line, 2), 2, '');
            $key = trim($key);
            // strip the inline comment and the surrounding quotes
            $value = trim(preg_replace('/\s+#.*$/', '', $value));
            $value = trim($value, "\"'");
            self::$cache[$key] = $value;
        }
        self::$loaded = true;
    }

    /**
     * Reads a value. An explicitly configured value is always honoured, even
     * when it is falsy ("0", "", "false") - only a genuinely absent key falls
     * back to $default.
     */
    public static function get(string $key, ?string $default = null): ?string
    {
        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }
        $fromEnv = getenv($key);
        return $fromEnv === false ? $default : $fromEnv;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower($v), ['1', 'true', 'on', 'yes'], true);
    }

    /**
     * A configured "0" is honoured; an empty value is treated as "not set",
     * because an empty string is not a number and silently coercing it to 0
     * would, for example, turn RATE_LIMIT_MAX= into a total lockout.
     */
    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return ($v === null || trim($v) === '') ? $default : (int) $v;
    }

    public static function float(string $key, float $default = 0.0): float
    {
        $v = self::get($key);
        return ($v === null || trim($v) === '') ? $default : (float) $v;
    }
}
