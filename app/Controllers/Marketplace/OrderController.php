<?php
declare(strict_types=1);

namespace App\Controllers\Marketplace;

use App\Core\Database;
use App\Core\Response;
use App\Services\Shop\CurrentUser;
use App\Services\Shop\Input;
use App\Services\Shop\Support;
use PDO;
use Throwable;

/** The buyer's own orders: history, tracking, and cancelling before the vendor starts preparing. */
final class OrderController
{
    private const STEPS = [
        'received'  => 'Order received',
        'preparing' => 'Being prepared',
        'ready'     => 'Ready',
        'completed' => 'Completed',
    ];

    /** GET /api/v1/orders?page=&per_page=&status= */
    public static function index(): void
    {
        $user = CurrentUser::require();
        $db   = Database::connection();
        [$page, $perPage, $offset] = Input::paging($_GET, 10, 50);

        $where  = 'o.user_id = :uid';
        $params = ['uid' => $user['id']];
        $status = $_GET['status'] ?? '';
        if ($status !== '') {
            if (!in_array($status, ['received', 'preparing', 'ready', 'completed', 'cancelled'], true)) {
                Response::error('Unknown status', 422);
            }
            $where .= ' AND o.status = :st';
            $params['st'] = $status;
        }

        $count = $db->prepare("SELECT COUNT(*) FROM orders o WHERE $where");
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $stmt = $db->prepare(
            "SELECT o.id, o.status, o.total, o.fulfilment, o.created_at, v.id AS vendor_id, v.shop_name,
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = o.id) AS item_count,
                    (SELECT item_name FROM order_items WHERE order_id = o.id ORDER BY id LIMIT 1) AS first_item
             FROM orders o JOIN vendors v ON v.id = o.vendor_id
             WHERE $where ORDER BY o.created_at DESC, o.id DESC LIMIT :lim OFFSET :off"
        );
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();

        $rows = array_map(static fn($r) => [
            'id' => (int)$r['id'], 'status' => $r['status'], 'total' => (float)$r['total'], 'fulfilment' => $r['fulfilment'],
            'created_at' => $r['created_at'], 'item_count' => (int)$r['item_count'], 'summary' => $r['first_item'],
            'vendor' => ['id' => (int)$r['vendor_id'], 'shop_name' => $r['shop_name']],
            'can_cancel' => $r['status'] === 'received',
        ], $stmt->fetchAll());

        Response::success($rows, 200, ['page' => $page, 'per_page' => $perPage, 'total' => $total, 'total_pages' => (int)ceil($total / $perPage)]);
    }

    /** GET /api/v1/orders/{id}: only the buyer's own order (anyone else gets 404). */
    public static function show(array $p): void
    {
        $user = CurrentUser::require();
        Response::success(self::load(Database::connection(), (int)$p['id'], (int)$user['id']));
    }

    /** POST /api/v1/orders/{id}/cancel: allowed only while the status is still "received". Gives the stock back. */
    public static function cancel(array $p): void
    {
        $user = CurrentUser::require();
        $db   = Database::connection();
        $id   = (int)$p['id'];

        $db->beginTransaction();
        try {
            $s = $db->prepare('SELECT id, status, vendor_id FROM orders WHERE id = ? AND user_id = ? FOR UPDATE');
            $s->execute([$id, $user['id']]);
            $o = $s->fetch();
            if (!$o) {
                $db->rollBack();
                Response::error('Order not found', 404);
            }
            if ($o['status'] !== 'received') {
                $db->rollBack();
                Response::error($o['status'] === 'cancelled'
                    ? 'This order is already cancelled'
                    : 'The vendor has already started this order, so it can no longer be cancelled. Please contact the vendor', 409);
            }

            $db->prepare("UPDATE orders SET status = 'cancelled' WHERE id = ?")->execute([$id]);
            $db->prepare('UPDATE items i JOIN order_items oi ON oi.item_id = i.id SET i.stock = i.stock + oi.quantity WHERE oi.order_id = ?')->execute([$id]);

            $owner = $db->prepare('SELECT user_id FROM vendors WHERE id = ?');
            $owner->execute([$o['vendor_id']]);
            $db->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, \'order_cancelled\', ?, \'orders.html\')')
               ->execute([$owner->fetchColumn(), "Order #$id was cancelled by the buyer"]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        }
        Response::success(self::load($db, $id, (int)$user['id']));
    }

    /** One order with lines, vendor and a ready-made timeline for the tracking page. */
    private static function load(PDO $db, int $orderId, int $userId): array
    {
        $s = $db->prepare(
            'SELECT o.*, v.id AS vendor_id, v.shop_name, v.phone AS vendor_phone
             FROM orders o JOIN vendors v ON v.id = o.vendor_id WHERE o.id = ? AND o.user_id = ?'
        );
        $s->execute([$orderId, $userId]);
        $o = $s->fetch();
        if (!$o) {
            Response::error('Order not found', 404);
        }
        $l = $db->prepare('SELECT item_id, item_name, unit_price, quantity FROM order_items WHERE order_id = ? ORDER BY id');
        $l->execute([$orderId]);
        $lines = array_map(static fn($r) => [
            'item_id' => (int)$r['item_id'], 'item_name' => $r['item_name'], 'unit_price' => (float)$r['unit_price'],
            'quantity' => (int)$r['quantity'], 'line_total' => Support::money(Support::cents($r['unit_price']) * (int)$r['quantity']),
        ], $l->fetchAll());

        $timeline = [];
        if ($o['status'] === 'cancelled') {
            $timeline[] = ['status' => 'cancelled', 'label' => 'Cancelled', 'state' => 'current'];
        } else {
            $reached = true;
            foreach (self::STEPS as $key => $label) {
                $isCurrent = $key === $o['status'];
                $timeline[] = ['status' => $key, 'label' => $label, 'state' => $reached ? ($isCurrent ? 'current' : 'done') : 'todo'];
                if ($isCurrent) {
                    $reached = false;
                }
            }
        }

        return [
            'id' => (int)$o['id'], 'status' => $o['status'], 'total' => (float)$o['total'],
            'fulfilment' => $o['fulfilment'], 'delivery_spot' => $o['delivery_spot'], 'contact_phone' => $o['contact_phone'],
            'payment_method' => $o['payment_method'],
            'pickup_code' => $o['status'] === 'cancelled' ? null : $o['pickup_code'],   // shown to the buyer to give the vendor
            'created_at' => $o['created_at'], 'updated_at' => $o['updated_at'],
            'vendor' => ['id' => (int)$o['vendor_id'], 'shop_name' => $o['shop_name'], 'phone' => $o['vendor_phone']],
            'items' => $lines, 'timeline' => $timeline,
            'can_cancel' => $o['status'] === 'received',
        ];
    }
}
