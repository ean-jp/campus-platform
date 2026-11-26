<?php
declare(strict_types=1);

namespace App\Controllers\Marketplace;

use App\Core\Database;
use App\Core\Response;
use App\Services\Shop\CurrentUser;
use App\Services\Shop\Input;
use App\Services\Shop\Support;
use PDO;

/** The logged-in buyer's cart. Every call is scoped to the caller's own cart. */
final class CartController
{
    /** GET /api/v1/cart */
    public static function show(): void
    {
        $user = CurrentUser::require();
        Response::success(self::build((int)$user['id']));
    }

    /** POST /api/v1/cart/items   { item_id, quantity? }  adds, or raises the quantity if already there */
    public static function add(): void
    {
        $user = CurrentUser::require();
        $body = Input::body();
        $itemId = Input::int($body, 'item_id');
        $qty    = Input::int($body, 'quantity', 1, 1, Support::MAX_QTY);
        if ($itemId === null) {
            Response::error("'item_id' is required", 422);
        }

        $db   = Database::connection();
        $item = self::buyableItem($db, $itemId);

        $cartId = self::cartId($db, (int)$user['id']);
        $cur = $db->prepare('SELECT COALESCE(SUM(quantity), 0) FROM cart_items WHERE cart_id = ? AND item_id = ?');
        $cur->execute([$cartId, $itemId]);
        $newQty = (int)$cur->fetchColumn() + $qty;
        self::checkQty($item, $newQty);

        $db->prepare(
            'INSERT INTO cart_items (cart_id, item_id, quantity) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE quantity = VALUES(quantity)'
        )->execute([$cartId, $itemId, $newQty]);
        $db->prepare('UPDATE carts SET updated_at = NOW() WHERE id = ?')->execute([$cartId]);

        Response::success(self::build((int)$user['id']), 201);
    }

    /** PATCH /api/v1/cart/items/{id}   { quantity }   (id = the cart line's id) */
    public static function update(array $p): void
    {
        $user = CurrentUser::require();
        $qty  = Input::int(Input::body(), 'quantity', null, 1, Support::MAX_QTY);
        if ($qty === null) {
            Response::error("'quantity' is required (use DELETE to remove a line)", 422);
        }
        $db   = Database::connection();
        $line = self::ownLine($db, (int)$user['id'], (int)$p['id']);
        self::checkQty(self::buyableItem($db, (int)$line['item_id']), $qty);

        $db->prepare('UPDATE cart_items SET quantity = ? WHERE id = ?')->execute([$qty, $line['id']]);
        Response::success(self::build((int)$user['id']));
    }

    /** DELETE /api/v1/cart/items/{id} */
    public static function remove(array $p): void
    {
        $user = CurrentUser::require();
        $db   = Database::connection();
        $line = self::ownLine($db, (int)$user['id'], (int)$p['id']);
        $db->prepare('DELETE FROM cart_items WHERE id = ?')->execute([$line['id']]);
        Response::success(self::build((int)$user['id']));
    }

    // ------------------------------------------------------------------ helpers

    /** The cart, grouped by vendor, with server-calculated totals and any problems flagged. */
    public static function build(int $userId): array
    {
        $db   = Database::connection();
        $stmt = $db->prepare(
            "SELECT ci.id AS line_id, ci.quantity, i.id AS item_id, i.name, i.price, i.stock, i.status AS item_status,
                    c.slug AS category_slug, v.id AS vendor_id, v.shop_name, v.status AS vendor_status, v.opens_at, v.closes_at,
                    (SELECT file_path FROM item_images im WHERE im.item_id = i.id ORDER BY im.sort_order, im.id LIMIT 1) AS image
             FROM carts ct
             JOIN cart_items ci ON ci.cart_id = ct.id
             JOIN items i       ON i.id = ci.item_id
             JOIN categories c  ON c.id = i.category_id
             JOIN vendors v     ON v.id = i.vendor_id
             WHERE ct.user_id = ?
             ORDER BY v.shop_name, ci.id"
        );
        $stmt->execute([$userId]);

        $groups = [];
        $totalCents = 0;
        $count = 0;
        $foodOnly = true;
        foreach ($stmt->fetchAll() as $r) {
            $vid = (int)$r['vendor_id'];
            $groups[$vid] ??= [
                'vendor'   => ['id' => $vid, 'shop_name' => $r['shop_name'], 'is_open' => Support::isOpen($r['opens_at'], $r['closes_at'])],
                'items'    => [],
                'subtotal' => 0,
                '_cents'   => 0,
            ];
            $qty = (int)$r['quantity'];
            $line = Support::cents($r['price']) * $qty;
            $problem = null;
            if ($r['item_status'] !== 'active' || $r['vendor_status'] !== 'approved') {
                $problem = 'This item is no longer available';
            } elseif ((int)$r['stock'] < $qty) {
                $problem = (int)$r['stock'] === 0 ? 'Sold out' : 'Only ' . (int)$r['stock'] . ' left';
            }
            $groups[$vid]['items'][] = [
                'id' => (int)$r['line_id'], 'item_id' => (int)$r['item_id'], 'name' => $r['name'],
                'price' => (float)$r['price'], 'quantity' => $qty, 'line_total' => Support::money($line),
                'stock' => (int)$r['stock'], 'image' => $r['image'], 'problem' => $problem,
            ];
            $groups[$vid]['_cents'] += $line;
            $totalCents += $line;
            $count += $qty;
            if (!in_array($r['category_slug'], Support::FOOD_CATEGORY_SLUGS, true)) {
                $foodOnly = false;
            }
        }
        foreach ($groups as &$g) {
            $g['subtotal'] = Support::money($g['_cents']);
            unset($g['_cents']);
        }
        unset($g);

        return [
            'groups'            => array_values($groups),
            'item_count'        => $count,
            'total'             => Support::money($totalCents),
            'delivery_possible' => $count > 0 && $foodOnly,     // delivery is only offered for food
            'has_problems'      => (bool)array_filter($groups, static fn($g) => array_filter($g['items'], static fn($i) => $i['problem'] !== null)),
        ];
    }

    private static function cartId(PDO $db, int $userId): int
    {
        $db->prepare('INSERT IGNORE INTO carts (user_id) VALUES (?)')->execute([$userId]);
        $s = $db->prepare('SELECT id FROM carts WHERE user_id = ?');
        $s->execute([$userId]);
        return (int)$s->fetchColumn();
    }

    /** An item a buyer is allowed to buy right now (active, vendor approved). 404/409 otherwise. */
    private static function buyableItem(PDO $db, int $itemId): array
    {
        $s = $db->prepare(
            "SELECT i.id, i.name, i.stock FROM items i JOIN vendors v ON v.id = i.vendor_id
             WHERE i.id = ? AND i.status = 'active' AND v.status = 'approved'"
        );
        $s->execute([$itemId]);
        $item = $s->fetch();
        if (!$item) {
            Response::error('Item not found', 404);
        }
        return $item;
    }

    private static function checkQty(array $item, int $qty): void
    {
        if ((int)$item['stock'] === 0) {
            Response::error($item['name'] . ' is sold out', 409);
        }
        if ($qty > (int)$item['stock']) {
            Response::error('Only ' . (int)$item['stock'] . ' of ' . $item['name'] . ' left in stock', 409);
        }
        if ($qty > Support::MAX_QTY) {
            Response::error('You can order at most ' . Support::MAX_QTY . ' of one item', 422);
        }
    }

    /** A cart line that belongs to this user, or 404 (never reveals other people's lines). */
    private static function ownLine(PDO $db, int $userId, int $lineId): array
    {
        $s = $db->prepare(
            'SELECT ci.id, ci.item_id FROM cart_items ci JOIN carts ct ON ct.id = ci.cart_id
             WHERE ci.id = ? AND ct.user_id = ?'
        );
        $s->execute([$lineId, $userId]);
        $row = $s->fetch();
        if (!$row) {
            Response::error('Cart item not found', 404);
        }
        return $row;
    }
}
