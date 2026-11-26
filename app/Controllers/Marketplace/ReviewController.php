<?php
declare(strict_types=1);

namespace App\Controllers\Marketplace;

use App\Core\Database;
use App\Core\Response;
use App\Services\Shop\CurrentUser;
use App\Services\Shop\Input;

/**
 * POST /api/v1/reviews   { item_id, rating (1-5), comment? }
 * Buyers can review an item once after they have a completed order containing it.
 */
final class ReviewController
{
    public static function create(): void
    {
        $user   = CurrentUser::require();
        $body   = Input::body();
        $itemId = Input::int($body, 'item_id');
        $rating = Input::int($body, 'rating', null, 1, 5);
        $comment = Input::text($body, 'comment', null, 500);

        if ($itemId === null) {
            Response::error("'item_id' is required", 422);
        }
        if ($rating === null) {
            Response::error("'rating' must be a whole number between 1 and 5", 422);
        }

        $db = Database::connection();
        $s = $db->prepare(
            "SELECT i.id, v.user_id AS owner
             FROM items i
             JOIN vendors v ON v.id = i.vendor_id
             WHERE i.id = ? AND i.status = 'active' AND v.status = 'approved'"
        );
        $s->execute([$itemId]);
        $item = $s->fetch();
        if (!$item) {
            Response::error('Item not found', 404);
        }
        if ((int)$item['owner'] === (int)$user['id']) {
            Response::error('You cannot review your own listing', 422);
        }

        $bought = $db->prepare(
            "SELECT 1
             FROM orders o
             JOIN order_items oi ON oi.order_id = o.id
             WHERE o.user_id = ? AND o.status = 'completed' AND oi.item_id = ?
             LIMIT 1"
        );
        $bought->execute([$user['id'], $itemId]);
        if (!$bought->fetchColumn()) {
            Response::error('You can only review items from a completed order', 422);
        }

        $dup = $db->prepare('SELECT 1 FROM reviews WHERE item_id = ? AND user_id = ?');
        $dup->execute([$itemId, $user['id']]);
        if ($dup->fetchColumn()) {
            Response::error('You have already reviewed this item', 409);
        }

        $db->prepare('INSERT INTO reviews (item_id, user_id, rating, comment) VALUES (?, ?, ?, ?)')
           ->execute([$itemId, $user['id'], $rating, $comment]);
        $reviewId = (int)$db->lastInsertId();

        $agg = $db->prepare('SELECT ROUND(AVG(rating), 1) AS avg, COUNT(*) AS n FROM reviews WHERE item_id = ?');
        $agg->execute([$itemId]);
        $a = $agg->fetch();

        Response::success([
            'id'     => $reviewId,
            'rating' => [
                'avg'   => $a['avg'] !== null ? (float)$a['avg'] : null,
                'count' => (int)$a['n'],
            ],
            'message' => 'Thanks for your review!',
        ], 201);
    }
}
