<?php
declare(strict_types=1);

namespace App\Services;

require_once dirname(__DIR__) . '/Core/Session.php';

/**
 * CSRF protection: a secret random token kept in the session.
 *
 *  - Pages get the token from the API (GET /api/v1/vendor/me returns "csrf_token")
 *    and api.js sends it back in the X-CSRF-Token header on every POST/PATCH/DELETE.
 *  - Another website can make the browser send our cookie, but it cannot read our
 *    token, so its forged request fails this check.
 *
 * Written by P4 so the vendor API is protected now. P1 (security) can move this
 * into app/Core and everyone can reuse it.
 */
final class Csrf
{
    private const KEY = 'csrf_token';

    /** The token for this session (created the first time it is needed). */
    public static function token(): string
    {
        \Session::start();
        if (empty($_SESSION[self::KEY])) {
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }
        return $_SESSION[self::KEY];
    }

    /** True if the request carries the right token (header, or a csrf_token form field). */
    public static function isValid(): bool
    {
        \Session::start();
        $expected = $_SESSION[self::KEY] ?? '';
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
        return $expected !== '' && is_string($sent) && hash_equals($expected, $sent);
    }
}
