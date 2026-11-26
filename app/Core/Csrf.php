<?php
declare(strict_types=1);

namespace App\Core;

require_once __DIR__ . '/Session.php';

/**
 * CSRF protection for state-changing API calls (POST/PATCH/DELETE).
 *
 * The token lives in the session. The browser gets it from
 * GET /api/v1/auth/csrf and sends it back in the X-CSRF-Token header
 * (or a "csrf_token" field in the body).
 *
 * To switch it off while testing with Postman, put CSRF_ENFORCE=0 in .env.
 */
final class Csrf
{
    private const KEY = 'csrf_token';

    public static function token(): string
    {
        \Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    public static function verify(array $body = []): bool
    {
        if ((string) Env::get('CSRF_ENFORCE', '1') === '0') {
            return true;
        }
        \Session::start();
        $expected = $_SESSION[self::KEY] ?? '';
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($body['csrf_token'] ?? '');
        return $expected !== '' && is_string($sent) && hash_equals($expected, $sent);
    }
}
