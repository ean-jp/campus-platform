<?php
/**
 * AuthMiddleware: guards for the JSON API.
 * Call one at the top of any controller/endpoint that needs protection.
 *
 *   AuthMiddleware::requireLogin();
 *   AuthMiddleware::requireVendor();
 *
 * On failure it sends a JSON error and stops the request.
 *
 * Every guard re-reads the user from the database, so a suspended account, a
 * newly approved vendor or a linked Ashesi email takes effect on the very next
 * request instead of waiting for the session to end.
 */
require_once __DIR__ . '/../Core/Session.php';

use App\Models\User;

class AuthMiddleware
{
    /**
     * Must be logged in (any role) and still active.
     * Returns the user array with fresh account_type and role.
     */
    public static function requireLogin(): array
    {
        $user = Session::user();
        if ($user === null) {
            $msg = Session::wasExpired()
                ? 'Your session expired. Please log in again.'
                : 'Please log in first.';
            self::fail(401, $msg);
        }

        $row = User::findById((int) $user['id']);
        if ($row === null || $row['status'] !== 'active') {
            Session::destroy();
            self::fail(401, 'Your account is not available. Please log in again.');
        }

        $user['account_type'] = $row['account_type'];
        $user['role']         = User::effectiveRole((int) $row['id'], (string) $row['role']);
        return $user;
    }

    /** Logged in with a verified @ashesi.edu.gh account. */
    public static function requireAshesi(): array
    {
        $user = self::requireLogin();
        if (($user['account_type'] ?? '') !== 'ashesi') {
            self::fail(403, 'This feature is for Ashesi accounts only.');
        }
        return $user;
    }

    /** Owner of a vendor shop that the admin has approved. */
    public static function requireVendor(): array
    {
        $user = self::requireLogin();
        if (!User::isApprovedVendor((int) $user['id'])) {
            self::fail(403, 'Vendor access only.');
        }
        return $user;
    }

    /** Login account of an active resource provider. */
    public static function requireProvider(): array
    {
        $user = self::requireLogin();
        if (!User::isProvider((int) $user['id'])) {
            self::fail(403, 'Provider access only.');
        }
        return $user;
    }

    public static function requireAdmin(): array
    {
        $user = self::requireLogin();
        if (($user['role'] ?? '') !== 'admin') {
            self::fail(403, 'Admin access only.');
        }
        return $user;
    }

    private static function fail(int $status, string $message): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => $message]);
        exit;
    }
}

/*
 * Plain-function shortcuts, so teammates can write requireLogin() exactly as
 * the team plan says, instead of AuthMiddleware::requireLogin().
 */
function requireLogin(): array    { return AuthMiddleware::requireLogin(); }
function requireAshesi(): array   { return AuthMiddleware::requireAshesi(); }
function requireVendor(): array   { return AuthMiddleware::requireVendor(); }
function requireProvider(): array { return AuthMiddleware::requireProvider(); }
function requireAdmin(): array    { return AuthMiddleware::requireAdmin(); }
