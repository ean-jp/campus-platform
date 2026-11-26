<?php
declare(strict_types=1);

namespace App\Controllers\Marketplace;

use App\Core\Database;
use App\Core\Response;
use App\Services\Shop\CurrentUser;
use App\Services\Shop\Input;
use App\Services\Shop\Support;

/**
 * Public catalogue endpoints (no login needed): categories, items, one item, one vendor shop.
 * Only ACTIVE items from APPROVED vendors are ever shown to buyers.
 */
final class CatalogController
{
    /** GET /api/v1/categories */
    public static function categories(): void
    {
        $rows = Database::connection()
            ->query('SELECT id, name, slug FROM categories WHERE is_active = 1 ORDER BY sort_order, name')
            ->fetchAll();
        Response::success($rows);
    }

    /**
     * GET /api/v1/items
     * Query: q, category (id or slug), vendor (id), min_price, max_price,
     *        sort (newest|price_asc|price_desc|name), page, per_page (max 50)
     */
    public static function items(): void
    {
        $db = Database::connection();
        $q  = $_GET;

        $where  = ["i.status = 'active'", "v.status = 'approved'", 'c.is_active = 1'];
        $params = [];

        if (($term = Input::text($q, 'q', null, 80)) !== null) {
            // PDO with real prepared statements cannot reuse one :name, so each LIKE gets its own
            $where[] = '(i.name LIKE :q1 ESCAPE \'\\\\\' OR i.description LIKE :q2 ESCAPE \'\\\\\' OR v.shop_name LIKE :q3 ESCAPE \'\\\\\')';
            $like = '%' . Support::likeEscape($term) . '%';
            $params['q1'] = $params['q2'] = $params['q3'] = $like;
        }
        if (isset($q['category']) && $q['category'] !== '') {
            if (ctype_digit((string)$q['category'])) {
                $where[] = 'i.category_id = :cat';
                $params['cat'] = (int)$q['category'];
            } else {
                $where[] = 'c.slug = :cat';
                $params['cat'] = (string)$q['category'];
            }
        }
        if (($vendor = Input::int($q, 'vendor')) !== null) {
            $where[] = 'i.vendor_id = :vendor';
            $params['vendor'] = $vendor;
        }
        if (($min = Input::money($q, 'min_price')) !== null) {
            $where[] = 'i.price >= :minp';
            $params['minp'] = $min;
        }
        if (($max = Input::money($q, 'max_price')) !== null) {
            $where[] = 'i.price <= :maxp';
            $params['maxp'] = $max;
        }

        $order = match ($q['sort'] ?? 'newest') {
            'price_asc'  => 'i.price ASC, i.id ASC',
            'price_desc' => 'i.price DESC, i.id ASC',
            'name'       => 'i.name ASC, i.id ASC',
            'newest'     => 'i.created_at DESC, i.id DESC',
            default      => Response::error("'sort' must be newest, price_asc, price_desc or name", 422),
        };

        [$page, $perPage, $offset] = Input::paging($q);
        $from = ' FROM items i
                  JOIN vendors v    ON v.id = i.vendor_id
                  JOIN categories c ON c.id = i.category_id
                  WHERE ' . implode(' AND ', $where);

        $count = $db->prepare('SELECT COUNT(*)' . $from);
        $count->execute($params);
        $total = (int)$count->fetchColumn();

        $sql = 'SELECT i.id, i.name, i.description, i.price, i.stock, i.created_at,
                       c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                       v.id AS vendor_id, v.shop_name, v.opens_at, v.closes_at,
                       (SELECT file_path FROM item_images im WHERE im.item_id = i.id ORDER BY im.sort_order, im.id LIMIT 1) AS image,
                       (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.item_id = i.id) AS rating_avg,
                       (SELECT COUNT(*) FROM reviews r WHERE r.item_id = i.id) AS rating_count'
             . $from . " ORDER BY $order LIMIT :lim OFFSET :off";
        $stmt = $db->prepare($sql);
        foreach ($params as $k => $v) {
            $stmt->bindValue(':' . $k, $v);
        }
        $stmt->bindValue(':lim', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, \PDO::PARAM_INT);
        $stmt->execute();

        $items = array_map([self::class, 'itemCard'], $stmt->fetchAll());
        Response::success($items, 200, [
            'page' => $page, 'per_page' => $perPage, 'total' => $total,
            'total_pages' => (int)ceil($total / $perPage),
        ]);
    }

    /** GET /api/v1/items/{id}: one item with images, vendor and recent reviews. */
    public static function item(array $p): void
    {
        $db = Database::connection();
        $stmt = $db->prepare(
            "SELECT i.id, i.name, i.description, i.price, i.stock, i.created_at,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                    v.id AS vendor_id, v.shop_name, v.description AS vendor_description, v.phone AS vendor_phone,
                    v.opens_at, v.closes_at
             FROM items i
             JOIN vendors v    ON v.id = i.vendor_id
             JOIN categories c ON c.id = i.category_id
             WHERE i.id = ? AND i.status = 'active' AND v.status = 'approved'"
        );
        $stmt->execute([(int)$p['id']]);
        $row = $stmt->fetch();
        if (!$row) {
            Response::error('Item not found', 404);
        }

        $imgs = $db->prepare('SELECT id, file_path, alt_text FROM item_images WHERE item_id = ? ORDER BY sort_order, id');
        $imgs->execute([$row['id']]);

        $rev = $db->prepare(
            'SELECT r.rating, r.comment, r.created_at, SUBSTRING_INDEX(u.full_name, \' \', 1) AS reviewer
             FROM reviews r JOIN users u ON u.id = r.user_id
             WHERE r.item_id = ? ORDER BY r.created_at DESC LIMIT 10'
        );
        $rev->execute([$row['id']]);
        $reviews = $rev->fetchAll();

        $agg = $db->prepare('SELECT ROUND(AVG(rating), 1) AS avg, COUNT(*) AS n FROM reviews WHERE item_id = ?');
        $agg->execute([$row['id']]);
        $a = $agg->fetch();

        $out = self::itemCard($row + ['image' => null, 'rating_avg' => $a['avg'], 'rating_count' => $a['n']]);
        $out['images']  = array_map(static fn($i) => ['id' => (int)$i['id'], 'path' => $i['file_path'], 'alt' => $i['alt_text']], $imgs->fetchAll());
        $out['image']   = $out['images'][0]['path'] ?? null;
        $out['vendor'] += ['description' => $row['vendor_description'], 'phone' => $row['vendor_phone'],
                           'opens_at' => Support::hhmm($row['opens_at']), 'closes_at' => Support::hhmm($row['closes_at'])];
        $out['reviews'] = array_map(static fn($r) => [
            'rating' => (int)$r['rating'], 'comment' => $r['comment'], 'reviewer' => $r['reviewer'], 'created_at' => $r['created_at'],
        ], $reviews);

        $canReview = false;
        $user = CurrentUser::get();
        if ($user !== null) {
            $chk = $db->prepare(
                "SELECT 1 FROM orders o
                 JOIN order_items oi ON oi.order_id = o.id
                 WHERE o.user_id = ? AND o.status = 'completed' AND oi.item_id = ?
                   AND NOT EXISTS (SELECT 1 FROM reviews r WHERE r.item_id = ? AND r.user_id = ?)
                 LIMIT 1"
            );
            $chk->execute([$user['id'], $row['id'], $row['id'], $user['id']]);
            $canReview = (bool)$chk->fetchColumn();
        }
        $out['can_review'] = $canReview;

        Response::success($out);
    }

    /** GET /api/v1/vendors: approved shops, for the "vendor" filter and a shop directory. */
    public static function vendors(): void
    {
        $rows = Database::connection()->query(
            "SELECT v.id, v.shop_name, v.description, v.logo_path, v.opens_at, v.closes_at,
                    (SELECT COUNT(*) FROM items i WHERE i.vendor_id = v.id AND i.status = 'active') AS item_count
             FROM vendors v WHERE v.status = 'approved' ORDER BY v.shop_name"
        )->fetchAll();
        Response::success(array_map(static fn($v) => [
            'id' => (int)$v['id'], 'shop_name' => $v['shop_name'], 'description' => $v['description'], 'logo' => $v['logo_path'],
            'is_open' => Support::isOpen($v['opens_at'], $v['closes_at']), 'item_count' => (int)$v['item_count'],
        ], $rows));
    }

    /** GET /api/v1/vendors/{id}: a shop page with its active items. */
    public static function vendor(array $p): void
    {
        $db = Database::connection();
        $stmt = $db->prepare("SELECT id, shop_name, description, phone, logo_path, opens_at, closes_at
                              FROM vendors WHERE id = ? AND status = 'approved'");
        $stmt->execute([(int)$p['id']]);
        $v = $stmt->fetch();
        if (!$v) {
            Response::error('Shop not found', 404);
        }

        $items = $db->prepare(
            "SELECT i.id, i.name, i.description, i.price, i.stock, i.created_at,
                    c.id AS category_id, c.name AS category_name, c.slug AS category_slug,
                    :vid AS vendor_id, :shop AS shop_name, :op AS opens_at, :cl AS closes_at,
                    (SELECT file_path FROM item_images im WHERE im.item_id = i.id ORDER BY im.sort_order, im.id LIMIT 1) AS image,
                    (SELECT ROUND(AVG(r.rating), 1) FROM reviews r WHERE r.item_id = i.id) AS rating_avg,
                    (SELECT COUNT(*) FROM reviews r WHERE r.item_id = i.id) AS rating_count
             FROM items i JOIN categories c ON c.id = i.category_id
             WHERE i.vendor_id = :vid2 AND i.status = 'active' AND c.is_active = 1
             ORDER BY c.sort_order, i.name"
        );
        $items->execute(['vid' => $v['id'], 'shop' => $v['shop_name'], 'op' => $v['opens_at'], 'cl' => $v['closes_at'], 'vid2' => $v['id']]);

        Response::success([
            'id'          => (int)$v['id'],
            'shop_name'   => $v['shop_name'],
            'description' => $v['description'],
            'phone'       => $v['phone'],
            'logo'        => $v['logo_path'],
            'opens_at'    => Support::hhmm($v['opens_at']),
            'closes_at'   => Support::hhmm($v['closes_at']),
            'is_open'     => Support::isOpen($v['opens_at'], $v['closes_at']),
            'items'       => array_map([self::class, 'itemCard'], $items->fetchAll()),
        ]);
    }

    /** One item row (with joined category/vendor columns) -> the JSON shape the pages use. */
    private static function itemCard(array $r): array
    {
        return [
            'id'          => (int)$r['id'],
            'name'        => $r['name'],
            'description' => $r['description'],
            'price'       => (float)$r['price'],
            'stock'       => (int)$r['stock'],
            'in_stock'    => (int)$r['stock'] > 0,
            'image'       => $r['image'] ?? null,
            'category'    => ['id' => (int)$r['category_id'], 'name' => $r['category_name'], 'slug' => $r['category_slug']],
            'vendor'      => ['id' => (int)$r['vendor_id'], 'shop_name' => $r['shop_name'],
                              'is_open' => Support::isOpen($r['opens_at'], $r['closes_at'])],
            'rating'      => ['avg' => $r['rating_avg'] !== null ? (float)$r['rating_avg'] : null, 'count' => (int)$r['rating_count']],
            'created_at'  => $r['created_at'],
        ];
    }
}
