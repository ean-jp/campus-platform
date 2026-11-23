<?php
/**
 * AuthMiddleware: guards for the JSON API.
 * Call one at the top of any controller/endpoint that needs protection.
 *
 *   AuthMiddleware::requireLogin();
 *   AuthMiddleware::requireVendor();
 *
 * On failure it sends a JSON error and stops the request.
 */
require_once __DIR__ . '/../Core/Session.php';

class AuthMiddleware
{
    /** Must be logged in (any role). Returns the user array. */
    public static function requireLogin(): array
    {
        $user = Session::user();
        if ($user === null) {
            $msg = Session::wasExpired()
                ? 'Your session expired. Please log in again.'
                : 'Please log in first.';
            self::fail(401, $msg);
        }
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

    public static function requireVendor(): array
    {
        return self::requireRole('vendor', 'Vendor access only.');
    }

    public static function requireProvider(): array
    {
        return self::requireRole('provider', 'Provider access only.');
    }

    public static function requireAdmin(): array
    {
        return self::requireRole('admin', 'Admin access only.');
    }

    private static function requireRole(string $role, string $message): array
    {
        $user = self::requireLogin();
        if (($user['role'] ?? '') !== $role) {
            self::fail(403, $message);
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
