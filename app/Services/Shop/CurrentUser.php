<?php
declare(strict_types=1);

namespace App\Services\Shop;

use App\Core\Database;
use App\Core\Env;
use App\Core\Response;

/**
 * Who is calling the marketplace API (Person 3's stand-in until Person 1's session helpers land).
 *
 * Contract with Person 1: after a successful login the session holds  $_SESSION['user_id'].
 * When Person 1 ships requireLogin()/current_user(), swap the body of require() to call them;
 * the controllers only use CurrentUser::require().
 *
 * Development only (APP_ENV=development): if there is no session, the header  X-Dev-User: <id>
 * (or DEV_USER_ID in .env) logs you in as that user so endpoints can be tested before real login exists.
 * Seed users: 2 = Ashesi student, 3 = guest.
 */
final class CurrentUser
{
    private static ?array $user = null;

    /** The logged-in user row, or null for a visitor. */
    public static function get(): ?array
    {
        if (self::$user !== null) {
            return self::$user;
        }
        if (session_status() !== PHP_SESSION_ACTIVE) {
            require_once dirname(__DIR__, 2) . '/Core/Session.php';
            \Session::start();
        }
        $id = isset($_SESSION['user']['id'])
            ? (int)$_SESSION['user']['id']
            : (isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0);

        if ($id === 0 && Env::get('APP_ENV') === 'development') {
            $dev = $_SERVER['HTTP_X_DEV_USER'] ?? Env::get('DEV_USER_ID');
            $id = ctype_digit((string)$dev) ? (int)$dev : 0;
        }
        if ($id === 0) {
            return null;
        }
        $stmt = Database::connection()->prepare(
            'SELECT id, full_name, phone, account_type, role, status FROM users WHERE id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return self::$user = ($row ?: null);
    }

    /** 401 if nobody is logged in, 403 if suspended; also checks the CSRF header on writes. */
    public static function require(): array
    {
        $user = self::get();
        if ($user === null) {
            Response::error('Please sign in to continue', 401);
        }
        if ($user['status'] !== 'active') {
            Response::error('This account is suspended', 403);
        }
        self::checkCsrf();
        return $user;
    }

    /** Enforced automatically once Person 1 stores $_SESSION['csrf_token']; skipped until then. */
    private static function checkCsrf(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        if (in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            return;
        }
        $expected = $_SESSION['csrf_token'] ?? '';
        if ($expected === '') {
            return;
        }
        $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!is_string($sent) || !hash_equals($expected, $sent)) {
            Response::error('Invalid security token. Refresh the page and try again', 403);
        }
    }
}
