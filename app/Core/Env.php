<?php

namespace App\Core;

/**
 * Minimal .env loader — no external dependency.
 * Parses KEY=VALUE lines, strips quotes/comments, and exposes values via
 * getenv()/$_ENV so the global env() helper can read them anywhere.
 */
class Env
{
    protected static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }

        if (!is_file($path)) {
            // Missing .env is not fatal — config/*.php files fall back to defaults.
            self::$loaded = true;
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$name, $value] = explode('=', $line, 2);
            $name = trim($name);
            $value = trim($value);

            if ($name === '') {
                continue;
            }

            // Strip a single pair of matching quotes.
            if (strlen($value) > 1) {
                $first = $value[0];
                $last = $value[strlen($value) - 1];
                if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                    $value = substr($value, 1, -1);
                }
            }

            // Don't clobber real environment variables set outside the app
            // (e.g. by the web server or a container orchestrator).
            if (getenv($name) === false) {
                putenv("{$name}={$value}");
            }

            $_ENV[$name] = $value;
            $_SERVER[$name] = $value;
        }

        self::$loaded = true;
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = getenv($key);

        if ($value === false) {
            return $default;
        }

        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            'null', '(null)' => null,
            'empty', '(empty)' => '',
            default => $value,
        };
    }
}
