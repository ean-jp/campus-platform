<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Core\Database;
use App\Services\Csrf;
use PDO;

require_once dirname(__DIR__, 2) . '/Core/Session.php';
require_once dirname(__DIR__, 2) . '/Middleware/AuthMiddleware.php';

/**
 * Shared helpers for every vendor controller (P4).
 *
 * Who is a vendor? Someone logged in who has a row in the `vendors` table.
 * We look it up in the database on every request instead of trusting a
 * "role" value, so a buyer can never reach vendor data by changing anything
 * in the browser, and a suspended vendor is locked out straight away.
 */
abstract class VendorBase
{
    protected PDO $db;

    public function __construct()
    {
        $this->db = Database::connection();
    }

    // ------------------------------------------------------------ who is asking

    /** The logged-in user (sends 401 and stops if nobody is logged in). */
    protected function user(): array
    {
        return \AuthMiddleware::requireLogin();
    }

    /** The user's vendor row, or null if they never applied. */
    protected function findVendor(int $userId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT id, user_id, shop_name, vendor_type, location, description, phone, logo_path,
                    opens_at, closes_at, status, created_at
               FROM vendors WHERE user_id = ?'
        );
        $stmt->execute([$userId]);
        return $stmt->fetch() ?: null;
    }

    /** The approved vendor making this request. Stops with 401/403 otherwise. */
    protected function vendor(): array
    {
        $user = $this->user();
        $vendor = $this->findVendor((int) $user['id']);
        if ($vendor === null) {
            $this->fail(403, 'NOT_A_VENDOR', 'You need a vendor account for this. Apply on the "Become a vendor" page.');
        }
        if ($vendor['status'] !== 'approved') {
            $msg = [
                'pending'   => 'Your shop is waiting for admin approval.',
                'rejected'  => 'Your vendor request was not approved.',
                'suspended' => 'Your shop has been suspended. Please contact the admin.',
            ][$vendor['status']] ?? 'Your shop is not active.';
            $this->fail(403, 'VENDOR_' . strtoupper($vendor['status']), $msg);
        }
        return $vendor;
    }

    /** Every POST/PATCH/DELETE must carry the CSRF token. */
    protected function requireCsrf(): void
    {
        if (!Csrf::isValid()) {
            $this->fail(403, 'CSRF', 'Your session has changed. Please refresh the page and try again.');
        }
    }

    // ------------------------------------------------------------ reading input

    /** JSON body (from api.js) or normal form fields. */
    protected function input(): array
    {
        $type = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($type, 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }
        return $_POST;
    }

    /** A whole number from the URL ({id}), or 404. */
    protected function idParam(array $params, string $key = 'id'): int
    {
        $id = $params[$key] ?? '';
        if (!ctype_digit((string) $id) || (int) $id < 1) {
            $this->fail(404, 'NOT_FOUND', 'We could not find that.');
        }
        return (int) $id;
    }

    protected static function str(mixed $v): string
    {
        return is_scalar($v) ? trim((string) $v) : '';
    }

    protected static function bool(mixed $v): bool
    {
        return in_array($v, [true, 1, '1', 'true', 'on', 'yes'], true);
    }

    // ------------------------------------------------------------ replies
    // Shapes agreed in docs/API-GUIDE.md (section 5).

    protected function ok(mixed $data = null, int $status = 200, ?array $meta = null): never
    {
        $body = ['success' => true, 'data' => $data];
        if ($meta !== null) {
            $body['meta'] = $meta;
        }
        $this->send($body, $status);
    }

    protected function fail(int $status, string $code, string $message, array $fields = []): never
    {
        $error = ['code' => $code, 'message' => $message];
        if ($fields) {
            $error['fields'] = $fields;
        }
        $this->send(['success' => false, 'error' => $error], $status);
    }

    /** 422 with one message per bad field, shown under the inputs by UI.fieldErrors(). */
    protected function invalid(array $fields): never
    {
        $this->fail(422, 'VALIDATION', 'Please check the highlighted fields.', $fields);
    }

    private function send(array $body, int $status): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        header('X-Content-Type-Options: nosniff');
        header('Cache-Control: no-store');
        echo json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    // ------------------------------------------------------------ small helpers

    /** "Kofi Owusu" -> "Kofi O." (buyers only see a short name). */
    protected static function shortName(string $full): string
    {
        $parts = preg_split('/\s+/', trim($full)) ?: [];
        if (count($parts) < 2) {
            return $parts[0] ?? 'Buyer';
        }
        return $parts[0] . ' ' . mb_strtoupper(mb_substr(end($parts), 0, 1)) . '.';
    }

    /** Add a row to the notifications table (shown in the bell). */
    protected function notify(int $userId, string $type, string $message, ?string $link = null): void
    {
        $stmt = $this->db->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $type, mb_substr($message, 0, 255), $link]);
    }
}
