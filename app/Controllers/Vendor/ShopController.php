<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

/**
 * Shop profile + sales report (P4, Stage 3).
 *
 *   GET   /api/v1/vendor/profile          my shop details
 *   PATCH /api/v1/vendor/profile          change shop details
 *   POST  /api/v1/vendor/profile/logo     upload a logo (multipart, field "logo")
 *   GET   /api/v1/vendor/sales?range=7d   sales report (7d | 30d | all)
 */
final class ShopController extends VendorBase
{
    // ================================================================ profile

    public function profile(): void
    {
        $this->ok($this->shapeProfile($this->vendor()));
    }

    public function updateProfile(): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $in = $this->input();
        $err = [];

        $shop = self::str($in['business_name'] ?? '');
        $desc = self::str($in['description'] ?? '');
        $phone = preg_replace('/[\s-]/', '', self::str($in['phone'] ?? ''));
        $location = self::str($in['location'] ?? '');
        $opens = self::str($in['opens_at'] ?? '');
        $closes = self::str($in['closes_at'] ?? '');

        if (mb_strlen($shop) < 2 || mb_strlen($shop) > 120) $err['business_name'] = 'Enter a shop name (2 to 120 characters).';
        if (mb_strlen($desc) > 500) $err['description'] = 'Keep it under 500 characters.';
        if (!preg_match('/^(\+233|0)\d{9}$/', (string) $phone)) $err['phone'] = 'Enter a Ghana phone number, e.g. 024 000 0000.';
        if (mb_strlen($location) < 2 || mb_strlen($location) > 150) $err['location'] = 'Say where buyers can collect from.';

        // Opening hours: both times, or both empty (= open any time)
        $time = '/^([01]\d|2[0-3]):[0-5]\d$/';
        if ($opens === '' xor $closes === '') {
            $err[$opens === '' ? 'opens_at' : 'closes_at'] = 'Fill in both times, or leave both empty.';
        } elseif ($opens !== '' && (!preg_match($time, $opens) || !preg_match($time, $closes))) {
            $err['opens_at'] = 'Use times like 08:00 and 18:00.';
        } elseif ($opens !== '' && $opens === $closes) {
            $err['closes_at'] = 'Closing time must be different from opening time.';
        }
        if ($err) {
            $this->invalid($err);
        }

        $this->db->prepare(
            'UPDATE vendors SET shop_name = ?, description = ?, phone = ?, location = ?, opens_at = ?, closes_at = ? WHERE id = ?'
        )->execute([$shop, $desc, $phone, $location, $opens ?: null, $closes ?: null, $vendor['id']]);

        $this->ok($this->shapeProfile($this->findVendor((int) $vendor['user_id'])));
    }

    public function uploadLogo(): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();

        $f = $_FILES['logo'] ?? null;
        if (!$f || $f['error'] === UPLOAD_ERR_NO_FILE) {
            $this->invalid(['logo' => 'Choose a picture.']);
        }
        $path = $this->saveImage($f, 'logos', 'logo');

        // remove the old logo file, then save the new path
        if (!empty($vendor['logo_path'])) {
            $this->deleteUpload($vendor['logo_path']);
        }
        $this->db->prepare('UPDATE vendors SET logo_path = ? WHERE id = ?')->execute([$path, $vendor['id']]);
        $this->ok($this->shapeProfile($this->findVendor((int) $vendor['user_id'])));
    }

    private function shapeProfile(array $v): array
    {
        return [
            'id'          => (int) $v['id'],
            'shop_name'   => $v['shop_name'],
            'vendor_type' => $v['vendor_type'],
            'description' => $v['description'] ?? '',
            'phone'       => $v['phone'] ?? '',
            'location'    => $v['location'] ?? '',
            'logo'        => $v['logo_path'],
            'opens_at'    => $v['opens_at'] ? substr($v['opens_at'], 0, 5) : '',
            'closes_at'   => $v['closes_at'] ? substr($v['closes_at'], 0, 5) : '',
            'is_open'     => VendorController::isOpen($v['opens_at'], $v['closes_at']),
            'status'      => $v['status'],
        ];
    }

    // ================================================================ sales

    public function sales(): void
    {
        $vendor = $this->vendor();
        $v = (int) $vendor['id'];
        $range = in_array($_GET['range'] ?? '', ['7d', '30d', 'all'], true) ? $_GET['range'] : '7d';
        $days = ['7d' => 7, '30d' => 30, 'all' => 0][$range];    // 0 = all time

        // A sale counts on the day the order was completed (updated_at).
        $since = $days ? "AND o.updated_at >= CURDATE() - INTERVAL " . ($days - 1) . " DAY" : '';
        $prev = $days
            ? "AND o.updated_at >= CURDATE() - INTERVAL " . (2 * $days - 1) . " DAY AND o.updated_at < CURDATE() - INTERVAL " . ($days - 1) . " DAY"
            : 'AND 1 = 0';

        $totals = $this->row(
            "SELECT COALESCE(SUM(CASE WHEN o.status = 'completed' $since THEN o.total END), 0) AS sales,
                    COUNT(CASE WHEN o.status = 'completed' $since THEN 1 END)                 AS completed,
                    COUNT(CASE WHEN o.status = 'cancelled' $since THEN 1 END)                 AS cancelled,
                    COALESCE(SUM(CASE WHEN o.status = 'completed' $prev THEN o.total END), 0) AS previous
               FROM orders o WHERE o.vendor_id = ?", [$v]);

        // sales per day for the chart (last 7 days, or last 30 for "30d" and "all")
        $chartDays = $days === 7 ? 7 : 30;
        $rows = $this->rows(
            "SELECT DATE(o.updated_at) AS day, SUM(o.total) AS total
               FROM orders o
              WHERE o.vendor_id = ? AND o.status = 'completed' AND o.updated_at >= CURDATE() - INTERVAL " . ($chartDays - 1) . " DAY
              GROUP BY DATE(o.updated_at)", [$v]);
        $byDay = array_column($rows, 'total', 'day');
        $series = [];
        for ($i = $chartDays - 1; $i >= 0; $i--) {
            $d = date('Y-m-d', strtotime("-$i day"));
            $series[] = ['date' => $d, 'total' => (float) ($byDay[$d] ?? 0)];
        }

        $best = $this->rows(
            "SELECT oi.item_name AS name, SUM(oi.quantity) AS qty, SUM(oi.quantity * oi.unit_price) AS revenue
               FROM order_items oi JOIN orders o ON o.id = oi.order_id
              WHERE o.vendor_id = ? AND o.status = 'completed' $since
              GROUP BY oi.item_name ORDER BY qty DESC, revenue DESC LIMIT 5", [$v]);

        $busy = $this->row(
            "SELECT HOUR(o.created_at) AS h, COUNT(*) AS n FROM orders o
              WHERE o.vendor_id = ? AND o.status = 'completed' $since
              GROUP BY HOUR(o.created_at) ORDER BY n DESC, h LIMIT 1", [$v]);

        $recent = $this->rows(
            "SELECT o.id, o.updated_at, o.total, o.fulfilment, u.full_name,
                    (SELECT SUM(quantity) FROM order_items WHERE order_id = o.id) AS items
               FROM orders o JOIN users u ON u.id = o.user_id
              WHERE o.vendor_id = ? AND o.status = 'completed'
              ORDER BY o.updated_at DESC LIMIT 10", [$v]);

        $completed = (int) $totals['completed'];
        $this->ok([
            'range'        => $range,
            'sales'        => (float) $totals['sales'],
            'previous'     => $days ? (float) $totals['previous'] : null,   // same length of time just before
            'completed'    => $completed,
            'cancelled'    => (int) $totals['cancelled'],
            'average'      => $completed ? round((float) $totals['sales'] / $completed, 2) : 0,
            'busiest_hour' => $busy ? (int) $busy['h'] : null,
            'by_day'       => $series,
            'best_sellers' => array_map(fn($r) => ['name' => $r['name'], 'qty' => (int) $r['qty'], 'revenue' => (float) $r['revenue']], $best),
            'recent'       => array_map(fn($r) => [
                'id' => (int) $r['id'], 'completed_at' => $r['updated_at'], 'buyer' => self::shortName($r['full_name']),
                'fulfilment' => $r['fulfilment'], 'items' => (int) $r['items'], 'total' => (float) $r['total'],
            ], $recent),
        ]);
    }

    // ================================================================ helpers

    private function row(string $sql, array $args): ?array
    {
        $s = $this->db->prepare($sql);
        $s->execute($args);
        return $s->fetch() ?: null;
    }

    private function rows(string $sql, array $args): array
    {
        $s = $this->db->prepare($sql);
        $s->execute($args);
        return $s->fetchAll();
    }
}
