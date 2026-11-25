<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

use App\Services\Csrf;

/**
 * Vendor account (P4).
 *
 *   GET  /api/v1/vendor/me          who is logged in + their shop (or null) + the CSRF token
 *   POST /api/v1/vendor/apply       "Become a vendor" request (status starts as pending)
 *   GET  /api/v1/vendor/stats       numbers for the dashboard
 *   GET  /api/v1/vendor/categories  categories for the product form
 */
final class VendorController extends VendorBase
{
    public function me(): void
    {
        $user = $this->user();
        $vendor = $this->findVendor((int) $user['id']);

        $unread = $this->db->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
        $unread->execute([$user['id']]);

        $waiting = 0;
        if ($vendor && $vendor['status'] === 'approved') {
            $w = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE vendor_id = ? AND status IN ('received','preparing','ready')");
            $w->execute([$vendor['id']]);
            $waiting = (int) $w->fetchColumn();
        }

        $name = trim((string) ($user['name'] ?? ''));
        $parts = preg_split('/\s+/', $name) ?: [];
        $initials = mb_strtoupper(mb_substr($parts[0] ?? '?', 0, 1) . (count($parts) > 1 ? mb_substr(end($parts), 0, 1) : ''));

        $this->ok([
            'user' => [
                'name'         => $name,
                'first_name'   => $parts[0] ?? '',
                'initials'     => $initials,
                'account_type' => $user['account_type'] ?? 'guest',
            ],
            'vendor' => $vendor ? [
                'id'          => (int) $vendor['id'],
                'shop_name'   => $vendor['shop_name'],
                'vendor_type' => $vendor['vendor_type'],
                'location'    => $vendor['location'],
                'status'      => $vendor['status'],          // pending | approved | rejected | suspended
                'opens_at'    => $vendor['opens_at'],
                'closes_at'   => $vendor['closes_at'],
                'is_open'     => self::isOpen($vendor['opens_at'], $vendor['closes_at']),
            ] : null,
            'unread_notifications' => (int) $unread->fetchColumn(),
            'waiting_orders'       => $waiting,
            'csrf_token'           => Csrf::token(),
        ]);
    }

    public function apply(): void
    {
        $user = $this->user();
        $this->requireCsrf();
        if ($this->findVendor((int) $user['id'])) {
            $this->fail(409, 'ALREADY_APPLIED', 'You already have a vendor request. Check its status on your dashboard.');
        }

        $in = $this->input();
        $err = [];
        $shop = self::str($in['business_name'] ?? '');
        $type = self::str($in['vendor_type'] ?? '');
        $phone = preg_replace('/[\s-]/', '', self::str($in['phone'] ?? ''));
        $location = self::str($in['location'] ?? '');
        $desc = self::str($in['description'] ?? '');

        if (mb_strlen($shop) < 2 || mb_strlen($shop) > 120) $err['business_name'] = 'Enter a shop name (2 to 120 characters).';
        if (!in_array($type, ['student', 'off_campus'], true)) $err['vendor_type'] = 'Choose student seller or off-campus business.';
        if (!preg_match('/^(\+233|0)\d{9}$/', (string) $phone)) $err['phone'] = 'Enter a Ghana phone number, e.g. 024 000 0000.';
        if (mb_strlen($location) < 2 || mb_strlen($location) > 150) $err['location'] = 'Say where buyers can collect from.';
        if (mb_strlen($desc) < 20 || mb_strlen($desc) > 500) $err['description'] = 'Tell the admin a bit more (20 to 500 characters).';
        if (!self::bool($in['agree'] ?? false)) $err['agree'] = 'Please agree to the marketplace rules.';
        if ($err) {
            $this->invalid($err);
        }

        $stmt = $this->db->prepare(
            "INSERT INTO vendors (user_id, shop_name, vendor_type, location, description, phone, status)
             VALUES (?, ?, ?, ?, ?, ?, 'pending')"
        );
        $stmt->execute([$user['id'], $shop, $type, $location, $desc, $phone]);
        $vendorId = (int) $this->db->lastInsertId();

        // Let every admin know there is a request to review.
        $admins = $this->db->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($admins as $a) {
            $this->notify((int) $a['id'], 'vendor_request', "New vendor request: $shop", 'admin/vendors.html');
        }

        $this->ok(['id' => $vendorId, 'status' => 'pending'], 201);
    }

    public function stats(): void
    {
        $vendor = $this->vendor();
        $v = (int) $vendor['id'];

        $q = function (string $sql, array $args = []) {
            $s = $this->db->prepare($sql);
            $s->execute($args);
            return $s;
        };

        $today = $q(
            "SELECT
                COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = CURDATE() THEN total END), 0) AS sales_today,
                COALESCE(SUM(CASE WHEN status = 'completed' AND DATE(updated_at) = CURDATE() - INTERVAL 1 DAY THEN total END), 0) AS sales_yesterday,
                SUM(DATE(created_at) = CURDATE() AND status <> 'cancelled') AS orders_today,
                SUM(status IN ('received','preparing','ready')) AS waiting
               FROM orders WHERE vendor_id = ?", [$v])->fetch();

        $items = $q(
            "SELECT SUM(status IN ('active','hidden')) AS listed, SUM(status = 'active' AND stock = 0) AS sold_out
               FROM items WHERE vendor_id = ?", [$v])->fetch();

        $low = $q(
            "SELECT i.id, i.name, i.stock, c.name AS category FROM items i JOIN categories c ON c.id = i.category_id
              WHERE i.vendor_id = ? AND i.status = 'active' AND i.stock <= 5
              ORDER BY i.stock, i.name LIMIT 5", [$v])->fetchAll();

        $rating = $q(
            'SELECT ROUND(AVG(r.rating), 1) AS avg_rating, COUNT(*) AS reviews
               FROM reviews r JOIN items i ON i.id = r.item_id WHERE i.vendor_id = ?', [$v])->fetch();

        $this->ok([
            'today' => [
                'sales'           => (float) $today['sales_today'],
                'sales_yesterday' => (float) $today['sales_yesterday'],
                'orders'          => (int) $today['orders_today'],
                'waiting'         => (int) $today['waiting'],
            ],
            'products' => [
                'listed'   => (int) $items['listed'],
                'sold_out' => (int) $items['sold_out'],
            ],
            'rating' => [
                'average' => $rating['avg_rating'] !== null ? (float) $rating['avg_rating'] : null,
                'reviews' => (int) $rating['reviews'],
            ],
            'low_stock' => array_map(fn($r) => [
                'id' => (int) $r['id'], 'name' => $r['name'], 'stock' => (int) $r['stock'], 'category' => $r['category'],
            ], $low),
        ]);
    }

    public function categories(): void
    {
        $this->user();
        $rows = $this->db->query('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')->fetchAll();
        $this->ok(array_map(fn($r) => ['id' => (int) $r['id'], 'name' => $r['name'], 'slug' => $r['slug']], $rows));
    }

    /** Open now? Uses the shop's opening and closing time (null = always open). */
    private static function isOpen(?string $opens, ?string $closes): bool
    {
        if (!$opens || !$closes) {
            return true;
        }
        $now = date('H:i:s');
        return $opens <= $closes ? ($now >= $opens && $now < $closes) : ($now >= $opens || $now < $closes);
    }
}
