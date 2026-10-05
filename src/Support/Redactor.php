<?php

namespace Heaventree\SeoMcp\Support;

/**
 * Shared credential redaction, ported from AI SEO MCP's (WordPress)
 * Redactor -- any tool response that surfaces stored settings runs
 * through this at OUR boundary, not trusted to whatever upstream
 * generated it.
 */
class Redactor
{
    public const SENTINEL = '[REDACTED:laravel-seo-mcp]';

    private static array $needles = [
        'password', 'passwd', 'secret', 'salt', 'token', 'nonce',
        'api_key', 'apikey', 'access_key', 'private_key', 'public_key',
        'client_secret', 'auth_key', 'auth_token', 'bearer',
        'license', 'licence', 'consumer_key', 'consumer_secret',
        'webhook_secret', 'session_key', 'credential', 'service_account',
    ];

    public static function looksSensitive(string $key): bool
    {
        if ($key === '') {
            return false;
        }
        $keyLc = strtolower($key);
        foreach (self::$needles as $needle) {
            if (str_contains($keyLc, $needle)) {
                return true;
            }
        }

        return false;
    }

    public static function redact(mixed $value, string $key = ''): mixed
    {
        if (self::looksSensitive($key)) {
            return self::SENTINEL;
        }
        if (is_object($value)) {
            $value = (array) $value;
        }
        if (is_array($value)) {
            $out = [];
            foreach ($value as $k => $v) {
                $out[$k] = self::redact($v, is_string($k) ? $k : '');
            }

            return $out;
        }
        if (is_string($value) && (stripos($value, 'PRIVATE KEY') !== false || stripos($value, 'private_key') !== false)) {
            return self::SENTINEL;
        }

        return $value;
    }
}
