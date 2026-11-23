<?php
/**
 * Session: safe session start, 30-minute idle logout, login/logout helpers.
 * Owner: Person 1 (Login & Security)
 *
 * Usage:
 *   require_once __DIR__ . '/../Core/Session.php';
 *   Session::start();
 */
class Session
{
    /** Idle time (seconds) before the user is logged out: 30 minutes. */
    public const IDLE_TIMEOUT = 1800;

    private const USER_KEY = 'user';
    private const ACTIVITY_KEY = 'last_activity';

    /** True if the last start() ended a session because of inactivity. */
    private static bool $expired = false;

    /** Start the session safely. Safe to call many times. */
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }

        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');

        session_set_cookie_params([
            'lifetime' => 0,          // cookie dies when the browser closes
            'path'     => '/',
            'secure'   => $https,     // only sent over HTTPS when available
            'httponly' => true,       // JavaScript cannot read the cookie
            'samesite' => 'Lax',      // basic CSRF protection
        ]);

        session_start();

        // Idle timeout: log out if no activity for IDLE_TIMEOUT seconds.
        $now = time();
        if (isset($_SESSION[self::ACTIVITY_KEY])
            && ($now - $_SESSION[self::ACTIVITY_KEY]) > self::IDLE_TIMEOUT) {
            self::destroy();
            session_start();           // fresh, empty session
            session_regenerate_id(true);
            self::$expired = true;
        }
        $_SESSION[self::ACTIVITY_KEY] = $now;
    }

    /**
     * Log a user in.
     * $user keys: id, name, email, role (buyer|vendor|provider|admin),
     *             account_type (ashesi|guest)
     * The role and account_type must come from the server (database), never
     * from the browser.
     */
    public static function login(array $user): void
    {
        self::start();
        session_regenerate_id(true); // stops session fixation
        $_SESSION[self::USER_KEY] = [
            'id'           => $user['id'],
            'name'         => $user['name'] ?? '',
            'email'        => $user['email'] ?? '',
            'role'         => strtolower($user['role'] ?? 'buyer'),
            'account_type' => strtolower($user['account_type'] ?? 'guest'),
        ];
        $_SESSION[self::ACTIVITY_KEY] = time();
    }

    /** Log out and wipe the session completely. */
    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires'  => time() - 42000,
                'path'     => $p['path'],
                'domain'   => $p['domain'],
                'secure'   => $p['secure'],
                'httponly' => $p['httponly'],
                'samesite' => $p['samesite'] ?? 'Lax',
            ]);
        }
        session_destroy();
    }

    /** The logged-in user array, or null. */
    public static function user(): ?array
    {
        self::start();
        return $_SESSION[self::USER_KEY] ?? null;
    }

    public static function isLoggedIn(): bool
    {
        return self::user() !== null;
    }

    /** True if this request's session was just ended for being idle. */
    public static function wasExpired(): bool
    {
        return self::$expired;
    }
}
