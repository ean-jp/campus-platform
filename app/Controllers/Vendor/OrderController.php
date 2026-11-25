<?php
declare(strict_types=1);

namespace App\Controllers\Vendor;

use Throwable;

/**
 * Vendor orders (P4).
 *
 *   GET   /api/v1/vendor/orders          my orders (?status=active|received|preparing|ready|completed|cancelled|all)
 *   PATCH /api/v1/vendor/orders/{id}     move an order on: { "status": "...", "pickup_code": "1234" }
 *
 * The order steps (schema: orders.status):
 *   received  -> preparing -> ready -> completed
 *   received / preparing / ready -> cancelled   (stock goes back on sale)
 * "completed" needs the buyer's 4-digit pickup code, so only the real buyer can collect.
 */
final class OrderController extends VendorBase
{
    private const NEXT = [
        'received'  => ['preparing', 'cancelled'],
        'preparing' => ['ready', 'cancelled'],
        'ready'     => ['completed', 'cancelled'],
    ];

    private const BUYER_MESSAGE = [
        'preparing' => 'is being prepared',
        'ready'     => 'is ready',
        'completed' => 'is complete. Thank you',
        'cancelled' => 'was cancelled by the vendor',
    ];

    public function index(): void
    {
        $vendor = $this->vendor();
        $filters = [
            'active'    => "o.status IN ('received','preparing','ready')",
            'received'  => "o.status = 'received'",
            'preparing' => "o.status = 'preparing'",
            'ready'     => "o.status = 'ready'",
            'completed' => "o.status = 'completed'",
            'cancelled' => "o.status = 'cancelled'",
            'all'       => '1 = 1',
        ];
        $status = $_GET['status'] ?? 'active';
        $cond = $filters[$status] ?? $filters['active'];

        $stmt = $this->db->prepare(
            "SELECT o.id, o.user_id, o.status, o.total, o.fulfilment, o.delivery_spot, o.contact_phone,
                    o.payment_method, o.created_at, o.updated_at, u.full_name, u.account_type
               FROM orders o JOIN users u ON u.id = o.user_id
              WHERE o.vendor_id = ? AND $cond
              ORDER BY (o.status IN ('received','preparing','ready')) DESC, o.created_at DESC
              LIMIT 100"
        );
        $stmt->execute([$vendor['id']]);
        $orders = $stmt->fetchAll();

        $counts = $this->db->prepare('SELECT status, COUNT(*) AS n FROM orders WHERE vendor_id = ? GROUP BY status');
        $counts->execute([$vendor['id']]);
        $c = array_fill_keys(['received', 'preparing', 'ready', 'completed', 'cancelled'], 0);
        foreach ($counts->fetchAll() as $row) {
            $c[$row['status']] = (int) $row['n'];
        }
        $c['active'] = $c['received'] + $c['preparing'] + $c['ready'];

        $this->ok($this->shapeAll($orders), 200, ['counts' => $c]);
    }

    public function update(array $p): void
    {
        $vendor = $this->vendor();
        $this->requireCsrf();
        $id = $this->idParam($p);
        $in = $this->input();
        $to = self::str($in['status'] ?? '');

        $this->db->beginTransaction();
        try {
            // Lock this order row so two clicks (or two staff) can't change it at the same time.
            $stmt = $this->db->prepare(
                'SELECT id, user_id, status, pickup_code FROM orders WHERE id = ? AND vendor_id = ? FOR UPDATE'
            );
            $stmt->execute([$id, $vendor['id']]);
            $order = $stmt->fetch();
            if (!$order) {
                $this->db->rollBack();
                $this->fail(404, 'NOT_FOUND', 'That order was not found in your shop.');
            }

            $allowed = self::NEXT[$order['status']] ?? [];
            if (!in_array($to, $allowed, true)) {
                $this->db->rollBack();
                $now = $order['status'];
                $this->fail(409, 'BAD_STEP', ($now === $to || in_array($now, ['completed', 'cancelled'], true))
                    ? "This order is already $now. Refresh to see its latest status."
                    : "An order that is \"$now\" can't be moved to \"$to\". Refresh to see its latest status.");
            }

            if ($to === 'completed') {
                $code = self::str($in['pickup_code'] ?? '');
                if (!preg_match('/^\d{4}$/', $code) || !hash_equals((string) $order['pickup_code'], $code)) {
                    $this->db->rollBack();
                    $this->invalid(['pickup_code' => 'That code does not match. Ask the buyer for the 4-digit code on their order.']);
                }
            }

            $this->db->prepare('UPDATE orders SET status = ? WHERE id = ? AND vendor_id = ?')
                     ->execute([$to, $id, $vendor['id']]);

            if ($to === 'cancelled') {
                // Put the stock back on sale.
                $this->db->prepare(
                    'UPDATE items i JOIN order_items oi ON oi.item_id = i.id
                        SET i.stock = i.stock + oi.quantity
                      WHERE oi.order_id = ?'
                )->execute([$id]);
            }

            $this->notify((int) $order['user_id'], 'order_status',
                "Your order #$id from {$vendor['shop_name']} " . self::BUYER_MESSAGE[$to] . '.',
                'orders.html?id=' . $id);

            $this->db->commit();
        } catch (Throwable $e) {
            if ($this->db->inTransaction()) {
                $this->db->rollBack();
            }
            throw $e;   // the router logs it and answers "Something went wrong"
        }

        $stmt = $this->db->prepare(
            'SELECT o.id, o.user_id, o.status, o.total, o.fulfilment, o.delivery_spot, o.contact_phone,
                    o.payment_method, o.created_at, o.updated_at, u.full_name, u.account_type
               FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?'
        );
        $stmt->execute([$id]);
        $this->ok($this->shapeAll($stmt->fetchAll())[0]);
    }

    /** Orders + their items, in the shape of assets/mock/vendor/orders.json. */
    private function shapeAll(array $orders): array
    {
        if (!$orders) {
            return [];
        }
        $ids = array_column($orders, 'id');
        $marks = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->db->prepare(
            "SELECT order_id, item_id, item_name, unit_price, quantity FROM order_items WHERE order_id IN ($marks) ORDER BY id"
        );
        $stmt->execute($ids);
        $items = [];
        foreach ($stmt->fetchAll() as $it) {
            $items[$it['order_id']][] = [
                'item_id'  => (int) $it['item_id'],
                'name'     => $it['item_name'],
                'quantity' => (int) $it['quantity'],
                'price'    => (float) $it['unit_price'],
            ];
        }

        return array_map(function (array $o) use ($items) {
            $isGuest = $o['account_type'] === 'guest';
            return [
                'id'                => (int) $o['id'],
                'status'            => $o['status'],
                'created_at'        => $o['created_at'],
                'updated_at'        => $o['updated_at'],
                'buyer'             => [
                    'name'         => self::shortName($o['full_name']),
                    'account_type' => $o['account_type'],
                    // the phone is only for this vendor's own order, and only while it is open
                    'phone'        => in_array($o['status'], ['completed', 'cancelled'], true) ? null : $o['contact_phone'],
                ],
                'fulfilment'        => $o['fulfilment'],
                'delivery_location' => $o['delivery_spot'],
                'payment_method'    => $o['payment_method'],
                'items'             => $items[$o['id']] ?? [],
                'total'             => (float) $o['total'],
                'next_steps'        => self::NEXT[$o['status']] ?? [],
                'is_guest'          => $isGuest,
            ];
        }, $orders);
    }
}
