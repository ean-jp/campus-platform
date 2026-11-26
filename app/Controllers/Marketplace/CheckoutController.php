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

/**
 * POST /api/v1/checkout
 * Turns the caller's cart into ONE ORDER PER VENDOR, inside one database transaction:
 * either every order is created and stock is taken, or nothing changes.
 *
 * Body: { fulfilment: "pickup"|"delivery", delivery_spot?, contact_phone?, payment_method?: "pay_on_delivery" }
 * Prices, totals, account type and the pickup code are all worked out here on the server.
 */
final class CheckoutController
{
    public static function place(): void
    {
        $user = CurrentUser::require();
        $body = Input::body();

        // ---- 1. validate what the buyer chose (no money values are ever read from the request)
        $fulfilment = Input::text($body, 'fulfilment', 'pickup', 10);
        if (!in_array($fulfilment, ['pickup', 'delivery'], true)) {
            Response::error("'fulfilment' must be pickup or delivery", 422);
        }
        $payment = Input::text($body, 'payment_method', 'pay_on_delivery', 30);
        if ($payment !== 'pay_on_delivery') {
            Response::error('Only pay on delivery is available right now', 422);
        }
        $spot = Input::text($body, 'delivery_spot', null, 150);
        if ($fulfilment === 'delivery' && $spot === null) {
            Response::error('Choose where on campus we should deliver', 422);
        }
        if ($fulfilment === 'pickup') {
            $spot = null;
        }
        $phone = Input::text($body, 'contact_phone', null, 20) ?? ($user['phone'] ?: null);
        if ($user['account_type'] === 'guest' && $phone === null) {
            Response::error('Guests must enter a phone number so vendors can reach them', 422);
        }
        if ($phone !== null && !Support::validPhone($phone)) {
            Response::error('Enter a valid phone number, for example 024 123 4567', 422);
        }

        $db = Database::connection();
        $db->beginTransaction();
        try {
            // ---- 2. read the cart, locking the item rows so stock cannot change underneath us
            $stmt = $db->prepare(
                "SELECT ci.id AS line_id, ci.quantity, i.id AS item_id, i.name, i.price, i.stock, i.status AS item_status,
                        c.slug AS category_slug, v.id AS vendor_id, v.shop_name, v.user_id AS vendor_user_id, v.status AS vendor_status
                 FROM carts ct
                 JOIN cart_items ci ON ci.cart_id = ct.id
                 JOIN items i       ON i.id = ci.item_id
                 JOIN categories c  ON c.id = i.category_id
                 JOIN vendors v     ON v.id = i.vendor_id
                 WHERE ct.user_id = ?
                 ORDER BY v.id, i.id
                 FOR UPDATE"
            );
            $stmt->execute([$user['id']]);
            $lines = $stmt->fetchAll();
            if (!$lines) {
                $db->rollBack();
                Response::error('Your cart is empty', 400);
            }

            // ---- 3. group by vendor and check every line
            $byVendor = [];
            foreach ($lines as $l) {
                if ($l['item_status'] !== 'active' || $l['vendor_status'] !== 'approved') {
                    $db->rollBack();
                    Response::error($l['name'] . ' is no longer available. Remove it from your cart to continue', 409);
                }
                if ((int)$l['stock'] < (int)$l['quantity']) {
                    $db->rollBack();
                    Response::error(((int)$l['stock'] === 0 ? $l['name'] . ' is sold out' : 'Only ' . (int)$l['stock'] . ' of ' . $l['name'] . ' left in stock'), 409);
                }
                if ($fulfilment === 'delivery' && !in_array($l['category_slug'], Support::FOOD_CATEGORY_SLUGS, true)) {
                    $db->rollBack();
                    Response::error('Delivery is only available for food. Choose pickup, or remove ' . $l['name'], 422);
                }
                $byVendor[(int)$l['vendor_id']][] = $l;
            }

            // ---- 4. one order per vendor
            $insOrder = $db->prepare(
                'INSERT INTO orders (user_id, vendor_id, status, total, fulfilment, delivery_spot, contact_phone, payment_method, pickup_code)
                 VALUES (?, ?, \'received\', ?, ?, ?, ?, ?, ?)'
            );
            $insLine  = $db->prepare('INSERT INTO order_items (order_id, item_id, item_name, unit_price, quantity) VALUES (?, ?, ?, ?, ?)');
            $takeStock = $db->prepare("UPDATE items SET stock = stock - ? WHERE id = ? AND stock >= ? AND status = 'active'");
            $notify   = $db->prepare('INSERT INTO notifications (user_id, type, message, link) VALUES (?, \'new_order\', ?, ?)');

            $orders = [];
            $grandCents = 0;
            foreach ($byVendor as $vendorId => $vlines) {
                $cents = 0;
                foreach ($vlines as $l) {
                    $cents += Support::cents($l['price']) * (int)$l['quantity'];
                }
                $code = Support::pickupCode();
                $insOrder->execute([$user['id'], $vendorId, number_format($cents / 100, 2, '.', ''), $fulfilment, $spot, $phone, $payment, $code]);
                $orderId = (int)$db->lastInsertId();

                $outLines = [];
                $qtyTotal = 0;
                foreach ($vlines as $l) {
                    $q = (int)$l['quantity'];
                    $insLine->execute([$orderId, $l['item_id'], $l['name'], $l['price'], $q]);
                    $takeStock->execute([$q, $l['item_id'], $q]);
                    if ($takeStock->rowCount() !== 1) {          // someone else bought it first
                        $db->rollBack();
                        Response::error('Sorry, ' . $l['name'] . ' just sold out. Please review your cart', 409);
                    }
                    $outLines[] = ['item_id' => (int)$l['item_id'], 'item_name' => $l['name'],
                                   'unit_price' => (float)$l['price'], 'quantity' => $q,
                                   'line_total' => Support::money(Support::cents($l['price']) * $q)];
                    $qtyTotal += $q;
                }

                $notify->execute([$vlines[0]['vendor_user_id'],
                    sprintf('New order #%d: %d item%s, GH₵ %s', $orderId, $qtyTotal, $qtyTotal === 1 ? '' : 's', number_format($cents / 100, 2)),
                    'orders.html']);

                $orders[] = [
                    'id' => $orderId, 'status' => 'received',
                    'vendor' => ['id' => $vendorId, 'shop_name' => $vlines[0]['shop_name']],
                    'fulfilment' => $fulfilment, 'delivery_spot' => $spot, 'contact_phone' => $phone,
                    'payment_method' => $payment, 'pickup_code' => $code,
                    'total' => Support::money($cents), 'items' => $outLines,
                ];
                $grandCents += $cents;
            }

            // ---- 5. empty the cart and commit
            $db->prepare('DELETE ci FROM cart_items ci JOIN carts ct ON ct.id = ci.cart_id WHERE ct.user_id = ?')->execute([$user['id']]);
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;            // index.php logs it and shows a friendly 500
        }

        Response::success(['orders' => $orders, 'order_count' => count($orders), 'total' => Support::money($grandCents)], 201);
    }
}
