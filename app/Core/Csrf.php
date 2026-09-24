<?php

namespace App\Core;

/**
 * Session-backed CSRF token. App::run() verifies this automatically on
 * every POST request before the route is dispatched — controllers don't
 * need to check it themselves. Use csrf_field() in <form> views.
 */
class Csrf
{
    protected const SESSION_KEY = '_csrf_token';

    public static function token(): string
    {
        if (empty($_SESSION[self::SESSION_KEY])) {
            $_SESSION[self::SESSION_KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::SESSION_KEY];
    }

    public static function verify(?string $token): bool
    {
        if (empty($_SESSION[self::SESSION_KEY]) || empty($token)) {
            return false;
        }

        return hash_equals($_SESSION[self::SESSION_KEY], $token);
    }
}
