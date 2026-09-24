<?php

/**
 * Small set of global helpers, loaded once via composer's "files" autoload.
 * Kept deliberately tiny — this is the entire "convenience layer" of the framework.
 */

use App\Core\Csrf;
use App\Core\Env;

if (!function_exists('env')) {
    function env(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('config')) {
    /**
     * Dot-notation config reader, e.g. config('mail.host'), config('app.debug').
     * Each config/*.php file is required once and cached for the request.
     */
    function config(string $key, mixed $default = null): mixed
    {
        static $cache = [];

        [$file, $path] = array_pad(explode('.', $key, 2), 2, null);

        if (!isset($cache[$file])) {
            $configPath = dirname(__DIR__, 2) . "/config/{$file}.php";

            if (!is_file($configPath)) {
                return $default;
            }

            $cache[$file] = require $configPath;
        }

        if ($path === null) {
            return $cache[$file];
        }

        $value = $cache[$file];

        foreach (explode('.', $path) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('e')) {
    /** HTML-escape a value for safe output in views. */
    function e(?string $value): string
    {
        return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('csrf_field')) {
    /** Hidden <input> carrying the current CSRF token, for use inside <form> tags. */
    function csrf_field(): string
    {
        return '<input type="hidden" name="csrf_token" value="' . e(Csrf::token()) . '">';
    }
}

if (!function_exists('old')) {
    /** Re-populate a form field after a failed submission (see Controller::withOldInput). */
    function old(string $key, mixed $default = ''): mixed
    {
        return $_SESSION['_old_input'][$key] ?? $default;
    }
}
